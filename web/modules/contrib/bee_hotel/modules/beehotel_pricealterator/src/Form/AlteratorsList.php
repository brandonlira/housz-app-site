<?php

namespace Drupal\beehotel_pricealterator\Form;

use Drupal\beehotel_pricealterator\PriceAlteratorPluginManager;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Price Alterators draggable list form.
 *
 * @ingroup beehotel_pricealterator
 */
class AlteratorsList extends FormBase {

  use StringTranslationTrait;

  /**
   * Drupal configuration service container.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * The plugin manager.
   *
   * @var \Drupal\beehotel_pricealterator\PriceAlteratorPluginManager
   */
  protected $priceAlteratorPluginManager;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   * @param \Drupal\Core\Routing\RouteProviderInterface $provider
   *   The route provider.
   * @param \Drupal\beehotel_pricealterator\PriceAlteratorPluginManager $price_alterator_plugin_manager
   *   The tempstore factory.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(ConfigFactory $config_factory, RouteProviderInterface $provider, PriceAlteratorPluginManager $price_alterator_plugin_manager, RendererInterface $renderer, Connection $database, MessengerInterface $messenger) {
    $this->configFactory = $config_factory;
    $this->routeProvider = $provider;
    $this->priceAlteratorPluginManager = $price_alterator_plugin_manager;
    $this->renderer = $renderer;
    $this->database = $database;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('router.route_provider'),
      $container->get('plugin.manager.beehotel.pricealterator'),
      $container->get('renderer'),
      $container->get('database'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'alteratorslist';
  }

  /**
   * Build the tabledrag price alterators form.
   *
   * @param array $form
   *   Render array representing from.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Current form state.
   *
   * @return array
   *   The render array defining the elements of the form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $module_handler = \Drupal::service('module_handler');
    $module_path = $module_handler->getModule('beehotel_pricealterator')->getPath();

    $data = [];
    $data['concept'][] = $this->t('This is the <b>draggable price alterator Table</b> to create you custom price algorithm.') . " ";
    $data['concept'][] = $this->t('A very powelful tool to boost your income and define your business stategy.');
    $data['concept'][] = $this->t('Starting from the price defined in the Unit Weekly Table,') . " ";
    $data['concept'][] = $this->t('Price Alterators are processed following the sorting here defined');
    $data['concept'][] = $this->t("Here's what you can:");
    $data['concept'][] = '<ul>';
    $data['concept'][] = '<li>' . $this->t('<b>Sort alterators</b>: Alterators are processed following order defined in this table. This setting has strong impact of your price algorithm. Change order carefully. Click save at the end of page to confirm the order.');
    $data['concept'][] = '<li>' . $this->t('<b>Enable alterators</b>: Alterators can be enabled at will. Click on settings to enable or disable.');
    $data['concept'][] = '<li>' . $this->t('<b>Quote alterators</b>: Mosto of alterators can be quoted at will. Click on settings see more.');
    $data['concept'][] = '</ul>';
    $data['concept'] = implode("<br/>", $data['concept']);

    $data['usecases'][] = '<ul>';
    $data['usecases'][] = '<li>' . $this->t("Use the unit weekly table. Do now use any price alterator");
    $data['usecases'][] = '<li>' . $this->t("Add extra charge when checkin is on Sunday");
    $data['usecases'][] = '<li>' . $this->t("Add extra charge when a Guest wants the only Saturday night");
    $data['usecases'][] = '<li>' . $this->t("Define a fixed price for a special day");
    $data['usecases'][] = '</ul>';
    $data['usecases'] = implode("<br/>", $data['usecases']);

    $data['howto'][] = $this->t("Drag the alterator up and down as per your business needs and save the sort at the bottom of the page");
    $data['howto'][] = $this->t("Click settings to enable and disable alterators");
    $data['howto'][] = $this->t("Click settings to quote alterators");
    $data['howto'] = implode("<br/>", $data['howto']);

    $data['tips'][] = $this->t("Handling Price Alterators can be confusing at first. Take it easy. Get started disabling every alterator. Add altertors one by one");
    $data['tips'] = implode("<br/>", $data['tips']);

    $data['examples'][] = $this->t("I can't easily manage reservations for the only Saturday night. Add 50€ to reservations for the only Saturday night");
    $data['examples'] = implode("<br/>", $data['examples']);

    $data['info'] = [
      '#theme' => 'beehotel_pricealterator_alterator_info',
      '#concept' => ['#markup' => $data['concept']],
      '#examples' => ['#markup' => $data['examples']],
      '#howto' => ['#markup' => $data['howto']],
      '#title' => "",
      '#tips' => ['#markup' => $data['tips']],
      '#usecases' => ['#markup' => $data['usecases']],
    ];

    $form['info'] = [
      '#type' => 'details',
      '#title' => $this->t('Info'),
      '#description' => $data['info'],
      '#open' => FALSE,
    ];

    $form['alterators'] = [
      '#type' => 'table',
      '#header' => [
        'title' => $this->t('Name'),
        'description' => $this->t('Description'),
        'current_value' => $this->t('Current value'),
        'settings' => $this->t('Settings'),
        // 'enabled' => $this->t('ON'),
        'weight' => $this->t('Weight'),
      ],
      '#empty' => $this->t('Sorry, There are no items!'),
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'table-sort-weight',
        ],
      ],
    ];

    $data['alterators'] = $this->priceAlteratorPluginManager->alterators([]);

    foreach ($data['alterators'] as $id => $values) {

      $tmp = [];

      $tmp['route'] = BEEHOTEL_PRICEALTERATOR_ROUTE_BASE . strtolower($id) . '_settings';
      $tmp['exists'] = count($this->routeProvider->getRoutesByNames([$tmp['route']]));

      $values['enabled'] = $values['enabled'] ?: 0;

      if ($tmp['exists'] == 1) {
        $tmp['url'] = Url::fromRoute(BEEHOTEL_PRICEALTERATOR_ROUTE_BASE . strtolower($id) . '_settings');
        $tmp['args'] = [
          '@p' => $module_path,
          '@e' => $values['enabled'] ?: 0,
          '@class' => strtolower($values['id']),
        ];

        $tmp['settings_icon'] = $this->t(
          '<img class="@class" src="/@p/assets/css/images/gear-solid-@e.svg" alt="Settings">',
          $tmp['args']
        );

        $tmp['settings'] = \Drupal::service('link_generator')->generate($tmp['settings_icon'], $tmp['url']);
      }

      $tmp['config'] = $this->configFactory
        ->getEditable(BEEHOTEL_PRICEALTERATOR_ROUTE_BASE . strtolower($id) . '.settings');

      $tmp['mandatory'] = ($values['type'] == 'mandatory') ? '<span class="mandatory">*</span>' : '';

      $form['alterators'][$values['id']]['#weight'] = $values['get_user_weight'];

      $form['alterators'][$values['id']]['name'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $values['id'],
        '#attributes' => [
          'class' => [
            'name',
            strtolower($values['id']),
            'enabled-' . $values['enabled'],
          ],
        ],
      ];

      $form['alterators'][$values['id']]['description'] = [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $values['description'],
        '#attributes' => [
          'class' => [
            'description',
            'enabled-' . $values['enabled'],
          ],
        ],
      ];

      $form['alterators'][$values['id']]['current_value'] = [
        '#attributes' => [
          'class' => [
            'current-value', strtolower($values['id']),
            'enabled-' . $values['enabled'],
          ],
        ],
        '#tag' => 'p',
        '#type' => 'html_tag',
        '#value' => $values['current_value'],
      ];

      if (!isset($tmp['settings'])) {
        $tmp['settings'] = "---";
      }

      $form['alterators'][$values['id']]['settings'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $tmp['settings'],
        '#attributes' => [
          'class' => [
            'settings',
            strtolower($values['id']),
            'enabled', 'enabled-' . $values['enabled'],
          ],
        ],
      ];

      $form['alterators'][$values['id']]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => $values['id']]),
        '#title_display' => 'invisible',
        '#default_value' => $values['get_user_weight'],
        '#attributes' => ['class' => ['table-sort-weight']],
      ];

      $form['alterators'][$values['id']]['#attributes'] = [
        'class' => [
          strtolower($values['id']),
          'enabled-' . $values['enabled'],
          'draggable',
        ],
      ];
    }

    $form['alterators'] = $form['alterators'];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Sorting'),
    ];
    $form['actions']['cancel'] = [
      '#type' => 'submit',
      '#value'  => 'Cancel',
      '#attributes' => [
        'title' => $this->t('Reset Sorting'),
      ],
      '#submit' => ['::cancel'],
      '#limit_validation_errors' => [],
    ];

    $form['#attached']['library'][] = 'beehotel_pricealterator/pricealterators';
    $form['#attached']['library'][] = 'beehotel_pricealterator/chart-chain';
    $form['#attached']['library'][] = 'beehotel_pricealterator/chart-seasons';

    return $form;
  }

  /**
   * Form submission handler for the 'Return to' action.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function cancel(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('beehotel_pricealterator.info.chain');
  }

  /**
   * Form submission handler for the simple form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $alterators = [];
    foreach ($form_state->getValue('alterators') as $key => $value) {
      $alterators[$key . "_weight"] = $value['weight'];
    }

    $this->normaliseWeight($alterators);

    $this->configFactory
      ->getEditable('beehotel_pricealterator.settings')
      ->set('price_alterators', $alterators)
      ->save();
  }

  /**
   * Set mandatory weight to alterators.
   */
  private function normaliseWeight(&$alterators) {

    // Mandatory alterators in required order at the very bottom of the pile.
    $alterators['PriceFromBaseTable_weight'] = -999;
    $alterators['GetSeason_weight'] = -998;
    $warning = $this->t("Mandatory Alterators 'Price from Base table' and 'Get Season' are weighted by Bee Hotel.");
    $this->messenger->addWarning($warning);

  }

}
