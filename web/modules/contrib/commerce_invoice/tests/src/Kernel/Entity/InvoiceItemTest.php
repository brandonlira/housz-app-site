<?php

namespace Drupal\Tests\commerce_invoice\Kernel\Entity;

use Drupal\commerce_invoice\Entity\InvoiceItem;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\Tests\commerce_invoice\Kernel\InvoiceKernelTestBase;

/**
 * Tests the invoice item entity.
 *
 * @coversDefaultClass \Drupal\commerce_invoice\Entity\InvoiceItem
 *
 * @group commerce_invoice
 */
class InvoiceItemTest extends InvoiceKernelTestBase {

  /**
   * Tests the invoice item entity and its methods.
   *
   * @covers ::getTitle
   * @covers ::setTitle
   * @covers ::getDescription
   * @covers ::setDescription
   * @covers ::getFormat
   * @covers ::setFormat
   * @covers ::getQuantity
   * @covers ::setQuantity
   * @covers ::getUnitPrice
   * @covers ::setUnitPrice
   * @covers ::getTotalPrice
   * @covers ::recalculateTotalPrice
   * @covers ::getAdjustments
   * @covers ::setAdjustments
   * @covers ::addAdjustment
   * @covers ::removeAdjustment
   * @covers ::getAdjustedTotalPrice
   * @covers ::getAdjustedUnitPrice
   * @covers ::getData
   * @covers ::setData
   * @covers ::unsetData
   * @covers ::getCreatedTime
   * @covers ::setCreatedTime
   * @covers ::getOrderItem
   * @covers ::getOrderItemId
   * @covers ::populateFromOrderItem
   */
  public function testInvoiceItem() {
    $invoice_item = InvoiceItem::create([
      'type' => 'commerce_product_variation',
    ]);
    $invoice_item->save();

    $invoice_item->setTitle('My invoice item');
    $this->assertEquals('My invoice item', $invoice_item->getTitle());

    $invoice_item->setDescription('Invoice item description');
    $this->assertEquals('Invoice item description', $invoice_item->getDescription());

    $invoice_item->setFormat('basic_html');
    $this->assertEquals('basic_html', $invoice_item->getFormat());

    $this->assertEquals(1, $invoice_item->getQuantity());
    $invoice_item->setQuantity('2');
    $this->assertEquals(2, $invoice_item->getQuantity());

    $this->assertEquals(NULL, $invoice_item->getUnitPrice());
    $unit_price = new Price('9.99', 'USD');
    $invoice_item->setUnitPrice($unit_price);
    $this->assertEquals($unit_price, $invoice_item->getUnitPrice());

    $adjustments = [];
    $adjustments[] = new Adjustment([
      'type' => 'custom',
      'label' => '10% off',
      'amount' => new Price('-1.00', 'USD'),
      'percentage' => '0.1',
    ]);
    $adjustments[] = new Adjustment([
      'type' => 'fee',
      'label' => 'Random fee',
      'amount' => new Price('2.00', 'USD'),
    ]);
    $invoice_item->addAdjustment($adjustments[0]);
    $invoice_item->addAdjustment($adjustments[1]);
    $adjustments = $invoice_item->getAdjustments();
    $this->assertEquals($adjustments, $invoice_item->getAdjustments());
    $this->assertEquals($adjustments, $invoice_item->getAdjustments(['custom', 'fee']));
    $this->assertEquals([$adjustments[0]], $invoice_item->getAdjustments(['custom']));
    $this->assertEquals([$adjustments[1]], $invoice_item->getAdjustments(['fee']));
    $invoice_item->removeAdjustment($adjustments[0]);
    $this->assertEquals([$adjustments[1]], $invoice_item->getAdjustments());
    $this->assertEquals(new Price('21.98', 'USD'), $invoice_item->getAdjustedTotalPrice());
    $this->assertEquals(new Price('10.99', 'USD'), $invoice_item->getAdjustedUnitPrice());
    $invoice_item->setAdjustments($adjustments);
    $this->assertEquals($adjustments, $invoice_item->getAdjustments());
    $this->assertEquals(new Price('9.99', 'USD'), $invoice_item->getUnitPrice());
    $this->assertEquals(new Price('19.98', 'USD'), $invoice_item->getTotalPrice());
    $this->assertEquals(new Price('20.98', 'USD'), $invoice_item->getAdjustedTotalPrice());
    $this->assertEquals(new Price('18.98', 'USD'), $invoice_item->getAdjustedTotalPrice(['custom']));
    $this->assertEquals(new Price('21.98', 'USD'), $invoice_item->getAdjustedTotalPrice(['fee']));
    // The adjusted unit prices are the adjusted total prices divided by 2.
    $this->assertEquals(new Price('10.49', 'USD'), $invoice_item->getAdjustedUnitPrice());
    $this->assertEquals(new Price('9.49', 'USD'), $invoice_item->getAdjustedUnitPrice(['custom']));
    $this->assertEquals(new Price('10.99', 'USD'), $invoice_item->getAdjustedUnitPrice(['fee']));

    $this->assertEquals('default', $invoice_item->getData('test', 'default'));
    $invoice_item->setData('test', 'value');
    $this->assertEquals('value', $invoice_item->getData('test', 'default'));
    $invoice_item->unsetData('test');
    $this->assertNull($invoice_item->getData('test'));
    $this->assertEquals('default', $invoice_item->getData('test', 'default'));

    $invoice_item->setCreatedTime(635879700);
    $this->assertEquals(635879700, $invoice_item->getCreatedTime());

    $invoice_item = InvoiceItem::create([
      'type' => 'commerce_product_variation',
    ]);
    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 3,
      'unit_price' => new Price('12.00', 'USD'),
      'adjustments' => $adjustments,
    ]);
    $order_item->save();
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $this->reloadEntity($order_item);
    $invoice_item->populateFromOrderItem($order_item);
    $this->assertEquals($order_item, $invoice_item->getOrderItem());
    $this->assertEquals($order_item->id(), $invoice_item->getOrderItemId());
    $this->assertEquals($order_item->getQuantity(), $invoice_item->getQuantity());
    $this->assertEquals($order_item->getUnitPrice(), $invoice_item->getUnitPrice());
    $this->assertEquals($order_item->getTitle(), $invoice_item->getTitle());
    $this->assertEquals($order_item->getAdjustments(), $invoice_item->getAdjustments());
  }

}
