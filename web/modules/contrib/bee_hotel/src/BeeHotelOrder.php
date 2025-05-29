<?php

namespace Drupal\bee_hotel;

/**
 * Commerce Order related utils for BeeHotel.
 */
class BeeHotelOrder {

  /**
   * For a given bat event, get related order.
   */
  public function getOrderFromBatEvent($bat_event_id) {

    $data['entity_type'] = "bat_booking";
    $data['field_to_search'] = "booking_event_reference";
    $data['field_value'] = $bat_event_id;

    $bat_booking = $this->getEntityByFieldValue($data);

    if ($bat_booking === FALSE) {
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
    $data['order'] = reset($entityManager->loadMultiple([$data['field_value']]));
    return $data['order'];
  }

  /**
   * For a given bat field_to_search, get related entity.
   */
  private function getEntityByFieldValue($data) {

    $query = \Drupal::entityQuery($data['entity_type'])
      ->condition($data['field_to_search'], $data['field_value']);

    $query->range(0, 1);
    $id = $query->execute();

    /** @var \Drupal\Core\Entity\EntityStorageInterface $entityManager */
    $entityManager = \Drupal::entityTypeManager()->getStorage($data['entity_type']);
    $entity = reset($entityManager->loadMultiple($id));
    return $entity;
  }

}
