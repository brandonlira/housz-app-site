<?php

namespace Drupal\bat_booking\Plugin\views\field;

use Drupal\views\ResultRow;
use Drupal\views\Plugin\views\field\FieldPluginBase;

/**
 * Some description.
 *
 * @ViewsField("bat_booking_handler_night_field")
 */
class BatBookingHandlerNightField extends FieldPluginBase {

  /**
   * This Method misses a description.
   */
  public function query() {
  }

  /**
   * This Method misses a description.
   */
  public function render(ResultRow $values) {
    $booking = $this->getEntity($values);

    $start_date = new \DateTime($booking->get('booking_start_date')->getValue()[0]['value']);
    $end_date = new \DateTime($booking->get('booking_end_date')->getValue()[0]['value']);

    return $end_date->diff($start_date)->days;
  }

}
