<?php

namespace Drupal\bee_hotel\Breadcrumb;

use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Provides custom breadcrumbs for Bee Hotel order pages.
 */
class BeeHotelBreadcrumbBuilder implements BreadcrumbBuilderInterface {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match, ?CacheableMetadata $cacheable_metadata = NULL) {

    // dump ("QQQQQ");
    // exit;

    // Add cache context for route
    $cacheable_metadata?->addCacheContexts(['route']);

    $route_name = $route_match->getRouteName();
    $path = \Drupal::service('path.current')->getPath();

    // Method 1: Check by entity parameter (most reliable)
    if ($route_match->getParameter('commerce_order') instanceof OrderInterface) {
      return TRUE;
    }

    // Method 2: Check by route name
    $commerce_routes = [
      'entity.commerce_order.canonical',
      'entity.commerce_order.edit_form',
      'entity.commerce_order.collection',
      'view.commerce_orders.page_1',
      'commerce_order.admin',
      'entity.commerce_order.admin_index',
    ];

    if (in_array($route_name, $commerce_routes)) {
      return TRUE;
    }

    // Method 3: Check by path pattern (for custom paths)
    $path_patterns = [
      '/admin/commerce/orders/',
      '/beehotel/vertical/order/',
    ];

    foreach ($path_patterns as $pattern) {
      if (strpos($path, $pattern) === 0) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    // Create new breadcrumb
    $breadcrumb = new Breadcrumb();

    // Add cache contexts
    $breadcrumb->addCacheContexts(['route', 'url.path', 'user']);

    // Get current info
    $route_name = $route_match->getRouteName();
    $path = \Drupal::service('path.current')->getPath();

    // Get order ID
    $order_id = $this->extractOrderId($route_match, $path);

    // Check if this is a collection/list page (no specific order)
    $is_collection_page = $this->isCollectionPage($route_name);

    // Build breadcrumb
    $breadcrumb->addLink(Link::createFromRoute($this->t('Home'), '<front>'));
    $breadcrumb->addLink(Link::createFromRoute($this->t('Bee Hotel'), $this->getBeeHotelRoute()));
    $breadcrumb->addLink(Link::createFromRoute($this->t('Vertical'), $this->getVerticalRoute()));

    if ($order_id && !$is_collection_page) {
      // For specific order pages
      $breadcrumb->addLink(Link::createFromRoute(
        $this->t('Order #@id', ['@id' => $order_id]),
        '<none>'
      ));

      // Add cache dependency for the order if we have it
      $order = $route_match->getParameter('commerce_order');
      if ($order instanceof OrderInterface) {
        $breadcrumb->addCacheableDependency($order);
      }
    } else {
      // For collection pages
      $breadcrumb->addLink(Link::createFromRoute(
        $this->t('Orders'),
        '<none>'
      ));
    }

    return $breadcrumb;
  }

  /**
   * Extract order ID from route match or path.
   */
  protected function extractOrderId(RouteMatchInterface $route_match, string $path): ?string {
    // Try to get from commerce_order parameter
    $order = $route_match->getParameter('commerce_order');
    if ($order instanceof OrderInterface) {
      return $order->id();
    }

    // Try from path patterns
    $path_patterns = [
      '/admin/commerce/orders/',
      '/beehotel/vertical/order/',
    ];

    foreach ($path_patterns as $pattern) {
      if (strpos($path, $pattern) === 0 && preg_match('/(\d+)/', $path, $matches)) {
        return $matches[1];
      }
    }

    return NULL;
  }

  /**
   * Check if this is a collection/list page.
   */
  protected function isCollectionPage(string $route_name): bool {
    $collection_routes = [
      'entity.commerce_order.collection',
      'view.commerce_orders.page_1',
      'commerce_order.admin',
      'entity.commerce_order.admin_index',
    ];

    return in_array($route_name, $collection_routes);
  }

  /**
   * Get the Bee Hotel route.
   */
  protected function getBeeHotelRoute(): string {
    return 'beehotel.admin';
  }

  /**
   * Get the Vertical route.
   */
  protected function getVerticalRoute(): string {
    return 'beehotel_vertical.vertical';
  }

}
