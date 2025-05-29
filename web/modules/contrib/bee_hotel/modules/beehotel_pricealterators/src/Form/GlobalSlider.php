<?php

namespace Drupal\beehotel_pricealterators\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Configure GlobalSlider Alterator.
 */
class GlobalSlider extends ConfigFormBase {

  use StringTranslationTrait;

  /**
   * Reference to the Alterator (as plugin).
   *
   *   This value matches the ID in the @PriceAlterator annotation.
   */
  public function pluginId() {
    return 'GlobalSlider';
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

    $pieces = [];
    $pieces[] = "<h5>" . $this->t("Concept") . "</h5>";
    $pieces[] = $this->t("<b>Global Slider</b> price Alterator will alter the every price passing through the price algorithm with the percentage here selected");
    $pieces[] = "<h5>" . $this->t("How to") . "</h5>";
    $pieces[] = $this->t("Keep the increase to 0 to leave price unchanged.");
    $pieces[] = $this->t("Alter the global slider value to change the price accordingly.");
    $pieces[] = "<h5>" . $this->t("Tip") . "</h5>";
    $pieces[] = $this->t("This alterator works best at the end of the algorithm chain (heavier weight).");

    $pieces[] = "<h5>" . $this->t("Example") . "</h5>";
    $pieces[] = $this->t("Base price: 100");
    $pieces[] = $this->t("Global Slider: 20%");
    $pieces[] = $this->t("Altereted price: 120");

    $pieces[] = " ";

    $pieces[] = $this->t("Base price: 100");
    $pieces[] = $this->t("Global Slider: -10%");
    $pieces[] = $this->t("Altereted price: 900");

    $info = implode("<br/>", $pieces);

    $form['info'] = [
      '#type' => 'details',
      '#title' => $this->t('Info'),
      '#description' => $info,
      '#open' => FALSE,
    ];

    $form = [
      '#type' => 'fieldset',
      '#title' => $this->t('Global Slider'),
      '#collapsible' => TRUE,
    ];

    $form['info'] = [
      '#type' => 'details',
      '#title' => $this->t('Info'),
      '#description' => $info,
      '#open' => FALSE,
    ];

    $form['enabled'] = [
      '#default_value' => $config->get('enabled'),
      '#type' => 'checkbox',
      '#title' => $this->t('Enable this alterator'),
      '#description' => $this->t('When on, this alterator will be applied the BEEHotel prices'),
    ];

    $tmp = $this->t("Price will be altered as per percentage value");
    $tmp .= "<br/>" . $this->t("price + (price / 100 * global slice) = Altered price");
    $tmp .= "<br/>" . $this->t("IE: 200 + (200 / 100 * 20) = 240");

    $form['globalslider'] = [
      '#default_value' => $config->get('globalslider'),
      '#type' => 'range_slider',
      '#title' => 'Global Slider',
      '#min' => -100,
      '#max' => 100,
      '#step' => 1,
      '#description' => $tmp,
      '#data-orientation' => 'horizontal',
      '#output' => 'below',
      '#output__field_prefix' => '',
      '#output__field_suffix' => '%',
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
      ->set('globalslider', $form_state->getValue('globalslider'))
      ->save();
    parent::submitForm($form, $form_state);
    $form_state->setRedirect('beehotel_pricealterator.info.chain');
  }

}
