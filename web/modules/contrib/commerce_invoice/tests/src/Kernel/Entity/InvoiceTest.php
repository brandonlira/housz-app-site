<?php

namespace Drupal\Tests\commerce_invoice\Kernel\Entity;

use Drupal\commerce_invoice\Entity\Invoice;
use Drupal\commerce_invoice\Entity\InvoiceItem;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_price\Price;
use Drupal\file\Entity\File;
use Drupal\profile\Entity\Profile;
use Drupal\Tests\commerce_invoice\Kernel\InvoiceKernelTestBase;
use Drupal\user\UserInterface;

/**
 * Tests the invoice entity.
 *
 * @coversDefaultClass \Drupal\commerce_invoice\Entity\Invoice
 *
 * @group commerce_invoice
 */
class InvoiceTest extends InvoiceKernelTestBase {

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
  }

  /**
   * Tests the invoice entity and its methods.
   *
   * @covers ::getInvoiceNumber
   * @covers ::setInvoiceNumber
   * @covers ::getStore
   * @covers ::setStore
   * @covers ::getStoreId
   * @covers ::setStoreId
   * @covers ::getCustomer
   * @covers ::setCustomer
   * @covers ::getCustomerId
   * @covers ::setCustomerId
   * @covers ::getEmail
   * @covers ::setEmail
   * @covers ::getBillingProfile
   * @covers ::setBillingProfile
   * @covers ::getOrders
   * @covers ::setOrders
   * @covers ::getItems
   * @covers ::setItems
   * @covers ::hasItems
   * @covers ::addItem
   * @covers ::removeItem
   * @covers ::hasItem
   * @covers ::getAdjustments
   * @covers ::setAdjustments
   * @covers ::addAdjustment
   * @covers ::removeAdjustment
   * @covers ::collectAdjustments
   * @covers ::recalculateTotalPrice
   * @covers ::getPaymentMethod
   * @covers ::setPaymentMethod
   * @covers ::getTotalPrice
   * @covers ::getTotalPaid
   * @covers ::setTotalPaid
   * @covers ::getBalance
   * @covers ::isPaid
   * @covers ::getState
   * @covers ::getData
   * @covers ::setData
   * @covers ::unsetData
   * @covers ::getCreatedTime
   * @covers ::setCreatedTime
   * @covers ::getChangedTime
   * @covers ::setChangedTime
   * @covers ::getInvoiceDateTime
   * @covers ::setInvoiceDateTime
   * @covers ::getDueDateTime
   * @covers ::setDueDateTime
   * @covers ::getFile
   * @covers ::setFile
   */
  public function testInvoice() {
    /** @var \Drupal\profile\Entity\ProfileInterface $profile */
    $profile = Profile::create([
      'type' => 'customer',
      'address' => [
        'country_code' => 'US',
        'postal_code' => '53177',
        'locality' => 'Milwaukee',
        'address_line1' => 'Pabst Blue Ribbon Dr',
        'administrative_area' => 'WI',
        'given_name' => 'Frederick',
        'family_name' => 'Pabst',
      ],
    ]);
    $profile->save();
    $profile = $this->reloadEntity($profile);

    /** @var \Drupal\commerce_invoice\Entity\InvoiceItemInterface $invoice_item */
    $invoice_item = InvoiceItem::create([
      'type' => 'commerce_product_variation',
      'quantity' => '1',
      'unit_price' => new Price('2.00', 'USD'),
    ]);
    $invoice_item->save();
    $invoice_item = $this->reloadEntity($invoice_item);
    /** @var \Drupal\commerce_invoice\Entity\InvoiceItemInterface $another_invoice_item */
    $another_invoice_item = InvoiceItem::create([
      'type' => 'commerce_product_variation',
      'quantity' => '2',
      'unit_price' => new Price('3.00', 'USD'),
    ]);
    $another_invoice_item->save();
    $another_invoice_item = $this->reloadEntity($another_invoice_item);

    /** @var \Drupal\commerce_invoice\Entity\InvoiceInterface $invoice */
    $invoice = Invoice::create([
      'type' => 'default',
      'store_id' => $this->store->id(),
    ]);
    $invoice->save();

    // Assert that saving a draft invoice didn't automatically generate an
    // invoice number.
    $this->assertNull($invoice->getInvoiceNumber());
    $invoice->setInvoiceNumber(7);
    $this->assertEquals(7, $invoice->getInvoiceNumber());

    $invoice->setStore($this->store);
    $this->assertEquals($this->store, $invoice->getStore());
    $this->assertEquals($this->store->id(), $invoice->getStoreId());
    $invoice->setStoreId(0);
    $this->assertEquals(NULL, $invoice->getStore());
    $invoice->setStoreId($this->store->id());
    $this->assertEquals($this->store, $invoice->getStore());
    $this->assertEquals($this->store->id(), $invoice->getStoreId());

    $this->assertInstanceOf(UserInterface::class, $invoice->getCustomer());
    $this->assertTrue($invoice->getCustomer()->isAnonymous());
    $this->assertEquals(0, $invoice->getCustomerId());
    $invoice->setCustomer($this->user);
    $this->assertEquals($this->user, $invoice->getCustomer());
    $this->assertEquals($this->user->id(), $invoice->getCustomerId());
    $this->assertTrue($invoice->getCustomer()->isAuthenticated());
    // Non-existent/deleted user ID.
    $invoice->setCustomerId(888);
    $this->assertInstanceOf(UserInterface::class, $invoice->getCustomer());
    $this->assertTrue($invoice->getCustomer()->isAnonymous());
    $this->assertEquals(888, $invoice->getCustomerId());
    $invoice->setCustomerId($this->user->id());
    $this->assertEquals($this->user, $invoice->getCustomer());
    $this->assertEquals($this->user->id(), $invoice->getCustomerId());

    $invoice->setEmail('commerce@example.com');
    $this->assertEquals('commerce@example.com', $invoice->getEmail());

    $invoice->setBillingProfile($profile);
    $this->assertEquals($profile, $invoice->getBillingProfile());

    $order = Order::create([
      'type' => 'default',
      'state' => 'completed',
    ]);
    $order->save();
    $order = $this->reloadEntity($order);
    $invoice->setOrders([$order]);
    $this->assertEquals([$order], $invoice->getOrders());

    $invoice->setItems([$invoice_item, $another_invoice_item]);
    $this->assertEquals([$invoice_item, $another_invoice_item], $invoice->getItems());
    $this->assertNotEmpty($invoice->hasItems());
    $invoice->removeItem($another_invoice_item);
    $this->assertEquals([$invoice_item], $invoice->getItems());
    $this->assertNotEmpty($invoice->hasItem($invoice_item));
    $this->assertEmpty($invoice->hasItem($another_invoice_item));
    $invoice->addItem($another_invoice_item);
    $this->assertEquals([$invoice_item, $another_invoice_item], $invoice->getItems());
    $this->assertNotEmpty($invoice->hasItem($another_invoice_item));
    $this->assertEquals(new Price('8.00', 'USD'), $invoice->getTotalPrice());

    $adjustments = [];
    $adjustments[] = new Adjustment([
      'type' => 'custom',
      'label' => '10% off',
      'amount' => new Price('-1.00', 'USD'),
    ]);
    $adjustments[] = new Adjustment([
      'type' => 'fee',
      'label' => 'Handling fee',
      'amount' => new Price('10.00', 'USD'),
      'locked' => TRUE,
    ]);
    $invoice->addAdjustment($adjustments[0]);
    $invoice->addAdjustment($adjustments[1]);
    $this->assertEquals($adjustments, $invoice->getAdjustments());
    $this->assertEquals($adjustments, $invoice->getAdjustments(['custom', 'fee']));
    $this->assertEquals([$adjustments[0]], $invoice->getAdjustments(['custom']));
    $this->assertEquals([$adjustments[1]], $invoice->getAdjustments(['fee']));
    $invoice->removeAdjustment($adjustments[0]);
    $this->assertEquals(new Price('8.00', 'USD'), $invoice->getSubtotalPrice());
    $this->assertEquals(new Price('18.00', 'USD'), $invoice->getTotalPrice());
    $this->assertEquals([$adjustments[1]], $invoice->getAdjustments());
    $invoice->setAdjustments($adjustments);
    $this->assertEquals($adjustments, $invoice->getAdjustments());
    $this->assertEquals(new Price('17.00', 'USD'), $invoice->getTotalPrice());

    $this->assertEquals($adjustments, $invoice->collectAdjustments());
    $this->assertEquals($adjustments, $invoice->collectAdjustments(['custom', 'fee']));
    $this->assertEquals([$adjustments[0]], $invoice->collectAdjustments(['custom']));
    $this->assertEquals([$adjustments[1]], $invoice->collectAdjustments(['fee']));

    $invoice->setPaymentMethod('Payment by invoice');
    $this->assertEquals('Payment by invoice', $invoice->getPaymentMethod());

    $this->assertEquals(new Price('0', 'USD'), $invoice->getTotalPaid());
    $this->assertEquals(new Price('17.00', 'USD'), $invoice->getBalance());
    $this->assertFalse($invoice->isPaid());

    $invoice->setTotalPaid(new Price('7.00', 'USD'));
    $this->assertEquals(new Price('7.00', 'USD'), $invoice->getTotalPaid());
    $this->assertEquals(new Price('10.00', 'USD'), $invoice->getBalance());
    $this->assertFalse($invoice->isPaid());

    $invoice->setTotalPaid(new Price('17.00', 'USD'));
    $this->assertEquals(new Price('17.00', 'USD'), $invoice->getTotalPaid());
    $this->assertEquals(new Price('0', 'USD'), $invoice->getBalance());
    $this->assertTrue($invoice->isPaid());

    $invoice->setTotalPaid(new Price('27.00', 'USD'));
    $this->assertEquals(new Price('27.00', 'USD'), $invoice->getTotalPaid());
    $this->assertEquals(new Price('-10.00', 'USD'), $invoice->getBalance());
    $this->assertTrue($invoice->isPaid());

    $this->assertEquals('draft', $invoice->getState()->getId());

    $this->assertEquals('default', $invoice->getData('test', 'default'));
    $invoice->setData('test', 'value');
    $this->assertEquals('value', $invoice->getData('test', 'default'));
    $invoice->unsetData('test');
    $this->assertNull($invoice->getData('test'));
    $this->assertEquals('default', $invoice->getData('test', 'default'));

    $invoice->setCreatedTime(635879700);
    $this->assertEquals(635879700, $invoice->getCreatedTime());

    $invoice->setChangedTime(635879800);
    $this->assertEquals(635879800, $invoice->getChangedTime());

    $invoice->setInvoiceDateTime(635879900);
    $this->assertEquals(635879900, $invoice->getInvoiceDateTime());

    $invoice->setDueDateTime(635879950);
    $this->assertEquals(635879950, $invoice->getDueDateTime());

    $this->assertNull($invoice->getFile());
    $file = File::create([
      'uri' => 'public://invoice.pdf',
      'filename' => 'invoice.pdf',
    ]);
    $file->save();
    $file = $this->reloadEntity($file);
    $invoice->setFile($file);
    $this->assertEquals($invoice->getFile(), $file);
  }

  /**
   * Tests the invoice total recalculation logic.
   *
   * @covers ::recalculateTotalPrice
   */
  public function testTotalCalculation() {
    $invoice = Invoice::create([
      'type' => 'default',
      'store_id' => $this->store->id(),
    ]);
    $invoice->save();

    /** @var \Drupal\commerce_invoice\Entity\InvoiceItemInterface $invoice_item */
    $invoice_item = InvoiceItem::create([
      'type' => 'commerce_product_variation',
      'quantity' => '2',
      'unit_price' => new Price('2.00', 'USD'),
    ]);
    $invoice_item->save();
    $invoice_item = $this->reloadEntity($invoice_item);
    /** @var \Drupal\commerce_invoice\Entity\InvoiceItemInterface $another_invoice_item */
    $another_invoice_item = InvoiceItem::create([
      'type' => 'commerce_product_variation',
      'quantity' => '1',
      'unit_price' => new Price('3.00', 'USD'),
    ]);
    $another_invoice_item->save();
    $another_invoice_item = $this->reloadEntity($another_invoice_item);

    $adjustments = [];
    $adjustments[0] = new Adjustment([
      'type' => 'tax',
      'label' => 'Tax',
      'amount' => new Price('100.00', 'USD'),
      'included' => TRUE,
    ]);
    $adjustments[1] = new Adjustment([
      'type' => 'tax',
      'label' => 'Tax',
      'amount' => new Price('2.121', 'USD'),
      'source_id' => 'us_sales_tax',
    ]);
    $adjustments[2] = new Adjustment([
      'type' => 'tax',
      'label' => 'Tax',
      'amount' => new Price('5.344', 'USD'),
      'source_id' => 'us_sales_tax',
    ]);

    // Included adjustments do not affect the invoice total.
    $invoice->addAdjustment($adjustments[0]);
    $invoice_item->addAdjustment($adjustments[1]);
    $another_invoice_item->addAdjustment($adjustments[2]);
    $invoice->setItems([$invoice_item, $another_invoice_item]);
    $invoice->save();
    /** @var \Drupal\commerce_invoice\Entity\InvoiceInterface $invoice */
    $invoice = $this->reloadEntity($invoice);

    $collected_adjustments = $invoice->collectAdjustments();
    $this->assertCount(3, $collected_adjustments);
    $this->assertEquals($adjustments[1], $collected_adjustments[0]);
    $this->assertEquals($adjustments[2], $collected_adjustments[1]);
    $this->assertEquals($adjustments[0], $collected_adjustments[2]);
    // The total will be correct only if the adjustments were correctly
    // combined, and rounded.
    $this->assertEquals(new Price('14.47', 'USD'), $invoice->getTotalPrice());

    // Test handling deleted invoice items + non-inclusive adjustments.
    $invoice->addAdjustment($adjustments[1]);
    $invoice_item->delete();
    $another_invoice_item->delete();
    $invoice->recalculateTotalPrice();
    $this->assertNull($invoice->getTotalPrice());
  }

  /**
   * Tests that the paid transition is applied when the balance reaches zero.
   */
  public function testPaidTransition() {
    /** @var \Drupal\commerce_invoice\Entity\InvoiceItemInterface $invoice_item */
    $invoice_item = InvoiceItem::create([
      'type' => 'commerce_product_variation',
      'quantity' => '2',
      'unit_price' => new Price('10.00', 'USD'),
    ]);
    $invoice_item->save();
    /** @var \Drupal\commerce_invoice\Entity\InvoiceInterface $invoice */
    $invoice = Invoice::create([
      'type' => 'default',
      'store_id' => $this->store->id(),
      'invoice_items' => [$invoice_item],
    ]);
    $invoice->save();
    $this->assertNull($invoice->getData('invoice_test_called'));
    $this->assertEquals('draft', $invoice->getState()->getId());

    $invoice->setTotalPaid(new Price('20.00', 'USD'));
    $invoice->save();
    $this->assertEquals(1, $invoice->getData('invoice_test_called'));
    $this->assertEquals('paid', $invoice->getState()->getId());

    // Confirm that the event is not dispatched the second time the balance
    // reaches zero.
    $invoice->setTotalPaid(new Price('10.00', 'USD'));
    $invoice->save();
    $invoice->setTotalPaid(new Price('20.00', 'USD'));
    $invoice->save();
    $this->assertEquals(1, $invoice->getData('invoice_test_called'));

    // Confirm that the event is dispatched for invoices created as paid.
    $another_invoice = Invoice::create([
      'type' => 'default',
      'store_id' => $this->store->id(),
      'invoice_items' => [$invoice_item],
      'total_paid' => new Price('20.00', 'USD'),
    ]);
    $another_invoice->save();
    $this->assertEquals(1, $another_invoice->getData('invoice_test_called'));
  }

}
