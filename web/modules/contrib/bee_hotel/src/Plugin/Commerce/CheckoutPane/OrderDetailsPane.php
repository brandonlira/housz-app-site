<?php

namespace Drupal\bee_hotel\Plugin\Commerce\CheckoutPane;

use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a custom message pane.
 *
 * @CommerceCheckoutPane(
 *   id = "bee_hotel_pane_order_details",
 *   label = @Translation("Order Details Pane by bee_hotel"),
 * )
 */
class OrderDetailsPane extends CheckoutPaneBase {

  /**
   * {@inheritdoc}
   */
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $pane_form['message'] = [
      '#markup' => $this->t('Please check and confirm your reservation.'),
    ];
    return $pane_form;
  }

}
