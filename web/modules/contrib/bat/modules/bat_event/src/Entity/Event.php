<?php

namespace Drupal\bat_event\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Database\Database;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\bat_event\EventInterface;
use Drupal\bat_unit\UnitInterface;
use Drupal\user\UserInterface;
use Drupal\user\EntityOwnerTrait;
use Drupal\bat_roomify\Calendar\Calendar;
use Drupal\bat_roomify\Store\DrupalDBStore;
use Drupal\bat_roomify\Unit\Unit;
use Drupal\bat_roomify\Event\Event as BatEvent;

/**
 * Defines the Event entity.
 *
 * @ingroup bat
 *
 * @ContentEntityType(
 *   id = "bat_event",
 *   label = @Translation("Event"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\bat_event\EventListBuilder",
 *     "views_data" = "Drupal\bat_event\Entity\EventViewsData",
 *     "form" = {
 *       "default" = "Drupal\bat_event\Entity\Form\EventForm",
 *       "add" = "Drupal\bat_event\Entity\Form\EventForm",
 *       "edit" = "Drupal\bat_event\Entity\Form\EventForm",
 *       "delete" = "Drupal\bat_event\Entity\Form\EventDeleteForm",
 *     },
 *     "access" = "Drupal\bat_event\EventAccessControlHandler",
 *   },
 *   base_table = "event",
 *   admin_permission = "administer event entity",
 *   entity_keys = {
 *     "id" = "id",
 *     "bundle" = "type",
 *     "uuid" = "uuid",
 *     "uid" = "uid",
 *     "owner" = "uid",
 *     "langcode" = "langcode",
 *   },
 *   bundle_entity_type = "bat_event_type",
 *   field_ui_base_route = "entity.bat_event_type.edit_form",
 *   permission_granularity = "bundle",
 *   links = {
 *     "canonical" = "/admin/event/{bat_event}",
 *     "edit-form" = "/admin/event/{bat_event}/edit",
 *     "delete-form" = "/admin/event/{bat_event}/delete"
 *   }
 * )
 */
