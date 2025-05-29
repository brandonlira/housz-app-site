<?php

namespace Drupal\bat_unit;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining Unit entities.
 *
 * @ingroup bat
 */
interface UnitInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface, EntityUnitTypeInterface {

  /**
   * Description.
   */
  public function getUnitType();

  /**
   * Description.
   */
  public function getUnitTypeId();

  /**
   * Description.
   */
  public function setUnitTypeId($utid);

  /**
   * Description.
   */
  public function setUnitType(UnitTypeInterface $unit_type);

}
