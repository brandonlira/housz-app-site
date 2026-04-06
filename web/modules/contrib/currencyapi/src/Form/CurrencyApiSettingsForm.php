<?php

namespace Drupal\currencyapi\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * A Configuration Form Class.
 */
class CurrencyApiSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'currencyapi_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['currencyapi.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $d = [];

    $d['currencyapi_service'] = \Drupal::service('currencyapi.service');
    $d['config'] = $this->config('currencyapi.settings');

    $form['api'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('API Configuration'),
    ];

    $form['api']['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => $d['config']->get('api_key'),
      '#required' => TRUE,
    ];

    $form['api']['api_url'] = [
      '#type' => 'url',
      '#title' => $this->t('API Endpoint'),
      '#default_value' => $d['config']->get('api_url'),
      '#description' => $this->t('Base URL for the currency API service. IE: https://api.currencyapi.com/v3/latest'),
    ];
    $form['currencies'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Currency Settings'),
    ];
    $form['currencies']['currencies'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Currencies'),
      '#default_value' => $d['config']->get('currencies') ?? 'EUR,GBP,JPY,CAD',
      '#description' => $this->t('Comma-separated list of currency codes sorted by your own preference'),
    ];
    $form['currencies']['setup'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Currencies Setup'),
    ];

    $d['last_update'] = $this->t("Last update: %last", ['%last' => date('d/m/Y H:m:d', $d['config']->get("last_update")) ?: "---"]);
    $d['table_prefix'] = $this->t("<a href='@link'>Exchange table</a> |", ['@link' => 'currency-exchange']);
    $d['table_prefix'] .= $this->t("<a href='@link'>Buy 100</a> |", ['@link' => 'buy-100']);
    $d['table_prefix'] .= $this->t("<a href='@link'>Fetch rates</a> |", ['@link' => 'fetch-rates']);
    $d['table_prefix'] .= $d['last_update'];

    // For better documentation, TR TH are not to be translated.
    $buyrate_table = [
      '#type' => 'table',
      '#header' => [
        'code' => "C",
        'base_value' => "BASE",
        'buy_at_base_with_euro' => "BASE 100€",
        'buy_rate' => "BUY",
        'buy_at_buy_with_euro' => "BUY 100€",
        'income' => "IN",
        'income_euro' => "IN €",
      ],
      '#empty' => $this->t('Sorry, There are no items!'),
      '#prefix' => $d['table_prefix'],
    ];

    $d['storage'] = $d['currencyapi_service']->getAllRates();

    $d['currencyapi_service']->getDefaultCurrency($d);

    if (isset($d['storage'])) {

      foreach ($d['storage'] as $code => $currency_values) {

        if ($currency_values['is_default'] == TRUE) {
          continue;
        }

        // A.
        $d[$code]['code'] = $code;

        $buyrate_table[$code]['name'] = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $d[$code]['code'],
          '#attributes' => [
            'class' => [
              'name',
              strtolower($d[$code]['code']),
            ],
          ],
        ];

        // B.
        $d[$code]['value'] = $d['currencyapi_service']->formatCurrency($currency_values['value']);

        $buyrate_table[$code]['value'] = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $d[$code]['value'],
          '#attributes' => [
            'class' => [
              'base-rate',
            ],
          ],
        ];

        // C.
        $d[$code]['buy_at_base'] = $d['currencyapi_service']->applyCurrencyApi($d[$code]['value']);
        $d[$code]['buy_at_base'] = $d['currencyapi_service']->formatCurrency($d[$code]['buy_at_base']);

        $buyrate_table[$code]['we_buy_base'] = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $d[$code]['buy_at_base'] . "<span class='currency'>" . $d[$code]['code'] . "</span>",
        ];

        // D.
        $d[$code]['buy'] = $d['config']->get('buyrate_' . $code);

        $buyrate_table[$code]['buy_rate'] = [
          '#type' => 'textfield',
          '#value' => $d[$code]['buy'],
          '#suffix' => "<span class='suffix'>%<span>",
          '#attributes' => [
            'class' => [
              'buy-rate',
            ],
          ],
        ];

        // E.
        $d = $d['currencyapi_service']->weBuy($code, $d);

        $d[$code]['buy_at_buy'] = $d['currencyapi_service']->formatCurrency($d[$code]['buy_at_buy']);

        $buyrate_table[$code]['we_buy_at_buy'] = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $d[$code]['buy_at_buy'] . "<span class='currency'>" . $d[$code]['code'] . "</span>",
        ];

        // F.
        $d[$code]['income'] = (float) $d[$code]['buy_at_buy'] - (float) $d[$code]['buy_at_base'];

        $buyrate_table[$code]['income'] = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $d[$code]['income'] . "<span class='currency'>" . $d[$code]['code'] . "</span>",
        ];

        // G.
        $d[$code]['income_euro'] = $d['currencyapi_service']
          ->convert($d[$code]['income'], $from = $code, $to = $d['default_currency']);

        $buyrate_table[$code]['income_euro'] = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $d[$code]['income_euro'] . "<span class='currency'>EUR</span>",
        ];
      }

    }

    $form['currencies']['setup']['buyrate_table'] = $buyrate_table;
    $form['#attached']['library'][] = 'currencyapi/settings-form';

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

    $d = [];
    $d['currencyapi_service'] = \Drupal::service('currencyapi.service');

    $this->config('currencyapi.settings')
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('api_url', $form_state->getValue('api_url'))
      ->set('currencies', $form_state->getValue('currencies'))
      ->save();

    foreach ($form_state->getValue("buyrate_table") as $code => $rate) {

      // We need this for the very form itself.
      $this->config('currencyapi.settings')
        ->set(
          'buyrate_' . $code,
          $d['currencyapi_service']->formatCurrency($rate['buy_rate'])
        )
        ->save();
    }

    parent::submitForm($form, $form_state);
  }

}
