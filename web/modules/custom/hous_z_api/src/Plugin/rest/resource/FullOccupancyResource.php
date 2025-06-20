<?php

namespace Drupal\hous_z_api\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Psr\Log\LoggerInterface;

/**
 * @RestResource(
 *   id = "full_occupancy_resource",
 *   label = @Translation("Full Occupancy"),
 *   uri_paths = {
 *     "canonical" = "/api/full-occupancy"
 *   }
 * )
 */
class FullOccupancyResource extends ResourceBase implements \Drupal\Core\Plugin\ContainerFactoryPluginInterface {

  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('hous_z_api')
    );
  }

  public function get() {
    $startDt = new DrupalDateTime('today');
    $endDt = (clone $startDt)->modify('+12 months');
    $interval = new \DateInterval('P1D');
    $period = new \DatePeriod($startDt->getPhpDateTime(), $interval, $endDt->getPhpDateTime());

    $storage_unit = \Drupal::entityTypeManager()->getStorage('bat_unit');
    $storage_event = \Drupal::entityTypeManager()->getStorage('bat_event');
    $unit_ids = $storage_unit->getQuery()->accessCheck(FALSE)->execute();
    $units = $storage_unit->loadMultiple($unit_ids);
    $unique_days = [];

    foreach ($period as $day) {
      $dateStr = $day->format('Y-m-d');
      $dateStart = $dateStr . 'T00:00:00';
      $dateEnd = $dateStr . 'T23:59:59';
      $isFullyOccupied = TRUE;

      foreach ($units as $unit) {
        $beds = $unit->get('field_beds')->referencedEntities();
        foreach ($beds as $bed) {
          $bed_type = $bed->get('field_bed_type')->value;
          $bed_quantity = (int)$bed->get('field_bed_quantity')->value;

          $events_query = $storage_event->getQuery()
            ->accessCheck(FALSE)
            ->condition('type', 'availability_daily')
            ->condition('event_bat_unit_reference', $unit->id())
            ->condition('field_bed_type', $bed_type)
            ->condition('event_dates.value', $dateEnd, '<=')
            ->condition('event_dates.end_value', $dateStart, '>=');
          $event_ids = $events_query->execute();

          if (count($event_ids) < $bed_quantity) {
            $isFullyOccupied = FALSE;
            break 2;
          }
        }
      }

      if ($isFullyOccupied) {
        $year = $day->format('Y');
        $month = (int)$day->format('n');
        $d = (int)$day->format('j');
        $unique_days["$year-$month-$d"] = [
          'year' => $year,
          'month' => $month,
          'month_name' => $day->format('F'),
          'day' => $d,
        ];
      }
    }

    $payload = ['calendar' => ['years' => []]];
    foreach ($unique_days as $item) {
      $Y = $item['year'];
      $M = $item['month'];
      $d = $item['day'];

      if (!isset($payload['calendar']['years'][$Y])) {
        $payload['calendar']['years'][$Y] = ['months' => []];
      }
      if (!isset($payload['calendar']['years'][$Y]['months'][$M])) {
        $payload['calendar']['years'][$Y]['months'][$M] = [
          'name' => $item['month_name'],
          'number' => $M,
          'days' => [],
        ];
      }
      $already_exists = false;
      foreach ($payload['calendar']['years'][$Y]['months'][$M]['days'] as $dayItem) {
        if ($dayItem['day'] === $d) {
          $already_exists = true;
          break;
        }
      }
      if (!$already_exists) {
        $payload['calendar']['years'][$Y]['months'][$M]['days'][] = [
          'day' => $d,
          'available' => false,
        ];
      }
    }

    return new JsonResponse($payload);
  }
}
