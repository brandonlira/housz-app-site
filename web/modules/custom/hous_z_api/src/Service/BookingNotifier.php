<?php

namespace Drupal\hous_z_api\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\symfony_mailer\EmailFactoryInterface;

/**
 * Service responsible for booking email notifications.
 */
class BookingNotifier {

  protected EmailFactoryInterface $emailFactory;
  protected $logger;

  public function __construct(
    EmailFactoryInterface $email_factory,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->emailFactory = $email_factory;
    $this->logger = $logger_factory->get('hous_z_api');
  }

  /**
   * Sends notification emails for a new booking.
   */
  public function notifyBookingCreated(EntityInterface $booking): void {
    $context = $this->buildContext($booking);
    $room    = (string) ($context['roomName'] ?? 'Room');
    $id      = (string) ($context['bookingId'] ?? '');

    foreach ($this->getManagerRecipients($context['managerEmail'] ?? '') as $recipient) {
      $this->send('booking_created_admin', $recipient,
        "New booking request #{$id} for {$room}", $context);
    }
    $this->send('booking_created_guest', $context['requesterEmail'] ?? '',
      "Booking request received for {$room}", $context);
  }

  /**
   * Sends notification emails for a status change.
   */
  public function notifyBookingUpdated(EntityInterface $booking, EntityInterface $original): void {
    $context = $this->buildContext($booking, $original);
    $room    = (string) ($context['roomName'] ?? 'Room');
    $id      = (string) ($context['bookingId'] ?? '');
    $status  = (string) ($context['statusLabel'] ?? ucfirst($context['status'] ?? ''));

    $guest_key = match ($context['status'] ?? '') {
      'confirmed' => 'booking_confirmed',
      'cancelled' => 'booking_cancelled',
      default     => 'booking_status_changed',
    };

    $guest_subject = match ($context['status'] ?? '') {
      'confirmed' => "Your booking is confirmed: {$room}",
      'cancelled' => "Your booking has been cancelled: {$room}",
      default     => "Your booking status was updated: {$room}",
    };

    $this->send($guest_key, $context['requesterEmail'] ?? '', $guest_subject, $context);
    foreach ($this->getManagerRecipients($context['managerEmail'] ?? '') as $recipient) {
      $this->send('booking_status_admin', $recipient,
        "Booking #{$id} changed to {$status}", $context);
    }
  }

  /**
   * Builds a normalised booking context array from entity fields.
   */
  public function buildContext(EntityInterface $booking, ?EntityInterface $original = NULL): array {
    $event          = $booking->get('booking_event_reference')->entity ?? NULL;
    $unit           = $event?->get('event_bat_unit_reference')->entity ?? NULL;
    $state          = $booking->get('field_event_state')->entity ?? NULL;
    $original_state = $original?->get('field_event_state')->entity ?? NULL;

    return [
      'bookingId'           => (int) $booking->id(),
      'roomName'            => $unit?->label() ?? '',
      'address'             => $unit?->get('field_address')->value ?? '',
      'managerEmail'        => $unit?->get('field_manager_email')->value ?? '',
      'requesterEmail'      => $booking->get('field_requester_email')->value ?? '',
      'checkInDate'         => $this->formatDate($booking->get('booking_start_date')->value ?? ''),
      'checkOutDate'        => $this->formatDate($booking->get('booking_end_date')->value ?? ''),
      'checkInTime'         => $booking->hasField('field_check_in_time') ? (string) $booking->get('field_check_in_time')->value : '',
      'checkOutTime'        => $booking->hasField('field_check_out_time') ? (string) $booking->get('field_check_out_time')->value : '',
      'details'             => $booking->hasField('field_booking_details') ? (string) $booking->get('field_booking_details')->value : '',
      'status'              => $state?->get('machine_name')->value ?? '',
      'statusLabel'         => $state?->label() ?? '',
      'previousStatus'      => $original_state?->get('machine_name')->value ?? '',
      'previousStatusLabel' => $original_state?->label() ?? '',
      'bedType'             => $event?->get('field_bed_type')->value ?? '',
    ];
  }

  /**
   * Sends one branded HTML email via Symfony Mailer EmailFactory.
   */
  protected function send(string $key, string $to, string $subject, array $context): void {
    $to = trim(strtolower($to));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
      $this->logger->warning('Skipping email "@key" — invalid or empty address "@to".', [
        '@key' => $key,
        '@to'  => $to,
      ]);
      return;
    }

