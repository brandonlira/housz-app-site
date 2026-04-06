<?php

namespace Drupal\beehotel_addtocart\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\commerce_product\Entity\Product;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\commerce_cart\CartProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller routines for products routes.
 */
class BeeHotelAddToCart extends ControllerBase {

  /**
   * The cart provider.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new CartController object.
   *
   * @param \Drupal\commerce_cart\CartProviderInterface $cart_provider
   *   The cart provider.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(CartProviderInterface $cart_provider, EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->cartProvider = $cart_provider;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_cart.cart_provider'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * Adds a product to Cart.
   */
  public function addToCart($productId) {

    $productObj = Product::load($productId);
    $storeId = $productObj->get('stores')->getValue()[0]['target_id'];
    $store = $this->entityTypeManager->getStorage('commerce_store')->load($storeId);

    $cart = $this->cartProvider->getCart('default', $store);

    if (!$cart) {
      $cart = $this->cartProvider->createCart('default', $store);
    }

    $response = new RedirectResponse(Url::fromRoute('commerce_cart.page')->toString());
    return $response;

  }

}
