<?php

namespace Drupal\bat_event\Plugin\views\field;

use Drupal\views\ResultRow;
use Drupal\views\Plugin\views\field\FieldPluginBase;

/**
 * Description message.
 *
 * @ViewsField("bat_event_handler_event_type_field")
 */
class BatEventHandlerEventTypeField extends FieldPluginBase {

  /**
   * This Method misses a description.
   */
  public function render(ResultRow $values) {
    $event_type = bat_event_type_load($this->getValue($values));
    return $event_type->label();
  }

}
