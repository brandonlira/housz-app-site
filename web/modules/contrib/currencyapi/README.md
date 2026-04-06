# Currency API Module

![Drupal 11](https://img.shields.io/badge/Drupal-11-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.1%2B-green.svg)

A Drupal service module that fetches and stores currency exchange rates from [CurrencyAPI.com](https://currencyapi.com/).

## Table of Contents

1. [Features](#features)
2. [Requirements](#requirements)
3. [Installation](#installation)
4. [Configuration](#configuration)
5. [Usage](#usage)
   - [PHP Examples](#php-examples)
   - [Twig Examples](#twig-examples)
   - [Drush Integration](#drush-integration)
6. [API Reference](#api-reference)
7. [Troubleshooting](#troubleshooting)
8. [Maintainers](#maintainers)
9. [License](#license)

## Features

- Retrieves exchange rates using PHP's native functions
- Stores rates with configurable cache expiration
- Provides currency conversion methods
- Configurable base and target currencies
- Administrative interface for settings
- Follows Drupal coding standards

## Requirements

- Drupal 11
- PHP 8.1+
- CurrencyAPI.com account (free tier available)
- `allow_url_fopen` enabled in PHP

## Installation

1. Install the module via composer Download and place the module in 
your `composer require drupal/currencyapi`

```bash
cd your/drupal/instance
composer require drupal/currencyapi
```

2. Enable the module via Drush:

```bash
drush pm:enable currencyapi
```

3. Or enable through the Drupal admin interface at `/admin/modules`


## Configuration

Configure the module at `/admin/config/services/currency-api`:

1. Enter your CurrencyAPI.com API key
2. Set base currency (default: EUR) (@todo)
3. Specify target currencies 
4. Configure cache expiration (default: 86400 seconds/24 hours) (@todo)

![Configuration Screenshot](docs/images/config-screen.png)

## Usage

### PHP Examples

#### Fetching Rates in a Controller

```php
// In your controller:
public function showRates() {
  $currencyApi = \Drupal::service('currencyapi.service');
  $rates = $currencyApi->fetchExchangeRates();
  
  return [
    '#theme' => 'currency_rates',
    '#rates' => $rates,
  ];
}
```

#### Using in a Custom Service

```php
// In your custom service:
public function getUsdAmount($eurAmount) {
  $currencyApi = \Drupal::service('currencyapi.service');
  return $currencyApi->convert($eurAmount, 'EUR', 'USD');
}
```

### Twig Examples

#### Displaying Rates

```twig
{% set currency = drupal_service('currencyapi.service') %}
{% set rates = currency.getAllRates() %}

<div class="exchange-rates">
  <h3>Current Exchange Rates (EUR base)</h3>
  <ul>
    {% for code, rate in rates %}
      <li>{{ code }}: {{ rate|number_format(4) }}</li>
    {% endfor %}
  </ul>
</div>
```

#### Currency Conversion

```twig
{% set converted = currency.convert(100, 'EUR', 'USD') %}
<p>100 EUR = {{ converted|number_format(2) }} USD</p>
```

### Drush Integration

The module provides Drush commands:

```bash
# Fetch and display current rates
drush currencyapi:fetch-rates

# Display cached rates
drush currencyapi:show-rates

# Clear cached rates
drush currencyapi:clear-cache
```

## API Reference

### CurrencyApiService Methods

| Method | Parameters | Return | Description |
|--------|------------|--------|-------------|
| `fetchExchangeRates()` | None | array or NULL | Fetches fresh rates from API |
| `getRate($currency)` | string $currency | float or NULL | Gets rate for specific currency |
| `getAllRates()` | None | array | Gets all stored rates |
| `convert($amount, $from, $to)` | float $amount, string $from, string $to | float or NULL | Converts between currencies |
| `getLastUpdate()` | None | int or NULL | Timestamp of last update |

## Troubleshooting

**Problem**: Rates aren't updating  
**Solution**:  
1. Verify API key is valid  
2. Check PHP error logs for connection issues  
3. Ensure `allow_url_fopen` is enabled  

**Problem**: Drush commands not working  
**Solution**:  
1. Clear Drush cache: `drush cache:rebuild`  
2. Verify module is properly installed  

**Problem**: Rates showing as expired  
**Solution**:  
1. Increase cache expiration time  
2. Set up cron to regularly fetch rates  

## Maintainers

- [afagioli] - [augustofagioli@gmail.com]  
- [Project Page](https://www.drupal.org/project/currencyapi)  

## License

This project is licensed under [GNU GPLv2](./LICENSE.md).

---

*This module was developed following Drupal coding .*  
*standards and best practices.*  
*CurrencyAPI.com is a third-party service*
*not affiliated with this module.*
