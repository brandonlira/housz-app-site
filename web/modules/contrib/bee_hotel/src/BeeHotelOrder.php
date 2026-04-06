<?php

namespace Drupal\bee_hotel;

use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Commerce Order related utils for BeeHotel.
 *
 * Candidation to be move into beehotel_utils.
 */
class BeeHotelOrder {

  /**
   * Retrieves a commerce order associated with a BAT event.
   *
   * This method follows the entity relationship chain from a BAT event to its
   * associated commerce order. The chain is: BAT Event → BAT Booking →
   * Commerce Order Item → Commerce Order.
   *
   * @param int $bat_event_id
   *   The ID of the BAT event entity to find the related commerce order for.
   *
   * @return array|null
   *   Returns an associative array containing the commerce order with the following structure:
   *   - 'object': The loaded commerce order entity object
   *   - 'dates': An array of date information retrieved from getOrderDates()
   *   Returns NULL if no related commerce order is found at any point
   *   in the chain.
   *
   * @ingroup commerce_bat_integration
   *
   * @see getEntityByFieldValue()
   * @see getOrderDates()
   * @see \Drupal\bat_event\Entity\Event
   * @see \Drupal\bat_booking\Entity\Booking
   * @see \Drupal\commerce_order\Entity\OrderItem
   * @see \Drupal\commerce_order\Entity\Order
   */
  public function getOrderFromBatEvent($bat_event_id) {

    $data = $order = [];

    $data['entity_type'] = "bat_booking";
    $data['field_to_search'] = "booking_event_reference";
    $data['field_value'] = $bat_event_id;

    $bat_booking = $this->getEntityByFieldValue($data);

    if($bat_booking === FALSE || empty ($bat_booking)) {
      return;
    }
    // Get the order item entity related to this bat_booking.
    $data['entity_type'] = "commerce_order_item";
    $data['field_to_search'] = "field_booking";
    $data['field_value'] = $bat_booking->Id();
    $order_item = $this->getEntityByFieldValue($data);
    $data['entity_type'] = "commerce_order";
    $data['field_to_search'] = "order_id";
    $data['field_value'] = $order_item->get("order_id")->target_id;
    $entityManager = \Drupal::entityTypeManager()->getStorage($data['entity_type']);
    $order['object'] = $entityManager->loadMultiple([$data['field_value']]);
    $order['object'] = reset($order['object']);

    // @todo add date fields to commerce order object.
    $order['dates'] = $this->getOrderDates($order['object']);

    return $order;
  }

  /**
   * For a given bat field_to_search, get related entity.
   */
  private function getEntityByFieldValue($data) {

    $query = \Drupal::entityQuery($data['entity_type'])
      ->condition($data['field_to_search'], $data['field_value']);

    $query->range(0, 1);
    $query->accessCheck(FALSE);
    $id = $query->execute();

    if (isset($id) && count($id) > 0) {
      /** @var \Drupal\Core\Entity\EntityStorageInterface $entityManager */
      $entityManager = \Drupal::entityTypeManager()->getStorage($data['entity_type']);
      $entity = $entityManager->loadMultiple($id);
      return reset($entity);
    }
  }

  /**
   * For a given bat event, get related order.
   */
  public function getOrderFirstItemEvent($order) {
    $data = [];
    $data['order'] = $order;
    // @todo Get BAT booking record
    $data['order_first_item'] = $data['order']->get('order_items')[0]->entity;
    if ($data['order_first_item']->hasField('field_booking')) {
      $data['order_first_item_booking'] = $data['order_first_item']->get('field_booking')->entity;
      $data['order_first_item_booking_event'] = $data['order_first_item_booking']->get('booking_event_reference')->entity;
      return $data['order_first_item_booking_event'];
    }
  }

  /**
   * For a given Commerce Order, get useful dates.
   */
  public function getOrderDates($order) {
    $data = [];
    $data['order'] = $order;
    $data['order_first_item'] = $this->getOrderFirstItemEvent($data['order']);
    $data['order_dates']['checkin'] = new DrupalDateTime($data['order_first_item']
      ->get('event_dates')->value);
    $data['order_dates']['checkout'] = (new DrupalDateTime($data['order_first_item']
      ->get('event_dates')->end_value))->modify('+1 day');

    $interval = $data['order_dates']['checkin']->diff($data['order_dates']['checkout']);
    $data['order_dates']['nights'] = $interval->days;
    return $data['order_dates'];
  }

  /**
   * For a given day/unit get day before Order.
   *
   * Add day_before_order as  Drupal commerce object to $data.
   */
  public function getDayBeforeOrder($data) {
    $data['day_before']['timestamp'] = DrupalDateTime::createFromFormat('Y-m-d', $data['day']['daybefore']['ISO8601'])->getTimestamp();
    $data['day'] = \Drupal::service('beehotel_utils.dates')->dayArray($data['day_before']['timestamp']);
    $data['bat']['day_before_event'] = \Drupal::service('bee_hotel.event')->getNightEvent($data);
    $data['beehotel']['day_before_order']['object'] = \Drupal::service('bee_hotel.beehotelbat')->getOrderFromEvent($data['bat']['day_before_event']);
    return $data['beehotel']['day_before_order'];
  }

  /**
   * For a given day/unit get day before Order.
   *
   * Add day_before_order as  Drupal commerce object to $data.
   */
  public function getDayAfterOrder($data) {
    $data['day_after']['timestamp'] = DrupalDateTime::createFromFormat('Y-m-d', $data['day']['dayafter']['ISO8601'])->getTimestamp();
    $data['day'] = \Drupal::service('beehotel_utils.dates')->dayArray($data['day_after']['timestamp']);
    $data['bat']['day_after_event'] = \Drupal::service('bee_hotel.event')->getNightEvent($data);
    $data['beehotel']['day_after_order']['object'] = \Drupal::service('bee_hotel.beehotelbat')->getOrderFromEvent($data['bat']['day_after_event']);
    return $data['beehotel']['day_after_order'];
  }

}
