<?php

namespace Drupal\bee_hotel\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;

/**
 * Provides a 'UnitsSearch' Block.
 *
 * @Block(
 *   id = "units_search_block",
 *   admin_label = @Translation("Unit search block"),
 *   category = @Translation("Unit search block"),
 * )
 */
class UnitsSearchBlock extends BlockBase {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a EntityEmbedDialog object.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The Form Builder.
   */
  public function __construct(FormBuilderInterface $form_builder) {
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $form = $this->formBuilder->getForm('Drupal\bee_hotel\Form\UnitsSearch');
    return $form;
  }

}
