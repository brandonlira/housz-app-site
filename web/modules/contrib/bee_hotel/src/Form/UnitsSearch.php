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
use Symfony\Component\HttpFoundation\RedirectResponse;
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
   * Raccolta di tutti i debug points
   *
   * @var array
   */
  protected $debugPoints = [];

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
   * Helper method to add debug point.
   *
   * @param string $point
   *   Nome del punto di debug.
   * @param mixed $data
   *   Dati da tracciare.
   * @param bool $dump
   *   Se fare il dump immediato.
   */
  private function addDebugPoint($point, $data, $dump = TRUE) {
    $this->debugPoints[$point] = [
      'timestamp' => microtime(TRUE),
      'memory' => memory_get_usage(),
      'data' => $data,
    ];

    if ($dump) {
      // Crea un array con una chiave unica per evitare sovrascritture
      $dump_data = [
        'debug_point' => $point,
        'timestamp' => date('H:i:s') . '.' . substr(microtime(), 2, 4),
        'data' => $data,
      ];

      // Usa una chiave unica per il dump
      // dump([$point . '_' . uniqid() => $dump_data]);
    }
  }

  /**
   * Helper method to build debug data array.
   *
   * @param \Drupal\node\NodeInterface|null $node
   *   The node entity.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $additional_data
   *   Additional data to include.
   *
   * @return array
   *   Debug data array.
   */
  private function buildDebugData(NodeInterface $node = NULL, FormStateInterface $form_state = NULL, array $additional_data = []) {
    $debug_data = [
      'timestamp' => time(),
      'datetime' => date('Y-m-d H:i:s'),
      'form_id' => $this->getFormId(),
      'current_user' => [
        'uid' => $this->account->id(),
        'roles' => $this->account->getRoles(),
        'is_authenticated' => $this->account->isAuthenticated(),
      ],
      'node' => $node ? [
        'nid' => $node->id(),
        'title' => $node->getTitle(),
        'type' => $node->bundle(),
      ] : null,
      'session' => [
        'beehotel_data' => $this->session->get("beehotel_data"),
        'beehotel_units_search_queries' => $this->session->get("beehotel_units_search_queries"),
      ],
      'config' => [
        'beehotel_settings' => $this->config('beehotel.settings')->getRawData(),
      ],
      'drupal_version' => \Drupal::VERSION,
      'modules' => [
        'beehotel_enabled' => \Drupal::moduleHandler()->moduleExists('bee_hotel'),
        'beehotel_utils_enabled' => \Drupal::moduleHandler()->moduleExists('beehotel_utils'),
        'commerce_enabled' => \Drupal::moduleHandler()->moduleExists('commerce'),
      ],
    ];

    if ($form_state) {
      $debug_data['form_state'] = [
        'values' => $form_state->getValues(),
        'triggering_element' => $form_state->getTriggeringElement() ? $form_state->getTriggeringElement()['#name'] ?? null : null,
        'errors' => $form_state->getErrors(),
        'storage' => $form_state->getStorage(),
        'temp' => $form_state->getTemporaryValue('beehotel_debug'),
      ];
    }

    // Add BeeHotelUnit utility debug info
    try {
      $debug_data['beehotel_unit_utility'] = [
        'max_occupancy' => $this->beeHotelUnit->maxOccupancy($node),
        'method_exists' => [
          'getAvailableUnits' => method_exists($this->beeHotelUnit, 'getAvailableUnits'),
          'maxOccupancy' => method_exists($this->beeHotelUnit, 'maxOccupancy'),
        ],
      ];
    } catch (\Exception $e) {
      $debug_data['beehotel_unit_utility']['error'] = $e->getMessage();
    }

    // Merge additional data
    $debug_data = array_merge($debug_data, $additional_data);

    return $debug_data;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {

    // Inizia la raccolta debug points
    $this->addDebugPoint('buildForm_start', [
      'node_exists' => !is_null($node),
      'form_build_id' => $form_state->getBuildInfo()['form_id'] ?? null,
    ]);

    $config = $this->config('beehotel.settings');

    if ($config->get("beehotel.off_value")) {
      $this->messenger()->addWarning($config->get("beehotel.off_text"));

      $this->addDebugPoint('config_off', [
        'off_value' => $config->get("beehotel.off_value"),
        'off_text' => $config->get("beehotel.off_text"),
      ]);

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

    // Debug session data
    $session_data = $this->session->get("beehotel_data");
    $this->addDebugPoint('session_data', $session_data);

    if (isset($session_data['values']['dates'])) {
      $default_dates = $session_data['values']['dates'];
      $default_guests = $session_data['values']['guests'];

      $this->addDebugPoint('default_from_session', [
        'dates' => $default_dates,
        'guests' => $default_guests,
        'full_session' => $session_data,
      ]);
    }
    else {
      $default_dates = date("j M Y", strtotime("+1 day")) . " - " . date("j M Y", strtotime("+3 day"));
      $default_guests = 2;

      $this->addDebugPoint('default_fallback', [
        'dates' => $default_dates,
        'guests' => $default_guests,
      ]);
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
    try {
      $max_occupancy = $this->beeHotelUnit->maxOccupancy($node);

      $this->addDebugPoint('max_occupancy', [
        'value' => $max_occupancy,
        'node_present' => !is_null($node),
        'node_id' => $node ? $node->id() : null,
      ]);

    } catch (\Exception $e) {
      $max_occupancy = 10; // Default fallback

      $this->addDebugPoint('max_occupancy_error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'fallback_value' => $max_occupancy,
      ]);

      \Drupal::logger('bee_hotel')->error('Error calculating max occupancy: @error', ['@error' => $e->getMessage()]);
    }

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

    // Add hidden debug field for tracking
    $form['debug_info'] = [
      '#type' => 'hidden',
      '#value' => base64_encode(json_encode([
        'timestamp' => time(),
        'form_build' => true,
        'debug_points_count' => count($this->debugPoints),
      ])),
    ];

    // Final form debug
    $this->addDebugPoint('form_structure', [
      'has_dates' => isset($form['dates']),
      'has_guests' => isset($form['guests']),
      'has_submit' => isset($form['actions']['submit']),
      'dates_default' => $form['dates']['#default_value'] ?? null,
      'guests_default' => $form['guests']['#default_value'] ?? null,
      'guests_options_count' => count($options),
    ]);

    // Log tutti i debug points alla fine
    $this->addDebugPoint('all_debug_points', $this->debugPoints, FALSE);
    \Drupal::logger('bee_hotel')->debug('All debug points: @data', ['@data' => print_r($this->debugPoints, TRUE)]);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    $this->addDebugPoint('validateForm_start', [
      'form_build_id' => $form_state->getValue('form_build_id'),
    ]);

    $data = [];
    $data['values'] = $form_state->getValues();
    $data['qid'] = substr($data['values']['form_build_id'], 5, 18);

    $this->addDebugPoint('validateForm_data', [
      'qid' => $data['qid'],
      'dates' => $data['values']['dates'] ?? null,
      'guests' => $data['values']['guests'] ?? null,
    ]);

    $data = $this->beeHotelUnit->getAvailableUnits($data);

    $this->addDebugPoint('availability_results', [
      'available_units_count' => isset($data['available_units']) ? count($data['available_units']) : 0,
      'available_units' => array_keys($data['available_units'] ?? []),
      'has_prices' => isset($data['prices']),
    ]);

    if (empty($data['available_units'])) {
      $form_state->setErrorByName('checkout', $this->t('No availability, sorry...'));

      $this->addDebugPoint('no_availability', [
        'search_criteria' => $data['values'] ?? null,
        'qid' => $data['qid'] ?? null,
      ]);

      $response = new RedirectResponse('/us');
      $response->send();
      return;
    }

    // Store debug info in form state for later use
    $form_state->setTemporaryValue('beehotel_debug', [
      'validation_success' => true,
      'available_units_count' => count($data['available_units']),
      'qid' => $data['qid'],
      'timestamp' => time(),
    ]);

    $this->addDebugPoint('validateForm_success', [
      'available_count' => count($data['available_units']),
      'qid' => $data['qid'],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->addDebugPoint('submitForm_start', [
      'form_build_id' => $form_state->getValue('form_build_id'),
    ]);

    $values = $form_state->getValues();
    $qid = substr($values['form_build_id'], 5, 18);

    $this->addDebugPoint('submitForm_data', [
      'qid' => $qid,
      'dates' => $values['dates'] ?? null,
      'guests' => $values['guests'] ?? null,
    ]);

    $this->session->set('beehotel_units_search_queries', [
      $qid => $values,
    ]);

    // Verify session data was set
    $session_check = $this->session->get('beehotel_units_search_queries');
    $this->addDebugPoint('session_after_set', [
      'has_data' => !is_null($session_check),
      'qid_present' => isset($session_check[$qid]),
    ]);

    $this->addDebugPoint('submitForm_end', [
      'redirect' => 'beehotel.search_result',
      'qid' => $qid,
    ]);

    // Log finale di tutti i punti di debug
    \Drupal::logger('bee_hotel')->debug('Complete debug trail: @data', [
      '@data' => print_r($this->debugPoints, TRUE)
    ]);

    $form_state->setRedirect('beehotel.search_result', ['qid' => $qid]);
  }

  /**
   * Ottiene tutti i punti di debug raccolti
   */
  public function getDebugPoints() {
    return $this->debugPoints;
  }

}