    try {
      $vars = $this->buildTemplateVars($key, $context);
      $this->emailFactory->sendTypedEmail('hous_z_api', $key, $to, $subject, $vars);
    }
    catch (\Exception $e) {
      $this->logger->error('Failed sending "@key" to @to for booking @id: @message', [
        '@key'     => $key,
        '@to'      => $to,
        '@id'      => $context['bookingId'] ?? 'unknown',
        '@message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Builds per-key template variables for the hous_z_api_email theme hook.
   */
  protected function buildTemplateVars(string $key, array $context): array {
    $id           = (string) ($context['bookingId'] ?? '');
    $room         = (string) ($context['roomName'] ?? 'Room');
    $status       = (string) ($context['status'] ?? 'pending');
    $status_label = (string) ($context['statusLabel'] ?? ucfirst($status));
    $prev_label   = (string) ($context['previousStatusLabel'] ?? ucfirst((string) ($context['previousStatus'] ?? '')));
    $check_in     = trim(($context['checkInDate'] ?? '') . ' ' . ($context['checkInTime'] ?? ''));
    $check_out    = trim(($context['checkOutDate'] ?? '') . ' ' . ($context['checkOutTime'] ?? ''));
    $address      = (string) ($context['address'] ?? '');
    $guest        = (string) ($context['requesterEmail'] ?? '');
    $manager      = (string) ($context['managerEmail'] ?? '');
    $details      = (string) ($context['details'] ?? '');
    $logo_url     = $this->getLogoUrl();

    $base = [
      'status'       => $status,
      'status_label' => $status_label,
      'details'      => $details,
      'logo_url'     => $logo_url,
    ];

    // Helper closure — builds a row array with named keys for Twig.
    $row = fn(string $label, string $value, bool $is_status = FALSE) => [
      'label'     => $label,
      'value'     => $value,
      'is_status' => $is_status,
    ];

    return match ($key) {
      'booking_created_admin' => $base + [
        'heading'   => 'New booking request',
        'intro'     => 'A new booking is pending your review.',
        'cta_email' => '',
        'rows'      => array_values(array_filter([
          $row('Booking ID', "#{$id}"),
          $row('Guest',      $guest),
          $row('Room',       $room),
          $row('Check-in',   $check_in),
          $row('Check-out',  $check_out),
          $address !== '' ? $row('Address', $address) : NULL,
        ])),
      ],
      'booking_created_guest' => $base + [
        'heading'   => 'Booking request received',
        'intro'     => 'Your booking request was received. You will be notified once it is reviewed.',
        'cta_email' => $manager,
        'rows'      => array_values(array_filter([
          $row('Booking ID', "#{$id}"),
          $row('Room',       $room),
          $row('Check-in',   $check_in),
          $row('Check-out',  $check_out),
          $row('Status',     'Pending review', TRUE),
          $address !== '' ? $row('Address', $address) : NULL,
        ])),
      ],
      'booking_confirmed' => $base + [
        'heading'   => 'Your booking is confirmed',
        'intro'     => 'Great news — your booking has been approved.',
        'cta_email' => $manager,
        'rows'      => array_values(array_filter([
          $row('Booking ID', "#{$id}"),
          $row('Room',       $room),
          $row('Check-in',   $check_in),
          $row('Check-out',  $check_out),
          $row('Status',     'Confirmed', TRUE),
          $address !== '' ? $row('Address', $address) : NULL,
        ])),
      ],
      'booking_cancelled' => $base + [
        'heading'   => 'Your booking has been cancelled',
        'intro'     => 'Unfortunately your booking has been cancelled.',
        'cta_email' => $manager,
        'rows'      => array_values(array_filter([
          $row('Booking ID', "#{$id}"),
          $row('Room',       $room),
          $row('Check-in',   $check_in),
          $row('Check-out',  $check_out),
          $row('Status',     'Cancelled', TRUE),
        ])),
      ],
      'booking_status_admin' => $base + [
        'heading'   => "Booking #{$id} status updated",
        'intro'     => 'A booking status has changed.',
        'cta_email' => '',
        'rows'      => array_values(array_filter([
          $row('Booking ID', "#{$id}"),
          $row('Room',       $room),
          $row('Guest',      $guest),
          $row('Check-in',   $check_in),
          $row('Check-out',  $check_out),
          $row('New status', $status_label, TRUE),
          $prev_label !== '' ? $row('Previous', $prev_label) : NULL,
        ])),
      ],
      'booking_status_changed' => $base + [
        'heading'   => 'Your booking was updated',
        'intro'     => 'Your booking status has changed.',
        'cta_email' => $manager,
        'rows'      => array_values(array_filter([
          $row('Booking ID', "#{$id}"),
          $row('Room',       $room),
          $row('Check-in',   $check_in),
          $row('Check-out',  $check_out),
          $row('New status', $status_label, TRUE),
          $prev_label !== '' ? $row('Previous', $prev_label) : NULL,
        ])),
      ],
      default => $base + [
        'heading'   => 'Booking notification',
        'intro'     => '',
        'cta_email' => '',
        'rows'      => [],
      ],
    };
  }

  /**
   * Returns the absolute URL to the module logo, or empty string if not found.
   */
  protected function getLogoUrl(): string {
    $module_path = \Drupal::service('extension.list.module')->getPath('hous_z_api');
    $logo_file   = DRUPAL_ROOT . '/' . $module_path . '/images/logo.png';
    if (file_exists($logo_file)) {
      return \Drupal::request()->getSchemeAndHttpHost() . '/' . $module_path . '/images/logo.png';
    }
    return '';
  }

  /**
   * Returns the list of manager email recipients.
   *
   * Priority:
   *  1. All active users with the configured notify_role.
   *  2. Additional emails from notify_emails config.
   *  3. Fallback: the room's field_manager_email.
   */
  protected function getManagerRecipients(string $fallback_email): array {
    $config    = \Drupal::config('hous_z_management.settings');
    $recipients = [];

    // Role-based recipients.
    $notify_role = $config->get('notify_role');
    if ($notify_role) {
      $users = \Drupal::entityTypeManager()->getStorage('user')->loadByProperties([
        'status' => 1,
        'roles'  => $notify_role,
      ]);
      foreach ($users as $user) {
        $email = $user->getEmail();
        if ($email) {
          $recipients[] = $email;
        }
      }
    }

    // Additional configured emails.
    foreach ($config->get('notify_emails') ?? [] as $email) {
      if ($email && !\Drupal::service('email.validator')->isValid($email) === FALSE) {
        $recipients[] = $email;
      }
    }

    // Fallback to room manager email if nothing configured.
    if (empty($recipients) && $fallback_email) {
      $recipients[] = $fallback_email;
    }

    return array_unique(array_filter($recipients));
  }

  /**
   * Formats a storage date value to British format (dd/mm/yyyy).
   */
  protected function formatDate(string $value): string {
    if ($value === '') {
      return '';
    }
    try {
      return (new \DateTime($value))->format('d/m/Y');
    }
    catch (\Exception) {
      return $value;
    }
  }

}
