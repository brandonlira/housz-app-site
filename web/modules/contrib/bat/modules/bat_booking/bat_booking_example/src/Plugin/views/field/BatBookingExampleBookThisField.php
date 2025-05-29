<?php

namespace Drupal\bat_booking_example\Plugin\views\field;

use Drupal\Core\Link;
use Drupal\views\ResultRow;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Description message.
 *
 * @ViewsField("bat_booking_example_book_this_field")
 */
class BatBookingExampleBookThisField extends FieldPluginBase {

  /**
   * The RequestStack service.
   *
   * @var Symfony\Component\HttpFoundation\RequestStack
   */
  private $requestStack;

  /**
   * The constructor object.
   *
   *   Some description.
   *
   * @param Symfony\Component\HttpFoundation\RequestStack $stack
   *   The request service.
   */
  public function __construct(RequestStack $stack) {
    $this->requestStack = $stack;
  }

  /**
   * This Method misses a description.
   */
  public function render(ResultRow $values) {
    $bat_start_date = $this->requestStack->getCurrentRequest()->query->get('bat_start_date');
    $bat_end_date = $this->requestStack->getCurrentRequest()->query->get('bat_end_date');
    return Link::fromTextAndUrl($this->t('Book this'), 'booking/' . $bat_start_date . '/' . $bat_end_date . '/' . $this->getEntity($values)->id());
  }

}
