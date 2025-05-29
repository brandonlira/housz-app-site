<?php

namespace Drupal\bat_api\Plugin\ServiceDefinition;

use Drupal\bat_fullcalendar\FullCalendarFixedStateEventFormatter;
use Drupal\bat_fullcalendar\FullCalendarOpenStateEventFormatter;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\services\ServiceDefinitionBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Database\Database;
use Drupal\bat_roomify\Calendar\Calendar;
use Drupal\bat_roomify\Store\DrupalDBStore;
use Drupal\bat_roomify\Unit\Unit;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The calendar event index class.
 *
 * @ServiceDefinition(
 *   id = "calendar_events_index",
 *   methods = {
 *     "GET"
 *   },
 *   translatable = true,
 *   deriver = "\Drupal\bat_api\Plugin\Deriver\CalendarEventsIndex"
 * )
 */
class CalendarEventsIndex extends ServiceDefinitionBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The fixed state event formatter.
   *
   * @var \Drupal\bat_fullcalendar\FullCalendarFixedStateEventFormatter
   */
  protected $fixedStateEventFormatter;

  /**
   * The open state event formatter.
   *
   * @var \Drupal\bat_fullcalendar\FullCalendarOpenStateEventFormatter
   */
  protected $openStateEventFormatter;

  /**
   * Consctruc the object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\bat_fullcalendar\FullCalendarFixedStateEventFormatter $fixedStateEventFormatter
   *   The fixed state event formatter.
   * @param \Drupal\bat_fullcalendar\FullCalendarOpenStateEventFormatter $openStateEventFormatter
   *   The open state event formatter.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_manager, AccountInterface $current_user, ModuleHandlerInterface $module_handler, Connection $connection, FullCalendarFixedStateEventFormatter $fixedStateEventFormatter, FullCalendarOpenStateEventFormatter $openStateEventFormatter) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_manager;
    $this->currentUser = $current_user;
    $this->moduleHandler = $module_handler;
    $this->connection = $connection;
    $this->fixedStateEventFormatter = $fixedStateEventFormatter;
    $this->openStateEventFormatter = $openStateEventFormatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('module_handler'),
      $container->get('database'),
      $container->get('bat_fullcalendar.fixed_state_event_formatter'),
      $container->get('bat_fullcalendar.open_state_event_formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

  /**
   * {@inheritdoc}
   */
  public function processRequest(Request $request, RouteMatchInterface $route_match, SerializerInterface $serializer) {

    $data = [];
    $data['unit_types'] = $request->query->get('unit_types');
    $data['event_types'] = $request->query->get('event_types');
    $data['background'] = $request->query->get('background');
    $data['unit_ids'] = $request->query->get('unit_ids');
    $data['path'] = $request->query->get('path');

    $data['start_date'] = $request->query->get('start');
    $data['end_date'] = $request->query->get('end');

    $data['start_date_object'] = new \DateTime($data['start_date']);
    $data['end_date_object'] = new \DateTime($data['end_date']);

    if ($data['unit_types'] == 'all') {
      $data['unit_types'] = [];
      foreach (bat_unit_get_types() as $type => $info) {
        $data['unit_types'][] = $type;
      }
    }
    else {
      $data['unit_types'] = array_filter(explode(',', $data['unit_types']));
    }

    if ($data['event_types'] == 'all') {
      $types = [];
      foreach (bat_event_get_types() as $type => $info) {
        $types[] = $type;
      }
    }
    else {
      $types = array_filter(explode(',', $data['event_types']));
    }

    $events_json = [];

    foreach ($types as $type) {
      // Check if user has permission to view calendar data for this event type.
      if (!$this->currentUser->hasPermission('view calendar data for any ' . $type . ' event')) {
        continue;
      }

      // Get the event type definition from Drupal.
      $bat_event_type = bat_event_type_load($type);

      // For each type of event create a state store and an event store.
      $database = Database::getConnectionInfo('default');
      $prefix = (isset($database['default']['prefix']['default'])) ? $database['default']['prefix']['default'] : '';

      $event_store = new DrupalDBStore($type, DrupalDBStore::BAT_EVENT, $prefix);

      $today = new \DateTime();
      if (!$this->currentUser->hasPermission('view past event information') && $today > $data['start_date_object']) {
        if ($today > $data['end_date_object']) {
          return [];
        }
        $data['start_date_object'] = $today;
      }

      $ids = array_filter(explode(',', $data['unit_ids']));

      foreach ($data['unit_types'] as $unit_type) {
        $entities = $this->getReferencedIds($unit_type, $ids);

        /*
         * Create an array of unit objects - the default value is set to 0
         * since we want  to know if the value in the database is actually 0.
         * This will allow us to identify which events are represented by
         * events in the database (i.e. have a value different to 0).
         */
        $units = [];
        foreach ($entities as $entity) {
          $units[] = new Unit($entity['id'], 0);
        }

        if (!empty($units)) {
          $event_calendar = new Calendar($units, $event_store);
          $event_ids = $event_calendar->getEvents($data['start_date_object'], $data['end_date_object']);

          if ($bat_event_type->getFixedEventStates()) {
            $event_formatter = $this->fixedStateEventFormatter;
          }
          else {
            $event_formatter = $this->openStateEventFormatter;
          }

          $event_formatter->setEventType($bat_event_type);
          $event_formatter->setBackground($data['background']);

          foreach ($event_ids as $unit_id => $unit_events) {
            foreach ($unit_events as $key => $event) {
              $events_json[] = [
                'id' => (string) $key . $unit_id,
                'bat_id' => $event->getValue(),
                'resourceId' => 'S' . $unit_id,
                // __METHOD__ => 'ok',
              ] + $event->toJson($event_formatter);
            }
          }
        }
      }
    }

    $context = [
      'unit_ids' => $data['unit_ids'],
      'unit_types' => $data['unit_types'],
      'start_date' => $data['start_date_object'],
      'end_date' => $data['end_date_object'],
      'event_types' => $data['event_types'],
      'background' => $data['background'],
      // __METHOD__ => 'js-debug',
    ];

    if (
      $this->currentUser->hasPermission('edit event entities'
      && \Drupal::requestStack()->getCurrentRequest()->attributes->get('_route') == 'bee.node.availability'
    )) {
      // @todo improve this
      $context['useraccess'] = '777';
    }

    $this->moduleHandler->alter('bat_api_events_index_calendar', $events_json, $context);

    return array_values($events_json);
  }

  /**
   * Gets all referenced entity IDs for the given unit type.
   *
   * @param string $unit_type
   *   The unit type.
   * @param array $ids
   *   Array of ids.
   *
   * @return string[]
   *   The referenced entity IDs.
   */
  public function getReferencedIds($unit_type, array $ids) {
    $query = $this->connection->select('unit', 'n')
      ->fields('n', ['id', 'unit_type_id', 'type', 'name']);
    if (!empty($ids)) {
      $query->condition('id', $ids, 'IN');
    }
    $query->condition('unit_type_id', $unit_type);
    $bat_units = $query->execute()->fetchAll();

    $units = [];
    foreach ($bat_units as $unit) {
      $units[] = [
        'id' => $unit->id,
        'name' => $unit->name,
        'type_id' => $unit_type,
      ];
    }

    return $units;
  }

}
