<?php

namespace Drupal\currencyapi\Controller;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * A class for currency output.
 */
class CurrencyApiController extends ControllerBase {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The currency code.
   *
   * @var string
   */
  protected $currencyCode;

  /**
   * Constructs a new CurrencyApiService.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {

    $d = [];

    $this->configFactory = $config_factory;

    // Get the current store and its currency code.
    $d['current_store'] = \Drupal::service('commerce_store.current_store')->getStore();
    $d['currency_code'] = $d['current_store']->getDefaultCurrencyCode();

    $this->currencyCode = $d['currency_code'];
  }

  /**
   * Fetch data from currencyapi.com.
   */
  public function fetchRates(): array {
    return ['#markup' => $this->t("Fetch is made Cron")];
  }

  /**
   * A Buy 100 [local_currency] page.
   *
   *  A page to offer local currency for sale.
   */
  public function buy100(): array {

    $d = [];
    $d['service'] = \Drupal::service('currencyapi.service');
    $d['currency']['base']['code'] = $this->currencyCode;
    $d['currency']['base']['amount'] = 100;
    $d['config'] = $this->config('currencyapi.settings');
    $d['storage'] = $d['service']->getAllRates();

    foreach ($d['storage'] as $code => $value) {

      if ($value['is_default'] != TRUE) {
        $d['list'][$value['code']] = $value;
        $d['list'][$code]['buy'] = (float) $d['config']->get('buyrate_' . $code);

        $d['list'][$code]['sell'] = $d['service']
          ->convert($d['currency']['base']['amount'] + ($d['currency']['base']['amount'] / 100 * $d['list'][$code]['buy']), 'EUR', $code);
        // Optional, ceil to integer.
        // $d['list'][$code]['sell'] = $d['service']
        // ->ceil($d['list'][$code]['sell']);.
      }
    }

    return [
      '#theme' => 'buy_100',
      '#amount' => $d['currency']['base']['amount'],
      '#base_currency' => $d['currency']['base']['code'],
      '#last_update' => $d['config']->get('last_update'),
      '#list' => $d['list'],
      '#rates' => $d['storage'],
      '#attached' => [
        'library' => ['currencyapi/currency_rates'],
      ],
    ];
  }

  /**
   * Currency Exchange Title.
   */
  public function buy100Title(): string {
    $title = $this->t("Buy 100 @currency_code", ['@currency_code' => $this->currencyCode]);
    return $title;
  }

  /**
   * Info page.
   */
  public function ratesPage(): array {

    $d = [];
    $d['service'] = \Drupal::service('currencyapi.service');
    $d['currency']['base']['code'] = 'EUR';
    $d['currency']['base']['amount'] = 100;

    $d['storage'] = $d['service']->getAllRates();

    foreach ($d['storage'] as $code => $value) {
      if ($value['is_default'] == TRUE) {
        $d['base_currency'] = $code;
      }
    }

    return [
      '#theme' => 'currency_rates',
      '#rates' => $d['storage'],
      '#base_currency' => $d['base_currency'],
      '#attached' => [
        'library' => ['currencyapi/currency_rates'],
      ],
    ];
  }

  /**
   * Json output.
   */
  public function ratesJson($key): JsonResponse {

    // @todo add sopme basic validation on $key.
    $d = [];
    $d['service'] = \Drupal::service('currencyapi.service');
    $d['storage'] = $d['service']->getAllRates();

    return new JsonResponse($d['storage']);
  }

}
