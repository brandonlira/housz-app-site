<?php

namespace Drupal\bat_api\Plugin\rest\resource;

use Drupal\bat_fullcalendar\FullCalendarFixedStateEventFormatter;
use Drupal\bat_fullcalendar\FullCalendarOpenStateEventFormatter;
use Drupal\bat_roomify\Calendar\Calendar;
use Drupal\bat_roomify\Store\DrupalDBStore;
use Drupal\bat_roomify\Unit\Unit;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\rest\Attribute\RestResource;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the BAT Events Resource.
 */
#[RestResource(
  id: "bat_api_events_resource",
  label: new TranslatableMarkup("BAT_API Events Resource"),
  uri_paths: [
    "canonical" => "/bat_api/rest/calendar-events",

  ]
)]
class EventsRestResource extends ResourceBase {

  /**
   * Current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The Request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

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
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user instance.
   * @param Symfony\Component\HttpFoundation\Request $current_request
   *   The current request.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\bat_fullcalendar\FullCalendarFixedStateEventFormatter $fixedStateEventFormatter
   *   The fixed state event formatter.
   * @param \Drupal\bat_fullcalendar\FullCalendarOpenStateEventFormatter $openStateEventFormatter
   *   The open state event formatter.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, AccountProxyInterface $current_user, Request $current_request, Connection $connection, FullCalendarFixedStateEventFormatter $fixedStateEventFormatter, FullCalendarOpenStateEventFormatter $openStateEventFormatter, ModuleHandlerInterface $module_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->currentUser = $current_user;
    $this->currentRequest = $current_request;
    $this->connection = $connection;
    $this->fixedStateEventFormatter = $fixedStateEventFormatter;
    $this->openStateEventFormatter = $openStateEventFormatter;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('example_rest'),
      $container->get('current_user'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('database'),
      $container->get('bat_fullcalendar.fixed_state_event_formatter'),
      $container->get('bat_fullcalendar.open_state_event_formatter'),
      $container->get('module_handler'),
    );
  }

  /**
   * Responds to entity GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *   a REST response.
   */
  public function get($nid = NULL) {

    $data = [];
    $data['unit_types'] = $this->currentRequest->query->get('unit_types');
    $data['event_types'] = $this->currentRequest->query->get('event_types');
    $data['background'] = $this->currentRequest->query->get('background');
    $data['unit_ids'] = $this->currentRequest->query->get('unit_ids');
    $data['path'] = $this->currentRequest->query->get('path');

    $data['start_date'] = $this->currentRequest->query->get('start');
    $data['end_date'] = $this->currentRequest->query->get('end');

    $data['start_date_object'] = new \DateTime($data['start_date']);
    $data['end_date_object'] = new \DateTime($data['end_date']);
    $response = [
      'message' => 'Hello, you called service: ' . $this->pluginId,
      'id' => $this->currentRequest->attributes->get('id'),
    ];

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
        $data['units'] = $this->getReferencedIds($unit_type, $ids);

        /*
         * Create an array of unit objects - the default value is set to 0
         * since we want  to know if the value in the database is actually 0.
         * This will allow us to identify which events are represented by
         * events in the database (i.e. have a value different to 0).
         */
        $units = [];
        foreach ($data['units'] as $unit) {
          $units[] = new Unit($unit['id'], 0);
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

              $event_formatted = $event_formatter->format($event);

              if ($event->getValue() > 0) {
                $events_json[] = [
                  'resourceId' => $unit_id,
                  'bat_id' => $event->getValue(),
                  // __METHOD__ => 'ok',
                ] + $event->toJson($event_formatter);
              }
            }
          }
        }
      }
    }

    $context = [
      'unit_ids' => $data['unit_ids'],
      'unit_types' => $data['unit_types'],
      'start_date' => $data['start_date'],
      'end_date' => $data['end_date'],
      'event_types' => $data['event_types'],
      'background' => $data['background'],
      // __METHOD__ => 'js-debug',
    ];

    if (
      $this->currentUser->hasPermission('edit event entities'
      && \Drupal::requestStack()->getCurrentRequest()->attributes->get('_route') == 'bee.node.availability'
    )) {
      // @todo improve this.
      $context['useraccess'] = '777';
    }

    $this->moduleHandler->alter('bat_api_events_index_calendar', $events_json, $context);

    $build = [
      '#cache' => [
        'max-age' => 0,
      ],
    ];

    return (new ResourceResponse($events_json))->addCacheableDependency($build);

  }

  /**
   * Gets all referenced Unit entity IDs for the given unit type.
   *
   * @param string $unit_type
   *   The unit type.
   * @param array $ids
   *   Array of ids.
   *
   * @return string[]
   *   The referenced entity IDs.
   */
  private function getReferencedIds($unit_type, array $ids) {
    $query = $this->connection->select('unit', 'n')
      ->fields('n', ['id', 'unit_type_id', 'type', 'name']);
    if (!empty($ids)) {
      $query->condition('id', $ids, 'IN');
    }
    $query->condition('unit_type_id', $unit_type);
    $bat_units = $query->execute()->fetchAll();

    $units = [];
    foreach ($bat_units as $unit) {
      $units[$unit->id] = [
        'id' => $unit->id,
        'name' => $unit->name,
        'type_id' => $unit_type,
      ];
    }

    return $units;
  }

}
