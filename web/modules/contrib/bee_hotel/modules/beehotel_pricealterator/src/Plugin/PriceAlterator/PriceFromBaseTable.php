<?php

namespace Drupal\beehotel_pricealterator\Plugin\PriceAlterator;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\beehotel_utils\BeeHotelCommerce;
use Drupal\beehotel_pricealterator\PriceAlteratorBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Get the Base Price from the BaseTable.
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
   * Constructs a new PriceFromBaseTable alterator.
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
   *   This value matches the ID in the @PriceAlterator annotation.
   */
  public function pluginId() {
    $tmp = explode("\\", __CLASS__);
    return end($tmp);
  }

  /**
   * {@inheritdoc}
   */
  public function alter(array $data, array $basetable): array {
    // Determine the day of the week.
    $day = strtolower(date("D", $data['tmp']['night_timestamp']));
    $data['tmp']['day_of_the_week'] = $day;

    $nid = $data['nid'] ?? NULL;
    $season = $data['season'] ?? NULL;

    // Verify the key hierarchy exists before assigning.
    if (isset($basetable[$nid][$season][$day])) {
      $data['tmp']['price'] = $basetable[$nid][$season][$day];
    }
    else {
      // Log the missing price.
      \Drupal::logger('beehotel_pricealterator')->warning(
        'Price missing in BaseTable for NID: @nid, Season: @season, Day: @day',
        ['@nid' => $nid, '@season' => $season, '@day' => $day]
      );

      // Set a fallback price if needed.
      $data['tmp']['price'] = $data['tmp']['price'] ?? 0;
    }

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
   * @return string
   *   Rendered output.
   */
  public function currentValue(array $data, array $pricetable): string {
    $current_value = [
      '#theme' => 'beehotel_pricealterator_current_value',
      '#class' => "",
      '#string' => "Unit > Weekly table",
      '#type' => "",
      '#description' => $this->t("to update base price"),
    ];
    return $this->renderer->renderPlain($current_value);
  }

}
