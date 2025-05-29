<?php

namespace Drupal\beehotel_samplehotel;

use Drupal\commerce_order\Entity\OrderItemType;
use Drupal\commerce_store\Entity\Store;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * A class to install Commerce related entities.
 */
class SampleHotelInstallCommerce {

  /**
   * C.1.
   */
  public static function createStore($sampleData) {

    $commerce_store = $sampleData['entities']['commerce_store'];

    // The store's address.
    $address = [
      'country_code' => $commerce_store['address']['country_code'],
      'address_line1' => $commerce_store['address']['address_line1'],
      'locality' => $commerce_store['address']['locality'],
      'administrative_area' => $commerce_store['address']['administrative_area'],
      'postal_code' => $commerce_store['address']['postal_code'],
    ];

    // If needed, this will import the currency.
    $currency_importer = \Drupal::service('commerce_price.currency_importer');
    $currency_importer->import($commerce_store['currency']);

    $commerce_store_entity = Store::create([
      'type' => $commerce_store['type'],
      'uid' => $commerce_store['uid'],
      'name' => $commerce_store['name'],
      'mail' => $commerce_store['mail'],
      'address' => $address,
      'default_currency' => $commerce_store['currency'],
      'billing_countries' => ['US'],
      'setDefault' => TRUE,
    ]);

    $commerce_store_entity->save();

    return [$commerce_store['where']];
  }

  /**
   * Create Order Item TYpe.
   */
  public static function createOrderItemType($sampleData) {

    $orderItemTypeConfig = $sampleData['entities']['commerce_order_item_type'];

    $orderItemType = OrderItemType::create([
      'purchasableEntityType' => $orderItemTypeConfig['purchasableEntityType'],
      'orderType' => $orderItemTypeConfig['orderType'],
      'id' => $orderItemTypeConfig['id'],
      'label' => $orderItemTypeConfig['label'],
    ]);
    $orderItemType->save();

    return [$orderItemTypeConfig['where']];
  }

  /**
   * Add fields.
   */
  public function addFieldsToOrderItemType($sampleData) {

    $where = [];
    $fieldsToOrderItemType = $sampleData['entities']['commerce_order_item_type']['fields'];
    $data = [];

    foreach ($fieldsToOrderItemType as $field_value) {

      $data['fieldStorageConfigCheckin'] = FieldStorageConfig::create([
        'field_name' => $field_value['fieldstorageconfig']['field_name'],
        'entity_type' => $field_value['fieldstorageconfig']['entity_type'],
        'type' => $field_value['fieldstorageconfig']['type'],
        'cardinality' => $field_value['fieldstorageconfig']['cardinality'],
      ])->save();

      $data['fieldConfigCheckin'] = FieldConfig::create([
        'field_name' => $field_value['fieldconfig']['field_name'],
        'entity_type' => $field_value['fieldconfig']['entity_type'],
        'bundle' => $field_value['fieldconfig']['bundle'],
        'label' => $field_value['fieldconfig']['label'],
      ])->save();
      $where[] = $field_value['where'] ?? '';
    }
    return $where;
  }

  /**
   * Create a sample Product attribute,.
   */
  public function createGuestsProductAttribute($sampleData) {

    if (!isset($sampleData['entities']['commerce_product_attribute'])) {
      return;
    }

    $attributes = $sampleData['entities']['commerce_product_attribute'];
    $attribute = \Drupal::entityTypeManager()->getStorage('commerce_product_attribute')->create([
      'id' => $attributes['id'],
      'label' => $attributes['label'],
      'elementType' => $attributes['elementType'],
    ]);
    $attribute->save();
    return [$attributes['where']];

  }

  /**
   * Create a sample checkout flow.
   */
  public function createCheckoutFlow($sampleData) {
    $checkoutFlow = $sampleData['entities']['commerce_checkout_flow'];
    $attribute = \Drupal::entityTypeManager()->getStorage('commerce_checkout_flow')->create($checkoutFlow);
    $attribute->save();
    return [$checkoutFlow['where']];
  }

  /**
   * Create a sample order type.
   */
  public function createOrderType($sampleData) {
    $orderType = $sampleData['entities']['commerce_order_type'];
    $attribute = \Drupal::entityTypeManager()->getStorage('commerce_order_type')->create($orderType);
    $attribute->save();
    return [$orderType['where']];
  }

  /**
   * Create a sample payment gateway.
   */
  public function createPaymentGateway($sampleData) {
    $orderType = $sampleData['entities']['commerce_payment_gateway'];
    $attribute = \Drupal::entityTypeManager()->getStorage('commerce_payment_gateway')->create([
      'conditionOperator' => $orderType['conditionOperator'],
      'id' => $orderType['id'],
      'label' => $orderType['label'],
      'plugin' => $orderType['plugin'],
    ]);
    $attribute->save();
    return [$orderType['where']];
  }

