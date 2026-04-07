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

    // ── Page header ────────────────────────────────────────────────────────
    $form['wrapper']['header'] = [
      '#type'   => 'markup',
      '#markup' => '
        <div class="hz-dash__welcome">
          <div>
            <h1 class="hz-dash__title">Settings</h1>
            <p class="hz-dash__subtitle">Configure notification recipients and system behaviour.</p>
          </div>
          <div class="hz-dash__welcome-actions">
            <a href="/housz" class="button button--secondary">← Dashboard</a>
          </div>
        </div>',
    ];

    // ── Booking notifications panel ────────────────────────────────────────
    $form['wrapper']['notifications_open'] = [
      '#markup' => '
        <div class="hz-panel" style="margin-bottom: 1.5rem;">
          <div class="hz-panel__header">
            <h2 class="hz-panel__title">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
              Booking Notifications
            </h2>
          </div>
          <div class="hz-settings__body">',
    ];

    $form['wrapper']['notify_role'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Notify all users with role'),
      '#description'   => $this->t('All active users with this role receive booking emails automatically. If disabled, only the emails below are notified.'),
      '#options'       => ['' => $this->t('— Disabled —')] + $role_options,
      '#default_value' => $config->get('notify_role') ?? 'housz_admin',
      '#wrapper_attributes' => ['class' => ['hz-settings__field']],
    ];

    $form['wrapper']['notify_emails'] = [
      '#type'          => 'textarea',
      '#title'         => $this->t('Additional notification emails'),
      '#description'   => $this->t('One email per line. These receive notifications in addition to the role-based recipients above. No account needed.'),
      '#default_value' => implode("\n", $config->get('notify_emails') ?? []),
      '#rows'          => 4,
      '#placeholder'   => "manager@zoocha.com\nteam@zoocha.com",
      '#wrapper_attributes' => ['class' => ['hz-settings__field']],
    ];

    $form['wrapper']['notifications_close'] = [
      '#markup' => '</div></div>',
    ];

    // ── Login behaviour panel ──────────────────────────────────────────────
    $form['wrapper']['login_open'] = [
      '#markup' => '
        <div class="hz-panel" style="margin-bottom: 1.5rem;">
          <div class="hz-panel__header">
            <h2 class="hz-panel__title">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M11 7L9.6 8.4l2.6 2.6H2v2h10.2l-2.6 2.6L11 17l5-5-5-5zm9 12h-8v2h8c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-8v2h8v14z"/></svg>
              Login Behaviour
            </h2>
          </div>
          <div class="hz-settings__body">',
    ];

    $form['wrapper']['login_redirect'] = [
      '#type'          => 'textfield',
      '#title'         => $this->t('After login, redirect to'),
      '#default_value' => $config->get('login_redirect') ?? '/housz',
      '#description'   => $this->t('Internal path to redirect managers to after signing in.'),
      '#wrapper_attributes' => ['class' => ['hz-settings__field']],
    ];

    $form['wrapper']['login_close'] = [
      '#markup' => '</div></div>',
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
    $emails_raw = $form_state->getValue('notify_emails') ?? '';
    $emails = array_values(array_filter(array_map('trim', explode("\n", $emails_raw))));

    $this->config('hous_z_management.settings')
      ->set('notify_role', $form_state->getValue('notify_role') ?? '')
      ->set('notify_emails', $emails)
      ->set('login_redirect', $form_state->getValue('login_redirect') ?? '/housz')
      ->save();

    parent::submitForm($form, $form_state);
  }

}
