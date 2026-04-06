<?php

namespace Drupal\bee_hotel;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * @file
 * Contains \Drupal\bee_hotel\BeeHotelGuestMessageHooks.
 */

/**
 * Processes dynamic tokens for BeeHotel guest messaging system.
 *
 * This service provides token replacement functionality with hotel-specific
 * business logic. It handles the transformation of token placeholders into
 * actual values by executing corresponding methods based on token identifiers.
 * The class supports various token types including guest information,
 * financial calculations, and room details.
 *
 * Token processing follows a dynamic dispatch pattern where each token ID
 * maps to a corresponding private method. If no matching method is found,
 * a fallback message is returned.
 *
 * @ingroup bee_hotel
 *
 * @see \Drupal\bee_hotel\BeeHotelGuestMessageHooks::main()
 */
class BeeHotelGuestMessageHooks {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Include the messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new GuestMessageHooks service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(ConfigFactoryInterface $config_factory, MessengerInterface $messenger) {
    $this->configFactory = $config_factory;
    $this->messenger = $messenger;
  }

  /**
   * Use token hook or fallback.
   *
   * Every yml token should have a method here to elaborate the value.
   * This method search for the token method, falling back when
   * token method is missing.
   *
   * @param string $id
   *   The token id.
   * @param array $token
   *   The token.
   * @param array $d
   *   The data.
   *
   * @return string
   *   A string value for the token.
   */
  public function main($id, array $token, array $d) {

    if (method_exists($this, $id)) {
      return $this->$id($token, $d);
    }
    else {
      return "Missing method for " . $id;
    }

  }

  /**
   * Hook method.
   */
  private function balance_cash_euro($token, $d) {
    $tmp = $token['#value']['settings']['property'];
    $d['tokens'][$token['#value']['id']]['value'] = $d['commerce_order']->get($token['#value']['settings']['field'])->$tmp;
    $d['tokens'][$token['#value']['id']]['value'] = floor($d['tokens'][$token['#value']['id']]['value'] * 0.1) / 0.1;
    return $d['tokens'][$token['#value']['id']]['value'];
  }

  /**
   * Hook method.
   */
  private function balance_cash_currencies($token, &$d) {

    $d['config'] = $this->configFactory;
    $balance = $d['commerce_order']->get('balance')->number;
    $currencyApi = \Drupal::service('currencyapi.service');
    $rates = $currencyApi->getAllRates();

    $output = "<ul>";
    $output = "";
    foreach ($rates as $code => $value) {
      if ($value['is_default'] != TRUE) {
        $tmp = $currencyApi->convert($balance, 'EUR', $value['code']);
        $output .= ". " . $value['code'] . ": " . $tmp . "<br/>";
      }
    }
    return $output;
  }

  /**
   * Hook method.
   */
  private function guest_name($token, $d) {
    $d['billing_profile']['id'] = $d['commerce_order']->get("billing_profile")->target_id;
    $d['billing_profile']['entity'] = $d['commerce_order']->get("billing_profile")->entity;

    if (!isset($d['billing_profile']['id'])) {
      $route = "entity.commerce_order.edit_form";
      $url = Url::fromRoute($route, ['commerce_order' => $d['commerce_order']->id()]);
      $this->messenger->addMessage('Please add and save mandatory fields');
      return new RedirectResponse($url->toString());
    }

    $d['billing_profile']['address'] = $d['billing_profile']['entity']->address->getValue();
    $d['billing_profile']['given_name'] = $d['billing_profile']['entity']->address->getValue()[0]['given_name'];
    if (isset($d['billing_profile']['given_name'])) {
      return $d['billing_profile']['given_name'];
    }
    else {
      return $d['commerce_order']->get('mail')->value;
    }
  }

  /**
   * Hook method.
   */
  private function room_name($token, &$d) {
    $d['config'] = $this->configFactory;
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = \Drupal::entityTypeManager();
    $d['order_item'] = $entity_type_manager->getStorage('commerce_order_item')
      ->load($d['commerce_order']->get('order_items')->target_id);
    $d['room_name'] = $d['order_item']->get("title")->value;
    return $d['room_name'];
  }

}
