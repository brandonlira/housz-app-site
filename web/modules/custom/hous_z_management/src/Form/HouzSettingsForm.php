<?php

namespace Drupal\hous_z_management\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Hous-Z system settings form.
 */
class HouzSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['hous_z_management.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'hous_z_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('hous_z_management.settings');

    // Load all roles for the dropdown.
    $roles = \Drupal::entityTypeManager()->getStorage('user_role')->loadMultiple();
    $role_options = [];
    foreach ($roles as $role) {
      if (!in_array($role->id(), ['anonymous', 'authenticated'])) {
        $role_options[$role->id()] = $role->label();
      }
    }

    $form['#attached']['library'][] = 'hous_z_management/dashboard';

    $form['wrapper'] = [
      '#type'       => 'container',
      '#attributes' => ['class' => ['hz-dash']],
    ];

    $form['wrapper']['welcome'] = [
      '#type'   => 'markup',
      '#markup' => '<div class="hz-dash__welcome"><div><h1 class="hz-dash__title">Settings</h1><p class="hz-dash__subtitle">Configure notification recipients and system behaviour.</p></div></div>',
    ];

    // ── Notification settings ──────────────────────────────────────────────
    $form['wrapper']['notifications'] = [
      '#type'        => 'details',
      '#title'       => $this->t('Booking notifications'),
      '#open'        => TRUE,
      '#attributes'  => ['class' => ['hz-panel'], 'style' => 'padding: 1.5rem; margin-bottom: 1.5rem;'],
    ];

    $form['wrapper']['notifications']['notify_role'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Notify all users with role'),
      '#description'   => $this->t('All active users with this role will receive booking notification emails automatically. Leave empty to use the override list below.'),
      '#options'       => ['' => $this->t('— Disabled —')] + $role_options,
      '#default_value' => $config->get('notify_role') ?? 'housz_admin',
    ];

    $form['wrapper']['notifications']['notify_emails'] = [
      '#type'          => 'textarea',
      '#title'         => $this->t('Additional notification emails'),
      '#description'   => $this->t('One email address per line. These receive notifications in addition to (or instead of) the role-based recipients above.'),
      '#default_value' => implode("\n", $config->get('notify_emails') ?? []),
      '#rows'          => 5,
      '#placeholder'   => "manager@zoocha.com\nteam@zoocha.com",
    ];

    // ── Redirect settings ──────────────────────────────────────────────────
    $form['wrapper']['redirects'] = [
      '#type'       => 'details',
      '#title'      => $this->t('Login behaviour'),
      '#open'       => TRUE,
      '#attributes' => ['class' => ['hz-panel'], 'style' => 'padding: 1.5rem; margin-bottom: 1.5rem;'],
    ];

    $form['wrapper']['redirects']['login_redirect'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('After login, redirect to'),
      '#default_value' => $config->get('login_redirect') ?? '/housz',
      '#description'   => $this->t('Internal path to redirect managers after signing in.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $emails_raw = $form_state->getValue('notify_emails');
    foreach (array_filter(array_map('trim', explode("\n", $emails_raw))) as $email) {
      if (!\Drupal::service('email.validator')->isValid($email)) {
        $form_state->setErrorByName('notify_emails', $this->t('"@email" is not a valid email address.', ['@email' => $email]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $emails_raw = $form_state->getValue('notify_emails');
    $emails = array_values(array_filter(array_map('trim', explode("\n", $emails_raw))));

    $this->config('hous_z_management.settings')
      ->set('notify_role', $form_state->getValue('notify_role'))
      ->set('notify_emails', $emails)
      ->set('login_redirect', $form_state->getValue('login_redirect'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
