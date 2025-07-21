<?php

namespace Drupal\hous_z_management\Plugin\rest\resource;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Psr\Log\LoggerInterface;

/**
 * Provides a REST resource for houz bookings.
 *
 * @RestResource(
 *   id = "houz_bookings",
 *   label = @Translation("Houz Bookings"),
 *   uri_paths = {
 *     "canonical" = "/api/bookings"
 *   }
 * )
 */
class HouzBookingsResource extends ResourceBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new HouzBookingsResource object.
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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->entityTypeManager = $entity_type_manager;
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
      $container->get('logger.factory')->get('hous_z_management'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Responds to GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the bookings data.
   */
  public function get(): ResourceResponse {
    try {
      $bookings = $this->getBookingsData();

      $data = [
        'data' => $bookings,
        'count' => count($bookings),
      ];

      $response = new ResourceResponse($data);

      $response->getCacheableMetadata()->setCacheMaxAge(0);

      return $response;
    }
    catch (\Exception $e) {
      $this->logger->error(
        'Error fetching houz bookings: @message', ['@message' => $e->getMessage()
        ]
      );

      throw new HttpException(500, 'Internal Server Error');
    }
  }


  /**
   * Get bookings data using entity type manager.
   *
   * @return array
   *   Array of booking data.
   */
  private function getBookingsData(): array {
    $booking_storage = $this->entityTypeManager->getStorage('bat_booking');

    $query = $booking_storage->getQuery()
      ->accessCheck(TRUE)
      ->sort('id', 'DESC');

    $booking_ids = $query->execute();

    if (empty($booking_ids)) {
      return [];
    }

    $bookings = $booking_storage->loadMultiple($booking_ids);

    $data = [];
    foreach ($bookings as $booking) {
      $booking_data = [
        'id' => (int) $booking->id(),
        'start_date' => NULL,
        'end_date' => NULL,
        'requester_email' => NULL,
        'state' => NULL,
        'room' => NULL,
      ];

      if ($booking->hasField('booking_start_date') && !$booking->get('booking_start_date')->isEmpty()) {
        $start_date = $booking->get('booking_start_date')->value;
        $booking_data['start_date'] = date('Y-m-d', strtotime($start_date));
      }

      if ($booking->hasField('booking_end_date') && !$booking->get('booking_end_date')->isEmpty()) {
        $end_date = $booking->get('booking_end_date')->value;
        $booking_data['end_date'] = date('Y-m-d', strtotime($end_date));
      }

      if ($booking->hasField('field_requester_email') && !$booking->get('field_requester_email')->isEmpty()) {
        $booking_data['requester_email'] = $booking->get('field_requester_email')->value;
      }

      if ($booking->hasField('field_event_state') && !$booking->get('field_event_state')->isEmpty()) {
        $state_id = $booking->get('field_event_state')->target_id;
        $booking_data['state'] = $this->getStateLabel($state_id);
      }

      if ($booking->hasField('booking_event_reference') && !$booking->get('booking_event_reference')->isEmpty()) {
        $event_id = $booking->get('booking_event_reference')->target_id;
        $booking_data['room'] = $this->getRoomFromEvent($event_id);
      }

      $data[] = $booking_data;
    }

    return $data;
  }

  /**
   * Get room details from event entity.
   *
   * @param int $event_id
   *   The event ID.
   *
   * @return array|null
   *   The room details or NULL if not found.
   */
  private function getRoomFromEvent(int $event_id): ?array {
    try {
      $event = $this->entityTypeManager->getStorage('bat_event')->load($event_id);
      if ($event && $event->hasField('event_bat_unit_reference') && !$event->get('event_bat_unit_reference')->isEmpty()) {
        $unit_id = $event->get('event_bat_unit_reference')->target_id;
        $unit = $this->entityTypeManager->getStorage('bat_unit')->load($unit_id);

        if ($unit) {
          return [
            'id' => (int) $unit->id(),
            'name' => $unit->label(),
          ];
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->warning('Could not load event @id or related unit: @message', [
        '@id' => $event_id,
        '@message' => $e->getMessage(),
      ]);
    }

    return NULL;
  }

  /**
   * Get state label by state ID.
   *
   * @param int $state_id
   *   The state ID.
   *
   * @return string|null
   *   The state label or NULL if not found.
   */
  private function getStateLabel(int $state_id): ?string {
    $state_labels = [
      7 => 'Pending',
      8 => 'Cancelled',
      9 => 'Confirmed',
    ];

    return $state_labels[$state_id] ?? NULL;
  }

}
