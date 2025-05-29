<?php

namespace Drupal\beehotel_vertical\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\beehotel_vertical\BeehotelVertical;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A form to define a time range for Vertical.
 */
class TimeRangeForm extends FormBase {

  /**
   * The BeeHotel vertical time range.
   *
   * @var \Drupal\beehotel_vertical\BeehotelVertical
   */
  private $beehotelVertical;

  /**
   * Constructs a new Vertical object.
   *
   * @param \Drupal\beehotel_vertical\BeehotelVertical $beehotel_vertical
   *   The BeeHotel Vertical Class for features.
   */
  public function __construct(BeehotelVertical $beehotel_vertical) {
    $this->beehotelVertical = $beehotel_vertical;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('beehotel_vertical.beehotelvertical'),
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
    return "";
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
    ];
    $form['#theme'] = 'vertical_form';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // We will need these values inside preprocess.
    $session = $this->getRequest()->getSession();
    $session->set('beehotel_requested_range', $form_state->getValue('range'));
  }

}
