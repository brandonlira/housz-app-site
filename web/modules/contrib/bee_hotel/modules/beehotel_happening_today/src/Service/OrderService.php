<?php

namespace Drupal\beehotel_happening_today\Service;

use Drupal\commerce_log\LogStorageInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Handles order-related operations and data.
 */
class OrderService {

  use StringTranslationTrait;

  /**
   * Populates order data in the main data array.
   *
   * @param array $data
   *   The data array passed by reference.
   */
  public function populateOrderData(array &$data) {
    $beehotelOrderService = \Drupal::service('bee_hotel.order');

    $data['commerce']['order'] = $beehotelOrderService->getOrderFromBatEvent($data['bat']['event_id']);
    $data['beehotel']['day_before_order'] = $beehotelOrderService->getDayBeforeOrder($data);

    $this->generateOrderLinks($data);
    $this->extractNumberOfPeople($data);
    $this->calculateCheckInTime($data);
    $this->populateCommentData($data);
  }

  /**
   * Generates order-related URLs.
   *
   * @param array $data
   *   The data array passed by reference.
   */
  public function generateOrderLinks(array &$data) {
    if (!isset($data['commerce']['order'])) {
      return;
    }

    $order = $data['commerce']['order']['object'];
    $orderId = $order->get('order_id')->value;

    $data['view_order_url'] = Url::fromRoute('entity.commerce_order.canonical', ['commerce_order' => $orderId])->toString();
    $data['edit_order_url'] = Url::fromRoute('entity.commerce_order.edit_form', ['commerce_order' => $orderId])->toString();
    $data['guest_messages_order_url'] = Url::fromRoute('entity.commerce_order.guest_messages', ['commerce_order' => $orderId])->toString();
    $data['edit_order_text'] = $this->t('#@number', ['@number' => $order->get("order_number")->value]);
  }

