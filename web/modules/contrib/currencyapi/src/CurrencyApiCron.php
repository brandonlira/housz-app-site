<?php

namespace Drupal\currencyapi;

use Drupal\Core\State\StateInterface;

/**
 * Provides currency exchange rates from CurrencyAPI.com.
 */
class CurrencyApiCron {

  /**
   * Constructs a new FileExampleHelper object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state.
   */
  public function __construct(protected StateInterface $state) {
    $this->state = $state;
  }

  /**
   * Cron the task.
   */
  public function cron() {

    $d = [];

    $d['now'] = \Drupal::time()->getRequestTime();

    if ($this->shouldRun($d['now']) == TRUE) {
      $this->queueTasks();
      $d['config'] = \Drupal::service('config.factory')->getEditable('currencyapi.settings');
      $d['config']->set('last_update', $d['now'])->save();
    }
  }

  /**
   * Check is to be run.
   */
  public function shouldRun($now) {

    $d = [];

    $d['now'] = $now;
    $d['config'] = \Drupal::config('currencyapi.settings');
    $d['timestamp_last'] = $d['config']->get('last_update') ?? 0;
    $d['wait_for'] = (86400 * 1);

    if ($d['now'] > ($d['timestamp_last'] + $d['wait_for'])) {
      return TRUE;
    }
    else {
      return FALSE;
    }

  }

  /**
   * Queue manager.
   */
  public function queueTasks() {
    $d = [];
    $d['service'] = \Drupal::service('currencyapi.service');
    $d['fetch_rates'] = $d['service']->fetchExchangeRates();
  }

}
