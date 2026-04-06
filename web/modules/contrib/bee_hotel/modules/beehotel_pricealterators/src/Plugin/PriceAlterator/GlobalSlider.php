<?php

namespace Drupal\beehotel_pricealterators\Plugin\PriceAlterator;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\beehotel_utils\BeeHotelCommerce;
use Drupal\beehotel_pricealterator\PriceAlteratorBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a "Global Slider" Price Alterator for BeeHotel.
 *
 * @PriceAlterator(
 *   description = @Translation("Slide globally price up and down"),
 *   id = "GlobalSlider",
 *   provider = "beehotel_pricealterator",
 *   status = 1,
 *   type = "optional",
 *   weight = 9999,
 * )
 */
class GlobalSlider extends PriceAlteratorBase {

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
   * Price alterator global slider value.
   *
   * @var array
   */
  private $globalslider;

  /**
   * Constructs a new GlobalSlider alterator.
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
    $this->globalslider = [$config->get('globalslider')];
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
    $slider = reset($this->globalslider);
    $data['tmp']['price'] += $data['tmp']['price'] / 100 * $slider;
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
    $slider = reset($this->globalslider);
    $value = $slider ?? '';
    $class = $this->polarity($slider) ?: '';
    $percentage = "%";

    $tmp = $this->t("Price will be altered as per percentage value");
    $tmp .= "<br/>" . $this->t("price + (price / 100 * global slice) = Altered price");
    $tmp .= "<br/>" . $this->t("IE: 200 + (200 / 100 * 20) = 240");

    $current_value = [
      '#default_value' => $value,
      '#type' => 'range_slider',
      '#title' => $this->t('Current value:') . " " . $value,
      '#min' => -100,
      '#max' => 100,
      '#step' => 1,
      '#description' => $tmp,
      '#description_display' => 'after',
      '#data-orientation' => 'horizontal',
      '#output' => 'below',
      '#output__field_prefix' => '',
      '#output__field_suffix' => '%',
      '#disabled' => TRUE,
    ];

    return $this->renderer->renderPlain($current_value);
  }

}
