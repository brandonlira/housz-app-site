<?php

namespace Drupal\beehotel_pricealterators\Plugin\PriceAlterator;

use Drupal\beehotel_utils\BeeHotelCommerce;
use Drupal\beehotel_pricealterator\PriceAlteratorBase;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a "Occupants" Price Alterator for BeeHotel.
 *
 * Because the plugin manager class for our plugins uses annotated class
 * discovery, Price Alterators only needs to exist within the
 * Plugin\PriceAlterator namespace, and provide a PriceAlterator
 * annotation to be declared as a plugin. This is defined in
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
 *   description = @Translation("Promote reservations with preferred number of Guests."),
 *   id = "Occupants",
 *   provider = "beehotel_pricealterator",
 *   status = 1,
 *   type = "optional",
 *   weight = 2,
 * )
 */
class Occupants extends PriceAlteratorBase {

  /**
   * The BeeHotel commerce Util.
   *
   * @var \Drupal\beehotel_utils\BeeHotelCommerce
   */
  protected $beehotelCommerce;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Price alterator Status.
   *
   * @var bool
   */
  private $status;

  /**
   * Price alterator Increase.
   *
   * @var float
   */
  private $increase;

  /**
   * Price alterator Enabled.
   *
   * @var bool
   */
  private $enabled;

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
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    BeeHotelCommerce $beehotel_commerce,
    RendererInterface $renderer,
  ) {
    $this->beehotelCommerce = $beehotel_commerce;
    $this->renderer = $renderer;
    $config = \Drupal::config($this->configName());
    $this->status = $config->get('status');

    // @todo increase should mnatch max quantity of product attribute guests.
    $this->increase = [
      '1' => $config->get('increase_1'),
      '2' => $config->get('increase_2'),
      '3' => $config->get('increase_3'),
      '4' => $config->get('increase_4'),
      '5' => $config->get('increase_5'),
      '6' => $config->get('increase_6'),
    ];
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
      $container->get('renderer'),
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

    $data['tmp']['price'] = $this->elaborate($data);

    $data['alterator'][] = __CLASS__;
    return $data;
  }

  /**
   * Elaborate values.
   */
  private function elaborate($data) {
    $elaborated = "";
    if (isset($data['tmp']['price'])) {
      if (isset($data['adults']) && $data['adults'] > 0) {
        $increase = (int) $this->increase[$data['adults']] + 1;
        $elaborated = $data['tmp']['price'] + ($data['tmp']['price'] / 100 * $increase);
      }
    }
    return $elaborated;
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
   * @return Drupal\Core\StringTranslation\TranslatableMarkup
   *   A translatable object containing the current value
   */
  public function currentValue(array $data, array $pricetable) {

    $data = [];
    $data[1] = [];
    $data[2] = [];
    $data[3] = [];
    $data[4] = [];

    $data['misc']['simulation']['base'] = 100;
    $data['misc']['currency'] = '€';

    for ($i = 1; $i <= count($this->increase); $i++) {
      if (isset($this->increase[$i])) {
        $data[$i]['class'] = $this->polarity($this->increase[$i]);
        $data[$i]['value'] = (int) $this->increase[$i];
        $data[$i]['simulation'] = $data['misc']['simulation']['base'] + ($data['misc']['simulation']['base'] / 100 * (int) $this->increase[$i]);
        $data[$i]['html'] = new TranslatableMarkup(
          "<span class='%class'><span class='value'>%increase</span><span class='smaller'>%</span>
          </span>", [
            '%class' => $data[$i]['class'],
            '%increase' => $this->increase[$i],
          ]
        );
      }
    }

    $occupants[] = [
      '#theme' => 'beehotel_pricealterators_occupants_values',
      '#first' => $data[1] ?: '---',
      '#second' => $data[2] ?: '---',
      '#third' => $data[3] ?: '---',
      '#fourth' => $data[4] ?: '---',
      '#misc' => $data['misc'] ?: '---',
      '#path' => \Drupal::service('extension.list.module')->getPath('beehotel_pricealterators'),
      '#enabled' => $this->enabled,
    ];

    $current_value = $this->renderer->renderPlain($occupants);
    return $current_value;
  }

}
