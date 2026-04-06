<?php

namespace Drupal\hous_z_api\Service;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\symfony_mailer\EmailFactoryInterface;

/**
 * Service responsible for booking email notifications.
 */
class BookingNotifier {

  /**
   * The email factory.
   *
   * @var \Drupal\symfony_mailer\EmailFactoryInterface
   */
  protected EmailFactoryInterface $emailFactory;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs the notifier.
   */
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
    $room    = Html::escape((string) ($context['roomName'] ?? 'Room'));
    $id      = Html::escape((string) ($context['bookingId'] ?? ''));

    $this->send(
      key: 'booking_created_admin',
      to: $context['managerEmail'] ?? '',
      subject: "New booking request #{$id} for {$room}",
      context: $context,
    );
    $this->send(
      key: 'booking_created_guest',
      to: $context['requesterEmail'] ?? '',
      subject: "Booking request received for {$room}",
      context: $context,
    );
  }

  /**
   * Sends notification emails for a status change.
   */
  public function notifyBookingUpdated(EntityInterface $booking, EntityInterface $original): void {
    $context = $this->buildContext($booking, $original);
    $room    = Html::escape((string) ($context['roomName'] ?? 'Room'));
    $id      = Html::escape((string) ($context['bookingId'] ?? ''));
    $status  = Html::escape((string) ($context['statusLabel'] ?? ucfirst($context['status'] ?? '')));

    $guest_key = match($context['status'] ?? '') {
      'confirmed' => 'booking_confirmed',
      'cancelled' => 'booking_cancelled',
      default     => 'booking_status_changed',
    };

    $guest_subject = match($context['status'] ?? '') {
      'confirmed' => "Your booking is confirmed: {$room}",
      'cancelled' => "Your booking has been cancelled: {$room}",
      default     => "Your booking status was updated: {$room}",
    };

    $this->send(
      key: $guest_key,
      to: $context['requesterEmail'] ?? '',
      subject: $guest_subject,
      context: $context,
    );
    $this->send(
      key: 'booking_status_admin',
      to: $context['managerEmail'] ?? '',
      subject: "Booking #{$id} changed to {$status}",
      context: $context,
    );
  }

  /**
   * Builds a normalized booking context array.
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
      $html = hous_z_api_build_email_html($key, $context);
      $this->emailFactory->sendTypedEmail('hous_z_api', $key, $to, $subject, $html);
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
   * Formats storage date values to Y-m-d.
   */
  protected function formatDate(string $value): string {
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

}
