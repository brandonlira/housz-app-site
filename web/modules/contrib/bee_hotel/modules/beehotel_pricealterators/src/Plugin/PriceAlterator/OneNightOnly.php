<?php

namespace Drupal\beehotel_pricealterators\Plugin\PriceAlterator;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\beehotel_utils\BeeHotelCommerce;
use Drupal\beehotel_pricealterator\PriceAlteratorBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a "One Night Only" Price Alterator for BeeHotel.
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
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Price alterator Status.
   *
   * @var bool|null
   */
  private $status;

  /**
   * Price alterator Enabled.
   *
   * @var bool|null
   */
  private $enabled;

  /**
   * Price alterator fixed amount.
   *
   * @var float|null
   */
  private $fixed;

  /**
   * Price alterator percentage.
   *
   * @var int|null
   */
  private $percentage;

  /**
   * Global slider value (unused but kept for compatibility).
   *
   * @var array|null
   */
  private $globalslider;

  /**
   * Constructs a new OneNightOnly alterator.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\beehotel_utils\BeeHotelCommerce $beehotel_commerce
   *   BeeHotel Commerce Utils.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory,
    BeeHotelCommerce $beehotel_commerce,
    RendererInterface $renderer
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $config_factory);

    $this->beehotelCommerce = $beehotel_commerce;
    $this->renderer = $renderer;

    // Load plugin-specific configuration.
    $config = $this->configFactory->get($this->configName());
    $this->status = $config->get('status');
    $this->enabled = $config->get('enabled');
    $this->fixed = $config->get('fixed');
    $this->percentage = $config->get('percentage');
    $this->globalslider = $config->get('globalslider') ? [$config->get('globalslider')] : [];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('beehotel_utils.beehotelcommerce'),
      $container->get('renderer')
    );
  }

  /**
   * Reference to the Alterator (as plugin).
   *
   * @return string
   *   The plugin ID.
   */
  public function pluginId() {
    $tmp = explode("\\", __CLASS__);
    return end($tmp);
  }

  /**
   * {@inheritdoc}
   */
  public function alter(array $data, array $pricetable): array {
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
   * Get current value for this alterator.
   *
   * @param array $data
   *   Array of data related to this price.
   * @param array $pricetable
   *   Array of prices by week day.
   *
   * @return string
   *   Rendered output.
   */
  public function currentValue(array $data, array $pricetable): string {
    $class = '';
    $value = '';
    $type = '';

    if (isset($this->fixed) && $this->fixed != 0) {
      $value = $this->fixed;
      $class = $this->polarity($this->fixed) ?: "";
      $type = $this->beehotelCommerce->currentStoreCurrency()->get('symbol') ?: "";
    }
    elseif (isset($this->percentage) && $this->percentage != 0) {
      $value = $this->percentage;
      $class = $this->polarity($this->percentage) ?: "";
      $type = "%";
    }

    $current_value = [
      '#theme' => 'beehotel_pricealterator_current_value',
      '#class' => $class,
      '#string' => $value,
      '#type' => $type,
    ];
    return $this->renderer->renderPlain($current_value);
  }

}
