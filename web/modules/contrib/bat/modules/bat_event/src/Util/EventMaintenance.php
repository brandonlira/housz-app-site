<?php

namespace Drupal\bat_event\Util;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Define a maintenance class.
 *
 * Defines the Utility EventMaintenance class.
 * Cloned from https://www.drupal.org/project/bee_hotel.
 */
class EventMaintenance {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;


  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, Connection $database) {
    $this->entityTypeManager = $entity_type_manager;
     $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('database'),
    );
  }

  /**
   * Remove old events from database.
   */
  public function deleteOldBatEvents($options) {

    // Delete old records from related tables.
    $data['related_tables'] = $this->deleteFromRelatedTables($options['days_back'], $minYear = 2000);

    // Note: this only works on healthy entities (data integrity).
    $data = [];
    $date = new DrupalDateTime($options['days_back'] . ' days ago');
    $date->setTimezone(new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE));
    $formatted = $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    $query = \Drupal::entityQuery('bat_event');
    $query->accessCheck(TRUE);
    $data['count_pre'] = $query->count()->execute();

    $data['ids'] = \Drupal::entityQuery('bat_event')
      ->accessCheck(TRUE)
      ->condition('event_dates.end_value', $formatted, '<')
      ->range(0, $options['how_many_per_cron'])
      ->execute();

    if (!isset($data['ids']) || count($data['ids']) < 1) {
      \Drupal::logger('bat_event')->notice($this->t("No old event to delete"));
      return FALSE;
    }

    bat_event_delete_multiple($data['ids']);

    $query = \Drupal::entityQuery('bat_event');
    $query->accessCheck(TRUE);
    $data['count_pre'] = $query->count()->execute();

    $tmp = [
      "%c" => $options['how_many_per_cron'],
      "%older" => $options['days_back'],
      "%remain" => $data['count_pre'],
      "%count_pre" => $data['count_pre'],
    ];

    $output = "counter pre: [ %counter_pre ]<br>\r\n";
    $output .= "how many per cron: [%c]<br>\r\n";
    $output .= "delete events older than  [%c] days<br>\r\n";
    $output .= "counter post: : [%remain]<br>\r\n";
    $message = $this->t($output, $tmp);

    \Drupal::logger('bat_event')->notice($message);
    return TRUE;
  }

  /**
   * Return BAT event tables.
   */
  public function batTables($options) {
    $data = [];

    // @todo get this from entity annotation.
    $main = "event";

    $database = \Drupal::database();
    $schema = $database->schema();
    $data['related'] = $schema->findTables('bat_event_%');

    foreach ($data['related'] as $key => $related) {
      $number_of_rows = $database->select($related)->countQuery()->execute()->fetchField();
      $data['related'][$key] = $number_of_rows;
    }

    ksort($data['related']);

    $tables['main'] = [
      $main => $database->select($main)->countQuery()->execute()->fetchField(),
    ];

    $tables = [
      'main' => [
        $main => $database->select($main)->countQuery()->execute()->fetchField(),
      ],
      'related' => $data['related'],
    ];
    return $tables;
  }


 /**
  * Delete old records from event availability daily tables.
  *
  * @param int $daysBack Number of days to retain data (e.g., 360)
  * @param int $minYear Optional minimum year to prevent accidental deletion of all data
  * @return array Result with deletion counts
  */

  private function deleteFromRelatedTables($daysBack, $minYear = 2000) {

      try {
          // Calculate the cutoff year based on days back from current date using DrupalDateTime
          $cutoffDate = new \Drupal\Core\Datetime\DrupalDateTime();
          $cutoffDate->modify("-$daysBack days");
          $cutoffYear = (int)$cutoffDate->format('Y');

          // Safety check - ensure we're not deleting all data
          if ($cutoffYear < $minYear) {
              throw new \InvalidArgumentException("Cutoff year $cutoffYear is below minimum allowed year $minYear");
          }

          // Get database connection
          $db = $this->database;

          // First, count records that will be deleted (optional, for logging)
          $countEvent = $db->query(
              "SELECT COUNT(*) FROM {bat_event_availability_daily_day_event} WHERE year < :year",
              [':year' => $cutoffYear]
          )->fetchField();

          $countState = $db->query(
              "SELECT COUNT(*) FROM {bat_event_availability_daily_day_state} WHERE year < :year",
              [':year' => $cutoffYear]
          )->fetchField();

          // Perform deletions using Drupal's delete query
          $rowsDeletedEvent = $db->delete('bat_event_availability_daily_day_event')
              ->condition('year', $cutoffYear, '<')
              ->execute();

          $rowsDeletedState = $db->delete('bat_event_availability_daily_day_state')
              ->condition('year', $cutoffYear, '<')
              ->execute();

          $result = [
              'cutoff_year' => $cutoffYear,
              'event_table_deleted' => $rowsDeletedEvent,
              'state_table_deleted' => $rowsDeletedState,
              'total_deleted' => $rowsDeletedEvent + $rowsDeletedState,
              'event_table_expected' => $countEvent,
              'state_table_expected' => $countState
          ];

          // Log results using bat_event module logger
          \Drupal::logger('bat_event')->info(
              "Event availability cleanup: Deleted @event_deleted/@event_expected records from event table and @state_deleted/@state_expected records from state table for years before @cutoff_year",
              [
                  '@event_deleted' => $rowsDeletedEvent,
                  '@event_expected' => $countEvent,
                  '@state_deleted' => $rowsDeletedState,
                  '@state_expected' => $countState,
                  '@cutoff_year' => $cutoffYear
              ]
          );

          return $result;

      } catch (\Exception $e) {
          \Drupal::logger('bat_event')->error("Error in deleteFromRelatedTables: @message",
              ['@message' => $e->getMessage()]
          );
          throw $e;
      }
  }

}
