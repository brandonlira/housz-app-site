<?php

namespace Drupal\beehotel_pricealterator\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a PriceAlterator annotation object.
 *
 * @see \Drupal\beehotel_pricealterator\PriceAlteratorPluginManager
 * @see plugin_api
 *
 * @Annotation
 */
class PriceAlterator extends Plugin {

  /**
   * A brief, human readable, description of Price Alterator.
   *
   * This property is designated as being translatable because it will appear
   * in the user interface. This provides a hint to other developers that they
   * should use the Translation() construct in their annotation when declaring
   * this property.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * Data to work with.
   *
   * This property is an array value, so we indicate that to other developers
   * who are writing annotations for a PriceAlterator plugin.
   *
   * @var array
   */
  public $data;

  /**
   * Alterated type value.
   *
   * This property is a string value, so we indicate that to other developers
   * who are writing annotations for a PriceAlterator plugin. This tells the
   * alter method what to do with the $value. $type is usually "percentage"
   *
   * @var string
   */
  public $type;

  /**
   * Alteration value.
   *
   * This property is a float value, so we indicate that to other developers
   * who are writing annotations for a PriceAlterator plugin. This tells the
   * alter method how much $data[price] is altered. Can be negative.
   *
   * @var float
   */
  public $alteration;

  /**
   * Weight value.
   *
   * This property is a int value, so we indicate that to other developers
   * who are writing annotations for a PriceAlterator plugin. This tells the
   * alterator PluginManager how to sort alterators.
   *
   * @var int
   */
  public $weigth;

}
