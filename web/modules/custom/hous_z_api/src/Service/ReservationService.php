<?php

namespace Drupal\hous_z_api\Service;

use Drupal\bat_booking\BookingInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Core\File\FileUrlGenerator;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for reservation/booking management operations.
 */
class ReservationService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGenerator
   */
  protected $fileUrlGenerator;

  /**
   * Constructs a ReservationService object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\File\FileUrlGenerator $file_url_generator
   *   The file URL generator.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    FileUrlGenerator $file_url_generator
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('hous_z_api');
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * Create a new reservation.
   *
   * @param array $data
   *   The reservation data.
   *
   * @return array
   *   Array with 'success' boolean and 'data' or 'error' message.
   */
  public function createReservation(array $data): array {
    try {
      // Validate required fields.
      $required_fields = ['unitId', 'bedType', 'checkInDate', 'checkOutDate'];
      foreach ($required_fields as $field) {
        if (empty($data[$field])) {
          return [
            'success' => FALSE,
            'error' => 'Required: unitId, bedType, checkInDate, checkOutDate',
          ];
        }
      }

      // Load unit.
      $unit = $this->entityTypeManager->getStorage('bat_unit')->load((int) $data['unitId']);
      if (!$unit) {
        return ['success' => FALSE, 'error' => 'Unit not found'];
      }

      // Check availability.
      $availability = $this->checkAvailability(
        $data['unitId'],
        $data['bedType'],
        $data['checkInDate'],
        $data['checkOutDate']
      );
      if (!$availability['available']) {
        return ['success' => FALSE, 'error' => $availability['message']];
      }

      // Get pending state.
      $pending_state = $this->getStateByName('Pending');
      if (!$pending_state) {
        return ['success' => FALSE, 'error' => 'Could not find the "Pending" state'];
      }

      // Validate email.
      if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return ['success' => FALSE, 'error' => 'A valid email is required'];
      }

      // Format dates.
      $start_value = (new \DateTime($data['checkInDate']))
        ->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
      $end_value = (new \DateTime($data['checkOutDate']))
        ->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

      // Create event.
      $event = $this->entityTypeManager->getStorage('bat_event')->create([
        'type' => 'availability_daily',
        'event_dates' => ['value' => $start_value, 'end_value' => $end_value],
        'event_bat_unit_reference' => ['target_id' => (int) $data['unitId']],
        'field_bed_type' => $data['bedType'],
        'event_state_reference' => ['target_id' => $pending_state->id()],
      ]);
      $event->save();

      // Create booking.
      $booking = $this->entityTypeManager->getStorage('bat_booking')->create([
        'type' => 'standard',
        'booking_event_reference' => ['target_id' => $event->id()],
        'uid' => ['target_id' => \Drupal::currentUser()->id()],
        'field_requester_email' => ['value' => $data['email']],
        'status' => 1,
        'field_event_state' => ['target_id' => $pending_state->id()],
        'booking_start_date' => ['value' => $start_value],
        'booking_end_date' => ['value' => $end_value],
      ]);
      $booking->save();

      $summary = [];

      if ($booking instanceof BookingInterface) {
        // Build response summary.
        $summary = $this->buildReservationSummary($unit, $data, $booking->id());

        // Send notification email.
        $this->sendNotificationEmail($unit, $summary);
      }

      return ['success' => TRUE, 'data' => $summary];
    }
    catch (\Exception $e) {
      $this->logger->error('Error creating reservation: @message', [
        '@message' => $e->getMessage(),
      ]);
      return ['success' => FALSE, 'error' => 'Internal server error'];
    }
  }

  /**
   * Update an existing booking.
   *
   * @param int $booking_id
   *   The booking ID.
   * @param array $data
   *   The data to update.
   *
   * @return array
   *   Array with 'success' boolean and 'data' or 'error' message.
   */
  public function updateBooking(int $booking_id, array $data): array {
    try {
      $booking = $this->entityTypeManager->getStorage('bat_booking')->load($booking_id);
      if (!$booking) {
        return ['success' => FALSE, 'error' => 'Booking not found'];
      }

      // Update event state if provided.
      if (!empty($data['state'])) {
        $state = $this->getStateByMachineName($data['state']);
        if (!$state) {
          return ['success' => FALSE, 'error' => 'Invalid state'];
        }

        $booking->set('field_event_state', ['target_id' => $state->id()]);

        // Update associated event state.
        if ($booking->hasField('booking_event_reference') && !$booking->get('booking_event_reference')->isEmpty()) {
          $event_id = $booking->get('booking_event_reference')->target_id;
          $event = $this->entityTypeManager->getStorage('bat_event')->load($event_id);
          if ($event) {
            $event->set('event_state_reference', ['target_id' => $state->id()]);
            $event->save();
          }
        }
      }

      // Update email if provided.
      if (!empty($data['email'])) {
        $booking->set('field_requester_email', ['value' => $data['email']]);
      }

      $booking->save();

      return ['success' => TRUE, 'data' => ['booking_id' => $booking->id()]];
    }
    catch (\Exception $e) {
      $this->logger->error('Error updating booking: @message', [
        '@message' => $e->getMessage(),
      ]);
      return ['success' => FALSE, 'error' => 'Internal server error'];
    }
  }

  /**
   * Delete a booking.
   *
   * @param int $booking_id
   *   The booking ID.
   *
   * @return array
   *   Array with 'success' boolean and 'error' message if failed.
   */
  public function deleteBooking(int $booking_id): array {
    try {
      $booking = $this->entityTypeManager->getStorage('bat_booking')->load($booking_id);
      if (!$booking) {
        return ['success' => FALSE, 'error' => 'Booking not found'];
      }

      // Delete associated event.
      if ($booking->hasField('booking_event_reference') && !$booking->get('booking_event_reference')->isEmpty()) {
        $event_id = $booking->get('booking_event_reference')->target_id;
        $event = $this->entityTypeManager->getStorage('bat_event')->load($event_id);
        if ($event) {
          $event->delete();
        }
      }

      $booking->delete();

      return ['success' => TRUE];
    }
    catch (\Exception $e) {
      $this->logger->error('Error deleting booking: @message', [
        '@message' => $e->getMessage(),
      ]);
      return ['success' => FALSE, 'error' => 'Internal server error'];
    }
  }

  /**
   * Get state by machine name.
   *
   * @param string $machine_name
   *   The state machine name.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The state entity or NULL.
   */
  private function getStateByMachineName(string $machine_name): ?\Drupal\Core\Entity\EntityInterface {
    $states = $this->entityTypeManager->getStorage('state')->loadByProperties([
      'machine_name' => $machine_name,
      'event_type' => 'availability_daily',
    ]);
    return reset($states) ?: NULL;
  }

  /**
   * Get state by name.
   *
   * @param string $name
   *   The state name.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The state entity or NULL.
   */
  private function getStateByName(string $name): ?\Drupal\Core\Entity\EntityInterface {
    $states = $this->entityTypeManager->getStorage('state')->loadByProperties([
      'name' => $name,
    ]);
    return reset($states) ?: NULL;
  }

  /**
   * Check availability for a unit and bed type.
   *
   * @param int $unit_id
   *   The unit ID.
   * @param string $bed_type
   *   The bed type.
   * @param string $check_in_date
   *   The check-in date.
   * @param string $check_out_date
   *   The check-out date.
   *
   * @return array
   *   Array with 'available' boolean and 'message' if not available.
   */
  private function checkAvailability(int $unit_id, string $bed_type, string $check_in_date, string $check_out_date): array {
    try {
      // Load unit and get total beds
      $unit = $this->entityTypeManager->getStorage('bat_unit')->load($unit_id);
      if (!$unit) {
        return ['available' => FALSE, 'message' => 'Unit not found'];
      }

      $total_beds = 0;
      foreach ($unit->get('field_beds') as $item) {
        if ($p = $item->entity) {
          if ($p->get('field_bed_type')->value === $bed_type) {
            $total_beds = (int) $p->get('field_bed_quantity')->value;
            break;
          }
        }
      }

      if ($total_beds < 1) {
        return ['available' => FALSE, 'message' => "No beds of type $bed_type"];
      }

      // Parse dates
      $in = new \DateTime($check_in_date);
      $out = new \DateTime($check_out_date);
      $out->modify('+1 day');
      $interval = new \DateInterval('P1D');
      $range = new \DatePeriod($in, $interval, $out);

      // Check existing events
      $storage = $this->entityTypeManager->getStorage('bat_event');
      $query = $storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'availability_daily')
        ->condition('event_bat_unit_reference', $unit_id)
        ->condition('field_bed_type', $bed_type)
        ->condition('event_dates.value', $check_out_date, '<=')
        ->condition('event_dates.end_value', $check_in_date, '>=');
      $ids = $query->execute();

      // Count occupied beds per day
      $occupied = [];
      if (!empty($ids)) {
        $events = $storage->loadMultiple($ids);
        foreach ($events as $ev) {
          $s = new \DateTime($ev->get('event_dates')->value);
          $e = new \DateTime($ev->get('event_dates')->end_value);
          $e->modify('+1 day');
          $inner = new \DatePeriod($s, $interval, $e);
          foreach ($inner as $d) {
            $key = $d->format('Y-m-d');
            $occupied[$key] = ($occupied[$key] ?? 0) + 1;
          }
        }
      }

      // Check each day
      foreach ($range as $d) {
        $key = $d->format('Y-m-d');
        if (($occupied[$key] ?? 0) >= $total_beds) {
          return ['available' => FALSE, 'message' => "No $bed_type beds available on $key"];
        }
      }

      return ['available' => TRUE];
    }
    catch (\Exception $e) {
      $this->logger->error('Error checking availability: @message', ['@message' => $e->getMessage()]);
      return ['available' => FALSE, 'message' => 'Error checking availability'];
    }
  }

  /**
   * Build reservation summary.
   *
   * @param \Drupal\Core\Entity\EntityInterface $unit
   *   The unit entity.
   * @param array $data
   *   The reservation data.
   *
   * @return array
   *   The reservation summary.
   */
  private function buildReservationSummary(EntityInterface $unit, array $data, $booking_id): array {
    $images = [];
    if ($img = $unit->get('field_cover_image')->entity) {
      $images[] = $this->fileUrlGenerator->generateAbsoluteString($img->getFileUri());
    }

    return [
      'room' => [
        'roomName' => $unit->label(),
        'bedType' => $data['bedType'],
        'imageUrls' => $images,
        'imageCount' => count($images),
        'address' => $unit->get('field_address')->value,
        'managerEmail' => $unit->get('field_manager_email')->value,
      ],
      'bookingInfo' => [
        'checkIn' => ['date' => $data['checkInDate'], 'time' => $data['checkInTime'] ?? ''],
        'checkOut' => ['date' => $data['checkOutDate'], 'time' => $data['checkOutTime'] ?? ''],
      ],
      'booking_id' => $booking_id,
      'requesterEmail' => $data['email'],
      'details' => $data['details'] ?? '',
    ];
  }

  /**
   * Send notification email.
   *
   * @param \Drupal\Core\Entity\EntityInterface $unit
   *   The unit entity.
   * @param array $summary
   *   The reservation summary.
   */
  private function sendNotificationEmail(\Drupal\Core\Entity\EntityInterface $unit, array $summary): void {
    $to = $unit->get('field_manager_email')->value;
    \Drupal::service('plugin.manager.mail')->mail(
      'hous_z_api',
      'booking_notification',
      $to,
      \Drupal::languageManager()->getDefaultLanguage()->getId(),
      ['summary' => $summary]
    );
  }

  /**
   * Get bookings data, optionally filtered by requester email.
   *
   * @param string|null $email
   *   The email address to filter bookings by, or NULL to return all bookings.
   *
   * @return array
   *   Array of booking data.
   */
  public function getBookingsData(?string $email = NULL): array {
    $booking_storage = $this->entityTypeManager->getStorage('bat_booking');

    $query = $booking_storage->getQuery()
      ->accessCheck(TRUE)
      ->sort('id', 'DESC');

    if ($email) {
      $email = strtolower(trim($email));
      $query->condition('field_requester_email', $email);
    }

    $booking_ids = $query->execute();

    if (empty($booking_ids)) {
      return [];
    }

    $bookings = $booking_storage->loadMultiple($booking_ids);

    $data = [];

    foreach ($bookings as $booking) {
      $booking_data = [
        'reservationId' => (int) $booking->id(),
        'roomName' => NULL,
        'bedType' => NULL,
        'checkInDate' => NULL,
        'checkOutDate' => NULL,
        'status' => NULL,
        'email' => $booking->get('field_requester_email')?->value,
      ];

      if ($booking->hasField('booking_start_date') && !$booking->get('booking_start_date')->isEmpty()) {
        $start_date = $booking->get('booking_start_date')->value;
        $booking_data['checkInDate'] = date('Y-m-d', strtotime($start_date));
      }

      if ($booking->hasField('booking_end_date') && !$booking->get('booking_end_date')->isEmpty()) {
        $end_date = $booking->get('booking_end_date')->value;
        $booking_data['checkOutDate'] = date('Y-m-d', strtotime($end_date));
      }

      if ($booking->hasField('field_event_state') && !$booking->get('field_event_state')->isEmpty()) {
        $state_id = $booking->get('field_event_state')->target_id;
        $booking_data['status'] = $this->getStateLabel($state_id);
      }

      if ($booking->hasField('booking_event_reference') && !$booking->get('booking_event_reference')->isEmpty()) {
        $event_id = $booking->get('booking_event_reference')->target_id;
        $roomDetails = $this->getRoomDetailsFromEvent($event_id);
        $booking_data['roomName'] = $roomDetails['name'];
        $booking_data['bedType'] = $roomDetails['bed'];
      }

      $data[] = $booking_data;
    }

    return $data;
  }

  /**
   * Get room details from event entity.
   *
   * @param int $event_id
   *   The event ID.
   *
   * @return array|null
   *   The room details or NULL if not found.
   */
  private function getRoomDetailsFromEvent(int $event_id): ?array {
    try {
      $event = $this->entityTypeManager->getStorage('bat_event')->load($event_id);

      $event_exists = $event && $event->hasField('event_bat_unit_reference') &&
        !$event->get('event_bat_unit_reference')->isEmpty();

      if ($event_exists) {
        $unit_id = $event->get('event_bat_unit_reference')->target_id;
        $unit = $this->entityTypeManager->getStorage('bat_unit')->load($unit_id);

        if ($unit) {
          return [
            'id' => (int) $unit->id(),
            'name' => $unit->label(),
            'bed' => $event->get('field_bed_type')->value,
          ];
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->warning('Could not load event @id or related unit: @message', [
        '@id' => $event_id,
        '@message' => $e->getMessage(),
      ]);
    }

    return NULL;
  }

  /**
   * Get state label by state ID.
   *
   * @param int $state_id
   *   The state ID.
   *
   * @return string|null
   *   The state label or NULL if not found.
   */
  private function getStateLabel(int $state_id): ?string {
    $state_labels = [
      7 => 'Pending',
      8 => 'Cancelled',
      9 => 'Confirmed',
    ];

    return $state_labels[$state_id] ?? NULL;
  }

}