class Event extends ContentEntityBase implements EventInterface {
  use EntityChangedTrait, EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'uid' => \Drupal::currentUser()->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('uid')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('uid', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getUnit() {
    return $this->get('unit_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getUnitId() {
    return $this->get('unit_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setUnitId($unit_id) {
    $this->set('unit_id', $unit_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setUnit(UnitInterface $unit) {
    $this->set('unit_id', $unit->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStartDate() {

    $value = $this->get('event_dates')->value;
    if (!isset($value)) {
      $value = "";
    }

    $date = new \DateTime($value);
    return $date;
  }

  /**
   * {@inheritdoc}
   */
  public function getEndDate() {

    $value = $this->get('event_dates')->end_value;
    if (!isset($value)) {
      $value = "";
    }

    $date = new \DateTime($value);
    return $date;
  }

  /**
   * {@inheritdoc}
   */
  public function setStartDate(\DateTime $date) {
    $value = [
      'value' => $date->format('Y-m-d\TH:i:00'),
      'end_value' => $this->getEndDate()->format('Y-m-d\TH:i:00'),
    ];
    $this->set('event_dates', $value);
  }

  /**
   * {@inheritdoc}
   */
  public function setEndDate(\DateTime $date) {
    $value = [
      'value' => $this->getStartDate()->format('Y-m-d\TH:i:00'),
      'end_value' => $date->format('Y-m-d\TH:i:00'),
    ];
    $this->set('event_dates', $value);
  }

  /**
   * {@inheritdoc}
   */
  public function save() {

    $data = [];
    $data['langcode'] = $this->defaultLangcode;
    $data['event_type'] = bat_event_type_load($this->bundle());

    // Construct target entity reference field name using this
    // event type's target entity type.
    $data['target_field_name'] = 'event_' . $data['event_type']->getTargetEntityType() . '_reference';

    // We are going to be updating the event - so the first step is to remove
    // the old event.
    if (!($this->isNew())) {
      $data['entity_original'] = \Drupal::entityTypeManager()->getStorage('bat_event')->loadUnchanged($this->id());

      if (($data['entity_original']->getStartDate() != '') &&
        ($data['entity_original']->getEndDate() != '') &&
        ($data['entity_original']->getTranslation($data['langcode'])->get($data['target_field_name']) !== FALSE)) {

        // Get the referenced entity ID.
        $data['event_target_entity_reference'] = $data['entity_original']->getTranslation($data['langcode'])->get($data['target_field_name'])->getValue();

        $data['target_entity_id'] = 0;
        if (isset($data['event_target_entity_reference'][0]['target_id'])) {
          $data['target_entity_id'] = $data['event_target_entity_reference'][0]['target_id'];
        }

        // Load the referenced entity.
        if ($data['target_entity'] = \Drupal::entityTypeManager()->getStorage($data['event_type']->getTargetEntityType())->load($data['target_entity_id'])) {
          $data['unit'] = new Unit($data['target_entity_id'], $data['target_entity']->getEventDefaultValue($data['event_type']->id()));

          $this->batStoreSave($data['unit'],
            $data['entity_original']->getStartDate(),
            $data['entity_original']->getEndDate()->sub(new \DateInterval('PT1M')),
            $data['event_type']->id(),
            $data['event_type']->getEventGranularity(),
            $data['unit']->getDefaultValue(),
            $this->get('id')->value,
            TRUE
          );
        }
      }
    }

    parent::save();

    // Now we store the new event.
    if ($this->getTranslation($data['langcode'])->get($data['target_field_name']) !== FALSE) {
      $data['event_value'] = '';

      if (isset($data['event_type']->default_event_value_field_ids)) {
        $data['field'] = $data['event_type']->default_event_value_field_ids;
        $data['field_info'] = FieldStorageConfig::loadByName('bat_event', $data['field']);
        $data['values'] = $this->getTranslation($data['langcode'])->get($data['field'])->getValue();

        if (!empty($data['values'])) {
          if ($data['field_info']->getType() == 'entity_reference') {
            $data['event_value'] = $data['values'][0]['target_id'];
          }
          elseif ($data['field_info']->getType() == 'commerce_price') {
            $data['event_value'] = $data['values'][0]['number'];
          }
          elseif ($data['field_info']->getType() == 'text' || $data['field_info']->getType() == 'string' || $data['field_info']->getType() == 'number_integer') {
            $data['event_value'] = $data['values'][0]['value'];
          }
        }
      }
      else {

        // Jan24: Do we always have a valid event_state_reference?
        $data['event_state_reference'] = $this->getTranslation($data['langcode'])->get('event_state_reference')->getValue();
        $data['event_value'] = $data['event_state_reference'][0]['target_id'];
      }

      $data['event_target_entity_reference'] = $this->getTranslation($data['langcode'])->get($data['target_field_name']);

      // https://git.drupalcode.org/issue/bat-3395073/-/compare/8.x-1.x...8.x-1.x?from_project_id=59843
      $data['references'] = $data['event_target_entity_reference']->referencedEntities();
      if ($data['references'] != []) {
        $data['target_entity_id'] = $data['event_target_entity_reference']->referencedEntities()[0]->id();
        if ($data['target_entity'] = \Drupal::entityTypeManager()->getStorage($data['event_type']->getTargetEntityType())->load($data['target_entity_id'])) {
          $data['unit'] = new Unit($data['target_entity_id'], $data['target_entity']->getEventDefaultValue($data['event_type']->id()));

          $this->batStoreSave($data['unit'],
            $this->getStartDate(),
            $this->getEndDate()->sub(new \DateInterval('PT1M')),
            $data['event_type']->id(),
            $data['event_type']->getEventGranularity(),
            $data['event_value'],
            $this->get('id')->value
          );
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {

    $langcode = $this->defaultLangcode;
    $event_type = bat_event_type_load($this->bundle());

    // Construct target entity reference field name using
    // this event type's target entity type.
    $target_field_name = 'event_' . $event_type->getTargetEntityType() . '_reference';

    // Check if the event had a unit associated with it and if so update the
    // availability calendar.
    if ($this->getTranslation($langcode)->get($target_field_name) !== FALSE) {

      $event_target_entity_reference = $this->getTranslation($langcode)->get($target_field_name);

      $target_entity = $event_target_entity_reference->referencedEntities()[0] ?? NULL;
      $target_entity_id = $target_entity ? $target_entity->id() : NULL;

      // Load the referenced entity.
      if ($target_entity_id && $target_entity = \Drupal::entityTypeManager()->getStorage($event_type->getTargetEntityType())->load($target_entity_id)) {
        $unit = new Unit($target_entity_id, $target_entity->getEventDefaultValue($event_type->id()));
        $this->batStoreSave($unit,
          $this->getStartDate(),
          $this->getEndDate()->sub(new \DateInterval('PT1M')),
          $event_type->id(),
          $event_type->getEventGranularity(),
          $unit->getDefaultValue(),
          $this->get('id')->value,
          TRUE
        );
      }
    }

    parent::delete();
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the Event entity.'))
      ->setReadOnly(TRUE);

    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the Event entity.'))
      ->setReadOnly(TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the Event entity.'))
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setDefaultValueCallback(static::class . '::getCurrentUserId')
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => 5,
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    $fields['type'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Type'))
      ->setDescription(t('The event type.'))
      ->setSetting('target_type', 'bat_event_type');

    return $fields;
  }

  /**
   * Handles saving to the BatStore.
   *
   * @param \Drupal\bat_roomify\Unit\Unit $unit
   *   The unit to save.
   * @param \DateTime $start_date
   *   Event start date.
   * @param \DateTime $end_date
   *   Event end date.
   * @param string $event_type
   *   Event type.
   * @param string $granularity
   *   Event granularity.
   * @param string $event_state
   *   Event state.
   * @param string $event_id
   *   Event id.
   * @param bool|false $remove
   *   Set to TRUE if the event is to be removed (event_id set to zero).
   */
  public function batStoreSave(Unit $unit, \DateTime $start_date, \DateTime $end_date, $event_type, $granularity, $event_state, string $event_id, $remove = FALSE) {

    $database = Database::getConnectionInfo('default');

    $prefix = (isset($database['default']['prefix']['default'])) ? $database['default']['prefix']['default'] : '';

    $state_store = new DrupalDBStore($event_type, DrupalDBStore::BAT_STATE, $prefix);
    $event_store = new DrupalDBStore($event_type, DrupalDBStore::BAT_EVENT, $prefix);

    $units = [$unit];
    $state_calendar = new Calendar($units, $state_store);
    $event_calendar = new Calendar($units, $event_store);

    $state_event = new BatEvent($start_date, $end_date, $unit, $event_state);

    if (!$remove) {
      $event_id_event = new BatEvent($start_date, $end_date, $unit, $event_id);
    }
    else {
      $event_id_event = new BatEvent($start_date, $end_date, $unit, 0);
    }

    $state_calendar->addEvents([$state_event], $granularity);
    $event_calendar->addEvents([$event_id_event], $granularity);
  }

  /**
   * Returns the event value.
   *
   * @return int|false
   *   Add some description.
   */
  public function getEventValue() {

    $langcode = $this->defaultLangcode;

    if ($field = $this->getEventValueField()) {
      $field_info = FieldStorageConfig::loadByName('bat_event', $field);
      $values = $this->getTranslation($langcode)->get($field)->getValue();

      if (!empty($values)) {
        if ($field_info->getType() == 'entity_reference') {
          return $values[0]['target_id'];
        }
        elseif ($field_info->getType() == 'commerce_price') {
          return $values[0]['number'];
        }
        elseif ($field_info->getType() == 'text' || $field_info->getType() == 'string' || $field_info->getType() == 'number_integer') {
          return $values[0]['value'];
        }
      }
      else {
        return FALSE;
      }
    }
  }

  /**
   * Returns the event label.
   *
   * @return string|false
   *   Return a string or FALSE
   */
  public function getEventLabel() {

    $type_bundle = bat_event_type_load($this->bundle());

    if (!empty($type_bundle->default_event_label_field_name)) {
      $field_name = $type_bundle->default_event_label_field_name;
      $field = $this->get($field_name);

      if ($field->getFieldDefinition()->getType() == 'entity_reference') {
        if ($entity = $field->entity) {
          return $entity->label();
        }
      }
      else {
        return $field->value;
      }
    }
    return FALSE;
  }

  /**
   * Determines which field holds the event value.
   *
   * @return string|false
   *   Return a string or FALSE.
   */
  public function getEventValueField() {
    $type_bundle = bat_event_type_load($this->bundle());

    if (isset($type_bundle->default_event_value_field_ids)) {
      return $type_bundle->default_event_value_field_ids;
    }

    if ($type_bundle->getFixedEventStates() == 1) {
      return 'event_state_reference';
    }

    return FALSE;
  }

  /**
   * Default value callback for 'uid' base field definition.
   *
   * @see ::baseFieldDefinitions()
   *
   * @return array
   *   An array of default values.
   */
  public static function getCurrentUserId() {
    return [\Drupal::currentUser()->id()];
  }

}
