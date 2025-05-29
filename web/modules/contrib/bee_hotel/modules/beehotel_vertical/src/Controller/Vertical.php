<?php

namespace Drupal\beehotel_vertical\Controller;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\bee_hotel\Event;
use Drupal\beehotel_utils\BeeHotelUnit;
use Drupal\beehotel_vertical\BeehotelVertical;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides route responses for BeeHotel module.
 */
#[AllowDynamicProperties]
class Vertical extends ControllerBase {

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
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The currency formatter.
   *
   * @var \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface
   */
  protected $currencyFormatter;

  /**
   * Representation of the current HTTP request.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  public $requestStack;

  /**
   * The config object.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The bee hotel event.
   *
   * @var \Drupal\bee_hotel\Event
   */
  protected $event;

  /**
   * The bee hotel unit.
   *
   * @var \Drupal\beehotel_utils\BeeHotelUnit
   */
  protected $beehotelUnit;

  /**
   * The BeeHotel vertical time range.
   *
   * @var \Drupal\beehotel_vertical\BeehotelVertical
   */
  protected $beehotelVertical;

  /**
   * The manager to be used for instantiating plugins.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $beehotelVerticalManager;

  /**
   * The plugin manager Interaface.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected $pluginManagerInterface;

  /**
   * Constructs a new Vertical object.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Form\FormBuilder $form_builder
   *   The form builder.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface $currency_formatter
   *   The currency formatter.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param Drupal\bee_hotel\Event $beehotel_event
   *   The Bee Hotel Event util.
   * @param \Drupal\beehotel_utils\BeeHotelUnit $bee_hotel_unit
   *   The BeeHotel Unit Utility.
   * @param \Drupal\beehotel_vertical\BeehotelVertical $beehotel_vertical
   *   The BeeHotel Vertical Class for features.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $beehotelVerticalManager
   *   The BeeHotel Vertical manager.
   */
  public function __construct(RendererInterface $renderer, FormBuilder $form_builder, DateFormatterInterface $date_formatter, EntityTypeManagerInterface $entity_type_manager, CurrencyFormatterInterface $currency_formatter, RequestStack $request_stack, ConfigFactoryInterface $config_factory, Event $beehotel_event, BeeHotelUnit $bee_hotel_unit, BeehotelVertical $beehotel_vertical, PluginManagerInterface $beehotelVerticalManager) {
    $this->renderer = $renderer;
    $this->formBuilder = $form_builder;
    $this->dateFormatter = $date_formatter;
    $this->entityTypeManager = $entity_type_manager;
    $this->currencyFormatter = $currency_formatter;
    $this->requestStack = $request_stack;
    $this->config = $config_factory;
    $this->event = $beehotel_event;
    $this->beehotelUnit = $bee_hotel_unit;
    $this->beehotelVertical = $beehotel_vertical;
    $this->pluginManagerInterface = $beehotelVerticalManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer'),
      $container->get('form_builder'),
      $container->get('date.formatter'),
      $container->get('entity_type.manager'),
      $container->get('commerce_price.currency_formatter'),
      $container->get('request_stack'),
      $container->get('config.factory'),
      $container->get('bee_hotel.event'),
      $container->get('beehotel_utils.beehotelunit'),
      $container->get('beehotel_vertical.beehotelvertical'),
      $container->get('plugin.manager.beehotel.pricealterator')
    );
  }

  /**
   * Produce the table.
   */
  public function result($data) {
    $data['rows'] = $this->rows($data);
    $data['header'] = $this->header($data);

    $output['table'] = [
      '#type' => 'table',
      '#attributes' => [
        'class' => ['vertical-table'],
      ],
      '#prefix' => $this->displayTimeRangeForm(),
      '#header' => $data['header'],
      '#rows' => $data['rows'],
      '#empty' => $this->t('Your table is empty'),
    ];
    return $this->renderer->render($output);
  }

  /**
   * Produce the table header.
   */
  private function header($data) {
    $data['units'] = $this->beehotelUnit->getBeeHotelUnits($options = []);
    $header = [];
    $url = Url::fromRoute('beehotel_vertical.admin_settings', []);
    $link = Link::fromTextAndUrl($this->t('Day'), $url);
    $link = $link->toRenderable();
    $link['#attributes'] = ['class' => ['button', 'gear']];

    $header[] = Markup::create("<h3 class='settings'>" . $this->renderer->render($link) . "</strong>");

    if (!empty($data['units'])) {
      foreach ($data['units'] as $unit) {
        $url = Url::fromRoute('entity.node.edit_form', ['node' => $unit->Id()]);
        $unit_link = Link::fromTextAndUrl($unit->GetTitle(), $url);
        $unit_link = $unit_link->toRenderable();
        $unit_link['#attributes'] = ['class' => ['button', 'link']];
        $header[] = Markup::create("<h3 class='unit'>" . $this->renderer->render($unit_link) . "</h3>");
      }
    }
    $data['header'] = $header;
    return $data['header'];
  }

  /**
   * Add result rows to data.
   *
   * @param array $data
   *   An array with report related data.
   */
  private function rows(array $data) {

    $data['rows'] = $tmp = [];
    $data['system_email'] = $this->config->get('system.site')->get('mail');

    $config = $this->config->get('beehotel_vertical.settings');
    $timejump = $config->get('vertical.timejump');
    $data['rowsnumber'] = $this->rowsNumber();

    $data['units'] = $this->beehotelUnit->getBeeHotelUnits($options = []);

    // Produce a row for every requested day.
    for ($i = 0; $i <= $data['rowsnumber']; $i++) {
      $data['day']['timestamp'] = (time() - $timejump) + ($i * (60 * 60 * 24));

      // @todo move this into a twig template
      $data['day']['formatted'] =
        "<span class='day-of-the-week'>" .
          $this->dateFormatter->format($data['day']['timestamp'], 'custom', 'D') . "
          </span>" .
        "<span class='day-month'>" .
         $this->dateFormatter->format($data['day']['timestamp'], 'custom', 'd M') . ",
        </span>" .
        "<span class='year'>" .
         $this->dateFormatter->format($data['day']['timestamp'], 'custom', 'Y') . "
        </span>";

      $data['day']['formatted'] =
        "<span class='day-of-the-week'>" .
          $this->dateFormatter->format($data['day']['timestamp'], 'custom', 'D') . "</span>" .
        "<span class='day-number'>" .
         $this->dateFormatter->format($data['day']['timestamp'], 'custom', 'd') . "</span>" .
        "<span class='month'>" .
         $this->dateFormatter->format($data['day']['timestamp'], 'custom', 'M') . "</span>";

      $data['day']['d'] = $this->dateFormatter->format($data['day']['timestamp'], 'custom', 'd');
      $data['day']['day'] = $this->dateFormatter->format($data['day']['timestamp'], 'custom', 'j');
      $data['day']['month'] = $this->dateFormatter->format($data['day']['timestamp'], 'custom', 'n');
      $data['day']['m'] = $this->dateFormatter->format($data['day']['timestamp'], 'custom', 'm');
      $data['day']['year'] = $this->dateFormatter->format($data['day']['timestamp'], 'custom', 'Y');

      // ISO 8601.
      $data['day']['today']['ISO8601'] = $data['day']['year'] . "-" . $data['day']['m'] . "-" . $data['day']['d'];
      $data['day']['day_of_elaboration']['ISO8601'] = $this->dateFormatter->format(time(), 'custom', 'Y-m-d');
      $data['day']['daybefore']['ISO8601'] = date('Y-m-d', strtotime("-1 day", strtotime($data['day']['today']['ISO8601'])));

      $elab_day = "";
      if ($data['day']['today']['ISO8601'] == $data['day']['day_of_elaboration']['ISO8601']) {
        $elab_day = "<span class='elab-day'>*</span>";
      }

      // Produce columns.
      $columns = [];

      // More this into day column.
      $this->getSeason($data);

      if (!isset($tmp['last_season']) || $tmp['last_season'] != $data['season']) {
        $tmp['season_label'] = $this->t("%label season", ['%label' => $data['season']]);
      }
      else {
        $tmp['season_label'] = "";
      }

      $col1 = [
        '#theme' => 'vertical_table_tr_td_col1',
        '#season' => $tmp['season_label'],
        '#season_class' => strtolower($data['season']),
        '#day' => ['#markup' => $data['day']['formatted']],
        '#elab_day' => ['#markup' => $elab_day],
      ];
      $columns[] = $this->renderer->render($col1);

      // Produce a column per unit.
      if (!empty($data['units'])) {
        foreach ($data['units'] as $unit_id => $unit) {
          $data['unit']['node'] = $unit;
          $data['unit']['nid'] = $unit_id;
          $data['unit']['bid'] = $unit->get("field_availability_daily")->target_id;
          $data = $this->cellContent($data);
          $columns[] = $this->renderer->render($data['cellcontent']);
        }
        $data['rows'][] = $columns;
      }

      $tmp['last_season'] = $data['season'];

    }

    return $data['rows'];
  }

  /**
   * Get time range for current report.
   */
  public function requestedRange() {
    $data = [];
    $data['session'] = $this->getSession();
    $data['tmp']['a'] = $data['session']->get('beehotel_requested_range');
    $data['tmp']['b'] = $this->defaultTimeRange();
    $data['range'] = $data['session']->get('beehotel_requested_range') ?? $this->defaultTimeRange();
    return $data['range'];
  }

  /**
   * Get rows number for current report.
   *
   * @return array
   *   Data array is enriched with rows number.
   */
  private function rowsNumber() {
    $data = [];
    $data['range'] = $this->RequestedRange();
    $data['ranges'] = $this->beehotelVertical->timeRanges();
    $data['rows'] = $data['ranges'][$data['range']]['rows'];
    return $data['rows'];
  }

  /**
   * Produce cell content(html + data)
   */
  private function cellContent($data) {
    $data = $this->event->typeofOccupacy($data);
    $data['cellcontent'] = $this->verticalTableTrTd($data, $options = []);
    return $data;
  }

  /**
   * Collect order data.
   */
  private function getOrderData($data, $options) {

    $tmp = [];
    $tmp['day'] = $options['day'];
    $tmp['unit'] = $data['unit']['bid'];
    $tmp['currentday_order_id'] = $this->getCurrentDayOrderId($data);
    $tmp['daybefore_order_id'] = $this->getDayBeforeOrderId($data, $tmp['unit']);
    $order = [];
    $order['total_price__number'] = 0;

    if (
      !empty($data['occupancy'][$tmp['day']][$tmp['unit']])
      && !empty($data['occupancy'][$tmp['day']][$tmp['unit']]['order'])
    ) {

      $order['order_id'] = $data['occupancy'][$tmp['day']][$tmp['unit']]['order']->order_id;
      $order['mail'] = $data['occupancy'][$tmp['day']][$tmp['unit']]['order']->mail;
      $order['total'] = $data['occupancy'][$tmp['day']][$tmp['unit']]['order']->total_price__number;
      $order['order_number'] = $data['occupancy'][$tmp['day']][$tmp['unit']]['order']->order_number;
      $order['order_balance'] = $this->getOrderBalance($order['order_id'], FALSE);

      // @todo Improve i18.
      $order['order_balance'] = number_format($order['order_balance'], 2, ',', ' ');

      // No text for same order on following days.
      $order['hidden_text'] = FALSE;

      if (isset($data['occupancy'][$tmp['day']][$tmp['unit']]['order'])) {
        if ($data['occupancy'][$tmp['day']][$tmp['unit']]['order']->object->billing_profile->entity) {
          $address = $data['occupancy'][$tmp['day']][$tmp['unit']]['order']->object->billing_profile->entity->address->getValue()[0];
          $order['name'] = $address['given_name'] ?? '';
          $order['surname'] = $address['surname'] ?? '';
        }
      }

      if ($tmp['currentday_order_id'] != $tmp['daybefore_order_id']) {
        $order['hidden_text'] = TRUE;
      }
      return $order;
    }
  }

  /**
   * Get the balance.
   */
  private function getOrderBalance($order_id, $formatted) {

    $order = $this->entityTypeManager
      ->getStorage('commerce_order')
      ->loadByProperties(['order_id' => $order_id]);

    $balance = $order[$order_id]->getBalance();

    if ($formatted == FALSE) {
      return $balance->getNumber();
    }
    else {
      return $this->currencyFormatter->format($balance->getNumber(), $balance->getCurrencyCode());
    }
  }

  /**
   * Theme cell content.
   */
  private function verticalTableTrTd($data, $options) {

    // A.
    $a_attributes = $this->verticalTableTrTdAttributes($data, $options = ['div' => "a"]);
    $a_class = implode(" ", $a_attributes['class']);
    $a_content = $this->verticalOrderItem($data, $options = ['div' => "a"]);

    // Spacer.
    $spacer_attributes = $this->verticalTableTrTdAttributes($data, $options = ['div' => "spacer"]);
    $spacer_class = implode(" ", $spacer_attributes['class']);

    // B.
    $b_attributes = $this->verticalTableTrTdAttributes($data, $options = ['div' => "b"]);
    $b_class = implode(" ", $b_attributes['class']);
    $b_content = $this->verticalTableTrTdOrderOrState($data, $options = ['div' => "b"]);

    $output = [
      '#theme' => 'vertical_table_tr_td',
      '#a_class' => $a_class,
      '#a_content' => $a_content,
      '#b_class' => $b_class,
      '#b_content' => $b_content,
    ];

    // what's this?
    if (isset($a_content['#order_id']) && (int) $a_content['#order_id'] > 0) {
      $output['#spacer_class'] = $spacer_class;
    }
    return $output;
  }

  /**
   * What to expose in that cell.
   */
  private function verticalTableTrTdOrderOrState($data, $options = ['div' => "b"]) {
    $content = $this->verticalOrderItem($data, $options = ['div' => "b"]);

    if (!isset($content)) {
      $content = $this->verticalTableTrTdState($data, $options);
      return $content;
    }
    return $content;
  }

  /**
   * Card base price from weekly table.
   */
  private function verticalTableTrTdPrice($data, $options) {
    $unit = $this->beehotelUnit->getUnitFromBid($data['unit']['bid']);
    $price = [];
    $config = $this->config->get('beehotel_pricealterator.settings');
    $node_id = $data['unit']['nid'];
    $day = strtolower(date('D', $data['day']['timestamp']));
    $season = $data['season'];
    $key = $node_id . "_" . $day . "_" . $season;
    if (isset($key)) {
      $price['#markup'] = "<div class=\"price\">
      " . $config->get($key) . "  <span class='smaller'>" . $unit['store']->getDefaultCurrencyCode() . "</span>
      </div>";
    }
    return $price;
  }

  /**
   * Expose that cell state.
   */
  private function verticalTableTrTdState($data, $options) {

    /*
     * State of the day
     *
     * from default values at /admin/bat/events/state.
     * 1 Available #42f649 AV Not blocking Availability Daily.
     * 2 Not available #f04b4b N/A  Blocking Availability Daily.
     * 3 Booked #4b3cea BOOK Blocking Availability Daily.
     */
    $card = $this->verticalTableTrTdCard($data, $options);

    if (isset($card['#id'])) {
      $build[0]['link'] = [
        '#type' => 'link',
        '#title' => ['#markup' => $card['#markup']],
        '#attached' => ['library' => ['core/drupal.ajax']],
        '#attributes' => ['class' => ['use-ajax', 'state-link']],
        '#url' => Url::fromRoute('beehotel_vertical.ajax_link_callback', [
          'nojs' => 'nojs',
          'card_id' => $card['#id'],
        ]),
      ];

      $build[0]['destination'] = [
        '#type' => 'container',
        '#attributes' => ['id' => ['ajax-beehotel-vertical-destination-div']],
      ];
    }

    /*
     * Price of the day.
     *
     * Get today base price from weekly table
     */
    $price = $this->verticalTableTrTdPrice($data, $options);
    $url_object = Url::fromRoute('beehotel_pricealterator.mandatory.basepricetable', ['node' => $data['unit']['nid']]);

    $build[1]['link'] = [
      '#type' => 'link',
      '#title' => ['#markup' => $price['#markup']],
      '#attributes' => ['class' => ['price-link']],
      '#url' => $url_object,
    ];

    return $build;
  }

  /**
   * Produce a flip card.
   *
   * With no reservation, we produce a flippable card to swap availability.
   */
  private function verticalTableTrTdCard($data, $options) {

    $state = $card = [];
    $state['id'] = $this->event->getNightState($data);
    $data['event']['id'] = $this->event->getNightEvent($data);
    $data['event']['lenght'] = "";
    if (isset($data['event']['id'])) {
      $data['event']['object'] = bat_event_load($data['event']['id'], $reset = FALSE);
      $data['event']['lenght'] = $this->event->getEventLength($data['event']['object'], ['output' => "timestamp"]);
    }

    if (isset($state['id'])) {
      /* Events with no reservation longer than
       * one day not supported by VertiCal UI.
       */
      if ($data['event']['lenght'] == 86400 || $data['event']['lenght'] == "") {

        if ($state['id'] == 1) {
          $state['card']['front']['label'] = $this->t("AV");
          $state['card']['front']['css'] = "green";
          $state['card']['back']['label'] = $this->t("NO");
          $state['card']['back']['css'] = "red";
        }
        else {
          $state['card']['back']['label'] = $this->t("AV");
          $state['card']['back']['css'] = "green";
          $state['card']['front']['label'] = $this->t("NO");
          $state['card']['front']['css'] = "red";
        }

        $card['#id'] = implode("-", [
          "card",
          $data['unit']['bid'],
          $data['day']['year'],
          $data['day']['month'],
          $data['day']['day'],
        ]);

        $card['#markup'] = "<div class=\"state state-card\">
          <div class=\"card\" id=\"" . $card['#id'] . "\">
            <div class=\"card__face card__face--front " . $state['card']['front']['css'] . "\">" . $state['card']['front']['label'] . "</div>
            <div class=\"card__face card__face--back " . $state['card']['back']['css'] . "\">" . $state['card']['back']['label'] . " </div>
          </div>
        </div>";
      }
    }
    return $card;
  }

  /**
   * Callback for link example.
   *
   * Takes different logic paths based on whether Javascript was enabled.
   * If $type == 'ajax', it tells this function that ajax.js has rewritten
   * the URL and thus we are doing an AJAX and can return an array of commands.
   *
   * @param string $nojs
   *   Either 'ajax' or 'nojs. Type is simply the normal URL argument to this
   *   URL.
   * @param string $card_id
   *   Unique ID for cards.
   *
   * @return string|array
   *   If $type == 'ajax', returns an array of AJAX Commands.
   *   Otherwise, just returns the content, which will end up being a page.
   */
  public function ajaxLinkCallback($nojs = 'nojs', $card_id = "") {

    // Determine whether the request is coming from AJAX or not.
    if ($nojs == 'ajax') {
      // $output = $this->t("This is some content delivered via AJAX");
      $response = new AjaxResponse();

      // Create a $data array to feed the set function.
      $tmp = explode("-", $card_id);
      $data['day'] = [
        'year' => $tmp[2],
        'month' => $tmp[3],
        'day' => $tmp[4],
        'm' => str_pad($tmp[3], 2, '0', STR_PAD_LEFT),
        'd' => str_pad($tmp[4], 2, '0', STR_PAD_LEFT),
      ];

      $data['start_date'] = new DrupalDateTime(
        $data['day']['year'] . '-' . $data['day']['m'] . '-' . $data['day']['d'] . ' 00:00:00', 'UTC');

      $data['unit'] = ['bid' => $tmp[1]];

      $data['event_state'] = $this->event->getNightState($data);

      if ($this->event->getNightState($data) == 1) {
        $data['new_state'] = 2;
      }
      else {
        $data['new_state'] = 1;
      }

      $event = bat_event_create(['type' => 'availability_daily']);
      $event_dates = [
        'value' => $data['start_date']->format('Y-m-d\TH:i:00'),
        'end_value' => $data['start_date']->modify('+1 day')->format('Y-m-d\TH:i:00'),
      ];
      $event->set('event_dates', $event_dates);
      $event->set('event_state_reference', $data['new_state']);
      $event->set('event_bat_unit_reference', $data['unit']['bid']);
      $event->save();

      // A jQuery selector.
      $selector = "#" . $card_id;

      // The name of a jQuery method to invoke.
      $method = 'toggleClass';

      // (Optional) An array of arguments to pass to the method.
      $arguments = ['is-flipped'];
      $response->addCommand(new InvokeCommand($selector, $method, $arguments));

      return $response;
    }
    $response = new Response($this->t("Delivering via page load."));
    return $response;
  }

  /**
   * Extra info about cell content (maybe events).
   */
  private function verticalOrderItemExtra($data, $options) {
    $output = NULL;
    $output = $this->verticalOrderItemLastComment($data, $options);
    return $output;
  }

  /**
   * Get the string of the last comment for a given order.
   */
  private function verticalOrderItemLastComment($data, $options) {}

  /**
   * Build up order info.
   *
   *   Returns null when no reservation is fund.
   */
  private function verticalOrderItem($data, $opt) {

    $order = [];
    $config = $this->config->get('beehotel_vertical.settings');

    $tmp = [];
    $tmp['unit'] = $data['unit']['bid'];
    $tmp['currentday_order_id'] = $this->getCurrentDayOrderId($data);
    $tmp['daybefore_order_id'] = $this->getDayBeforeOrderId($data, $tmp['unit']);

    if ($opt['div'] == "a") {
      $options = [
        'day' => $data['day']['daybefore']['ISO8601'],
        'div' => $opt['div'],
      ];
    }
    elseif ($opt['div'] == "b") {
      $options = [
        'day' => $data['day']['today']['ISO8601'],
        'div' => $opt['div'],
      ];
    }
    $order = $this->getOrderData($data, $options);
    $mail = $order['mail'] ?? '';
    $total = $order['total_price__number'] ?? 0;

    if ($order) {

      if ($config->get('vertical.warning.money') == 1) {
        $this->messenger()->addWarning($this->t('IMPORTANT: You have money to collect'));
      }

      $items = $this->entityTypeManager->getStorage('commerce_payment')->loadByProperties(['order_id' => $order['order_id']]);

      $payments = [];
      foreach ($items as $item) {
        if ($item->get('state')->value == "completed") {
          $payments[] = [
            'amount' => number_format($item->get('amount')->number, 2, ',', ' '),
            'payment_id' => $item->get('payment_id')->value,
            'date' => $this->dateFormatter->format($item->get('completed')->value, 'custom', 'd/m/Y'),
          ];
        }
      }

      // @todo add extra info/notes
      $order['extra'] = $this->verticalOrderItemExtra($data, $options);

      return [
        '#theme' => 'vertical_order_item',
        '#balance' => $order['order_balance'],
        '#comments' => $data['tmp']['order']->last_comment ?? "",
        '#extra' => $order['extra'],
        '#hidden_text' => $order['hidden_text'],
        '#mail' => Unicode::truncate($mail, 8, FALSE, TRUE) ,
        '#name' => $order['name'] ?? '',
        '#surname' => $order['surname'] ?? '',
        '#order_id' => $order['order_id'] ?? '' ,
        '#order_number' => $order['order_number'] ?? '',
        '#payments' => $payments ?? '',
        '#total' => number_format($total),
      ];
    }
  }

  /**
   * Build up atributes for table cell.
   */
  private function verticalTableTrTdAttributes($data, $options) {

    $attributes = ['class' => []];
    $tmp['unit'] = $data['unit']['bid'];

    $tmp['currentday_order_id'] = $this->getCurrentDayOrderId($data);
    $tmp['daybefore_order_id'] = $this->getDayBeforeOrderId($data, $tmp['unit']);

    if (empty($data['occupancy'][$data['day']['daybefore']['ISO8601']])) {
      $data['first_row_of_the_table'] = TRUE;
    }

    // A.
    if ($options['div'] == "a") {

      // A1. Same reservation today and yestarday falls  into A2/A3.
      // A2. Guest is checking out today.
      if (!empty($tmp['daybefore_order_id']) && $tmp['currentday_order_id'] != $tmp['daybefore_order_id']) {
        $attributes['class'][] = 'reservation';
        $attributes['class'][] = 'checkout';
      }

      // A3. Today's reservation is the same as yestarday.
      if (!empty($tmp['daybefore_order_id']) && $tmp['currentday_order_id'] == $tmp['daybefore_order_id']) {
        $attributes['class'][] = 'reservation';
        $attributes['class'][] = 'same-as-daybefore';
      }

      // @todo A4. Show Checkout on the first day in table.
    }

    // Spacer.
    if ($options['div'] == "spacer") {
      /*
       * Spacer 1. We have some reservation today and today reservation
       * is not checking in.
       */
      if (!empty($data['occupancy']['current']) &&
          !empty($data['occupancy']['current']['order'])  &&
          $data['occupancy']['current']['order']->checkin != $data['day']['today']['ISO8601'] &&
          $data['occupancy']['current']['order']->checkout != $data['day']['today']['ISO8601']) {
        $attributes['class'][] = 'occupied';
      }

      if (isset($data['occupancy']['current']['event']['id'])) {
        $attributes['class'][] = 'no-display';
      }

    }

    // B.
    if ($options['div'] == "b") {

      // B1. We have a reservation today.
      if (!empty($data['occupancy']['current']) && !empty($data['occupancy']['current']['order'])) {

        $attributes['class'][] = 'reservation';

        // B1.1. Today reservation is checkin today.
        if ($data['occupancy']['current']['order']->checkin == $data['day']['today']['ISO8601']) {
          $attributes['class'][] = 'checkin';
        }
      }

    }
    return $attributes;
  }

  /**
   * Add order id to data.
   */
  private function getCurrentDayOrderId($data) {
    if (!empty($data)) {
      if (!empty($data['occupancy'])) {
        if (!empty($data['day']['today']['ISO8601'])) {
          if (!empty($data['occupancy'][$data['day']['today']['ISO8601']])) {
            if (!empty($data['unit']['bid'])) {
              if (!empty($data['occupancy'][$data['day']['today']['ISO8601']][$data['unit']['bid']]['order'])) {
                if (!empty($data['occupancy'][$data['day']['today']['ISO8601']][$data['unit']['bid']]['order'])) {
                  return $data['occupancy'][$data['day']['today']['ISO8601']][$data['unit']['bid']]['order']->order_id;
                }
              }
            }
          }
        }
      }
    }
  }

  /**
   * Get Day before.
   */
  private function getDayBeforeOrderId($data) {
    if (!empty($data)) {
      if (!empty($data['occupancy'])) {
        if (!empty($data['day']['daybefore']['ISO8601'])) {
          if (!empty($data['occupancy'][$data['day']['daybefore']['ISO8601']])) {
            if (!empty($data['unit']['bid'])) {
              if (!empty($data['occupancy'][$data['day']['daybefore']['ISO8601']][$data['unit']['bid']]['order'])) {
                $res = $data['occupancy'][$data['day']['daybefore']['ISO8601']][$data['unit']['bid']]['order']->order_id;
                return $res;
              }
            }
          }
        }
      }
    }
  }

  /**
   * TimeMachine data to step back in time.
   */
  public function page() {
    $result = $this->result($data = []);
    return [
      '#type' => 'markup',
      '#markup' => $result,
    ];
  }

  /**
   * Display a form.
   */
  private function displayTimeRangeForm() {
    $data = [];
    $data['class'] = "\Drupal\beehotel_vertical\Form\TimeRangeForm";
    $form = $this->formBuilder->getForm($data['class']);
    $htmlForm = $this->renderer->render($form);
    return $htmlForm;
  }

  /**
   * Give a default value.
   */
  private function defaultTimeRange() {
    return "cw";
  }

  /**
   * Callback for opening the modal form.
   */
  public function editState() {
    $response = new AjaxResponse();
    // Get the modal form using the form builder.
    $modal_form = $this->formBuilder->getForm('Drupal\beehotel_vertical\Form\BeeHotelVerticalEditEvent');
    // Add an AJAX command to open a modal dialog with the form as the content.
    $response->addCommand(new OpenModalDialogCommand('Edit Event', $modal_form, ['width' => '555']));
    return $response;
  }

  /**
   * Get a fresh session object.
   *
   * @return \Symfony\Component\HttpFoundation\Session\SessionInterface
   *   A session object.
   */
  protected function getSession() {
    return $this->requestStack->getCurrentRequest()->getSession();
  }

  /**
   * Checks access for a specific request.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account) {
    return AccessResult::allowedIf($account->hasPermission('view beehotel_vertical all'));
  }

  /**
   * Get data set as Season.
   *
   *  @todo move this into some Util class.
   */
  private function getSeason(&$data) {
    $timestamp = $data['day']['timestamp'];
    $plugin_id = "GetSeason";
    $getSeason = $this->pluginManagerInterface->createInstance($plugin_id, []);
    $getSeason->getThisDaySeasonFromInput($timestamp, $data);
  }

}
