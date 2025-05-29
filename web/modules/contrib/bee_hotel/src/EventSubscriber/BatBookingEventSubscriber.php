<?php

namespace Drupal\bee_hotel\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\bat_booking\Event\DeleteBatBooking;

/**
 * Interact with BatBooking.
 */
class BatBookingEventSubscriber implements EventSubscriberInterface {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new BatBookingEventSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {

    $events = [
      DeleteBatBooking::EVENT_NAME => 'batBookingDeleteBooking',
    ];

    return $events;
  }

  /**
   * Finalise delete event.
   *
   * @param \Drupal\bat_booking\Event\DeleteBatBooking $event
   *   The workflow transition event.
   */
  public function batBookingDeleteBooking(DeleteBatBooking $event) {
    $data = [];
    $data['batEventId'] = $event->booking->get("booking_event_reference")->target_id;
    $data['batEvent'] = bat_event_load($data['batEventId'], $reset = FALSE);
  }

}
