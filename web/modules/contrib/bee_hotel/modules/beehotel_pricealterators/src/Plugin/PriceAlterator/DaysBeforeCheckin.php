<?php

namespace Drupal\beehotel_pricealterators\Plugin\PriceAlterator;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\beehotel_pricealterator\PriceAlteratorBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a "DaysBeforeCheckin" Price Alterator for BeeHotel.
 *
 * @PriceAlterator(
 *   description = @Translation("The closer checkin is, the higher the price"),
 *   id = "DaysBeforeCheckin",
 *   provider = "beehotel_pricealterator",
 *   type = "optional",
 *   status = 1,
 *   buggy = 1,
 *   weight = 3,
 * )
 */
class DaysBeforeCheckin extends PriceAlteratorBase {

  use StringTranslationTrait;

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
   * Price alterator Increase (percentage).
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
   * Price alterator days (how many days before checkin to start increasing).
   *
   * @var int|null
   */
  private $days;

  /**
   * Constructs a new DaysBeforeCheckin alterator.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory,
    RendererInterface $renderer
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $config_factory);

    $this->renderer = $renderer;

    // Load plugin-specific configuration.
    $config = $this->configFactory->get($this->configName());
    $this->status = $config->get('status');
    $this->increase = $config->get('increase');
    $this->days = $config->get('days');
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
   *
   * @todo improve code readability.
   */
  public function alter(array $data, array $pricetable): array {
    // Check days from today not greater than $this->days.
    $elab['BEEHOTEL_STATIC_INCREMENT'] = $this->increase;
    $elab['BEEHOTEL_DAYS_BEFORE'] = $this->days;

    // Timestamp from which we start increasing (days before checkin).
    $elab['tmp'] = $data['norm']['dates_from_search_form']['checkin']['timestamp']
      - (60 * 60 * 24 * $elab['BEEHOTEL_DAYS_BEFORE']);

    $elab['days_first_day_to_increase'] = DrupalDateTime::createFromTimestamp($elab['tmp'])->getTimestamp();
    $elab['days_today'] = DrupalDateTime::createFromTimestamp(time())->getTimestamp();

    // Days from the start increase (can be fractional, giving hourly increments).
    $elab['days_days_from_start_increase'] = ($elab['days_today'] - $elab['days_first_day_to_increase']) / 60 / 60 / 24;

    $elab['price_max_increase_same_day'] = ($data['tmp']['price'] / 100 * $this->increase);

    if ($elab['price_max_increase_same_day'] != 0) {
      $elab['price_daily_increase'] = $elab['price_max_increase_same_day'] / $elab['BEEHOTEL_DAYS_BEFORE'];
      $elab['price_altered'] = $data['tmp']['price'] + ($elab['price_daily_increase'] * $elab['days_days_from_start_increase']);

      if (isset($elab['price_altered']) && $elab['price_altered'] > 0) {
        $data['tmp']['price'] = $elab['price_altered'];
        $data['alterator'][] = __CLASS__;
      }
    }

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
    $type = "%";

    if (isset($this->increase)) {
      $value = $this->t("%increase%type from %days<sup class='smaller'>th</sup>", [
        '%increase' => $this->increase,
        '%days' => $this->days,
        '%type' => $type,
      ]);

      $current_value = [
        '#theme' => 'beehotel_pricealterator_current_value',
        '#class' => 'add',
        '#string' => $value,
        '#type' => NULL,
        '#description' => $this->t("@value@type will be added to price from @days days before checkin", [
          '@type' => $type,
          '@value' => $this->increase,
          '@days' => $this->days,
        ]),
      ];
    }
    else {
      $current_value = [];
    }

    return $this->renderer->renderPlain($current_value);
  }

}
