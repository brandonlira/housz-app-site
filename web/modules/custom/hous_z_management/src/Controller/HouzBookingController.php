<?php

namespace Drupal\hous_z_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides booking management functionality.
 */
class HouzBookingController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a HouzBookingController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MailManagerInterface $mail_manager, MessengerInterface $messenger) {
    $this->entityTypeManager = $entity_type_manager;
    $this->mailManager = $mail_manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.mail'),
      $container->get('messenger')
    );
  }

  /**
   * Confirms a booking.
   *
   * @param int $booking
   *   The booking ID.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response.
   */
  public function confirm($booking) {
    $booking_entity = $this->entityTypeManager
      ->getStorage('bat_booking')
      ->load($booking);

    if (!$booking_entity) {
      $this->messenger->addError($this->t('Booking not found.'));
      return new RedirectResponse(Url::fromRoute('hous_z_management.bookings')->toString());
    }

    // Update booking status (assuming there's a status field)
    if ($booking_entity->hasField('status')) {
      $booking_entity->set('status', 'confirmed');
      $booking_entity->save();
    }

    // Send confirmation email
    $this->sendBookingEmail($booking_entity, 'confirmed');

    $this->messenger->addStatus($this->t('Booking @id has been confirmed.', ['@id' => $booking]));

    return new RedirectResponse(Url::fromRoute('hous_z_management.bookings')->toString());
  }

  /**
   * Cancels a booking.
   *
   * @param int $booking
   *   The booking ID.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect response.
   */
  public function cancel($booking) {
    $booking_entity = $this->entityTypeManager
      ->getStorage('bat_booking')
      ->load($booking);

    if (!$booking_entity) {
      $this->messenger->addError($this->t('Booking not found.'));
      return new RedirectResponse(Url::fromRoute('hous_z_management.bookings')->toString());
    }

    // Update booking status
    if ($booking_entity->hasField('status')) {
      $booking_entity->set('status', 'cancelled');
      $booking_entity->save();
    }

    // Send cancellation email
    $this->sendBookingEmail($booking_entity, 'cancelled');

    $this->messenger->addStatus($this->t('Booking @id has been cancelled.', ['@id' => $booking]));

    return new RedirectResponse(Url::fromRoute('hous_z_management.bookings')->toString());
  }

  /**
   * Sends booking notification email.
   *
   * @param \Drupal\Core\Entity\EntityInterface $booking
   *   The booking entity.
   * @param string $status
   *   The booking status (confirmed/cancelled).
   */
  protected function sendBookingEmail($booking, $status) {
    $module = 'hous_z_management';
    $key = 'booking_' . $status;
    $to = $this->config('system.site')->get('mail');
    $langcode = $this->languageManager()->getCurrentLanguage()->getId();

    $params = [
      'booking' => $booking,
      'status' => $status,
    ];

    $result = $this->mailManager->mail($module, $key, $to, $langcode, $params, NULL, TRUE);

    if ($result['result'] !== TRUE) {
      $this->messenger->addError($this->t('Failed to send @status email.', ['@status' => $status]));
    }
  }

}