  /**
   * Extracts number of people from order.
   *
   * @param array $data
   *   The data array passed by reference.
   */
  public function extractNumberOfPeople(array &$data) {
    $data['people'] = 0;

    if (!isset($data['commerce']['order']['object'])) {
      return;
    }

    try {
      $order_items = $data['commerce']['order']['object']->get('order_items')->referencedEntities();

      if (!empty($order_items)) {
        $product_title = $order_items[0]->get('title')->value;
        $pattern = '/(\d+)\s*(?:Guests?|People|Persons?|Adults?|Pax)/i';

        if (preg_match($pattern, $product_title, $matches)) {
          $data['people'] = (int) $matches[1];
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('beehotel_happening_today')->error('Error extracting number of people: @error', [
        '@error' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Calculates check-in time.
   *
   * @param array $data
   *   The data array passed by reference.
   */
  public function calculateCheckInTime(array &$data) {
    $data['check_in_time'] = $data['configFactory']
      ->get('beehotel.settings')
      ->get('beehotel')['dateandtime']['default_checkin_time'] ?? "???";

    if (isset($data['commerce']['order']['object']) &&
        $data['commerce']['order']['object']->hasField('field_announced_time_of_arrival') &&
        !$data['commerce']['order']['object']->get('field_announced_time_of_arrival')->isEmpty()) {

      $data['check_in_time'] = $data['commerce']['order']['object']
        ->get('field_announced_time_of_arrival')
        ->value;
    }
  }

  /**
   * Builds data for checking-in bookings.
   *
   * @param array $data
   *   The data array.
   *
   * @return array
   *   Structured data for checking-in bookings.
   */
  public function buildCheckingInData(array $data) {
    return [
      'booking_id' => $data['edit_order_text'] ?? "",
      'booking_link' => $data['view_order_url'] ?? "",
      'check_in_time' => $data['check_in_time'] ?? "",
      'comment_count' => $data['comment_count'] ?? 0,
      'comments' => $data['comments'] ?? [],
      'event_id' => $data['bat']['event_id'] ?? "",
      'end_date' => $data['commerce']['order']['dates']['checkout']->format('d-m-Y') ?? "",
      'nights_stayed' => $data['commerce']['order']['dates']['nights'] ?? "",
      'people' => $data['people'] ?? "",
      'start_date' => $data['commerce']['order']['dates']['checkin']->format('d-m-Y') ?? "",
      'unit_id' => $data['unit']['node']->Id() ?? "",
      'unit_name' => $data['unit']['node']->getTitle() ?? "",
    ];
  }

  /**
   * Builds data for in-progress bookings.
   *
   * @param array $data
   *   The data array.
   *
   * @return array
   *   Structured data for in-progress bookings.
   */
  public function buildInProgressData(array $data) {
    return [
      'event_id' => $data['bat']['event_id'] ?? "",
      'booking_id' => $data['commerce']['order']['object']->order_number->value ?? "",
      'booking_link' => $data['view_order_url'] ?? "",
      'comment_count' => $data['comment_count'] ?? 0,
      'comments' => $data['comments'] ?? [],
      'unit_id' => $data['unit']['node']->Id() ?? "",
      'unit_name' => $data['unit']['node']->getTitle() ?? "",
      'start_date' => $data['commerce']['order']['dates']['checkin']->format('d-m-Y') ?? "",
      'end_date' => $data['commerce']['order']['dates']['checkout']->format('d-m-Y') ?? "",
      'check_out_time' => '10:00am',
      'nights_stayed' => $data['commerce']['order']['object']->nights ?? "",
    ];
  }

  /**
   * Builds data for checking-out tomorrow bookings.
   *
   * @param array $data
   *   The data array.
   *
   * @return array
   *   Structured data for checking-out tomorrow bookings.
   */
  public function buildCheckingOutTomorrowData(array $data) {
    return [
      'event_id' => $data['bat']['event_id'] ?? "",
      'booking_id' => $data['edit_order_text'] ?? "",
      'booking_link' => $data['view_order_url'] ?? "",
      'booking_guest_messages' => $data['guest_messages_order_url'] ?? "",
      'comment_count' => $data['comment_count'] ?? 0,
      'comments' => $data['comments'] ?? [],
      'unit_id' => $data['unit']['node']->Id() ?? "",
      'unit_name' => $data['unit']['node']->getTitle() ?? "",
      'start_date' => $data['commerce']['order']['dates']['checkin']->format('d-m-Y') ?? "",
      'end_date' => $data['commerce']['order']['dates']['checkout']->format('d-m-Y') ?? "",
      'nights_stayed' => $data['commerce']['order']['dates']['nights'] ?? "",
      'people' => $data['people'] ?? "",
    ];
  }

  /**
   * Builds data for first night passed bookings.
   *
   * @param array $data
   *   The data array.
   *
   * @return array
   *   Structured data for first night passed bookings.
   */
  public function buildFirstNightPassedData(array $data) {
    return [
      'event_id' => $data['bat']['event_id'] ?? "",
      'booking_id' => $data['edit_order_text'] ?? "",
      'booking_link' => $data['view_order_url'] ?? "",
      'booking_guest_messages' => $data['guest_messages_order_url'] ?? "",
      'comment_count' => $data['comment_count'] ?? 0,
      'comments' => $data['comments'] ?? [],
      'unit_id' => $data['unit']['node']->Id() ?? "",
      'unit_name' => $data['unit']['node']->getTitle() ?? "",
      'start_date' => $data['commerce']['order']['dates']['checkin']->format('d-m-Y') ?? "",
      'end_date' => $data['commerce']['order']['dates']['checkout']->format('d-m-Y') ?? "",
      'nights_stayed' => $data['commerce']['order']['dates']['nights'] ?? "",
      'people' => $data['people'] ?? "",
    ];
  }

  /**
   * Builds data for checking-out bookings.
   *
   * @param array $data
   *   The data array.
   *
   * @return array
   *   Structured data for checking-out bookings.
   */
  public function buildCheckingOutData(array $data) {
    return [
      'booking_id' => $data['edit_order_text'] ?? "",
      'booking_link' => $data['view_order_url'] ?? "",
      'check_out_time' => '10:00am',
      'comment_count' => $data['comment_count'] ?? 0,
      'comments' => $data['comments'] ?? [],
      'end_date' => $data['beehotel']['day_before_order']['object']->checkout ?? "",
      'event_id' => $data['bat']['event_id'] ?? "",
      'guest_messages_order_url' => $data['guest_messages_order_url'] ?? "",
      'nights_stayed' => $data['beehotel']['day_before_order']['object']->nights ?? "",
      'start_date' => $data['beehotel']['day_before_order']['object']->checkin ?? "",
      'unit_id' => $data['unit']['node']->Id() ?? "",
      'unit_name' => $data['unit']['node']->getTitle() ?? "",
    ];
  }

  /**
   * Counts order comments from activity log.
   *
   * @param object $order
   *   The commerce order object.
   *
   * @return int
   *   The number of order comments.
   */
  public function countOrderComments($order) {
    if (!$order) {
      return 0;
    }

    try {
      /** @var \Drupal\commerce_log\LogStorageInterface $logStorage */
      $logStorage = \Drupal::entityTypeManager()->getStorage('commerce_log');

      $query = $logStorage->getQuery()
        ->accessCheck(FALSE)
        ->condition('source_entity_id', $order->id())
        ->condition('source_entity_type', 'commerce_order')
        ->condition('category_id', 'commerce_order');

      $logIds = $query->execute();

      return count($logIds);
    }
    catch (\Exception $e) {
      \Drupal::logger('beehotel_happening_today')->error('Error counting order comments: @error', [
        '@error' => $e->getMessage(),
      ]);
      return 0;
    }
  }

  /**
   * Gets order comments content from activity log.
   *
   * @param object $order
   *   The commerce order object.
   *
   * @return array
   *   Array of comment data.
   */
  public function getOrderComments($order) {
    $comments = [];

    if (!$order) {
      return $comments;
    }

    try {
      /** @var \Drupal\commerce_log\LogStorageInterface $logStorage */
      $logStorage = \Drupal::entityTypeManager()->getStorage('commerce_log');

      $query = $logStorage->getQuery()
        ->accessCheck(FALSE)
        ->condition('source_entity_id', $order->id())
        ->condition('source_entity_type', 'commerce_order')
        ->condition('category_id', 'commerce_order')
        ->condition('template_id', 'commerce_order_comment')
        ->sort('created', 'ASC');

      $logIds = $query->execute();
      $logs = $logStorage->loadMultiple($logIds);

      foreach ($logs as $log) {
        if ($log->hasField('comment') && !$log->get('comment')->isEmpty()) {
          $comment = $log->get('comment')->value;
          if (!empty(trim($comment))) {
            $comments[] = [
              'comment' => $comment,
              'author' => $log->getOwner()->getDisplayName(),
              'created' => \Drupal::service('date.formatter')->format($log->getCreatedTime(), 'short'),
              'timestamp' => $log->getCreatedTime(),
            ];
          }
        }
      }

      return $comments;
    }
    catch (\Exception $e) {
      \Drupal::logger('beehotel_happening_today')->error('Error getting order comments: @error', [
        '@error' => $e->getMessage(),
      ]);
      return [];
    }
  }

  /**
   * Populates comment data in the main data array.
   *
   * @param array $data
   *   The data array passed by reference.
   */
  public function populateCommentData(array &$data) {
    if (!isset($data['commerce']['order']['object'])) {
      $data['comment_count'] = 0;
      $data['comments'] = [];
      return;
    }

    $data['comment_count'] = $this->countOrderComments($data['commerce']['order']['object']);
    $data['comments'] = $this->getOrderComments($data['commerce']['order']['object']);
  }

}
