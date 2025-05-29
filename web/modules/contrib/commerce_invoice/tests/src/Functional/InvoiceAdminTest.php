<?php

namespace Drupal\Tests\commerce_invoice\Functional;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\commerce_price\Price;
use Drupal\Core\Url;
use Drupal\views\Entity\View;

/**
 * Tests the invoice admin UI.
 *
 * @group commerce_invoice
 */
class InvoiceAdminTest extends InvoiceBrowserTestBase {

  /**
   * The invoice collection url.
   *
   * @var string
   */
  protected $collectionUrl;

  /**
   * The order invoices url.
   *
   * @var string
   */
  protected $orderInvoicesUrl;

  /**
   * The order invoice generate form url.
   *
   * @var string
   */
  protected $orderInvoiceAddUrl;

  /**
   * A sample order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'administer commerce_order',
      'access commerce_order overview',
    ], parent::getAdministratorPermissions());
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $order_type = OrderType::load('default');
    $order_type->setThirdPartySetting('commerce_invoice', 'invoice_type', 'default');
    $order_type->save();

    $variation = $this->createEntity('commerce_product_variation', [
      'title' => $this->randomMachineName(),
      'type' => 'default',
      'sku' => 'sku-' . $this->randomMachineName(),
      'price' => [
        'number' => '7.99',
        'currency_code' => 'USD',
      ],
    ]);
    $order_item = $this->createEntity('commerce_order_item', [
      'title' => $this->randomMachineName(),
      'type' => 'default',
      'quantity' => 2,
      'unit_price' => new Price('10', 'USD'),
      'overridden_unit_price' => TRUE,
      'purchased_entity' => $variation,
    ]);
    $order_item->save();
    $adjustment = new Adjustment([
      'type' => 'custom',
      'label' => 'Random fee',
      'amount' => new Price('2.00', 'USD'),
    ]);
    $billing_profile = $this->createEntity('profile', [
      'type' => 'customer',
      'uid' => $this->adminUser->id(),
    ]);
    $this->order = $this->createEntity('commerce_order', [
      'uid' => $this->loggedInUser->id(),
      'order_number' => '6',
      'type' => 'default',
      'state' => 'draft',
      'order_items' => [$order_item],
      'adjustments' => [$adjustment],
      'store_id' => $this->store,
      'billing_profile' => $billing_profile,
    ]);
    $this->collectionUrl = Url::fromRoute('entity.commerce_invoice.collection')->toString();
    $this->orderInvoicesUrl = $this->order->toUrl('invoices')->toString();
    $this->orderInvoiceAddUrl = $this->order->toUrl('invoice-add-form')->toString();
  }

  /**
   * Tests access to the order invoices tab.
   */
  public function testOrderInvoicesAccess() {
    $user = $this->drupalCreateUser();
    $this->drupalLogin($user);
    $this->drupalGet($this->orderInvoicesUrl);
    $this->assertSession()->pageTextContains('Access denied');
    $this->order->set('state', 'completed');
    $this->order->save();
    $this->getSession()->reload();
    $this->assertSession()->pageTextContains('Access denied');
    $user2 = $this->drupalCreateUser(['administer commerce_invoice']);
    $this->drupalLogin($user2);
    $this->drupalGet($this->orderInvoicesUrl);
    $this->assertSession()->pageTextContains('There are no invoices yet.');
    $this->assertSession()->linkByHrefExists($this->orderInvoiceAddUrl);
  }

  /**
   * Tests the order "Invoices" tab and the invoice generate form.
   */
  public function testOrderInvoices() {
    // Ensure the "Invoices" operation is not shown for a draft order.
    $this->drupalGet($this->order->toUrl('collection'));
    $this->assertSession()->linkByHrefNotExists($this->orderInvoicesUrl);
    $order_edit_link = $this->order->toUrl('edit-form')->toString();
    $this->assertSession()->linkByHrefExists($order_edit_link);

    $this->order->set('state', 'completed');
    $this->order->save();
    $this->getSession()->reload();
    $this->assertSession()->linkByHrefExists($this->orderInvoicesUrl);
    $this->drupalGet($this->orderInvoicesUrl);
    $this->assertSession()->pageTextContains('There are no invoices yet.');
    $this->assertSession()->linkByHrefExists($this->orderInvoiceAddUrl);

    $this->drupalGet($this->orderInvoiceAddUrl);
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('The Invoice #1 has been successfully saved.');
    $this->assertSession()->pageTextNotContains('There are no invoices yet.');
    $this->assertSession()->linkExists('Download');
    $this->assertSession()->pageTextContains('Number');
    $this->assertSession()->linkByHrefNotExists($this->orderInvoiceAddUrl);

    /** @var \Drupal\Core\Entity\ContentEntityStorageInterface $invoice_storage */
    $invoice_storage = $this->container->get('entity_type.manager')->getStorage('commerce_invoice');
    /** @var \Drupal\commerce_invoice\Entity\InvoiceInterface $invoice */
    $invoice = $invoice_storage->load(1);
    $this->drupalGet($invoice->toUrl('download')->toString());
    $invoice = $this->reloadEntity($invoice);
    $file = $invoice->getFile();
    $this->assertNotNull($file);

    // Assert that re-downloading the invoice doesn't generate a new file.
    $this->drupalGet($invoice->toUrl('download')->toString());
    $invoice = $this->reloadEntity($invoice);
    $this->assertEquals($file->id(), $invoice->getFile()->id());
    /** @var \Drupal\file\FileStorageInterface $file_storage */
    $file_storage = $this->container->get('entity_type.manager')->getStorage('file');
    $this->assertNull($file_storage->load(2));
  }

