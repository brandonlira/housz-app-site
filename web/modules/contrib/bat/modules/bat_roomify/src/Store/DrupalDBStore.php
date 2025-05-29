<?php

/**
 * @file
 * Class DrupalDBStore
 */

namespace Drupal\bat_roomify\Store;

use Drupal\bat_roomify\Event\EventInterface;
use Drupal\bat_roomify\Event\Event;
use Drupal\bat_roomify\Event\EventItemizer;
use Drupal\bat_roomify\Store\SqlDBStore;
use Drupal\Core\Database\Connection;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * This is a Drupal-specific implementation of the Store.
 *
 */
class DrupalDBStore extends SqlDBStore {

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;


  /**
   * Constructs a new Event object.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  // public function __construct(LoggerChannelFactoryInterface $logger_factory, Connection $database) {
  //   $this->loggerFactory = $logger_factory;
  //   $this->database = $database;
  // }
  //
  // /**
  //  * {@inheritdoc}
  //  */
  // public static function create(ContainerInterface $container) {
  //   return new static(
  //     $container->get('logger.factory'),
  //     $container->get('database'),
  //   );
  // }



  /**
   *
   * @param \DateTime $start_date
   * @param \DateTime $end_date
   * @param $unit_ids
   *
   * @return array
   */
  public function getEventData(\DateTime $start_date, \DateTime $end_date, $unit_ids) {

    $queries  = $this->buildQueries($start_date, $end_date, $unit_ids);

    $results = array();
    // Run each query and store results
    foreach ($queries as $type => $query) {
      $results[$type] = \Drupal\Core\Database\Database::getConnection()->query($query);
    }

    $db_events = array();

    // Cycle through day results and setup an event array
    while( $data = $results[Event::BAT_DAY]->fetchAssoc()) {
      // Figure out how many days the current month has
      $temp_date = new \DateTime($data['year'] . "-" . $data['month']);
      $days_in_month = (int)$temp_date->format('t');
      for ($i = 1; $i<=$days_in_month; $i++) {
        $db_events[$data['unit_id']][Event::BAT_DAY][$data['year']][$data['month']]['d' . $i] = $data['d'.$i];
      }
    }

    // With the day events taken care off let's cycle through hours
    while( $data = $results[Event::BAT_HOUR]->fetchAssoc()) {
      for ($i = 0; $i<=23; $i++) {
        $db_events[$data['unit_id']][Event::BAT_HOUR][$data['year']][$data['month']]['d' . $data['day']]['h'. $i] = $data['h'.$i];
      }
    }

    // With the hour events taken care off let's cycle through minutes
    while( $data = $results[Event::BAT_MINUTE]->fetchAssoc()) {
      for ($i = 0; $i<=59; $i++) {
        if ($i <= 9) {
          $index = 'm0'.$i;
        } else {
          $index = 'm'.$i;
        }
        $db_events[$data['unit_id']][Event::BAT_MINUTE][$data['year']][$data['month']]['d' . $data['day']]['h' . $data['hour']][$index] = $data[$index];
      }
    }

    return $db_events;
  }

  /**
   * @param \Drupal\bat_roomify\Event\EventInterface $event
   * @param $granularity
   *
   * @return bool
   */

  public function storeEvent(EventInterface $event, $granularity = Event::BAT_HOURLY) {

    $stored = TRUE;

    $connection = \Drupal::database();
    $transaction = $connection->startTransaction();

    $unitId = (int) $event->getUnitId();

    // Get existing event data from db
    $existing_events = $this->getEventData($event->getStartDate(), $event->getEndDate(), [$unitId] );

    try {
      // Itemize an event so we can save it
      $itemized = $event->itemize(new EventItemizer($event, $granularity));

      foreach ($itemized[Event::BAT_DAY] as $year => $months) {

        foreach ($months as $month => $days) {
          if ($granularity === Event::BAT_HOURLY) {
            foreach ($days as $day => $value) {
              $day = (int) $day;
              $this->itemizeSplitDay($existing_events, $itemized, $value, $unitId, $year, $month, $day);
            }
          }

          \Drupal\Core\Database\Database::getConnection()->merge($this->day_table_no_prefix)
            ->keys([
              'unit_id' => $unitId,
              'year' => $year,
              'month' => $month
            ])
            ->fields($days)
            ->execute();
        }
      }

      if (($granularity == Event::BAT_HOURLY) && isset($itemized[Event::BAT_HOUR])) {

        foreach ($itemized[Event::BAT_HOUR] as $year => $months) {
          foreach ($months as $month => $days) {

            foreach ($days as $day => $hours) {

              // Count required as we may receive empty hours for granular events that start and end on midnight
              if (count($hours) > 0) {
                foreach ($hours as $hour => $value){
                  $this->itemizeSplitHour($existing_events, $itemized, $value, $unitId, $year, $month, $day, $hour);
                }

                $day_sql = (int) substr($day, 1);

                $res = \Drupal\Core\Database\Database::getConnection()->merge($this->hour_table_no_prefix)
                  ->keys([
                    'unit_id' => $unitId,
                    'year' => $year,
                    'month' => $month,
                    'day' => $day_sql,
                  ])
                  ->fields($hours)
                  ->execute();
              }
            }
          }
        }

        // If we have minutes write minutes
        foreach ($itemized[Event::BAT_MINUTE] as $year => $months) {
          foreach ($months as $month => $days) {
            foreach ($days as $day => $hours) {
              foreach ($hours as $hour => $minutes) {
              \Drupal\Core\Database\Database::getConnection()->merge($this->minute_table_no_prefix)
                ->keys(array(
                  'unit_id' => $unitId,
                  'year' => $year,
                  'month' => $month,
                  'day' => (int) substr($day, 1),
                  'hour' => substr($hour, 1)
                ))
                ->fields($minutes)
                ->execute();
              }
            }
          }
        }
      }
    }

    catch (Exception $e) {

      $stored = FALSE;

      // There was an error in writing to the database, so the database is rolled back
      // to the state when the transaction was started.
      $transaction->rollBack();

      \Drupal::logger('bat_roomify')->error('BAT Event Save Exception');
      \Drupal::logger('bat_roomify')->error($e->getMessage());
    }

    // Commit the transaction by unsetting the $transaction variable.
    unset($transaction);
    return $stored;

  }

}
