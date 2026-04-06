<?php

namespace Drupal\bee_hotel;

use Drupal\bat_event\Entity\Event as EntityEvent;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Utilities for Bat events in the bee hotel environment.
 */
class Event {

  /**
   * The bee hotel unit.
   *
   * @var \Drupal\bee_hotel\BeeHotelBat
   */
  protected $beeHotelBat;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

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
   * Include the messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new Event object.
   *
   * @param \Drupal\bee_hotel\BeeHotelBat $beehotel_bat
   *   The bee hotel bat service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(
    BeeHotelBat $beehotel_bat,
    LoggerChannelFactoryInterface $logger_factory,
    EntityTypeManagerInterface $entity_type_manager,
    Connection $database,
    MessengerInterface $messenger
  ) {
    $this->beeHotelBat = $beehotel_bat;
    $this->loggerFactory = $logger_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('bee_hotel.beehotelbat'),
      $container->get('logger.factory'),
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('messenger')
    );
  }

  /**
   * Moved from bee_hotel.module as bee_hotel_get_day_status.
   *
   * @param array $data
   *   The data array containing day and unit information.
   *
   * @return mixed
   *   The state ID or NULL if not found.
   */
  public function getNightState(array $data) {
    $query = $this->database->select('bat_event_availability_daily_day_state', 'statetable');

    // Fields.
    $query->addField('statetable', 'd' . ($data['day']['d'] * 1), 'd' . ($data['day']['d'] * 1));

    // Conditions.
    $query->condition('statetable.year', $data['day']['year'], '=');
    $query->condition('statetable.month', $data['day']['month'], '=');
    $query->condition('statetable.unit_id', $data['unit']['bid'], '=');

    $result = $query->execute()->fetch();

    if ($result !== FALSE) {
      $day = ($data['day']['d'] * 1);
      $d = "d" . $day;
      if (!empty($state_id = $result->$d)) {
        return $state_id;
      }
      return NULL;
    }
  }

  /**
   * Set a state to a give night/bat unit.
   *
   * This method updates bat_event_availability_daily_day_state.
   *
   * @param array $data
   *   The data array containing day, unit, and new state information.
   */
  public function setNightState(array $data) {
    $this->loggerFactory->get(__METHOD__)->notice('<pre>' . print_r($data, TRUE) . '</pre>');

    $query = $this->database->update('bat_event_availability_daily_day_state');

    $query->fields([
      'd' . $data['day']['day'] => $data['new_state'],
    ]);

    // Conditions.
    $query->condition('year', $data['day']['year'], '=');
    $query->condition('month', $data['day']['month'], '=');
    $query->condition('unit_id', $data['unit']['bid'], '=');
    $query->execute();
  }

  /**
   * For a given night/unit, get event id.
   *
   * @param array $data
   *   The data array containing day and unit information.
   *
   * @return mixed
   *   The event ID or NULL if not found.
   */
  public function getNightEvent(array $data) {
    $id = $this->beeHotelBat->getUnitDateBatEventId($data);

    // Events in BeeHotel MUST be one night long.
    if (isset($id)) {
      $event = $this->entityTypeManager->getStorage('bat_event')->load($id);
      if (isset($event)) {
        $start_date = new \DateTime($event->get("event_dates")->value);
        $end_date = new \DateTime($event->get("event_dates")->end_value);
        $duration = $start_date->diff($end_date);

        /**
         * 21oct25 - suspended
           if ($duration->format('%a') > 1) {
           // @bug: I delete a reservation (commerce order) and a long event as unavailable is created.
           $this->messenger->addMessage('Events below can\'t be longer than 1 day. Check and fix values');
              (new RedirectResponse("/admin/bat/events/event/" . $event->Id() . "/edit"))->send();
          }
        */

      }
      return $id;
    }
    return NULL;
  }

  /**
   * Let's see what's type of occupancy is this.
   *
   * @param array $data
   *   The data array containing day and unit information.
   *
   * @return array
   *   The occupancy data array.
   */
  public function typeofOccupacy(array $data) {
    $query = $this->database->select('bat_event_availability_daily_day_state', 'montlytable');

    // Fields.
    $query->addField('montlytable', 'd' . ($data['day']['d'] * 1), 'd' . ($data['day']['d'] * 1));

    // Conditions.
    $query->condition('montlytable.unit_id', $data['unit']['bid'], '=');
    $query->condition('montlytable.year', $data['day']['year'], '=');
    $query->condition('montlytable.month', $data['day']['month'], '=');
    $query->condition('montlytable.d' . ($data['day']['d'] * 1), 0, '<>');

    // Range.
    $query->range(0, 1);

    // State id as result.
    $state_id = $query->execute()->fetchField(0);

    // Get event ID.
    $data['tmp']['event_id'] = $this->beeHotelBat->getUnitDateBatEventId($data);

    // Populate $data.
    $data['occupancy']['current']['event']['id'] = $data['tmp']['event_id'];
    $data['occupancy']['current']['event']['state'] = $this->getEventState($state_id);

    // Get order.
    $data['tmp']['order'] = $data['occupancy']['current']['order'] = $this->beeHotelBat->getOrderFromEvent($data['occupancy']['current']['event']['id']);

    // 20oct2025: double row. Removed.
    // $data['tmp']['event_id'] = $this->beeHotelBat
    // ->getUnitDateBatEventId($data);
    // Do we have occupancy tonight?
    if (isset($data['tmp']['order']->checkout)) {
      if (strtotime($data['tmp']['order']->checkout) >= strtotime($data['day']['today']['ISO8601'])) {
        // Given ISO 8601/unit.
        $data['occupancy'][$data['day']['today']['ISO8601']][$data['unit']['bid']]['event']['id'] = $data['tmp']['event_id'];
        $data['occupancy'][$data['day']['today']['ISO8601']][$data['unit']['bid']]['event']['state'] = $this->getEventState($state_id);
        $data['occupancy'][$data['day']['today']['ISO8601']][$data['unit']['bid']]['order'] = $data['tmp']['order'];
        $data['occupancy'][$data['day']['today']['ISO8601']][$data['unit']['bid']]['qqqqq'] = 'pppppp';
      }
      else {
        // This *SHOULD* be a today checkout. To be tested. 20oct2025.
        $data['occupancy'][$data['day']['today']['ISO8601']][$data['unit']['bid']]['checkout'] = TRUE;
      }
    }

    return $data;
  }

  /**
   * Let's see the id event state.
   *
   * @param int $state_id
   *   The state ID.
   *
   * @return mixed
   *   The state data or FALSE if not found.
   */
  public function getEventState($state_id) {
    $query = $this->database->select('states', 's');

    // Fields.
    $query->addField('s', 'blocking');
    $query->addField('s', 'name');
    $query->addField('s', 'color');
    $query->addField('s', 'calendar_label');
    $query->addField('s', 'id');

    // Conditions.
    $query->condition('s.id', $state_id, '=');
    $result = $query->execute()->fetch();
    return $result;
  }

  /**
   * Returns the event duration.
   *
   * @param \Drupal\bat_event\Entity\Event $entityEvent
   *   An event object.
   * @param array $options
   *   An array of options.
   *
   * @return int|false
   *   Return seconds as integer or FALSE.
   */
  public function getEventLength(EntityEvent $entityEvent, array $options) {
    $data = [];
    if ($options['output'] == "timestamp") {
      $data['from'] = (new DrupalDateTime($entityEvent->get("event_dates")[0]->value))->getTimestamp();
      $data['to'] = (new DrupalDateTime($entityEvent->get("event_dates")[0]->end_value))->getTimestamp();
      $data['diff'] = $data['to'] - $data['from'];
    }
    return $data['diff'];
  }

  /**
   * Let's see if this event is blocking the unit.
   *
   * @param int $state_id
   *   The state ID.
   *
   * @return mixed
   *   The blocking status or NULL.
   */
  public function eventIsBlocking($state_id) {
    $data = [];
    $data['state']['id'] = $state_id;
    $data['states'] = bat_event_get_states();
    $data['state']['details'] = $data['states'][$data['state']['id']];
    $data['state']['is_blocking'] = $data['state']['details']->get('blocking')->value;
    return $data['state']['is_blocking'];
  }

  /**
   * Let's see if this event is related to an order.
   *
   * @param int $event_id
   *   The event ID.
   *
   * @return mixed
   *   The order data or NULL.
   */
  public function eventHasOrder($event_id) {
    $data = [];
    $data['order'] = $this->beeHotelBat->getOrderFromEvent($event_id);
    return $data['order'];
  }

  /**
   * Remove old events we don't need anymore.
   *
   * @param array $options
   *   An array of options with keys:
   *   - daysago: Number of days ago to purge from.
   *   - howmany: Maximum number of events to purge.
   */
  public function purgeOldBatEvents(array $options) {
    if (!isset($options['daysago'])) {
      // @todo move this inside a config form page.
      $options['daysago'] = 20;
    }

    if (!isset($options['howmany'])) {
      // @todo move this inside a config form page.
      $options['howmany'] = 600;
    }

    $date = new DrupalDateTime($options['daysago'] . ' days ago');
    $date->setTimezone(new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE));
    $formatted = $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    $storage = $this->entityTypeManager->getStorage('bat_event');
    $count_pre = $storage->count()->execute();

    $ids = $storage
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('event_dates.value', $formatted, '<')
      ->range(0, $options['howmany'])
      ->execute();

    if (!empty($ids)) {
      $storage->delete($storage->loadMultiple($ids));
    }

    $count_post = $storage->count()->execute();

    $tmp['options'] = [
      "%c" => $options['howmany'],
      "%older" => $options['daysago'],
      "%remain" => $count_post,
      "%count_pre" => $count_pre,
    ];

    $message = $this->t("counter_pre : [ %count_pre ].N. %c bat_event(s) older than %older days deleted. %remain bat_event(s) still in DB", $tmp['options']);
    $this->loggerFactory->get('beehotel')->notice($message);
  }

}