  /**
   * Tests the "Pay invoice" operation.
   */
  public function testPayInvoiceOperation() {
    $invoice_item = $this->createEntity('commerce_invoice_item', [
      'type' => 'commerce_product_variation',
      'unit_price' => new Price('10', 'USD'),
      'quantity' => 1,
    ]);
    $invoice = $this->createEntity('commerce_invoice', [
      'type' => 'default',
      'invoice_number' => $this->randomString(),
      'invoice_items' => $invoice_item,
      'store_id' => $this->store->id(),
      'orders' => [$this->order->id()],
      'total_paid' => new Price('10', 'USD'),
    ]);
    $payment_form_url = $invoice->toUrl('payment-form')->toString();
    // Ensure the "Pay invoice" operation is not shown for a paid invoice.
    $this->drupalGet($this->collectionUrl);
    $this->assertSession()->linkByHrefNotExists($payment_form_url);
    $invoice->setTotalPaid(new Price(0, 'USD'));
    $invoice->save();

    $this->getSession()->reload();
    $this->assertSession()->linkByHrefExists($payment_form_url);
    $this->drupalGet($payment_form_url);
    $this->assertSession()->buttonExists(t('Pay'));
    $this->assertSession()->linkExists('Cancel');
    $this->submitForm([], t('Pay'));
    $this->getSession()->reload();
    $this->assertSession()->linkByHrefNotExists($payment_form_url);
  }

  /**
   * Tests the Invoices listing with and without the view.
   */
  public function testInvoiceListing() {
    $invoice_collection_route = Url::fromRoute('entity.commerce_invoice.collection');
    $this->drupalGet($invoice_collection_route);
    $this->assertSession()->pageTextContains('There are no invoices yet.');
    $invoice = $this->createEntity('commerce_invoice', [
      'type' => 'default',
      'invoice_number' => '9999999',
      'store_id' => $this->store->id(),
      'orders' => [$this->order->id()],
      'total_price' => new Price('10', 'USD'),
    ]);
    $this->getSession()->reload();
    $this->assertSession()->pageTextNotContains('There are no invoices yet.');
    $this->assertSession()->pageTextContains($invoice->getInvoiceNumber());
    $this->assertSession()->linkExists('Download');
    $this->assertSession()->linkExists('Pay');

    // Ensure the listing works without the view.
    View::load('commerce_invoices')->delete();
    \Drupal::service('router.builder')->rebuild();
    $this->drupalGet($invoice_collection_route);
    $this->assertSession()->pageTextNotContains('There are no invoices yet.');
    $this->assertSession()->pageTextContains($invoice->label());
    $invoice->delete();
    $this->getSession()->reload();
    $this->assertSession()->pageTextContains('There are no invoices yet.');
  }

  /**
   * Tests the invoice add form.
   */
  public function testInvoiceAddForm() {
    $assert_session = $this->assertSession();

    $this->order->set('state', 'completed');
    $this->order->save();

    $this->drupalGet($this->orderInvoiceAddUrl);

    // Check that the invoice items were pre-populated with the order items.
    $assert_session->elementsCount('css', 'tr.ief-row-entity', 1);
    $assert_session->elementContains('css', 'td.inline-entity-form-commerce_invoice_item-quantity', '2.00');
    $assert_session->elementContains('css', 'td.inline-entity-form-commerce_invoice_item-unit_price', '$10.00');

    // Check that the invoice adjustments were pre-populated with the order
    // adjustments.
    $assert_session->optionExists('adjustments[0][type]', 'Custom');
    $assert_session->fieldValueEquals('adjustments[0][definition][label]', 'Random fee');
    $assert_session->fieldValueEquals('adjustments[0][definition][amount][number]', '2.00');

    $this->getSession()->getPage()->pressButton('Save');
    $assert_session->pageTextContains('The Invoice #1 has been successfully saved.');
  }

}
