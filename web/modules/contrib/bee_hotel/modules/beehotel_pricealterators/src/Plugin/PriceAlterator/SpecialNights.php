<?php

namespace Drupal\beehotel_pricealterators\Plugin\PriceAlterator;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\beehotel_utils\BeeHotelCommerce;
use Drupal\beehotel_pricealterator\PriceAlteratorBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a "SpecialNights" Price Alterator for BeeHotel.
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
   * The BeeHotel commerce Util.
   *
   * @var \Drupal\beehotel_utils\BeeHotelCommerce
   */
  protected $beehotelCommerce;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

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
   * Constructs a new SpecialNights alterator.
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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory,
    BeeHotelCommerce $beehotel_commerce,
    EntityTypeManagerInterface $entity_type_manager,
    RendererInterface $renderer,
    RouteMatchInterface $route_match
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $config_factory);

    $this->beehotelCommerce = $beehotel_commerce;
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
    $this->routeMatch = $route_match;

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
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('current_route_match')
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
   * @param string $current_night
   *   The night date (ISO date).
   *
   * @return array|null
   *   Special night data or NULL.
   */
  private function isSpecialNight(string $current_night): ?array {
    $date = new DrupalDateTime($current_night);
    $date->setTimezone(new \DateTimezone($this->getTimezone()));
    $formatted = $date->format($this->getTimestorage());

    $nid = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('type', 'special_night')
      ->condition('field_nights.value', $formatted . 'T00:00:00', '<=')
      ->condition('field_nights.end_value', $formatted . 'T23:59:59', '>=')
      ->range(0, 1)
      ->execute();

    if (!empty($nid)) {
      $node = $this->entityTypeManager->getStorage('node')->load(reset($nid));
      if ($node) {
        return [
          "nid" => $node->id(),
          "title" => $node->getTitle(),
          "value" => $node->get('field_alteration')->value,
          "type" => $node->get('field_type')->value,
          "polarity" => $node->get('field_polarity')->value,
        ];
      }
    }
    return NULL;
  }

  /**
   * Get current value info (used for display).
   *
   * @param array $data
   *   Data array.
   * @param array $pricetable
   *   Price table.
   *
   * @return string|array
   *   Rendered string or render array.
   */
  private function getCurrentValue(array $data, array $pricetable) {
    if ($this->routeMatch->getRouteName() == "beehotel_pricealterator.info.chain") {
      $now = new DrupalDateTime('now');
      $now->setTimezone(new \DateTimezone($this->getTimezone()));
      $formatted = $now->format($this->getTimestorage());

      for ($i = 0; $i < 36; $i++) {
        $date = new DrupalDateTime('now +' . $i . ' day');
        $date->setTimezone(new \DateTimezone($this->getTimezone()));
        $formatted = $date->format($this->getTimestorage());
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
    $nextSpecialNight = $this->getCurrentValue($data, $pricetable);

    $current_value = [
      '#theme' => 'beehotel_pricealterator_current_value',
      '#class' => NULL,
      '#string' => $nextSpecialNight,
      '#type' => NULL,
    ];
    return $this->renderer->renderPlain($current_value);
  }

}
