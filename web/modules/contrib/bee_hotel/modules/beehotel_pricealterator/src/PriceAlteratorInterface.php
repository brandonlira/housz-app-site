<?php

namespace Drupal\beehotel_pricealterator;

/**
 * The interface for PriceAlterator type plugins.
 */
interface PriceAlteratorInterface {

  /**
   * Alter price into \$data.
   *
   * @param array $data
   *   Array of data related to this price.
   * @param array $pricetable
   *   Array of prices by week day.
   *
   * @return array
   *   An updated $data array.
   */
  public function alter(array $data, array $pricetable);

}
