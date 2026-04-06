<?php

namespace Drupal\beehotel_utils;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\StringTranslation\StringTranslationTrait;
/**
 * Utilitis for dates management.
 */
class Dates {

  use StringTranslationTrait;

  /**
   * Days for the base table.
   *
   * @return array
   *   a list of days with HR index and label.
   */
  public function days() {
    // First day of the week may chagen by the country (Sun or Mon)
    // we use HR index to keeps things clear.
    $days = [
      'mon' => $this->t("Monday"),
      'tue' => $this->t("Tuesday"),
      'wed' => $this->t("Wednesay"),
      'thu' => $this->t("Thursday"),
      'fri' => $this->t("Friday"),
      'sat' => $this->t("Saturday"),
      'sun' => $this->t("Sunday"),
    ];
    return $days;
  }

  /**
   * Normalise input to feed Bee Hotel.
   *
   * The resutling array should be the one place where bee hotel dates
   * are stored to de used along the complete reservation process.
   *
   * @todo a better integration with /admin/config/regional/date-time.
   *
   * @param array $data
   *   An array with input data.
   *   Dates come from the litepicker input form (from - to).
   *   An enriched $data with normalized data is returned.
   */
  public function normaliseDatesFromSearchForm(array &$data) {

    // @todo place this inside some bee hotel config form.
    $checkintime = "15:00:00";
    $checkouttime = "10:00:00";

    $tmp = explode("-", $data['values']['dates']);

    $tmp['first_night'] = strtotime(trim($tmp[0]));
    $tmp['check_in_from'] = strtotime(trim($tmp[0] . " " . $checkintime));

    // Subtract one minute to get one day back.
    $tmp['last_night'] = strtotime(trim($tmp[1])) - 60;

    // Subtract one minute to get one day back.
    $tmp['check_out'] = strtotime(trim($tmp[1]));

    // Subtract one minute to get one day back.
    $tmp['check_out_at'] = strtotime(trim($tmp[1] . " " . $checkouttime));

    $dates = [];

    // Check-in.
    $dates = $this->createCheckinForData($tmp);

    // Check-out.
    $dates['checkout'] = [];
    $dates['checkout']["Y-m-d"] = date("Y-m-d", $tmp['check_out']);
    $dates['checkout']["Y-m-d-H-i-s"] = date("Y-m-d\TH:i:s", $tmp['check_out_at']);
    $dates['checkout']["timestamp"] = $tmp['check_out_at'];
    $dates['checkout']['dM'] = date("d M", $tmp['check_out']);
    $dates['checkout']["locale"]['short'] = date("d M y", $tmp['check_out']);
    $dates['checkout']["locale"]['long'] = date("d F Y", $tmp['check_out']);
    $dates['checkout']['object']['day'] = new \DateTime($dates['checkout']["Y-m-d"]);
    $dates['checkout']['object']['day-time'] = new \DateTime($dates['checkout']["Y-m-d-H-i-s"]);

    // Last night.
    $dates['lastnight'] = [];

    $dates['lastnight']["Y-m-d"] = date("Y-m-d", $tmp['last_night']);
    $dates['lastnight']["Y-m-d-H-i-s"] = date("Y-m-d\TH:i:s", $tmp['last_night']);
    $dates['lastnight']['dM'] = date("d M", $tmp['last_night']);
    $dates['lastnight']["locale"]['short'] = date("d M y", $tmp['last_night']);
    $dates['lastnight']["locale"]['long'] = date("d F Y", $tmp['last_night']);
    $dates['lastnight']['object'] = new \DateTime($dates['lastnight']["Y-m-d-H-i-s"]);

    $dates['interval'] = $dates['checkout']['object']["day"]->diff($dates['checkin']['object']["day"]);
    $dates['days'] = $dates['interval']->days;

    $data['norm']['dates_from_search_form'] = $dates;

  }

  /**
   * Checkin data as array.
   *
   * This methos will be consumed from more submodules as the
   * price alterator plugin manage. We may need a more generical
   * method to handle more critial moments as checkout, etc.
   */
  public function createCheckinForData($tmp) {

    $dates = [];
    $dates['checkin'] = [];
    $dates['checkin']["Y-m-d"] = date("Y-m-d", $tmp['check_in_from']);
    $dates['checkin']["Y-m-d-H-i-s"] = date("Y-m-d\TH:i:s", $tmp['check_in_from']);
    $dates['checkin']["l"] = date("l", $tmp['first_night']);
    $dates['checkin']['dM'] = date("d M", $tmp['first_night']);
    $dates['checkin']["D"] = date("D", $tmp['first_night']);
    $dates['checkin']["n"] = date("n", $tmp['first_night']);
    $dates['checkin']["j"] = date("j", $tmp['first_night']);
    $dates['checkin']["timestamp"] = $tmp['check_in_from'];
    $dates['checkin']["locale"]['short'] = date("d M y", $tmp['first_night']);
    $dates['checkin']["locale"]['long'] = date("d F Y", $tmp['first_night']);
    $dates['checkin']['object']['day'] = new \DateTime($dates['checkin']["Y-m-d"]);
    $dates['checkin']['object']['day-time'] = new \DateTime($dates['checkin']["Y-m-d-H-i-s"]);

    return $dates;
  }

