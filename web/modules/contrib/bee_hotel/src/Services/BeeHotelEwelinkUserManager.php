<?php

namespace Drupal\bee_hotel\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserInterface;

/**
 * The user manager for ewelink on BeeHotel.
 */
class BeeHotelEwelinkUserManager {

  use StringTranslationTrait;

  /**
   * Construct the Class.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected MailManagerInterface $mailManager,
    protected LanguageManagerInterface $languageManager
  ) {}

  /**
   * Gestisce l'accesso utente basato su email.
   */
  public function handleUserAccessByEmail(string $email) {

    $d = [];
    $d['email'] = $email;

    // Email validation.
    if (!\Drupal::service('email.validator')->isValid($email)) {
      \Drupal::logger('bee_hotel')->error('Invalid email address provided: @email', ['@email' => $email]);
      return FALSE;
    }

    // Search user.
    $d['user'] = $this->loadUserByEmail($email);

    if ($d['user']) {
      $d['uid'] = $d['user']->get('uid')->value;
    }
    else {
      $d['uid'] = $this->createUserAndSendEmail($d['email']);
    }

    return $d['uid'];
  }

  /**
   * Load user by email.
   */
  protected function loadUserByEmail(string $email): ?UserInterface {
    $users = $this->entityTypeManager->getStorage('user')
      ->loadByProperties(['mail' => $email]);
    return $users ? reset($users) : NULL;
  }

  /**
   * Create user and email access.
   */
  protected function createUserAndSendEmail(string $email) {

    $d = [];

    $d['email'] = $email;
    $d['password'] = \Drupal::service('password_generator')->generate();

    $d['user'] = $this->entityTypeManager->getStorage('user')->create([
      'name' => $this->generateUsernameFromEmail($d['email']),
      'mail' => $d['email'] ,
      'pass' => $d['password'],
      'status' => 1,
      'init' => $d['email'],
    ]);

    try {
      $d['user']->save();
      $d['uid'] = $d['user']->id();
      $d['user'] = \Drupal::entityTypeManager()
        ->getStorage('user')
        ->loadByProperties(['mail' => $d['email']]);
      $d['output'] = $this->sendLoginEmail(reset($d['user']));

      return $d['output'];

    }
    catch (\Exception $e) {
      \Drupal::logger('bee_hotel')->error('Error creating user: @error', ['@error' => $e->getMessage()]);
      return FALSE;
    }

  }

  /**
   * Email access details.
   */
  protected function sendLoginEmail(UserInterface $user) {

    $d = [];

    $d['user'] = $user;

    $d['langcode'] = $this->languageManager->getCurrentLanguage()->getId();

    $d['params'] = [
      'account' => $d['user'],
      'subject' => $this->t('Your account access information'),
      'message' => $this->t('You can now access our site. Use your email address to log in.'),
    ];

    $d['result'] = $this->mailManager->mail(
      'user',
      'register_no_approval_required',
      $d['user']->getEmail(),
      $d['langcode'],
      $d['params']
    );

    if ($d['result']['result'] !== TRUE) {
      \Drupal::logger('bee_hotel')->error('Failed to send login email to @email', ['@email' => $d['user']->getEmail()]);
      return FALSE;
    }
    return $d['user']->id();
  }

  /**
   * Create unique username from email address.
   */
  protected function generateUsernameFromEmail(string $email): string {
    $username = preg_replace('/@.+$/', '', $email);
    $username = preg_replace('/[^a-z0-9_]/', '_', strtolower($username));

    $storage = $this->entityTypeManager->getStorage('user');
    $i = 0;
    $original = $username;

    while ($storage->loadByProperties(['name' => $username])) {
      $username = $original . '_' . ++$i;
    }

    return $username;
  }

}
