<?php

namespace Drupal\beehotel_pricealterators\Form;

use Drupal\beehotel_pricealterators\PriceAlteratorsSettings;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure ConsecutiveNights Alterator.
 */
class ConsecutiveNights extends ConfigFormBase {

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
   * Constructs a new ConsecutiveNights alterator object.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(ConfigFactory $config_factory, RendererInterface $renderer) {
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
    return 'ConsecutiveNights';
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

    $config = $this->configFactory->get($this->configName());
    $maxConsecutiveNights = PriceAlteratorsSettings::MaxConsecutiveNights->value;
    $pricePreviewBase = PriceAlteratorsSettings::PricePreviewBase->value;

    $form = [
      '#type' => 'fieldset',
      '#title' => $this->t('Consecutive Nights'),
      '#collapsible' => FALSE,
    ];

    $form['enabled'] = [
      '#default_value' => $config->get('enabled'),
      '#type' => 'checkbox',
      '#title' => $this->t('Enable this alterator'),
      '#description' => $this->t('When on, this alterator will be applied the BEEHotel prices'),
    ];

    $data = [];
    $data['concept'][] = $this->t("ConsecutiveNights price Alterator will adjust the price to the number of consecutive nights.");
    $data['concept'] = implode("<br/>", $data['concept']);

    $data['howto'][] = $this->t("The ConsecutiveNights setting page has a slider for every further night.");
    $data['howto'][] = $this->t("Keep the increase to 0 till the minimal occupancy for the given unit.");
    $data['howto'][] = $this->t("Add desidered increase for every further night.");
    $data['howto'] = implode("<br/>", $data['howto']);

    $data['usecases'][] = '<ul><li>' . $this->t("Limit or avoid  reservation with a too short number of nights.");
    $data['usecases'][] = '<li>' . $this->t("Grant a minimum of nights for your reservations.");
    $data['usecases'][] = '</ul>';
    $data['usecases'] = implode("<br/>", $data['usecases']);

    $data['examples'][] = $this->t("I prefer Hosts booking for 3 nights-long reservations or longer");
    $data['examples'][] = $this->t("This is how I set up the Consecutive Nights price alterator:");
    $data['examples'][] = $this->t("1 night: +60%");
    $data['examples'][] = $this->t("2 nights: +60%");
    $data['examples'][] = $this->t("3 nights 0% (neutral)");
    $data['examples'][] = $this->t("4 nights  -10% (neutral)");
    $data['examples'] = implode("<br/>", $data['examples']);

    $data['tips'][] = $this->t("Use this alternator with positive (discourage Guest) or negative (attract Guest) variations");
    $data['tips'] = implode("<br/>", $data['tips']);

    $data['info'] = [
      '#theme' => 'beehotel_pricealterator_alterator_info',
      '#concept' => ['#markup' => $data['concept']],
      '#usecases' => ['#markup' => $data['usecases']],
      '#howto' => ['#markup' => $data['howto']],
      '#tips' => ['#markup' => $data['tips']],
      '#examples' => ['#markup' => $data['examples']],
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
      '#description' => $this->t('When on, this alterator will be applied the BEEHotel prices'),
    ];

    for ($i = 1; $i < $maxConsecutiveNights; $i++) {
      $title = $this->formatPlural($i, '1 night.', '@count nights.');

      $description = $this->formatPlural($i, 'Percentage alteration for 1 night.', 'Percentage alteration for @count nights.');

      $description = "";

      $previewValue = ($pricePreviewBase / 100 * $config->get('increase_' . $i) + $pricePreviewBase) * $i;

      $tmp = [
        '#theme' => 'beehotel_pricealterators_consecutivenights_preview',
        '#average' => $previewValue / $i,
        '#base' => $pricePreviewBase,
        '#nights' => $i,
        '#total' => $previewValue,
      ];

      $previewLabel = $this->renderer->render($tmp);

      $form['increase'][$i] = [
        '#default_value' => $config->get('increase_' . $i),
        '#type' => 'range_slider',
        '#title' => $title,
        '#min' => -100,
        '#max' => 100,
        '#step' => 1,
        '#description' => $description . $previewLabel,
        '#data-orientation' => 'horizontal',
        '#output' => 'below',
        '#output__field_prefix' => '',
        '#output__field_suffix' => '%',
      ];
    }

    $form['#attributes'] = ['class' => ['beehotel-pricealterator']];
    $form['#attached']['library'][] = 'beehotel_pricealterator/pricealterator';
    $form['#attached']['library'][] = 'beehotel_pricealterators/alterator-consecutivenights';

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $maxConsecutiveNights = PriceAlteratorsSettings::MaxConsecutiveNights->value;
    $config = $this->configFactory->getEditable($this->configName());

    $config
      ->set('enabled', $form_state->getValue('enabled'))
      ->save();

    for ($i = 1; $i < $maxConsecutiveNights; $i++) {
      $config
        ->set('increase_' . $i, $form_state->getValue($i))
        ->save();
    }

    parent::submitForm($form, $form_state);
    $form_state->setRedirect('beehotel_pricealterator.info.chain');

  }

}
