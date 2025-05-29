<?php

namespace Drupal\beehotel_sps;

use Drupal\commerce_price\Price;

/**
 * A class to apply slider to the base price.
 */
class ApplyPriceSlider {

  /**
   * Apply the value.
   *
   * The slider value is applied to base price.
   *
   * @return Drupal\commerce_price\Price|price
   *   a new price with slider value applied
   */
  public function apply($amount, $store) {
    $currency_code = $store->get('default_currency')->getValue()[0]['target_id'];
    $sps = $store->get('field_price_slider')->getValue();
    $sps = reset($sps);
    $tmp = $amount + ($amount / 100 * $sps['value']);
    $price_after_slider = new Price($tmp, $currency_code);
    return $price_after_slider;
  }

}
