<?php

namespace Drupal\beehotel_vertical\Controller;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\bee_hotel\Event;
use Drupal\beehotel_utils\BeeHotelUnit;
use Drupal\beehotel_vertical\BeehotelVertical;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AlertCommand;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\CssCommand;
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
use Drupal\user\Entity\Role;
use Symfony\Component\DependencyInjection\ContainerInterface;
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
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\bee_hotel\Event $beehotel_event
   *   The Bee Hotel Event util.
   * @param \Drupal\beehotel_utils\BeeHotelUnit $bee_hotel_unit
   *   The BeeHotel Unit Utility.
   * @param \Drupal\beehotel_vertical\BeehotelVertical $beehotel_vertical
   *   The BeeHotel Vertical Class for features.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $beehotelVerticalManager
   *   The BeeHotel Vertical manager.
   */
  public function __construct(RendererInterface $renderer, FormBuilder $form_builder, DateFormatterInterface $date_formatter, EntityTypeManagerInterface $entity_type_manager, CurrencyFormatterInterface $currency_formatter, ConfigFactoryInterface $config_factory, Event $beehotel_event, BeeHotelUnit $bee_hotel_unit, BeehotelVertical $beehotel_vertical, PluginManagerInterface $beehotelVerticalManager) {
    $this->renderer = $renderer;
    $this->formBuilder = $form_builder;
    $this->dateFormatter = $date_formatter;
    $this->entityTypeManager = $entity_type_manager;
    $this->currencyFormatter = $currency_formatter;
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
      $container->get('config.factory'),
      $container->get('bee_hotel.event'),
      $container->get('beehotel_utils.beehotelunit'),
      $container->get('beehotel_vertical.beehotelvertical'),
      $container->get('plugin.manager.beehotel.pricealterator'),
    );
  }

  /**
   * Produce the table.
   */
  public function result($data) {
    $output = $this->buildTable($data);
    $output['#prefix'] = $this->displayTimeRangeForm();
    return $this->renderer->render($output);
  }

  /**
   * Returns only the rendered table HTML (without the time range form).
   * Used for AJAX updates.
   *
   * @param array $data
   *   An array with report related data.
   *
   * @return string
   *   The rendered table HTML.
   */
  public function renderTable($data) {
    $output = $this->buildTable($data);
    return $this->renderer->render($output);
  }

  /**
   * Builds the table render array (without the time range form).
   *
   * @param array $data
   *   An array with report related data.
   *
   * @return array
   *   A render array for the table.
   */
  protected function buildTable(array $data) {
    $data['rows'] = $this->rows($data);
    $data['header'] = $this->header($data);

    return [
      '#type' => 'table',
      '#attributes' => [
        'class' => ['vertical-table'],
      ],
      '#header' => $data['header'],
      '#rows' => $data['rows'],
      '#empty' => $this->t('Your table is empty'),
    ];
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

    $header[] = Markup::create("<h3 class='settings'>" . $this->renderer->render($link) . "</h3>");

    $config = $this->config('beehotel_vertical.settings');
    $header_fields = $config->get('vertical.header_fields') ?: ['title'];
    $header_format = $config->get('vertical.header_format') ?: '[node:title]';

    if (!empty($data['units'])) {
      foreach ($data['units'] as $unit) {
        $header_text = $this->generateUnitHeader($unit, $header_format, $header_fields);

        $url = Url::fromRoute('entity.node.edit_form', ['node' => $unit->Id()]);

        $unit_link = [
          '#type' => 'link',
          '#title' => Markup::create($header_text),
          '#url' => $url,
          '#attributes' => ['class' => ['button', 'link']],
        ];

        $rendered_link = $this->renderer->render($unit_link);
        $header[] = Markup::create("<h3 class='unit'>" . $rendered_link . "</h3>");
      }
    }

    $data['header'] = $header;
    return $data['header'];
  }

  private function generateUnitHeader($unit, $header_format, $header_fields) {
    $header_text = $header_format;

    foreach ($header_fields as $field_name) {
      if ($field_name && $field_name !== '0') {
        $token = '[node:' . $field_name . ']';
        $value = $this->getFieldValue($unit, $field_name);

        if (!empty($value)) {
          $css_class = str_replace('_', '-', $field_name);
          $wrapped_value = '<span class="header-field header-field--' . $css_class . '">' . $value . '</span>';
          $header_text = str_replace($token, $wrapped_value, $header_text);
        } else {
          $header_text = str_replace($token, '', $header_text);
        }
      }
    }

    return trim($header_text) ?: $unit->getTitle(); // Fallback al titolo se vuoto
  }

  private function getFieldValue($node, $field_name) {
    if ($field_name == 'title') {
      return $node->getTitle();
    }

    if (!$node->hasField($field_name) || $node->get($field_name)->isEmpty()) {
      return '';
    }

    $field = $node->get($field_name);
    $field_type = $field->getFieldDefinition()->getType();

    switch ($field_type) {
      case 'entity_reference':
        $entity = $field->entity;
        return $entity ? $entity->label() : '';

      case 'datetime':
      case 'date':
        return $field->date ? $field->date->format('d/m/Y') : '';

      case 'boolean':
        return $field->value ? $this->t('Yes') : $this->t('No');

      case 'list_string':
        $value = $field->value;
        $allowed_values = $field->getFieldDefinition()->getSetting('allowed_values');
        return $allowed_values[$value] ?? $value;

      case 'text_with_summary':
      case 'text_long':
        return strip_tags($field->value);

      default:
        return $field->value;
    }
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

    $beeHotelUtil = \Drupal::service('beehotel_utils.beehotel');

    $data = [];

    $data['session'] = $beeHotelUtil->getSession();

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
      $order['show_text'] = FALSE;

      if (isset($data['occupancy'][$tmp['day']][$tmp['unit']]['order'])) {
        if ($data['occupancy'][$tmp['day']][$tmp['unit']]['order']->object->billing_profile->entity) {
          $address = $data['occupancy'][$tmp['day']][$tmp['unit']]['order']->object->billing_profile->entity->address->getValue()[0];
          $order['name'] = $address['given_name'] ?? '';
          $order['surname'] = $address['surname'] ?? '';
        }
      }

      if ($tmp['currentday_order_id'] != $tmp['daybefore_order_id']) {
        $order['show_text'] = TRUE;
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

    $beeHotelUtil = \Drupal::service('beehotel_utils.beehotel');
    $data['session'] = $beeHotelUtil->getSession();
    $beehotel_data = $data['session']->get('beehotel_data');

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

    $td_classes = ['vertical-tr-td'];

    if (isset($data['tmp']['order']->is_blocking) && $data['tmp']['order']->is_blocking != 1) {
      $td_classes[] = 'not-blocking';
    }

    if (isset($beehotel_data) && isset($beehotel_data['not_blocking_order_events_by_date_nid'])) {

      $not_blocking_order_events_by_date_nid = $beehotel_data['not_blocking_order_events_by_date_nid'];

      if (isset($not_blocking_order_events_by_date_nid[$data['day']['daybefore']['ISO8601']][$data['unit']['nid']])) {
        $td_classes[] = 'not-blocking';
      }
    }

    if (isset($data['tmp']['order']->bat_event)) {
      $data['current_order_event_id'] = $data['tmp']['order']->bat_event->get('id')->value;
      if (isset($beehotel_data['not-blocking-order-events'][$data['current_order_event_id']])) {
        $td_classes[] = 'not-blocking';
      }
    }

    $output = [
      '#theme' => 'vertical_table_tr_td',
      '#a_class' => $a_class,
      '#a_content' => $a_content,
      '#b_class' => $b_class,
      '#b_content' => $b_content,
      '#td_classes' => $td_classes,
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
      // return "";
      return $content;
    }
    return $content;
  }

  /**
  * Generates price markup for vertical table cells with currency formatting.
  *
  * This function retrieves the price for a specific unit on a given day and
  * season, formats it with the currency symbol from the store, and displays
  * it with the currency symbol at the beginning and decimal parts as superscript.
  *
  * @param array $data
  *   An associative array containing:
  *   - 'unit': Unit data with 'bid' and 'nid'.
  *   - 'day': Day data with 'timestamp'.
  *   - 'season': Season identifier.
  * @param array $options
  *   Optional array of formatting options (currently unused but kept for
  *   compatibility with calling patterns).
  *
  * @return array
  *   A render array with '#markup' containing the formatted price HTML.
  *   Includes appropriate cache metadata for currency and store context.
  */
  private function verticalTableTrTdPrice(array $data, array $options): array {
    // Initialize the price render array.
    $price = [];

    // Retrieve the unit data using the business ID.
    $unit = $this->beehotelUnit->getUnitFromBid($data['unit']['bid']);

    // Get the price configuration settings.
    $config = $this->config->get('beehotel_pricealterator.settings');

    // Build the unique key for price lookup based on node, day, and season.
    $node_id = $data['unit']['nid'];
    $day = strtolower(date('D', (int) $data['day']['timestamp']));
    $season = $data['season'];
    $key = $node_id . "_" . $day . "_" . $season;

    // Check if the price key exists and has a value.
    if (isset($key) && ($price_value = $config->get($key))) {
      // Get the store from the unit to determine the currency.
      $store = $unit['store'];
      $currency_code = $store->getDefaultCurrencyCode();

      // Get the currency symbol using Commerce's currency formatter service.
      $currency_formatter = \Drupal::service('commerce_price.currency_formatter');
      // $symbol = $currency_formatter->getSymbol($currency_code, 'narrow');

      $currency = \Drupal\commerce_price\Entity\Currency::load($currency_code);

      if ($currency) {
          $symbol = $currency->getSymbol();
          // Se il simbolo è troppo lungo (es: "USD"), usa il codice
          if (mb_strlen($symbol) > 3) {
              $symbol = $currency_code;
          }
      } else {
          $symbol = $currency_code;
      }

      // Fallback to standard symbol if narrow symbol is not available.
      if (empty($symbol)) {
        $symbol = $currency_formatter->getSymbol($currency_code);
      }

      // Final fallback to currency code if no symbol is found.
      if (empty($symbol)) {
        $symbol = $currency_code;
      }

      // Format the price value to ensure exactly two decimal places.
      $formatted_price = number_format((float) $price_value, 2, '.', '');
      list($integer_part, $decimal_part) = explode('.', $formatted_price);

      // Only show decimal superscript if decimal part is not "00".
      $decimal_markup = '';
      if ($decimal_part !== '00') {
        $decimal_markup = '<sup class="price__decimal">'
          . htmlspecialchars($decimal_part, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
          . '</sup>';
      }

      // Build the HTML markup for the formatted price.
      $price_markup = sprintf(
        '<div class="price price--formatted" data-currency="%s" data-value="%s">
          <span class="price__currency">%s</span>
          <span class="price__integer">%s</span>
          %s
        </div>',
        htmlspecialchars($currency_code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        htmlspecialchars($price_value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        htmlspecialchars($symbol, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        htmlspecialchars($integer_part, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
        $decimal_markup
      );

      $price['#markup'] = $price_markup;

      // Add cache contexts for currency and language interface.
      $price['#cache']['contexts'][] = 'commerce_currency';
      $price['#cache']['contexts'][] = 'languages:language_interface';

      // Add store-specific cache tags for proper cache invalidation.
      if ($store) {
        $price['#cache']['tags'] = $store->getCacheTags();
      }
    }
    else {
      // Display a placeholder when no price is available.
      $price['#markup'] = '<div class="price price--empty" title="Price not available">-</div>';
      $price['#attributes']['class'][] = 'no-price';
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
     *
     */

    $data['has_full_access'] = \Drupal::currentUser()->hasPermission('beehotel_vertical_access_full_vertical');

    if ($data['has_full_access'] != TRUE) {
      return;
    }

    // Full content below.
    $card = $this->verticalTableTrTdCard($data, $options);

    if (isset($card['#id'])) {
      $build[]['link'] = [
        '#type' => 'link',
        '#title' => ['#markup' => $card['#markup']],
        '#attached' => [
          'library' => [
            'core/drupal.ajax',
            'core/jquery',
          ],
        ],
        '#attributes' => [
          'class' => ['use-ajax', 'state-link'],
          'data-dialog-type' => 'ajax',
          'data-ajax-progress' => 'none', // Disable default progress indicator
        ],
        '#url' => Url::fromRoute('beehotel_vertical.ajax_link_callback', [
          'nojs' => 'ajax',
          'card_id' => $card['#id'],
        ]),
      ];
    }

    /*
     * Price of the day.
     *
     * Get today base price from weekly table
     */
    $price = $this->verticalTableTrTdPrice($data, $options);
    $url_object = Url::fromRoute('beehotel_pricealterator.mandatory.basepricetable', ['node' => $data['unit']['nid']]);

    $build[]['link'] = [
      '#type' => 'link',
      '#title' => ['#markup' => $price['#markup']],
      '#attributes' => ['class' => ['price-link']],
      '#url' => $url_object,
    ];

    return $build;
  }

  private function verticalTableTrTdCard($data, $options) {
    $state = $card = [];
    $state['id'] = $this->event->getNightState($data);
    $data['event']['id'] = $this->event->getNightEvent($data);
    $data['event']['length'] = "";

    if (isset($data['event']['id'])) {
      $data['event']['object'] = bat_event_load($data['event']['id'], $reset = FALSE);
      $data['event']['length'] = $this->event->getEventLength($data['event']['object'], ['output' => "timestamp"]);
    }

    if (isset($state['id'])) {
      // Events with no reservation longer than one day not supported by VertiCal UI
      if ($data['event']['length'] == 86400 || $data['event']['length'] == "") {
        if ($state['id'] == 1) {
          $state['card']['front']['label'] = $this->t("AV");
          $state['card']['front']['css'] = "green";
          $state['card']['back']['label'] = $this->t("NO");
          $state['card']['back']['css'] = "red";
        }
        else {
          $state['card']['front']['css'] = "red";
          $state['card']['front']['label'] = $this->t("NO");
          $state['card']['back']['label'] = $this->t("AV");
          $state['card']['back']['css'] = "green";
        }
      }
    }
    else {
      // ND = not defined
      $state['card']['front']['label'] = $this->t("ND");
      $state['card']['front']['css'] = "grey";
      $state['card']['back']['label'] = $this->t("AV");
      $state['card']['back']['css'] = "green";
    }

    $card['#id'] = implode("-", [
      "card",
      $data['unit']['bid'],
      $data['day']['year'],
      $data['day']['month'],
      $data['day']['day'],
    ]);

    $card['#markup'] = "";
    if (isset($state['card'])) {
      // Generate proper HTML structure
      $card['#markup'] = '<div class="state state-card">
        <div class="card" id="' . $card['#id'] . '">
          <div class="card__face card__face--front ' . $state['card']['front']['css'] . '">' .
            $state['card']['front']['label'] .
          '</div>
          <div class="card__face card__face--back ' . $state['card']['back']['css'] . '">' .
            $state['card']['back']['label'] .
          '</div>
        </div>
      </div>';
    }

    return $card;
  }

  /**
   * Callback for card.
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

      $selector = "#" . $card_id;

      // Get the new state
      $newState = $data['new_state'];

      // Determine new colors and text
      if ($newState == 1) {
        $frontColor = 'green';
        $frontText = $this->t('AV');
        $backColor = 'red';
        $backText = $this->t('NO');
      } else {
        $frontColor = 'red';
        $frontText = $this->t('NO');
        $backColor = 'green';
        $backText = $this->t('AV');
      }

      // Update back face
      $backHtml = '<div class="card__face card__face--back ' . $backColor . '">' . $backText . '</div>';
      $response->addCommand(new HtmlCommand($selector . ' .card__face--back', $backHtml));

      // Update front face using HtmlCommand (more reliable)
      $frontHtml = '<div class="card__face card__face--front ' . $frontColor . '">' . $frontText . '</div>';
      $response->addCommand(new HtmlCommand($selector . ' .card__face--front', $frontHtml));

      // Toggle the flip class AFTER updating content
      $response->addCommand(new InvokeCommand($selector, 'toggleClass', ['is-flipped']));

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
   *  Returns null when no reservation is fund.
   */
  private function verticalOrderItem($data, $opt) {

    $beeHotelUtil = \Drupal::service('beehotel_utils.beehotel');

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

      // Jan 26 Error is payment doen not exists
      //$items = $this->entityTypeManager->getStorage('commerce_payment')->loadByProperties(['order_id' => $order['order_id']]);

      try {
        $storage = $this->entityTypeManager->getStorage('commerce_payment');
        $items = $storage->loadByProperties(['order_id' => $order['order_id']]);

        if (empty($items)) {
          // No item exists for this order.
        } else {
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
        }
      } catch (\Exception $e) {
        // Entity type 'commerce_payment' doesn't exist or other error
        \Drupal::logger('beehotel_vertical')->error('Payment entity type not found: @error', ['@error' => $e->getMessage()]);
      }

      // @todo add extra info/notes.
      $order['extra'] = $this->verticalOrderItemExtra($data, $options);

      $order['classes'] = [
        'vertical-order-item',
      ];

      if (isset($data['tmp']['order']->is_blocking) && $data['tmp']['order']->is_blocking != 1) {
        $order['classes'] = 'not-blocking';
        $order['extra'] = $this->t('No-Blocking');
        $data['not_blocking_order_events_by_date_nid'][$data['day']['day_of_elaboration']['ISO8601']][$data['unit']['nid']] = $data['tmp']['event_id'];
        $beeHotelUtil->storeInSession($data);
      }

      $data['current_user'] = \Drupal::currentUser();
      $data['roles'] = $data['current_user']->getRoles();

      $data['entities'] = Role::loadMultiple($data['roles']);
      $data['role_permissions'] = [];
      foreach ($data['roles'] as $rid) {
        $data['role_permissions'][$rid] = $data['entities'][$rid]?->getPermissions();
      }

      $data['has_full_access'] = \Drupal::currentUser()->hasPermission('beehotel_vertical_access_full_vertical');

      if ($data['has_full_access'] != TRUE) {
        $order['show_text'] = "";
      }

      return [
        '#theme' => 'vertical_order_item',
        '#balance' => $order['order_balance'],
        '#classes' => $order['classes'],
        '#comments' => $data['tmp']['order']->last_comment ?? "",
        '#extra' => $order['extra'],
        '#show_text' => $order['show_text'],
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
    return [
      'form' => [
        '#markup' => $this->displayTimeRangeForm(),
      ],
      'table_container' => [
        '#type' => 'container',
        '#attributes' => ['id' => 'vertical-table-container'],
        '#markup' => $this->renderTable([]),
      ],
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
   * Checks access for a specific request.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account) {

    $d = [];

    $d['access_vertical_basic'] = $account->hasPermission('access_vertical_basic');
    $d['access_vertical_full'] = $account->hasPermission('access_vertical_full');
    $d['access_vertical'] = NULL;

    if ($d['access_vertical_basic'] || $d['access_vertical_full']) {
      $d['access_vertical'] = TRUE;
    }

    return AccessResult::allowedIf($d['access_vertical']);

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
