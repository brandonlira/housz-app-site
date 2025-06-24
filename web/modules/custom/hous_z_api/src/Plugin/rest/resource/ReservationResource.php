<?php

namespace Drupal\hous_z_api\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\File\FileUrlGenerator;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Psr\Log\LoggerInterface;

/**
 * Creates a new reservation (booking) for a given unit and bed type.
 *
 * @RestResource(
 *   id = "reservation_resource",
 *   label = @Translation("Create reservation"),
 *   uri_paths = {
 *     "create" = "/api/reservation"
 *   }
 * )
 */
class ReservationResource extends ResourceBase implements \Drupal\Core\Plugin\ContainerFactoryPluginInterface {

  /** @var \Drupal\Core\File\FileUrlGenerator */
  protected $fileUrlGenerator;

  /**
   * Constructs a ReservationResource.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, FileUrlGenerator $file_url_generator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('hous_z_api'),
      $container->get('file_url_generator')
    );
  }

  /**
   * Responds to POST /api/reservation.
   *
   * Expected JSON body keys:
   *  - unitId (int)
   *  - bedType (string)
   *  - checkInDate (YYYY-MM-DD)
   *  - checkOutDate (YYYY-MM-DD)
   *  - checkInTime (optional)
   *  - checkOutTime (optional)
   *  - details (optional)
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON summary or error.
   */
  public function post() {
    $request = Request::createFromGlobals();
    $input = json_decode($request->getContent(), TRUE);

    // Validate required fields.
    if (empty($input['unitId']) || empty($input['bedType']) || empty($input['checkInDate']) || empty($input['checkOutDate'])) {
      return new JsonResponse(['error' => 'Required: unitId, bedType, checkInDate, checkOutDate'], 400);
    }

    // Load unit.
    $unit = \Drupal::entityTypeManager()->getStorage('bat_unit')->load((int) $input['unitId']);
    if (!$unit) {
      return new JsonResponse(['error' => 'Unit not found'], 404);
    }

    // Determine total beds of type.
    $desired = $input['bedType'];
    $totalBeds = 0;
    foreach ($unit->get('field_beds') as $item) {
      if ($p = $item->entity) {
        if ($p->get('field_bed_type')->value === $desired) {
          $totalBeds = (int) $p->get('field_bed_quantity')->value;
          break;
        }
      }
    }
    if ($totalBeds < 1) {
      return new JsonResponse(['error' => "No beds of type $desired"], 400);
    }

    // Parse dates.
    try {
      $in  = new \DateTime($input['checkInDate']);
      $out = new \DateTime($input['checkOutDate']);
    }
    catch (\Exception $e) {
      return new JsonResponse(['error' => 'Invalid date format'], 400);
    }
    $out->modify('+1 day');
    $interval = new \DateInterval('P1D');
    $range = new \DatePeriod($in, $interval, $out);

    // Fetch existing events for this unit+bedType overlapping the period.
    $storage = \Drupal::entityTypeManager()->getStorage('bat_event');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'availability_daily')
      ->condition('event_bat_unit_reference', (int) $input['unitId'])
      ->condition('field_bed_type', $desired)
      ->condition('event_dates.value', $input['checkOutDate'], '<=')
      ->condition('event_dates.end_value', $input['checkInDate'], '>=');
    $ids = $query->execute();

    // Count occupied beds per day.
    $occupied = [];
    if (!empty($ids)) {
      $events = $storage->loadMultiple($ids);
      foreach ($events as $ev) {
        try {
          $s = new \DateTime($ev->get('event_dates')->value);
          $e = new \DateTime($ev->get('event_dates')->end_value);
        }
        catch (\Exception $ignore) {
          continue;
        }
        $e->modify('+1 day');
        $inner = new \DatePeriod($s, $interval, $e);
        foreach ($inner as $d) {
          $key = $d->format('Y-m-d');
          $occupied[$key] = ($occupied[$key] ?? 0) + 1;
        }
      }
    }

    // Validate each day is available.
    foreach ($range as $d) {
      $key = $d->format('Y-m-d');
      if (($occupied[$key] ?? 0) >= $totalBeds) {
        return new JsonResponse(['error' => "No $desired beds on $key"], 400);
      }
    }

    // Load "Booked" state.
    $term_storage = \Drupal::entityTypeManager()->getStorage('state');
    $terms = $term_storage->loadByProperties(['name' => 'Pending']);
    $term = reset($terms);
    if (!$term) {
      return new JsonResponse(['error' => 'State "Pending" missing'], 500);
    }
    $state_id = $term->id();

    // Format dates for storage.
    $startValue = (new \DateTime($input['checkInDate']))
      ->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
    $endValue   = (new \DateTime($input['checkOutDate']))
      ->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    // Create event.
    $event = $storage->create([
      'type'                     => 'availability_daily',
      'event_dates'              => ['value' => $startValue, 'end_value' => $endValue],
      'event_bat_unit_reference' => ['target_id' => (int) $input['unitId']],
      'field_bed_type'           => $desired,
      'event_state_reference'    => ['target_id' => $state_id],
    ]);
    $event->save();

    // Create booking.
    $booking_storage = \Drupal::entityTypeManager()->getStorage('bat_booking');
    $booking = $booking_storage->create([
      'type'                    => 'standard',
      'booking_event_reference' => ['target_id' => $event->id()],
      'uid'                     => ['target_id' => \Drupal::currentUser()->id()],
      'status'                  => 1,
      'field_event_state'       => ['target_id' => $state_id],
      'booking_start_date'      => ['value' => $startValue],
      'booking_end_date'        => ['value' => $endValue],
    ]);
    $booking->save();

    // Build response summary.
    $images = [];
    if ($img = $unit->get('field_cover_image')->entity) {
      $images[] = $this->fileUrlGenerator->generateAbsoluteString($img->getFileUri());
    }
    $summary = [
      'room' => [
        'roomName'     => $unit->label(),
        'bedType'      => $desired,
        'imageUrls'    => $images,
        'imageCount'   => count($images),
        'address'      => $unit->get('field_address')->value,
        'managerEmail' => $unit->get('field_manager_email')->value,
      ],
      'bookingInfo' => [
        'checkIn'  => ['date' => $input['checkInDate'], 'time' => $input['checkInTime'] ?? ''],
        'checkOut' => ['date' => $input['checkOutDate'], 'time' => $input['checkOutTime'] ?? ''],
      ],
      'details' => $input['details'] ?? '',
    ];

    // Send notification email to manager.
    $to = $unit->get('field_manager_email')->value;
    \Drupal::service('plugin.manager.mail')->mail(
      'hous_z_api',
      'booking_notification',
      $to,
      \Drupal::languageManager()->getDefaultLanguage()->getId(),
      ['summary' => $summary]
    );

    return new JsonResponse($summary, 201);
  }

}
