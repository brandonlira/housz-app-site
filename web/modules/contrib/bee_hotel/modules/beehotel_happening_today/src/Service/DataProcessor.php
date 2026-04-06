<?php

namespace Drupal\beehotel_happening_today\Service;

use Drupal\beehotel_utils\BeeHotelUnit;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;

/**
 * Handles data processing and aggregation.
 */
class DataProcessor {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'beehotel.settings';

  /**
   * The bee hotel unit service.
   *
   * @var \Drupal\beehotel_utils\BeeHotelUnit
   */
  protected $beehotelUnit;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The unit processor service.
   *
   * @var \Drupal\beehotel_happening_today\Service\UnitProcessor
   */
  protected $unitProcessor;

  /**
   * Constructs a new DataProcessor object.
   *
   * @param \Drupal\beehotel_utils\BeeHotelUnit $bee_hotel_unit
   *   The bee hotel unit service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\beehotel_happening_today\Service\UnitProcessor $unit_processor
   *   The unit processor service.
   */
  public function __construct(
    BeeHotelUnit $bee_hotel_unit,
    DateFormatterInterface $date_formatter,
    ConfigFactoryInterface $config_factory,
    UnitProcessor $unit_processor
  ) {
    $this->beehotelUnit = $bee_hotel_unit;
    $this->dateFormatter = $date_formatter;
    $this->configFactory = $config_factory;
    $this->unitProcessor = $unit_processor;
  }

  /**
   * Prepares data for daily report generation.
   *
   * @return array
   *   Structured data array containing:
   *   - day: Date information
   *   - day_before: Previous day information
   *   - units: Array of hotel units
   *   - rooms_to_clean: Rooms requiring cleaning
   *   - special_notes: Special operational notes
   */
  public function prepareData() {
    $data = [];

    // Setup base data.
    $this->setupBaseData($data);

    // Process all units.
    $this->processAllUnits($data);

    // Add additional data.
    $data['rooms_to_clean'] = $this->getRoomsToClean($data);
    $data['special_notes'] = $this->getSpecialNotes($data);

    return $data;
  }

  /**
   * Sets up base data structure for processing.
   *
   * @param array $data
   *   The data array passed by reference.
   */
  private function setupBaseData(array &$data) {
    // Test aside days.

    // Today.
    $data['test']['alter_timestamp'] = 0;

    // 4 days ago.
    //$data['test']['alter_timestamp'] = (24 * 3600) * -4;

    $timestamp = (time() + $data['test']['alter_timestamp']);

    $data['day'] = \Drupal::service('beehotel_utils.dates')->dayArray($timestamp);
    $data['day_before'] = \Drupal::service('beehotel_utils.dates')->dayArray(($timestamp - (24 * 3600)));
    $data['day']['formatted_date'] = $this->dateFormatter->format($timestamp, 'custom', 'j M y');
    $data['units'] = $this->beehotelUnit->getBeeHotelUnits([]);
    $data['configFactory'] = $this->configFactory;
    $data['beehotel_config'] = $this->configFactory->get(self::SETTINGS);
  }

  /**
   * Processes all hotel units.
   *
   * @param array $data
   *   The data array passed by reference.
   */
  private function processAllUnits(array &$data) {
    foreach ($data['units'] as $unit) {
      $this->unitProcessor->processUnit($unit, $data);
    }
  }

  /**
   * Gets rooms that need cleaning.
   *
   * @param array $data
   *   The data array.
   *
   * @return array
   *   Array of rooms requiring cleaning.
   *
   * @todo Implement room cleaning logic.
   */
  public function getRoomsToClean(array $data) {
    return [];
  }

  /**
   * Gets special notes for daily operations.
   *
   * @param array $data
   *   The data array.
   *
   * @return array
   *   Array of special notes.
   *
   * @todo Implement special notes logic.
   */
  public function getSpecialNotes(array $data) {
    return [];
  }

}
