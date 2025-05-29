<?php

namespace Drupal\beehotel_utils;

use Drupal\commerce_product\Entity\Product;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Manage the BeeHotel Unit.
 */
class BeeHotelUnit {

  use StringTranslationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Drupal configuration service container.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

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
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Alter constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   * @param \Drupal\beehotel_utils\Dates $bee_hotel_dates
   *   The BeeHotel Dates Utility.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(
    AccountInterface $currentUser,
    EntityTypeManagerInterface $entity_type_manager,
    MessengerInterface $messenger,
    ConfigFactory $config_factory,
    Dates $bee_hotel_dates,
    RequestStack $request_stack,
    RendererInterface $renderer
  ) {
    $this->currentUser = $currentUser;
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
    $this->configFactory = $config_factory;
    $this->datesUtil = $bee_hotel_dates;
    $this->requestStack = $request_stack;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('entity_type.manager'),
      $container->get('messenger'),
      $container->get('config.factory'),
      $container->get('beehotel_utils.dates'),
      $container->get('request_stack'),
      $container->get('renderer'),
    );
  }

  /**
   * Is this node a beehotelunit?
   *
   * @param Drupal\node\Entity\Node $node
   *   A node to be validated.
   *
   * @return bool
   *   Bool TRUE | FALSE
   */
  public function isThisNodeBeeHotel(Node $node) {
    $data = [];
    $storage = $this->entityTypeManager->getStorage('node_type');
    $data['node_bundle'] = $node->bundle();
    $data['node_type'] = $storage->load($data['node_bundle']);
    $data['bee_settings'] = $data['node_type']->getThirdPartySetting('bee', 'bee');
    if (isset($data['bee_settings'])  &&  $data['bee_settings']['bookable'] == 1) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * A List of Bee Hotel Units.
   *
   * @param array $options
   *   An array of options.
   *
   * @return array
   *   An array of nodes.
   */
  public function beeHotelUnits(array $options) {

    $storage = $this->entityTypeManager->getStorage('node');
    $nids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('field_accept_reservations', 1)
      ->execute();
    return $storage->loadMultiple($nids);
  }

  /**
   * Get a BeeHotel Units?
   *
   * @param array $options
   *   Options query.
   *
   * @return array
   *   An array of BeeHotel Units.
   */
  public function getBeeHotelUnits(array $options) {
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('field_accept_reservations', 1, '=');
    $nids = $query->execute();
    return $storage->loadMultiple($nids);
  }

  /**
   * Get a list of beeHotel Units receiving reservations.
   *
   * @return array
   *   Give a list of units.
   */
  public function getReceivingUnits() {

    $storage = $this->entityTypeManager->getStorage('bat_unit');

    $unit_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->execute();

    $bat_units = $storage->loadMultiple($unit_ids);
    $units = [];
    foreach ($bat_units as $bat_unit) {
      $bid = $bat_unit->Id();
      if (isset($bid)) {
        if ($unit = $this->getUnitFromBid($bid)) {
          if ($unit['node']->get("field_accept_reservations")->value == 1) {
            $units[$bid] = $unit;
          }
        }
      }
    }

    if (count($units) < 1) {
      $tmp = $this->t("No Unit is receiving reservation. Check your Unit's setup");
      $this->messenger->addError($tmp);
      (new RedirectResponse('/admin/content'))->send();
    }
    return $units;
  }

  /**
   * Get Units available in a given time range.
   *
   * @param array $data
   *   A structured array.
   *
   * @return array
   *   An array with useful data.
   */
  public function getAvailableUnits(array &$data) {

    $utilDates = new Dates($data);
    $utilDates->normaliseDatesFromSearchForm($data);
    $utilDates->easter($data);
    $data['event_type'] = 'availability_daily';

    // Fetch every unit type we can book for the overnight.
    foreach (bat_unit_type_ids("daily") as $k => $v) {
      $data['unit_types'][$k] = $k;
    }

    // Oct23: bat supports multiple unit types.
    $data['available_units'] = bat_event_get_matching_units(
      $start_date = $data['norm']['dates_from_search_form']['checkin']['object']['day'],
      $end_date = $data['norm']['dates_from_search_form']['lastnight']['object'],
      $valid_name_states = ['bee_daily_available'],
      $type_ids = $data['unit_types'],
      $event_type = $data['event_type'],
      $intersect = NULL,
      $drupal_units = NULL,
    );

    return $data;
  }

  /**
   * Load data for the BeeHotel unit.
   */
  public function getUnitFromBid($bid) {
    $unit = [];
    $unit['bid'] = $bid;
    $unit['node'] = $this->getUnitNode($unit);

    if ($unit['node'] === FALSE) {
      return;
    }

    $unit['pid'] = $unit['node']->get("field_product")[0]->target_id;
    $unit['product'] = Product::load((int) $unit['pid']);
    $unit['store'] = $this->getUnitStore($unit);
    $unit['bat'] = current($this->entityTypeManager->getStorage('bat_unit')->loadByProperties(['id' => $bid]));
    $unit['bee_settings'] = $this->configFactory->get('bee');
    $unit['product_variations'] = $unit['product']->getVariations();

    // Performance killer!
    $unit['cover_image'] = $this->getUnitMainImage($unit['node']);
    return $unit;
  }

  /**
   * Get BAT unit ID from node.
   */
  public function getBidFromNode($node) {
    return $node->get('field_availability_daily')->target_id;
  }

  /**
   * For a given Unit, get the Store.
   */
  public function getUnitStore($unit) {
    $unit['tmp'] = $unit['product']->get("stores");
    $unit['tmp'] = $unit['tmp']->getValue();
    $store = $this->entityTypeManager->getStorage('commerce_store')
      ->load(reset($unit['tmp'])['target_id']);
    return $store;
  }

  /**
   * Get the node of a BEE Hotel BAT unit.
   */
  public function getUnitNode($unit) {
    $node = $this->entityTypeManager->getStorage('node')
      ->loadByProperties([
        'field_availability_daily' => $unit['bid'],
      ]);
    return reset($node);
  }

  /**
   * Get the node of a Bee Hotel Commerce product variation.
   */
  public function getVariationNode($variation) {
    $product = $variation->getProduct();
    $node = $this->entityTypeManager->getStorage('node')
      ->loadByProperties([
        'field_product' => $product->Id(),
      ]);
    return reset($node);
  }

  /**
   * Get the main image for a given node.
   */
  public function getUnitMainImage(Node $node) {

    $config = $this->configFactory->get('beehotel.settings');

    $fieldCoverImage = [];
    $fieldCoverImage['field'] = $config->get("beehotel")['beehotel_unit']['field_cover_image'];
    $fieldCoverImage['tid'] = $node->get($fieldCoverImage['field'])->target_id;

    // Field name field_cover_image is a standard in the bee hotel enviroment.
    // @todo set style_name via UI.
    if (!empty($fieldCoverImage['tid'])) {
      $fieldCoverImage['uri'] = isset($node->get($fieldCoverImage['field'])->entity) ? $node->get($fieldCoverImage['field'])->entity->getFileUri() : NULL;

      // @todo Check cover_image_teaser imag style exists.
      if ($fieldCoverImage['uri']) {
        $fieldCoverImage['image'] = [
          '#theme' => 'image_style',
          '#style_name' => 'cover_image_teaser',
          '#uri' => $fieldCoverImage['uri'],
        ];

        // Performance killer!
        // We only want cover image markup when:
        // * search units (qid is set)
        $request = $this->requestStack->getCurrentRequest();
        $qid = $request->query->get('qid');

        if (isset($qid)) {
          $fieldCoverImage['markup'] = $this->renderer->render($fieldCoverImage['image']);
        }
      }
    }
    else {
      $output = "This Unit node has no valid cover image, which is required";
      return $output;
    }
    return $fieldCoverImage;
  }

  /**
   * Get the currency of a given node.
   */
  public function getNodeCurrency(Node $node) {
    if (isset($node)) {
      $data = [];
      $data['tmp'] = $node->get('field_product')->getValue()[0]['target_id'];
      $data['product'] = $this->entityTypeManager->getStorage('commerce_product')->load($data['tmp']);
      $data['stores'] = $data['product']->getStores();
      $data['store'] = reset($data['stores']);
      $data['currency_code'] = $data['store']->get('default_currency')->getValue()[0]['target_id'];
      return $data['currency_code'];
    }
  }

  /**
   * Get current variation.
   */
  public function getCurrentVariation($unit, $data) {
    if (!empty($unit['node'])) {
      foreach ($unit['product_variations'] as $variation) {
        $data['request_guests'] = (int) $data['values']['guests'];
        $data['variation_max_occupancy'] = $this->maxOccupancy($unit['node']);
        if ($data['request_guests'] <= $data['variation_max_occupancy']) {
          return $variation;
        }
      }
    }
  }

  /**
   * Get the product for a given node.
   */
  public function getNodeProduct(Node $node) {
    if (isset($node)) {
      $data = [];
      $data['pid'] = $node->get("field_product")->target_id;
      $data['product'] = Product::load((int) $data['pid']);
      return $data['product'];
    }
  }

  /**
   * Get variations for a given product.
   */
  public function getProductVariations(Product $product) {
    if (isset($product)) {
      $data = [];
      $data['vids'] = $product->getVariationIds();
      foreach ($data['vids'] as $v) {
        $data['variation'][$v] = $this->entityTypeManager->getStorage('commerce_product_variation')->load((int) $v);
      }
      return $data['variation'];
    }
  }

  /**
   * Find max number occupancy across variants.
   */
  public function maxOccupancy($node) {
    $data = [];
    if (isset($node)) {
      $data['product'] = $this->getNodeProduct($node);
      $data['variations'] = $this->getProductVariations($data['product']);

      // Get occupancy from node field.
      // Occupancy from double setting ($node field and product variation).
      // This should be limited to the product variation (as guest attribute).
      $data['occupancy'][] = $node->get('field_occupancy')->value;

      // Get occupancy from node field.
      // @todo add a custom field to attribute.
      foreach ($data['variations'] as $variation) {
        $data['occupancy'][] = $variation->get('field_max_occupancy')->value;
      }
    }
    else {
      $units = $this->getReceivingUnits();
      foreach ($units as $unit) {
        foreach ($unit['product_variations'] as $variation) {
          $value = $variation->get("field_max_occupancy")->value;
          if (isset($value)) {
            $data['occupancy'][] = $value;
          }
        }
      }
    }
    if (count($data) == 0) {
      $tmp = ("Something wrong with commerce setup...");
      $this->messenger->addError($tmp);
      (new RedirectResponse('/admin/commerce'))->send();
      exit();
    }

    return (int) max($data['occupancy']);

  }

  /**
   * Store usefull data in session.
   *
   * @param array $data
   *
   *   Return array.
   */
  public function storeInSession(array $data) {
    $session = $this->requestStack->getSession();
    $session->set('beehotel_data', $data);
  }

  /**
   * Get nights for a given entity. Deprecated.
   */
  public function beeHotelCalculateNights(&$entity) {

    $tmp = [];
    if ($entity->hasField('field_checkin') && $entity->hasField('field_check_out')) {
      $dateTime = new DrupalDateTime($entity->get('field_checkin')->value, 'UTC');
      $tmp['checkin_timestamp'] = $dateTime->getTimestamp();
      $dateTime = new DrupalDateTime($entity->get('field_checkout')->value, 'UTC');
      $tmp['checkout_timestamp'] = $dateTime->getTimestamp();
      $tmp['days'] = (int) ceil(($tmp['checkout_timestamp'] - $tmp['checkin_timestamp']) / 60 / 60 / 24);
    }
  }

  /**
   * Enable reservations for Bee Hotel Units.
   */
  public function beeHotelUnitsEnableAcceptReservations() {}

  /**
   * Store pause from reservation.
   *
   * When a BHU is paused from accepting reservation, store data in
   * module configuration.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *
   *   Current node.
   */
  public function registerAcceptReservationPause(EntityInterface $node) {

    if ($node->hasField('field_accept_reservations')) {
      $data = [];

      $data['config'] = $this->configFactory->getEditable('beehotel.settings');
      $timestamp = time() + ($node->get("field_accept_reservations")->value * 60 * 60);
      $data['config']->set("reservation_paused_" . $node->Id(), $timestamp)->save();
    }

  }

  /**
   * Check variations for a given node.
   *
   * Bee Hotel required a match between max occupancy set per node.
   * with number of variations, with related max occupancy.
   */
  public function checkProductVariations(Node $node) {

    if (!$this->currentUser->hasPermission('configure beehotel settings')) {
      return FALSE;
    }

    $data = [];
    $config = $this->configFactory->getEditable('beehotel.settings');

    if ($node->hasField('field_occupancy') && !$node->get("field_occupancy")->isEmpty()) {
      $data['node']['maxOccupancy'] = $node->get("field_occupancy")->value;
      $data['tmp'] = $node->get("field_product")->target_id;
      $data['product']['object'] = Product::load((int) $data['tmp']);
      $data['product']['variations'] = $data['product']['object']->getVariationIds();

      if ($data['node']['maxOccupancy'] <> count($data['product']['variations'])) {
        $tmp = 'Occupancy for this unit is ' . $node->get("field_occupancy")->value . '. Number of variations MUST match occupancy. ';
        $tmp .= "Check <a href='/product/" . $data['product']['object']->Id() . "/variations'>variations</a> for this Unit.";
        $this->messenger->addWarning(Markup::create($tmp));
        $config->set('unit_reservation_form_disabled', TRUE)->save();
        return;
      }
      else {
        $config->set('unit_reservation_form_disabled', FALSE)->save();
      }
    }
  }

  /**
   * Deprecated. Use checkBasePriceTable.
   */
  public function validateBasePriceTable(EntityInterface $node) {

    $data = [];
    $data['config'] = $this->configFactory('beehotel_pricealterator.settings');
    $data['days'] = $this->datesUtil->days();

    $status = 1;
    foreach ($data['days'] as $k => $label) {
      $tmp = [];
      $tmp['nid_day_high'] = $node->Id() . "_" . $k . "_high";
      $tmp['nid_day_low'] = $node->Id() . "_" . $k . "_low";
      $tmp['nid_day_peak'] = $node->Id() . "_" . $k . "_peak";
      foreach ($tmp as $neededvar) {
        if ($data['config']->get($neededvar) == NULL  ||  !is_numeric($data['config']->get($neededvar))) {
          $status = 0;
        }
      }
    }
    return $status;
  }

  /**
   * Check the node price table is correctly filled.
   */
  public function checkBasePriceTable(EntityInterface $node) {

    if (!$this->currentUser->hasPermission('configure beehotel settings')) {
      return FALSE;
    }

    if (!$this->isThisNodeBeeHotel($node)) {
      return FALSE;
    }

    $data = [];
    $data['config'] = $this->configFactory->getEditable('beehotel_pricealterator.settings');
    $data['days'] = $this->datesUtil->days();

    $status = TRUE;
    foreach ($data['days'] as $k => $label) {
      $tmp = [];
      $tmp['nid_day_high'] = $node->Id() . "_" . $k . "_high";
      $tmp['nid_day_low'] = $node->Id() . "_" . $k . "_low";
      $tmp['nid_day_peak'] = $node->Id() . "_" . $k . "_peak";
      foreach ($tmp as $neededvar) {
        if ($data['config']->get($neededvar) == NULL  ||  !is_numeric($data['config']->get($neededvar))) {
          $status = FALSE;

          $data['tmp'] = $this->t("Weekly price table is not correct.");
          $data['tmpuri'] = Url::fromUri("base://node/" . $node->Id() . "/basepricetable", ['absolute' => FALSE])->toString();

          $data['tmp'] = $this->t("Weekly price table is not correct. Please <a href='@path'>check price</a>", ['@path' => $data['tmpuri']]) . " ";
          $data['tmp'] .= $this->t("for every season, for every weekly day");
          $this->messenger->addWarning(Markup::create($data['tmp']));
        }
      }
    }
    return $status;
  }

  /**
   * Check the node price table is correctly filled.
   */
  public function checkUnitAcceptsReservations(EntityInterface $node) {

    $config = $this->configFactory->getEditable('beehotel.settings');

    // Warn this unit is not receiving reservations.
    if ($node->hasField('field_accept_reservations')) {

      if ($node->get('field_accept_reservations')->value != 1) {
        $data['tmpuri'] = Url::fromUri("base://node/" . $node->Id() . "/edit", ['absolute' => FALSE])->toString();
        $data['tmp'] = $this->t("This Bee Hotel Unit is not receiving reservations. <a href='@path'>Edit</a> to enable reservation.", ['@path' => $data['tmpuri']]);
        $this->messenger->addWarning(Markup::create($data['tmp']));
        $config->set('unit_reservation_form_disabled', TRUE)->save();
      }
    }
  }

}
