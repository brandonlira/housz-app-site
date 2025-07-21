<?php

namespace Drupal\hous_z_api\Plugin\rest\resource;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\File\FileUrlGenerator;
use Drupal\hous_z_api\Service\ReservationService;
use Psr\Log\LoggerInterface;

/**
 * Creates a new reservation (booking) for a given unit and bed type.
 *
 * @RestResource(
 *   id = "reservation_resource",
 *   label = @Translation("Create reservation"),
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
  protected $reservationService;

  /**
   * Constructs a ReservationResource.
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
   * @param \Drupal\Core\File\FileUrlGenerator $file_url_generator
   *   The file URL generator.
   * @param \Drupal\hous_z_api\Service\ReservationService $reservation_service
   *   The reservation service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    FileUrlGenerator $file_url_generator,
    ReservationService $reservation_service
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->fileUrlGenerator = $file_url_generator;
    $this->reservationService = $reservation_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('hous_z_api'),
      $container->get('file_url_generator'),
      $container->get('hous_z_api.reservation')
    );
  }

  /**
   * Responds to POST /api/reservation.
   *
   * Expected JSON body keys:
   *  - unitId (int)
   *  - bedType (string)
   *  - checkInDate (YYYY-MM-DD)
   *  - checkOutDate (YYYY-MM-DD)
   *  - checkInTime (optional)
   *  - checkOutTime (optional)
   *  - details (optional)
   *
   * @param array $data
   *   The reservation data.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON summary or error.
   */
  public function post(array $data): JsonResponse {
    $result = $this->reservationService->createReservation($data);

    if ($result['success']) {
      return new JsonResponse([
        'message' => 'Reservation created successfully',
        'booking_id' => $result['data']['booking_id'],
      ], 201);
    }

    return new JsonResponse(['error' => $result['error']], 400);
  }

  /**
   * Responds to PATCH /api/reservation/{id}.
   *
   * Updates an existing reservation.
   *
   * @param int $id
   *   The booking ID.
   * @param array $data
   *   The update data.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with success/error message.
   */
  public function patch(int $id, array $data): JsonResponse {
    $result = $this->reservationService->updateBooking($id, $data);

    if ($result['success']) {
      return new JsonResponse([
        'message' => 'Reservation updated successfully',
        'booking_id' => $result['data']['booking_id'],
      ], 200);
    }

    $status_code = $result['error'] === 'Booking not found' ? 404 : 400;
    return new JsonResponse(['error' => $result['error']], $status_code);
  }

  /**
   * Responds to DELETE /api/reservation/{id}.
   *
   * Deletes an existing reservation.
   *
   * @param int $id
   *   The booking ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   JSON response with success/error message.
   */
  public function delete(int $id): JsonResponse {
    $result = $this->reservationService->deleteBooking($id);

    if ($result['success']) {
      return new JsonResponse([
        'message' => 'Reservation deleted successfully',
        'booking_id' => $id,
      ], 200);
    }

    $status_code = $result['error'] === 'Booking not found' ? 404 : 400;
    return new JsonResponse(['error' => $result['error']], $status_code);
  }

}
