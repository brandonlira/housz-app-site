<?php

namespace Drupal\beehotel_pricealterator\Plugin\PriceAlterator;

use Drupal\beehotel_utils\BeeHotelCommerce;
use Drupal\beehotel_pricealterator\PriceAlteratorBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Get the Base Price from the BaseTable.
 *
 * Because the plugin manager class for our plugins uses annotated class
 * discovery, Price Alterators only needs to exist within the
 * Plugin\PriceAlterator namespace, and provide a PriceAlterator annotation
 * to be declared as a plugin. This is defined in
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
 * The weight Key is the weight for this alterator
 * -9999 : heaviest, to be used as very first (reserved)
 * -9xxx : heavy, to be used as first (reserved)
 *     0 : no need to be weighted
 *  1xxx : allowed in custom modules (@TODO)
 *  xxxx : everything else
 *  9xxx : ligh, to be used as last (reserved)
 *  9999 : lighest, to be used as very last (reserved)
 *
 * @PriceAlterator(
 *   description = @Translation("Get prices from the  Weekly Unit Price Table. Every Unit has a Weekly Unit Price Table. This price alterator *MUST* be on the top of the alterators chain."),
 *   id = "PriceFromBaseTable",
 *   status = 1,
 *   type = "mandatory",
 *   weight = -9990,
 * )
 */
class PriceFromBaseTable extends PriceAlteratorBase {


  /**
   * The BeeHotel commerce Util.
   *
   * @var \Drupal\beehotel_utils\BeeHotelCommerce
   */
  protected $beehotelCommerce;

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
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    BeeHotelCommerce $beehotel_commerce) {
    $this->beehotelCommerce = $beehotel_commerce;
    $config = \Drupal::config($this->configName());
    $this->status = $config->get('status');
    $this->increase = $config->get('increase');
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
   * Every Alterator must have an alter method.
   *
   * @param array $data
   *   Array of data related to this price.
   * @param array $basetable
   *   Array of prices by week day.
   *
   * @return array
   *   An updated $data array.
   */
  public function alter(array $data, array $basetable) {
    // Set the base price for this request.
    $data['tmp']['day_of_the_week'] = strtolower(date("D", $data['tmp']['night_timestamp']));
    $data['tmp']['price'] = $basetable[$data['nid']][$data['season']][$data['tmp']['day_of_the_week']];

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
   * @return Drupal\Core\StringTranslation\TranslatableMarkup
   *   A translatable object containing the current value
   */
  public function currentValue(array $data, array $pricetable) {
    $current_value = [
      '#theme' => 'beehotel_pricealterator_current_value',
      '#class' => "",
      '#string' => "Unit > Weekly table",
      '#type' => "",
      '#description' => $this->t("to update base price"),
    ];
    return \Drupal::service('renderer')->renderPlain($current_value);
  }

}
