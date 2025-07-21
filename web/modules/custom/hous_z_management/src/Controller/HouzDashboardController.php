<?php

namespace Drupal\hous_z_management\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides the Houz management dashboard.
 */
class HouzDashboardController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a HouzDashboardController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Displays the dashboard.
   */
  public function dashboard() {
    $build = [];

    // Dashboard cards
    $build['dashboard'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['houz-dashboard']],
    ];

    // Quick stats
    $unit_count = $this->entityTypeManager
      ->getStorage('bat_unit')
      ->getQuery()
      ->accessCheck(TRUE)
      ->count()
      ->execute();

    $booking_count = $this->entityTypeManager
      ->getStorage('bat_booking')
      ->getQuery()
      ->accessCheck(TRUE)
      ->count()
      ->execute();

    $build['dashboard']['stats'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['houz-stats']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Quick Stats'),
      ],
      'units' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Total Units: @count', ['@count' => $unit_count]),
      ],
      'bookings' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Total Bookings: @count', ['@count' => $booking_count]),
      ],
    ];

    // Quick actions
    $build['dashboard']['actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['houz-actions']],
      'title' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $this->t('Quick Actions'),
      ],
      'bookings_link' => [
        '#type' => 'link',
        '#title' => $this->t('Manage Bookings'),
        '#url' => Url::fromRoute('hous_z_management.bookings'),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
      'units_link' => [
        '#type' => 'link',
        '#title' => $this->t('Manage Units'),
        '#url' => Url::fromRoute('hous_z_management.units'),
        '#attributes' => ['class' => ['button']],
      ],
    ];

    $build['#attached']['library'][] = 'hous_z_management/dashboard';

    return $build;
  }

  /**
   * Displays the bookings management page.
   */
  public function bookings() {
    $build = [];

    // Embed the bookings view
    $view = $this->entityTypeManager
      ->getStorage('view')
      ->load('houz_bookings');

    if ($view) {
      $build['view'] = [
        '#type' => 'view',
        '#name' => 'houz_bookings',
        '#display_id' => 'default',
      ];
    }
    else {
      $build['message'] = [
        '#type' => 'markup',
        '#markup' => $this->t('Bookings view not found. Creating...'),
      ];
    }

    $build['#attached']['library'][] = 'hous_z_management/dashboard';

    return $build;
  }

  /**
   * Displays the units management page.
   */
  public function units() {
    $build = [];

    // Embed the units view
    $view = $this->entityTypeManager
      ->getStorage('view')
      ->load('units');

    if ($view) {
      $build['view'] = [
        '#type' => 'view',
        '#name' => 'units',
        '#display_id' => 'default',
      ];
    }
    else {
      $build['message'] = [
        '#type' => 'markup',
        '#markup' => $this->t('Units view not found.'),
      ];
    }

    // Add links for unit management
    $build['unit_actions'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['unit-actions']],
      'add_unit' => [
        '#type' => 'link',
        '#title' => $this->t('Add New Unit'),
        '#url' => Url::fromRoute('entity.bat_unit.add_page'),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
      'manage_all_units' => [
        '#type' => 'link',
        '#title' => $this->t('Manage All Units'),
        '#url' => Url::fromRoute('entity.bat_unit.collection'),
        '#attributes' => ['class' => ['button']],
      ],
    ];

    return $build;
  }

}