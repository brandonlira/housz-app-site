<?php

namespace Drupal\beehotel_samplehotel;

/**
 * A class to install BAT related entities.
 */
class SampleHotelInstallBat {

  /**
   * Create BAT entities for BEE Hotel.
   */
  public function createEntities($data) {

    $where = [];
    $entities = $data['entities'];

    foreach ($entities as $entity => $details) {

      $add = \Drupal::entityTypeManager()->getStorage($entity)->create([
        'entityTypeId' => $entity,
        'type' => $details['type'],
        'name' => $details['name'],
        'label' => $details['name'],
        'uid' => $details['uid'],
        'owner' => $details['owner'],
      ]);
      $add->save();
      $where[] = $details['where'];
    }
    return $where;
  }

  /**
   * Delete BAT entites. With this done, re-install BAT module.
   */
  public function beehotelSamplehotelDeleteBatEntities() {

    $batEntities = [
      // "bat_booking_bundle",
      // "bat_event_type",
      // "bat_type_bundle",
      "bat_unit",
      // "bat_unit_bundle",
      // "bat_unit_type",
      // "state",
    ];

    foreach ($batEntities as $batEntity) {
      $storage = \Drupal::entityTypeManager()->getStorage($batEntity);
      $query = $storage->getQuery();
      $query->accessCheck(TRUE);
      $ids = $query->execute();
      $ents = $storage->loadMultiple($ids);
      foreach ($ents as $ent) {
        $ent->delete();
      }
    }
  }

}
