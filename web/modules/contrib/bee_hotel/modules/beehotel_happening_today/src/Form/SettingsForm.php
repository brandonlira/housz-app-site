<?php

namespace Drupal\beehotel_happening_today\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure BeeHotel Happening Today settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'beehotel_happening_today_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['beehotel_happening_today.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('beehotel_happening_today.settings');

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable daily reports'),
      '#default_value' => $config->get('enabled') ?? TRUE,
      '#description' => $this->t('If checked, daily reports will be generated and sent automatically.'),
    ];

    $form['send_time'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Daily report send time'),
      '#default_value' => $config->get('send_time') ?? '07:00',
      '#description' => $this->t('Enter time in 24-hour format (HH:MM). Example: 07:00 for 7 AM.'),
      '#required' => TRUE,
      '#size' => 5,
      '#maxlength' => 5,
    ];

    $form['email_subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Email subject template'),
      '#default_value' => $config->get('email_subject') ?? 'Happening Today - [date]',
      '#description' => $this->t('Use [date] as placeholder for the current date.'),
      '#required' => TRUE,
    ];

    $form['recipients'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Email recipients'),
      '#default_value' => $config->get('recipients') ? implode("\n", $config->get('recipients')) : '',
      '#description' => $this->t('Enter one email address per line.'),
      '#required' => TRUE,
      '#rows' => 5,
    ];

    $form['test'] = [
      '#type' => 'details',
      '#title' => $this->t('Test configuration'),
      '#open' => FALSE,
    ];

    $form['test']['test_email'] = [
      '#type' => 'email',
      '#title' => $this->t('Send test email to'),
      '#description' => $this->t('Send a test report to this email address.'),
    ];

    $form['test']['submit_test'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send test email'),
      '#submit' => ['::submitTestEmail'],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // Validate time format.
    $send_time = $form_state->getValue('send_time');
    if (!preg_match('/^([0-1][0-9]|2[0-3]):[0-5][0-9]$/', $send_time)) {
      $form_state->setErrorByName('send_time', $this->t('Please enter a valid time in HH:MM format (24-hour).'));
    }

    // Validate email recipients
    $recipients_text = $form_state->getValue('recipients');
    $recipients = array_filter(array_map('trim', explode("\n", $recipients_text)));

    foreach ($recipients as $email) {
      if (!\Drupal::service('email.validator')->isValid($email)) {
        $form_state->setErrorByName('recipients', $this->t('Invalid email address: @email', ['@email' => $email]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('beehotel_happening_today.settings');

    $recipients = array_filter(array_map('trim', explode("\n", $form_state->getValue('recipients'))));

    $config
      ->set('enabled', $form_state->getValue('enabled'))
      ->set('send_time', $form_state->getValue('send_time'))
      ->set('email_subject', $form_state->getValue('email_subject'))
      ->set('recipients', $recipients)
      ->save();

    parent::submitForm($form, $form_state);

    $this->messenger()->addStatus($this->t('Configuration saved.'));
  }

  /**
   * Submit handler for test email.
   */
  public function submitTestEmail(array &$form, FormStateInterface $form_state) {
    $test_email = $form_state->getValue('test_email');

    if (!\Drupal::service('email.validator')->isValid($test_email)) {
      $this->messenger()->addError($this->t('Please enter a valid email address for the test.'));
      return;
    }

    // Temporarily set the test email as recipient.
    $original_recipients = $this->config('beehotel_happening_today.settings')->get('recipients');
    $this->config('beehotel_happening_today.settings')
      ->set('recipients', [$test_email])
      ->save();

    // Generate and send test report.
    _beehotel_happening_today_generate_daily_report();

    // Restore original recipients.
    $this->config('beehotel_happening_today.settings')
      ->set('recipients', $original_recipients)
      ->save();

    $this->messenger()->addStatus($this->t('Test email sent to @email.', ['@email' => $test_email]));
  }

}
