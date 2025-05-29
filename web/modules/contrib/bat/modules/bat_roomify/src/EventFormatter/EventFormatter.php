<?php

/**
 * @file
 * Interface EventFormatter
 */

namespace Drupal\bat_roomify\EventFormatter;

use Drupal\bat_roomify\Event\EventInterface;

interface EventFormatter {

  /**
   * @param \Drupal\bat_roomify\Event\EventInterface $event
   */
  public function format(EventInterface $event);

}
