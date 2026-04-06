<?php

namespace Drupal\bee_hotel\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'UnitsSearch' Block.
 *
 * @Block(
 *   id = "units_search_block",
 *   admin_label = @Translation("BeeHotel Unit search block"),
 *   category = @Translation("BeeHotel"),
 * )
 */
class UnitsSearchBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = \Drupal::formBuilder()->getForm('\Drupal\bee_hotel\Form\UnitsSearch');
    return $form;
  }

}
