<?php

namespace Drupal\Tests\commerce_invoice\Kernel;

use Drupal\commerce_invoice\Entity\Invoice;
use Drupal\commerce_invoice\Entity\InvoiceItem;
use Drupal\commerce_price\Price;

/**
 * Tests the price splitter.
 *
 * @coversDefaultClass \Drupal\commerce_invoice\PriceSplitter
 *
 * @group commerce_invoice
 */
class PriceSplitterTest extends InvoiceKernelTestBase {

  /**
   * A sample invoice.
   *
   * @var \Drupal\commerce_invoice\Entity\InvoiceInterface
   */
  protected $invoice;

  /**
   * The price splitter.
   *
   * @var \Drupal\commerce_invoice\PriceSplitterInterface
   */
  protected $splitter;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $user = $this->createUser();

    $invoice = Invoice::create([
      'type' => 'default',
      'state' => 'draft',
      'mail' => $user->getEmail(),
      'uid' => $user->id(),
      'ip_address' => '127.0.0.1',
      'invoice_number' => '6',
      'store_id' => $this->store->id(),
    ]);
    $invoice->save();
    $this->invoice = $this->reloadEntity($invoice);

    $this->splitter = $this->container->get('commerce_invoice.price_splitter');
  }

  /**
   * @covers ::split
   */
  public function testEmptyInvoice(): void {
    // Confirm that the splitter can be run on invoices with no items.
    $amounts = $this->splitter->split($this->invoice, new Price('42', 'USD'), '50');
    $this->assertEquals([], $amounts);
    $amounts = $this->splitter->split($this->invoice, new Price('42', 'USD'));
    $this->assertEquals([], $amounts);
  }

  /**
   * @covers ::split
   */
  public function testSplit(): void {
    // 6 x 3 + 6 x 3 = 36.
    $unit_price = new Price('6', 'USD');
    $invoice_items = $this->buildInvoiceItems([$unit_price, $unit_price], 3);
    $this->invoice->setItems($invoice_items);
    $this->invoice->save();

    // Each invoice item should be discounted by half (9 USD).
    $amounts = $this->splitter->split($this->invoice, new Price('18', 'USD'));
    $expected_amount = new Price('9', 'USD');
    foreach ($amounts as $amount) {
      $this->assertEquals($expected_amount, $amount);
    }

    // Same result with an explicit percentage.
    $amounts = $this->splitter->split($this->invoice, new Price('18', 'USD'), '0.5');
    $expected_amount = new Price('9', 'USD');
    foreach ($amounts as $amount) {
      $this->assertEquals($expected_amount, $amount);
    }
    // 9.99 x 3 + 1.01 x 3 = 33.
    $first_unit_price = new Price('9.99', 'USD');
    $second_unit_price = new Price('1.01', 'USD');
    $invoice_items = $this->buildInvoiceItems([
      $first_unit_price,
      $second_unit_price,
    ], 3);
    $this->invoice->setItems($invoice_items);
    $this->invoice->save();

    $amount = new Price('5', 'USD');
    $amounts = $this->splitter->split($this->invoice, $amount);
    $first_expected_amount = new Price('4.54', 'USD');
    $second_expected_amount = new Price('0.46', 'USD');
    $this->assertEquals($first_expected_amount->add($second_expected_amount), $amount);
    $amounts = array_values($amounts);
    $this->assertEquals($first_expected_amount, $amounts[0]);
    $this->assertEquals($second_expected_amount, $amounts[1]);

    // Split an amount that has a remainder.
    $unit_price = new Price('69.99', 'USD');
    $invoice_items = $this->buildInvoiceItems([$unit_price, $unit_price]);
    $this->invoice->setItems($invoice_items);
    $this->invoice->save();

    $amount = new Price('41.99', 'USD');
    $amounts = $this->splitter->split($this->invoice, $amount, '0.3');
    $first_expected_amount = new Price('21.00', 'USD');
    $second_expected_amount = new Price('20.99', 'USD');
    $this->assertEquals($first_expected_amount->add($second_expected_amount), $amount);
    $amounts = array_values($amounts);
    $this->assertEquals($first_expected_amount, $amounts[0]);
    $this->assertEquals($second_expected_amount, $amounts[1]);

    // Split a negative amount that has a negative remainder.
    $unit_price = new Price('100.00', 'USD');
    $invoice_items = $this->buildInvoiceItems([
      $unit_price,
      $unit_price,
      $unit_price,
    ]);
    $this->invoice->setItems($invoice_items);
    $this->invoice->save();

    $amount = new Price('-10.00', 'USD');
    $amounts = $this->splitter->split($this->invoice, $amount);
    $first_expected_amount = new Price('-3.34', 'USD');
    $second_expected_amount = new Price('-3.33', 'USD');
    $third_expected_amount = new Price('-3.33', 'USD');
    $this->assertEquals($first_expected_amount->add($second_expected_amount)->add($third_expected_amount), $amount);
    $amounts = array_values($amounts);
    $this->assertEquals($first_expected_amount, $amounts[0]);
    $this->assertEquals($second_expected_amount, $amounts[1]);
    $this->assertEquals($third_expected_amount, $amounts[2]);
  }

  /**
   * Builds the invoice items for the given unit prices.
   *
   * @param \Drupal\commerce_price\Price[] $unit_prices
   *   The unit prices.
   * @param string $quantity
   *   The quantity. Same for all items.
   *
   * @return \Drupal\commerce_invoice\Entity\InvoiceItemInterface[]
   *   The invoice items.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function buildInvoiceItems(array $unit_prices, string $quantity = '1'): array {
    $invoice_items = [];
    foreach ($unit_prices as $unit_price) {
      $invoice_item = InvoiceItem::create([
        'type' => 'test',
        'unit_price' => $unit_price,
        'quantity' => $quantity,
      ]);
      $invoice_item->save();

      $invoice_items[] = $invoice_item;
    }

    return $invoice_items;
  }

}
