<?php

/**
 * @file
 * Post update functions for the Hous Z API module.
 */

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Adds extra booking fields used by the API contract.
 */
function hous_z_api_post_update_add_booking_metadata_fields(&$sandbox): void {
  $definitions = [
    'field_booking_details' => [
      'type' => 'string_long',
      'label' => 'Booking Details',
      'settings' => [],
    ],
    'field_check_in_time' => [
      'type' => 'string',
      'label' => 'Check-in Time',
      'settings' => ['max_length' => 32],
    ],
    'field_check_out_time' => [
      'type' => 'string',
      'label' => 'Check-out Time',
      'settings' => ['max_length' => 32],
    ],
  ];

  foreach ($definitions as $field_name => $definition) {
    if (!FieldStorageConfig::loadByName('bat_booking', $field_name)) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'bat_booking',
        'type' => $definition['type'],
        'settings' => $definition['settings'],
        'cardinality' => 1,
      ])->save();
    }

    if (!FieldConfig::loadByName('bat_booking', 'standard', $field_name)) {
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'bat_booking',
        'bundle' => 'standard',
        'label' => $definition['label'],
        'required' => FALSE,
      ])->save();
    }
  }
}
