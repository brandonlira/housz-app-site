<?php

namespace Drupal\beehotel_pricealterators\Form;

use Drupal\beehotel_utils\BeeHotelCommerce;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure OneNightOnly Alterator.
 */
class OneNightOnly extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The price Alterator Base.
   *
   * @var \Drupal\beehotel_pricealterator\PriceAlteratorBase
   */
  protected $priceAlteratorBase;

  /**
   * The BeeHotel commerce Util.
   *
   * @var \Drupal\beehotel_utils\BeeHotelCommerce
   */
  protected $beehotelCommerce;

  /**
   * Constructs a new OrderSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\beehotel_utils\BeeHotelCommerce $beehotel_commerce
   *   BeeHotel Commerce Utils.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, BeeHotelCommerce $beehotel_commerce) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
    $this->beehotelCommerce = $beehotel_commerce;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('beehotel_utils.beehotelcommerce')
    );
  }

  /**
   * Reference to the Alterator (as plugin).
   *
   *   This value matches the ID in the @PriceAlterator annotation.
   */
  public function pluginId() {
    return 'OneNightOnly';
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
      '#title' => $this->t('One Night Only'),
      '#collapsible' => FALSE,
    ];

    $form['enabled'] = [
      '#default_value' => $config->get('enabled'),
      '#type' => 'checkbox',
      '#title' => $this->t('Enable this alterator'),
      '#description' => $this->t('When on, this alterator will be applied the BEEHotel prices'),
    ];

    $tmp = $this->t("Bee Hotel units Prices will be altered as per this percentage value");

    $form['increase']['percentage'] = [
      '#default_value' => $config->get('percentage'),
      '#type' => 'range_slider',
      '#title' => 'Percentage alteration',
      '#min' => -100,
      '#max' => 100,
      '#step' => 1,
      '#description' => $tmp,
      '#data-orientation' => 'horizontal',
      '#output' => 'below',
      '#output__field_prefix' => '',
      '#output__field_suffix' => '%',
    ];

    $tmp = $this->t("<b>Important:</b> This fixed value overrides above percentage value. Set this value to 0 to apply the percentage value");

    $form['increase']['fixed'] = [
      '#default_value' => $config->get('fixed'),
      '#type' => 'number',
      '#min' => 0,
      '#max' => 999,
      '#step' => 1,
      '#title' => $this->t('@symbol to add to one night only reservations', ['@symbol' => $this->beehotelCommerce->currentStoreCurrency()->get('symbol')]),
      '#description' => $tmp,
    ];

    $form['#attributes'] = ['class' => ['beehotel-pricealterator']];
    $form['#attached']['library'][] = 'beehotel_pricealterator/pricealterator';

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
