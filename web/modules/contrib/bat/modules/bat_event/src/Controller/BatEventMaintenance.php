<?php

namespace Drupal\bat_event\Controller;

use Drupal\Core\Database\Connection;
use Drupal\bat_event\Util\EventMaintenance;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides route responses for the Example module.
 */
class BatEventMaintenance extends ControllerBase {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The Bat event Utility.
   *
   * @var \Drupal\bat_event\Util\EventMaintenance
   */
  protected $batEventMAintenance;


  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Constructs a new object.
   *
   * @param \Drupal\bat_event\Util\EventMaintenance $eventMaintenance
   *   The Bee Hotel Event util.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(EventMaintenance $eventMaintenance, Connection $database) {
    $this->batEventMAintenance = $eventMaintenance;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('bat_event.util.event_maintenance'),
      $container->get('database'),
    );
  }

  /**
   * Returns a simple page.
   *
   * @return array
   *   A simple renderable array.
   */
  public function main() {

    $links = $data = [];
    $data['batTables'] = $this->batEventMAintenance->batTables([]);
    $report = [];

    foreach ($data['batTables']['main'] as $k => $v) {
      $data['event_table']['name'] = $k;
      $data['event_table']['count'] = $v;
    }

    // An array of diagnostic to be exposed.
    $report = [];

    $url = Url::fromRoute('bat_event.admin.deleteoldevents');
    $link = Link::fromTextAndUrl($this->t('Delete old events'), $url);
    $link = $link->toRenderable();
    $link['#attributes'] = ['class' => ['button', 'gear']];
    $links['deleteOldEvents'] = $link;

    if (isset($data['corrupted_related_tables']) && $data['corrupted_related_tables'] == TRUE) {

      $url = Url::fromRoute('bat_event.admin.fixtablesintegrity');
      $link = Link::fromTextAndUrl($this->t('Fix tables integrity'), $url);
      $link = $link->toRenderable();
      $link['#attributes'] = ['class' => ['button', 'gear', 'critical']];
      $links['fixTablesIngegrity'] = $link;
    }

    $theme = [
      '#theme' => 'bat_event_maintenance_main',
      '#data' => $data,
      '#links' => $links,
      '#report' => $report,
    ];

    return $theme;
  }

  /**
   * Delete old events.
   */
  public function deleteOldEvents() {

    $config = \Drupal::configFactory()->get('bat_event.settings');
    $bat_event_config = $config->get("bat_event");

    if ($bat_event_config == NULL) {
      \Drupal::messenger()->addMessage(t('Set up BAT Event preference'));
      (new RedirectResponse('/admin/bat/config/bat_event'))->send();
      exit();
    }

    if ($this->batEventMAintenance->deleteOldBatEvents($bat_event_config['delete_old'])) {
      $this->messenger()->addStatus($this->t('Old events deleted.'));
    }
    else {
      $this->messenger()->addError($this->t('No old event canceled.'));
    }

    return $this->redirect('bat_event.admin.maintenance');
  }

  /**
   * Perform a simple integrity check.
   */
  public function fixTablesIntegrity() {

    $data = [];
    $data['batTables'] = $this->batEventMAintenance->batTables([]);

    foreach ($data['batTables']['main'] as $k => $v) {
      $data['event_table']['name'] = $k;
      $data['event_table']['count'] = $v;
    }

    foreach ($data['batTables']['related'] as $key => $related) {

      if ($this->database->schema()->fieldExists($key, 'entity_id') == TRUE) {

        $query = $this->database->select($key, 'r')
          ->fields('r', ['entity_id']);

        $records_in_related = $query->execute()->fetchAll();

        foreach ($records_in_related as $record_in_related) {

          $query_main_table = $this->database->select($data['event_table']['name'], 'm')
            ->fields('m', ['id'])
            ->condition('m.uid', $record_in_related->entity_id, '=');
          // $record_in_main = $query_main_table->execute()->fetchAll();
          $num_rows = $query_main_table->countQuery()->execute()->fetchField();
          if ($num_rows <= 0) {
            $num_deleted = $this->database->delete($key)
              ->condition('entity_id', $record_in_related->entity_id)
              ->execute();
          }
          else {
          }
        }
      }
    }
    return $this->redirect('bat_event.admin.maintenance');
  }

}
