<?php

namespace Drupal\bat_api\Plugin\rest\resource;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\rest\Attribute\RestResource;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the BAT Units Resource.
 */
#[RestResource(
  id: "bat_api_units_resource",
  label: new TranslatableMarkup("BAT_API Units Resource"),
  uri_paths: [
    "canonical" => "/bat_api/rest/calendar-units",
  ]
)]
class UnitsRestResource extends ResourceBase {

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
   * The Request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $currentRequest;

  /**
   * Construct the object.
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
   * @param Symfony\Component\HttpFoundation\Request $current_request
   *   The current request.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    Request $current_request,
    EntityTypeManagerInterface $entity_manager,
    AccountInterface $current_user,
    ModuleHandlerInterface $module_handler,
    Connection $connection
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->currentRequest = $current_request;
    $this->entityTypeManager = $entity_manager;
    $this->currentUser = $current_user;
    $this->moduleHandler = $module_handler;
    $this->connection = $connection;

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
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('module_handler'),
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

  /**
   * Responds to entity GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *
   *   a REST response.
   */
  public function get($nid = NULL) {

    $data = [];

    // $data['nid'] = $this->currentRequest->query->get('id');
    $data['unit_type'] = $this->currentRequest->query->get('unit_type');

    // Node BAT units coming from BeeController.
    $data['unitIds'] = $this->currentRequest->query->get('unit_ids');

    $data['units'] = $this->getReferencedIds($data['unit_type'], $data['unitIds']);

    $response = $data['units'];

    $build = ['#cache' => ['max-age' => 0]];

    return (new ResourceResponse($response))->addCacheableDependency($build);
  }

  /**
   * Gets all referenced entity IDs for the given unit type.
   *
   * @param string $unit_type
   *   The unit type.
   * @param string $ids
   *   Array of ids.
   *
   * @todo Add unit's child support.
   *
   * @return array
   *   Array of referenced unit type.
   */
  public function getReferencedIds(string $unit_type, string $ids) {

    $data = [];
    $data['unit_type'] = $unit_type;
    $data['ids'] = explode(",", $ids);

    $query = $this->connection->select('unit', 'n')
      ->fields('n', ['id', 'unit_type_id', 'type', 'name']);

    if (!empty($data['ids'])) {
      $query->condition('id', $data['ids'], 'IN');
    }

    $query->condition('unit_type_id', $data['unit_type']);

    // Required in 9.2 onwards.
    // $query->accessCheck(FALSE);
    $data['bat_units'] = $query->execute()->fetchAll();

    $data['units'] = [];

    // Produce array per FullCalendar.
    // See https://fullcalendar.io/docs/resource-parsing.
    foreach ($data['bat_units'] as $unit) {
      $data['units'][] = [
        'id' => $unit->id,
        'title' => $unit->name,
      ];
    }
    return $data['units'];
  }

}
