<?php

namespace Drupal\beehotel_samplehotel;

use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * A Class to install a Sample Beel Hotel.
 */
class SampleHotelInstall {

  /**
   * SampleHotel Install constructor.
   */
  public function __construct() {
    $this->batInstaller = new SampleHotelInstallBat();
    $this->commerceInstaller = new SampleHotelInstallCommerce();
    $this->drupalInstaller = new SampleHotelInstallDrupal();
  }

  /**
   * {@inheritdoc}
   */
  public function install() {

    $config = \Drupal::configFactory()->getEditable('beehotel_samplehotel.settings');
    $sampleData = $config->get("sample");

    /*
     * Drupal.
     * Drupal.1 - Install modules.
     * $res[] = $this->drupalInstaller->installModules().
     */

    // COMMERCE.
    // C.1 - Create Store.
    $res[] = $this->commerceInstaller->createStore($sampleData['commerce']);

    // C.2 - Create Order item type.
    $res[] = $this->commerceInstaller->createOrderItemType($sampleData['commerce']);

    // C.3 - Add custom fields to OrderItemTupe.
    $res[] = $this->commerceInstaller->addFieldsToOrderItemType($sampleData['commerce']);

    // C.4 - Create Guests product attribute.
    $res[] = $this->commerceInstaller->createGuestsProductAttribute($sampleData['commerce']);

    // C.5 - Add Chekout flow.
    $res[] = $this->commerceInstaller->createCheckoutFlow($sampleData['commerce']);

    // C.6 - Add Order type.
    $res[] = $this->commerceInstaller->createOrderType($sampleData['commerce']);

    // C.7 - Add a payment gateway.
    $res[] = $this->commerceInstaller->createPaymentGateway($sampleData['commerce']);

    // C.8 - Set Guests.
    $res[] = $this->commerceInstaller->setProductVariationToGuests($sampleData['commerce']);

    // BAT.
    // BAT.1 - Add BAT Entities.
    $res[] = $this->batInstaller->createEntities($sampleData['bat']);

    // Drupal.
    // Drupal.1.
    $res[] = $this->drupalInstaller->createDrupalUnitBundle($sampleData['drupal']);

    // Drupal.2 - Add custom fields to the node.
    $res[] = $this->drupalInstaller->addFieldsToUnitBundle($sampleData['drupal']);

    // Commerce.
    // C.9 - Add fields to variation type (to be done after Drupal.2).
    // We want to add the product attributesd Guest fields also.
    $res[] = $this->commerceInstaller->addFieldsToProductVariation($sampleData['commerce']);

    // Commerce.
    // C.10 - Set beeh_booking_flow checkout_flow to commerce_order_type/bee.
    $res[] = $this->commerceInstaller->setCheckoutFlow($sampleData['commerce']);

    /*
     * Drupal.x - Create sample room node.
     * @todo write code.
     * $res[] = $this->drupalInstaller->createSampleRoom($sampleData['drupal']);
     */

    $output = new ConsoleOutput();

    $output->writeln(" ");
    $output->writeln("Complete your installation from step D) at https://www.drupal.org/node/3127235");
    return $res;
  }

  /**
   * {@inheritdoc}
   */
  public function uninstall() {
    $config = \Drupal::configFactory()->getEditable('beehotel_samplehotel.settings');
    $sampleData = $config->get("sample");
    $this->beehotelSamplehotelDeleteEntities($sampleData);
    $this->batInstaller->beehotelSamplehotelDeleteBatEntities($sampleData = []);
  }

  /**
   * Delete beehotel entites.
   */
  private function beehotelSamplehotelDeleteEntities($sampleData) {
    foreach ($sampleData as $values) {
      if (isset($values['entities'])) {
        foreach ($values['entities'] as $type => $type_values) {
          $properties = [];
          if (isset($type_values['type'])) {
            $properties['type'] = $type_values['type'];
          }
          elseif (isset($type_values['id'])) {
            $properties['id'] = $type_values['id'];
          }
          $entities = \Drupal::entityTypeManager()->getStorage($type)
            ->loadByProperties($properties);
          foreach ($entities as $entity) {
            $entity->delete();
          }
        }
      }
    }
  }

}
