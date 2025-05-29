<?php

namespace Drupal\bee_hotel\Form;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure bee_hotel settings.
 */
class BeeHotelSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'beehotel.settings';

  /**
   * Drupal configuration service container.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
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

    $config = $this->config('beehotel.settings');
    $path = \Drupal::service('extension.list.module')->getPath('bee_hotel');

    // Vertical Tabs.
    $form['settings'] = [
      '#type' => 'vertical_tabs',
      '#default_tab' => 'edit-publication',
    ];

    // A. Main.
    $form['main'] = [
      '#type' => 'details',
      '#title' => $this->t('Main'),
      '#group' => 'settings',
    ];

    $form['main']['off'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Reservations OFF'),
      '#collapsible' => FALSE,
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
      '#collapsible' => FALSE,
    ];

    $form['main']['setupmode']['setupmode_on_value'] = [
      '#default_value' => $config->get('beehotel.setup_mode'),
      '#type' => 'checkbox',
      '#title' => $this->t('Switch the SETUP mode ON'),
      '#description' => $this->t('When ON, a deep diagnostic check is made. With SETUP mode ON, your system maybe slower. Disable this once your BEE Hotel enviroment is working fine'),
    ];

    $weight = [
      'bee' => bee_hotel_get_module_weight('bee'),
      'bee_hotel' => bee_hotel_get_module_weight('bee_hotel'),
    ];

    $details = "<div>";
    $details .= "Bee HOTEL weight: " . $weight['bee_hotel'] . "<br/>";
    $details .= "BEE weight: " . $weight['bee'] . "<br/>";

    if ($weight['bee'] <= $weight['bee_hotel']) {
      $weight['status'] = $this->t("Please update bee_hotel weight");
    }
    else {
      $weight['status'] = $this->t("Modules weight look good!");
    }

    // Book this unit.
    $details .= $weight['status'];
    $details .= "</div>";

    $form['main']['weight'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Module weight'),
      '#description' => $this->t('Bee Hotel weight matters. Check values below'),
      '#collapsible' => FALSE,
    ];

    $form['main']['weight']['update'] = [
      '#prefix' => $details,
      '#type' => 'submit',
      '#value' => $this->t('Update weight'),
      '#submit' => ['::submitUpdateweight'],
    ];

    // B. Booking.
    $form['booking'] = [
      '#type' => 'details',
      '#title' => $this->t('Booking'),
      '#group' => 'settings',
    ];

    $form['booking']['calendar'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Calendar'),
      '#collapsible' => FALSE,
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
      '#description' => $this->t('Selecting today, you should be ready to checkin Guest with few hours time. This feature is currently @TODO'),
    ];

    $form['booking']['booking_forms'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Booking Forms'),
      '#description' => $this->t("Custom settings for booking forms"),
      '#collapsible' => FALSE,
    ];

    $description = [];

    $description['pieces'][] = $this->t("The 'Units Search' form allows Guests to search for units with given dates and occupants.");
    $description['pieces'][] = $this->t("Options:");
    $description['pieces'][] = $this->t('<code><<i>none</i>></code> : nothing');
    $description['pieces'][] = $this->t('<code><<i>ct-label</i>></code> : the Content type label');
    $description['pieces'][] = $this->t('<title><<i>title</i>></code> : the node Title');
    $description['pieces'][] = $this->t("A 'String': the 'String' itself");
    $description['output'] = implode("<br/>", $description['pieces']);

    // Units search.
    $form['booking']['booking_forms']['units_search'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Units Search'),
      '#description' => $description['output'],
      '#collapsible' => FALSE,
    ];

    $form['booking']['booking_forms']['units_search']['units_search_position'] = [
      '#default_value' => $config->get('beehotel.units_search_position'),
      '#options' => [
        'none' => $this->t("none"),
        'top' => $this->t("Top"),
        'bottom' => $this->t("Bottom"),
      ],
      '#type' => 'select',
      '#title' => $this->t('Position'),
      '#required' => TRUE,
      '#description' => $this->t('Position inside the node display. @todo: code for the top and bottom position'),
    ];

    $form['booking']['booking_forms']['units_search']['units_search_submit_label'] = [
      '#default_value' => $config->get('beehotel.units_search_submit'),
      '#type' => 'textfield',
      '#title' => $this->t('Submit label'),
      '#required' => TRUE,
      '#description' => $this->t('The label of the submit button'),
    ];

    $form['booking']['booking_forms']['units_search']['units_search_header_label'] = [
      '#default_value' => $config->get('beehotel.units_search_header'),
      '#type' => 'textfield',
      '#title' => $this->t('Header label'),
      '#description' => $this->t('A string introducing the form'),
    ];

    $description = [];

    $description['pieces'][] = $this->t("The 'Book this unit' form allows guests to book a given BeeHotelUnit");
    $description['pieces'][] = $this->t("The Form is exposed in the node view of the Unit");
    $description['pieces'][] = $this->t("You can here decide how this Form is introducted to the audience");
    $description['pieces'][] = $this->t("Options:");
    $description['pieces'][] = $this->t('<code><<i>none</i>></code> : nothing');
    $description['pieces'][] = $this->t('<code><<i>ct-label</i>></code> : the Content type label');
    $description['pieces'][] = $this->t('<title><<i>title</i>></code> : the node Title');
    $description['pieces'][] = $this->t("A 'String': the 'String' itself");
    $description['output'] = implode("<br/>", $description['pieces']);

    $form['booking']['booking_forms']['book_this_unit'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Book this unit Form'),
      '#description' => $description['output'],
      '#collapsible' => FALSE,
    ];

    $form['booking']['booking_forms']['book_this_unit']['book_this_unit_position'] = [
      '#default_value' => $config->get('beehotel.book_this_unit_position'),
      '#options' => [
        'none' => $this->t("none"),
        'top' => $this->t("Top"),
        'bottom' => $this->t("Bottom"),
      ],
      '#type' => 'select',
      '#title' => $this->t('Position'),
      '#required' => TRUE,
      '#description' => $this->t('Position inside the node display. @todo: code for the top and bottom position'),
    ];

    $form['booking']['booking_forms']['book_this_unit']['book_this_unit_submit_label'] = [
      '#default_value' => $config->get('beehotel.book_this_unit_submit'),
      '#type' => 'textfield',
      '#title' => $this->t('Submit label'),
      '#required' => TRUE,
      '#description' => $this->t('The label of the submit button'),
    ];

    $form['booking']['booking_forms']['book_this_unit']['book_this_unit_header_label'] = [
      '#default_value' => $config->get('beehotel.book_this_unit_header'),
      '#type' => 'textfield',
      '#title' => $this->t('Header label'),
      '#description' => $this->t('A string introducing the form'),
    ];

    // Vertical.
    $form['vertical'] = [
      '#type' => 'details',
      '#title' => $this->t('Vertical'),
      '#group' => 'settings',
    ];

    $form['vertical']['setup'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => "Set up <a href='/admin/beehotel/vertical/settings'>Setup vertical</a>",
      '#title' => $this->t('Set up Vertical'),
    ];

    // Price Alteration.
    $form['pricelateration'] = [
      '#type' => 'details',
      '#title' => $this->t('Price Alteration'),
      '#group' => 'settings',
    ];

    $form['pricelateration']['chain_chart'] = [
      '#type' => 'radios',
      '#title' => $this->t('Chain chart'),
      '#default_value' => $config->get('beehotel.chain_chart'),
      '#options' => [
        'pie' => $this->t('Pie') . "<img src='/" . $path . "/assets/images/google_chart_pie.jpg' alt='Google pie chart' height=40 width=40>",
        'combochart' => $this->t('Combo Chart') . "<img src='/" . $path . "/assets/images/google_chart_combo.jpg' alt='Google combo chart' height=40 width=120>",
      ],
      '#description' => $this->t('This feature is ALPHA'),
    ];

    $form['pricelateration']['setuppage'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => "Set up <a href='/admin/beehotel/pricealterator/alterators'>price alterators</a>",
      '#title' => $this->t('Setup Alter ators'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $config = $this->configFactory->getEditable('beehotel.settings');
    $config
      ->set('beehotel.off_value', $form_state->getValue('off_value'))
      ->set('beehotel.off_text', $form_state->getValue('off_text'))
      ->set('beehotel.setup_mode', $form_state->getValue('setupmode_on_value'))
      ->set('beehotel.calendar_from', $form_state->getValue('calendar_from'))
      ->set('beehotel.book_this_unit_header', $form_state->getValue('book_this_unit_header_label'))
      ->set('beehotel.book_this_unit_submit', $form_state->getValue('book_this_unit_submit_label'))
      ->set('beehotel.book_this_unit_position', $form_state->getValue('book_this_unit_position'))
      ->set('beehotel.units_search_header', $form_state->getValue('units_search_header_label'))
      ->set('beehotel.units_search_submit', $form_state->getValue('units_search_submit_label'))
      ->set('beehotel.units_search_position', $form_state->getValue('units_search_position'))
      ->set('beehotel.chain_chart', $form_state->getValue('chain_chart'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Update modules weight.
   */
  public function submitUpdateweight(array &$form, FormStateInterface $form_state) {
    bee_hotel_update_modules_weight();
    $this->messenger()->addStatus($this->t('Modules weight updated'));
  }

}
