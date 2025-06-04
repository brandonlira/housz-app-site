<?php

namespace Drupal\hous_z_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\File\FileUrlGenerator;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * API controller for room listings, availability, and reservations.
 */
class ApiController extends ControllerBase {

  /**
   * The FileUrlGenerator service.
   *
   * @var \Drupal\Core\File\FileUrlGenerator
   */
  protected $fileUrlGenerator;

  /**
   * Constructs a new ApiController object.
   *
   * @param \Drupal\Core\File\FileUrlGenerator $file_url_generator
   *   The file URL generator service.
   */
  public function __construct(FileUrlGenerator $file_url_generator) {
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_url_generator')
    );
  }

  /**
   * GET /api/rooms
   *
   * Returns a list of active rooms (bat_unit) with metadata and bed templates.
   * If checkInDate and checkOutDate are provided as query parameters, they
   * are echoed back in calendarData along with a fixed minStay value.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing an array of rooms.
   */
  public function rooms(Request $request): JsonResponse {
    $checkInDate  = $request->query->get('checkInDate');
    $checkOutDate = $request->query->get('checkOutDate');

    // Minimum number of nights (could be pulled from configuration).
    $minStay = 2;

    // Load all active bat_unit entities.
    $storage = $this->entityTypeManager()->getStorage('bat_unit');
    $units = $storage->loadByProperties(['status' => 1]);

    $payload = [];

    /** @var \Drupal\bat_unit\Entity\Unit $unit */
    foreach ($units as $unit) {
      // Gather bed templates from paragraphs.
      $beds = [];
      foreach ($unit->get('field_beds') as $item) {
        if ($paragraph = $item->entity) {
          $beds[] = [
            'type'     => $paragraph->get('field_bed_type')->value,
            'quantity' => (int) $paragraph->get('field_bed_quantity')->value,
          ];
        }
      }

      // Gather tags (taxonomy terms).
      $tags = [];
      foreach ($unit->get('field_tags')->referencedEntities() as $term) {
        $tags[] = $term->label();
      }

      // Build absolute URL for the cover image, if it exists.
      $image_url = '';
      if ($unit->get('field_cover_image')->entity) {
        $image_item = $unit->get('field_cover_image')->entity;
        $image_url = $this->fileUrlGenerator
          ->generateAbsoluteString($image_item->getFileUri());
      }

      // Optional: fetch a description field (e.g., "body").
      $description = '';
      if ($unit->hasField('body') && $unit->get('body')->value) {
        $description = $unit->get('body')->value;
      }

      $payload[] = [
        'room' => [
          'roomName'      => $unit->label(),
          'description'   => $description,
          'imageUrl'      => $image_url,
          'tags'          => $tags,
          'availableBeds' => $beds,
        ],
        'calendarData' => [
          'checkInDate'  => $checkInDate ?? '',
          'checkOutDate' => $checkOutDate ?? '',
          'minStay'      => $minStay,
        ],
      ];
    }

    return new JsonResponse(['rooms' => $payload]);
  }


  /**
   * GET /api/availability/{unitId}/{bedType}/{start}/{end}
   *
   * Returns daily availability for a specific unit and bed type between
   * start and end dates. Each day is marked "available" if at least one
   * bed of that type is free.
   *
   * @param int    $unitId
   *   The bat_unit entity ID.
   * @param string $bedType
   *   The machine name of the bed type (e.g., "single_bed").
   * @param string $start
   *   The start date in "YYYY-MM-DD" format.
   * @param string $end
   *   The end date in "YYYY-MM-DD" format.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing a nested "calendar" structure.
   */
  public function calendar(int $unitId, string $bedType, string $start, string $end): JsonResponse {
    // Validate bedType.
    $allowed_bed_types = ['single_bed', 'double_bed'];
    if (!in_array($bedType, $allowed_bed_types, TRUE)) {
      return new JsonResponse([
        'error' => 'Invalid bedType. Allowed: ' . implode(', ', $allowed_bed_types) . '.',
      ], 400);
    }

    // Convert start and end to DrupalDateTime to validate format.
    try {
      $startDt = new DrupalDateTime($start);
      $endDt   = new DrupalDateTime($end);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'error' => 'Invalid date format. Use YYYY-MM-DD.',
      ], 400);
    }

    // Include the end date in the iteration.
    $endDt->modify('+1 day');

    // Prepare PHP DatePeriod to iterate day by day.
    $interval = new \DateInterval('P1D');
    $period = new \DatePeriod(
      $startDt->getPhpDateTime(),
      $interval,
      $endDt->getPhpDateTime()
    );

    // Query all "availability_daily" events that overlap [start .. end]
    // for this unit and bedType.
    $event_storage = \Drupal::entityTypeManager()->getStorage('bat_event');
    $query = $event_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'availability_daily')
      ->condition('event_bat_unit_reference', $unitId)
      ->condition('field_bed_type', $bedType)
      ->condition('event_dates.value', $end, '<=')
      ->condition('event_dates.end_value', $start, '>=');
    $event_ids = $query->execute();

    // Count how many beds are occupied on each date.
    $occupiedCounts = [];
    if (!empty($event_ids)) {
      $events = $event_storage->loadMultiple($event_ids);
      foreach ($events as $ev) {
        try {
          $evStart = new DrupalDateTime($ev->get('event_dates')->value);
          $evEnd   = new DrupalDateTime($ev->get('event_dates')->end_value);
        }
        catch (\Exception $e) {
          continue;
        }
        $evEnd->modify('+1 day');
        $innerPeriod = new \DatePeriod(
          $evStart->getPhpDateTime(),
          $interval,
          $evEnd->getPhpDateTime()
        );
        foreach ($innerPeriod as $dayObj) {
          $isoKey = $dayObj->format('Y-m-d');
          $occupiedCounts[$isoKey] = ($occupiedCounts[$isoKey] ?? 0) + 1;
        }
      }
    }

    // Load the unit to determine total beds of this type.
    $unit = $this->entityTypeManager()->getStorage('bat_unit')->load($unitId);
    if (!$unit) {
      return new JsonResponse([
        'error' => "Unit ID {$unitId} not found.",
      ], 404);
    }
    $totalBedsOfType = 0;
    foreach ($unit->get('field_beds') as $item) {
      if ($paragraph = $item->entity) {
        if ($paragraph->get('field_bed_type')->value === $bedType) {
          $totalBedsOfType = (int) $paragraph->get('field_bed_quantity')->value;
          break;
        }
      }
    }

    // Build the calendar payload.
    $payload = ['calendar' => ['years' => []]];

    foreach ($period as $dt) {
      $year  = $dt->format('Y');
      $month = (int) $dt->format('n');
      $day   = (int) $dt->format('j');

      if (!isset($payload['calendar']['years'][$year])) {
        $payload['calendar']['years'][$year] = ['months' => []];
      }
      if (!isset($payload['calendar']['years'][$year]['months'][$month])) {
        $payload['calendar']['years'][$year]['months'][$month] = [
          'name'   => $dt->format('F'),
          'number' => $month,
          'days'   => [],
        ];
      }

      if ($totalBedsOfType <= 0) {
        // No beds of this type → always unavailable.
        $isAvailable = FALSE;
      }
      else {
        $isoKey       = $dt->format('Y-m-d');
        $alreadyCount = $occupiedCounts[$isoKey] ?? 0;
        $isAvailable  = ($alreadyCount < $totalBedsOfType);
      }

      $payload['calendar']['years'][$year]['months'][$month]['days'][] = [
        'day'       => $day,
        'available' => $isAvailable,
      ];
    }

    return new JsonResponse($payload);
  }

  /**
   * POST /api/reservation
   *
   * Creates an 'availability_daily' Event and a corresponding Booking for the
   * given unit. Validates bed‐type availability over the requested date range.
   *
   * Expected JSON keys:
   *   - unitId      (integer):   ID of the bat_unit.
   *   - bedType     (string):    Machine name of bed type (e.g.,"single_bed").
   *   - checkInDate (string):    Check‐in date "YYYY-MM-DD".
   *   - checkOutDate(string):    Check‐out date "YYYY-MM-DD".
   *   - checkInTime (string):    (optional) Check‐in time, e.g., "14:00".
   *   - checkOutTime(string):    (optional) Check‐out time, e.g., "11:00".
   *   - details     (string):    Optional reservation notes.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The HTTP request containing JSON in the body.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON summary of the created reservation, or an error response.
   * @throws \DateMalformedStringException
   */
  public function book(Request $request): JsonResponse {
    $input = json_decode($request->getContent(), TRUE);

    if (
      empty($input['unitId']) ||
      empty($input['bedType']) ||
      empty($input['checkInDate']) ||
      empty($input['checkOutDate'])
    ) {
      return new JsonResponse([
        'error' => 'Required fields: unitId, bedType, checkInDate, checkOutDate.',
      ], 400);
    }

    // Load the unit and verify existence.
    $unit = $this->entityTypeManager()
      ->getStorage('bat_unit')
      ->load((int) $input['unitId']);
    if (!$unit) {
      return new JsonResponse([
        'error' => "Unit ID {$input['unitId']} not found.",
      ], 404);
    }

    // Count total beds of the requested type.
    $desiredBedType = $input['bedType'];
    $totalBedsOfType = 0;
    foreach ($unit->get('field_beds') as $item) {
      if ($paragraph = $item->entity) {
        if ($paragraph->get('field_bed_type')->value === $desiredBedType) {
          $totalBedsOfType = (int) $paragraph->get('field_bed_quantity')->value;
          break;
        }
      }
    }
    if ($totalBedsOfType <= 0) {
      return new JsonResponse([
        'error' => "Unit {$input['unitId']} has no beds of type {$desiredBedType}.",
      ], 400);
    }

    // Parse check‐in and check‐out dates.
    try {
      $checkInDate  = new \DateTime($input['checkInDate']);
      $checkOutDate = new \DateTime($input['checkOutDate']);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'error' => 'Invalid date format. Use YYYY-MM-DD.',
      ], 400);
    }

    // Include the last day in the period.
    $checkOutDate->modify('+1 day');

    // Build a DatePeriod for the requested range.
    $interval = new \DateInterval('P1D');
    $rangePeriod = new \DatePeriod($checkInDate, $interval, $checkOutDate);

    // Query existing "availability_daily" events overlapping this range.
    $event_storage = $this->entityTypeManager()->getStorage('bat_event');
    $query = $event_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'availability_daily')
      ->condition('event_bat_unit_reference', $input['unitId'])
      ->condition('field_bed_type', $desiredBedType)
      ->condition('event_dates.value', $input['checkOutDate'], '<=')
      ->condition('event_dates.end_value', $input['checkInDate'], '>=');
    $existing_ids = $query->execute();

    // Count already‐booked beds per day.
    $occupiedCounts = [];
    if (!empty($existing_ids)) {
      $existingEvents = $event_storage->loadMultiple($existing_ids);
      foreach ($existingEvents as $ev) {
        try {
          $evStart = new \DateTime($ev->get('event_dates')->value);
          $evEnd   = new \DateTime($ev->get('event_dates')->end_value);
        }
        catch (\Exception $ignore) {
          continue;
        }
        $evEnd->modify('+1 day');
        $innerPeriod = new \DatePeriod($evStart, $interval, $evEnd);
        foreach ($innerPeriod as $d) {
          $isoKey = $d->format('Y-m-d');
          $occupiedCounts[$isoKey] = ($occupiedCounts[$isoKey] ?? 0) + 1;
        }
      }
    }

    // Verify availability for each requested day.
    foreach ($rangePeriod as $dt) {
      $isoDay = $dt->format('Y-m-d');
      $alreadyOccupied = $occupiedCounts[$isoDay] ?? 0;
      if ($alreadyOccupied >= $totalBedsOfType) {
        return new JsonResponse([
          'error' => "No “{$desiredBedType}” beds available on {$isoDay}.",
        ], 400);
      }
    }

    // Load “Booked” state term.
    $term_storage = $this->entityTypeManager()->getStorage('state');
    $terms = $term_storage->loadByProperties(['name' => 'Booked']);
    $term = reset($terms);
    if (!$term) {
      return new JsonResponse([
        'error' => 'Could not find the "Booked" state.',
      ], 500);
    }
    $state_id = $term->id();

    // Format dates for storage (ISO 8601).
    $startValue = (new \DateTime($input['checkInDate']))
      ->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
    $endValue   = (new \DateTime($input['checkOutDate']))
      ->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    // Create the "availability_daily" Event.
    $event = $event_storage->create([
      'type'                     => 'availability_daily',
      'event_dates'              => [
        'value'     => $startValue,
        'end_value' => $endValue,
      ],
      'event_bat_unit_reference' => ['target_id' => (int) $input['unitId']],
      'field_bed_type'           => $desiredBedType,
      'event_state_reference'    => ['target_id' => $state_id],
    ]);
    $event->save();

    // Create the 'standard' Booking referencing this event.
    $booking_storage = $this->entityTypeManager()->getStorage('bat_booking');
    $booking = $booking_storage->create([
      'type'                    => 'standard',
      'booking_event_reference' => ['target_id' => $event->id()],
      'uid'                     => ['target_id' => $this->currentUser()->id()],
      'status'                  => 1,
    ]);
    $booking->save();

    // Build response summary.
    $imageUrls = [];
    if (
      $unit->hasField('field_cover_image') &&
      ($imgFile = $unit->get('field_cover_image')->entity)
    ) {
      $imageUrls[] = $this->fileUrlGenerator
        ->generateAbsoluteString($imgFile->getFileUri());
    }

    $summary = [
      'room' => [
        'roomName'     => $unit->label(),
        'bedType'      => $desiredBedType,
        'imageUrls'    => $imageUrls,
        'imageCount'   => count($imageUrls),
        'address'      => $unit->get('field_address')->value,
        'managerEmail' => $unit->get('field_manager_email')->value,
      ],
      'bookingInfo' => [
        'checkIn'  => [
          'date' => $input['checkInDate'],
          'time' => $input['checkInTime']  ?? '',
        ],
        'checkOut' => [
          'date' => $input['checkOutDate'],
          'time' => $input['checkOutTime'] ?? '',
        ],
      ],
      'details' => $input['details'] ?? '',
    ];

    return new JsonResponse($summary, 201);
  }

}
