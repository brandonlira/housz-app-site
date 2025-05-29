<?php

namespace Drupal\bat_api\Plugin\Deriver;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides index for calendar events.
 */
class CalendarEventsIndex extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('router.route_provider'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $entity_type_id = 'calendar-events';

    $this->derivatives[$entity_type_id] = $base_plugin_definition;
    $this->derivatives[$entity_type_id]['title'] = $this->t('Calendar events');
    $this->derivatives[$entity_type_id]['description'] = $this->t('Index of events objects.');
    $this->derivatives[$entity_type_id]['category'] = $this->t('Calendar events');
    $this->derivatives[$entity_type_id]['path'] = "$entity_type_id";

    return $this->derivatives;
  }

}
