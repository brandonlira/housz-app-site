<?php

namespace Drupal\beehotel_addtocart;

use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Add to Cart  features for BEE HOTEL.
 */
class AddToCart {

  /**
   * The order item storage.
   *
   * @var \Drupal\commerce_order\OrderItemStorageInterface
   */
  protected $orderItemStorage;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The cart manager.
   *
   * @var \Drupal\commerce_cart\CartManagerInterface
   */
  protected $cartManager;

  /**
   * The cart provider.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;

  /**
   * Constructs a new AddReservationForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\commerce_cart\CartManagerInterface|null $cart_manager
   *   The cart manager.
   * @param \Drupal\commerce_cart\CartProviderInterface|null $cart_provider
   *   The cart provider.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, ?CartManagerInterface $cart_manager, ?CartProviderInterface $cart_provider) {
    if ($entity_type_manager->hasHandler('commerce_order_item', 'storage')) {
      $this->orderItemStorage = $entity_type_manager->getStorage('commerce_order_item');
    }
    $this->configFactory = $config_factory;
    $this->cartManager = $cart_manager;
    $this->cartProvider = $cart_provider;
  }

  /**
   * We dont know this method is being used...
   */
  public function add($data) {}

}
