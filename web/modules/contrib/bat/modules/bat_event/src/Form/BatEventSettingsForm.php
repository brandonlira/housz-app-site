<?php

namespace Drupal\bat_event\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * {@inheritdoc}
 */
class BatEventSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bat__event_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['bat_event.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('bat_event.settings');

    $form['settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('BAT Event Settings'),
      '#collapsible' => FALSE,
    ];

    $form['settings']['old_events'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Remove old events'),
      '#description' => $this->t('With these values you build the following rule: "When <b>cron</b> runs, delete N. <b>YYY</b> events with end-date older than <b>XXX</b> days ago"'),
      '#collapsible' => FALSE,
    ];

    $form['settings']['old_events']['old_events_status'] = [
      '#default_value' => isset($config->get("bat_event")['delete_old']['status']) ?: "",
      '#type' => 'checkbox',
      '#title' => $this->t('Enable'),
      '#description' => $this->t('<b>Important</b> This feature may break your data integrity. <b>Do not enable</b> this if unsure.'),
    ];

    $form['settings']['old_events']['old_events_days_back'] = [
      '#default_value' => isset($config->get("bat_event")['delete_old']['days_back']) ?: "",
      '#min' => 1,
      '#required' => TRUE,
      '#step' => 1,
      '#title' => $this->t("How many past days"),
      '#description' => $this->t("Depending your business requirement, to improve performance, you only want to keep recent events. Choose here how many past days you want to keep in record. <b>XXX</b> in example."),
      '#type' => 'number',
    ];

    $form['settings']['old_events']['old_events_how_many_per_cron'] = [
      '#default_value' => isset($config->get("bat_event")['delete_old']['how_many_per_cron']) ?: 0,
      '#min' => 0,
      '#required' => TRUE,
      '#step' => 1,
      '#title' => $this->t("How many event to delete per cron"),
      '#description' => $this->t("Old events are delete at cron time. Choose here how many event you want to delete everytime cron runs. Use this as part of performance settings. <b>YYY</b> in example."),
      '#type' => 'number',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->config('bat_event.settings')
      ->set('bat_event.delete_old.status', $form_state->getValue('old_events_status'))
      ->set('bat_event.delete_old.days_back', $form_state->getValue('old_events_days_back'))
      ->set('bat_event.delete_old.how_many_per_cron', $form_state->getValue('old_events_how_many_per_cron'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
