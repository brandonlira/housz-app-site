<?php

namespace Drupal\hous_z_api\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Psr\Log\LoggerInterface;

/**
 * Provides a REST resource for checking bed availability in a unit.
 *
 * @RestResource(
 *   id = "availability_resource",
 *   label = @Translation("Bed availability"),
 *   uri_paths = {
 *     "canonical" = "/api/availability/{unitId}/{bedType}/{start}/{end}"
 *   }
 * )
 */
class AvailabilityResource extends ResourceBase implements \Drupal\Core\Plugin\ContainerFactoryPluginInterface {

  /**
   * Constructs a new AvailabilityResource object.
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
   *   Logger service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
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
      $container->get('logger.factory')->get('hous_z_api')
    );
  }

  /**
   * Responds to GET requests on
   * /api/availability/{unitId}/{bedType}/{start}/{end}.
   *
   * @param int $unitId
   *   The bat_unit entity ID.
   * @param string $bedType
   *   Machine name of the bed type (single_bed|double_bed).
   * @param string $start
   *   Start date in YYYY-MM-DD.
   * @param string $end
   *   End date in YYYY-MM-DD.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Calendar of daily availability.
   * @throws \DateMalformedPeriodStringException
   */
  public function get($unitId = NULL, $bedType = NULL, $start = NULL, $end = NULL) {
    // Validate bedType.
    $allowed = ['single_bed','double_bed'];
    if (!in_array($bedType, $allowed, TRUE)) {
      return new JsonResponse(['error' => 'Invalid bedType.'], 400);
    }

    // Parse dates.
    try {
      $startDt = new DrupalDateTime($start);
      $endDt   = new DrupalDateTime($end);
    }
    catch (\Exception $e) {
      return new JsonResponse(['error' => 'Invalid date format.'], 400);
    }
    $endDt->modify('+1 day');
    $interval = new \DateInterval('P1D');
    $period = new \DatePeriod($startDt->getPhpDateTime(), $interval, $endDt->getPhpDateTime());

    // Query events.
    $storage = \Drupal::entityTypeManager()->getStorage('bat_event');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type','availability_daily')
      ->condition('event_bat_unit_reference',(int)$unitId)
      ->condition('field_bed_type',$bedType)
      ->condition('event_dates.value',$end,'<=')
      ->condition('event_dates.end_value',$start,'>=');
    $ids = $query->execute();

    // Count occupied per day.
    $occupied = [];
    if (!empty($ids)) {
      $events = $storage->loadMultiple($ids);
      foreach ($events as $ev) {
        try {
          $s = new DrupalDateTime($ev->get('event_dates')->value);
          $e = new DrupalDateTime($ev->get('event_dates')->end_value);
        }
        catch (\Exception $ignore) {
          continue;
        }
        $e->modify('+1 day');
        $inner = new \DatePeriod($s->getPhpDateTime(), $interval, $e->getPhpDateTime());
        foreach ($inner as $d) {
          $key = $d->format('Y-m-d');
          $occupied[$key] = ($occupied[$key] ?? 0) + 1;
        }
      }
    }

    // Load unit to get total beds.
    $unit = \Drupal::entityTypeManager()->getStorage('bat_unit')->load($unitId);
    if (!$unit) {
      return new JsonResponse(['error'=>'Unit not found.'],404);
    }
    $total = 0;
    foreach ($unit->get('field_beds') as $item) {
      /** @var \\Drupal\\paragraphs\\Entity\\Paragraph $p */
      $p = $item->entity;
      if ($p && $p->get('field_bed_type')->value === $bedType) {
        $total = (int) $p->get('field_bed_quantity')->value;
        break;
      }
    }

    // Build payload.
    $payload = ['calendar'=>['years'=>[]]];
    foreach ($period as $dt) {
      $Y = $dt->format('Y');
      $M = (int)$dt->format('n');
      $d = (int)$dt->format('j');
      if (!isset($payload['calendar']['years'][$Y])) {
        $payload['calendar']['years'][$Y] = ['months'=>[]];
      }
      if (!isset($payload['calendar']['years'][$Y]['months'][$M])) {
        $payload['calendar']['years'][$Y]['months'][$M] = [
          'name'=>$dt->format('F'),
          'number'=>$M,
          'days'=>[]
        ];
      }
      $key = $dt->format('Y-m-d');
      $isAvailable = ($total>0 && (($occupied[$key] ?? 0) < $total));
      $payload['calendar']['years'][$Y]['months'][$M]['days'][] = [
        'day'=>$d,'available'=>$isAvailable
      ];
    }

    return new JsonResponse($payload);
  }

}
