<?php

namespace Drupal\bat_fullcalendar;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\bat_event\EventTypeInterface;
use Drupal\bat_roomify\Event\EventInterface;
use Drupal\bat_roomify\EventFormatter\AbstractEventFormatter;

/**
 * Description message.
 */
class FullCalendarFixedStateEventFormatter extends AbstractEventFormatter {

  /**
   * The event type.
   *
   * @var \Drupal\bat_event\EventTypeInterface
   */
  protected $eventType;

  /**
   * Print as background event.
   *
   * @var bool
   */
  protected $background;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Description message.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(AccountInterface $current_user, ModuleHandlerInterface $module_handler) {
    $this->background = TRUE;
    $this->currentUser = $current_user;
    $this->moduleHandler = $module_handler;
  }

  /**
   * Description message.
   *
   * @param \Drupal\bat_event\EventTypeInterface $event_type
   *   The event type.
   */
  public function setEventType(EventTypeInterface $event_type) {
    $this->eventType = $event_type;
  }

  /**
   * Description message.
   *
   * @param bool $background
   *   The event type.
   */
  public function setBackground($background) {
    $this->background = $background;
  }

  /**
   * {@inheritdoc}
   */
  public function format(EventInterface $event) {
    $data = [];
    $data['event'] = $event;
    $data['editable'] = FALSE;

    // Load the unit entity from Drupal.
    $data['bat_unit'] = bat_unit_load($event->getUnitId());

    // Get the unit entity default value.
    $data['default_value'] = (int) $data['bat_unit']->getEventDefaultValue($this->eventType->id());
    $this->eventLabel($data);

    $formatted_event = [
      'start' => $event->startYear() . '-' . $event->startMonth('m') . '-' . $event->startDay('d') . 'T' . $event->startHour('H') . ':' . $event->startMinute() . ':00',
      'end' => $event->endYear() . '-' . $event->endMonth('m') . '-' . $event->endDay('d') . 'T' . $event->endHour('H') . ':' . $event->endMinute() . ':00',
      'title' => $data['calendar_label'],
      'color' => isset($data['state_info']) ? $data['state_info']->getColor() : '#B4B4B4',
      'blocking' => 1,
      'fixed' => 1,
      'editable' => $data['editable'],
    ];

    // Render non blocking events in the background.
    if (isset($data['state_info']) && !$data['state_info']->getBlocking()) {
      if ($this->background) {
        $formatted_event['rendering'] = 'background';
      }
      $formatted_event['blocking'] = 0;
    }

    $formatted_event['type'] = $this->eventType->id();

    // Allow other modules to alter the event data.
    $this->moduleHandler->alter('bat_fullcalendar_formatted_event', $formatted_event);

    return $formatted_event;
  }

  /**
   * Produce a label for the event.
   *
   * @param array $data
   *   Needed data.
   */
  private function eventLabel(array &$data) {

    // See https://www.drupal.org/files/issues/2020-04-05/3125109.missing_states.patch
    $data['calendar_label'] = '';

    // Get default state info to provide default value for formatting.
    if ($data['state_info'] = bat_event_load_state($data['event']->getValue())) {
      $data['calendar_label'] = $data['state_info']->getCalendarLabel();
    }

    // However if the event is in the database,
    // then load the actual event and get its value.
    // @todo mar25 get this value from event->getEventLabel()?
    if ($data['calendar_label'] = $data['bat_unit']->get('name')->value) {
      return;
    }
    elseif ($data['event']->getValue()) {
      // Load the event from the database to get the actual.
      // state and load that info.
      if ($data['bat_event'] = bat_event_load($data['event']->getValue())) {

        // See https://www.drupal.org/files/issues/2023-05-25/bat-missing_states-3125109-7.patch
        $data['state_info'] = bat_event_load_state($data['bat_event']->getEventValue());

        if ($data['event_label'] = $data['bat_event']->getEventLabel()) {
          $data['calendar_label'] = $data['event_label'];
        }
        elseif (isset($data['state_info'])) {
          $data['calendar_label'] = $data['state_info']->getCalendarLabel();
        }

        else {
          // Feb25.
          $data['calendar_label'] = "---";
        }

        if (bat_event_access($data['bat_event'], 'update', $this->currentUser)->isAllowed()) {
          $data['editable'] = TRUE;
        }
      }
    }
    return $data;
  }

}
