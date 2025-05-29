<?php

namespace Drupal\beehotel_samplehotel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * A class to install Drupal related entities.
 */
class SampleHotelInstallDrupal {

  /**
   * Install required modules.
   */
  public function installModules() {

    $where = [];
    $installer = \Drupal::service('module_installer');

    $modules = $this->modules();

    foreach ($modules as $module) {
      $installer->install($module);
    }

    return $where;
  }

  /**
   * Uninstall required modules.
   */
  public function uninstallModules() {

    $where = [];
    $installer = \Drupal::service('module_installer');
    $modules = $this->modules();
    $modules = array_reverse($modules);
    foreach ($modules as $module) {
      $installer->uninstall($module);
    }
    return $where;

  }

  /**
   * Modules required by bee_hotel grouped by priority phases.
   */
  private function modules() {

    $modules = [];
    $modules[] = [
      "devel",
      "commerce",
      "commerce_checkout",
      "commerce_invoice",
      "commerce_payment",
      "commerce_order",
      "bee",
      "bat",
      "bat_api",
    ];

    $modules[] = [
      "bee_hotel",
      "beehotel_pricealterators",
    ];
    return $modules;
  }

  /**
   * Create a node bundle.
   */
  public function createDrupalUnitBundle($sampleData) {

    $output = new ConsoleOutput();
    $output->writeln(" ");
    $where = [];
    $entities = $sampleData['entities'];

    foreach ($entities as $entity => $details) {

      if ($entity == "node_type") {
        $add = \Drupal::entityTypeManager()->getStorage($entity)
          ->create([
            'entityTypeId' => $entity,
            'type' => $details['type'],
            'name' => $details['name'],
            'description' => $details['description'],
            'uid' => $details['uid'],
            'status' => 1,
          ]);
        $add->save();

        $bee_settings = $details['third_party_settings']['bee']['bee'];

        if (isset($bee_settings)) {
          bee_set_bee_to_node($add, $bee_settings);
        }
        $where[] = $details['where'];
      }
    }

    return $where;
  }

  /**
   * Add fields to Unit.
   */
  public function addFieldsToUnitBundle($sampleData) {

    $where = [];
    $fieldsToUnitBundle = $sampleData['entities']['node_type']['fields'];

    foreach ($fieldsToUnitBundle as $field_name => $field_values) {

      $query = \Drupal::entityTypeManager()
        ->getStorage('node');

      $exists = $query
        ->loadByProperties([
          'type' => $sampleData['entities']['node_type']['type'],
        ]);

      if (isset($exists)) {
        $allowed_values = [];
        if (isset($field_values['fieldstorageconfig']['settings']) &&
            isset($field_values['fieldstorageconfig']['settings']['allowed_values'])
        ) {
          foreach ($field_values['fieldstorageconfig']['settings']['allowed_values'] as $allowed_value) {
            $allowed_values[$allowed_value['value']] = $allowed_value['label'];
          }
        }

        $storageDependencies = $field_values['fieldstorageconfig']['dependencies'] ?? [];
        $dependencies = $field_values['fieldconfig']['dependencies'] ?? [];

        if ($fieldsToUnitBundle[$field_name]['fieldconfig']['create'] == TRUE) {
          $data['fieldStorageConfig_' . $field_name] = FieldStorageConfig::create([
            'dependencies' => $storageDependencies,
            'field_name' => $fieldsToUnitBundle[$field_name]['fieldstorageconfig']['field_name'],
            'entity_type' => $fieldsToUnitBundle[$field_name]['fieldstorageconfig']['entity_type'],
            'type' => $fieldsToUnitBundle[$field_name]['fieldstorageconfig']['type'],
            'cardinality' => $fieldsToUnitBundle[$field_name]['fieldstorageconfig']['cardinality'],
            'settings' => ['allowed_values' => $allowed_values],
          ])->save();

          $data['fieldConfig_' . $field_name] = FieldConfig::create([
            'field_name' => $fieldsToUnitBundle[$field_name]['fieldconfig']['field_name'],
            'entity_type' => $fieldsToUnitBundle[$field_name]['fieldconfig']['entity_type'],
            'bundle' => $fieldsToUnitBundle[$field_name]['fieldconfig']['bundle'],
            'label' => $fieldsToUnitBundle[$field_name]['fieldconfig']['label'],
            'dependencies' => $dependencies,
            'description' => $fieldsToUnitBundle[$field_name]['fieldconfig']['description'],
          ])->save();
        }
      }

      // Add field to the form display.
      \Drupal::service('entity_display.repository')->getFormDisplay('node', $fieldsToUnitBundle[$field_name]['fieldconfig']['bundle'])
        ->setComponent($field_name, [
          'type' => $fieldsToUnitBundle[$field_name]['fieldentityformdisplay']['content']['type'],
          'weight' => $fieldsToUnitBundle[$field_name]['fieldentityformdisplay']['content']['weight'],
          'settings' => $fieldsToUnitBundle[$field_name]['fieldentityformdisplay']['content']['settings'],
        ])
        ->save();

      // Add field to the view display.
      \Drupal::service('entity_display.repository')->getViewDisplay('node', $fieldsToUnitBundle[$field_name]['fieldconfig']['bundle'])
        ->setComponent($field_name, [
          'type' => $fieldsToUnitBundle[$field_name]['fieldentityviewdisplay']['content']['type'],
          'weight' => $fieldsToUnitBundle[$field_name]['fieldentityviewdisplay']['content']['weight'],
          'settings' => $fieldsToUnitBundle[$field_name]['fieldentityviewdisplay']['content']['settings'],
        ])
        ->save();

      if (
          isset($fieldsToUnitBundle[$field_name]['fieldentityformdisplay']['content']['hidden']) &&
          $fieldsToUnitBundle[$field_name]['fieldentityformdisplay']['content']['hidden'] == TRUE) {
        // Remove field from form display.
        \Drupal::service('entity_display.repository')->getFormDisplay('node', $fieldsToUnitBundle[$field_name]['fieldconfig']['bundle'])
          ->removeComponent($field_name)
          ->save();
      }

      $where[] = $field_values['where'];

    }
    return $where;
  }

  /**
   * Create a sample node.
   *
   * @todo write code.
   */
  public function createSampleRoom($sampleData) {

    $new_room = Node::create([
      'type' => $sampleData['entities']['node_type']['type'],
    ]);
    $new_room->set('title', "");
    $new_room->set('body', "");
    $new_room->enforceIsNew();
    $new_room->save();
  }

}
