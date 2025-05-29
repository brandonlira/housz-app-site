<?php

namespace Drupal\Tests\commerce_invoice\Functional;

use Drupal\commerce_invoice\Entity\Invoice;
use Drupal\commerce_invoice\Entity\InvoiceItem;
use Drupal\commerce_price\Price;

/**
 * Tests normal user operations with invoices.
 *
 * @group commerce
 */
class InvoiceUserTest extends InvoiceBrowserTestBase {

  /**
   * A test user with normal privileges.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $permissions = [
      'view own commerce_invoice',
    ];

    $this->user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($this->user);
  }

  /**
   * Tests viewing a created invoice.
   */
  public function testViewInvoice() {
    $uid = $this->loggedInUser->id();

    /** @var \Drupal\commerce_invoice\Entity\InvoiceItemInterface $invoice_item */
    $invoice_item = InvoiceItem::create([
      'type' => 'commerce_product_variation',
      'quantity' => '1',
      'unit_price' => new Price('2.00', 'USD'),
    ]);
    $invoice_item->save();
    $invoice_item = $this->reloadEntity($invoice_item);

    /** @var \Drupal\commerce_invoice\Entity\InvoiceInterface $invoice */
    $invoice = Invoice::create([
      'type' => 'default',
      'store_id' => $this->store->id(),
      'invoice_items' => [$invoice_item],
      'mail' => $this->user->getEmail(),
      'uid' => $this->user->id(),
      'state' => 'draft',
    ]);
    $invoice->save();

    // Check that we can view the invoices page.
    $this->drupalGet('/user/' . $uid . '/invoices/');
    $this->assertSession()->statusCodeEquals(200);

    // Check that the draft invoice is not available.
    $this->assertSession()->linkByHrefNotExists('/user/' . $uid . '/invoices/' . $invoice->id());
    // Verify the invoice cannot be viewed, either.
    $this->drupalGet('/user/' . $uid . '/invoices/' . $invoice->id());
    $this->assertSession()->statusCodeEquals(403);
    // Verify the invoice PDF cannot be viewed, either.
    $this->drupalGet('/invoice/' . $invoice->id() . '/download');
    $this->assertSession()->statusCodeEquals(403);

    $invoice->getState()->applyTransitionById('pay');
    $invoice->save();

    // Check that the paid invoice is available.
    $this->drupalGet('/user/' . $uid . '/invoices/');
    $this->assertSession()->linkByHrefExists('/user/' . $uid . '/invoices/' . $invoice->id());

    // Click invoice and make sure it works.
    $this->getSession()->getPage()->clickLink($invoice->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($invoice->getInvoiceNumber());

    // Verify the invoice PDF can be viewed.
    $this->drupalGet('/invoice/' . $invoice->id() . '/download');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests viewing an anonymous invoice is denied.
   */
  public function testAnonymousViewInvoice() {
    /** @var \Drupal\commerce_invoice\Entity\InvoiceItemInterface $invoice_item */
    $invoice_item = InvoiceItem::create([
      'type' => 'commerce_product_variation',
      'quantity' => '1',
      'unit_price' => new Price('2.00', 'USD'),
    ]);
    $invoice_item->save();
    $invoice_item = $this->reloadEntity($invoice_item);

    /** @var \Drupal\commerce_invoice\Entity\InvoiceInterface $invoice */
    $invoice = Invoice::create([
      'type' => 'default',
      'state' => 'paid',
      'store_id' => $this->store->id(),
      'invoice_items' => [$invoice_item],
      'mail' => 'testViewInvoice@example.com',
      'uid' => 0,
    ]);
    $invoice->save();

    // Check invoice list page is not available even though there is a paid
    // invoice.
    $this->drupalGet('/user/0/invoices/');
    $this->assertSession()->statusCodeEquals(404);

    // Check that the invoice is also not available directly.
    $this->drupalGet('/user/0/invoices/' . $invoice->id());
    $this->assertSession()->statusCodeEquals(403);

    // Verify the invoice PDF cannot be viewed, either.
    $this->drupalGet('/invoice/' . $invoice->id() . '/download');
    $this->assertSession()->statusCodeEquals(403);
  }

}
