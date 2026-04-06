<?php

namespace Drupal\beehotel_pricealterators\Form;

use Drupal\beehotel_utils\BeeHotelCommerce;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for the Check-in Time Price Alterator.
 *
 * @package Drupal\beehotel_pricealterators\Form
 */
class CheckinTime extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The BeeHotel commerce utility.
   *
   * @var \Drupal\beehotel_utils\BeeHotelCommerce
   */
  protected $beehotelCommerce;

  /**
   * Constructs a new CheckinTime configuration form.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   * The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config
   * The typed configuration manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * The entity type manager.
   * @param \Drupal\beehotel_utils\BeeHotelCommerce $beehotel_commerce
   * The BeeHotel commerce utility.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config,
    EntityTypeManagerInterface $entity_type_manager,
    BeeHotelCommerce $beehotel_commerce
  ) {
    parent::__construct($config_factory, $typed_config);
    $this->entityTypeManager = $entity_type_manager;
    $this->beehotelCommerce = $beehotel_commerce;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('entity_type.manager'),
      $container->get('beehotel_utils.beehotelcommerce')
    );
  }

  /**
   * Gets the plugin ID based on the class name.
   *
   * @return string
   * The plugin ID.
   */
  public function pluginId() {
    $class_parts = explode("\\", __CLASS__);
    return end($class_parts);
  }

  /**
   * Gets the configuration name for this plugin.
   *
   * @return string
   * The configuration name.
   */
  public function configName() {
    return 'beehotel_pricealterator.pricealterator.' . $this->pluginId() . '.settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'beehotel_pricealterator_checkin_time_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [$this->configName()];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config($this->configName());
    $time_slots = $config->get('time_slots') ?: [];

    // Convert stored array back to textarea string for editing.
    $textarea_value = '';
    foreach ($time_slots as $slot) {
      $label = !empty($slot['label']) ? '|' . $slot['label'] : '';
      $textarea_value .= $slot['start'] . '-' . $slot['end'] . '|' . $slot['adjustment'] . $label . "\n";
    }

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable this Alterator'),
      '#default_value' => $config->get('enabled'),
      '#description' => $this->t('When enabled, price will vary based on selected check-in time in the booking form.'),
    ];

    $form['display_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Form Display Settings'),
      '#open' => TRUE,
    ];

    $form['display_settings']['display_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Select Field Title'),
      '#default_value' => $config->get('display_label') ?: $this->t('Check-in Time'),
      '#description' => $this->t('The label shown to the user in the reservation form.'),
      '#required' => TRUE,
    ];

    $form['display_settings']['options_pattern'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Options Display Pattern'),
      '#default_value' => $config->get('options_pattern') ?: '[label] ([start] - [end]) [[adjustment]]',
      '#description' => $this->t('Available placeholders: <code>[label], [start], [end], [adjustment]</code>'),
      '#required' => TRUE,
    ];

    $currency_symbol = $this->beehotelCommerce->currentStoreCurrency()->get('symbol');

    $form['time_slots'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Time Slots Configuration'),
      '#default_value' => trim($textarea_value),
      '#rows' => 10,
      '#description' => $this->t('Enter one time slot per line in the format: <strong>start-end|adjustment|label</strong> (Adjustment in @currency)', ['@currency' => $currency_symbol]),
      '#required' => FALSE,
    ];

    // Visual examples for the user.
    $form['example'] = [
      '#type' => 'details',
      '#title' => $this->t('Example Configuration'),
      '#open' => FALSE,
    ];

    $example_content = "14:00-18:00|0|Standard check-in\n18:00-20:00|20|Late check-in\n20:00-22:00|40|Very late check-in\n22:00-23:59|60|After hours";

    $form['example']['example_text'] = [
      '#type' => 'textarea',
      '#value' => $example_content,
      '#rows' => 5,
      '#disabled' => TRUE,
      '#description' => $this->t('Copy and paste this example into the configuration field above.'),
    ];

    $form['#attributes'] = ['class' => ['beehotel-pricealterator-settings']];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $time_slots_text = $form_state->getValue('time_slots');
    if (empty($time_slots_text)) {
      return;
    }

    $lines = explode("\n", str_replace("\r", "", $time_slots_text));
    foreach ($lines as $i => $line) {
      $line = trim($line);
      if (empty($line)) {
        continue;
      }

      $parts = explode('|', $line);
      if (count($parts) < 2) {
        $form_state->setErrorByName('time_slots', $this->t('Line @n: Format must be start-end|adjustment|label', ['@n' => $i + 1]));
        continue;
      }

      $times = explode('-', $parts[0]);
      if (count($times) !== 2) {
        $form_state->setErrorByName('time_slots', $this->t('Line @n: Range must be HH:MM-HH:MM', ['@n' => $i + 1]));
      }

      if (!is_numeric($parts[1])) {
        $form_state->setErrorByName('time_slots', $this->t('Line @n: Adjustment must be a numeric value.', ['@n' => $i + 1]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $time_slots = [];
    $lines = explode("\n", str_replace("\r", "", $form_state->getValue('time_slots')));

    foreach ($lines as $line) {
      $line = trim($line);
      if (empty($line)) {
        continue;
      }

      $parts = explode('|', $line);
      $times = explode('-', $parts[0]);

      $time_slots[] = [
        'start' => trim($times[0]),
        'end' => trim($times[1]),
        'adjustment' => (float) $parts[1],
        'label' => isset($parts[2]) ? trim($parts[2]) : '',
      ];
    }

    $this->config($this->configName())
      ->set('enabled', $form_state->getValue('enabled'))
      ->set('display_label', $form_state->getValue('display_label'))
      ->set('options_pattern', $form_state->getValue('options_pattern'))
      ->set('time_slots', $time_slots)
      ->save();

    parent::submitForm($form, $form_state);
    $this->messenger()->addStatus($this->t('Check-in Time configuration saved successfully.'));

    // Optional redirect to the alterators overview.
    $form_state->setRedirect('beehotel_pricealterator.info.chain');
  }

}
