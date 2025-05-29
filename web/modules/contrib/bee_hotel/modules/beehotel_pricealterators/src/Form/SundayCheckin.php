<?php

namespace Drupal\beehotel_pricealterators\Form;

use Drupal\beehotel_utils\BeeHotelCommerce;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure SundayCheckin Alterator.
 */
class SundayCheckin extends ConfigFormBase {


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
    return 'SundayCheckin';
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
      '#title' => $this->t('Sunday check-in'),
      '#collapsible' => FALSE,
    ];

    $form['enabled'] = [
      '#default_value' => $config->get('enabled'),
      '#type' => 'checkbox',
      '#title' => $this->t('Enable this Alterator'),
      '#description' => $this->t('When on, this Alterator will be applied the BEEHotel prices'),
    ];

    $form['increase'] = [
      '#default_value' => $config->get('increase'),
      '#type' => 'textfield',
      '#title' => $this->t('@symbol to add for checkins happening on Sunday', ['@symbol' => $this->beehotelCommerce->currentStoreCurrency()->get('symbol')]),
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
      ->set('increase', $form_state->getValue('increase'))
      ->save();
    parent::submitForm($form, $form_state);
    $form_state->setRedirect('beehotel_pricealterator.info.chain');
  }

}