  /**
   * Add easter day to data.
   *
   * @param array $data
   *   An array with input data.
   */
  public function easter(array &$data) {

    $tmp = explode("-", $data['values']['dates']);
    $tmp['first_night'] = strtotime(trim($tmp[0]));

    $dates['timestamp'] = easter_date(date("Y", $tmp['first_night']));
    $dates["Y-m-d"] = date("Y-m-d", $dates['timestamp']);
    $dates["n"] = date("n", $dates['timestamp']);
    $dates["j"] = date("j", $dates['timestamp']);

    $data['norm']['easter'] = $dates;
  }

  /**
   * Prepare data with uri query input.
   *
   * @todo improve input validation for url input (IE: "in > out")
   * @todo a better integration with /admin/config/regional/date-time.
   *
   * @param array $data
   *   An array with input data.
   *   Dates come from the litepicker input form ( from - to ).
   *   Return an enriched \$data array with normalized data.
   */
  public function fromRequestToLitepicker(array &$data) {

    if (count($data['request_values']['pieces']) == 7) {
      $data['value_for_litepicker']['tmp'] =
        trim($data['request_values']['pieces'][2]) . "-" .
        trim($data['request_values']['pieces'][1]) . "-" .
        trim($data['request_values']['pieces'][0]);
      $data['value_for_litepicker']['tmp'] = strtotime(trim($data['value_for_litepicker']['tmp']));

      $data['value_for_litepicker']['begin'] = date("d M Y", $data['value_for_litepicker']['tmp']);

      $data['value_for_litepicker']['tmp'] =
        trim($data['request_values']['pieces'][5]) . "-" .
        trim($data['request_values']['pieces'][4]) . "-" .
        trim($data['request_values']['pieces'][3]);
      $data['value_for_litepicker']['tmp'] = strtotime(trim($data['value_for_litepicker']['tmp']));
      $data['value_for_litepicker']['end'] = date("d M Y", $data['value_for_litepicker']['tmp']);

      $data['value_for_litepicker']['input'] = $data['value_for_litepicker']['begin'] . " - " . $data['value_for_litepicker']['end'];
      $data['default_values']['dates'] = $data['value_for_litepicker']['input'];
    }
  }

  /**
 * Generates a comprehensive date array from a timestamp in various formats.
 *
 * This function takes a Unix timestamp and returns an array containing
 * the date information formatted in multiple commonly used formats.
 * The returned array includes ISO, month, year, and full weekday formats
 * for flexible use in different contexts.
 *
 * @param int $timestamp
 *   The Unix timestamp to convert to date formats. This is a required parameter
 *   and must be a valid positive integer representing a Unix timestamp.
 *
 * @return array
 *   An associative array containing the date in various formats with the
 *   following structure:
 *   - 'm': Two-digit numeric representation of the month (01 to 12)
 *   - 'Y': Four-digit representation of the year (e.g., 2025)
 *   - 'l': Full textual representation of the day of the week (e.g., "Monday")
 *   - 'ISO8601': ISO 8601 date format (Y-m-d, e.g., "2025-10-21")
 *   - 'timestamp': The original input timestamp for reference
 *   - 'd': Two-digit day of the month (01 to 31)
 *   - 'F': Full textual representation of the month (e.g., "October")
 *   - 'D': Short textual representation of the day of the week (e.g., "Mon")
 *
 * @Example:
 * @code
 *   $timestamp = 1761091140; // October 21, 2025
 *   $dayArray = dayArray($timestamp);
 *
 *   // Resulting array:
 *   // [
 *   //   'm' => '10',
 *   //   'Y' => '2025',
 *   //   'l' => 'Tuesday',
 *   //   'ISO' => '2025-10-21',
 *   //   'timestamp' => 1761091140,
 *   //   'd' => '21',
 *   //   'F' => 'October',
 *   //   'D' => 'Tue'
 *   // ]
 * @endcode
 *
 * @see \Drupal\Core\Datetime\DrupalDateTime::createFromTimestamp()
 * @see https://www.php.net/manual/en/function.date.php
 * @see hook_date_formats()
 */
  public function dayArray($timestamp) {
    $dateFormatter = \Drupal::service('date.formatter');

    $day['d'] = $dateFormatter->format($timestamp, 'custom', 'd');
    $day['day'] = $dateFormatter->format($timestamp, 'custom', 'j');
    $day['month'] = $dateFormatter->format($timestamp, 'custom', 'n');
    $day['m'] = $dateFormatter->format($timestamp, 'custom', 'm');
    $day['year'] = $dateFormatter->format($timestamp, 'custom', 'Y');
    $day['l'] = $dateFormatter->format($timestamp, 'custom', 'l');
    // ISO 8601
    $day['today']['ISO8601'] = $day['year'] . "-" . $day['m'] . "-" . $day['d'];
    $day['day_of_elaboration']['ISO8601'] = $dateFormatter->format(time(), 'custom', 'Y-m-d');

    $day['daybefore']['ISO8601'] = date('Y-m-d', strtotime("-1 day", strtotime($day['today']['ISO8601'])));
    $day['daybefore']['timestamp'] = strtotime($day['daybefore']['ISO8601']);

    $day['dayafter']['ISO8601'] = date('Y-m-d', strtotime("+1 day", strtotime($day['today']['ISO8601'])));
    $day['dayafter']['timestamp'] = strtotime($day['dayafter']['ISO8601']);

    $day['object'] = new DrupalDateTime($day['today']['ISO8601']);

    return $day;

  }


}
