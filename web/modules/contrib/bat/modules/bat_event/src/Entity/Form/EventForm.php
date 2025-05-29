<?php

namespace Drupal\bat_event\Entity\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Messenger\MessengerInterface;
// Feb25: implement bat_roomify.
use Drupal\bat_roomify\Calendar\Calendar;
use Drupal\bat_roomify\Store\DrupalDBStore;
use Drupal\bat_roomify\Unit\Unit;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Form controller for Event edit forms.
 *
 * @ingroup bat
 */
class EventForm extends ContentEntityForm {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The current Request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Include the messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;


  /**
   * The DrupalDBStore from Roomify.
   *
   * @var Drupal\bat_roomify\Store\DrupalDBStore
   */
  protected $drupalDBStore;

  /**
   * Constructs a EventForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date service.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\bat_roomify\Store\DrupalDBStore $drupal_db_store
   *   The bat event manager.
   */
  public function __construct(
    EntityRepositoryInterface $entity_repository,
    DateFormatterInterface $date_formatter,
    Request $request,
    EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL,
    TimeInterface $time = NULL,
    MessengerInterface $messenger,
    DrupalDBStore $drupal_db_store
    ) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->dateFormatter = $date_formatter;
    $this->request = $request;
    $this->messenger = $messenger;
    $this->drupalDBStore = $drupal_db_store;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('date.formatter'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('messenger'),
      $container->get('bat_roomify.drupaldbstore')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form = parent::buildForm($form, $form_state);
    $entity = $this->entity;

    $event_type = bat_event_type_load($entity->bundle());

    $form['changed'] = [
      '#type' => 'hidden',
      '#default_value' => $entity->getChangedTime(),
    ];

    $form['#theme'] = ['bat_entity_edit_form'];
    $form['#attached']['library'][] = 'bat/bat_ui';

    $form['advanced'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['entity-meta']],
      '#weight' => 99,
    ];

    $is_new = !$entity->isNew() ? $this->dateFormatter->format($entity->getChangedTime(), 'short') : $this->t('Not saved yet');
    $form['meta'] = [
      '#attributes' => ['class' => ['entity-meta__header']],
      '#type' => 'container',
      '#group' => 'advanced',
      '#weight' => -100,
      'changed' => [
        '#type' => 'item',
        '#wrapper_attributes' => [
          'class' => [
            'entity-meta__last-saved',
            'container-inline',
          ],
        ],
        '#markup' => '<h4 class="label inline">' . $this->t('Last saved') . '</h4> ' . $is_new,
      ],
      'author' => [
        '#type' => 'item',
        '#wrapper_attributes' => ['class' => ['author', 'container-inline']],
        '#markup' => '<h4 class="label inline">' . $this->t('Author') . '</h4> ' . $entity->getOwner()->getDisplayName(),
      ],
    ];

    $form['author'] = [
      '#type' => 'details',
      '#title' => $this->t('Authoring information'),
      '#group' => 'advanced',
      '#attributes' => [
        'class' => ['type-form-author'],
      ],
      '#weight' => 90,
      '#optional' => TRUE,
      '#open' => TRUE,
    ];

    if (isset($form['uid'])) {
      $form['uid']['#group'] = 'author';
    }

    if (isset($form['created'])) {
      $form['created']['#group'] = 'author';
    }

    if ($event_type->getEventGranularity() == 'bat_daily') {
      $form['event_dates']['widget'][0]['value']['#date_time_element'] = 'none';
      $form['event_dates']['widget'][0]['end_value']['#date_time_element'] = 'none';
    }
    else {

      $widget_type = bat_get_entity_display(
          $entity->getEntityTypeId(),
          $entity->bundle(), 'form'
      )->getComponent('event_dates')['type'];

      // Don't allow entering seconds with the default daterange widget.
      if ($widget_type == 'daterange_default') {
        $form['event_dates']['widget'][0]['value']['#date_increment'] = 60;
        $form['event_dates']['widget'][0]['end_value']['#date_increment'] = 60;
      }
    }

