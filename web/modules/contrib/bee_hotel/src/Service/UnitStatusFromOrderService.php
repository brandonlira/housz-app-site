<?php

namespace Drupal\bee_hotel\Service;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Service for managing hotel unit status based on order information.
 */
class UnitStatusFromOrderService {

  /**
   * Determines the unit status for a given order.
   *
   * @param array $data
   *   A data array with order object and dates.
   *
   * @return string
   *   The unit status with one of three options:
   *   - "today is checkin"
   *   - "today is checkout"
   *   - "running"
   */
  public function getUnitStatus(array $data): array {
    // 1. Check date-based status first
    $dateStatus = $this->getDateBasedStatus($data);
    if ($dateStatus) {
      return $dateStatus;
    }

    // 2. Check custom field state if available
    $customStatus = $this->getCustomFieldStatus($order);
    if ($customStatus) {
      return $customStatus;
    }

    // 3. Default status
    return 'running';
  }

  /**
   * Determines unit status based on checkin/checkout dates.
   *
   * @param array $data
   *   A data array with order object an dates.
   *
   * @return string|null
   *   The unit status or NULL if dates are not available.
   */
  protected function getDateBasedStatus(array $data): ?array {
    if ($this->hasDateFields($order)) {
      $data['checkinDate'] = $this->getCheckinDate($order);
      $data['checkoutDate'] = $this->getCheckoutDate($order);

      if ($data['checkinDate'] && $data['checkoutDate']) {
        $today = new DrupalDateTime('today');

        if ($this->isSameDay($data['checkinDate'], $today)) {
          return 'today is checkin';
        }

        if ($this->isSameDay($data['checkoutDate'], $today)) {
          return 'today is checkout';
        }

        if ($this->isDateBetween($today, $data['checkinDate'], $data['checkoutDate'])) {
          return 'running';
        }
      }
    }

    return NULL;
  }

  /**
   * Gets unit status from custom field_state field.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The commerce order entity.
   *
   * @return string|null
   *   The unit status or NULL if not determined.
   */
  protected function getCustomFieldStatus(OrderInterface $order): ?string {
    if ($order->hasField('field_state') && !$order->get('field_state')->isEmpty()) {
      $state = $order->get('field_state')->value;

      $statusMap = [
        'checkin' => 'today is checkin',
        'checked_in' => 'today is checkin',
        'arrival' => 'today is checkin',
        'checkout' => 'today is checkout',
        'checked_out' => 'today is checkout',
        'departure' => 'today is checkout',
        'in_progress' => 'running',
        'active' => 'running',
        'ongoing' => 'running',
        'stay' => 'running',
      ];

      return $statusMap[$state] ?? NULL;
    }

    return NULL;
  }

  /**
   * Checks if order has required date fields.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The commerce order entity.
   *
   * @return bool
   *   TRUE if date fields exist and are not empty.
   */
  protected function hasDateFields(OrderInterface $order): bool {
    return $order->hasField('checkin_date') &&
           $order->hasField('checkout_date') &&
           !$order->get('checkin_date')->isEmpty() &&
           !$order->get('checkout_date')->isEmpty();
  }

  /**
   * Gets the checkin date from order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The commerce order entity.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime|null
   *   The checkin date or NULL if not available.
   */
  protected function getCheckinDate(OrderInterface $order): ?DrupalDateTime {
    try {
      $checkinValue = $order->get('checkin_date')->value;
      return $checkinValue ? new DrupalDateTime($checkinValue) : NULL;
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * Gets the checkout date from order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The commerce order entity.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime|null
   *   The checkout date or NULL if not available.
   */
  protected function getCheckoutDate(OrderInterface $order): ?DrupalDateTime {
    try {
      $checkoutValue = $order->get('checkout_date')->value;
      return $checkoutValue ? new DrupalDateTime($checkoutValue) : NULL;
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * Checks if two dates represent the same day.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $date1
   *   First date to compare.
   * @param \Drupal\Core\Datetime\DrupalDateTime $date2
   *   Second date to compare.
   *
   * @return bool
   *   TRUE if dates are the same day.
   */
  protected function isSameDay(DrupalDateTime $date1, DrupalDateTime $date2): bool {
    return $date1->format('Y-m-d') === $date2->format('Y-m-d');
  }

  /**
   * Checks if a date is between two other dates (inclusive).
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $date
   *   The date to check.
   * @param \Drupal\Core\Datetime\DrupalDateTime $start
   *   The start date.
   * @param \Drupal\Core\Datetime\DrupalDateTime $end
   *   The end date.
   *
   * @return bool
   *   TRUE if date is between start and end (inclusive).
   */
  protected function isDateBetween(DrupalDateTime $date, DrupalDateTime $start, DrupalDateTime $end): bool {
    $dateTimestamp = $date->getTimestamp();
    $startTimestamp = $start->getTimestamp();
    $endTimestamp = $end->getTimestamp();

    return $dateTimestamp >= $startTimestamp && $dateTimestamp <= $endTimestamp;
  }

}
