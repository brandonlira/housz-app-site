<?php

namespace Drupal\bee_hotel\Form;

use Drupal\beehotel_utils\BeeHotelCommerce;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure bee_hotel settings.
 */
class BeeHotelSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   */
  const SETTINGS = 'beehotel.settings';

  /**
   * The BeeHotel commerce Util.
   *
   * @var \Drupal\beehotel_utils\BeeHotelCommerce
   */
  protected $beehotelCommerce;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a new BeeHotelSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Config\TypedConfigManagerInterface $typed_config_manager
   *   The typed config manager.
   * @param \Drupal\beehotel_utils\BeeHotelCommerce $beehotel_commerce
   *   BeeHotel Commerce Utils.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   The module extension list.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    $typed_config_manager,
    BeeHotelCommerce $beehotel_commerce,
    ModuleExtensionList $module_extension_list,
    DateFormatterInterface $date_formatter,
    RendererInterface $renderer
  ) {
    parent::__construct($config_factory, $typed_config_manager);
    $this->beehotelCommerce = $beehotel_commerce;
    $this->moduleExtensionList = $module_extension_list;
    $this->dateFormatter = $date_formatter;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('beehotel_utils.beehotelcommerce'),
      $container->get('extension.list.module'),
      $container->get('date.formatter'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'beehotel_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);
    $path = $this->moduleExtensionList->getPath('bee_hotel');

    $form['settings'] = [
      '#type' => 'vertical_tabs',
      '#default_tab' => 'edit-publication',
    ];

    // Main section.
    $form['main'] = [
      '#type' => 'details',
      '#title' => $this->t('Main'),
      '#group' => 'settings',
    ];

    $form['main']['off'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Reservations OFF'),
    ];

    $form['main']['off']['off_value'] = [
      '#default_value' => $config->get('beehotel.off_value'),
      '#type' => 'checkbox',
      '#title' => $this->t('Switch the reservation system OFF'),
      '#description' => $this->t('When OFF, user booking units will receive a message saying the reservation system is OFF'),
    ];

    $form['main']['off']['off_text'] = [
      '#default_value' => $config->get('beehotel.off_text'),
      '#type' => 'textfield',
      '#title' => $this->t('A message to guests, saying reservation system is OFF.'),
      '#description' => $this->t('When OFF, user booking units will receive this message.'),
    ];

    $form['main']['setupmode'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('SETUP mode'),
    ];

    $form['main']['setupmode']['setupmode_on_value'] = [
      '#default_value' => $config->get('beehotel.setup_mode'),
      '#type' => 'checkbox',
      '#title' => $this->t('Switch the SETUP mode ON'),
      '#description' => $this->t('When ON, a deep diagnostic check is made. With SETUP mode ON, your system maybe slower. Disable this once your BEE Hotel environment is working fine'),
    ];

    // Module Weights.
    $weight_bee = bee_hotel_get_module_weight('bee');
    $weight_bee_hotel = bee_hotel_get_module_weight('bee_hotel');

    $weight_status = ($weight_bee <= $weight_bee_hotel)
    ? $this->t("Please update bee_hotel weight")
    : $this->t("Modules weight look good!");

    $details_markup = '<div>' .
    $this->t('Bee HOTEL weight: @w1', ['@w1' => $weight_bee_hotel]) . '<br/>' .
    $this->t('BEE weight: @w2', ['@w2' => $weight_bee]) . '<br/>' .
    $weight_status . '</div>';

    $form['main']['weight'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Module weight'),
      '#description' => $this->t('Bee Hotel weight matters. Check values below'),
    ];

    $form['main']['weight']['update'] = [
      '#prefix' => $details_markup,
      '#type' => 'submit',
      '#value' => $this->t('Update weight'),
      '#submit' => ['::submitUpdateWeight'],
    ];

    // Booking section.
    $form['booking'] = [
      '#type' => 'details',
      '#title' => $this->t('Booking'),
      '#group' => 'settings',
    ];

    $form['booking']['calendar'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Calendar'),
    ];

    $form['booking']['calendar']['calendar_from'] = [
      '#default_value' => $config->get('beehotel.calendar_from'),
      '#type' => 'select',
      '#options' => [
        0 => $this->t('Today'),
        1 => $this->t('Tomorrow'),
        2 => $this->t('2 days'),
      ],
      '#title' => $this->t('Accept reservations from'),
      '#description' => $this->t('Selecting today, you should be ready to checkin Guest with few hours time.'),
    ];

    // Cancellation Policy.
    $cancellation_options = [0 => $this->t('No cancellation policy')];
    for ($i = 1; $i <= 30; $i++) {
      $cancellation_options[$i] = $i;
    }
    foreach ([40, 45, 50, 60] as $day) {
      $cancellation_options[$day] = $day;
    }

    $form['booking']['cancellation_policy'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Cancellation Policy'),
    ];

    $form['booking']['cancellation_policy']['cancellation_policy_days'] = [
      '#type' => 'select',
      '#title' => $this->t('Cancellation Policy Days'),
      '#options' => $cancellation_options,
      '#default_value' => $config->get('beehotel.cancellation_policy_days') ?? 0,
      '#required' => TRUE,
    ];

    // UI Settings.
    $form['ui_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('UI Settings'),
      '#group' => 'settings',
    ];

    $form['ui_settings']['bybeehotel_promo_enabled'] = [
      '#default_value' => $config->get('beehotel.bybeehotel_promo_enabled'),
      '#type' => 'checkbox',
      '#title' => $this->t('Enable byBeeHotel promo'),
    ];

    // Price Alteration.
    $form['price_alteration'] = [
      '#type' => 'details',
      '#title' => $this->t('Price Alteration'),
      '#group' => 'settings',
    ];

    $form['price_alteration']['chain_chart'] = [
      '#type' => 'radios',
      '#title' => $this->t('Chain chart'),
      '#default_value' => $config->get('beehotel.chain_chart'),
      '#options' => [
        'pie' => $this->t('Pie'),
        'combochart' => $this->t('Combo Chart'),
      ],
    ];

    // Date and Time.
    $form['dateandtime'] = [
      '#type' => 'details',
      '#title' => $this->t('Date and time'),
      '#group' => 'settings',
    ];

    $time_options = [];
    for ($hour = 0; $hour < 24; $hour++) {
      for ($minute = 0; $minute < 60; $minute += 30) {
        $time_value = sprintf('%02d:%02d', $hour, $minute);
        $time_display = $this->dateFormatter->format(strtotime($time_value), 'custom', 'g:i A');
        $time_options[$time_value] = $time_display;
      }
    }

    $form['dateandtime']['default_checkin_time'] = [
      '#type' => 'select',
      '#title' => $this->t('Default check-in time'),
      '#options' => $time_options,
      '#default_value' => $config->get('beehotel.dateandtime.default_checkin_time') ?: '14:00',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $cancellation_days = $form_state->getValue('cancellation_policy_days');
    $valid_days = array_merge(range(0, 30), [40, 45, 50, 60]);

    if (!in_array($cancellation_days, $valid_days)) {
      $form_state->setErrorByName('cancellation_policy_days', $this->t('The value is not valid.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config(static::SETTINGS)
      ->set('beehotel.off_value', $form_state->getValue('off_value'))
      ->set('beehotel.off_text', $form_state->getValue('off_text'))
      ->set('beehotel.setup_mode', $form_state->getValue('setupmode_on_value'))
      ->set('beehotel.calendar_from', $form_state->getValue('calendar_from'))
      ->set('beehotel.cancellation_policy_days', $form_state->getValue('cancellation_policy_days'))
      ->set('beehotel.bybeehotel_promo_enabled', (bool) $form_state->getValue('bybeehotel_promo_enabled'))
      ->set('beehotel.dateandtime.default_checkin_time', $form_state->getValue('default_checkin_time'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Update modules weight.
   */
  public function submitUpdateWeight(array &$form, FormStateInterface $form_state) {
    bee_hotel_update_modules_weight();
    $this->messenger()->addStatus($this->t('Modules weight updated'));
  }

}
