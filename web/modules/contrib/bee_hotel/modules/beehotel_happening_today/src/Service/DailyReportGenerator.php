<?php

namespace Drupal\beehotel_happening_today\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Main service orchestrator for daily reports.
 */
class DailyReportGenerator {

  use StringTranslationTrait;

  const SETTINGS = 'beehotel.settings';

  protected $dataProcessor;
  protected $reportBuilder;
  protected $configFactory;

  public function __construct(
    DataProcessor $data_processor,
    ReportBuilder $report_builder,
    ConfigFactoryInterface $config_factory
  ) {
    $this->dataProcessor = $data_processor;
    $this->reportBuilder = $report_builder;
    $this->configFactory = $config_factory;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('beehotel_happening_today.data_processor'),
      $container->get('beehotel_happening_today.report_builder'),
      $container->get('config.factory')
    );
  }

  public function generateDailyReport() {
    // Get processed data
    $data = $this->dataProcessor->prepareData();

    // Build and return the report
    return $this->reportBuilder->buildReport($data);
  }
}
