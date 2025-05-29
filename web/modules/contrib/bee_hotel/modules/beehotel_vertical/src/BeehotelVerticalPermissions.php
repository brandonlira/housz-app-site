<?php

namespace Drupal\beehotel_vertical;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Class to manage vertical permissions.
 */
class BeehotelVerticalPermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * Constructs a new BeeHotelVerticalPermissions instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL) {
    $this->entityTypeManager = $entity_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * Manage permissions.
   */
  public function permissions() {
    $permissions = [];
    $bundles = $this->entityTypeBundleInfo->getAllBundleInfo();

    $permissions['view beehotel_vertical all'] = [
      'title' => $this->t('View BeeHotel Vertical Every Unit'),
    ];

    foreach ($bundles['node'] as $bundle_name => $bundle_info) {
      $permissions['view unit vertical ' . $bundle_name . ' nodes'] = [
        'title' => $this->t('SUSP - View unit Vertical calendar for all %bundle nodes', ['%bundle' => $bundle_info['label']]),
      ];

      $permissions['view unit vertical for own ' . $bundle_name . ' nodes'] = [
        'title' => $this->t('SUSP - View unit Vertical calendar for own %bundle nodes', ['%bundle' => $bundle_info['label']]),
      ];
    }

    $permissions['administer vertical settings'] = [
      'title' => $this->t('Access to the Vertical settings form', []),
    ];

    return $permissions;
  }

}