    $form['event_dates']['widget'][0]['value']['#date_timezone'] = 'UTC';
    $form['event_dates']['widget'][0]['end_value']['#date_timezone'] = 'UTC';

    if (isset($form['event_dates']['widget'][0]['value']['#default_value'])) {
      $form['event_dates']['widget'][0]['value']['#default_value']->setTimezone(new \DateTimeZone('UTC'));
    }
    if (isset($form['event_dates']['widget'][0]['end_value']['#default_value'])) {
      $form['event_dates']['widget'][0]['end_value']['#default_value']->setTimezone(new \DateTimeZone('UTC'));
    }

    if ($this->request->query->get(MainContentViewSubscriber::WRAPPER_FORMAT) == 'drupal_ajax') {
      $form['actions']['submit']['#attributes']['class'][] = 'use-ajax-submit';
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    parent::validateForm($form, $form_state);

    $entity = $this->entity;
    $event_type = bat_event_type_load($entity->bundle());

    $values = $form_state->getValues();

    $start_date = new \DateTime($values['event_dates'][0]['value']->format('Y-m-d H:i:s'));
    $end_date = new \DateTime($values['event_dates'][0]['end_value']->format('Y-m-d H:i:s'));

    // The end date must be greater or equal than start date.
    if ($end_date < $start_date) {
      $form_state->setErrorByName('event_dates', $this->t('End date must be on or after the start date.'));
    }

    $event_type = bat_event_type_load($this->entity->bundle());
    $target_field_name = 'event_' . $event_type->getTargetEntityType() . '_reference';

    if ($event_type->getFixedEventStates()) {
      if ($values[$target_field_name][0]['target_id'] != '') {
        $database = Database::getConnectionInfo('default');

        $prefix = (isset($database['default']['prefix']['default'])) ? $database['default']['prefix']['default'] : '';

        $event_store = new DrupalDBStore($this->entity->bundle(), DrupalDBStore::BAT_EVENT, $prefix);

        $end_date->sub(new \DateInterval('PT1M'));

        $bat_units = [
          new Unit($values[$target_field_name][0]['target_id'], 0),
        ];

        $calendar = new Calendar($bat_units, $event_store);

        $events = $calendar->getEvents($start_date, $end_date);

        if (isset($target_field_name) && isset($values) && isset($events)) {
          $tmp = $values[$target_field_name][0]['target_id'];
          if(isset($tmp)) {
            $tmp = $events[$tmp];
            foreach ($tmp as $event) {
              $event_id = $event->getValue();

              if ($event_id != $this->entity->id()) {
                if ($event = bat_event_load($event_id)) {
                  $state = $event->get('event_state_reference')->entity;

                  if ($state->getBlocking()) {
                    $form_state->setErrorByName('', $this->t('Cannot save this event as an event in a blocking state exists within the same timeframe.'));
                    break;
                  }
                }
              }
            }
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

    $data = [];

    $data['event'] = $this->entity;
    $data['event_type'] = bat_event_type_load($data['event']->bundle());
    $data['values'] = $form_state->getValues();

    $data['start_date'] = new \DateTime($data['values']['event_dates'][0]['value']->format('Y-m-d H:i:s'));
    $data['end_date'] = new \DateTime($data['values']['event_dates'][0]['end_value']->format('Y-m-d H:i:s'));

    $data['event']->setStartDate($data['start_date']);
    $data['event']->setEndDate($data['end_date']);

    $data['event']->save();

    switch ($data['event']->isNew()) {

      // Feb25: label replaced by uid (not every event has label).
      case TRUE:
        $this->messenger()->addMessage($this->t('Created Event #%label.', [
          '%label' => $data['event']->get('id')->value,
        ]));
        break;

      default:
        $this->messenger()->addMessage($this->t('Saved Event %label.', [
          '%label' => $data['event']->get('id')->value,
        ]));
    }

    $form_state->setRedirect('entity.bat_event.edit_form', ['bat_event' => $data['event']->id()]);
  }

}
