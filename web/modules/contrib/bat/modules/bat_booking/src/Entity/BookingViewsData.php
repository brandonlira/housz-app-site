<?php

namespace Drupal\bat_booking\Entity;

use Drupal\views\EntityViewsData;
use Drupal\views\EntityViewsDataInterface;

/**
 * Description message.
 */
class BookingViewsData extends EntityViewsData implements EntityViewsDataInterface {

  /**
   * {@inheritdoc}
   */
  public function getViewsData() {
    $data = parent::getViewsData();

    $data['booking']['nights']['field'] = [
      'title' => $this->t('Nights'),
      'help' => $this->t('Provide number of nights.'),
      'id' => 'bat_booking_handler_night_field',
    ];

    return $data;
  }

}
