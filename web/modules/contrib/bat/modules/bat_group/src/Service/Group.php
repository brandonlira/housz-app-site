<?php

namespace Drupal\bat_group\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Manage Bat Groups.
 */
class Group {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager,) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Check unit belongs to group.
   *
   * @argument integer $unit_id
   * @argument integer $group_id
   *
   * @return bool
   *   Boolean indicating whether Unit belongs to Group
   *
   * @todo coding
   * .
   */
  public function unitBelongs($unit_id, $group_id) {
    return FALSE;
  }

  /**
   * Get active units in group.
   *
   * @argument integer $group_id
   *
   * @return array
   *   Array of unit ids related to a given group
   *
   * @todo coding
   */
  public function getUnits($group_id) {
    return [];
  }

  /**
   * List groups for a unit_id.
   *
   * @argument integer $unit_id
   *
   * @return array
   *   Array of group ids related to a given unit
   *
   * @todo coding
   */
  public function unitGroups($unit_id) {
    return [];
  }

}
