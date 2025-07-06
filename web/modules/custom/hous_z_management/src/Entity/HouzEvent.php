<?php

namespace Drupal\hous_z_management\Entity;

use Drupal\bat_event\Entity\Event;

/**
 * Extends BAT Event entity to add proper label functionality.
 */
class HouzEvent extends Event {

  /**
   * {@inheritdoc}
   */
  public function label() {
    // Generate a label based on event ID and dates
    $dates = $this->get('event_dates')->getValue();
    if (!empty($dates)) {
      $start_date = isset($dates[0]['value']) ? $dates[0]['value'] : '';
      $end_date = isset($dates[0]['end_value']) ? $dates[0]['end_value'] : '';
      if ($start_date && $end_date) {
        return t('Event @id (@start to @end)', [
          '@id' => $this->id(),
          '@start' => date('Y-m-d', strtotime($start_date)),
          '@end' => date('Y-m-d', strtotime($end_date))
        ]);
      }
    }
    return t('Event @id', ['@id' => $this->id()]);
  }

}