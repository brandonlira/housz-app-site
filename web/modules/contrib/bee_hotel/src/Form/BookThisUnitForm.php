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
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Defines the reservation form for a Bee Hotel unit.
 *
 * @package Drupal\bee_hotel
 */
class BookThisUnitForm extends FormBase {

  /**
   * The product field name constant.
   */
  const FIELD_PRODUCT = 'field_product';

  /**
   * The Bee Hotel unit utility.
   *
   * @var \Drupal\beehotel_utils\BeeHotelUnit
   */
  protected $beehotelunit;

  /**
   * The Bee Hotel date utility.
   *
   * @var \Drupal\beehotel_utils\Dates
   */
  protected $beehoteldates;

  /**
   * The session interface.
   *
   * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
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
   * Constructs the BookThisUnitForm object.
   */
  public function __construct(
    BeeHotelUnit $bee_hotel_unit,
    Dates $bee_hotel_dates,
    SessionInterface $session,
    EntityTypeManagerInterface $entity_type_manager,
    CartManagerInterface $cart_manager,
    CartProviderInterface $cart_provider,
    RouteMatchInterface $route_match,
    RequestStack $request_stack
  ) {
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
      $container->get('request_stack')
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
    $data['node'] = $this->routeMatch->getParameter('node');
    $data['bid'] = $this->beehotelunit->getBidFromNode($data['node']);

    if (!$this->beehotelunit->isThisNodeBeeHotel($data['node'])) {
      return ['#markup' => $this->t('A Bee Hotel Unit is required')];
    }

    if ($data['beehotel_settings']->get('beehotel.off_value')) {
      $this->messenger()->addWarning($data['beehotel_settings']->get('beehotel.off_text'));
      return [];
    }

    if (!$data['node']->hasField(self::FIELD_PRODUCT)) {
      return [];
    }

    $data['default_values']['dates'] = date("j M Y", strtotime("+1 day")) . " - " . date("j M Y", strtotime("+3 day"));
    $data['request_values']['row'] = $this->requestStack->getCurrentRequest()->query->get("v");

    if (!empty($data['request_values']['row'])) {
      $data['request_values']['pieces'] = explode("-", $data['request_values']['row']);
      $this->beehoteldates->fromRequestToLitepicker($data);
    }

    $form['node'] = [
      '#type' => 'hidden',
      '#value' => $data['node']->id(),
    ];

    $header_setting = (string) $data['beehotel_settings']->get('beehotel.book_this_unit_header');
    $title_value = $this->t('Book now');

    if (trim($header_setting) === '<ct-label>') {
      $title_value = $this->t('Book this @t', ['@t' => $data['node']->type->entity->label()]);
    }
    elseif (trim($header_setting) === '<title>') {
      $title_value = $this->t('Book @t', ['@t' => $data['node']->getTitle()]);
    }

    $form['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $title_value,
      '#weight' => 1,
    ];

    $form['dates'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Check in > Check out'),
      '#default_value' => $data['default_values']['dates'],
      '#attributes' => [
        'class' => ['book-this-unit', 'edit-dates', 'bee_hotel_search_availability'],
      ],
      '#required' => TRUE,
      '#weight' => 2,
    ];

    $product_field = $data['node']->get(self::FIELD_PRODUCT)->getValue();
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
      '#weight' => 3,
    ];

    if (isset($data['request_values']['pieces'][6])) {
      $form['guests']['#default_value'] = (int) $data['request_values']['pieces'][6];
    }

    $form['pid'] = ['#type' => 'hidden', '#value' => $data['pid']];
    $form['bid'] = ['#type' => 'hidden', '#value' => $data['bid']];

    // Integration with CheckinTime plugin.
    $form['checkin_time'] = $this->buildCheckinTimeField($data);

