<?php

namespace Drupal\bee_hotel\Form;

use Drupal\beehotel_utils\BeeHotelUnit;
use Drupal\beehotel_utils\Dates;
use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_product\Entity\Product;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;

define('FIELD_PRODUCT', 'field_product');
define('RESERVATION_CHECK_IN_TIME', '15:00');
define('RESERVATION_CHECK_OUT_TIME', '10:00');

/**
 * Reservation form for a unit.
 *
 * @package Drupal\bee_hotel
 *   Defines the reservation form for a unit
 */
class BookThisUnitForm extends FormBase {

  /**
   * The bee hotel unit.
   *
   * @var \Drupal\beehotel_utils\BeeHotelUnit
   */
  private $beehotelunit;

  /**
   * The bee hotel date utility.
   *
   * @var \Drupal\beehotel_utils\Dates
   */
  private $beehoteldates;

  /**
   * The session.
   *
   * @var \Symfony\Component\HttpFoundation\Session\Session
   */
  protected $session;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The cart manager.
   *
   * @var \Drupal\commerce_cart\CartManagerInterface
   */
  protected $cartManager;

  /**
   * The cart provider.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs the object.
   *
   * @param \Drupal\beehotel_utils\BeeHotelUnit $bee_hotel_unit
   *   The BeeHotel Unit Utility.
   * @param \Drupal\beehotel_utils\Dates $bee_hotel_dates
   *   The BeeHotel Dates Utility.
   * @param \Symfony\Component\HttpFoundation\Session\Session $session
   *   The session.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_cart\CartManagerInterface $cart_manager
   *   The cart manager.
   * @param \Drupal\commerce_cart\CartProviderInterface $cart_provider
   *   The cart provider.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(BeeHotelUnit $bee_hotel_unit, Dates $bee_hotel_dates, Session $session, EntityTypeManagerInterface $entity_type_manager, CartManagerInterface $cart_manager, CartProviderInterface $cart_provider, RouteMatchInterface $route_match, RequestStack $request_stack) {
    $this->beehotelunit = $bee_hotel_unit;
    $this->beehoteldates = $bee_hotel_dates;
    $this->session = $session;
    $this->entityTypeManager = $entity_type_manager;
    $this->cartManager = $cart_manager;
    $this->cartProvider = $cart_provider;
    $this->routeMatch = $route_match;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('beehotel_utils.beehotelunit'),
      $container->get('beehotel_utils.dates'),
      $container->get('session'),
      $container->get('entity_type.manager'),
      $container->get('commerce_cart.cart_manager'),
      $container->get('commerce_cart.cart_provider'),
      $container->get('current_route_match'),
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bee_hotel_book_this_unit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $data = [];

    $data['beehotel_settings'] = $this->config('beehotel.settings');
    $data['bee_settings'] = $this->config('bee.settings');
    $data['node'] = $this->routeMatch->getParameter('node');
    $data['bid'] = $this->beehotelunit->getBidFromNode($data['node']);

    if ($this->beehotelunit->isThisNodeBeeHotel($data['node']) == FALSE) {
      return $this->t("A Bee Hotel Unit is required");
    }

    if ($data['beehotel_settings']->get("beehotel.off_value")) {
      $this->messenger()->addWarning($data['beehotel_settings']->get("beehotel.off_text"));
      return;
    }

    if ($data['node']->hasField(FIELD_PRODUCT) != TRUE) {
      return;
    }

    $data['default_values']['dates'] = date("j M Y", strtotime("+1 day")) . " - " . date("j M Y", strtotime("+3 day"));
    $data['request_values']['row'] = $this->requestStack->getCurrentRequest()->query->get("v");

    // Pre-fill for Litepicker.
    if (!empty($data['request_values']['row'])) {
      $data['request_values']['pieces'] = explode("-", $data['request_values']['row']);
      $this->beehoteldates->fromRequestToLitepicker($data);
    }

    $form['node'] = [
      '#type' => 'hidden',
      '#value' => $data['node']->id(),
    ];

    if (trim((string) $data['beehotel_settings']->get("beehotel.book_this_unit_header")) == "<ct-label>") {
      $form['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Book this @t', ['@t' => $data['node']->type->entity->label()]),
      ];
    }
    elseif (trim((string) $data['beehotel_settings']->get("beehotel.book_this_unit_header")) == "<title>") {
      $form['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Book @t', ['@t' => $data['node']->getTitle()]),
      ];
    }
    else {
      $form['title'] = [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Book now'),
      ];
    }

    $form['dates'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Check in > Check out'),
      '#default_value' => $data['default_values']['dates'],
      '#attributes' => [
        'class' => [
          'book-this-unit',
          'edit-dates',
          'bee_hotel_search_availability',
        ],
      ],
      '#required' => TRUE,
    ];

    $product_field = $data['node']->get(FIELD_PRODUCT)->getValue();
    $data['pid'] = $product_field[0]['target_id'];

    $data['max_occupancy'] = $this->beehotelunit->maxOccupancy($data['node']);

    $options = [];
    for ($o = 1; $o <= $data['max_occupancy']; $o++) {
      $options[$o] = $o;
    }

    $form['guests'] = [
      '#type' => 'select',
      '#title' => $this->t('Guests'),
      '#options' => $options,
    ];

    if (isset($data['request_values']['pieces'][6])) {
      $form['guests']['#default_value'] = trim((int) $data['request_values']['pieces'][6]);
    }

    // Product ID (commerce).
    $form['pid'] = [
      '#type' => 'hidden',
      '#value' => $data['pid'],
    ];

    // Unit ID (BAT).
    $form['bid'] = [
      '#type' => 'hidden',
      '#value' => $data['bid'],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $data['beehotel_settings']->get("beehotel.book_this_unit_submit"),
      '#name' => $data['beehotel_settings']->get("beehotel.book_this_unit_submit"),
    ];

    $form['#attached']['library'][] = 'bee_hotel/book-this-unit';
    $form['#attached']['library'][] = 'bee_hotel/beehotel-litepicker';
    $form['#weight'] = -2000;

    if ($data['beehotel_settings']->get("unit_reservation_form_disabled") == TRUE) {
      $form['#disabled'] = TRUE;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    $data = [];

    $data['values'] = $form_state->getValues();
    $data['node'] = $this->routeMatch->getParameter('node');

    if ($this->beehotelunit->isThisNodeBeeHotel($data['node']) == FALSE) {
      return $this->t("A Bee Hotel Unit is required");
    }

    $data['node_type'] = $data['node']->type->entity;
    $bee_settings = $data['node_type']->getThirdPartySetting('bee', 'bee');

    $data = $this->beehotelunit->getAvailableUnits($data);

    if (empty($data['available_units'])) {
      $form_state->setError($form, $this->t('No Unit available now'));
    }

    if (!in_array($data['values']['bid'], $data['available_units'])) {
      $form_state->setError($form, $this->t('Sorry, this Unit not available for your request'));
    }

    // Payment validation.
    if ($bee_settings['payment'] != 1) {
      $form_state->setErrorByName('checkin', $this->t('No payment available for this Content type. Please check "Enable payment for bookings"'));
    }

    if ($data['node']->get("field_accept_reservations")->value != 1) {
      $form_state->setErrorByName('checkin', $this->t('Sorry, no Unit available now...'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $data = [];
    $data['values'] = $form_state->getValues();

    $data['node'] = $this->routeMatch->getParameter('node');

    if ($this->beehotelunit->isThisNodeBeeHotel($data['node']) == FALSE) {
      return $this->t("A Bee Hotel Unit is required");
    }

    $data['qid'] = substr($data['values']['form_build_id'], 5, 18);
    $this->session->set('beehotel_units_search_queries', [
      $data['qid'] => $data['values'],
    ]);

    $data = $this->beehotelunit->getAvailableUnits($data);

    $booking = bat_booking_create([
      'type' => 'bee',
      'label' => $data['node']->label(),
    ]);
    $booking->set('booking_start_date', $data['norm']['dates_from_search_form']['checkin']['Y-m-d-H-i-s']);
    $booking->set('booking_end_date', $data['norm']['dates_from_search_form']['lastnight']['Y-m-d-H-i-s']);
    $booking->set('booking_capacity', $data['values']['guests']);

    $booking->save();

    /*Load Product*/
    $product = Product::load((int) $data['values']['pid']);

