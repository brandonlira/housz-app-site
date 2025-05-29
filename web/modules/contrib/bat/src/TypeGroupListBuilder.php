<?php

namespace Drupal\bat;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of Type Group entities.
 *
 * @ingroup bat
 */
class TypeGroupListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id())
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'id' => [
        'data' => $this->t('Type Group ID'),
        'field' => 'id',
        'specifier' => 'id',
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'name' => [
        'data' => $this->t('Name'),
        'field' => 'name',
        'specifier' => 'name',
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'type' => [
        'data' => $this->t('Bundle (Type)'),
        'field' => 'type',
        'specifier' => 'type',
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
      'status' => [
        'data' => $this->t('Status'),
        'field' => 'status',
        'specifier' => 'status',
        'class' => [RESPONSIVE_PRIORITY_LOW],
      ],
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['id'] = $entity->id();
    $row['name'] = Link::fromTextAndUrl(
      $entity->label(),
      new Url(
        'entity.bat_type_group.edit_form', [
          'bat_type_group' => $entity->id(),
        ]
      )
    );
    $row['bundle'] = bat_type_group_bundle_load($entity->bundle())->label();
    $row['status'] = ($entity->getStatus()) ? $this->t('Published') : $this->t('Unpublished');
    return $row + parent::buildRow($entity);
  }

}
