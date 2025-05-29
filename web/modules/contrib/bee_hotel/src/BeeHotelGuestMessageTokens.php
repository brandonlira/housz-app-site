<?php

namespace Drupal\bee_hotel;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides route responses for BeeHotel module.
 */
class BeeHotelGuestMessageTokens {

  use StringTranslationTrait;

  /**
   * Get Tokens.
   */
  public function get($commerce_order) {

    $data = [];
    $data['commerce_order'] = $commerce_order;
    $data['tokens'] = $this->guestMessageTokensSchema();
    foreach ($data['tokens'] as $id => $token) {
      if ($token["entity"] == "field_token") {

      }
      elseif ($token['entity'] == "commerce_order") {
        $tmp = $token['property'];
        $data['tokens'][$id]['value'] = $data['commerce_order']->get($token['field'])->$tmp;
        if ($token['field'] == 'uid') {
          $data['tokens'][$id]['value'] = $data['tokens'][$id]['value']->get("name")->value;
        }
        elseif ($token['field'] == 'order_items') {
          $data['tokens'][$id]['value'] = $data['tokens'][$id]['value']->get("title")->value;

          // @todo Get BAT booking record
          $data['order_first_item'] = $data['commerce_order']->get('order_items')[0]->entity;
          if ($data['order_first_item']->hasField('field_booking')) {
            $data['order_first_item_booking'] = $data['order_first_item']->get('field_booking')->entity;
            $data['order_first_item_booking_event'] = $data['order_first_item_booking']->get('booking_event_reference')->entity;

            if (isset($data['order_first_item_booking_event'])) {
              $data['order_first_item_booking_event_id'] = $data['order_first_item_booking_event']->get('id')->value;
              $data['markup'] .= "<a href='/admin/bat/events/event/" . $data['order_first_item_booking_event_id'] . "/edit?/admin/commerce/orders/" . $data['order_id'] . "'>Event</a>";
            }
          }
        }
      }
    }

    return $data['tokens'];
  }

  /**
   * Consume token contributed module.
   */
  private function getTokenFromTokenModule($beehotel_token, $options) {
    $data['tokens'] = \Drupal::service('token.tree_builder')->buildRenderable([
      'node',
      'user',
      'commerce_order',
    ]);
    return $token;
  }

  /**
   * Structured list of BeeHotel Guest message tokens.
   */
  public function guestMessageTokensSchema() {

    $schema = [
      'balance' => [
        'description' => $this->t('The balance left to paid'),
        'entity' => 'commerce_order',
        'field' => 'balance',
        'property' => 'number',
        'value' => NULL,
      ],

      'checkin_time' => [
        'description' => $this->t('Time of check IN'),
        'entity' => '',
        'field' => '',
        'property' => NULL,
        'value' => '15:00',
      ],

      'checkout_time' => [
        'description' => $this->t('Time of check OUT'),
        'entity' => '',
        'field' => '',
        'value' => '10:00',
      ],

      'guest_email' => [
        'description' => $this->t('Email used by Guest at the reservation'),
        'entity' => 'commerce_order',
        'field' => 'mail',
        'property' => 'value',
        'value' => NULL,
      ],

      'guest_name' => [
        'description' => $this->t('Guest name (or email if not provided)'),
        'entity' => 'commerce_order',
        'field' => 'mail',
        'property' => 'value',
        'value' => NULL,
      ],

      'mail' => [
        'description' => $this->t('MAil (DUPLICATE????)'),
        'entity' => 'commerce_order',
        'field' => 'mail',
        'property' => 'value',
        'value' => NULL,
      ],

      'order_items' => [
        'description' => $this->t('items in resevation (rooms)'),
        'entity' => 'commerce_order',
        'field' => 'order_items',
        'property' => 'target_id',
        'value' => NULL,
      ],

      'order_items' => [
        'description' => $this->t('List of items (Bee Hotel Units) in reservations'),
        'entity' => 'commerce_order',
        'field' => 'order_items',
        'multiple' => TRUE,
        'property' => 'entity',
        'sub_property' => "purchased_entity->entity->title->value",
        'value' => NULL,
      ],

      'total_price' => [
        'description' => $this->t('Total price for current reservation'),
        'entity' => 'commerce_order',
        'field' => 'total_price',
        'property' => 'number',
        'value' => NULL,
      ],

      'total_paid' => [
        'description' => $this->t('Total paid at today'),
        'entity' => 'commerce_order',
        'field' => 'total_paid',
        'property' => 'number',
        'value' => NULL,
      ],

      'uid' => [
        'description' => $this->t('User ID'),
        'entity' => 'commerce_order',
        'field' => 'uid',
        'property' => "entity",
        'sub_property' => "uid",
        'value' => NULL,
      ],

    ];

    return $schema;
  }

}
