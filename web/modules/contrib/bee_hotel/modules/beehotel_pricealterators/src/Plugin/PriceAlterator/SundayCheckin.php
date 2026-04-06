<?php

namespace Drupal\beehotel_pricealterators\Plugin\PriceAlterator;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\beehotel_utils\BeeHotelCommerce;
use Drupal\beehotel_pricealterator\PriceAlteratorBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a "Sunday Checkin" Price Alterator for BeeHotel.
 *
 * @PriceAlterator(
 *   description = @Translation("Price increases when checkin on Sunday."),
 *   id = "SundayCheckin",
 *   provider = "beehotel_pricealterator",
 *   type = "optional",
 *   status = 1,
 *   weight = 9001,
 * )
 */
class SundayCheckin extends PriceAlteratorBase {

  use StringTranslationTrait;

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
   * Price alterator Increase.
   *
   * @var float|null
   */
  private $increase;

  /**
   * Price alterator Enabled.
   *
   * @var bool|null
   */
  private $enabled;

  /**
   * Constructs a new SundayCheckin alterator.
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
    // Check if checkin day is Sunday (7 = Sunday in ISO-8601).
    if ((date("N", $data['norm']['dates_from_search_form']['checkin']['timestamp']) * 1) == 7) {
      $data['tmp']['price'] = $data['tmp']['price'] + ($this->increase ?? 0);
      $txt = $this->t("Sunday check in costs more. Checking in on Monday instead is much cheaper!");
      \Drupal::messenger()->addWarning($txt);
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
    $value = '';
    $class = '';
    $type = '';

    if (isset($this->increase)) {
      $value = $this->increase;
      $class = "add";
      $type = $this->beehotelCommerce->currentStoreCurrency()->get('symbol');
    }

    $current_value = [
      '#theme' => 'beehotel_pricealterator_current_value',
      '#class' => $class,
      '#string' => $value,
      '#type' => $type,
      '#description' => $this->t("@value@type will be added when checkin is on Sunday", [
        '@type' => $type,
        '@value' => $value,
      ]),
    ];

    return $this->renderer->renderPlain($current_value);
  }

}
