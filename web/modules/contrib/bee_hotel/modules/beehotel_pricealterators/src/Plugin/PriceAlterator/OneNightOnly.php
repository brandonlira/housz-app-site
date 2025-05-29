<?php

namespace Drupal\beehotel_pricealterators\Plugin\PriceAlterator;

use Drupal\beehotel_utils\BeeHotelCommerce;
use Drupal\beehotel_pricealterator\PriceAlteratorBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a "One Night Only" Price Alterator for BeeHotel.
 *
 * Because the plugin manager class for our plugins uses annotated class
 * discovery, Price Alterators only needs to exist within the
 * Plugin\PriceAlterator namespace, and provide a PriceAlterator
 * annotation to be declared  as a plugin. This is defined in
 * \Drupal\beehotel_pricealterator\PriceAlteratorPluginManager::__construct().
 *
 * The following is the plugin annotation. This is parsed by Doctrine to make
 * the plugin definition. Any values defined here will be available in the
 * plugin definition.
 *
 * This should be used for metadata that is specifically required to instantiate
 * the plugin, or for example data that might be needed to display a list of all
 * available plugins where the user selects one. This means many plugin
 * annotations can be reduced to a plugin ID, a label and perhaps a description.
 *
 *
 *  The weight Key is the weight for this alterator.
 * Legenda for the 'weight' key:
 * -9999 : heaviest, to be used as very first (reserved)
 * -9xxx : heavy, to be used as first (reserved)
 *     0 : no need to be weighted
 *  1xxx : allowed in custom modules (@TODO)
 *  xxxx : everything else
 *  9xxx : light, to be used as last (reserved)
 *  9999 : lightest, to be used as very last (reserved)
 *
 * @PriceAlterator(
 *   description = @Translation("When a single night staying is requested, price rises."),
 *   id = "OneNightOnly",
 *   provider = "beehotel_pricealterator",
 *   type = "optional",
 *   status = 0,
 *   buggy = 1,
 *   weight = 1,
 * )
 */
class OneNightOnly extends PriceAlteratorBase {

  /**
   * The BeeHotel commerce Util.
   *
   * @var \Drupal\beehotel_utils\BeeHotelCommerce
   */
  protected $beehotelCommerce;

  /**
   * Constructs a new alterator object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\beehotel_utils\BeeHotelCommerce $beehotel_commerce
   *   BeeHotel Commerce Utils.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, BeeHotelCommerce $beehotel_commerce) {
    $config = \Drupal::config($this->configName());
    $this->status = $config->get('status');
    $this->enabled = $config->get('enabled');
    $this->globalslider = [$config->get('globalslider')];
    $this->beehotelCommerce = $beehotel_commerce;
    $this->enabled = $config->get('enabled');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('beehotel_utils.beehotelcommerce'),
    );
  }

  /**
   * Reference to the Alterator (as plugin).
   *
   *   This value matches the ID in the @PriceAlterator annotation.
   */
  public function pluginId() {
    $tmp = explode("\\", __CLASS__);
    return end($tmp);
  }

  /**
   * Alter a price.
   *
   * Every Alterator needs to have an  alter method.
   *
   * @param array $data
   *   Array of data related to this price.
   * @param array $pricetable
   *   Array of prices by week day.
   *
   * @return array
   *   An updated $data array.
   */
  public function alter(array $data, array $pricetable) {

    if ($data['norm']['dates_from_search_form']['days'] < 2) {

      if (isset($this->fixed) && $this->fixed != 0) {
        $data['tmp']['price'] += (float) $this->fixed;
      }
      elseif (isset($this->percentage) && $this->percentage != 0) {
        $data['tmp']['price'] += $data['tmp']['price'] / 100 * $this->percentage;
      }

    }
    $data['alterator'][] = __CLASS__;
    return $data;
  }

  /**
   * Current value.
   *
   * Get current value for this alterator. We can use this
   * method to get info and settings for the alterator.
   *
   * @param array $data
   *   Array of data related to this price.
   * @param array $pricetable
   *   Array of prices by week day.
   *
   * @return array
   *   A render array as expected by the renderer
   */
  public function currentValue(array $data, array $pricetable) {

    $data = [];
    $data['class'] = "";
    $data['value'] = $this->fixed;
    $data['currency'] = $this->beehotelCommerce->currentStoreCurrency()->get('symbol');
    $data['percentage'] = "%";
    $data['type'] = "";

    if (isset($this->fixed) && $this->fixed != "") {
      $data['value'] = $this->fixed;
      $data['class'] = $this->polarity($this->fixed) ?: "";
      $data['type'] = $data['currency'] ?: "";
    }
    elseif (isset($this->percentage) && $this->percentage != 0) {
      $data['value'] = $this->percentage;
      $data['class'] = $this->polarity($this->percentage) ?: "";
      $data['type'] = "%";
    }

    $current_value = [
      '#theme' => 'beehotel_pricealterator_current_value',
      '#class' => $data['class'],
      '#string' => $data['value'],
      '#type' => $data['type'],
    ];
    return \Drupal::service('renderer')->renderPlain($current_value);
  }

}
