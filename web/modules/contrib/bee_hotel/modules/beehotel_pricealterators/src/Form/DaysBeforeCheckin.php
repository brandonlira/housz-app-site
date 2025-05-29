<?php

namespace Drupal\beehotel_pricealterators\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure DaysBeforeCheckin Alterator.
 */
class DaysBeforeCheckin extends ConfigFormBase {

  /**
   * Reference to the Alterator (as plugin).
   *
   *   This value matches the ID in the @PriceAlterator annotation.
   */
  public function pluginId() {
    $tmp = explode("\\", __CLASS__);
    return end($tmp);
  }

  /**
   * {@inheritdoc}
   */
  public function configName() {
    return BEEHOTEL_PRICEALTERATOR_ROUTE_BASE . $this->pluginId() . '.settings';
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return $this->configName();
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      $this->configName(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config($this->configName());

    $form = [
      '#type' => 'fieldset',
      '#title' => $this->t('Days Before Check-in'),
      '#collapsible' => FALSE,
    ];

    $form['enabled'] = [
      '#default_value' => $config->get('enabled'),
      '#type' => 'checkbox',
      '#title' => $this->t('Enable this alterator'),
      '#description' => $this->t('When on, this alterator will be applied the BEEHotel prices'),
    ];

    $title = $this->t("Daily price increase");

    $description = $this->t('The closer the check-in day is, the higher the price goes. Value 20 is a good option to give Guests a sense of urgency when checkin price again and again before booking.');

    $form['increase'] = [
      '#default_value' => $config->get('increase'),
      '#type' => 'range_slider',
      '#title' => $title,
      '#min' => 0,
      '#max' => 100,
      '#step' => 1,
      '#description' => $description,
      '#data-orientation' => 'horizontal',
      '#output' => 'below',
      '#output__field_prefix' => '',
      '#output__field_suffix' => '%',
    ];

    $title = $this->t("Days before");
    $description = $this->t('Days before check-in when this alterator begins to work.') . "<br/>";
    $description .= $this->t('Select 10 if you want this alterator to be applied to reservations required 10 days before check-in.') . "<br/>";
    $description .= $this->t('IE: This value is set to 10. This alterator will be applied to reservations made on "10th May", with check-in "20th May". Not applied instead to reservations made on "9th May", with check-in "20th May"');

    $form['days'] = [
      '#default_value' => $config->get('days'),
      '#type' => 'range_slider',
      '#title' => $title,
      '#min' => 1,
      '#max' => 365,
      '#step' => 1,
      '#description' => $description,
      '#data-orientation' => 'horizontal',
      '#output' => 'below',
      '#output__field_prefix' => '',
      '#output__field_suffix' => ' ' . $this->t('days'),
    ];

    $form['#attributes'] = ['class' => ['beehotel-pricealterator']];
    $form['#attached']['library'][] = 'beehotel_pricealterator/pricealterator';

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config($this->configName())
      ->set('enabled', $form_state->getValue('enabled'))
      ->set('increase', $form_state->getValue('increase'))
      ->set('days', $form_state->getValue('days'))
      ->save();
    parent::submitForm($form, $form_state);
    $form_state->setRedirect('beehotel_pricealterator.info.chain');
  }

}
