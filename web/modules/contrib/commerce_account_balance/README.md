# Commerce Account Balance Module

## Overview
The Commerce Account Balance module provides comprehensive account balance
management for Drupal Commerce stores. It allows:

- Tracking user account balances as commerce price values
- Admin interface for balance adjustments
- Transaction history logging
- Balance display blocks
- Integration with Commerce orders

## Features

- [@todo] **User Balance Tracking**: Maintain balance records for each user
- [@todo] **Admin Adjustments**: Administrator add, subtract, or set balances
- [@todo] **Transaction History**: Full audit trail of all balance changes
- [@todo] **Order Integration**: View balance links directly from order pages
- [@todo] **Balance Display**: Configurable blocks to show current balance
- [@todo] **Multi-currency Support**: Works with Commerce's currency system

## Requirements

- Drupal 10.3 or sup
- Drupal Commerce module
- Commerce Price module

## Installation

1. Get the module `composer require drupal/commerce_account_balance`
2. Enable the module:
   - Via Drush: `drush en commerce_account_balance`
   - Or through Admin UI: `/admin/modules`

3. Configure permissions at `/admin/people/permissions`:
   - View account balance
   - View any account balance
   - Administer account balances

## Configuration

### Balance Settings
Configure default settings at `/admin/commerce/config/account-balance`:
- [@todo] Default currency
- [@todo] Minimum/maximum balance limits
- [@todo] Low balance threshold for notifications

### Display Options
1. [@todo] Add the "Account Balance" block to regions via Block Layout
2. [@todo] Configure order view modes to include balance links

## Usage

### For Administrators
- [@todo] Adjust balances at `/admin/commerce/balance/adjust/{user}`
- [@todo] View all transactions at `/admin/commerce/balance/transactions`
- [@todo] View user balances at `/user/{user}/balance`

### For Customers
- [@todo] View their balance via the account balance block
- [@todo] Access transaction history at their balance page
- [@todo] See balance information during checkout

## API Usage

The module provides several API endpoints and services:

### Services
- `commerce_account_balance.link_builder`: Generates balance links for orders
- `commerce_account_balance.manager`: Handles balance calculations

### Hooks
- `hook_commerce_account_balance_adjust()`: React to balance changes
- `hook_commerce_account_balance_query()`: Modify balance queries

Example API call:
```php
$balance_service = \Drupal::service('commerce_account_balance.manager');
$current_balance = $balance_service->getBalance($user);
```

## Theming

Override these templates:
- `commerce-order-account-balance--admin.html.twig`: Admin Order display
- [@todo] `account-balance.html.twig`: Main balance display
- [@todo] `account-balance-transaction.html.twig`: Individual transaction rows

## Troubleshooting

### Common Issues

**Q: Balances aren't showing for users**
- Verify the user has permission to view balances
- Check that the balance field exists on user entities

**Q: Adjustment form isn't working**
- Ensure the commerce_price module is enabled
- Verify the user has "administer account balances" permission

**Q: Transaction history isn't recording**
- Check the database table `commerce_account_balance_transactions` exists
- Verify cron is running to process any queue items

## Maintainers

- Current maintainer: [afagioli]
- Issue queue: [https://www.drupal.org/project/issues/commerce_account_balance]
- Documentation: [@todo]

## License

GPL-2.0+
