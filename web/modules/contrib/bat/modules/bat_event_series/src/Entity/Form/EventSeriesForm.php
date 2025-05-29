<?php

namespace Drupal\bat_event_series\Entity\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Form controller for Event edit forms.
 *
 * @ingroup bat
 */
class EventSeriesForm extends ContentEntityForm {

  /**
   * The tempstore object.
   *
   * @var \Drupal\user\SharedTempStore
   */
  protected $tempStore;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Representation of the current HTTP request.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a new EventSeriesForm object.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The entity repository service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date service.
   * @param \Drupal\Core\Form\FormBuilder $formBuilder
   *   The form builder.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory, EntityRepositoryInterface $entity_repository, DateFormatterInterface $date_formatter, FormBuilder $formBuilder, EntityTypeBundleInfoInterface $entity_type_bundle_info = NULL, TimeInterface $time = NULL, RequestStack $request_stack) {
    $this->tempStore = $temp_store_factory->get('event_series_update_confirm');
    $this->dateFormatter = $date_formatter;
    $this->formBuilder = $formBuilder;
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private'),
      $container->get('entity.repository'),
      $container->get('date.formatter'),
      $container->get('form_builder'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $entity = $this->entity;

    if (!$entity->isNew() && isset($form['rrule'])) {
      $form['rrule']['edit'] = [
        '#type' => 'button',
        '#value' => $this->t('Edit repeating rule'),
        '#limit_validation_errors' => [],
        '#ajax' => [
          'callback' => '::editRepeatingRuleFormSubmitAjax',
          'event' => 'click',
        ],
        '#prefix' => '<div>',
        '#suffix' => '</div>',
      ];
    }

    $event_series_type = bat_event_series_type_load($entity->bundle());

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

    if ($event_series_type->getEventGranularity() == 'bat_daily') {
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

    if (isset($form['actions']['delete'])) {
      $form['actions']['delete']['#title'] = $this->t('Delete Event Series');

      $form['actions']['delete_events'] = [
        '#type' => 'link',
        '#title' => $this->t('Delete remaining events in this series'),
        '#url' => Url::fromRoute('entity.bat_event_series.delete_events_form', ['bat_event_series' => $entity->id()]),
        '#attributes' => [
          'class' => ['button', 'button--danger'],
        ],
        '#weight' => 999,
      ];
    }

    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

    //
    // Remove url destination and bring priority at the redirect level.
    // \Drupal::request()->query->remove('destination');
    // \Drupal calls should be avoided in classes.
    // Use dependency injection instead.
    $this->requestStack->query->remove('destination');

    $entity = $this->entity;

    if ($entity->isNew()) {
      $entity->save();

      $this->messenger()->addMessage($this->t('Created the %label Event series.', [
        '%label' => $entity->label(),
      ]));

      $form_state->setRedirect('entity.bat_event_series.edit_form', ['bat_event_series' => $entity->id()]);
    }
    else {
      $this->tempStore->set($this->currentUser()->id(), $entity);

      $form_state->setRedirect('entity.bat_event_series.confirm_edit_form', ['bat_event_series' => $entity->id()]);
    }
  }

  /**
   * Open modal to edit repeating rule.
   */
  public function editRepeatingRuleFormSubmitAjax(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    $modal_form = $this->formBuilder->getForm('Drupal\bat_event_series\Form\EditRepeatingRuleModalForm', $this->entity);
    $modal_form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    $response->addCommand(new OpenModalDialogCommand($this->t('Edit repeating rule'), $modal_form, ['width' => 600]));

    return $response;
  }

}
