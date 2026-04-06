<?php

namespace Drupal\bee_hotel;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Bat related utils for BeeHotel.
 *
 * See also Drupal\bee_hotel\Event for BAT Event methods.
 */
class BeeHotelBat {

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
   * Constructs a new BeeHotelBat object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
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
      $container->get('database')
    );
  }

  /**
   * Checks if a BAT event exists for a specific unit on a given date.
   *
   * This method queries BAT events availability table to determine if an event
   * exists for specified unit and date. The method only checks for existence
   * of an event record, regardless of the event's status or whether a related
   * commerce order exists.
   *
   * @param array $data
   *   An associative array containing date and unit info with following keys:
   *   - 'day': An array containing date components:
   *     - 'd': The day of the month (1-31)
   *     - 'month': The month (1-12)
   *     - 'year': The year (four digits)
   *   - 'unit': An array containing unit information:
   *     - 'bid': The unit ID (bat_unit bundle ID)
   *     - 'title': The unit title (for debugging purposes)
   *
   * @return int|null
   *   Returns event ID as integer if an event exists for given parameters,
   *   or NULL if no event is found.
   *
   * @ingroup bat_events
   *
   * @see bat_event_availability_daily_day_event
   * @see \Drupal\bat_event\Entity\Event
   */
  public function getUnitDateBatEventId(array $data) {
    $query = $this->database->select('bat_event_availability_daily_day_event', 'montlytable');

    // Fields.
    $query->addField('montlytable', 'd' . ($data['day']['d'] * 1), 'd' . ($data['day']['d'] * 1));

    // Conditions.
    $query->condition('montlytable.year', $data['day']['year'], '=');
    $query->condition('montlytable.month', $data['day']['month'], '=');
    $query->condition('montlytable.unit_id', $data['unit']['bid'], '=');

    $result = $query->execute()->fetch();

    if ($result !== FALSE) {
      $day = ($data['day']['d'] * 1);
      $d = "d" . $day;
      if (!empty($event_id = $result->$d)) {
        return (int) $event_id;
      }
    }

    return NULL;
  }

  /**
   * Retrieve Order details for a given event.
   *
   * @param int $event_id
   *   The BAT event ID.
   *
   * @return object|null
   *   Order object with details or NULL if not found.
   */
  public function getOrderFromEvent($event_id) {
    $query = $this->database->select('bat_booking__booking_event_reference', 'bbber');

    $order = new \stdClass();
    $order->is_blocking = "-";

    // Join.
    $query->join('commerce_order_item__field_booking', 'coifb', 'bbber.entity_id = coifb.field_booking_target_id');
    $query->join('commerce_order_item', 'coi', 'coifb.entity_id = coi.order_item_id');
    $query->join('commerce_order', 'co', 'coi.order_id = co.order_id');

    // Conditions.
    $query->condition('bbber.booking_event_reference_target_id', $event_id, '=');

    // Fields.
    $query->addField('co', 'mail', 'mail');
    $query->addField('co', 'order_number', 'order_number');
    $query->addField('co', 'total_price__number', 'total_price__number');
    $query->addField('co', 'order_id', 'order_id');
    $order = $query->execute()->fetch();

    // Get checkin date.
    $query = $this->database->select('bat_event_availability_daily_day_event', 'beadde');

    // Fields and orConditionGroup.
    $query->addField('beadde', 'year', 'year');
    $query->addField('beadde', 'month', 'month');
    $or_group = $query->orConditionGroup();

    for ($d = 1; $d <= 31; $d++) {
      $query->addField('beadde', 'd' . $d, 'd' . $d);
      $or_group->condition('beadde.d' . $d, $event_id, '=');
    }

    // Add the group to the query.
    $query->condition($or_group);

    $result = $query->execute()->fetchAll();

    $magicnumber = [];
    foreach ($result as $record) {
      foreach ($record as $d_key => $day) {
        if ($day == $event_id) {
          if ($d_key[0] == "d") {
            $day_numeric = sprintf("%02d", substr($d_key, 1, 3));
            $magicnumber[$event_id][] = ($record->year . sprintf("%02d", $record->month) . $day_numeric) * 1;
          }
        }
      }
    }

    if (empty($magicnumber)) {
      return NULL;
    }

    $m = $magicnumber[$event_id];
    asort($m);

    $ddt = new DrupalDateTime();

    $tmp = $ddt->createFromFormat('Ymd', $m[0]);

    if (!empty($order)) {
      $objs = $this->entityTypeManager
        ->getStorage('commerce_order')
        ->loadByProperties(['order_id' => $order->order_id]);
      $obj = reset($objs);

      $order->object = $obj;
      $order->checkin = $tmp->format('Y-m-d');
      rsort($m);
      $order->lastnight = $m[0];

      // From 24oct2025 - Add modify +1 day.
      // This is because BeeHotel reservations (orders) work by the night.
      $checkout_object = new DrupalDateTime($order->lastnight)->modify('+1 day');

      // *****
      // pre 24oct2025
      // This is tricky... @todo check how time of
      // Checkout is set $checkout->modify('+1 day');.
      // $order->checkout = $checkout->format('Y-m-d');
      $order->checkout = $checkout_object->format('Y-m-d');
      // ******
      $order->last_comment = $obj->get("field_order_comments")->value;

      $order->is_blocking = $this->orderHasBlockingEvent($obj);
      $order->bat_event = \Drupal::service('bee_hotel.order')->getOrderFirstItemEvent($obj);

      // Add 1 for hotel reservation purpose.
      $order->nights = ($tmp->diff($checkout_object)->days + 1);

    }
    return $order;
  }

  /**
   * Is this Order blocking calendar?
   *
   * We need this to show order or not on the calendar.
   *
   * @param object $order
   *   The commerce order object.
   *
   * @return bool
   *   TRUE if the order has a blocking event, FALSE otherwise.
   */
  private function orderHasBlockingEvent($order) {
    $data = [];
    $data['OrderFirstItemEvent']['object'] = \Drupal::service('bee_hotel.order')->getOrderFirstItemEvent($order);
    $data['OrderFirstItemEvent']['state']['id'] = $data['OrderFirstItemEvent']['object']->get('event_state_reference')->target_id;
    $data['OrderFirstItemEvent']['state']['object'] = $this->entityTypeManager
      ->getStorage('state')
      ->load($data['OrderFirstItemEvent']['state']['id']);
    $data['OrderFirstItemEvent']['state']['blocking'] = $data['OrderFirstItemEvent']['state']['object']
      ->get('blocking')
      ->value;
    return $data['OrderFirstItemEvent']['state']['blocking'];
  }

}
