<?php

namespace Drupal\hous_z_api\Plugin\rest\resource;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\hous_z_api\Service\ReservationService;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * User reservation lookup resource.
 *
 * @RestResource(
 *   id = "user_reservations_resource",
 *   label = @Translation("User reservations resource"),
 *   uri_paths = {
 *     "create" = "/api/user/reservations"
 *   }
 * )
 */
class UserReservationsResource extends ResourceBase implements ContainerFactoryPluginInterface {

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
   * Responds to POST /api/user/reservations.
   */
  public function post(array $data): JsonResponse {
    $result = $this->reservationService->getUserReservationsByEmail((string) ($data['email'] ?? ''));
    if ($result['success']) {
      return new JsonResponse($result['data'], 200);
    }

    return new JsonResponse(['error' => $result['error']], 400);
  }

}
