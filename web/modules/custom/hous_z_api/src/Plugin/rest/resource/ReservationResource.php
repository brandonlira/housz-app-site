<?php

namespace Drupal\hous_z_api\Plugin\rest\resource;

use Drupal\Core\File\FileUrlGenerator;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\hous_z_api\Service\ReservationService;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Reservation create/update/delete resource.
 *
 * @RestResource(
 *   id = "reservation_resource",
 *   label = @Translation("Reservation resource"),
 *   uri_paths = {
 *     "canonical" = "/api/reservation/{id}",
 *     "create" = "/api/reservation"
 *   }
 * )
 */
class ReservationResource extends ResourceBase implements ContainerFactoryPluginInterface {

  /**
   * The file URL generator.
   *
   * @var \Drupal\Core\File\FileUrlGenerator
   */
  protected $fileUrlGenerator;

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
    FileUrlGenerator $file_url_generator,
    ReservationService $reservation_service,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->fileUrlGenerator = $file_url_generator;
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
      $container->get('file_url_generator'),
      $container->get('hous_z_api.reservation'),
    );
  }

  /**
   * Responds to POST /api/reservation.
   */
  public function post(array $data): JsonResponse {
    $result = $this->reservationService->createReservation($data);
    if ($result['success']) {
      return new JsonResponse($result['data'], 201);
    }

    return new JsonResponse(['error' => $result['error']], 400);
  }

  /**
   * Responds to PATCH /api/reservation/{id}.
   */
  public function patch(int $id, array $data): JsonResponse {
    $result = $this->reservationService->updateBooking($id, $data);
    if ($result['success']) {
      return new JsonResponse([
        'message' => 'Reservation updated successfully.',
        'bookingId' => $result['data']['booking_id'],
      ], 200);
    }

    $status_code = $result['error'] === 'Booking not found.' ? 404 : 400;
    return new JsonResponse(['error' => $result['error']], $status_code);
  }

  /**
   * Responds to DELETE /api/reservation/{id}.
   */
  public function delete(int $id): JsonResponse {
    $result = $this->reservationService->deleteBooking($id);
    if ($result['success']) {
      return new JsonResponse([
        'message' => 'Reservation deleted successfully.',
        'bookingId' => $id,
      ], 200);
    }

    $status_code = $result['error'] === 'Booking not found.' ? 404 : 400;
    return new JsonResponse(['error' => $result['error']], $status_code);
  }

}
