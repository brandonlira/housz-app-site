<?php

namespace Drupal\beehotel_vertical;

use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * A Class to provide features for the VertiCal report.
 */
class BeehotelVertical {

  use StringTranslationTrait;

  /**
   * List of timerange for vertical.
   *
   *  @todo add coming months.
   */
  public function timeRanges() {

    $ranges = [
      'cw' => [
        'label' => $this->t("Current week"),
        'value' => "",
        'rows' => "7",
      ],

      'cm' => [
        'label' => $this->t("Current month"),
        'value' => "",
        'rows' => date("t"),
      ],

      'n15d' => [
        'label' => $this->t("Next 15 days"),
        'value' => "",
        'rows' => 15,
      ],

      'n30d' => [
        'label' => $this->t("Next 30 days"),
        'rows' => 30,
      ],

      'n45d' => [
        'label' => $this->t("Next 45 days"),
        'rows' => 45,
      ],

      'n60d' => [
        'label' => $this->t("Next 60 days"),
        'rows' => 60,
      ],
      'n180d' => [
        'label' => $this->t("Next 180 days"),
        'rows' => 180,
      ],
    ];
    return $ranges;
  }

}
