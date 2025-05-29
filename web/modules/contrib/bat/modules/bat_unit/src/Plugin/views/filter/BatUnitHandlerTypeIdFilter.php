<?php

namespace Drupal\bat_unit\Plugin\views\filter;

use Drupal\views\Plugin\views\filter\ManyToOne;

/**
 * Description.
 *
 * Contains a Views filter handler to take care of displaying the correct label
 * for unit bundles.
 *
 * @ViewsFilter("bat_unit_handler_type_id_filter")
 */
class BatUnitHandlerTypeIdFilter extends ManyToOne {

  /**
   * Description.
   */
  public function getValueOptions() {
    $types = bat_unit_get_types();

    $options = [];
    foreach ($types as $type) {
      $options[$type->id()] = $type->label();
    }

    $this->valueOptions = $options;

    return $this->valueOptions;
  }

}
