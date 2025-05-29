<?php

namespace Drupal\beehotel_pricealterators\Plugin\PriceAlterator;

use Drupal\beehotel_utils\BeeHotelCommerce;
use Drupal\beehotel_pricealterator\PriceAlteratorBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a "SpecialNights" Price Alterator for BeeHotel.
 *
 * Because the plugin manager class for our plugins uses annotated class
 * discovery, Price Alterators only needs to exist within the
 * Plugin\PriceAlterator namespace, and provide a PriceAlterator annotation
 * to be declared as a plugin. This is defined in
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
 *   description = @Translation("Price increases when special nights are requested (Christmas, bank holidays etc)"),
 *   id = "SpecialNights",
 *   provider = "beehotel_pricealterator",
 *   type = "optional",
 *   status = 1,
 *   weight = 9004,
 * )
 */
class SpecialNights extends PriceAlteratorBase {

  use StringTranslationTrait;

  /**
  * The node content type for this alterator.
  *
  * @var string
  */
  // @todo remove uselss const
  const BEEHOTEL_CONTENT_TYPE = 'special_night';

  /**
  * The type for this alterator.
  *
  * @var string
  */
  // @todo remove uselss const
  const BEEHOTEL_TYPE = 'variable';

  /**
   * The value for this alterator.
   *
   * @var float
   */
  // @todo remove uselss const
  const BEEHOTEL_STATIC_INCREMENT = 'variable';

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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, BeeHotelCommerce $beehotel_commerce) {
    $config = \Drupal::config($this->configName());
    $this->status = $config->get('status');
    $this->beehotelCommerce = $beehotel_commerce;
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
   * Alter a price.
   *
   * Every Alterator needs to have an  alter method.
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

    $data['tmp']['special_night'] = $this->isSpecialNight($data['tmp']['night_date']);

    if (isset($data['tmp']['special_night'])) {
      $data['tmp']['value'] = $data['tmp']['special_night']['value'] * 1;
      $data['tmp']['price_pre_alter'] = $data['tmp']['price'];

      if ($data['tmp']['special_night']['type'] == "percentage") {
        $data['tmp']['alteration'] = $data['tmp']['price_pre_alter'] / 100 * $data['tmp']['value'];
        if ($data['tmp']['special_night']['polarity'] == 'add') {
          $data['tmp']['price'] = $data['tmp']['price_pre_alter'] + $data['tmp']['alteration'];
        }
        elseif ($data['tmp']['special_night']['polarity'] == 'subtract') {
          $data['tmp']['price'] = $data['tmp']['price_pre_alter'] - $data['tmp']['alteration'];
        }
      }

      elseif ($data['tmp']['special_night']['type'] == "integer") {
        $data['tmp']['alteration'] = $data['tmp']['value'];
        if ($data['tmp']['special_night']['polarity'] == 'add') {
          $data['tmp']['price'] = $data['tmp']['price_pre_alter'] + $data['tmp']['alteration'];
        }
        elseif ($data['tmp']['special_night']['polarity'] == 'subtract') {
          $data['tmp']['price'] = $data['tmp']['price_pre_alter'] - $data['tmp']['alteration'];
        }
      }

      elseif ($data['tmp']['special_night']['type'] == "fixed") {
        $data['tmp']['price'] = $data['tmp']['value'];
      }

    }
    $data['alterator'][] = __CLASS__;
    return $data;
  }

  /**
   * Is this a special night?
   *
   * Every Alterator needs to have an  alter method.
   *
   * @param string $current_night
   *   Array of data related to this price.
   *
   * @return array
   *   An updated $data array.
   */
  private function isSpecialNight(string $current_night) {

    $date = new DrupalDateTime($current_night);
    $date->setTimezone(new \DateTimezone($this->getTimezone()));
    $formatted = $date->format($this->getTimestorage());
    $specialnight = NULL;
    $nid = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('field_nights.value', $formatted . 'T00:00:00', '<=')
      ->condition('field_nights.end_value', $formatted . 'T23:59:59', '>=')
      ->range(0, 1)
      ->execute();

    if ($nid) {
      $node = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($nid);
      $node = reset($node);

      $specialnight = [
        "nid" => $node->Id(),
        "title" => $node->getTitle(),
        "value" => $node->get('field_alteration')->value,
        "type" => $node->get('field_type')->value,
        "polarity" => $node->get('field_polarity')->value,
      ];
    }
    return $specialnight;
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
   *   Array of prices.
   *
   * @return array
   *   A render array as expected by the renderer
   */
  private function getCurrentValue(array $data, array $pricetable) {
    // In this price alterator we only need  the current value.
    // for the Alterators table.
    // Is this valid for every method in plugin classes?
    if (\Drupal::routeMatch()->getRouteName() == "beehotel_pricealterator.info.chain") {
      $date = new DrupalDateTime('now');
      $date->setTimezone(new \DateTimezone($this->getTimezone()));
      $formatted = $date->format($formatted = $date->format($this->getTimestorage()));
      for ($i = 0; $i < 36; $i++) {
        $date = new DrupalDateTime('now +' . $i . ' day');
        $date->setTimezone(new \DateTimezone($this->getTimezone()));
        $formatted = $date->format($formatted = $date->format($this->getTimestorage()));
        $nextSpecialNight = $this->isSpecialNight($formatted);
        if (isset($nextSpecialNight)) {

          $description = "<span style='font-weight:normal;font-size:0.6em;'>" . $this->t("Next: %next in %number days", [
            "%next" => $nextSpecialNight['title'],
            "%number" => $i,
          ]) . "</span>";

          $current_value = [
            '#theme' => 'beehotel_pricealterator_current_value',
            '#class' => "",
            '#string' => $i == 0 ? $this->t("Now") : $nextSpecialNight['title'],
            '#type' => "",
            '#description' => $description,
          ];
          return $current_value;
        }
      }
    }
    return "***";
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
   *   A translatable object containing the current value
   */
  public function currentValue(array $data, array $pricetable) {
    $nextSpecialNight = $this->getCurrentValue($data, $pricetable);

    $current_value = [
      '#theme' => 'beehotel_pricealterator_current_value',
      '#class' => NULL,
      '#string' => $nextSpecialNight,
      '#type' => NULL,
    ];
    return \Drupal::service('renderer')->renderPlain($current_value);

  }

}
