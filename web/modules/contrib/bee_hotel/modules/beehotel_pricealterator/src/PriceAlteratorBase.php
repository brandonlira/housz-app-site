<?php

namespace Drupal\beehotel_pricealterator;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginWithFormsTrait;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the base class for price alterator plugins.
 */
class PriceAlteratorBase extends PluginBase implements PriceAlteratorInterface, ContainerFactoryPluginInterface {

  use PluginWithFormsTrait;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new PriceAlteratorBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function alter(array $data, array $pricetable): array {
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function configName(): string {
    return BEEHOTEL_PRICEALTERATOR_ROUTE_BASE . $this->getPluginId() . '.settings';
  }

  /**
   * {@inheritdoc}
   */
  public function description(): string {
    return $this->pluginDefinition['description'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function status(): bool {
    return (bool) ($this->pluginDefinition['status'] ?? FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function weight(): int {
    return (int) ($this->pluginDefinition['weight'] ?? 0);
  }

  /**
   * Determines the polarity of a numeric value.
   *
   * @param int|float $value
   *   The value to evaluate.
   *
   * @return string|null
   *   'add' if positive, 'sub' if negative, NULL if zero.
   */
  public function polarity($value): ?string {
    if ($value > 0) {
      return "add";
    }
    if ($value < 0) {
      return "sub";
    }
    return NULL;
  }

  /**
   * Checks if this alterator is enabled by the user.
   *
   * @return bool
   *   TRUE if enabled, FALSE otherwise.
   */
  public function enabled(): bool {
    $config = $this->configFactory->get($this->configName());
    return (bool) $config->get('enabled');
  }

  /**
   * Gets the user-defined weight for this alterator.
   *
   * @return int
   *   The weight value.
   */
  // public function getUserWeight(): int {
  //   $globalConfig = $this->configFactory->get('beehotel_pricealterator.settings');
  //   $weights = $globalConfig->get('price_alterators') ?: [];
  //   return (int) ($weights[$this->getPluginId() . '_weight'] ?? 0);
  // }


  public function getUserWeight(): int {
  if (!$this->configFactory) {
    // Log the plugin ID and stop execution with a meaningful message.
    $plugin_id = $this->getPluginId();
    throw new \Exception("Plugin '$plugin_id' does not have ConfigFactory injected. Check its constructor and create() method.");
  }
  $globalConfig = $this->configFactory->get('beehotel_pricealterator.settings');
  $weights = $globalConfig->get('price_alterators') ?: [];
  return (int) ($weights[$this->getPluginId() . '_weight'] ?? 0);
}


  /**
   * Gets the storage timezone used for datetime fields.
   *
   * @return string
   *   The timezone constant.
   */
  protected function getTimezone(): string {
    return DateTimeItemInterface::STORAGE_TIMEZONE;
  }

  /**
   * Gets the storage date format used for datetime fields.
   *
   * @return string
   *   The date storage format constant.
   */
  protected function getTimestorage(): string {
    return DateTimeItemInterface::DATE_STORAGE_FORMAT;
  }

}
