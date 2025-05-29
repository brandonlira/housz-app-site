<?php

namespace Drupal\beehotel_pricealterator\Plugin\PriceAlterator;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\beehotel_utils\BeeHotelCommerce;
use Drupal\beehotel_pricealterator\PriceAlteratorBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Get the current price season.
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
 * The weight Key is the weight for this alterator
 * -9999 : heaviest, to be used as very first (reserved)
 * -9xxx : heavy, to be used as first (reserved)
 *     0 : no need to be weighted
 *  1xxx : allowed in custom modules
 *  xxxx : everything else
 *  9xxx : ligh, to be used as last (reserved)
 *  9999 : lighest, to be used as very last (reserved)
 *
 * @PriceAlterator(
 *   id = "GetSeason",
 *   description = @Translation("Get the current season from Weekly Unit Price Table. This price alterator *MUST* be on position 2 from the top of the alterators chain."),
 *   type = "mandatory",
 *   weight = -9999,
 *   status = 1,
 * )
 */
class GetSeason extends PriceAlteratorBase {

  use StringTranslationTrait;

  /**
   * The value for this alterator.
   *
   * @var float
   */
  private $value = NULL;

  /**
   * The type for this alterator.
   *
   * IE: "percentage", or "fixed".
   *
   * @var string
   */
  private $type = NULL;

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
   * The dates Utilities.
   *
   * @var \Drupal\beehotel_utils\Dates
   */
  protected $datesUtil;

  /**
   * Constructs a new alterator object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\beehotel_utils\BeeHotelCommerce $beehotel_commerce
   *   BeeHotel Commerce Utils.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    BeeHotelCommerce $beehotel_commerce,
  ) {
    $this->beehotelCommerce = $beehotel_commerce;
    $this->config = \Drupal::config($this->configName());
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('beehotel_utils.beehotelcommerce'),
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
   * Get season.
   *
   * Get season applied to checkin day.
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
    $data = $this->applyLogic($data);
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
   */
  public function currentValue(array $data, array $pricetable) {
    if ($data['caller'] == 'Drupal\beehotel_pricealterator\PriceAlteratorPluginManager::alterators') {
      $output = [
        '#theme' => 'beehotel_pricealterator_seasons',
        '#low' => [],
        '#high' => [],
        '#peak' => [],
      ];
      return \Drupal::service('renderer')->renderPlain($output);
    }
    else {
      $output = "somesasons";
      return $output;
    }
  }

  /**
   * Apply the logic.
   */
  private function applyLogic($data) {

    $data = beehotel_pricealterator_current_seasons($data);
    $day = $data['norm']['dates_from_search_form']['checkin'];
    $dates = [];
    $dates['checkin']['timestamp'] = $day['timestamp'];
    foreach ($data['seasons']['array']['seasons']['range'] as $key => $ranges) {
      foreach ($ranges as $range) {
        $dates['key'] = $key;
        $dates['from']['ISO8601'] = $range['from'];
        $dates['tmp'] = new DrupalDateTime($dates['from']['ISO8601'], 'UTC');
        $dates['from']['timestamp'] = $dates['tmp']->getTimestamp();
        $dates['to']['ISO8601'] = $range['to'];
        $dates['tmp'] = new DrupalDateTime($dates['to']['ISO8601'], 'UTC');
        $dates['to']['timestamp'] = $dates['tmp']->getTimestamp();
        if (($dates['checkin']['timestamp'] >= $dates['from']['timestamp']) && ($dates['checkin']['timestamp'] <= $dates['to']['timestamp'])) {
          $data['season'] = $dates['key'];
          if (isset($data['tmp']['day_of_the_week'])) {
            $data['tmp']['price'] = $data['basetable'][$data['nid']][$data['season']][$data['tmp']['day_of_the_week']];
          }
          return $data;
        }
      }
    }
    $data['season'] = $data['seasons']['array']['seasons']['fallback'];
    $data['tmp']['price'] = $data['basetable'][$data['nid']][$data['season']][$data['tmp']['day_of_the_week']];

    return $data;
  }

  /**
   * Get Season settings.
   *
   * Get Seasons settings from user input.
   */
  public function getThisDaySeasonFromInput($timestamp, &$data) {

    $data = beehotel_pricealterator_get_config($data);
    $dates = [];
    $dates['checkin']['timestamp'] = $timestamp;
    foreach ($data['seasons']['array']['seasons']['range'] as $label => $season_ranges) {
      foreach ($season_ranges as $seasons_range) {
        $dates['label'] = $label;
        $dates['seasons_range_from']['ISO8601'] = $seasons_range['from'];
        $dates['tmp'] = new DrupalDateTime($dates['seasons_range_from']['ISO8601'], 'UTC');
        $dates['seasons_range_from']['timestamp'] = $dates['tmp']->getTimestamp();
        $dates['seasons_range_to']['ISO8601'] = $seasons_range['to'];
        $dates['tmp'] = new DrupalDateTime($dates['seasons_range_to']['ISO8601'], 'UTC');
        $dates['seasons_range_to']['timestamp'] = $dates['tmp']->getTimestamp();
        if (($timestamp > $dates['seasons_range_from']['timestamp'])) {
          if (($timestamp < $dates['seasons_range_to']['timestamp'])) {
            $data['season'] = $dates['label'];
          }
        }
      }
    }

    if (!isset($data['season'])) {
      // Season is not set. Use fallback season.
      $data['season'] = $data['seasons']['array']['seasons']['fallback'];
    }
    $data['last'] = __METHOD__;
  }

  /**
   * Get Season settings.
   *
   * Get Seasons settings from user input.
   */
  public function getThisDaySeasonFromInputPublic(&$data) {}

}
