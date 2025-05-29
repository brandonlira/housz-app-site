<?php

namespace Drupal\bat_booking_example\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;

/**
 * Description message.
 */
class BatBookingExampleController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * This Method misses a description.
   */
  public function batBookingConfirmationPage($start_date, $end_date, $type_id) {
    $header = $start_date->format('Y-m-d') . ' - ' . $end_date->format('Y-m-d');
    $form = $this->formBuilder()->getForm('Drupal\bat_booking_example\Form\BookingConfirmationForm', $start_date, $end_date, $type_id);

    return [
      '#theme' => 'booking_confirmation_page',
      '#header' => $header,
      '#form' => $form,
    ];
  }

}
