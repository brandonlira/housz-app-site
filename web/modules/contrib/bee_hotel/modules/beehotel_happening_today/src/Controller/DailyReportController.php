<?php

namespace Drupal\beehotel_happening_today\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\beehotel_happening_today\Service\DailyReportGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for daily reports.
 */
class DailyReportController extends ControllerBase {

  protected $dailyReportGenerator;

  public function __construct(DailyReportGenerator $daily_report_generator) {
    $this->dailyReportGenerator = $daily_report_generator;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('beehotel_happening_today.daily_report_generator')
    );
  }

  /**
   * Displays the daily report.
   */
  public function dailyReport() {
    return $this->dailyReportGenerator->generateDailyReport();
  }
}
