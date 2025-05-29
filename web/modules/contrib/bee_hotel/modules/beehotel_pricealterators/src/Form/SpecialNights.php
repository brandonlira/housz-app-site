<?php

namespace Drupal\beehotel_pricealterators\Form;

use Drupal\beehotel_utils\BeeHotelCommerce;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure SpecialNights Alterator.
 */
class SpecialNights extends ConfigFormBase {

  use StringTranslationTrait;

  /**
  * The node  content type for this alterator.
  *
  * @var string
  */
  const BEEHOTEL_TYPE = 'special_night';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The comment storage.
   *
   * @var \Drupal\comment\CommentStorageInterface
   */
  protected $nodeStorage;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The BeeHotel commerce Util.
   *
   * @var \Drupal\beehotel_utils\BeeHotelCommerce
   */
  protected $beehotelCommerce;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Creates a SpecialNight form.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The tempstore factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\beehotel_utils\BeeHotelCommerce $beehotel_commerce
   *   BeeHotel Commerce Utils.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, DateFormatterInterface $date_formatter, ModuleHandlerInterface $module_handler, PrivateTempStoreFactory $temp_store_factory, ConfigFactoryInterface $config_factory, BeeHotelCommerce $beehotel_commerce, RendererInterface $renderer) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
    $this->nodeStorage = $entity_type_manager->getStorage('node');
    $this->dateFormatter = $date_formatter;
    $this->moduleHandler = $module_handler;
    $this->tempStoreFactory = $temp_store_factory;
    $this->beehotelCommerce = $beehotel_commerce;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('module_handler'),
      $container->get('tempstore.private'),
      $container->get('config.factory'),
      $container->get('beehotel_utils.beehotelcommerce'),
      $container->get('renderer'),
    );
  }

  /**
   * Reference to the Alterator (as plugin).
   *
   *   This value matches the ID in the @PriceAlterator annotation.
   */
  public function pluginId() {
    return 'SpecialNights';
  }

  /**
   * {@inheritdoc}
   */
  public function configName() {
    return BEEHOTEL_PRICEALTERATOR_ROUTE_BASE . $this->pluginId() . '.settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return $this->configName();
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      $this->configName(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $data = [];
    $data['symbol'] = $this->beehotelCommerce->currentStoreCurrency()->get('symbol');

    $config = $this->config($this->configName());

    $form = [
      '#type' => 'fieldset',
      '#title' => $this->t('Special Nights alterator'),
      '#collapsible' => FALSE,
    ];

    $form = [
      '#type' => 'fieldset',
      '#title' => $this->t('Special Nights'),
      '#collapsible' => FALSE,
    ];

    $info = $this->t("<b>Special Nights</b> will produce for every night you want a given special price. <a href='/node/add/special_night'>Create a special night</a> for:
    <ul>
      <li><b>Bank holidays</b>: rise the base price for Christmas, Easter, National or local holidays, etc.
      <li><b>Events</b>: night with Concerts, Exibitions, sport Tournmentas.
      <li><b>Revenue Management</b>: create special nights to apply fixed priced created by your revenus manager. These special nights usually have the <i>fixed</i> alterator and can be programmatically imported though the <a href='https://www.drupal.org/project/feeds' target=\"_blank\">Feeds</a> module
     </ul>
    ");

    $form['info'] = [
      '#type' => 'details',
      '#title' => $this->t('Info'),
      '#description' => $info,
      '#open' => FALSE,
    ];

    $form['enabled'] = [
      '#default_value' => $config->get('enabled'),
      '#type' => 'checkbox',
      '#title' => $this->t('Enable this alterator'),
      '#description' => $this->t('When on, this alterator will be applied the BEEHotel prices'),
    ];

    $header = [
      'title' => [
        'data' => $this->t('Title'),
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],

      'from' => [
        'data' => $this->t('From'),
        'specifier' => 'name',
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],

      'to' => [
        'data' => $this->t('To'),
        'specifier' => 'name',
        'class' => [RESPONSIVE_PRIORITY_MEDIUM],
      ],

      'value' => [
        'data' => $this->t('Value'),
        'specifier' => 'name',
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],

      'status' => [
        'data' => $this->t('Status'),
        'specifier' => 'name',
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],

      'changed' => [
        'data' => $this->t('Updated'),
        'specifier' => 'changed',
        'sort' => 'desc',
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'operations' => $this->t('Operations'),
    ];

    $date = new DrupalDateTime("-1 day");
    $date->setTimezone(new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE));
    $formatted = $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    $node_query = $this->nodeStorage->getQuery();
    $nids = $node_query
      ->accessCheck(TRUE)
      ->condition('type', 'special_night')
      ->condition('field_nights.value', $formatted, '>')
      ->sort('field_nights.value', 'ASC')
      ->tableSort($header)
      ->pager(14)
      ->execute();

    $nodes = $this->nodeStorage->loadMultiple($nids);

    // Build a table listing the appropriate comments.
    $options = [];
    $destination = $this->getDestinationArray();

    foreach ($nodes as $node) {
      $data['from'] = new \DateTime($node->get('field_nights')->value);
      $data['to'] = new \DateTime($node->get('field_nights')->end_value);

      $data['value']['polarity'] = $node->get("field_polarity")->value;
      $data['value']['polarity'] = str_replace("add", "+", $data['value']['polarity']);
      $data['value']['polarity'] = str_replace("subtract", "-", $data['value']['polarity']);
      $data['value']['value'] = $node->get("field_alteration")->value;
      $data['value']['type'] = $node->get("field_type")->value;
      $data['value']['type'] = str_replace("percentage", "%", $data['value']['type']);
      $data['value']['type'] = str_replace("integer", $data['symbol'], $data['value']['type']);
      $data['value']['class'] = implode(
        ' ', [
          $node->get("field_polarity")->value, $node->get("field_type")->value,
        ],
      );
      $data['value']['markup'] = "<span class=\"" . $node->get("field_polarity")->value . "\" >" . $data['value']['polarity'] . " </span>";
      $data['value']['markup'] .= $data['value']['value'];

      $data['value']['markup'] .= "<span class=\"" . $node->get("field_type")->value . " " . $node->get("field_polarity")->value . "\" \" >" . $data['value']['type'] . " </span>";

      if ($node->get("status")->value == 1) {
        $data['status']['class'] = "enabled";
        $data['status']['markup'] = "<span class=\"" . $data['status']['class'] . "\" >" . $this->t("enabled") . " </span>";
      }
      else {
        $data['status']['class'] = "disabled";
        $data['status']['markup'] = "<span class=\"" . $data['status']['class'] . "\" >" . $this->t("disabled") . " </span>";
      }
      $data['class'] = $data['status']['class'];

      $options[$node->id()] = [
        '#attributes' => ['class' => $data['class']],
        'title' => [
          'data' => [
            '#type' => 'link',
            '#title' => $node->label(),
            '#access' => $node->access('view'),
            '#url' => $node->toUrl(),
          ],
        ],
        'from' => [
          $data['from']->format('l d M Y'),
        ],

        'to' => [
          $data['to']->format('l d M Y'),
        ],

        'value' => [
          'title' => [
            'data' => [
              '#markup' => Markup::create($data['value']['markup']),
            ],
          ],
        ],

        'status' => [
          'status' => Markup::create($data['status']['markup']),
        ],

        'changed' => $this->dateFormatter->format($node->getChangedTimeAcrossTranslations(), 'short'),
      ];

      $node_uri_options = $node->toUrl()->getOptions() + ['query' => $destination];

      $links = [];

      $links['edit'] = [
        'title' => $this->t('Edit'),
        'url' => $node->toUrl('edit-form', $node_uri_options),
      ];

      $options[$node->id()]['operations']['data'] = [
        '#type' => 'operations',
        '#links' => $links,
      ];
    }

    $form['nodes'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Next special night'),
      '#collapsible' => FALSE,
    ];

    $url = Url::fromRoute('node.add', ['node_type' => 'special_night']);
    $link = Link::fromTextAndUrl($this->t('add Special Night'), $url);
    $link = $link->toRenderable();

    $form['nodes']['table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $options,
      '#empty' => $this->t('No special night available.'),
      '#suffix' => $this->renderer->render($link),
    ];

    $form['#attached']['library'][] = 'beehotel_pricealterator/pricealterator';
    $form['#attached']['library'][] = 'beehotel_pricealterators/alterator-special-nights';
    $form['#attributes'] = ['class' => ['beehotel-pricealterator']];

    $form['pager'] = ['#type' => 'pager'];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config($this->configName())
      ->set('enabled', $form_state->getValue('enabled'))
      ->set('percentage', $form_state->getValue('percentage'))
      ->set('fixed', $form_state->getValue('fixed'))
      ->save();
    parent::submitForm($form, $form_state);
    $form_state->setRedirect('beehotel_pricealterator.info.chain');
  }

}
