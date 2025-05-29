<?php

namespace Drupal\beehotel_pricealterators\Form;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Occupants Alterator.
 */
class Occupants extends ConfigFormBase {

  use StringTranslationTrait;

  /**
   * Drupal configuration service container.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * Constructs a new Occupants alterator object.
   */
  public function __construct(ConfigFactory $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
    );
  }

  /**
   * Reference to the Alterator (as plugin).
   *
   *   This value matches the ID in the @PriceAlterator annotation.
   */
  public function pluginId() {
    return 'Occupants';
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

    $form = [
      '#type' => 'fieldset',
      '#title' => $this->t('Occupants'),
      '#collapsible' => FALSE,
    ];

    $form['enabled'] = [
      '#default_value' => $config->get('enabled'),
      '#type' => 'checkbox',
      '#title' => $this->t('Enable this alterator'),
      '#description' => $this->t('When on, this alterator will be applied the BEEHotel prices'),
    ];

    $data = [];
    $data['concept'][] = $this->t("Occupants price Alterator will adjust the price to the number of people occupying the unit.");
    $data['concept'] = implode("<br/>", $data['concept']);

    $data['howto'][] = $this->t("The Occupants setting page has a slider for every further occupant.");
    $data['howto'][] = $this->t("Keep the increase to 0 till the minimal occupancy for the given unit.");
    $data['howto'][] = $this->t("Add desidered increase for every further occupant.");
    $data['howto'] = implode("<br/>", $data['howto']);

    $data['usecases'][] = '<ul><li>' . $this->t("Give solo-travellers tailored offers when you don't have single units.");
    $data['usecases'][] = '<li>' . $this->t("Increase your income when more people occupy your unit.");
    $data['usecases'][] = '</ul>';
    $data['usecases'] = implode("<br/>", $data['usecases']);

    $data['examples'][] = $this->t("Every hotel's room of mine has a minumum occupancy of two people to be charged. One person pays as two. My rooms can fit up to 3 people.");
    $data['examples'][] = $this->t("This is how I set up the Occupancy price alterator:");
    $data['examples'][] = $this->t("1 people: 0");
    $data['examples'][] = $this->t("2 people: 0");
    $data['examples'][] = $this->t("3 people +30%");
    $data['examples'] = implode("<br/>", $data['examples']);

    $data['tips'][] = $this->t("The increase for further person should not be higher than 50% than preceding.");
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

    for ($i = 1; $i <= 6; $i++) {
      $title = $this->formatPlural($i, '1 occupant.', '@count occupants.');
      $description = $this->formatPlural($i, 'Percentage alteration for 1 occupant.', 'Percentage alteration for @count occupants.');

      $form['increase'][$i] = [
        '#default_value' => $config->get('increase_' . $i),
        '#type' => 'range_slider',
        '#title' => $title,
        '#min' => 0,
        '#max' => 100,
        '#step' => 1,
        '#description' => $description,
        '#data-orientation' => 'horizontal',
        '#output' => 'below',
        '#output__field_prefix' => '',
        '#output__field_suffix' => '%',
      ];
    }

    $form['#attributes'] = ['class' => ['beehotel-pricealterator']];
    $form['#attached']['library'][] = 'beehotel_pricealterator/pricealterator';

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable($this->configName());
    // @todo loop by the max Guests attribute
    $config
      ->set('enabled', $form_state->getValue('enabled'))
      ->set('increase_1', $form_state->getValue('1'))
      ->set('increase_2', $form_state->getValue('2'))
      ->set('increase_3', $form_state->getValue('3'))
      ->set('increase_4', $form_state->getValue('4'))
      ->set('increase_5', $form_state->getValue('5'))
      ->set('increase_6', $form_state->getValue('6'))
      ->save();
    parent::submitForm($form, $form_state);
    $form_state->setRedirect('beehotel_pricealterator.info.chain');
  }

}
