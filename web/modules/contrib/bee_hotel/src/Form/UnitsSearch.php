<?php

namespace Drupal\bee_hotel\Form;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\beehotel_utils\BeeHotelUnit;
use Drupal\commerce_price\Resolver\ChainPriceResolverInterface;
use Drupal\commerce_order\PriceCalculatorInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Search form for available beehotel units.
 */
class UnitsSearch extends FormBase {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The chain price resolver.
   *
   * @var \Drupal\commerce_price\Resolver\ChainPriceResolverInterface
   */
  protected $chainPriceResolver;

  /**
   * The currency formatter.
   *
   * @var \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface
   */
  protected $currencyFormatter;

  /**
   * The Bee Hotel Unit utility.
   *
   * @var \Drupal\beehotel_utils\BeeHotelUnit
   */
  protected $beeHotelUnit;

  /**
   * The price calculator.
   *
   * @var \Drupal\commerce_order\PriceCalculatorInterface
   */
  protected $priceCalculator;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The session.
   *
   * @var \Symfony\Component\HttpFoundation\Session\Session
   */
  protected $session;

  /**
   * Constructs a new UnitsSeach object.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   User account.
   * @param \Drupal\commerce_price\Resolver\ChainPriceResolverInterface $chain_price_resolver
   *   Chain price resolver.
   * @param \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface $currency_formatter
   *   The currency formatter.
   * @param \Drupal\beehotel_utils\BeeHotelUnit $bee_hotel_unit
   *   The BeeHotelUnit utility.
   * @param \Drupal\commerce_order\PriceCalculatorInterface $price_calculator
   *   The price calculator.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\HttpFoundation\Session\Session $session
   *   The session.
   */
  public function __construct(AccountInterface $account, ChainPriceResolverInterface $chain_price_resolver, CurrencyFormatterInterface $currency_formatter, BeeHotelUnit $bee_hotel_unit, PriceCalculatorInterface $price_calculator, EntityTypeManagerInterface $entity_type_manager, Session $session) {
    $this->account = $account;
    $this->chainPriceResolver = $chain_price_resolver;
    $this->currencyFormatter = $currency_formatter;
    $this->beeHotelUnit = $bee_hotel_unit;
    $this->priceCalculator = $price_calculator;
    $this->entityTypeManager = $entity_type_manager;
    $this->session = $session;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('commerce_price.chain_price_resolver'),
      $container->get('commerce_price.currency_formatter'),
      $container->get('beehotel_utils.beehotelunit'),
      $container->get('commerce_order.price_calculator'),
      $container->get('entity_type.manager'),
      $container->get('session'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'beehotel_units_search';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {

    $config = $this->config('beehotel.settings');

    if ($config->get("beehotel.off_value")) {
      $this->messenger()->addWarning($config->get("beehotel.off_text"));
      return;
    }

    // Clean booking related data in session.
    $today = new \DateTime();

    $tomorrow = clone($today);
    $tomorrow->modify('+1 day');

    $one_hour_later = clone($today);
    $one_hour_later->modify('+1 hour');

    $form['#prefix'] = "<div id='units-search-form-container'>";

    $form['title'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $config->get("beehotel")['units_search_header'] ?? $this->t('Book now'),
      '#weight' => -2000,
    ];

    if (isset($this->session->get("beehotel_data")['values']['dates'])) {
      $default_dates = $this->session->get("beehotel_data")['values']['dates'];
      $default_guests = $this->session->get("beehotel_data")['values']['guests'];
    }
    else {
      $default_dates = date("j M Y", strtotime("+1 day")) . " - " . date("j M Y", strtotime("+3 day"));
      $default_guests = 2;
    }

    // Dates.
    $form['dates'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Check in > Check out'),
      '#default_value' => $default_dates,
      '#required' => TRUE,
      '#weight' => -1000,
      '#attributes' => [
        'class' => [
          'unit.search',
          'edit-dates',
          'bee_hotel_search_availability',
        ],
      ],
    ];

    // People (occupants).
    $max_occupancy = $this->beeHotelUnit->maxOccupancy($node = NULL);
    $options = [];
    for ($o = 1; $o <= $max_occupancy; $o++) {
      $options[$o] = $o . " " . $this->t("guests");
    }

    $form['guests'] = [
      '#type' => 'select',
      '#title' => $this->t('Guests'),
      '#options' => $options,
      '#default_value' => $default_guests,
      '#required' => TRUE,
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $config->get("beehotel")['units_search_submit'] ?? $this->t('Book now'),
      '#button_type' => 'primary',
    ];

    $form['#suffix'] = "</div>";
    $form['#attached']['library'][] = 'bee_hotel/beehotel-litepicker';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    $data = [];
    $data['values'] = $form_state->getValues();
    $data['qid'] = substr($data['values']['form_build_id'], 5, 18);
    $data = $this->beeHotelUnit->getAvailableUnits($data);
    if (empty($data['available_units'])) {
      $form_state->setErrorByName('checkout', $this->t('No availability, sorry...'));
      return;
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $qid = substr($values['form_build_id'], 5, 18);
    $this->session->set('beehotel_units_search_queries', [
      $qid => $values,
    ]);
    $form_state->setRedirect('beehotel.search_result', ['qid' => $qid]);
  }

}
