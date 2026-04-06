<?php

namespace Drupal\commerce_account_balance\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for handling account balance operations.
 */
class AccountBalanceController extends ControllerBase {

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Constructs a new AccountBalanceController.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user service.
   */
  public function __construct(AccountInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user')
    );
  }

  /**
   * Displays the account balance page.
   *
   * @return array
   *   A render array representing the account balance page.
   */
  public function viewBalance($order) {

    $data = [];

    // Load the full user entity if needed.
    $data['order'] = $order;
    $data['mail'] = $data['order']->get("mail")->value;

    // Get orders related to the same email.
    $data['orders'] = commerce_account_balance_get_mail_orders($data['mail']);
    $data['email_balance'] = commerce_account_balance_get_email_balance($data['orders']);

    // Build the render array.
    $build = [
      '#theme' => 'commerce_orders_balance_table',
      '#email_balance' => $data['email_balance'],
      // @todo improve polarity
      '#balance_polarity' => 'positive',
      '#empty_message' => t('No orders found.'),
      '#label' => $data['mail'],
      '#orders' => $data['orders'],
    ];

    return $build;

  }

  /**
   * Helper method to get user balance.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user entity.
   *
   * @return float
   *   The user's balance.
   */
  protected function getUserBalance(UserInterface $user) {
    // Implement the actual balance retrieval logic here.
    // Get from custom field?
    if ($user->hasField('field_account_balance')) {
      return (float) $user->get('field_account_balance')->value ?? 0.00;
    }

    // Default return if no balance field exists.
    return 0.00;
  }

}
