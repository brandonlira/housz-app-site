<?php

namespace Drupal\bat_event_ui\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Description.
 */
class BatEventUIController extends ControllerBase {

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(RendererInterface $renderer) {
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer'),
    );
  }

  /**
   * Description.
   */
  public function calendarPage($unit_type, $event_type) {
    $ev_type = bat_event_type_load($event_type);

    // Check if current type support this event type.
    if ($type = bat_unit_type_load($unit_type)) {
      $type_bundle = bat_type_bundle_load($type->bundle());

      if (is_array($type_bundle->default_event_value_field_ids)) {
        if (!(isset($type_bundle->default_event_value_field_ids[$event_type]) && !empty($type_bundle->default_event_value_field_ids[$event_type]))) {
          throw new NotFoundHttpException();
        }
      }
      else {
        throw new NotFoundHttpException();
      }
    }
    elseif ($unit_type != 'all') {
      throw new NotFoundHttpException();
    }

    // Check if user has permission to view calendar data for this event type.
    if (!($this->currentUser()->hasPermission('view calendar data for any ' . $ev_type->id() . ' event'))) {
      throw new AccessDeniedHttpException();
    }

    // Pick what modal style to use.
    $calendar_settings['modal_style'] = 'default';

    if ($type = bat_event_type_load($event_type)) {
      $event_granularity = $type->getEventGranularity();
    }
    else {
      $event_granularity = 'both';
    }

    // All Drupal JS settings inside the batCalendar object.
    $fc_settings = [
      'batCalendar' => [
        [
          'unitType' => $unit_type,
          'eventType' => $event_type,
          'eventGranularity' => $event_granularity,
        ],
      ],
    ];

    $calendar_settings['fc_settings'] = $fc_settings;
    $calendar_settings['calendar_id'] = 'fullcalendar-scheduler';

    if ($ev_type->getFixedEventStates()) {
      $calendar_settings['class'] = ['fixed_event_states'];
    }
    else {
      $calendar_settings['class'] = ['open_event_states'];
    }

    $render_array = [
      'event_type_form' => $this->formBuilder()->getForm('Drupal\bat_event_ui\Form\BatEventUiEventTypeForm', $unit_type, $event_type),
      'bulk_update_form' => [],
      'calendar' => [
        '#theme' => 'bat_fullcalendar',
        '#calendar_settings' => $calendar_settings,
        '#attached' => [
          'library' => [
            'bat_event_ui/bat_event_ui',
            'bat_fullcalendar/bat-fullcalendar-scheduler',
          ],
        ],
      ],
    ];

    if ($ev_type->getFixedEventStates()) {
      $render_array['bulk_update_form'] = $this->formBuilder()->getForm('Drupal\bat_event_ui\Form\BatEventUiBulkUpdateForm', $unit_type, $event_type);
    }

    $page['calendar_page'] = [
      '#markup' => $this->renderer->render($render_array),
    ];

    return $page;
  }

}
