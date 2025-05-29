<?php

namespace Drupal\bat_event\Util;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Manager for BAT events.
 */
class EventManager {

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
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a new Event object.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(LoggerChannelFactoryInterface $logger_factory, EntityTypeManagerInterface $entity_type_manager, Connection $database, RendererInterface $renderer) {
    $this->loggerFactory = $logger_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->database = $database;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.factory'),
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('renderer'),
    );
  }

  /**
   * Get a BAT event id from values.
   *
   * @param array $values
   *   An array of values, originally created by form API.
   *
   * @return array
   *   An array of useful data.
   */
  public function getEventByValues(array $values) {

    $start_date = new \DateTime($values['bat_start_date']);
    $end_date = new \DateTime($values['bat_end_date']);
    $entity_id = $values['entity_id'];
    $event_type = $values['event_type'];
    $field_name = $values['field_name'];

    $event = bat_event_create(['type' => $event_type]);
    $event->uid = $this->currentUser()->id();

    $event_dates = [
      'value' => $start_date->format('Y-m-d\TH:i:00'),
      'end_value' => $end_date->format('Y-m-d\TH:i:00'),
    ];
    $event->set('event_dates', $event_dates);

    $event_type_entity = bat_event_type_load($event_type);
    // Construct target entity reference field name using this
    // event type's target entity type.
    $target_field_name = 'event_' . $event_type_entity->getTargetEntityType() . '_reference';
    $event->set($target_field_name, $entity_id);

    $event->set($field_name, $values[$field_name]);
    $event->save();

    $data['unit'] = $this->entityTypeManager->getStorage($event_type_entity->getTargetEntityType())->load($entity_id);
    $data['elements'] = $event->{$field_name}->view(['label' => 'hidden']);
    $data['value'] = $value = $this->renderer->render($data['elements']);

    return $data;

  }

  /**
   * Delete a BAT event.
   *
   * @param array $values
   *   An array of values, originally created by form API.
   */
  public function deleteEvent(array $values) {
    $event->delete($event_id);
  }

  /**
   * Create a BAT event.
   *
   * Moved here from FullcalendarEventManagerForm as service,
   * to be consumed by more methods and modules.
   *
   * @param array $values
   *   An array of values, originally created by form API.
   *
   * @return array
   *   An array of useful data.
   */
  public function createEvent(array $values) {

    $start_date = new \DateTime($values['bat_start_date']);
    $end_date = new \DateTime($values['bat_end_date']);
    $entity_id = $values['entity_id'];
    $event_type = $values['event_type'];
    $field_name = $values['field_name'];

    $event = bat_event_create(['type' => $event_type]);
    $event->uid = $this->currentUser()->id();

    $event_dates = [
      'value' => $start_date->format('Y-m-d\TH:i:00'),
      'end_value' => $end_date->format('Y-m-d\TH:i:00'),
    ];
    $event->set('event_dates', $event_dates);

    $event_type_entity = bat_event_type_load($event_type);
    // Construct target entity reference field name using this
    // event type's target entity type.
    $target_field_name = 'event_' . $event_type_entity->getTargetEntityType() . '_reference';
    $event->set($target_field_name, $entity_id);

    $event->set($field_name, $values[$field_name]);
    $event->save();
    $data['event_id'] = $event->Id();
    $data['unit'] = $this->entityTypeManager->getStorage($event_type_entity->getTargetEntityType())->load($entity_id);
    $data['elements'] = $event->{$field_name}->view(['label' => 'hidden']);
    $data['value'] = $value = $this->renderer->render($data['elements']);

    return $data;

  }

  /**
   * Updat status to a given BAT event.
   *
   * Moved here from FullcalendarEventManagerForm as service,
   * to be consumed by more methods and modules.
   *
   * @param array $values
   *   An array of values, originally created by form API.
   *
   * @todo Coding.
   *   Code this method when the use case arises.
   */
  public function updateStatus(array $values) {}

}
