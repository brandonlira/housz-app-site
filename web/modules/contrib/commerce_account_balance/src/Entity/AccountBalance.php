<?php

namespace Drupal\commerce_account_balance\Entity;

use Drupal\commerce_price\Price;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Account Balance entity.
 *
 * @ContentEntityType(
 *   id = "commerce_account_balance",
 *   label = @Translation("Account Balance"),
 *   base_table = "commerce_account_balance",
 *   entity_keys = {
 *     "id" = "id",
 *     "uid" = "uid",
 *   },
 *   handlers = {
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\commerce_account_balance\AccountBalanceAccessControlHandler",
 *   },
 *   admin_permission = "administer account balances",
 * )
 */
class AccountBalance extends ContentEntityBase implements AccountBalanceInterface {}
