<?php

namespace Drupal\beehotel_pricealterators\Plugin\PriceAlterator;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\beehotel_utils\BeeHotelCommerce;
use Drupal\beehotel_pricealterator\PriceAlteratorBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a "Checkin Time" Price Alterator for BeeHotel.
 *
 * @PriceAlterator(
 * id = "CheckinTime",
 * description = @Translation("Price variation based on check-in time"),
 * provider = "beehotel_pricealterator",
 * type = "optional",
 * status = 1,
 * weight = 500,
 * )
 */
class CheckinTime extends PriceAlteratorBase {

  /**
   * The BeeHotel commerce utility.
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
   * The request stack to access the session.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Status of the alterator.
   *
   * @var bool
   */
  private $enabled;

  /**
   * Configured time slots.
   *
   * @var array
   */
  private $timeSlots;

  /**
   * Constructs a CheckinTime object.
   *
   * @param array $configuration
   * A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   * The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   * The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   * The config factory.
   * @param \Drupal\beehotel_utils\BeeHotelCommerce $beehotel_commerce
   * The BeeHotel commerce utility.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   * The renderer service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   * The request stack.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ConfigFactoryInterface $config_factory,
    BeeHotelCommerce $beehotel_commerce,
    RendererInterface $renderer,
    RequestStack $request_stack
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $config_factory);

    $this->beehotelCommerce = $beehotel_commerce;
    $this->renderer = $renderer;
    $this->requestStack = $request_stack;

    $config = $this->configFactory->get($this->configName());
    $this->enabled = (bool) $config->get('enabled');
    $this->timeSlots = $config->get('time_slots') ?: [];
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
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function alter(array $data, array $pricetable): array {
    if (!$this->enabled || empty($this->timeSlots)) {
      return $data;
    }

    $checkin_time = $this->getCheckinTimeFromSession();
    if (!$checkin_time) {
      return $data;
    }

    $current_time = strtotime($checkin_time);

    foreach ($this->timeSlots as $slot) {
      // Ensure keys exist to prevent warnings.
      if (!isset($slot['start'], $slot['end'])) {
        continue;
      }

      $start = strtotime($slot['start']);
      $end = strtotime($slot['end']);

      if ($current_time >= $start && $current_time <= $end) {
        $adjustment = (float) ($slot['adjustment'] ?? 0);

        if ($adjustment != 0) {
          // Store original price if not already stored.
          if (!isset($data['tmp']['price_pre_alter'])) {
            $data['tmp']['price_pre_alter'] = $data['tmp']['price'];
          }

          $data['tmp']['price'] += $adjustment;
          $data['tmp']['checkin_time_alteration'] = $adjustment;
          $data['tmp']['applied_time_slot'] = $slot['label'] ?? ($slot['start'] . '-' . $slot['end']);
        }
        break;
      }
    }

    $data['alterator'][] = __CLASS__;
    return $data;
  }

  /**
   * Retrieves the selected check-in time from the user session.
   *
   * @return string|null
   * The check-in time string or null.
   */
  private function getCheckinTimeFromSession() {
    return $this->requestStack->getCurrentRequest()->getSession()->get('beehotel_checkin_time');
  }

  /**
   * {@inheritdoc}
   */
  public function currentValue(array $data, array $pricetable): string {
    if (empty($this->timeSlots)) {
      return (string) $this->t('No time slots configured.');
    }

    $output = '<div class="checkin-values">';
    foreach ($this->timeSlots as $slot) {
      $label = $slot['label'] ?: $this->t('Standard');
      $output .= sprintf(
        "<div><strong>%s (%s-%s)</strong>: %s%s</div>",
        htmlspecialchars($label),
        htmlspecialchars($slot['start']),
        htmlspecialchars($slot['end']),
        $slot['adjustment'] > 0 ? '+' : '',
        (float) $slot['adjustment']
      );
    }
    $output .= '</div>';

    return $output;
  }

}
