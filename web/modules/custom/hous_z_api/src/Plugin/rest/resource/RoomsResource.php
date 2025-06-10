<?php

namespace Drupal\hous_z_api\Plugin\rest\resource;

use Drupal\rest\Annotation\RestResource;
use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\File\FileUrlGenerator;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Psr\Log\LoggerInterface;

/**
 * Provides a REST resource for listing all active rooms.
 *
 * @RestResource(
 *   id = "rooms_resource",
 *   label = @Translation("Rooms list"),
 *   uri_paths = {
 *     "canonical" = "/api/rooms"
 *   }
 * )
 */
class RoomsResource extends ResourceBase implements ContainerFactoryPluginInterface {

  /**
   * The file URL generator service.
   *
   * @var \Drupal\Core\File\FileUrlGenerator
   */
  protected $fileUrlGenerator;

  /**
   * Constructs a new RoomsResource object.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\File\FileUrlGenerator $file_url_generator
   *   The file URL generator service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, FileUrlGenerator $file_url_generator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->fileUrlGenerator = $file_url_generator;
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
      $container->get('logger.factory')->get('hous_z_api'),
      $container->get('file_url_generator')
    );
  }

  /**
   * Responds to GET requests on /api/rooms.
   *
   * Returns a JSON array of all active rooms with metadata and bed templates.
   * Query parameters `checkInDate` and `checkOutDate` are echoed back in calendarData.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON response containing the rooms list.
   */
  public function get() {
    // Retrieve optional query parameters.
    $request = \Drupal::request();
    $checkInDate  = $request->query->get('checkInDate');
    $checkOutDate = $request->query->get('checkOutDate');

    // Minimum stay nights (static value; can be moved to config).
    $minStay = 2;

    // Load all active units.
    $storage = \Drupal::entityTypeManager()->getStorage('bat_unit');
    $units = $storage->loadByProperties(['status' => 1]);

    $result = [];
    /** @var \Drupal\bat_unit\Entity\Unit $unit */
    foreach ($units as $unit) {
      // Build bed templates.
      $beds = [];
      foreach ($unit->get('field_beds') as $item) {
        if ($paragraph = $item->entity) {
          $beds[] = [
            'type'     => $paragraph->get('field_bed_type')->value,
            'quantity' => (int) $paragraph->get('field_bed_quantity')->value,
          ];
        }
      }

      // Build tags.
      $tags = [];
      foreach ($unit->get('field_tags')->referencedEntities() as $term) {
        $tags[] = $term->label();
      }

      // Build cover image URL, if available.
      $image_url = '';
      if ($image_item = $unit->get('field_cover_image')->entity) {
        $image_url = $this->fileUrlGenerator->generateAbsoluteString($image_item->getFileUri());
      }

      // Optional description field (body).
      $description = '';
      if ($unit->hasField('body') && !empty($unit->get('body')->value)) {
        $description = $unit->get('body')->value;
      }

      $result[] = [
        'room' => [
          'roomName'      => $unit->label(),
          'description'   => $description,
          'imageUrl'      => $image_url,
          'tags'          => $tags,
          'availableBeds' => $beds,
        ],
        'calendarData' => [
          'checkInDate'  => $checkInDate ?? '',
          'checkOutDate' => $checkOutDate ?? '',
          'minStay'      => $minStay,
        ],
      ];
    }

    return new JsonResponse(['rooms' => $result]);
  }

}