    $submit_label = $data['beehotel_settings']->get('beehotel.book_this_unit_submit');
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $submit_label,
      '#name' => $submit_label,
    ];

    $form['#attached']['library'][] = 'bee_hotel/book-this-unit';
    $form['#attached']['library'][] = 'bee_hotel/beehotel-litepicker';
    $form['#weight'] = -2000;

    if ($data['beehotel_settings']->get('unit_reservation_form_disabled')) {
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

    if (!$this->beehotelunit->isThisNodeBeeHotel($data['node'])) {
      $form_state->setError($form, $this->t('A Bee Hotel Unit is required'));
      return;
    }

    $data['node_type'] = $data['node']->type->entity;
    $bee_settings = $data['node_type']->getThirdPartySetting('bee', 'bee');
    $data = $this->beehotelunit->getAvailableUnits($data);

    if (empty($data['available_units'])) {
      $form_state->setError($form, $this->t('No Unit available now'));
    }

    if (!in_array($data['values']['bid'], $data['available_units'])) {
      $form_state->setError($form, $this->t('Sorry, this Unit is not available for your request'));
    }

    if ($bee_settings['payment'] != 1) {
      $form_state->setErrorByName('checkin', $this->t('Payment not enabled for this content type.'));
    }

    if ($data['node']->get('field_accept_reservations')->value != 1) {
      $form_state->setErrorByName('checkin', $this->t('Sorry, this unit is not accepting reservations.'));
    }

    // Save check-in time selection to session.
    $checkinTimeKey = $form_state->getValue('checkin_time');
    if ($checkinTimeKey) {
      $this->session->set('beehotel_checkin_time_key', $checkinTimeKey);
      $timeParts = explode('-', $checkinTimeKey);
      $this->session->set('beehotel_checkin_time', $timeParts[0]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $data = [];
    $data['values'] = $form_state->getValues();
    $data['node'] = $this->routeMatch->getParameter('node');

    $data['qid'] = substr($data['values']['form_build_id'], 5, 18);
    $this->session->set('beehotel_units_search_queries', [
      $data['qid'] => $data['values'],
    ]);

    $data = $this->beehotelunit->getAvailableUnits($data);

    $booking = $this->entityTypeManager->getStorage('bat_booking')->create([
      'type' => 'bee',
      'label' => $data['node']->label(),
    ]);
    $booking->set('booking_start_date', $data['norm']['dates_from_search_form']['checkin']['Y-m-d-H-i-s']);
    $booking->set('booking_end_date', $data['norm']['dates_from_search_form']['lastnight']['Y-m-d-H-i-s']);
    $booking->set('booking_capacity', $data['values']['guests']);
    $booking->save();

    $product = Product::load((int) $data['values']['pid']);
    $variations = $product->getVariationIds();
    $stores = $product->getStores();
    $store = reset($stores);

    $usable_variation = [];
    foreach ($variations as $v) {
      $variation = $this->entityTypeManager->getStorage('commerce_product_variation')->load((int) $v);
      if ($variation->get('field_max_occupancy')->value >= (int) $data['values']['guests']) {
        $usable_variation['price'] = $variation->getPrice();
        $usable_variation['variant_id'] = $variation->id();
        break;
      }
    }

    $cart = $this->cartProvider->getCart('default', $store) ?: $this->cartProvider->createCart('default', $store);
    $this->cartManager->emptyCart($cart);

    $order_item = $this->entityTypeManager->getStorage('commerce_order_item')->create([
      'title' => $data['node']->label(),
      'type' => 'bee',
      'purchased_entity' => $usable_variation['variant_id'],
      'quantity' => $data['norm']['dates_from_search_form']['days'],
      'unit_price' => $usable_variation['price'],
    ]);

    $order_item->set('field_booking', $booking);
    $order_item->set('field_node', $data['node']);
    $order_item->set('field_checkin', $data['norm']['dates_from_search_form']['checkin']['Y-m-d-H-i-s']);
    $order_item->set('field_checkout', $data['norm']['dates_from_search_form']['checkout']['Y-m-d-H-i-s']);
    $order_item->set('field_order_item_nights', $data['norm']['dates_from_search_form']['days']);
    $order_item->save();

    $this->cartManager->addOrderItem($cart, $order_item);
    $this->session->set('beehotel_data', ['from' => $this->getFormId()]);
    $form_state->setRedirect('commerce_cart.page', ['commerce_order' => $cart->id()]);
  }

  /**
   * Build check-in time field with times and price variations.
   */
  private function buildCheckinTimeField(array $data) {
    $config = $this->config('beehotel_pricealterator.pricealterator.CheckinTime.settings');

    if (!$config->get('enabled')) {
      return [];
    }

    $timeSlots = $config->get('time_slots') ?: [];
    if (empty($timeSlots)) {
      return [];
    }

    $currency_service = \Drupal::service('beehotel_utils.beehotelcommerce');
    $currency_symbol = $currency_service->currentStoreCurrency()->get('symbol');

    $form_title = $config->get('display_label') ?: $this->t('Check-in Time');
    $pattern = $config->get('options_pattern') ?: '[label] ([start] - [end]) [[adjustment]]';

    $options = [];
    $defaultOption = '';

    foreach ($timeSlots as $index => $slot) {
      $key = $slot['start'] . '-' . $slot['end'];
      $adjustment = (float) ($slot['adjustment'] ?? 0);
      $adj_text = '';

      if ($adjustment > 0) {
        $adj_text = "+" . number_format($adjustment, 2) . " " . $currency_symbol;
      }
      elseif ($adjustment < 0) {
        $adj_text = number_format($adjustment, 2) . " " . $currency_symbol;
      }
      elseif ($adjustment == 0) {
        $adj_text = '0';
      }

      $replacements = [
        '[label]' => !empty($slot['label']) ? $this->t($slot['label']) : '',
        '[start]' => $slot['start'],
        '[end]' => $slot['end'],
        '[adjustment]' => $adj_text,
      ];

      $fullLabel = str_replace(array_keys($replacements), array_values($replacements), $pattern);
      // Cleanup empty brackets.
      $fullLabel = str_replace([' ()', ' []', ' []'], '', $fullLabel);
      $options[$key] = trim($fullLabel);

      if ($index === 0 && empty($defaultOption)) {
        $defaultOption = $key;
      }
    }

    $defaultValue = $this->session->get('beehotel_checkin_time_key', $defaultOption);

    return [
      '#type' => 'select',
      '#title' => $form_title,
      '#options' => $options,
      '#default_value' => $defaultValue,
      '#description' => $this->t('Select your preferred check-in time.'),
      '#weight' => 10,
    ];
  }

}
