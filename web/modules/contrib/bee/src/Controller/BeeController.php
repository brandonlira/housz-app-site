<?php

namespace Drupal\bee\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\office_hours\OfficeHoursDateHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * A BEE Controller.
 */
class BeeController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The entity type manager.
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
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new BeeController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, RequestStack $request_stack) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('request_stack'),
    );
  }

  /**
   * Availability calendar page.
   */
  public function availability(NodeInterface $node) {

    $batFullCalendarConfig = $this->configFactory->get('bat_fullcalendar.settings');

    // Store node id for more external handling (BAT deleting event).
    $request = $this->requestStack->getCurrentRequest();
    $request->query->set('bee_nid', $node->Id());

    $nodetypeStorage = $this->entityTypeManager->getStorage('node_type');
    $node_type = $nodetypeStorage->load($node->bundle());

    assert($node_type instanceof NodeType);
    $bee_settings = $node_type->getThirdPartySetting('bee', 'bee');

    $unit_type = $bee_settings['type_id'];

    $bat_unit_ids = [];

    foreach ($node->get('field_availability_' . $bee_settings['bookable_type']) as $unit) {
      if ($unit->entity) {
        $bat_unit_ids[] = $unit->entity->id();
      }
    }

    if ($bee_settings['bookable_type'] == 'daily') {

      $event_type = 'availability_daily';
      $event_granularity = 'bat_daily';

      $fc_settings = [
        'batCalendar' => [
          [
            'className' => 'bee-calendar',
            'editable' => FALSE,
            'eventStartEditable' => TRUE,
            'eventType' => $event_type,
            'eventGranularity' => $event_granularity,
            'initialView' => $batFullCalendarConfig->get('bat_fullcalendar_calendar_view_edit'),
            'unitType' => $unit_type,
            'unitIds' => implode(',', $bat_unit_ids),
            'viewsTimelineThirtyDaySlotDuration' => ['days' => 1],
            'sourcescript_a' => __METHOD__,
          ],
        ],
      ];
    }
    else {
      $minTime = FALSE;
      $maxTime = FALSE;
      $hidden_days = [];

      if ($node->get('field_use_open_hours')->value) {
        $business_hours = [];
        $hidden_days = range(0, 6, 1);

        foreach ($node->get('field_open_hours')->getValue() as $value) {
          $day = $value['day'];
          $starthours = OfficeHoursDateHelper::format($value['starthours'], 'H:i:s');
          $endhours = OfficeHoursDateHelper::format($value['endhours'], 'H:i:s');

          $business_hours[] = [
            'dow' => [$day],
            'start' => $starthours,
            'end' => $endhours,
          ];

          if ($minTime == FALSE || strtotime($starthours) < strtotime($minTime)) {
            $minTime = $starthours;
          }
          if ($maxTime == FALSE || strtotime($endhours) < strtotime($maxTime)) {
            $maxTime = $endhours;
          }

          unset($hidden_days[$day]);
        }
      }
      else {
        $business_hours = [
          'start' => '00:00',
          'end' => '24:00',
          'dow' => [0, 1, 2, 3, 4, 5, 6],
        ];
      }

      $event_type = 'availability_hourly';
      $event_granularity = 'bat_hourly';

      $fc_settings = [
        'batCalendar' => [
          [
            'businessHours' => $business_hours,
            'defaultView' => 'timelineDay',
            'editable' => FALSE,
            'eventType' => $event_type,
            'eventGranularity' => $event_granularity,
            'hiddenDays' => array_keys($hidden_days),
            'initialView' => $batFullCalendarConfig->get('bat_fullcalendar_calendar_view_edit'),
            'minTime' => ($minTime) ? $minTime : '00:00:00',
            'maxTime' => ($maxTime) ? $maxTime : '24:00:00',
            'selectConstraint' => 'businessHours',
            'unitType' => $unit_type,
            'unitIds' => implode(',', $bat_unit_ids),
            'views' => 'timelineDay, timelineTenDay, timelineMonth',
            'sourcescript_b' => __METHOD__,
          ],
        ],
      ];
    }

    $fc_settings['batCalendar'][0]['title'] = $node->getTitle();
    $fc_settings['batCalendar'][0]['type'] = $node->type->entity->label();
    $fc_settings['batCalendar'][0]['nid'] = $node->Id();
    $calendar_settings['fc_settings'] = $fc_settings;
    $calendar_settings['calendar_id'] = 'fullcalendar-scheduler';

    $render_array = [
      'calendar' => [
        '#theme' => 'bat_fullcalendar',
        '#calendar_settings' => $calendar_settings,
        '#attached' => [
          'library' => [
            'bat_event_ui/bat_event_ui',
          ],
        ],
      ],
    ];

    return [
      'form' => $this->formBuilder()->getForm('Drupal\bee\Form\UpdateAvailabilityForm', $node),
      'calendar' => $render_array,
    ];
  }

  /**
   * The _title_callback for the page that renders the availability.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   A BEE node.
   *
   * @return string
   *   The page title.
   */
  public function availabilityTitle(EntityInterface $node) {
    return $this->t('Availability for %label', ['%label' => $node->label()]);
  }

  /**
   * The _title_callback for the page that renders the add reservation form.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   A BEE node.
   *
   * @return string
   *   The page title.
   */
  public function addReservationTitle(EntityInterface $node) {
    return $this->t('Create a reservation for %label', ['%label' => $node->label()]);
  }

}
