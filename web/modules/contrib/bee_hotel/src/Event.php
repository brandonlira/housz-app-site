<?php

namespace Drupal\bee_hotel;

use Drupal\bat_event\Entity\Event as EntityEvent;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Utilities for Bat events in the bee hotel enviroment.
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
   *   The logger factory.
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
      $container->get('messenger'),
    );
  }

  /**
   * Moved from bee_hotel.module as bee_hotel_get_day_status.
   */
  public function getNightState($data) {
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
      return;
    }
  }

  /**
   * Set a state to a give night/bat unit.
   *
   * This method updates bat_event_availability_daily_day_state.
   */
  public function setNightState($data) {

    $this->loggerFactory(__METHOD__)->notice('<pre>' . print_r($data, TRUE) . '</pre>');

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
   */
  public function getNightEvent($data) {

    $id = $this->beeHotelBat->getUnitDateBatEventId($data);

    // Events in BeeHotel MUST be one night long.
    if (isset($id)) {
      $event = $this->entityTypeManager->getStorage('bat_event')->load($id);
      if (isset($event)) {
        $start_date = new \DateTime($event->get("event_dates")->value);
        $end_date = new \DateTime($event->get("event_dates")->end_value);
        $duration = $start_date->diff($end_date);
        if ($duration->format('%a') > 1) {
          // @bug: I delete a reservation (commerce order) and a long event as unavailable is created.
          $this->messenger->addMessage('Events below can\'t be longer than 1 day. Check and fix values');

          (new RedirectResponse("/admin/bat/events/event/" . $event->Id() . "/edit"))->send();
        }
      }

      return $this->beeHotelBat->getUnitDateBatEventId($data);
    }

  }

  /**
   * Let's see what's type of occupancy is this.
   */
  public function typeofOccupacy($data) {

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
    $state_id = $query->execute()->fetchField(0);

    $data['tmp']['event_id'] = $this->beeHotelBat->getUnitDateBatEventId($data);

    // Populate $data.
    $data['occupancy']['current']['event']['id'] = $data['tmp']['event_id'];
    $data['occupancy']['current']['event']['state'] = $this->GetEventState($state_id);
    $data['tmp']['order'] = $data['occupancy']['current']['order'] = $this->beeHotelBat->getOrderFromEvent($data['occupancy']['current']['event']['id']);

    $data['tmp']['event_id'] = $this->beeHotelBat->getUnitDateBatEventId($data);
    if (isset($data['tmp']['order']->checkout)) {
      if (strtotime($data['tmp']['order']->checkout) >= strtotime($data['day']['today']['ISO8601'])) {
        // Given ISO 8601/unit.
        $data['occupancy'][$data['day']['today']['ISO8601']][$data['unit']['bid']]['event']['id'] = $data['tmp']['event_id'];
        $data['occupancy'][$data['day']['today']['ISO8601']][$data['unit']['bid']]['event']['state'] = $this->GetEventState($state_id);
        $data['occupancy'][$data['day']['today']['ISO8601']][$data['unit']['bid']]['order'] = $data['tmp']['order'];
        $data['occupancy'][$data['day']['today']['ISO8601']][$data['unit']['bid']]['qqqqq'] = 'pppppp';
      }
    }

    return $data;
  }

  /**
   * Let's see the id event state.
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
   *   Return seconds as integer or FALSE
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
   */
  public function eventHasOrder($event_id) {
    $data = [];
    $data['state']['id'] = $state_id;
    $data['states'] = bat_event_get_states();
    $data['state']['details'] = $data['states'][$data['state']['id']];
    $data['state']['is_blocking'] = $data['state']['details']->get('blocking')->value;
    return $data['state']['is_blocking'];
  }

  /**
   * Remove old events we don't need anymore.
   */
  public function purgeOldBatEvents($options) {

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
      ->condition('event_dates.value', $formatted, '<')
      ->range(0, $options['howmany'])
      ->execute();
    bat_event_delete_multiple($ids);

    $count_post = $storage->count()->execute();

    $tmp['options'] = [
      "%c" => $options['howmany'],
      "%older" => $options['daysago'],
      "%remain" => $count_post,
      "%count_pre" => $count_pre,
    ];

    $message = $this->t("counter_pre : [ %counter_pre ].N. %c bat_event(s)  older than %older days deleted.
      %remain bat_event(s) still in DB", $tmp['options']);
    $this->loggerFactory::logger('beehotel')->notice($message);
  }

}
