<?php

namespace Drupal\beehotel_pricealterator\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a page with editable price base table.
 */
class UnitBasePriceTable extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new UnitBasePriceTable object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  /**
   * Availability calendar page.
   */
  public function table(NodeInterface $node) {
    return [
      'form' => $this->formBuilder()->getForm('Drupal\beehotel_pricealterator\Form\UnitBasePriceTable', $node),
    ];
  }

  /**
   * The _title_callback for the page that renders the availability.
   */
  public function title(EntityInterface $node) {
    return $this->t('Price table for %label', ['%label' => $node->label()]);
  }

}
