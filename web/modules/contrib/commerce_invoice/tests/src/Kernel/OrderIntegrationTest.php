<?php

namespace Drupal\Tests\commerce_invoice\Kernel;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_product\Entity\ProductVariationType;
use Drupal\profile\Entity\Profile;

/**
 * Tests integration with order events.
 *
 * @group commerce_invoice
 */
class OrderIntegrationTest extends InvoiceKernelTestBase {

  /**
   * A sample order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * The invoice storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $invoiceStorage;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_product',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['commerce_product']);
    $this->installEntitySchema('commerce_product');
    $this->installEntitySchema('commerce_product_variation');
    $user = $this->createUser();
    $this->invoiceStorage = $this->container->get('entity_type.manager')->getStorage('commerce_invoice');

    // Turn off title generation to allow explicit values to be used.
    $variation_type = ProductVariationType::load('default');
    $variation_type->setGenerateTitle(FALSE);
    $variation_type->save();

    $product = Product::create([
      'type' => 'default',
      'title' => 'Default testing product',
    ]);
    $product->save();

    $variation1 = ProductVariation::create([
      'type' => 'default',
      'sku' => 'TEST_' . strtolower($this->randomMachineName()),
      'title' => $this->randomString(),
      'status' => 1,
      'price' => new Price('12.00', 'USD'),
    ]);
    $variation1->save();
    $product->addVariation($variation1)->save();

    $profile = Profile::create([
      'type' => 'customer',
    ]);
    $profile->save();
    $profile = $this->reloadEntity($profile);

    /** @var \Drupal\commerce_order\OrderItemStorageInterface $order_item_storage */
    $order_item_storage = $this->container->get('entity_type.manager')->getStorage('commerce_order_item');

    $order_item1 = $order_item_storage->createFromPurchasableEntity($variation1);
    $order_item1->save();
    $order = Order::create([
      'type' => 'default',
      'store_id' => $this->store->id(),
      'state' => 'draft',
      'mail' => $user->getEmail(),
      'uid' => $user->id(),
      'ip_address' => '127.0.0.1',
      'order_number' => '6',
      'billing_profile' => $profile,
      'order_items' => [$order_item1],
    ]);
    $order->save();
    $this->order = $this->reloadEntity($order);
  }

  /**
   * Tests that an invoice is generated when an order is placed.
   */
  public function testPlace() {
    // Ensure that no invoice is generated when the automatic invoice generation
    // is turned off for this order type.
    $this->order->getState()->applyTransitionById('place');
    $this->order->save();
    $invoices = $this->invoiceStorage->loadMultiple();
    $this->assertEquals(0, count($invoices));

    $order_type = OrderType::load('default');
    $order_type->setThirdPartySetting('commerce_invoice', 'invoice_type', 'default');
    $order_type->setThirdPartySetting('commerce_invoice', 'order_placed_generation', TRUE);
    $order_type->save();

    $this->order = $this->reloadEntity($this->order);
    $this->order->set('state', 'draft');
    $this->order->setRefreshState(OrderInterface::REFRESH_SKIP);
    $this->order->save();
    $this->order->getState()->applyTransitionById('place');
    $this->order->save();
    $this->order = $this->reloadEntity($this->order);
    $invoices = $this->invoiceStorage->loadMultiple();
    $this->assertEquals(1, count($invoices));

    /** @var \Drupal\commerce_invoice\Entity\InvoiceInterface $invoice */
    $invoice = reset($invoices);
    $invoice_billing_profile = $invoice->getBillingProfile();
    $this->assertNotEmpty($invoice->getBillingProfile());
    $this->assertTrue($invoice_billing_profile->equalToProfile($this->order->getBillingProfile()));
    $this->assertEquals($this->order->getCustomerId(), $invoice->getCustomerId());

    $this->assertEquals([$this->order], $invoice->getOrders());
    $this->assertEquals($this->store, $invoice->getStore());
    $this->assertEquals($this->order->getTotalPrice(), $invoice->getTotalPrice());
    $this->assertCount(1, $invoice->getItems());
    $this->assertCount(0, $invoice->getAdjustments());
  }

  /**
   * Tests that the invoice total paid is updated when an order is paid.
   */
  public function testPaid() {
    $order_type = OrderType::load('default');
    $order_type->setThirdPartySetting('commerce_invoice', 'invoice_type', 'default');
    $order_type->setThirdPartySetting('commerce_invoice', 'order_placed_generation', TRUE);
    $order_type->save();
    $this->order->getState()->applyTransitionById('place');
    $this->order->save();
    $invoices = $this->invoiceStorage->loadMultiple();
    $this->assertEquals(1, count($invoices));

    /** @var \Drupal\commerce_invoice\Entity\InvoiceInterface $invoice */
    $invoice = reset($invoices);
    $this->assertEquals(new Price('0.0', 'USD'), $invoice->getTotalPaid());
    $this->order->setTotalPaid($this->order->getTotalPrice());
    $this->order->save();

    $this->assertEquals($this->order->getTotalPaid(), $invoice->getTotalPaid());
  }

}
