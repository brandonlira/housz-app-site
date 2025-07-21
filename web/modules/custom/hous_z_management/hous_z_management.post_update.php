<?php

/**
 * @file
 * Post update functions for Housz Management module.
 */

use Drupal\Core\Entity\EntityStorageException;

/**
 * Create confirmed bat event state.
 */
function hous_z_management_post_update_create_confirmed_state(&$sandbox) {
  $entity_type_manager = \Drupal::entityTypeManager();
  $state_storage = $entity_type_manager->getStorage('state');

  // Check if confirmed state already exists
  $existing = $state_storage->loadByProperties([
    'machine_name' => 'confirmed',
    'event_type' => 'availability_daily',
  ]);

  if (empty($existing)) {
    try {
      $state = $state_storage->create([
        'name' => 'Confirmed',
        'machine_name' => 'confirmed',
        'event_type' => 'availability_daily',
        'color' => '#28a745',
        'calendar_label' => 'CONF',
        'blocking' => 1,
        'locked' => 0,
      ]);
      $state->save();

      \Drupal::logger('hous_z_management')->info('Created confirmed bat event state.');
    }
    catch (EntityStorageException $e) {
      \Drupal::logger('hous_z_management')->error(
        'Failed to create confirmed state: @message', ['@message' => $e->getMessage()
        ]
      );
    }
  }
}

/**
 * Create cancelled bat event state.
 */
function hous_z_management_post_update_create_cancelled_state(&$sandbox) {
  $entity_type_manager = \Drupal::entityTypeManager();
  $state_storage = $entity_type_manager->getStorage('state');

  // Check if cancelled state already exists
  $existing = $state_storage->loadByProperties([
    'machine_name' => 'cancelled',
    'event_type' => 'availability_daily',
  ]);

  if (empty($existing)) {
    try {
      $state = $state_storage->create([
        'name' => 'Cancelled',
        'machine_name' => 'cancelled',
        'event_type' => 'availability_daily',
        'color' => '#dc3545',
        'calendar_label' => 'CANC',
        'blocking' => 0,
        'locked' => 0,
      ]);
      $state->save();

      \Drupal::logger('hous_z_management')->info('Created cancelled bat event state.');
    }
    catch (EntityStorageException $e) {
      \Drupal::logger('hous_z_management')->error(
        'Failed to create cancelled state: @message', ['@message' => $e->getMessage()
        ]
      );
    }
  }
}
