<?php

namespace Drupal\bee\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

/**
 * Check access for BEE availability.
 */
class BeeAvailabilityAccessCheck implements AccessInterface {

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a BeeAvailabilityAccessCheck object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * Access method.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   * @param \Drupal\node\Entity\Node $node
   *   A BEE node.
   *
   * @return string
   *   A \Drupal\Core\Access\AccessInterface constant value.
   */
  public function access(AccountInterface $account, Node $node = NULL) {
    if ($node) {
      $nodetypeStorage = $this->entityTypeManager->getStorage('node_type');
      $node_type = $nodetypeStorage->load($node->bundle());

      assert($node_type instanceof NodeType);
      $bee_settings = $node_type->getThirdPartySetting('bee', 'bee');

      if (isset($bee_settings['bookable']) && $bee_settings['bookable']) {
        if ($account->hasPermission('manage availability for all ' . $node->bundle() . ' nodes')) {
          return AccessResult::allowed();
        }

        if ($account->hasPermission('manage availability for own ' . $node->bundle() . ' nodes')) {
          if ($account->id() == $node->getOwnerId()) {
            return AccessResult::allowed();
          }
        }
      }
    }

    return AccessResult::forbidden();
  }

}
