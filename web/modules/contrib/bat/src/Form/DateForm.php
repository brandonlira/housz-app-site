<?php

namespace Drupal\bat\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * {@inheritdoc}
 */
class DateForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bat_date_format_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['bat.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('bat.settings');

    $form['main']['format'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Date Format'),
      '#collapsible' => FALSE,
    ];

    $form['main']['format']['bat_date_format'] = [
      '#type' => 'item',
      '#title' => $this->t('BAT PHP Date Format'),
      '#description' => $this->t("A custom date format for events, search summary and calendar pop-ups. Define a php date format string like 'Y-m-d H:i' (see <a href=\"@link\">http://php.net/date</a> for more details).", ['@link' => 'http://php.net/date']),
    ];

    $form['main']['format']['bat_date_format']['date_format'] = [
      '#type' => 'textfield',
      '#size' => 12,
      '#title' => $this->t('Date format'),
      '#default_value' => $config->get('date_format'),
    ];

    $form['main']['format']['bat_date_format']['daily_date_format'] = [
      '#type' => 'textfield',
      '#size' => 12,
      '#title' => $this->t('Daily date format'),
      '#default_value' => $config->get('daily_date_format'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('bat.settings')
      ->set('date_format', $form_state->getValue('date_format'))
      ->set('daily_date_format', $form_state->getValue('daily_date_format'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
