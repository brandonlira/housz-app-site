<?php

/**
 * @file
 * Class Unit
 */

namespace Drupal\bat_roomify\Unit;

use Drupal\bat_roomify\Unit\AbstractUnit;

/**
 * The basic BAT unit class.
 */
class Unit extends AbstractUnit {

  /**
   * @param $unit_id
   * @param $default_value
   * @param $constraints
   */
  public function __construct($unit_id, $default_value, $constraints = array()) {
    $this->unit_id = $unit_id;
    $this->default_value = $default_value;
    $this->constraints = $constraints;
  }

}
