<?php

namespace Drupal\commerce_account_balance;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Provides a link builder.
 */
class AccountBalanceLinkBuilder {

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(AccountInterface $current_user, EntityTypeManagerInterface $entity_type_manager) {
    $this->currentUser = $current_user;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Get balance link.
   */
  public function getBalanceLink($order) {

    $data = [];
    $data['order'] = $order;
    $data['customer'] = $order->getCustomer();
    $data['order_id'] = $order->Id();

    if (!$data['customer'] || $data['customer']->isAnonymous()) {
      if ($order->getEmail()) {
        $data['email'] = $order->getEmail();
      }
      else {
        return NULL;
      }
    }
    else {
      $data['id'] = $data['customer']->id();
    }

    // Check permissions.
    if (!$this->currentUser->hasPermission('view any account balance') &&
        $this->currentUser->id() != $customer->id()) {
      return NULL;
    }

    $url = Url::fromRoute('commerce_account_balance.account_balance', ['order' => $data['order_id']]);

    $data['mail'] = $data['order']->get("mail")->value;
    $data['orders'] = commerce_account_balance_get_mail_orders($data['mail']);
    $data['email_balance'] = commerce_account_balance_get_email_balance($data['orders']);
    $data['output'] = $data['link'] = Link::fromTextAndUrl($data['email_balance'], $url)->toRenderable();

    return $data['output'];
  }

}
