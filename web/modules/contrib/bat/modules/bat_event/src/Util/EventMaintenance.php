<?php

namespace Drupal\bat_event\Util;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Define a maintenance class.
 *
 * Defines the Utility EventMaintenance class.
 * Cloned from https://www.drupal.org/project/bee_hotel.
 */
class EventMaintenance {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new class instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Remove old events from database.
   */
  public function deleteOldBatEvents($options) {
    // Note: this only works on healthy entities (data integrity).
    $data = [];
    $date = new DrupalDateTime($options['days_back'] . ' days ago');
    $date->setTimezone(new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE));
    $formatted = $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    $query = \Drupal::entityQuery('bat_event');
    $query->accessCheck(TRUE);
    $data['count_pre'] = $query->count()->execute();

    $data['ids'] = \Drupal::entityQuery('bat_event')
      ->accessCheck(TRUE)
      ->condition('event_dates.end_value', $formatted, '<')
      ->range(0, $options['how_many_per_cron'])
      ->execute();

    if (!isset($data['ids']) || count($data['ids']) < 1) {
      \Drupal::logger('bat_event')->notice($this->t("No old event to delete"));
      return FALSE;
    }

    bat_event_delete_multiple($data['ids']);

    $query = \Drupal::entityQuery('bat_event');
    $query->accessCheck(TRUE);
    $data['count_pre'] = $query->count()->execute();

    $tmp = [
      "%c" => $options['how_many_per_cron'],
      "%older" => $options['days_back'],
      "%remain" => $data['count_pre'],
      "%count_pre" => $data['count_pre'],
    ];

    $output = "counter pre: [ %counter_pre ]<br>\r\n";
    $output .= "how many per cron: [%c]<br>\r\n";
    $output .= "delete events older than  [%c] days<br>\r\n";
    $output .= "counter post: : [%remain]<br>\r\n";
    $message = $this->t($output, $tmp);

    \Drupal::logger('bat_event')->notice($message);
    return TRUE;
  }

  /**
   * Return BAT event tables.
   */
  public function batTables($options) {
    $data = [];

    // @todo get this from entity annotation.
    $main = "event";

    $database = \Drupal::database();
    $schema = $database->schema();
    $data['related'] = $schema->findTables('bat_event_%');

    foreach ($data['related'] as $key => $related) {
      $number_of_rows = $database->select($related)->countQuery()->execute()->fetchField();
      $data['related'][$key] = $number_of_rows;
    }

    ksort($data['related']);

    $tables['main'] = [
      $main => $database->select($main)->countQuery()->execute()->fetchField(),
    ];

    $tables = [
      'main' => [
        $main => $database->select($main)->countQuery()->execute()->fetchField(),
      ],
      'related' => $data['related'],
    ];
    return $tables;
  }

}
