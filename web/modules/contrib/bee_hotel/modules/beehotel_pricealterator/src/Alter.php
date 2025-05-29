<?php

namespace Drupal\beehotel_pricealterator;

use Drupal\beehotel_utils\BeeHotelCommerce;
use Drupal\beehotel_utils\BeeHotelUnit;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Class Alter in charge of the price alteration.
 *
 * @package Drupal\beehotel_pricealterator\Services
 */
class Alter {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The preparing Class.
   *
   * @var \Drupal\beehotel_pricealterator\PreAlter
   */
  protected $preAlter;

  /**
   * The plugin manager.
   *
   * @var \Drupal\beehotel_pricealterator\PriceAlteratorPluginManager
   */
  protected $priceAlteratorPluginManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The BeeHotel commerce Util.
   *
   * @var \Drupal\beehotel_utils\BeeHotelUnit
   */
  protected $beehotelCommerce;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The session.
   *
   * @var \Symfony\Component\HttpFoundation\Session\Session
   */
  protected $session;

  /**
   * The Bee Hotel Unit utility.
   *
   * @var \Drupal\beehotel_utils\BeeHotelUnit
   */
  protected $beeHotelUnit;

  /**
   * Alter constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\beehotel_pricealterator\PreAlter $pre_alter
   *   The prealter process.
   * @param \Drupal\beehotel_pricealterator\PriceAlteratorPluginManager $price_alterator_plugin_manager
   *   The Price alterators manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\beehotel_utils\BeeHotelCommerce $beehotel_commerce
   *   BeeHotel Commerce Utils.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Symfony\Component\HttpFoundation\Session\Session $session
   *   The session.
   * @param \Drupal\beehotel_utils\BeeHotelUnit $bee_hotel_unit
   *   The BeeHotelUnit utility.
   */
  public function __construct(
    AccountInterface $currentUser,
    PreAlter $pre_alter,
    PriceAlteratorPluginManager $price_alterator_plugin_manager,
    EntityTypeManagerInterface $entity_type_manager,
    BeeHotelCommerce $beehotel_commerce,
    RendererInterface $renderer,
    Session $session,
    BeeHotelUnit $bee_hotel_unit,
  ) {
    $this->currentUser = $currentUser;
    $this->preAlter = $pre_alter;
    $this->priceAlteratorPluginManager = $price_alterator_plugin_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->beehotelCommerce = $beehotel_commerce;
    $this->renderer = $renderer;
    $this->session = $session;
    $this->beeHotelUnit = $bee_hotel_unit;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('beehotel_pricealterator.prealter'),
      $container->get('plugin.manager.beehotel.pricealterator'),
      $container->get('entity_type.manager'),
      $container->get('beehotel_utils.beehotelcommerce'),
      $container->get('renderer'),
      $container->get('session'),
      $container->get('beehotel_utils.beehotelunit'),
    );
  }

  /**
   * Contains the alteration flow.
   */
  public function alter($data) {

    $this->session->set('alterators_current_stack', []);

    /* 2. Loop per night.
     * Drupal Commerce calls Resolver once per order item (not once per night)
     * We loop nights here, providing an average price
     * to be multiplied by items (nights).
     * @todo keep track of every night price into the $data array.
     */
    for ($n = 0; $n < $data['norm']['dates_from_search_form']['days']; $n++) {

      // 1. Get content.
      // 1a. Base Table with base price.
      $data['basetable'] = $basetable = $this->preAlter->baseTable($data);
      $data['output'] = 'row';

      // 1b. Available Alterators.
      $alterators = $this->priceAlteratorPluginManager->alterators($data);

      // 1c. Annotation status Alterators.
      $alterators = $this->checkStatus($alterators);

      // 1c. UI enabled  Alterators.
      $alterators = $this->checkEnabled($alterators);

      $data['tmp'] = NULL;
      $data['tmp']['night_timestamp'] =
        $data['norm']['dates_from_search_form']['checkin']['timestamp'] + (24 * 3600 * $n);

      $data['tmp']['night_date'] = date('Y-m-d', $data['tmp']['night_timestamp']);

      foreach ($alterators as $a) {
        $alterator = new $a['class']([], [], [], $this->beehotelCommerce, $this->renderer);
        $data = $alterator->alter($data, $basetable);

        if (isset($data['tmp']['price'])) {

          $price[$data['tmp']['night_timestamp']] = $data['tmp']['price'];

          $data['alterators_current_stack'][$data['tmp']['night_date']][] = [
            'id' => $a['id'],
            'night' => $data['tmp']['night_date'],
            'price' => $data['tmp']['price'],
            'season' => $data['season'],
          ];

        }

      }
    }

    $this->session->set('alterators_current_stack',

    // Price is averaged across nights of reservation.
    // We only expose the last day price.
    // this is an issue when season changes.
    end($data['alterators_current_stack'])
    );

    $data['price'] = $price = array_filter($price);
    $average = count($price) ? array_sum($price) / count($price) : '';
    $data['amount'] = $average;
    return $data;
  }

  /**
   * Check annotation status.
   */
  private function checkStatus($alterators) {
    $a = [];
    foreach ($alterators as $item) {
      if ($item['status'] == 1) {
        $a[] = $item;
      }
    }
    return $a;
  }

  /**
   * Check UI enabled.
   */
  private function checkEnabled($alterators) {

    $a = [];
    foreach ($alterators as $item) {
      if ($item['enabled'] == 1) {
        $a[] = $item;
      }
    }

    return $a;

  }

  /**
   * How to send this output to the debug block?
   *
   * @todo implement the feature.
   */
  public function beehotelLog($data, $context) {}

}