    /*Load Product Variations*/
    $variations = $product->getVariationIds();

    $stores = $product->getStores();
    $store = reset($stores);

    // This works fine for single slot units.
    foreach ($variations as $v) {
      $product_variation = $this->entityTypeManager->getStorage('commerce_product_variation')->load((int) $v);
      // We set max accupancy per varaition in the custom field_max_occupancy.
      // See  /admin/commerce/config/product-variation-types/bee/edit/fields/commerce_product_variation.bee.field_max_occupancy.
      $variation_max_occupancy = $product_variation->get("field_max_occupancy")->value;

      $usable_variation = [];

      if ($variation_max_occupancy >= (int) $data['values']['guests']) {
        $usable_variation['price'] = $product_variation->getPrice();
        $usable_variation['title'] = $product_variation->get('title')->get(0)->value;
        $usable_variation['variant_id'] = $product_variation->get('variation_id')->get(0)->value;
        break;
      }
    }

    // @todo check "default" cart exists.
    $cart = $this->cartProvider->getCart('default', $store);

    if (!$cart) {
      $cart = $this->cartProvider->createCart('default', $store);
    }
    else {
      // @todo allow more units per order.
      $this->cartManager->emptyCart($cart);
    }

    $order_item = $this->entityTypeManager->getStorage('commerce_order_item')->create([
      'title'            => $data['node']->label(),
      'type'             => 'bee',
      'purchased_entity' => $usable_variation['variant_id'],
      'quantity'         => $data['norm']['dates_from_search_form']['days'],
      'unit_price'       => $usable_variation['price'],
    ]);

    $order_item->set('field_booking', $booking);
    $order_item->set('field_node', $data['node']);
    $order_item->set('field_checkin', [
      $data['norm']['dates_from_search_form']['checkin']['Y-m-d-H-i-s'],
    ]);
    $order_item->set('field_checkout', [
      $data['norm']['dates_from_search_form']['checkout']['Y-m-d-H-i-s'],
    ]);

    $order_item->set('field_order_item_nights', [$data['norm']['dates_from_search_form']['days']]);
    $order_item->save();
    $this->cartManager->addOrderItem($cart, $order_item);
    $this->session->set('beehotel_data', ['from' => $this->getFormId()]);
    $form_state->setRedirect('commerce_cart.page', ['commerce_order' => $cart->id()]);
  }

  /**
   * Load entity form display configuration.
   *
   * @param string $entity_type
   *   Entity type.
   * @param string $bundle
   *   Bundle of the entity.
   * @param string $mode
   *   Form mode.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   EntityFormDisplay or null
   */
  public function getEntityFormConfiguration($entity_type, $bundle, $mode = 'default') {
    $config_keys = [$entity_type, $bundle, $mode];
    $config_id = implode('.', $config_keys);
    try {
      return $this->entityTypeManager->getStorage('entity_form_display')->load($config_id);
    }
    catch (PluginException $e) {
      return NULL;
    }
  }

}
