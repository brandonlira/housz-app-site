<?php

namespace Drupal\beehotel_pricealterators\Plugin\PriceAlterator;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ExtensionList;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\beehotel_utils\BeeHotelCommerce;
use Drupal\beehotel_pricealterator\PriceAlteratorBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a "ConsecutiveNights" Price Alterator for BeeHotel.
 *
 * @PriceAlterator(
 *   description = @Translation("Promote reservations with preferred duration (nights)."),
 *   id = "ConsecutiveNights",
 *   provider = "beehotel_pricealterator",
 *   status = 1,
 *   type = "optional",
 *   weight = 2,
 * )
 */
class ConsecutiveNights extends PriceAlteratorBase {

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
   * The extension list module service.
   *
   * @var \Drupal\Core\Extension\ExtensionList
   */
  protected $extensionListModule;

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
   * Price alterator Increase (array per number of nights).
   *
   * @var array
   */
  private $increase;

  /**
   * Maximum consecutive nights to consider.
   *
   * @var int
   */
  private $maxConsecutiveNights;

  /**
   * Constructs a new ConsecutiveNights alterator.
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
   * @param \Drupal\Core\Extension\ExtensionList $extension_list_module
   *   The extension list module service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory,
    BeeHotelCommerce $beehotel_commerce,
    RendererInterface $renderer,
    ExtensionList $extension_list_module
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $config_factory);

    $this->beehotelCommerce = $beehotel_commerce;
    $this->renderer = $renderer;
    $this->extensionListModule = $extension_list_module;

    // Load plugin-specific configuration.
    $config = $this->configFactory->get($this->configName());
    $this->status = $config->get('status');
    $this->enabled = $config->get('enabled');

    // Maximum consecutive nights to consider (default to 5 if not defined elsewhere).
    // Previously used PriceAlteratorsSettings::MaxConsecutiveNights->value.
    $this->maxConsecutiveNights = 5;

    $this->increase = [];
    for ($i = 1; $i < $this->maxConsecutiveNights; $i++) {
      $this->increase[$i] = $config->get('increase_' . $i);
    }
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
      $container->get('renderer'),
      $container->get('extension.list.module')
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
    $data['tmp']['price'] = $this->elaborate($data);
    $data['alterator'][] = __CLASS__;
    return $data;
  }

  /**
   * Elaborate value.
   *
   * @param array $data
   *   The data array.
   *
   * @return float|string
   *   Elaborated price or empty string.
   */
  private function elaborate(array $data) {
    $elaborated = '';
    $days = $data['norm']['dates_from_search_form']['days'] ?? 0;

    if (isset($data['tmp']['price']) && $days > 0) {
      $increase = (int) ($this->increase[$days] ?? 0) + 1;
      $elaborated = $data['tmp']['price'] + ($data['tmp']['price'] / 100 * $increase);
    }
    return $elaborated;
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
          "<span class='%class'><span class='value'>%increase</span>%</span>",
          [
            '%class' => $data[$i]['class'],
            '%increase' => $this->increase[$i],
          ]
        );
      }
    }

    $nights[] = [
      '#theme' => 'beehotel_pricealterators_consecutivenights_values',
      '#first' => $data[1] ?: '---',
      '#second' => $data[2] ?: '---',
      '#third' => $data[3] ?: '---',
      '#fourth' => $data[4] ?: '---',
      '#misc' => $data['misc'] ?: '---',
      '#path' => $this->extensionListModule->getPath('beehotel_pricealterators'),
      '#enabled' => $this->enabled,
    ];

    return $this->renderer->renderPlain($nights);
  }

}
