<?php

namespace Drupal\beehotel_pricealterator;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Utilites for PriceAlterator.
 *
 *   This is part of the beehotel package.
 */
class Util {

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

}
