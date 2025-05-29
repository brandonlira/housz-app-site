<?php

namespace Drupal\Tests\commerce_invoice\Kernel;

use Drupal\commerce_invoice\Entity\Invoice;
use Drupal\commerce_invoice\Entity\InvoiceItem;
use Drupal\commerce_invoice\Entity\InvoiceTypeInterface;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Price;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Tests the 'access_check.invoice_order' access checker.
 *
 * @coversDefaultClass \Drupal\commerce_invoice\Access\InvoiceOrderAccessCheck
 *
 * @group commerce_invoice
 */
class InvoiceOrderAccessCheckTest extends OrderIntegrationTest {

  /**
   * @var \Drupal\commerce_invoice\Access\InvoiceOrderAccessCheck
   */
  protected $accessChecker;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->accessChecker = $this->container->get('access_check.invoice_order');
  }

  /**
   * @covers ::access
   */
  public function testAccessWithoutPermission() {
    $access = $this->accessChecker->access($this->getRoute(FALSE), $this->getRouteMatch(), $this->getAccount(FALSE));

    $this->assertFalse($access->isAllowed());
  }

  /**
   * @covers ::access
   */
  public function testAccessForDraftOrders() {
    // The order created in the parent ::setUp() method is in a draft state, so
    // access to create invoices for it shouldn't be allowed.
    $this->assertEquals('draft', $this->order->getState()->getId());
    $access = $this->accessChecker->access($this->getRoute(), $this->getRouteMatch($this->order), $this->getAccount());

    $this->assertFalse($access->isAllowed());
  }

  /**
   * @covers ::access
   */
  public function testAccessWithPartialInvoices() {
    // The parent test class creates an order containing a single order item
    // with a quantity of 1. Increase that quantity to 3 and add an order
    // adjustment so we can test multiple invoices generated for the same order.
    $order_item = $this->order->getItems()[0];
    $order_item->setQuantity(3);
    $this->order->setItems([$order_item]);

    $adjustment = new Adjustment([
      'type' => 'custom',
      'label' => 'Random fee',
      'amount' => new Price('2.00', 'USD'),
    ]);
    $this->order->addAdjustment($adjustment);
    $this->order->getState()->applyTransitionById('place');
    $this->order->save();

    // Create a partial invoice which contains an invoice item with a quantity
    // of 1.
    /** @var \Drupal\commerce_invoice\Entity\InvoiceItemInterface $invoice_item */
    $invoice_item = InvoiceItem::create([
      'type' => 'default',
    ]);
    $invoice_item->populateFromOrderItem($order_item);
    $invoice_item->setQuantity(1);
    $invoice_item->save();

    $invoice = Invoice::create([
      'type' => 'default',
      'store_id' => $this->store->id(),
      'invoice_items' => [$invoice_item],
      'orders' => [$this->order],
    ]);
    $invoice->save();

    // Check that access is still allowed to add invoices this order, since the
    // order contains an extra quantity of 2 for its order item, and the
    // adjustment.
    $access = $this->accessChecker->access($this->getRoute(), $this->getRouteMatch($this->order), $this->getAccount());
    $this->assertTrue($access->isAllowed());

    // Create another partial invoice containing an invoice item with a quantity
    // of 1.
    $invoice_item = InvoiceItem::create([
      'type' => 'default',
    ]);
    $invoice_item->populateFromOrderItem($order_item);
    $invoice_item->setQuantity(1);
    $invoice_item->save();

    $invoice = Invoice::create([
      'type' => 'default',
      'store_id' => $this->store->id(),
      'invoice_items' => [$invoice_item],
      'orders' => [$this->order],
    ]);
    $invoice->save();

    // Check that access is still allowed to add invoices for this order, since
    // the order contains an extra quantity of 1 for its order item, and the
    // adjustment.
    $access = $this->accessChecker->access($this->getRoute(), $this->getRouteMatch($this->order), $this->getAccount());
    $this->assertTrue($access->isAllowed());

    // Create a "final" partial invoice containing an invoice item with the
    // remaining quantity of 1, as well as the adjustment.
    $invoice_item = InvoiceItem::create([
      'type' => 'default',
    ]);
    $invoice_item->populateFromOrderItem($order_item);
    $invoice_item->setQuantity(1);
    $invoice_item->save();

    $invoice = Invoice::create([
      'type' => 'default',
      'store_id' => $this->store->id(),
      'invoice_items' => [$invoice_item],
      'orders' => [$this->order],
      'adjustments' => [$adjustment],
    ]);
    $invoice->save();

    // Check that access is no longer allowed to add invoices for this order,
    // since the three partial invoices now contain invoice items that match the
    // quantity of 3 of the order item, as well as the order adjustment.
    $access = $this->accessChecker->access($this->getRoute(), $this->getRouteMatch($this->order), $this->getAccount());
    $this->assertFalse($access->isAllowed());
  }

  /**
   * Returns a route object for the access check.
   *
   * @param bool $has_requirement
   *   TRUE if the route should have the '_invoice_generate_form_access'
   *   requirement, FALSE otherwise.
   *
   * @return \Symfony\Component\Routing\Route
   *   A test route.
   */
  protected function getRoute($has_requirement = TRUE) {
    if (!$has_requirement) {
      $route = new Route('/foo');
    }
    else {
      $route = new Route('/foo', [], ['_invoice_generate_form_access' => 'TRUE']);
    }
    return $route;
  }

  /**
   * Returns a user account object for the access check.
   *
   * @param bool $has_permission
   *   TRUE if the account should have the 'administer commerce_invoice'
   *   permission, FALSE otherwise.
   *
   * @return \Drupal\Core\Session\AccountInterface|object|\Prophecy\Prophecy\ProphecySubjectInterface
   *   A test user account.
   */
  protected function getAccount($has_permission = TRUE) {
    $account = $this->prophesize(AccountInterface::class);
    $account->hasPermission('administer commerce_invoice')->willReturn($has_permission);
    return $account->reveal();
  }

  /**
   * Returns a route match object for the access check.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface|null $order
   *   A commerce order entity.
   * @param \Drupal\commerce_invoice\Entity\InvoiceTypeInterface|null $invoice_type
   *   An invoice type entity.
   *
   * @return \Drupal\Core\Routing\RouteMatchInterface|object|\Prophecy\Prophecy\ProphecySubjectInterface
   *   A test route match object.
   */
  protected function getRouteMatch($order = NULL, $invoice_type = NULL) {
    if (!$order) {
      $order = $this->prophesize(OrderInterface::class)->reveal();
    }
    if (!$invoice_type) {
      $invoice_type = $this->prophesize(InvoiceTypeInterface::class);
      $invoice_type->id()->willReturn('default');
      $invoice_type = $invoice_type->reveal();
    }

    /** @var \Drupal\Core\Routing\RouteMatchInterface|\Prophecy\Prophecy\ObjectProphecy $route_match */
    $route_match = $this->prophesize(RouteMatchInterface::class);
    $route_match->getRawParameter('commerce_order')->willReturn($order->id());
    $route_match->getParameter('commerce_order')->willReturn($order);
    $route_match->getRawParameter('commerce_invoice_type')->willReturn($invoice_type->id());
    $route_match->getParameter('commerce_invoice_type')->willReturn($invoice_type);

    return $route_match->reveal();
  }

}
