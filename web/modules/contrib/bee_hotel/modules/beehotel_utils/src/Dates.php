<?php

namespace Drupal\beehotel_utils;

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

}
