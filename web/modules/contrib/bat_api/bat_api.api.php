<?php

/**
 * @file
 * Bat api file.
 *
 * This file contains no working PHP code; it exists to provide additional
 * documentation for doxygen as well as to document hooks in the standard
 * Drupal manner.
 */

/**
 * Alter units index calendar.
 *
 * @param array $units
 *   Array of events.
 */
function hook_bat_api_units_index_calendar_alter(array &$units) {
  // No example.
}

/**
 * Alter events index calendar.
 *
 * @param array $events
 *   Events array.
 * @param array $context
 *   Context array.
 */
function hook_bat_api_events_index_calendar_alter(array &$events, array $context) {
  // No example.
}

/**
 * Alter matching units calendar.
 *
 * @param array $events
 *   Events array.
 * @param array $context
 *   Context array.
 */
function hook_bat_api_matching_units_calendar_alter(array &$events, array $context) {
  // No example.
}
