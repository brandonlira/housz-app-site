<?php

namespace Drupal\commerce_account_balance\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\commerce_account_balance\Entity\AccountBalance;

/**
 * Provides an account balance block.
 *
 * @Block(
 *   id = "commerce_account_balance_block",
 *   admin_label = @Translation("Account Balance"),
 *   category = @Translation("Commerce")
 * )
 */
class AccountBalanceBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $account_balance = AccountBalance::load($this->currentUser->id());
    $balance = $account_balance ? $account_balance->getBalance() : NULL;

    return [
      '#theme' => 'account_balance',
      '#balance' => $balance,
      '#user' => $this->currentUser,
      '#cache' => [
        'contexts' => ['user'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIf($account->hasPermission('view account balance'));
  }

}
