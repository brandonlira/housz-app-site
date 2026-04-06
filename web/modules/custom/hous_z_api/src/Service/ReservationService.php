<?php

namespace Drupal\hous_z_api\Service;

use Drupal\bat_booking\BookingInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGenerator;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Service for reservation and booking operations.
 */
class ReservationService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

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
  protected FileUrlGenerator $fileUrlGenerator;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * Constructs the service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    FileUrlGenerator $file_url_generator,
    AccountProxyInterface $current_user,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('hous_z_api');
    $this->fileUrlGenerator = $file_url_generator;
    $this->currentUser = $current_user;
  }

  /**
   * Creates a new reservation.
   */
  public function createReservation(array $data): array {
    try {
      foreach (['unitId', 'bedType', 'checkInDate', 'checkOutDate', 'email'] as $field) {
        if (empty($data[$field])) {
          return [
            'success' => FALSE,
            'error' => 'Required fields: unitId, bedType, checkInDate, checkOutDate, email.',
          ];
        }
      }

      $email = strtolower(trim((string) $data['email']));
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => FALSE, 'error' => 'Valid email is required.'];
      }

      $unit = $this->entityTypeManager->getStorage('bat_unit')->load((int) $data['unitId']);
      if (!$unit) {
        return ['success' => FALSE, 'error' => 'Unit not found.'];
      }

      $availability = $this->checkAvailability(
        (int) $data['unitId'],
        (string) $data['bedType'],
        (string) $data['checkInDate'],
        (string) $data['checkOutDate'],
      );
      if (!$availability['available']) {
        return ['success' => FALSE, 'error' => $availability['message']];
      }

      $pending_state = $this->getStateByMachineName('pending') ?? $this->getStateByName('Pending');
      if (!$pending_state) {
        return ['success' => FALSE, 'error' => 'Pending state not found.'];
      }

      $start_value = (new \DateTime((string) $data['checkInDate']))->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
      $end_value = (new \DateTime((string) $data['checkOutDate']))->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

      $event = $this->entityTypeManager->getStorage('bat_event')->create([
        'type' => 'availability_daily',
        'event_dates' => ['value' => $start_value, 'end_value' => $end_value],
        'event_bat_unit_reference' => ['target_id' => (int) $data['unitId']],
        'field_bed_type' => (string) $data['bedType'],
        'event_state_reference' => ['target_id' => $pending_state->id()],
      ]);
      $event->save();

      $booking_values = [
        'type' => 'standard',
        'booking_event_reference' => ['target_id' => $event->id()],
        'field_requester_email' => ['value' => $email],
        'status' => 1,
        'field_event_state' => ['target_id' => $pending_state->id()],
        'booking_start_date' => ['value' => $start_value],
        'booking_end_date' => ['value' => $end_value],
      ];

      $user_id = $this->resolveUserIdByEmail($email);
      if ($user_id !== NULL) {
        $booking_values['uid'] = ['target_id' => $user_id];
      }

      $booking = $this->entityTypeManager->getStorage('bat_booking')->create($booking_values);
      $this->setBookingExtraFields($booking, $data);
      $booking->save();

      if (!$booking instanceof BookingInterface) {
        return ['success' => FALSE, 'error' => 'Booking could not be created.'];
      }

      return [
        'success' => TRUE,
        'data' => $this->buildReservationSummary($unit, $booking, $data),
      ];
    }
    catch (\Exception $e) {
      $this->logger->error('Error creating reservation: @message', ['@message' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Internal server error.'];
    }
  }

  /**
   * Legacy booking update endpoint.
   */
  public function updateBooking(int $booking_id, array $data): array {
    try {
      $booking = $this->entityTypeManager->getStorage('bat_booking')->load($booking_id);
      if (!$booking) {
        return ['success' => FALSE, 'error' => 'Booking not found.'];
      }

      if (!empty($data['status']) || !empty($data['state'])) {
        $status = strtolower(trim((string) ($data['status'] ?? $data['state'])));
        $state = $this->getStateByMachineName($status);
        if (!$state) {
          return ['success' => FALSE, 'error' => 'Invalid status.'];
        }

        $booking->set('field_event_state', ['target_id' => $state->id()]);
        $event = $booking->get('booking_event_reference')->entity ?? NULL;
        if ($event) {
          $event->set('event_state_reference', ['target_id' => $state->id()]);
          $event->save();
        }
      }

      if (!empty($data['email']) && filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $booking->set('field_requester_email', ['value' => strtolower(trim((string) $data['email']))]);
      }

      $this->setBookingExtraFields($booking, $data);
      $booking->save();

      return ['success' => TRUE, 'data' => ['booking_id' => (int) $booking->id()]];
    }
    catch (\Exception $e) {
      $this->logger->error('Error updating booking: @message', ['@message' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Internal server error.'];
    }
  }

  /**
   * Updates a booking status for the new API contract.
   */
  public function updateBookingStatus(array $data): array {
    try {
      $booking_id = isset($data['bookingId']) ? (int) $data['bookingId'] : 0;
      $status = strtolower(trim((string) ($data['status'] ?? '')));

      if ($booking_id < 1 || !in_array($status, ['confirmed', 'cancelled'], TRUE)) {
        return ['success' => FALSE, 'error' => 'Invalid or missing parameters.'];
      }

      $booking = $this->entityTypeManager->getStorage('bat_booking')->load($booking_id);
      if (!$booking) {
        return ['success' => FALSE, 'error' => 'Booking not found.'];
      }

      $state = $this->getStateByMachineName($status);
      if (!$state) {
        return ['success' => FALSE, 'error' => 'Invalid or missing parameters.'];
      }

      $booking->set('field_event_state', ['target_id' => $state->id()]);
      if ($booking->hasField('booking_event_reference') && !$booking->get('booking_event_reference')->isEmpty()) {
        $event = $booking->get('booking_event_reference')->entity;
        if ($event) {
          $event->set('event_state_reference', ['target_id' => $state->id()]);
          $event->save();
        }
      }

      $booking->save();

      return [
        'success' => TRUE,
        'data' => [
          'bookingId' => $booking_id,
          'status' => $status,
          'message' => 'Booking status updated successfully.',
        ],
      ];
    }
    catch (\Exception $e) {
      $this->logger->error('Error updating booking status: @message', ['@message' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Internal server error.'];
    }
  }

  /**
   * Deletes a booking.
   */
  public function deleteBooking(int $booking_id): array {
    try {
      $booking = $this->entityTypeManager->getStorage('bat_booking')->load($booking_id);
      if (!$booking) {
        return ['success' => FALSE, 'error' => 'Booking not found.'];
      }

      if ($booking->hasField('booking_event_reference') && !$booking->get('booking_event_reference')->isEmpty()) {
        $event = $booking->get('booking_event_reference')->entity;
        if ($event) {
          $event->delete();
        }
      }

      $booking->delete();
      return ['success' => TRUE];
    }
    catch (\Exception $e) {
      $this->logger->error('Error deleting booking: @message', ['@message' => $e->getMessage()]);
      return ['success' => FALSE, 'error' => 'Internal server error.'];
    }
  }

  /**
   * Returns all bookings for admin usage, optionally filtered by email.
   */
  public function getBookingsData(?string $email = NULL): array {
    $booking_storage = $this->entityTypeManager->getStorage('bat_booking');
    $query = $booking_storage->getQuery()->accessCheck(TRUE)->sort('id', 'DESC');

    if ($email !== NULL) {
      $query->condition('field_requester_email', strtolower(trim($email)));
    }

    $booking_ids = $query->execute();
    if (empty($booking_ids)) {
      return [];
    }

    $bookings = $booking_storage->loadMultiple($booking_ids);
    $data = [];

    foreach ($bookings as $booking) {
      $event = $booking->get('booking_event_reference')->entity ?? NULL;
      $unit = $event?->get('event_bat_unit_reference')->entity ?? NULL;
      $state = $booking->get('field_event_state')->entity ?? NULL;

      $data[] = [
        'reservationId' => (int) $booking->id(),
        'roomName' => $unit?->label(),
        'bedType' => $event?->get('field_bed_type')->value,
        'checkInDate' => $this->formatStorageDate($booking->get('booking_start_date')->value ?? ''),
        'checkOutDate' => $this->formatStorageDate($booking->get('booking_end_date')->value ?? ''),
        'checkInTime' => $booking->hasField('field_check_in_time') ? $booking->get('field_check_in_time')->value : '',
        'checkOutTime' => $booking->hasField('field_check_out_time') ? $booking->get('field_check_out_time')->value : '',
        'status' => $state?->get('machine_name')->value,
        'email' => $booking->get('field_requester_email')->value ?? '',
        'details' => $booking->hasField('field_booking_details') ? $booking->get('field_booking_details')->value : '',
      ];
    }

    return $data;
  }

  /**
   * Returns the new user reservations response format.
   */
  public function getUserReservationsByEmail(string $email): array {
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      return ['success' => FALSE, 'error' => 'Valid email is required.'];
    }

    $booking_storage = $this->entityTypeManager->getStorage('bat_booking');
    $booking_ids = $booking_storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('field_requester_email', $email)
      ->sort('id', 'DESC')
      ->execute();

    $reservations = [];
    foreach ($booking_storage->loadMultiple($booking_ids) as $booking) {
      $event = $booking->get('booking_event_reference')->entity ?? NULL;
      $unit = $event?->get('event_bat_unit_reference')->entity ?? NULL;
      $state = $booking->get('field_event_state')->entity ?? NULL;

      $reservations[] = [
        'bookingId' => (int) $booking->id(),
        'room' => [
          'unitId' => $unit ? (int) $unit->id() : NULL,
          'roomName' => $unit?->label() ?? '',
          'bedType' => $event?->get('field_bed_type')->value ?? '',
          'address' => $unit?->get('field_address')->value ?? '',
          'managerEmail' => $unit?->get('field_manager_email')->value ?? '',
        ],
        'dates' => [
          'checkInDate' => $this->formatStorageDate($booking->get('booking_start_date')->value ?? ''),
          'checkInTime' => $booking->hasField('field_check_in_time') ? (string) $booking->get('field_check_in_time')->value : '',
          'checkOutDate' => $this->formatStorageDate($booking->get('booking_end_date')->value ?? ''),
          'checkOutTime' => $booking->hasField('field_check_out_time') ? (string) $booking->get('field_check_out_time')->value : '',
        ],
        'status' => $state?->get('machine_name')->value ?? '',
        'details' => $booking->hasField('field_booking_details') ? (string) $booking->get('field_booking_details')->value : '',
        'createdAt' => $this->extractCreatedAt($booking),
      ];
    }

    return [
      'success' => TRUE,
      'data' => [
        'user' => [
          'email' => $email,
          'totalReservations' => count($reservations),
        ],
        'reservations' => $reservations,
      ],
    ];
  }

  /**
   * Checks unit availability for the period.
   */
  private function checkAvailability(int $unit_id, string $bed_type, string $check_in_date, string $check_out_date): array {
    try {
      $unit = $this->entityTypeManager->getStorage('bat_unit')->load($unit_id);
      if (!$unit) {
        return ['available' => FALSE, 'message' => 'Unit not found.'];
      }

      $total_beds = 0;
      foreach ($unit->get('field_beds') as $item) {
        if ($item->entity && $item->entity->get('field_bed_type')->value === $bed_type) {
          $total_beds = (int) $item->entity->get('field_bed_quantity')->value;
          break;
        }
      }

      if ($total_beds < 1) {
        return ['available' => FALSE, 'message' => "No beds of type {$bed_type}."];
      }

      $start = new \DateTime($check_in_date);
      $end = new \DateTime($check_out_date);
      $end->modify('+1 day');
      $interval = new \DateInterval('P1D');
      $range = new \DatePeriod($start, $interval, $end);

      $event_ids = $this->entityTypeManager->getStorage('bat_event')->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'availability_daily')
        ->condition('event_bat_unit_reference', $unit_id)
        ->condition('field_bed_type', $bed_type)
        ->condition('event_dates.value', $check_out_date, '<=')
        ->condition('event_dates.end_value', $check_in_date, '>=')
        ->execute();

      $occupied = [];
      if (!empty($event_ids)) {
        foreach ($this->entityTypeManager->getStorage('bat_event')->loadMultiple($event_ids) as $event) {
          $inner_start = new \DateTime($event->get('event_dates')->value);
          $inner_end = new \DateTime($event->get('event_dates')->end_value);
          $inner_end->modify('+1 day');
          foreach (new \DatePeriod($inner_start, $interval, $inner_end) as $day) {
            $key = $day->format('Y-m-d');
            $occupied[$key] = ($occupied[$key] ?? 0) + 1;
          }
        }
      }

      foreach ($range as $day) {
        $key = $day->format('Y-m-d');
        if (($occupied[$key] ?? 0) >= $total_beds) {
          return ['available' => FALSE, 'message' => "No {$bed_type} beds available on {$key}."];
        }
      }

      return ['available' => TRUE];
    }
    catch (\Exception $e) {
      $this->logger->error('Error checking availability: @message', ['@message' => $e->getMessage()]);
      return ['available' => FALSE, 'message' => 'Error checking availability.'];
    }
  }

  /**
   * Builds the creation response payload.
   */
  private function buildReservationSummary(EntityInterface $unit, EntityInterface $booking, array $data): array {
    $images = [];
    if ($unit->hasField('field_cover_image') && !$unit->get('field_cover_image')->isEmpty()) {
      $image = $unit->get('field_cover_image')->entity;
      if ($image) {
        $images[] = $this->fileUrlGenerator->generateAbsoluteString($image->getFileUri());
      }
    }

    return [
      'room' => [
        'roomName' => $unit->label(),
        'bedType' => (string) $data['bedType'],
        'imageUrls' => $images,
        'imageCount' => count($images),
        'address' => $unit->get('field_address')->value ?? '',
        'managerEmail' => $unit->get('field_manager_email')->value ?? '',
      ],
      'bookingInfo' => [
        'email' => $booking->get('field_requester_email')->value ?? '',
        'checkIn' => [
          'date' => (string) $data['checkInDate'],
          'time' => (string) ($data['checkInTime'] ?? ''),
        ],
        'checkOut' => [
          'date' => (string) $data['checkOutDate'],
          'time' => (string) ($data['checkOutTime'] ?? ''),
        ],
      ],
      'details' => (string) ($data['details'] ?? ''),
    ];
  }

  /**
   * Sets additional optional booking fields when they exist.
   */
  private function setBookingExtraFields(EntityInterface $booking, array $data): void {
    if ($booking->hasField('field_booking_details')) {
      $booking->set('field_booking_details', ['value' => (string) ($data['details'] ?? '')]);
    }
    if ($booking->hasField('field_check_in_time')) {
      $booking->set('field_check_in_time', ['value' => (string) ($data['checkInTime'] ?? '')]);
    }
    if ($booking->hasField('field_check_out_time')) {
      $booking->set('field_check_out_time', ['value' => (string) ($data['checkOutTime'] ?? '')]);
    }
  }

  /**
   * Resolves a booking owner from email or current user.
   */
  private function resolveUserIdByEmail(string $email): ?int {
    $accounts = $this->entityTypeManager->getStorage('user')->loadByProperties(['mail' => $email]);
    $account = reset($accounts);
    if ($account) {
      return (int) $account->id();
    }

    if ($this->currentUser->isAuthenticated()) {
      return (int) $this->currentUser->id();
    }

    return NULL;
  }

  /**
   * Gets a state by machine name.
   */
  private function getStateByMachineName(string $machine_name): ?EntityInterface {
    $states = $this->entityTypeManager->getStorage('state')->loadByProperties([
      'machine_name' => $machine_name,
      'event_type' => 'availability_daily',
    ]);
    return reset($states) ?: NULL;
  }

  /**
   * Gets a state by label.
   */
  private function getStateByName(string $name): ?EntityInterface {
    $states = $this->entityTypeManager->getStorage('state')->loadByProperties(['name' => $name]);
    return reset($states) ?: NULL;
  }

  /**
   * Formats a stored date for API responses.
   */
  private function formatStorageDate(string $value): string {
    if ($value === '') {
      return '';
    }

    try {
      return (new \DateTime($value))->format('Y-m-d');
    }
    catch (\Exception) {
      return $value;
    }
  }

  /**
   * Formats booking timestamps in ISO-8601 UTC.
   */
  private function formatCreatedAt(int $timestamp): string {
    return gmdate('Y-m-d\TH:i:s\Z', $timestamp);
  }

  /**
   * Extracts the booking creation timestamp safely.
   */
  private function extractCreatedAt(EntityInterface $booking): string {
    if ($booking->hasField('created') && !$booking->get('created')->isEmpty()) {
      return $this->formatCreatedAt((int) $booking->get('created')->value);
    }

    return $this->formatCreatedAt(time());
  }

}
