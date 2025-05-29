<?php

namespace Drupal\Tests\commerce_invoice\Kernel\Mail;

use Drupal\commerce_invoice\Entity\Invoice;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\profile\Entity\Profile;
use Drupal\Tests\commerce_invoice\Kernel\InvoiceKernelTestBase;

/**
 * Tests the sending of invoice confirmation emails.
 *
 * @coversDefaultClass \Drupal\commerce_invoice\Mail\InvoiceConfirmationMail
 * @group commerce_invoice
 */
class InvoiceConfirmationMailTest extends InvoiceKernelTestBase {

  use AssertMailTrait;

  /**
   * The profile.
   *
   * @var \Drupal\profile\Entity\Profile
   */
  protected $profile;

  /**
   * A sample invoice.
   *
   * @var \Drupal\commerce_invoice\Entity\InvoiceInterface
   */
  protected $invoice;

  /**
   * The invoice confirmation.
   *
   * @var \Drupal\commerce_invoice\Mail\InvoiceConfirmationMailInterface
   */
  protected $invoiceConfirmationMail;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $user = $this->createUser([], NULL, FALSE, [
      'mail' => 'customer@example.com',
      'preferred_langcode' => 'en',
    ]);
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
      'uid' => $user->id(),
    ]);
    $profile->save();
    $this->profile = $this->reloadEntity($profile);
    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 1,
      'title' => $this->randomString(),
      'unit_price' => new Price('12.00', 'USD'),
    ]);
    $order_item->save();
    $order = Order::create([
      'type' => 'default',
      'mail' => $user->getEmail(),
      'state' => 'completed',
      'store_id' => $this->store,
      'billing_profile' => $this->profile,
      'uid' => $user->id(),
      'order_items' => [$order_item],
    ]);
    $order->save();

    $invoice = Invoice::create([
      'type' => 'default',
      'mail' => $user->getEmail(),
      'invoice_number' => '10',
      'orders' => [$order],
      'store_id' => $this->store->id(),
      'billing_profile' => $this->profile,
      'state' => 'paid',
      'uid' => $user->id(),
    ]);
    $invoice->save();
    $this->invoice = $this->reloadEntity($invoice);

    $this->invoiceConfirmationMail = $this->container->get('commerce_invoice.invoice_confirmation_mail');
  }

  /**
   * @covers ::send
   */
  public function testSend() {
    $file = $this->invoice->getFile();
    $this->assertEmpty($file);

    $this->invoiceConfirmationMail->send($this->invoice);

    $emails = $this->getMails();
    $this->assertCount(1, $emails);
    $email = end($emails);
    $this->assertEquals('text/html; charset=UTF-8;', $email['headers']['Content-Type']);
    $this->assertEquals('8Bit', $email['headers']['Content-Transfer-Encoding']);
    $this->assertEquals($this->invoice->getStore()->getEmail(), $email['from']);
    $this->assertEquals('customer@example.com', $email['to']);
    $this->assertFalse(isset($email['headers']['Bcc']));
    $this->assertEquals('Invoice #10', $email['subject']);
    $this->assertStringContainsString('A new invoice has been created for you.', $email['body']);
    $this->assertEquals('en', $email['params']['langcode']);
    $this->assertEquals($this->invoice, $email['params']['invoice']);
    $this->assertNotEmpty($email['params']['attachments']);

    $this->invoiceConfirmationMail->send($this->invoice, 'custom@example.com', 'store@example.com');

    $emails = $this->getMails();
    $this->assertCount(2, $emails);
    $email = end($emails);
    $this->assertEquals('text/html; charset=UTF-8;', $email['headers']['Content-Type']);
    $this->assertEquals('8Bit', $email['headers']['Content-Transfer-Encoding']);
    $this->assertEquals($this->invoice->getStore()->getEmail(), $email['from']);
    $this->assertEquals('custom@example.com', $email['to']);
    $this->assertEquals('store@example.com', $email['headers']['Bcc']);
    $this->assertEquals('Invoice #10', $email['subject']);
    $this->assertStringContainsString('A new invoice has been created for you.', $email['body']);
    $this->assertEquals('en', $email['params']['langcode']);
    $this->assertEquals($this->invoice, $email['params']['invoice']);
    $this->assertNotEmpty($email['params']['attachments']);

    $this->invoice = $this->reloadEntity($this->invoice);
    $file = $this->invoice->getFile();
    $this->assertNotEmpty($file);
    $this->assertMatchesRegularExpression('#private://(.*)\.pdf#', $file->getFileUri());
    $this->assertEquals('10-en-paid.pdf', $file->getFilename());
    $this->assertEquals('application/pdf', $file->getMimeType());

    $attachments = $email['params']['attachments'];
    $attachment = reset($attachments);
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $this->assertEquals($file_system->realpath($file->getFileUri()), $attachment['filepath']);
  }

}
