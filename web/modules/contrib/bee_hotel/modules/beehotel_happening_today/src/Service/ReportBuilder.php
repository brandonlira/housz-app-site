<?php

namespace Drupal\beehotel_happening_today\Service;

/**
 * Builds the final report render array.
 */
class ReportBuilder {

  public function buildReport($data) {
    return [
      '#theme' => 'daily_report_main',
      '#are_leaving_tomorrow' => $data['are_leaving_tomorrow'] ?? [],
      '#day_of_the_week' => $data['day']['l'],
      '#date' => $data['day']['formatted_date'],
      '#arrivals' => $data['arrivals'] ?? [],
      '#first_night_passed' => $data['first_night_passed'] ?? [],
      '#departures' => $data['departures'] ?? [],
      '#progress' => $data['progress'] ?? [],
      '#special_notes' => $data['special_notes'] ?? [],
      '#generated_time' => date('d M Y H:i'),
      '#attached' => [
        'library' => [
          'beehotel_happening_today/daily_report',
        ],
      ],
      '#cache' => [
        'max-age' => 3600,
        'contexts' => ['url'],
      ],
    ];
  }
}
