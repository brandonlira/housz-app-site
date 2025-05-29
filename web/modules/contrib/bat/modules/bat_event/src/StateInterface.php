<?php

namespace Drupal\bat_event;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining State entities.
 */
interface StateInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface {

  /**
   * This Method misses a description.
   */
  public function getMachineName();

  /**
   * This Method misses a description.
   */
  public function getColor();

  /**
   * This Method misses a description.
   */
  public function getCalendarLabel();

  /**
   * This Method misses a description.
   */
  public function getBlocking();

  /**
   * This Method misses a description.
   */
  public function getEventType();

  /**
   * This Method misses a description.
   */
  public function setColor($color);

  /**
   * This Method misses a description.
   */
  public function setCalendarLabel($calendar_label);

  /**
   * This Method misses a description.
   */
  public function setBlocking($blocking);

}
