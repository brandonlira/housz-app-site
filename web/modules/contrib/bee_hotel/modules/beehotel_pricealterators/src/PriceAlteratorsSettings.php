<?php

/**
 * @file
 * This file contains constants for price alterators.
 */

declare(strict_types=1);

namespace Drupal\beehotel_pricealterators;

/**
 * Settings for price Alterators.
 */
enum PriceAlteratorsSettings: int {
  case MaxConsecutiveNights = 32;
  case PricePreviewBase = 100;
}
