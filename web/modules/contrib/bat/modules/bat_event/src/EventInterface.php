<?php

namespace Drupal\bat_event;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\bat_unit\UnitInterface;

/**
 * Provides an interface for defining Event entities.
 *
 * @ingroup bat
 */
interface EventInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * This Method misses a description.
   */
  public function getStartDate();

  /**
   * This Method misses a description.
   */
  public function getEndDate();

  /**
   * This Method misses a description.
   */
  public function getUnit();

  /**
   * This Method misses a description.
   */
  public function getUnitId();

  /**
   * This Method misses a description.
   */
  public function setUnitId($unit_id);

  /**
   * This Method misses a description.
   */
  public function setUnit(UnitInterface $unit);

  /**
   * This Method misses a description.
   */
  public function setStartDate(\DateTime $date);

  /**
   * This Method misses a description.
   */
  public function setEndDate(\DateTime $date);

}
