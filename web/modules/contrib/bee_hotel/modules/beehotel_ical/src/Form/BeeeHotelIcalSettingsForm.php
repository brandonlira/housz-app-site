<?php

namespace Drupal\beehotel_ical\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure beehotel_ical settings.
 */
class BeeeHotelIcalSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'beehotel_ical.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'beehotel_ical_admin_settings';
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

    $config = $this->config('beehotel_ical.settings');

    $form['beehotel_ical_default_options'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Default ICal options'),
      '#collapsible' => FALSE,
    ];

    $form['beehotel_ical_default_options']['beehotel_ical_blocking_status'] = [
      '#default_value' => $config->get('ical.blocking_status'),
      '#type' => 'textfield',
      '#title' => $this->t('Blocking status (comma separated list of existing status in BAT settings)'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('beehotel_ical.settings')
      ->set('ical.blocking_status', $form_state->getValue('beehotel_ical_blocking_status'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
