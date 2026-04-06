<?php

namespace Drupal\commerce_account_balance\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\UserInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_account_balance\Entity\AccountBalance;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AccountBalanceAdjustmentForm extends FormBase {

  protected $messenger;

  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger')
    );
  }

  public function getFormId() {
    return 'commerce_account_balance_adjustment_form';
  }

  // public function buildForm(array $form, FormStateInterface $form_state, UserInterface $user = NULL) {
  //   $account_balance = AccountBalance::load($user->id());
  //   $current_balance = $account_balance ? $account_balance->getBalance() : new Price('0', 'USD');
  //
  //   $form['current_balance'] = [
  //     '#type' => 'item',
  //     '#title' => $this->t('Current Balance'),
  //     '#markup' => $current_balance->__toString(),
  //   ];
  //
  //   $form['adjustment'] = [
  //     '#type' => 'commerce_price',
  //     '#title' => $this->t('Adjustment Amount'),
  //     '#required' => TRUE,
  //     '#default_value' => [
  //       'number' => '0',
  //       'currency_code' => $current_balance->getCurrencyCode(),
  //     ],
  //   ];
  //
  //   $form['operation'] = [
  //     '#type' => 'radios',
  //     '#title' => $this->t('Operation'),
  //     '#options' => [
  //       'add' => $this->t('Add to balance'),
  //       'subtract' => $this->t('Subtract from balance'),
  //       'set' => $this->t('Set balance to this amount'),
  //     ],
  //     '#default_value' => 'add',
  //   ];
  //
  //   $form['notes'] = [
  //     '#type' => 'textarea',
  //     '#title' => $this->t('Notes'),
  //     '#description' => $this->t('Optional notes about this adjustment.'),
  //   ];
  //
  //   $form['user'] = [
  //     '#type' => 'value',
  //     '#value' => $user->id(),
  //   ];
  //
  //   $form['actions'] = [
  //     '#type' => 'actions',
  //   ];
  //
  //   $form['actions']['submit'] = [
  //     '#type' => 'submit',
  //     '#value' => $this->t('Apply Adjustment'),
  //   ];
  //
  //   return $form;
  // }

  // public function submitForm(array &$form, FormStateInterface $form_state) {
  //   $user_id = $form_state->getValue('user');
  //   $adjustment = $form_state->getValue('adjustment');
  //   $operation = $form_state->getValue('operation');
  //   $notes = $form_state->getValue('notes');
  //
  //   $account_balance = AccountBalance::load($user_id) ?: AccountBalance::create(['uid' => $user_id]);
  //   $current_balance = $account_balance->getBalance();
  //   $adjustment_price = new Price($adjustment['number'], $adjustment['currency_code']);
  //
  //   switch ($operation) {
  //     case 'add':
  //       $new_balance = $current_balance->add($adjustment_price);
  //       break;
  //
  //     case 'subtract':
  //       $new_balance = $current_balance->subtract($adjustment_price);
  //       break;
  //
  //     case 'set':
  //       $new_balance = $adjustment_price;
  //       break;
  //   }
  //
  //   $account_balance->setBalance($new_balance);
  //   $account_balance->save();
  //
  //   // Log the transaction
  //   \Drupal::database()->insert('commerce_account_balance_transactions')
  //     ->fields([
  //       'uid' => $user_id,
  //       'amount' => $adjustment['number'],
  //       'currency_code' => $adjustment['currency_code'],
  //       'operation' => $operation,
  //       'previous_balance' => $current_balance->getNumber(),
  //       'new_balance' => $new_balance->getNumber(),
  //       'notes' => $notes,
  //       'timestamp' => \Drupal::time()->getRequestTime(),
  //       'admin_uid' => \Drupal::currentUser()->id(),
  //     ])
  //     ->execute();
  //
  //   $this->messenger->addStatus($this->t('Balance adjusted successfully. New balance: @balance', [
  //     '@balance' => $new_balance->__toString(),
  //   ]));
  //
  //   $form_state->setRedirect('commerce_account_balance.user_balance', ['user' => $user_id]);
  // }
}