  /**
   * Set value to product Variation.
   */
  public function setProductVariationToGuests($sampleData) {

    // No action here. We simply print a message.
    $host = \Drupal::request()->getSchemeAndHttpHost();
    $output = new ConsoleOutput();
    $output->writeln(" ");
    $text = 'MANUAL Action required:';
    $output->writeln($text);
    $text = '* Goto ' . $host . '/admin/commerce/product-attributes/manage/guests  and set ';
    $text .= 'product variation type as BEE:';
    $output->writeln($text);

    $text = '*** 1 Guest';
    $output->writeln($text);

    $text = '*** 2 Guest';
    $output->writeln($text);

    $text = '*** till global max occupancy';
    $output->writeln($text);
    return [];

  }

  /**
   * Add fields to Product Variation.
   */
  public function addFieldsToProductVariation($sampleData) {
    $where = [];
    $fieldsForProductVariationType = $sampleData['entities']['commerce_product_variation_type']['fields'];
    foreach ($fieldsForProductVariationType as $field_name => $field_values) {
      $allowed_values = [];
      if (isset($field_values['fieldstorageconfig']['settings']) &&
          isset($field_values['fieldstorageconfig']['settings']['allowed_values'])
      ) {
        foreach ($field_values['fieldstorageconfig']['settings']['allowed_values'] as $allowed_value) {
          $allowed_values[$allowed_value['value']] = $allowed_value['label'];
        }
      }

      $data['fieldStorageConfig_' . $field_name] = FieldStorageConfig::create([
        'field_name' => $field_values['fieldstorageconfig']['field_name'],
        'entity_type' => $field_values['fieldstorageconfig']['entity_type'],
        'type' => $field_values['fieldstorageconfig']['type'],
        'cardinality' => $field_values['fieldstorageconfig']['cardinality'],
        'settings' => ['allowed_values' => $allowed_values],
      ])->save();

      $data['fieldConfig_' . $field_name] = FieldConfig::create([
        'field_name' => $field_values['fieldconfig']['field_name'],
        'entity_type' => $field_values['fieldconfig']['entity_type'],
        'bundle' => $field_values['fieldconfig']['bundle'],
        'label' => $field_values['fieldconfig']['label'],
        'description' => $field_values['fieldconfig']['description'],
      ])->save();

      // Add field to the form display.
      \Drupal::service('entity_display.repository')->getFormDisplay($fieldsForProductVariationType[$field_name]['fieldconfig']['entity_type'], $fieldsForProductVariationType[$field_name]['fieldconfig']['bundle'])
        ->setComponent($field_name, [
          'type' => $fieldsForProductVariationType[$field_name]['fieldentityformdisplay']['content']['type'],
          'weight' => $fieldsForProductVariationType[$field_name]['fieldentityformdisplay']['content']['weight'],
          'settings' => $fieldsForProductVariationType[$field_name]['fieldentityformdisplay']['content']['settings'],
        ])
        ->save();

      // Add field to the view display.
      \Drupal::service('entity_display.repository')->getViewDisplay($fieldsForProductVariationType[$field_name]['fieldconfig']['entity_type'], $fieldsForProductVariationType[$field_name]['fieldconfig']['bundle'])
        ->setComponent($field_name, [
          'type' => $fieldsForProductVariationType[$field_name]['fieldentityviewdisplay']['content']['type'],
          'weight' => $fieldsForProductVariationType[$field_name]['fieldentityviewdisplay']['content']['weight'],
          'settings' => $fieldsForProductVariationType[$field_name]['fieldentityviewdisplay']['content']['settings'],
        ])
        ->save();

      $where[] = $field_values['where'];

    }
    return $where;
  }

  /**
   * Set required attributes.
   *
   * @todo write code.
   */
  public function setAttributesToVariationType($sampleData) {}

  /**
   * Set required attributes.
   *
   * @todo write code.
   */
  public function setCheckoutFlow($sampleData) {
    $entity = \Drupal::entityTypeManager()->getStorage("commerce_order_type")->load("bee");

    $entity->setThirdPartySetting('commerce_checkout', "checkout_flow", 'beeh_booking_flow');
    $entity->setThirdPartySetting('commerce_cart', "cart_block_view", 'commerce_cart_block');
    $entity->setThirdPartySetting('commerce_cart', "cart_form_view", 'commerce_cart_form');
    $entity->setThirdPartySetting('commerce_cart', "cart_expiration", []);
    $entity->setThirdPartySetting('commerce_cart', "enable_cart_message", TRUE);
    $entity->setThirdPartySetting('commerce_invoice', "invoice_type", NULL);
    $entity->setThirdPartySetting('commerce_invoice', "order_placed_generation", FALSE);
    $entity->save();
  }

}
