<?php

namespace Drupal\beehotel_pricealterator\Form;

use Drupal\beehotel_utils\BeeHotelUnit;
use Drupal\beehotel_pricealterator\Util;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provide a table form for the base price.
 */
class UnitBasePriceTable extends FormBase {

  /**
   * The bee hotel unit.
   *
   * @var \Drupal\beehotel_pricealterator\Util
   */
  private $beehotelPricealteratorUtil;

  /**
   * Drupal configuration service container.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The bee hotel unit.
   *
   * @var \Drupal\beehotel_utils\BeeHotelUnit
   */
  private $beehotelUnit;

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\beehotel_pricealterator\Util $beehotel_pricealterator_util
   *   The BeeHotel Pricealtertor Utility.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\beehotel_utils\BeeHotelUnit $bee_hotel_unit
   *   The BeeHotel Unit Utility.
   */
  public function __construct(
      Util $beehotel_pricealterator_util,
      ConfigFactory $config_factory,
      EntityTypeManagerInterface $entity_type_manager,
      BeeHotelUnit $bee_hotel_unit
    ) {
    $this->beehotelPricealteratorUtil = $beehotel_pricealterator_util;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->beehotelUnit = $bee_hotel_unit;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('beehotel_pricealterator.util'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('beehotel_utils.beehotelunit'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bee_hotel_unit_base_price_table_form';
  }

  /**
   * The _title_callback .
   */
  public function title(Node $node) {
    return $this->t('Price table for %label', ['%label' => $node->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {

    $data = [];
    $data['beehotel_pricealterator']['config'] = $this->configFactory->getEditable('beehotel_pricealterator.settings');
    $data['bee_hotel']['config'] = $this->configFactory->getEditable('beehotel.settings');
    $data['bee_hotel']['config']->set('unit_reservation_form_disabled', FALSE)->save();

    if ($data['bee_hotel']['config']->get('beehotel.setup_mode') == 1) {
      // @todo Jan254, not working.
      // $this->beehotelUnit->checkBeeHotelSetup($node);
    }

    $data['days'] = $this->beehotelPricealteratorUtil->days();

    $data['currency_code'] = $this->beehotelUnit->getNodeCurrency($node);

    $form['node'] = [
      '#type' => 'hidden',
      '#value' => $node->id(),
    ];

    $tmp = $this->t("Base prices for a given unit, a given day, a given season.") . " ";
    $tmp .= $this->t("If no price alterator is active, Bee Hotel offers to Guest price here exposed.");

    // Add headers.
    $form['basepricetable'] = [
      '#type' => 'table',
      '#title' => $this->t('Unit base price table'),
      '#header' => [
        $this->t('Day'),
        $this->t('Low season'),
        $this->t('High season'),
        $this->t('Peak season'),
      ],
      '#prefix' => $tmp,
    ];

    $tmp = $this->t("Base price per unit per night per 1 person.") . " ";
    $tmp .= $this->t("Use price_alterators to increase prise when request is for more than 1 person.");

    foreach ($data['days'] as $code => $label) {
      $form['basepricetable'][$code]['label'] = [
        '#type' => 'label',
        '#title' => $label,
        '#description' => $tmp,
      ];

      $form['basepricetable'][$code]['low'] = [
        '#default_value' => $data['beehotel_pricealterator']['config']->get($node->id() . "_" . $code . "_low"),
        '#min' => 0.01,
        '#required' => TRUE,
        '#step' => 0.01,
        '#suffix' => $data['currency_code'],
        '#title' => $label,
        '#title_display' => 'invisible',
        '#type' => 'number',
      ];

      $form['basepricetable'][$code]['high'] = [
        '#default_value' => $data['beehotel_pricealterator']['config']->get($node->id() . "_" . $code . "_high"),
        '#min' => 0.01,
        '#required' => TRUE,
        '#step' => 0.01,
        '#suffix' => $data['currency_code'],
        '#title' => $label,
        '#title_display' => 'invisible',
        '#type' => 'number',
      ];

      $form['basepricetable'][$code]['peak'] = [
        '#default_value' => $data['beehotel_pricealterator']['config']->get($node->id() . "_" . $code . "_peak"),
        '#min' => 0.01,
        '#required' => TRUE,
        '#step' => 0.01,
        '#suffix' => $data['currency_code'],
        '#title' => $label,
        '#title_display' => 'invisible',
        '#type' => 'number',
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update'),
    ];

    $form['#attached']['library'][] = 'beehotel_pricealterator/unitbasepricetable';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $data['beehotel_pricealterator']['config'] = $this->configFactory->getEditable('beehotel_pricealterator.settings');
    $values = $form_state->getValues();
    foreach ($values['basepricetable'] as $day => $val) {
      foreach ($val as $sea => $pri) {
        $data['beehotel_pricealterator']['config']->set($values['node'] . "_" . $day . "_" . $sea, $pri)->save();
      }
    }

  }

}
