<?php

namespace Drupal\bee_hotel\Services;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

/**
 * Class BeeHotelEwelinkRoleManager.
 *
 * Handles assignment of self::EWELINK_ROLE role based on reservation status.
 */
class BeeHotelEwelinkRoleManager {

  use StringTranslationTrait;

  /**
   * Role to enable via cron.
   *
   * Via Cron, we give this role to users with active reservations.
   * This role is managed by ewelink module.
   */

  public const EWELINK_ROLE = 'open_the_door_user';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new BeeHotelEwelinkRoleManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger) {
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('messenger'),
    );
  }

  /**
   * Updates self::EWELINK_ROLE roles based on active reservations.
   */
  public function updateRolesFromReservations() {

    $d = [];

    $d['now']['object'] = new DrupalDateTime('now');
    $d['now']['object']->setTimezone(new \DateTimeZone('UTC'));
    $d['now']['formatted'] = $d['now']['object']->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    $d['entity_type'] = "commerce_order_item";
    $d['checkin'] = "field_checkin";
    $d['checkout'] = "field_checkout";

    $d['entity_type_manager'] = \Drupal::entityTypeManager();
    $d['reservation_storage'] = $d['entity_type_manager']->getStorage($d['entity_type']);

    $query = $d['reservation_storage']->getQuery();

    $group = $query->andConditionGroup()
      ->condition('field_checkin', $d['now']['formatted'], '<')
      ->condition('field_checkout', $d['now']['formatted'], '>');
    $query->condition($group);

    $query->accessCheck(FALSE);

    $d['order_item_ids'] = $query->execute();
    $d['order_items'] = $d['reservation_storage']->loadMultiple($d['order_item_ids']);

    // Track users who should have the role.
    $d['users_with_active_reservations'] = [];

    foreach ($d['order_items'] as $order_item) {
      $userAccessManager = \Drupal::service('bee_hotel.ewelink.user_access_manager');

      if (isset($order_item->order_id->entity->mail->value)) {
        $d['uid'] = $userAccessManager->handleUserAccessByEmail($order_item->order_id->entity->mail->value);
        $d['users_with_active_reservations'][$d['uid']] = $d['uid'];
      }

      // $d['uid'] = $userAccessManager->handleUserAccessByEmail($order_item->order_id->entity->mail->value);
      // $d['users_with_active_reservations'][$d['uid']] = $d['uid'];
    }

    // Users who should NOT have role.
    $this->removeRoleFromInactiveUsers($d['users_with_active_reservations']);

    // Process users who should have the role.
    $this->addRoleToUsers($d['users_with_active_reservations']);

  }

  /**
   * Adds self::EWELINK_ROLE role to specified users.
   *
   * @param array $uids
   *   Array of user IDs that should have the role.
   */
  protected function addRoleToUsers(array $uids) {

    $d = [];

    $d['uids'] = $uids;

    if (empty($d['uids'])) {
      return;
    }

    $d['user_storage'] = $this->entityTypeManager->getStorage('user');
    $d['users_can_open_the_doors'] = $d['user_storage']->loadMultiple($d['uids']);

    foreach ($d['users_can_open_the_doors'] as $user) {
      if (!$user->hasRole(self::EWELINK_ROLE)) {
        $user->addRole(self::EWELINK_ROLE);
        $user->save();
      }
    }
  }

  /**
   * Removes self::EWELINK_ROLE role from users not in the active list.
   *
   * @param array $active_uids
   *   Array of user IDs that should keep the role.
   */
  protected function removeRoleFromInactiveUsers(array $active_uids) {

    $d = [];
    $d['user_storage'] = $this->entityTypeManager->getStorage('user');

    // Find all users with self::EWELINK_ROLE role.
    $d['query'] = $d['user_storage']->getQuery()
      ->condition('roles', self::EWELINK_ROLE)
      ->accessCheck(FALSE);

    // @todo leave permission to guests with active reservation.
    if (!empty($active_uids)) {
      $d['query']->condition('uid', $active_uids, 'NOT IN');
    }

    $d['uids'] = $d['query']->execute();

    foreach ($d['user_storage']->loadMultiple($d['uids']) as $user) {
      $user->removeRole(self::EWELINK_ROLE);
      $user->save();
    }
  }

}
