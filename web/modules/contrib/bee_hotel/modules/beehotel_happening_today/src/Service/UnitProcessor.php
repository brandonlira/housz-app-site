<?php

namespace Drupal\beehotel_happening_today\Service;

/**
 * Processes individual units and their booking status.
 */
class UnitProcessor {

  protected $orderService;
  // protected $batService;

  public function __construct(
    OrderService $order_service,
    // BatService $bat_service
  ) {
    $this->orderService = $order_service;
    // $this->batService = $bat_service;
  }

  public function processUnit($unit, &$data) {

    $beehotelBatService = \Drupal::service('bee_hotel.beehotelbat');
    $data['unit']['node'] = $unit;
    $data['unit']['nid'] = $data['unit']['node']->Id();
    $data['unit']['title'] = $data['unit']['node']->getTitle();
    $data['unit']['bid'] = $unit->get("field_availability_daily")->target_id;

    // dump ($data);

    // $data = [
    //   'node' => $unit,
    //   'nid' => $unit->Id(),
    //   'title' => $unit->getTitle(),
    //   'bid' => $unit->get("field_availability_daily")->target_id,
    // ];


    // BAT event ID for a given unit, given day.
    //$data['bat']['event_id'] = 999;
    // $data['bat']['event_id'] = $beehotelBatService->getUnitDateBatEventId($data);


    // Get BAT event
    // $data['bat']['event_id'] = $beehotelBatService->getUnitDateBatEventId($data['day']);
    // $data['bat']['event_id'] = $beehotelBatService->getUnitDateBatEventId($data['day']);



    // BAT event ID for a given unit, given day.
    $beehotelBatService = \Drupal::service('bee_hotel.beehotelbat');
    //$data['bat']['event_id'] = 999;
    $data['bat']['event_id'] = $beehotelBatService->getUnitDateBatEventId($data);


    // Get order data
    $this->orderService->populateOrderData($data);

    // Process booking status
    $this->processBookingStatus($data);
  }

  private function processBookingStatus(&$data) {
    // Check-in today

    // dump ($data);

    if ($this->isCheckingInToday($data)) {
      $data['arrivals'][] = $this->orderService->buildCheckingInData($data);
    }

    // First night passed
    if ($this->isFirstNightPassed($data)) {
      $data['first_night_passed'][] = $this->orderService->buildFirstNightPassedData($data);
    }

    // Check-out today
    if ($this->isCheckingOutToday($data)) {
      $data['departures'][] = $this->orderService->buildCheckingOutData($data);
    }

    // In progress
    if ($this->isInProgress($data)) {
      $data['progress'][] = $this->orderService->buildInProgressData($data);

      // Check if leaving tomorrow
      if ($this->isCheckingOutTomorrow($data)) {
        $data['are_leaving_tomorrow'][] = $this->orderService->buildCheckingOutTomorrowData($data);
      }
    }
  }

  private function isCheckingInToday($data) {
    return isset($data['commerce']['order']) &&
           $data['commerce']['order']['dates']['checkin']->format('Y-m-d') === $data['day']['object']->format('Y-m-d');
  }

  private function isFirstNightPassed($data) {
    return isset($data['commerce']['order']) &&
           isset($data['beehotel']['day_before_order']) &&
           isset($data['beehotel']['day_before_order']['object']->checkin) &&
           $data['beehotel']['day_before_order']['object']->checkin === $data['day']['daybefore']['ISO8601'];
  }

  private function isCheckingOutToday($data) {
    return isset($data['beehotel']['day_before_order']['object']) &&
           isset($data['beehotel']['day_before_order']['object']->checkout) &&
           $data['beehotel']['day_before_order']['object']->checkout === $data['day']['object']->format('Y-m-d');
  }

  private function isInProgress($data) {
    return isset($data['commerce']['order']) &&
           $data['day']['object']->format('Y-m-d') >= $data['commerce']['order']['dates']['checkin']->format('Y-m-d') &&
           $data['day']['object']->format('Y-m-d') <= $data['commerce']['order']['dates']['checkout']->format('Y-m-d');
  }

  private function isCheckingOutTomorrow($data) {
    return $data['day']['dayafter']['ISO8601'] === $data['commerce']['order']['dates']['checkout']->format('Y-m-d');
  }
}
