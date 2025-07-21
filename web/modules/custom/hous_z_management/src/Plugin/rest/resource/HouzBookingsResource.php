<?php

namespace Drupal\hous_z_management\Plugin\rest\resource;

use Drupal\hous_z_api\Service\ReservationService;
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
   * The reservation service.
   *
   * @var \Drupal\hous_z_api\Service\ReservationService
   */
  protected ReservationService $reservationService;

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
   * @param LoggerInterface $logger
   *   A logger instance.
   * @param ReservationService $reservation_service
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    ReservationService $reservation_service,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->reservationService = $reservation_service;
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
      $container->get('hous_z_api.reservation')
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
      $bookings = $this->reservationService->getBookingsData();

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

}
