<?php

namespace Drupal\beehotel_vertical\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;

/**
 * Configure beehotel_vertical settings.
 */
class BeeeHotelVerticalSettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'beehotel_vertical.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'beehotel_vertical_admin_settings';
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

    $config = $this->config('beehotel_vertical.settings');

    $url = Url::fromRoute('beehotel_vertical.vertical', []);
    $linkText = 'Go to ' . $this->t('Vertical');
    $linkHTMLMarkup = Markup::create($linkText);
    $link = Link::fromTextAndUrl($linkHTMLMarkup, $url);
    $link = $link->toRenderable();

    $form['beehotel_vertical_default_options'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Default VertiCal options'),
      '#collapsible' => FALSE,
      '#description' => $link,
    ];

    $options = [
      0 => $this->t("Start Vertical from today"),
      86400 => $this->t("One day back"),
      (86400 * 2) => $this->t("2 days back"),
      (86400 * 3) => $this->t("3 days back"),
      (86400 * 4) => $this->t("4 days back"),
      (86400 * 7) => $this->t("7 days back"),
      (86400 * 30) => $this->t("30 days back"),
    ];

    $form['beehotel_vertical_default_options']['beehotel_vertical_timejump'] = [
      '#type' => 'select',
      '#title' => $this->t('Time jump'),
      '#options' => $options,
      '#description' => $this->t('The first day for VertiCal. Useful to expose past reservations.'),
      '#default_value' => $config->get('vertical.timejump'),
    ];

    $form['beehotel_vertical_default_options']['beehotel_vertical_warning_money'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Warning: Money to collect'),
      '#description' => $this->t('Expose a warning when money is to be collected.'),
      '#default_value' => $config->get('vertical.warning.money'),
    ];

    $form['beehotel_vertical_google_calendar'] = [
      '#type' => 'details',
      '#title' => $this->t('Google Calendar'),
      '#description' => $this->t('Import a public Google Calendar'),
      '#open' => TRUE,
      '#suffix' => $this->t("Example: https://www.googleapis.com/calendar/v3/calendars/0golue773vail1a18dt3gl6ooo@group.calendar.google.com/events?key=AIzaSyDRdKewQrdsKyzBCNF3HMqw9L6fRz4aYik"),
    ];

    $form['beehotel_vertical_google_calendar']['beehotel_google_calendar_id'] = [
      '#default_value' => $config->get('vertical.google_calendar_id'),
      '#type' => 'textfield',
      '#title' => $this->t('The calendar ID to exposed inside VertiCal'),
      '#required' => FALSE,
    ];

    $form['beehotel_vertical_google_calendar']['beehotel_google_calendar_api_key'] = [
      '#default_value' => $config->get('vertical.google_calendar_api_key'),
      '#type' => 'textfield',
      '#title' => $this->t('Google Calendar key'),
      '#description' => $this->t('From google api console'),
      '#required' => FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('beehotel_vertical.settings')
      ->set('vertical.timejump', $form_state->getValue('beehotel_vertical_timejump'))
      ->set('vertical.warning.money', $form_state->getValue('beehotel_vertical_warning_money'))
      ->set('vertical.google_calendar_id', $form_state->getValue('beehotel_google_calendar_id'))
      ->set('vertical.google_calendar_api_key', $form_state->getValue('beehotel_google_calendar_api_key'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
