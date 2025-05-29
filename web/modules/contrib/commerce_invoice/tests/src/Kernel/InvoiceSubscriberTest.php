<?php

namespace Drupal\Tests\commerce_invoice\Kernel;

use Drupal\commerce_invoice\Entity\Invoice;
use Drupal\commerce_invoice\Entity\InvoiceItem;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;

/**
 * Tests the InvoiceSubscriber.
 *
 * @coversDefaultClass \Drupal\commerce_invoice\EventSubscriber\InvoiceSubscriber
 *
 * @group commerce_invoice
 */
class InvoiceSubscriberTest extends InvoiceKernelTestBase {

  /**
   * A sample order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * A sample order item.
   *
   * @var \Drupal\commerce_order\Entity\OrderItemInterface
   */
  protected $orderItem;

  /**
   * A sample user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig('commerce_order');
    $this->installEntitySchema('commerce_order');
    $user = $this->createUser();
    $this->user = $this->reloadEntity($user);

    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 1,
      'unit_price' => new Price('12.00', 'USD'),
    ]);
    $order_item->save();
    $this->orderItem = $this->reloadEntity($order_item);
    $order = Order::create([
      'type' => 'default',
      'state' => 'completed',
      'store_id' => $this->store,
      'uid' => $this->user->id(),
      'order_items' => [$order_item],
    ]);
    $order->save();
    $this->order = $this->reloadEntity($order);
  }

  /**
   * Tests that applying the paid transition sets the invoice total paid.
   */
  public function testPreTransition() {
    $invoice_item = InvoiceItem::create([
      'type' => 'test',
    ]);
    $invoice_item->populateFromOrderItem($this->orderItem);
    $invoice_item->save();
    $invoice = Invoice::create([
      'type' => 'default',
      'store_id' => $this->store->id(),
      'invoice_items' => [$invoice_item],
      'orders' => [$this->order],
    ]);
    $invoice->save();
    $this->assertEquals(new Price('12.00', 'USD'), $invoice->getTotalPrice());
    $this->assertEquals(new Price('0', 'USD'), $invoice->getTotalPaid());
    $this->assertEquals(new Price('0', 'USD'), $this->order->getTotalPaid());
    $invoice->getState()->applyTransitionById('pay');
    $invoice->save();
    $this->assertEquals(new Price('12.00', 'USD'), $invoice->getTotalPaid());
    $this->assertEquals(new Price('12.00', 'USD'), $this->order->getTotalPaid());
  }

  /**
   * Tests that paying an invoice mark the orders as paid.
   */
  public function testPostTransition() {
    $this->assertEquals(new Price('12.00', 'USD'), $this->order->getTotalPrice());
    $invoice_item = InvoiceItem::create([
      'type' => 'test',
    ]);
    $invoice_item->populateFromOrderItem($this->orderItem);
    $invoice_item->save();
    $invoice = Invoice::create([
      'type' => 'default',
      'store_id' => $this->store->id(),
      'invoice_items' => [$invoice_item],
      'orders' => [$this->order],
    ]);
    $invoice->save();
    $this->assertEquals(new Price('12.00', 'USD'), $invoice->getTotalPrice());
    $this->assertEquals(new Price('0', 'USD'), $invoice->getTotalPaid());

    $invoice->setTotalPaid(new Price('10.00', 'USD'));
    $invoice->save();
    $this->assertFalse($invoice->isPaid());
    $this->assertEquals(new Price('0', 'USD'), $this->order->getTotalPaid());

    $invoice->setTotalPaid(new Price('12.00', 'USD'));
    $invoice->save();
    $this->assertTrue($invoice->isPaid());
    $this->order = $this->reloadEntity($this->order);
    $this->assertEquals(new Price('12.00', 'USD'), $this->order->getTotalPaid());
  }

}
