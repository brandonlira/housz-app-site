<?php

namespace Drupal\hous_z_api\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Mail\MailManagerInterface;

/**
 * Service responsible for booking email notifications.
 */
class BookingNotifier {

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected MailManagerInterface $mailManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

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
    MailManagerInterface $mail_manager,
    LanguageManagerInterface $language_manager,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->mailManager = $mail_manager;
    $this->languageManager = $language_manager;
    $this->logger = $logger_factory->get('hous_z_api');
  }

  /**
   * Sends notification emails for a new booking.
   */
  public function notifyBookingCreated(EntityInterface $booking): void {
    $context = $this->buildContext($booking);
    $this->send('booking_created_admin', $context['managerEmail'] ?? '', $context);
    $this->send('booking_created_guest', $context['requesterEmail'] ?? '', $context);
  }

  /**
   * Sends notification emails for a status change.
   */
  public function notifyBookingUpdated(EntityInterface $booking, EntityInterface $original): void {
    $context = $this->buildContext($booking, $original);

    if (($context['status'] ?? '') === 'confirmed') {
      $this->send('booking_confirmed', $context['requesterEmail'] ?? '', $context);
    }
    elseif (($context['status'] ?? '') === 'cancelled') {
      $this->send('booking_cancelled', $context['requesterEmail'] ?? '', $context);
    }
    else {
      $this->send('booking_status_changed', $context['requesterEmail'] ?? '', $context);
    }

    $this->send('booking_status_admin', $context['managerEmail'] ?? '', $context);
  }

  /**
   * Builds a normalized booking context used by the mail hook.
   */
  public function buildContext(EntityInterface $booking, ?EntityInterface $original = NULL): array {
    $event = $booking->get('booking_event_reference')->entity ?? NULL;
    $unit = $event?->get('event_bat_unit_reference')->entity ?? NULL;
    $state = $booking->get('field_event_state')->entity ?? NULL;
    $original_state = $original?->get('field_event_state')->entity ?? NULL;

    return [
      'bookingId' => (int) $booking->id(),
      'roomName' => $unit?->label() ?? '',
      'address' => $unit?->get('field_address')->value ?? '',
      'managerEmail' => $unit?->get('field_manager_email')->value ?? '',
      'requesterEmail' => $booking->get('field_requester_email')->value ?? '',
      'checkInDate' => $this->formatDate($booking->get('booking_start_date')->value ?? ''),
      'checkOutDate' => $this->formatDate($booking->get('booking_end_date')->value ?? ''),
      'checkInTime' => $booking->hasField('field_check_in_time') ? (string) $booking->get('field_check_in_time')->value : '',
      'checkOutTime' => $booking->hasField('field_check_out_time') ? (string) $booking->get('field_check_out_time')->value : '',
      'details' => $booking->hasField('field_booking_details') ? (string) $booking->get('field_booking_details')->value : '',
      'status' => $state?->get('machine_name')->value ?? '',
      'statusLabel' => $state?->label() ?? '',
      'previousStatus' => $original_state?->get('machine_name')->value ?? '',
      'previousStatusLabel' => $original_state?->label() ?? '',
      'bedType' => $event?->get('field_bed_type')->value ?? '',
    ];
  }

  /**
   * Sends one email.
   */
  protected function send(string $key, string $to, array $context): void {
    $to = trim(strtolower($to));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
      return;
    }

    $result = $this->mailManager->mail(
      'hous_z_api',
      $key,
      $to,
      $this->languageManager->getDefaultLanguage()->getId(),
      ['context' => $context],
      NULL,
      TRUE,
    );

    if (empty($result['result'])) {
      $this->logger->error('Failed sending "@key" email to @to for booking @booking_id.', [
        '@key' => $key,
        '@to' => $to,
        '@booking_id' => $context['bookingId'] ?? 'unknown',
      ]);
    }
  }

  /**
   * Formats storage values to API-friendly dates.
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
