<?php

namespace Drupal\beehotel_pricealterators\Plugin\PriceAlterator;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\beehotel_pricealterator\PriceAlteratorBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a "Occupants" Price Alterator for BeeHotel.
 *
 * Because the plugin manager class for our plugins uses annotated class
 * discovery, Price Alterators only needs to exist within the
 * Plugin\PriceAlterator namespace, and provide a PriceAlterator
 * annotation to be declared as a plugin. This is defined in
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
 *  The weight Key is the weight for this alterator.
 * Legenda for the 'weight' key:
 * -9999 : heaviest, to be used as very first (reserved)
 * -9xxx : heavy, to be used as first (reserved)
 *     0 : no need to be weighted
 *  1xxx : allowed in custom modules
 *  xxxx : everything else
 *  9xxx : light, to be used as last (reserved)
 *  9999 : lightest, to be used as very last (reserved)
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
   * Price alterator days.
   *
   * @var int
   */
  private $days;

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
   * Constructs a new alterator object.
   */
  public function __construct() {
    $config = \Drupal::config($this->configName());
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
      $plugin_definition
    );
  }

  /**
   * Alter a price.
   *
   * Every Alterator needs to have an  alter method.
   *
   * @todo improve code readibility.
   *
   * @param array $data
   *   Array of data related to this price.
   * @param array $pricetable
   *   Array of prices by week day.
   *
   * @return array
   *   An updated $data array.
   */
  public function alter(array $data, array $pricetable) {

    // Check days from today not major than $this->days.
    $elab['BEEHOTEL_STATIC_INCREMENT'] = $this->increase;
    $elab['BEEHOTEL_DAYS_BEFORE'] = $this->days;

    $elab['timestamp_fra_checkin_ed_inizio_incremento'] = $elab['tmp'] =

    // Checkin timestamp.
    $data['norm']['dates_from_search_form']['checkin']['timestamp'] - (
      // One day.
      60 * 60 * 24
      // Times.
      *
      // Days from which start increasing.
      $elab['BEEHOTEL_DAYS_BEFORE']

    );
    $elab['days_first_day_to_increase'] = DrupalDateTime::createFromTimestamp($elab['tmp'])->getTimestamp();
    $elab['days_today'] = DrupalDateTime::createFromTimestamp(time())->getTimestamp();

    // We technically should floor days_days_from_start_increase to define the
    // very days as integers. While - in the real world - leaving this as it
    // is will give us a price increment on a hourly basis as well. So nice :)
    $elab['days_days_from_start_increase'] = ($elab['days_today'] - $elab['days_first_day_to_increase']) / 60 / 60 / 24;

    $elab['price_max_increase_same_day'] = ($data['tmp']['price'] / 100 * $this->increase);

    if ($elab['price_max_increase_same_day'] <> 0) {
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
   * Get current value for this alterator. We can use this
   * method to get info and settings for the alterator.
   *
   * @param array $data
   *   Array of data related to this price.
   * @param array $pricetable
   *   Array of prices by week day.
   *
   * @return Drupal\Core\StringTranslation\TranslatableMarkup
   *   A translatable abject containing the current value
   */
  public function currentValue(array $data, array $pricetable) {

    $data = [];

    $data['days']['future'] = 10;
    $data['timestamp']['today'] = date('U');
    $data['timestamp']['future'] = date('U', strtotime('+' . $data['days']['future'] . ' day'));
    $data['norm']['dates_from_search_form']['checkin']['timestamp'] = $data['timestamp']['future'];
    $data['tmp']['price'] = 10;
    $data['res'] = $this->alter($data, []);
    $data['type'] = "%";

    if (isset($this->increase)) {
      $data['value'] = $this->t("%increase%type from %days<sup class='smaller'>th</sup>", [
        "%increase" => $this->increase,
        "%days" => $this->days,
        "%type" => $data['type'],
      ]);

      $data['class'] = 'add';

      $current_value = [
        '#theme' => 'beehotel_pricealterator_current_value',
        '#class' => 'add',
        '#string' => $data['value'],
        '#type' => NULL,
        '#description' => $this->t("@value@type will be added to price from @days days before checkin", [
          "@type" => $data['type'],
          "@value" => $this->increase,
          "@days" => $this->days,
        ]),
      ];
    }
    else {
      $current_value = [];
    }
    return \Drupal::service('renderer')->renderPlain($current_value);
  }

}
