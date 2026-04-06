<?php

namespace Drupal\beehotel_pricealterators\Plugin\PriceAlterator;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\beehotel_utils\BeeHotelCommerce;
use Drupal\beehotel_pricealterator\PriceAlteratorBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a "Sunday Night Only" Price Alterator for BeeHotel.
 *
 * @PriceAlterator(
 *   description = @Translation("Price goes up when asking to stay one single night on Saturday."),
 *   id = "SaturdayNightOnly",
 *   provider = "beehotel_pricealterator",
 *   type = "optional",
 *   status = 1,
 *   weight = 4,
 * )
 */
class SaturdayNightOnly extends PriceAlteratorBase {

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
   * Price alterator Increase (fixed amount).
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
   * Price alterator Enabled.
   *
   * @var bool|null
   */
  private $enabled;

  /**
   * Constructs a new SaturdayNightOnly alterator.
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
    $this->fixed = $config->get('fixed');
    $this->percentage = $config->get('percentage');
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
      if (date("N", $data['norm']['dates_from_search_form']['checkin']['timestamp']) == 6) {
        if (isset($this->fixed) && $this->fixed != 0) {
          $data['tmp']['price'] += (float) $this->fixed;
        }
        elseif (isset($this->percentage) && $this->percentage != 0) {
          $data['tmp']['price'] += $data['tmp']['price'] / 100 * $this->percentage;
        }
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
      $class = $this->polarity($this->fixed);
      $value = $this->fixed;
      $type = $this->beehotelCommerce->currentStoreCurrency()->get('symbol');
    }
    elseif (isset($this->percentage) && $this->percentage != 0) {
      $class = $this->polarity($this->percentage);
      $value = $this->percentage;
      $type = "%";
    }
    else {
      $class = "empty";
      $value = $this->fixed ?? '';
      $type = "?";
    }

    $current_value = [
      '#theme' => 'beehotel_pricealterator_current_value',
      '#class' => $class,
      '#string' => $value,
      '#type' => $type,
      '#description' => $this->t("@value@type will be added to Saturday one-night reservations", [
        '@type' => $type,
        '@value' => $value,
      ]),
    ];

    return $this->renderer->renderPlain($current_value);
  }

}
