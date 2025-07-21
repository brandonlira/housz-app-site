<?php

namespace Drupal\hous_z_api\Plugin\rest\resource;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\hous_z_api\Service\ReservationService;
use Drupal\rest\Attribute\RestResource;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

#[RestResource(
  id: 'hous_z_api_my_reservations',
  label: new TranslatableMarkup('My Reservations'),
  uri_paths: [
    'canonical' => '/api/my-reservations/{id}',
  ],
)]
final class MyReservationsResource extends ResourceBase {

  /**
   * The reservation service.
   *
   * @var \Drupal\hous_z_api\Service\ReservationService
   */
  protected ReservationService $reservationService;

  /**
   * {@inheritdoc}
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
      $container->get('logger.factory')->get('rest'),
      $container->get('hous_z_api.reservation')
    );
  }

  /**
   * Responds to GET requests.
   */
  public function get($id): ResourceResponse {
    try {
      $email = $id;
      $myReservations = $this->reservationService->getBookingsData($email);

      if (empty($myReservations)) {
        return new ResourceResponse("Reservations not found for $email.", 404);
      }

      $data = [
        'data' => $myReservations,
        'count' => count($myReservations),
      ];

      return new ResourceResponse($data, 200);
    }
    catch (\Exception $e) {
      return new ResourceResponse(
        'An error occurred while getting reservations for ' . $email, $e->getCode(), $e->getMessage()
      );
    }
  }

}
