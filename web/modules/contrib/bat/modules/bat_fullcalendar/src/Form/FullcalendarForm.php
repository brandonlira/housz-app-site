<?php

namespace Drupal\bat_fullcalendar\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Description message.
 */
class FullcalendarForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bat_fullcalendar_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['bat_fullcalendar.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('bat_fullcalendar.settings');

    $form['bat_fullcalendar_scheduler'] = [
      '#type' => 'container',
      '#prefix' => '<div id="label-settings">',
      '#suffix' => '</div>',
    ];

    $form['bat_fullcalendar_scheduler']['settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Settings'),
      '#description' => $this->t('Improve user experience with custom settings'),
      '#open' => TRUE,
    ];

    // Time range.
    $form['bat_fullcalendar_scheduler']['settings']['bat_fullcalendar_calendar_timerange_container'] = [
      '#type' => 'details',
      '#title' => $this->t('Time range'),
      '#description' => $this->t('Time range for events'),
    ];

    // Value as integer (js will abs).
    $timerange_options_start = [
      '-30' => 'From 30 days ago',
      '-14' => 'From 14 days ago',
      '-6' => 'From 6 days ago',
      '0' => 'From today',
      '1' => 'From tomorrow',
    ];

    // Value as integer.
    $timerange_options_end = [
      '30' => 'till 30 days from today',
      '90' => 'till 90 days from today',
      '180' => 'till 180 days from today',
      '360' => 'till 360 days from today',
      '720' => 'till 720 days from today',
    ];

    $form['bat_fullcalendar_scheduler']['settings']['bat_fullcalendar_calendar_timerange_container']['bat_fullcalendar_calendar_timerange_start'] = [
      '#type' => 'radios',
      '#title' => $this->t('Time range from'),
      '#description' => $this->t('Define begin of time range to be exposed on calendar'),
      '#default_value' => $config->get("bat_fullcalendar_calendar_timerange_start"),
      '#options' => $timerange_options_start,
      '#required' => TRUE,
    ];

    $form['bat_fullcalendar_scheduler']['settings']['bat_fullcalendar_calendar_timerange_container']['bat_fullcalendar_calendar_timerange_end'] = [
      '#type' => 'radios',
      '#title' => $this->t('Time range till'),
      '#description' => $this->t('Define begin of time range to be exposed on calendar'),
      '#default_value' => $config->get("bat_fullcalendar_calendar_timerange_end"),
      '#options' => $timerange_options_end,
      '#required' => TRUE,
    ];

    // Height.
    $form['bat_fullcalendar_scheduler']['settings']['bat_fullcalendar_calendar_height_container'] = [
      '#type' => 'details',
      '#title' => $this->t('Height'),
      '#description' => $this->t('Global fixed height for the Fullcalendars'),
    ];

    $form['bat_fullcalendar_scheduler']['settings']['bat_fullcalendar_calendar_height_container']['bat_fullcalendar_calendar_height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Calendars height'),
      '#description' => $this->t('Integer, a CSS value like "100%" or "auto" (suggested)'),
      '#default_value' => $config->get("bat_fullcalendar_calendar_height"),
      '#required' => TRUE,
      '#maxlength' => 14,
    ];

    // Views.
    $form['bat_fullcalendar_scheduler']['settings']['bat_fullcalendar_calendar_views_container'] = [
      '#type' => 'details',
      '#title' => $this->t('Views'),
      '#description' => $this->t('Global views Fullcalendars'),
    ];

    $view_options = [
      'timeGridDay' => 'timeGridDay',
      'timeGridWeekday' => 'timeGridWeekday',
      'timeGridWeek' => 'timeGridWeek',
      'timeGridMonth' => 'timeGridMonth',
      'dayGridDay' => 'dayGridDay',
      'dayGridWeek' => 'dayGridWeek',
      'dayGridMonth' => 'dayGridMonth (suggested)',
      'dayGridYear' => 'dayGridYear',
      'listWeek' => 'listWeek',
      'multiMonthYear' => 'multiMonthYear  plugins: [multiMonthPlugin]',
      'resourceTimelineDay' => 'Resource Timeline Day [GPLv3, premium]',
      'resourceTimelineWeek' => 'Resource Timeline Week [GPLv3, premium]',
    ];

    $form['bat_fullcalendar_scheduler']['settings']['bat_fullcalendar_calendar_views_container']['bat_fullcalendar_calendar_view_daily'] = [
      '#type' => 'select',
      '#title' => $this->t('Default view daily'),
      '#description' => $this->t('Fullcalendar default view for daily events. See https://fullcalendar.io/docs/custom-views'),
      '#default_value' => $config->get("bat_fullcalendar_calendar_view_daily"),
      '#options' => $view_options,
      '#required' => TRUE,
    ];

    $form['bat_fullcalendar_scheduler']['settings']['bat_fullcalendar_calendar_views_container']['bat_fullcalendar_calendar_view_hourly'] = [
      '#type' => 'select',
      '#title' => $this->t('View hourly'),
      '#description' => $this->t('Fullcalendar default view for hourly events. See https://fullcalendar.io/docs/custom-views'),
      '#default_value' => $config->get("bat_fullcalendar_calendar_view_hourly"),
      '#options' => $view_options,
      '#required' => TRUE,
    ];

    $form['bat_fullcalendar_scheduler']['settings']['bat_fullcalendar_calendar_views_container']['bat_fullcalendar_calendar_edit_daily'] = [
      '#type' => 'select',
      '#title' => $this->t('Default edit view daily'),
      '#description' => $this->t('Fullcalendar default edir view for daily events. See https://fullcalendar.io/docs/custom-views'),
      '#default_value' => $config->get("bat_fullcalendar_calendar_edit_daily"),
      '#options' => $view_options,
      '#required' => TRUE,
    ];

    $form['bat_fullcalendar_scheduler']['settings']['bat_fullcalendar_calendar_views_container']['bat_fullcalendar_calendar_edit_hourly'] = [
      '#type' => 'select',
      '#title' => $this->t('Default edit view hourly'),
      '#description' => $this->t('Fullcalendar default edit view for hourly events. See https://fullcalendar.io/docs/custom-views'),
      '#default_value' => $config->get("bat_fullcalendar_calendar_edit_hourly"),
      '#options' => $view_options,
      '#required' => TRUE,
    ];

    // License.
    $form['bat_fullcalendar_scheduler']['bat_fullcalendar_calendar_license_container'] = [
      '#type' => 'details',
      '#title' => $this->t('License'),
      '#description' => $this->t('Global License settings for Fullcalendars'),
    ];

    $form['bat_fullcalendar_scheduler']['bat_fullcalendar_calendar_license_container']['bat_fullcalendar_scheduler_key'] = [
      '#type' => 'radios',
      '#title' => $this->t('FullCalendar Scheduler License'),
      '#default_value' => $config->get('bat_fullcalendar_scheduler_key'),
      '#options' => [
        'commercial' => $this->t('Commercial License'),
        'non-commercial' => $this->t('Non-Commercial Creative Commons'),
        'gpl' => $this->t('GPL License'),
        'none' => $this->t('None'),
      ],
      '#description' => $this->t('Please visit http://fullcalendar.io/scheduler/license/ to find out about the license terms for the Scheduler View of FullCalendar'),
      '#ajax' => [
        'callback' => [$this, 'fullcalendarSettingsAjax'],
        'wrapper' => 'label-settings',
      ],
    ];

    $values = $form_state->getValues();

    if ((isset($values['bat_fullcalendar_scheduler_key']) && $values['bat_fullcalendar_scheduler_key'] == 'commercial') ||
         (!isset($values['bat_fullcalendar_scheduler_key']) && $config->get('bat_fullcalendar_scheduler_key') == 'commercial')) {
      $form['bat_fullcalendar_scheduler']['bat_fullcalendar_scheduler_commercial_key'] = [
        '#type' => 'textfield',
        '#title' => $this->t('FullCalendar Scheduler Commercial License Key'),
        '#required' => TRUE,
        '#default_value' => $config->get('bat_fullcalendar_scheduler_commercial_key'),
      ];
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * Ajax callback.
   */
  public function fullcalendarSettingsAjax(array &$form, FormStateInterface $form_state) {
    return $form['bat_fullcalendar_scheduler'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->config('bat_fullcalendar.settings')
      ->set('bat_fullcalendar_calendar_timerange_start', $form_state->getValue('bat_fullcalendar_calendar_timerange_start'))
      ->set('bat_fullcalendar_calendar_timerange_end', $form_state->getValue('bat_fullcalendar_calendar_timerange_end'))
      ->set('bat_fullcalendar_calendar_height', $form_state->getValue('bat_fullcalendar_calendar_height'))
      ->set('bat_fullcalendar_calendar_view_daily', $form_state->getValue('bat_fullcalendar_calendar_view_daily'))
      ->set('bat_fullcalendar_calendar_edit_daily', $form_state->getValue('bat_fullcalendar_calendar_edit_daily'))
      ->set('bat_fullcalendar_calendar_view_hourly', $form_state->getValue('bat_fullcalendar_calendar_view_hourly'))
      ->set('bat_fullcalendar_calendar_edit_hourly', $form_state->getValue('bat_fullcalendar_calendar_edit_hourly'))
      ->set('bat_fullcalendar_scheduler_key', $form_state->getValue('bat_fullcalendar_scheduler_key'))
      ->set('bat_fullcalendar_scheduler_commercial_key', $form_state->getValue('bat_fullcalendar_scheduler_commercial_key'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
