<?php

namespace Drupal\beehotel_pricealterator\Form;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure GetSeason Alterator.
 */
class GetSeason extends ConfigFormBase {

  use StringTranslationTrait;

  /**
   * Drupal configuration service container.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(
      ConfigFactory $config_factory,
      RendererInterface $renderer,
    ) {
    $this->configFactory = $config_factory;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('renderer'),
    );
  }

  /**
   * Reference to the Alterator (as plugin).
   *
   *   This value matches the ID in the @PriceAlterator annotation.
   */
  public function pluginId() {
    return 'GetSeason';
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

    $config = $this->config($this->configName());

    $form = [
      '#type' => 'fieldset',
      '#title' => $this->t('Get Season alterator'),
      '#collapsible' => TRUE,
    ];

    $data = [];
    $data['concept'][] = $this->t("This Alterator assigns a seasonal price to a night request.");
    $data['concept'][] = $this->t("Seasonal price is unit-based, set in the Price Table link available for beehotel units.");
    $data['concept'][] = $this->t("As every price alterator, this overrides prices set elsewhere via BAT, Commerce, BEE settings.");
    $data['concept'][] = $this->t("This alterator is meant to be on the top of your price algorithm, with the minimum weight in the Algorithm list page, always before the PriceFromBaseTable alterator.", ["@PriceFromBaseTable" => "<a href='#'>PriceFromBaseTable</a>."]);
    $data['concept'] = implode("<br/>", $data['concept']);
    $data['concept'] = $data['concept'];

    $data['usecases'][] = $this->t("Give a fixed price to a given unit for a defined time range");
    $data['usecases'][] = $this->t("I have a unit on the seaside in Spain. August is peak, from January till March is low season");
    $data['usecases'][] = $this->t("I have a unit in Florence, Italy. From April till June and from September to October is peak. January and February are low");
    $data['usecases'] = implode("<br/>", $data['usecases']);

    $data['howto'][] = $this->t("Input is to be sent as JSON array.");
    $data['howto'][] = $this->t("Supported seasons: Low, High, Peak.");
    $data['howto'][] = $this->t("Time ranges are not to be overlaped.");
    $data['howto'][] = $this->t("Input is not validated. Do a validation test of JSON configuration before pasting here.");
    $data['howto'] = implode("<br/>", $data['howto']);

    $data['tips'][] = $this->t("Same season may be repeated more times along the year.");
    $data['tips'] = implode("<br/>", $data['tips']);

    $data['examples'][] = $this->t("Mountains can be peak season on both winter and summer");
    $data['examples'][] = $this->t("So, my peak season is from begin movember till New years eve and from July till mid August.");
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

    $form['enabled'] = [
      '#default_value' => $config->get('enabled'),
      '#type' => 'checkbox',
      '#title' => $this->t('Enable this alterator'),
      '#description' => $this->t('This altertor is mandatory'),
    ];

    // See beehotel_pricealterator_preprocess().
    $seasons = [
      '#theme' => 'beehotel_pricealterator_seasons',
      '#low' => [],
      '#high' => [],
      '#peak' => [],
    ];

    $form['chart'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => $this->renderer->renderPlain($seasons),
    ];

    $now = new DrupalDateTime('now');

    $json_seasons_example_long = '<code><pre>{
    "seasons": {
        "fallback": "high",
        "range": {
          "low": {
            "0": {
              "from": "' . $now->format("Y") . '-01-06T13:33:03.969Z",
              "to": "' . $now->format("Y") . '-03-15T13:33:03.969Z"
            },
            "1": {
              "from": "' . $now->format("Y") . '-07-30T13:33:03.969Z",
              "to": "' . $now->format("Y") . '-08-24T13:33:03.969Z"
            },
            "2": {
              "from": "' . $now->format("Y") . '-10-21T13:33:03.969Z",
              "to": "' . $now->format("Y") . '-10-24T13:33:03.969Z",
            }
          },
          "high": {
            "0": {
              "from": "' . $now->format("Y") . '-03-16T13:33:03.969Z",
              "to": "' . $now->format("Y") . '-04-29T13:33:03.969Z"
            },
            "1": {
              "from": "' . $now->format("Y") . '-07-05T13:33:03.969Z",
              "to": "' . $now->format("Y") . '-07-29T13:33:03.969Z"
            },
            "2": {
              "from": "' . $now->format("Y") . '-10-05T13:33:03.969Z",
              "to": "' . $now->format("Y") . '-10-20T13:33:03.969Z"
            }
          },
          "peak": {
            "0": {
              "from": "' . $now->format("Y") . '-04-30T13:33:03.969Z",
              "to": "' . $now->format("Y") . '-07-04T13:33:03.969Z"
            },
            "1":  {
              "from": "' . $now->format("Y") . '-08-25T13:33:03.969Z",
              "to": "' . $now->format("Y") . '-10-04T13:33:03.969Z"
            }
          }
       }
     }
    }</code></pre>';

    $json_seasons_example_short = '<code><pre>{
    "seasons": {
        "fallback": "high",
        "range": {
            "low": {},
            "high": {
                "0": {
                    "from": "' . $now->format("Y") . '-01-01T00:00:00.000Z",
                    "to": "' . $now->format("Y") . '-12-31T23:59:59.999Z"
                }
            },
            "peak": {}
        }
    }
  }</code></pre>';

    $form['seasons'] = [
      '#default_value' => $config->get('seasons'),
      '#type' => 'textarea',
      '#title' => $this->t('Seasons as JSON'),
      '#rows' => 25,
      '#weight' => 998,
    ];

    $form['example'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Seasons Example'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#weight' => 999,
    ];

    $form['example']['value'] = [
      // '#description' => $json_seasons_example_long,
      '#description' => $json_seasons_example_short,
      '#type' => 'item',
      '#title' => FALSE,
      '#rows' => 25,
    ];

    $form['#attributes'] = ['class' => ['beehotel-pricealterator']];
    $form['#attached']['library'][] = 'beehotel_pricealterator/pricealterator';
    $form['#attached']['library'][] = 'beehotel_pricealterator/pricealterators';
    $form['#attached']['library'][] = 'beehotel_pricealterator/chart-seasons';

    return parent::buildForm($form, $form_state);

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable($this->configName());
    $config
      ->set('enabled', $form_state->getValue('enabled'))
      ->set('seasons', $form_state->getValue('seasons'))
      ->save();
    parent::submitForm($form, $form_state);

    $this->messenger()->addStatus($this->t('Configuration saved'));

    $form_state->setRedirect('beehotel_pricealterator.info.chain');
  }

}
