<?php

namespace Drupal\beehotel_vertical\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AlertCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\beehotel_vertical\BeehotelVertical;
use Drupal\beehotel_vertical\Controller\Vertical;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A form to define a time range for Vertical.
 */
class TimeRangeForm extends FormBase {

  /**
   * The BeeHotel vertical service.
   *
   * @var \Drupal\beehotel_vertical\BeehotelVertical
   */
  protected $beehotelVertical;

  /**
   * The vertical controller.
   *
   * @var \Drupal\beehotel_vertical\Controller\Vertical
   */
  protected $verticalController;

  /**
   * Constructs a new TimeRangeForm object.
   *
   * @param \Drupal\beehotel_vertical\BeehotelVertical $beehotel_vertical
   *   The BeeHotel vertical service.
   * @param \Drupal\beehotel_vertical\Controller\Vertical $vertical_controller
   *   The vertical controller.
   */
  public function __construct(BeehotelVertical $beehotel_vertical, Vertical $vertical_controller) {
    $this->beehotelVertical = $beehotel_vertical;
    $this->verticalController = $vertical_controller;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('beehotel_vertical.beehotelvertical'),
      $container->get('beehotel_vertical.controller.vertical')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'beehotel_vertical_timerange';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $session = $this->getRequest()->getSession();
    $range = $session->get('beehotel_requested_range');

    $items = $this->beehotelVertical->timeRanges();
    foreach ($items as $key => $item) {
      $ranges[$key] = $item['label'];
    }

    $form['range'] = [
      '#title' => $this->t('Time range'),
      '#type' => 'select',
      '#options' => $ranges,
      '#default_value' => $range,
      '#attributes' => [
        'class' => ['timerange-title'],
      ],
    ];

    $form['actions']['#type'] = 'actions';

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('OK'),
      '#button_type' => 'primary',
      '#ajax' => [
        'callback' => '::ajaxSubmit',
        'wrapper' => 'vertical-table-container',
        'progress' => ['type' => 'throbber'],
      ],
    ];
    $form['#theme'] = 'vertical_form';
    return $form;
  }

  /**
   * AJAX callback: updates the session and returns the new table HTML.
   */
  public function ajaxSubmit(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    try {
      $range = $form_state->getValue('range');
      $session = $this->getRequest()->getSession();
      $session->set('beehotel_requested_range', $range);

      // Use renderTable() to get only the table HTML (without the form).
      $table_html = $this->verticalController->renderTable([]);
      $response->addCommand(new HtmlCommand('#vertical-table-container', $table_html));
    }
    catch (\Exception $e) {
      $this->logger('beehotel_vertical')->error('Error in TimeRangeForm AJAX callback: @message', [
        '@message' => $e->getMessage(),
      ]);
      $this->logger('beehotel_vertical')->debug('Stack trace: @trace', [
        '@trace' => $e->getTraceAsString(),
      ]);
      $response->addCommand(new AlertCommand($this->t('An error occurred while updating the table. Please check the logs.')));
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Fallback for non‑AJAX submissions.
    if (!$this->getRequest()->isXmlHttpRequest()) {
      $range = $form_state->getValue('range');
      $session = $this->getRequest()->getSession();
      $session->set('beehotel_requested_range', $range);
    }
  }

}
