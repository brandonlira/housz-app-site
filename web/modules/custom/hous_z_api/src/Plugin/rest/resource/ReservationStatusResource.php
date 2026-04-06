<?php

namespace Drupal\hous_z_api\Plugin\rest\resource;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\hous_z_api\Service\ReservationService;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Booking status update resource.
 *
 * @RestResource(
 *   id = "reservation_status_resource",
 *   label = @Translation("Reservation status resource"),
 *   uri_paths = {
 *     "canonical" = "/api/reservation/status"
 *   }
 * )
 */
class ReservationStatusResource extends ResourceBase implements ContainerFactoryPluginInterface {

  /**
   * The reservation service.
   *
   * @var \Drupal\hous_z_api\Service\ReservationService
   */
  protected ReservationService $reservationService;

  /**
   * Constructs the resource.
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
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('hous_z_api'),
      $container->get('hous_z_api.reservation'),
    );
  }

  /**
   * Responds to PATCH /api/reservation/status.
   */
  public function patch(array $data): JsonResponse {
    $result = $this->reservationService->updateBookingStatus($data);
    if ($result['success']) {
      return new JsonResponse($result['data'], 200);
    }

    $status_code = $result['error'] === 'Booking not found.' ? 404 : 400;
    return new JsonResponse(['error' => $result['error']], $status_code);
  }

}
