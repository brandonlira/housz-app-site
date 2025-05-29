<?php

namespace Drupal\bat_event\Plugin\views\filter;

use Drupal\views\Views;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\filter\BooleanOperator;
use Drupal\views\ViewExecutable;

/**
 * Description.
 *
 * @ViewsFilter("bat_event_handler_blocking_filter")
 */
class BatEventHandlerBlockingFilter extends BooleanOperator {

  /**
   * Stores the available options.
   *
   * @var array
   */
  protected $valueOptions;

  /**
   * Value title.
   *
   * @var string
   */
  protected $value_value;

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    // 'value_value' is out of phpcs, required by views.
    $this->value_value = $this->t('State');
  }

  /**
   * This Method misses a description.
   */
  public function getValueOptions() {
    $options = [
      'blocking' => $this->t('Blocking'),
      'not_blocking' => $this->t('Not blocking'),
    ];

    $this->valueOptions = $options;
  }

  /**
   * This Method misses a description.
   */
  public function query() {
    $this->ensureMyTable();

    if ($this->value == 'not_blocking' || $this->value == 'blocking') {
      $configuration = [
        'table' => 'bat_event__event_state_reference',
        'field' => 'entity_id',
        'left_table' => 'event',
        'left_field' => 'id',
        'type' => 'left',
      ];
      $state_reference_join = Views::pluginManager('join')->createInstance('standard', $configuration);

      $this->query->addRelationship('bat_event__event_state_reference', $state_reference_join, 'event');

      $configuration = [
        'table' => 'states',
        'field' => 'id',
        'left_table' => 'bat_event__event_state_reference',
        'left_field' => 'event_state_reference_target_id',
        'type' => 'left',
      ];
      $state_join = Views::pluginManager('join')->createInstance('standard', $configuration);

      $this->query->addRelationship('states', $state_join, 'bat_event__event_state_reference');

      if ($this->value == 'not_blocking') {
        $this->query->addWhere(1, 'states.blocking', '0', '=');
      }
      elseif ($this->value == 'blocking') {
        $this->query->addWhere(1, 'states.blocking', '1', '=');
      }
    }
  }

  /**
   * This Method misses a description.
   */
  public function adminSummary() {
    if ($this->isAGroup()) {
      return $this->t('grouped');
    }
    if (!empty($this->options['exposed'])) {
      return $this->t('exposed');
    }
    if (empty($this->valueOptions)) {
      $this->getValueOptions();
    }

    return $this->valueOptions[$this->value];
  }

}
