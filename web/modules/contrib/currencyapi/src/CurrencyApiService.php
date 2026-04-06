<?php

namespace Drupal\currencyapi;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;

/**
 * Provides currency exchange rates from CurrencyAPI.com.
 */
class CurrencyApiService {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The key-value expirable storage.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface
   */
  protected $keyValueExpirable;

  /**
   * Constructs a new CurrencyApiService.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    KeyValueExpirableFactoryInterface $key_value_expirable
  ) {
    $this->configFactory = $config_factory;
    $this->keyValueExpirable = $key_value_expirable;
  }

  /**
   * Fetches and save.
   *
   * Fetches exchange rates from CurrencyAPI.com and save into module config.
   *
   * @return array|null
   *   The exchange rates data or NULL on failure.
   */
  public function fetchExchangeRates(): ?array {

    $d = [];

    $d['collection'] = 'currencyapi.com';
    $d['expiration'] = 360 * 24;
    $d['store'] = $this->keyValueExpirable->get($d['collection'], $d['expiration']);

    $d['config'] = $this->configFactory->getEditable('currencyapi.settings');

    $d['api_key'] = $d['config']->get('api_key');
    $d['base_currency'] = $d['config']->get('base_currency') ?? 'EUR';
    $d['target_currencies'] = $d['config']->get('target_currencies') ?? ['USD'];
    $d['currencies'] = explode(",", $d['config']->get('currencies'));
    $d['default_currency'] = $d['currencies'][0];

    if (empty($d['api_key'])) {
      throw new \RuntimeException('CurrencyAPI.com API key is not configured.');
    }

    $d['url'] = $this->buildApiUrl($d['api_key'], $d['currencies']);

    // Live query, Consuming API!
    $d['response'] = $this->makeApiRequest($d);

    $this->setDefaultCurrency($d);

    if ($d['response'] && isset($d['response']['data'])) {
      $d['store']->set('rates', $d['response']['data']);
      return $d['response'];
    }

    return NULL;
  }

  /**
   * Builds the API URL.
   */
  protected function buildApiUrl(string $api_key, array $currencies): string {

    return sprintf(
      'https://api.currencyapi.com/v3/latest?apikey=%s&base_currency=%s&currencies=%s',
      urlencode($api_key),
      urlencode($currencies[0]),
      urlencode(implode(',', $currencies))
    );
  }

  /**
   * Makes the API request using file_get_contents().
   */
  protected function makeApiRequest(array $d): ?array {

    $d['context'] = stream_context_create([
      'http' => [
        'timeout' => 10,
        'header' => "Accept: application/json\r\n",
      ],
    ]);

    $d['response'] = @file_get_contents($d['url'], FALSE, $d['context']);

    if ($d['response'] === FALSE) {
      \Drupal::logger('currencyapi')->error('Failed to fetch exchange rates from CurrencyAPI.com');
      return NULL;
    }

    return json_decode($d['response'], TRUE);
  }

  /**
   * Gets the exchange rate for a specific currency.
   */
  public function getRate(string $currency): ?float {}

  /**
   * Gets all stored exchange rates.
   */
  public function getAllRates(): array {

    $d = [];

    $d['collection'] = 'currencyapi.com';
    $d['storage'] = $this->keyValueExpirable->get($d['collection'])->get('rates');

    $this->getDefaultCurrency($d);

    return $d['storage'] ?: [];
  }

  /**
   * Converts an amount between currencies.
   *
   * @param float $amount
   *   How much $from currency to convert.
   * @param string $from
   *   Currency to convert from.
   * @param string $to
   *   Currency to convert to.
   */
  public function convert(float $amount, string $from, string $to) {

    $d = [];
    $d['amount'] = $amount;
    $d['from'] = $from;
    $d['to'] = $to;

    if ($d['from'] === $d['to']) {
      $d['output'] = $d['amount'];
    }

    $d['storage'] = $this->getAllRates();

    // Direct conversion available.
    // @todo replace EUR with store default currency.
    if ($d['from'] === 'EUR' && isset($d['storage'][$d['to']])) {
      $d['output'] = ($d['amount'] * $d['storage'][$d['to']]['value']);
      $d['output'] = $this->formatCurrency($d['output']);
    }
    else {
      $d['output'] = $d['amount'] / $d['storage'][$d['from']]['value'];
      $d['output'] = $this->formatCurrency($d['output']);
    }
    return $d['output'];
  }

  /**
   * Converts an amount between currencies.
   *
   * @param float $amount
   *   How much out_currency currency to sell.
   * @param string $in_currency
   *   Currency to receive.
   * @param string $out_currency
   *   Currency to give (thus sell).
   */
  public function sell(float $amount, string $in_currency, string $out_currency/*, $in_currency_buy*/) {

    $d = [];
    $d['config'] = $this->configFactory->get('currencyapi.settings');
    $d['amount'] = $amount;
    $d['in_currency'] = $in_currency;
    $d['out_currency'] = $out_currency;
    $d['all_rates'] = $this->getAllRates();
    $d['buy_rate'] = (float) $d['config']->get('buyrate_' . $d['in_currency']);

    $d['bare_conversion'] = $this->convert($d['amount'], $d['out_currency'], $d['in_currency']);
    $d['sell'] = $d['bare_conversion'] + ($d['bare_conversion'] / 100 * $d['buy_rate']);

    return $d['sell'];
  }

  /**
   * Gets the last update timestamp.
   */
  public function getLastUpdate(): ?int {
    $storage = $this->keyValueExpirable->get('currencyapi_rates');
    return $storage->get('_last_update');
  }

  /**
   * Get currency from Core.
   */
  public function getSystemCurrencies()  {}

  /**
   * Format the value.
   *
   * Why so difficult to fin Drupal decimal API?
   *
   * @todo use core delimited etc.
   */
  public function formatCurrency($number) {
    $output = number_format($number, 2, '.', ' ');
    // $output = number_format($d['output'], 2, ',', ' ');
    return $output;
  }

  /**
   * Elaborate the base price.
   */
  public function applyCurrencyApi($number) {
    $number = $number * 100;
    return $number;
  }

  /**
   * Elaborate the buy price base 1.
   *
   * @argument $code float
   * @argument $d array
   *
   * @return array
   *   A structure array with buy price.
   */
  public function weBuy($code, $d) {
    $d[$code]['buy_at_buy'] = (float) $d[$code]['buy_at_base'] + ((float) $d[$code]['buy_at_base'] / 100 * (float) $d[$code]['buy']);
    return $d;
  }

  /**
   * Get default curerncy.
   *
   * Default currenct is the first in the currency setting field.
   */
  public function getDefaultCurrency(&$d) {
    foreach ($d['storage'] as $code => $currency) {
      if ($currency['is_default'] == TRUE) {
        return $d['default_currency'] = $code;
      }
    }
  }

  /**
   * Set default Currency in $d array.
   */
  protected function setDefaultCurrency(&$d) {
    foreach ($d['response']['data'] as $code => $value) {
      $d['response']['data'][$code]['is_default'] = FALSE;
      if ($code == $d['default_currency']) {
        $d['response']['data'][$code]['is_default'] = TRUE;
      }
    }
  }

  /**
   * Round up to the unit.
   */
  public function ceil($float) {
    return (int) ($float + 0.5);
  }

}
