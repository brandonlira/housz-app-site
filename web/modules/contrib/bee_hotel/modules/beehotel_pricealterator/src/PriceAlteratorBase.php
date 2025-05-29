<?php

namespace Drupal\beehotel_pricealterator;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginWithFormsTrait;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the base class for payment gateways.
 */
class PriceAlteratorBase extends PluginBase implements PriceAlteratorInterface, ContainerFactoryPluginInterface {

  use PluginWithFormsTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The BeeHotel commerce Util.
   *
   * @var \Drupal\beehotel_utils\BeeHotelCommerce
   */
  protected $beehotelCommerce;

  /**
   * The devel config.
   */
  protected ImmutableConfig $config;

  /**
   * Constructs a new Occupants alterator object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {}

  /**
   * Creates an instance of the plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {}

  /**
   * {@inheritdoc}
   */
  public function configName() {
    return BEEHOTEL_PRICEALTERATOR_ROUTE_BASE . $this->pluginId() . '.settings';
  }

  /**
   * {@inheritdoc}
   */
  public function alter(array $data, array $pricetable) {}

  /**
   * {@inheritdoc}
   */
  public function description() {
    // Retrieve the @description property from the annotation and return it.
    return $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function status() {
    // Retrieve the @status property from the annotation and return it.
    return $this->pluginDefinition['status'];
  }

  /**
   * {@inheritdoc}
   */
  public function weight() {
    // Retrieve the @weight property from the annotation and return it.
    return $this->pluginDefinition['weight'];
  }

  /**
   * {@inheritdoc}
   */
  public function polarity($value) {
    if ($value > 0) {
      $polarity = "add";
    }
    elseif ($value < 0) {
      $polarity = "sub";
    }
    else {
      $polarity = NULL;
    }
    return $polarity;
  }

  /**
   * Is this alterator enabled by user?
   */
  public function enabled($value) {
    return \Drupal::config($this->configName())->get("enabled");
  }

  /**
   * Get alterator weight given by user.
   */
  public function getUserWeight() {
    $tmp = [];
    $tmp['settings'] = \Drupal::service('config.factory')->get('beehotel_pricealterator.settings');
    $tmp['price_alterators'] = $tmp['settings']->get('price_alterators');

    $user_weight = 0;
    if (isset($tmp['price_alterators'])) {
      $user_weight = $tmp['price_alterators'][$this->pluginId() . "_weight"];
    }

    return (int) $user_weight;
  }

  /**
   * {@inheritdoc}
   */
  protected function getTimezone() {
    return DateTimeItemInterface::STORAGE_TIMEZONE;
  }

  /**
   * {@inheritdoc}
   */
  protected function getTimestorage() {
    return DateTimeItemInterface::DATE_STORAGE_FORMAT;
  }

}
