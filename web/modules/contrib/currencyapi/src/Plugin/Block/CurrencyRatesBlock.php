<?php

namespace Drupal\currencyapi\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\currencyapi\Services\CurrencyApiClient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Currency Rate' Block.
 *
 * @Block(
 *   id = "currency_rates_block",
 *   description = "currency_rates_block",
 *   admin_label = @Translation("Currency Rates"),
 *   category = @Translation("Finance")
 * )
 */
class CurrencyRatesBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected CurrencyApiClient $currencyClient
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('currencyapi.client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $config = $this->configFactory()->get('currencyapi.settings');
    $currencies = explode(',', $config->get('default_currencies') ?? 'EUR,GBP,JPY,CAD');
    $rates = $this->currencyClient->getExchangeRates('USD', $currencies);

    return [
      '#theme' => 'currency_rates',
      '#rates' => $rates,
      '#cache' => [
        'max-age' => 3600,
        'tags' => ['currency_rates'],
      ],
    ];
  }

}
