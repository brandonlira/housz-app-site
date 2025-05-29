<?php

namespace Drupal\bat_booking\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\bat_booking\Entity\Booking;

/**
 * Event that is fired when a bat_booking is deleted.
 */
class DeleteBatBooking extends Event {

  const EVENT_NAME = 'bat_booking_delete_booking';

  /**
   * The booking object.
   *
   * @var \Drupal\bat_booking\Entity\Booking
   */
  public $booking;

  /**
   * Constructs a new DeleteBatBooking object.
   *
   * @param \Drupal\bat_booking\Entity\Booking $booking
   *   The booking event.
   */
  public function __construct(Booking $booking) {
    $this->booking = $booking;
  }

}
