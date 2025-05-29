<?php

namespace Drupal\bee_hotel\Resolvers;

use Drupal\beehotel_pricealterator\Alter;
use Drupal\beehotel_pricealterator\PreAlter;
use Drupal\bee_hotel\BeeHotel;
use Drupal\beehotel_utils\BeeHotelUnit;
use Drupal\beehotel_utils\Dates;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\commerce\Context;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_price\Resolver\PriceResolverInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Interacts with Commerce price system.
 *
 * @package Drupal\bee_hotel\Resolvers
 */
class SalepriceResolver implements PriceResolverInterface {

  /**
   * The dates Utilities.
   *
   * @var \Drupal\beehotel_utils\Dates
   */
  protected $datesUtil;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The bee hotel utils.
   *
   * @var \Drupal\bee_hotel\BeeHotel
   */
  private $beehotel;

  /**
   * The bee hotel unit.
   *
   * @var \Drupal\beehotel_utils\BeeHotelUnit
   */
  private $beehotelunit;

  /**
   * The Alter manager.
   *
   * @var \Drupal\beehotel_pricealterator\Alter
   */
  private $alterManager;

  /**
   * The Prealter manager.
   *
   * @var \Drupal\beehotel_pricealterator\Prealter
   */
  private $preAlterManager;

  /**
   * The session.
   *
   * @var \Symfony\Component\HttpFoundation\Session\Session
   */
  protected $session;

  /**
   * The plugin manager Interaface.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $pluginManagerInterface;

  /**
   * Constructs the object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\beehotel_utils\Dates $bee_hotel_dates
   *   The BeeHotel Dates Utility.
   * @param \Drupal\bee_hotel\BeeHotel $bee_hotel
   *   The BeeHotel Utilities.
   * @param \Drupal\beehotel_utils\BeeHotelUnit $bee_hotel_unit
   *   The BeeHotel Unit Utility.
   * @param \Drupal\beehotel_pricealterator\Prealter $pre_alter_manager
   *   The BeeHotel Unit Utility.
   * @param \Drupal\beehotel_pricealterator\Alter $alter_manager
   *   The BeeHotel Unit Utility.
   * @param \Symfony\Component\HttpFoundation\Session\Session $session
   *   The session.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $plugin_manager_interface
   *   Plugin manager interface.
   */
  public function __construct(RequestStack $request_stack, Dates $bee_hotel_dates, BeeHotel $bee_hotel, BeeHotelUnit $bee_hotel_unit, Prealter $pre_alter_manager, Alter $alter_manager, Session $session, PluginManagerInterface $plugin_manager_interface) {
    $this->requestStack = $request_stack;
    $this->datesUtil = $bee_hotel_dates;
    $this->beehotel = $bee_hotel;
    $this->beehotelunit = $bee_hotel_unit;
    $this->preAlterManager = $pre_alter_manager;
    $this->alterManager = $alter_manager;
    $this->session = $session;
    $this->pluginManagerInterface = $plugin_manager_interface;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('beehotel_utils.dates'),
      $container->get('bee_hotel.beehotel'),
      $container->get('beehotel_utils.beehotelunit'),
      $container->get('beehotel_pricealterator.prealter'),
      $container->get('beehotel_pricealterator.alter'),
      $container->get('session'),
      $container->get('plugin.manager.beehotel.pricealterator'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(PurchasableEntityInterface $entity, $quantity, Context $context) {

    if ($entity->bundle() != 'bee') {
      return;
    }

    $data = [];
    $data['product'] = $entity->getProduct();

    // See https://www.drupal.org/project/bee_hotel/issues/3446298
    $data['currency_code'] = $this->beehotel->getCurrencyCode($data, $options = []);
    $data['beehotel_units_search_queries'] = $this->session->get('beehotel_units_search_queries');
    $data['order'] = $this->requestStack->getCurrentRequest()->query->get("commerce_order");
    $data['qid'] = $this->requestStack->getCurrentRequest()->query->get("qid");

    // Units search.
    if (isset($data['qid'])) {
      $data['values']['dates'] = $data['beehotel_units_search_queries'][$data['qid']]['dates'];
    }
    // Single unit search.
    elseif (!empty($this->requestStack->getCurrentRequest()->request->get("dates"))) {
      $data['values']['dates'] = $this->requestStack->getCurrentRequest()->request->get("dates");
    }
    else {
      return;
    }

    $this->datesUtil->normaliseDatesFromSearchForm($data);
    $this->datesUtil->easter($data);

    $data = $this->priceFromPriceAlterators($data, $context, $entity);

    $data['amount'] = bee_hotel_number_format($data['amount']);
    $price = new Price($data['amount'], $data['currency_code']);
    return $price;
  }

  /**
   * Get useful data.
   */
  private function priceFromPriceAlterators($data, $context, $entity) {

    $res = [];
    $tmp = $this->session->get('beehotel_units_search_queries');

    if (isset($tmp)) {
      $data['beehotel_units_search_queries'] = reset($tmp);
      $data['adults'] = (int) $data['beehotel_units_search_queries']['guests'];
      $data['context'] = $context;

      $data['date'] = time();
      $data['now'] = time();

      $data['entity'] = $entity;
      $data['node'] = $this->beehotelunit->getVariationNode($entity);
      $data['nid'] = $data['node']->Id();

      // Buggy.
      $data['season'] = $this->preAlterManager->season($data);

      // Move this into day column.
      $this->getSeason($data);

      $res = $this->alterManager->alter($data);
    }

    return $res;
  }

  /**
   * Get data set as Season.
   *
   *   @todo move this into some Util class.
   */
  private function getSeason(&$data) {
    $timestamp = $data['date'];
    $plugin_id = "GetSeason";
    $getSeason = $this->pluginManagerInterface->createInstance($plugin_id, []);
    $getSeason->getThisDaySeasonFromInput($timestamp, $data);
  }

}
