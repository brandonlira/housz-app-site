<?php

namespace Drupal\beehotel_event\Util;

use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Defines  the Utility EventMaintenance class.
 */
class EventMaintenance {

  use StringTranslationTrait;

  /**
   * Remove old events from database.
   */
  public function deleteOldBatEvents($options) {

    if (!isset($options['daysago'])) {
      $options['daysago'] = 20;
    }

    if (!isset($options['howmany'])) {
      $options['howmany'] = 600;
    }

    $date = new DrupalDateTime($options['daysago'] . ' days ago');
    $date->setTimezone(new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE));
    $formatted = $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    // Never delete recent events.
    $protection_date = new DrupalDateTime('10 days ago');
    $protection_date->setTimezone(new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE));
    $formatted_protection_date = $protection_date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    $query = \Drupal::entityQuery('bat_event')->accessCheck(FALSE);
    $count_pre = $query->count()->execute();

    $ids = \Drupal::entityQuery('bat_event')
      ->condition('event_dates.value', $formatted, '<')
      ->condition('event_dates.value', $formatted_protection_date, '<')
      ->range(0, $options['howmany'])
      ->accessCheck(FALSE)
      ->execute();

    bat_event_delete_multiple($ids);

    $query = \Drupal::entityQuery('bat_event');
    $count_post = $query->count()->execute();

    $tmp = [
      "%c" => $options['howmany'],
      "%older" => $options['daysago'],
      "%remain" => $count_post,
      "%count_pre" => $count_pre,
    ];

    $message = $this->t("counter_pre : [ %counter_pre ].N. %c bat_event(s)  older than %older days deleted. %remain bat_event(s) still in DB", $tmp);

    \Drupal::logger('beehotel_event')->notice($message);

  }

}
