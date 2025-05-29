<?php

namespace Drupal\bee_hotel\Controller;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\bee_hotel\BeeHotel;
use Drupal\beehotel_utils\BeeHotelUnit;
use Drupal\commerce\Context;
use Drupal\commerce_order\PriceCalculatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * Provides route responses for BeeHotel module.
 */
class SearchResult extends ControllerBase {

  use StringTranslationTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The Bee Hotel utility.
   *
   * @var \Drupal\bee_hotel\BeeHotel
   */
  protected $beehotel;

  /**
   * The Bee Hotel Unit utility.
   *
   * @var \Drupal\beehotel_utils\BeeHotelUnit
   */
  protected $beeHotelUnit;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The session.
   *
   * @var \Symfony\Component\HttpFoundation\Session\Session
   */
  protected $session;

  /**
   * The price calculator.
   *
   * @var \Drupal\commerce_order\PriceCalculatorInterface
   */
  protected $priceCalculator;

  /**
   * The currency formatter.
   *
   * @var \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface
   */
  protected $currencyFormatter;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new SearchResult object.
   *
   * @param \Drupal\bee_hotel\BeeHotel $bee_hotel
   *   The BeeHotel Utility.
   * @param \Drupal\beehotel_utils\BeeHotelUnit $bee_hotel_unit
   *   The BeeHotelUnit utility.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Symfony\Component\HttpFoundation\Session\Session $session
   *   The session.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   User account.
   * @param \Drupal\commerce_order\PriceCalculatorInterface $price_calculator
   *   The price calculator.
   * @param \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface $currency_formatter
   *   The currency formatter.
   * @param Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The langauge manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(
    BeeHotel $bee_hotel,
    BeeHotelUnit $bee_hotel_unit,
    EntityTypeManagerInterface $entity_type_manager,
    RendererInterface $renderer,
    ConfigFactoryInterface $config_factory,
    Session $session,
    AccountInterface $account,
    PriceCalculatorInterface $price_calculator,
    CurrencyFormatterInterface $currency_formatter,
    LanguageManagerInterface $language_manager,
    RequestStack $request_stack
  ) {
    $this->beehotel = $bee_hotel;
    $this->beeHotelUnit = $bee_hotel_unit;
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
    $this->configFactory = $config_factory;
    $this->session = $session;
    $this->account = $account;
    $this->priceCalculator = $price_calculator;
    $this->currencyFormatter = $currency_formatter;
    $this->languageManager = $language_manager;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('bee_hotel.beehotel'),
      $container->get('beehotel_utils.beehotelunit'),
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('config.factory'),
      $container->get('session'),
      $container->get('current_user'),
      $container->get('commerce_order.price_calculator'),
      $container->get('commerce_price.currency_formatter'),
      $container->get('language_manager'),
      $container->get('request_stack'),
    );
  }

  /**
   * Produces search result.
   */
  public function result() {

    $data = [];
    $data['qid'] = $this->requestStack->getCurrentRequest()->query->get("qid");
    if (isset($data['qid'])) {
      $data['beehotel_units_search_queries'] = $this->session->get('beehotel_units_search_queries');
      $data['query'] = $data['beehotel_units_search_queries'][$data['qid']] ?? NULL;

      if (isset($data['query'])) {
        $data['result'] = $this->produceResult($data['query']);
        $output = $this->renderer->render($data['result']);
        $build = ['#markup' => $output];
      }
      else {
        $build = $this->newSearchLink(["description" => $this->t("No result here. Select new dates")]);
      }
    }
    else {
      $data = [
        "description" => $this->t("No result here. Select new dates"),
      ];
      $build = $this->newSearchLink($data);
    }
    return $build;
  }

  /**
   * Produce results.
   *
   * Produce results from submitted values.
   * Cloned from UnitsSearch.
   */
  private function produceResult($values) {

    $data = [];
    $data['values'] = $values;

    $data = $this->beeHotelUnit->getAvailableUnits($data);

    // Get current language code.
    $data['current_language'] = $this->languageManager->getCurrentLanguage()->getId();
    $data['new_search_link'] = "<div class='new-search'><a href='/us'>" . $this->t("New search") . "</a></div>";
    $items = [];
    $items['#markup'] = $this->t("No availability for these days...");
    $items['#markup'] .= $data['new_search_link'];

    foreach ($data['available_units'] as $unit_id) {

      $beeHotelUnit = $this->beeHotelUnit->getUnitFromBid($unit_id);

      if (isset($beeHotelUnit)) {

        // Skip disabled units.
        if ($beeHotelUnit['node']->get("status")->value != 1) {
          continue;
        }

        // Skip Units not accepting reservations.
        if ($beeHotelUnit['node']->get("field_accept_reservations")->value != 1) {
          continue;
        }

        // Skip units without language.
        if (!$beeHotelUnit['node']->hasTranslation($data['current_language'])) {
          continue;
        }

        // We only want the translated node.
        $beeHotelUnit['translated_node'] = $beeHotelUnit['node']->getTranslation($data['current_language']);
        $beeHotelUnit['node'] = $beeHotelUnit['translated_node'];

        $variation = $this->beeHotelUnit->GetCurrentVariation($beeHotelUnit, $data);

        if (isset($variation)) {

          $data['v_param'] = $data['norm']['dates_from_search_form']['checkin']['Y-m-d'] .
            "-" .
            $data['norm']['dates_from_search_form']['checkout']['Y-m-d'] .
            "-" .
            $data['values']['guests'];

          $data['destination']['title'] = Link::createFromRoute($beeHotelUnit['node']->getTitle(), 'entity.node.canonical', [
            'node' => $beeHotelUnit['node']->Id(),
            'v' => $data['v_param'],
          ]);

          $data['destination']['text'] = Link::createFromRoute($this->t('Book now'), 'entity.node.canonical', [
            'node' => $beeHotelUnit['node']->Id(),
            'v' => $data['v_param'],
          ]);

          $data['destination']['img'] = Link::createFromRoute($beeHotelUnit['cover_image']['markup'], 'entity.node.canonical', [
            'node' => $beeHotelUnit['node']->Id(),
            'v' => $data['v_param'],
          ]);

          $context = new Context($this->account,
            $this->entityTypeManager->getStorage('commerce_store')->load($beeHotelUnit['store']->Id())
          );

          $purchasable_entity = $variation;
          $data['addtocart']['caller'] = __CLASS__ . "::" . __FUNCTION__;
          $this->beeHotelUnit->storeInSession($data);

          $price = $this->priceCalculator->calculate($variation, 1, $context);
          $calculatedprice = $price->getCalculatedPrice();

          $formattedprice = $this->currencyFormatter->format($calculatedprice->getNumber(), $calculatedprice->getCurrencyCode(), [
            'locale' => $beeHotelUnit['store']->get("langcode")->value,
            'minimum_fraction_digits' => 2,
            'maximum_fraction_digits' => 2,
            'currency_display' => 'none',
          ]);

          $items[] = [
            '#theme' => 'bee_hotel_s_unit',
            '#currency' => $calculatedprice->getCurrencyCode(),
            '#img' => $data['destination']['img'],
            '#nights' => $data['values']['dates'],
            '#price' => $formattedprice,
            '#title' => $data['destination']['title'],
            '#store' => $beeHotelUnit['store']->get('name')->value,
            '#product_id' => $beeHotelUnit['pid'],
            '#variation_id' => (int) $purchasable_entity->id(),
            '#destination' => $data['destination']['text'],
          ];

          $items['#markup'] = NULL;

          // Optional field for marketing pourposes.
          if ($beeHotelUnit['node']->hasField('field_slogan')) {
            $items['#description'] = $beeHotelUnit['node']->get("field_slogan")->value;
          }
          $items['#prefix'] = $items['#suffix'] = $data['new_search_link'];
        }
      }
    }
    return $items;
  }

  /**
   * New search link.
   *
   * Produce a new search link.
   */
  private function newSearchLink(array $data) {
    $data['url'] = Url::fromRoute('beehotel.unit_search');
    $data['link'] = Link::fromTextAndUrl($this->t('New search'), $data['url']);

    $link = [
      '#theme' => 'bee_hotel_new_search_link',
      '#description' => $data['description'],
      '#link' => $data['link'] ,
    ];

    return $link;
  }

}
