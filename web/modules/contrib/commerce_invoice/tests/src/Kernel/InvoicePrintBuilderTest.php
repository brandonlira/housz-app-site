<?php

namespace Drupal\Tests\commerce_invoice\Kernel;

use Drupal\commerce_invoice\Entity\Invoice;
use Drupal\commerce_invoice\Entity\InvoiceType;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\Core\File\FileSystemInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\profile\Entity\Profile;

/**
 * Tests the invoice print builder.
 *
 * @coversDefaultClass \Drupal\commerce_invoice\InvoicePrintBuilder
 * @group commerce_invoice
 */
class InvoicePrintBuilderTest extends InvoiceKernelTestBase {

  /**
   * The invoice print builder.
   *
   * @var \Drupal\commerce_invoice\InvoicePrintBuilderInterface
   */
  protected $printBuilder;

  /**
   * The profile.
   *
   * @var \Drupal\profile\Entity\Profile
   */
  protected $profile;

  /**
   * A sample user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'language',
    'content_translation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    ConfigurableLanguage::createFromLangcode('fr')->save();
    ConfigurableLanguage::createFromLangcode('en')->save();

    $user = $this->createUser();
    $this->user = $this->reloadEntity($user);
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
      'uid' => $this->user->id(),
    ]);
    $profile->save();
    $this->profile = $this->reloadEntity($profile);
    $this->printBuilder = $this->container->get('commerce_invoice.print_builder');
  }

  /**
   * @covers ::generateFilename
   * @covers ::savePrintable
   */
  public function testSavePrintable() {
    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 1,
      'title' => $this->randomString(),
      'unit_price' => new Price('12.00', 'USD'),
    ]);
    $order_item->save();

    $order = Order::create([
      'type' => 'default',
      'state' => 'completed',
      'store_id' => $this->store,
      'billing_profile' => $this->profile,
      'uid' => $this->user->id(),
      'order_items' => [$order_item],
    ]);
    $order->save();
    $order = $this->reloadEntity($order);
    $invoice = Invoice::create([
      'type' => 'default',
      'langcode' => 'fr',
      'invoice_number' => '10',
      'orders' => [$order],
      'store_id' => $this->store->id(),
      'billing_profile' => $this->profile,
      'state' => 'paid',
      'uid' => $this->user->id(),
    ]);
    $invoice->save();
    $print_engine = $this->container->get('plugin.manager.entity_print.print_engine')->createInstance('testprintengine');

    $language_manager = $this->container->get('language_manager');
    $current_language = $language_manager->getCurrentLanguage()->getId();
    $file = $this->printBuilder->savePrintable($invoice, $print_engine);
    $this->assertNotEmpty($file);
    $this->assertMatchesRegularExpression('#private://(.*)\.pdf#', $file->getFileUri());
    $this->assertEquals('10-fr-paid.pdf', $file->getFilename());
    $this->assertEquals('application/pdf', $file->getMimeType());
    $this->assertEquals($invoice->language()->getId(), $file->language()->getId());

    // Tests the filename alteration via an event subscriber.
    $invoice->setData('alter_filename', TRUE);
    $file = $this->printBuilder->savePrintable($invoice, $print_engine);
    $this->assertEquals($language_manager->getCurrentLanguage()->getId(), $current_language);

    $this->assertMatchesRegularExpression('#private://(.*)\.pdf#', $file->getFileUri());
    $this->assertEquals('10-fr-paid-altered.pdf', $file->getFilename());
    $this->assertEquals('application/pdf', $file->getMimeType());

    $invoice_type = InvoiceType::load('default');
    $invoice_type->setPrivateSubdirectory('invoices');
    $invoice_type->save();
    $directory = 'private://invoices';
    $writable = \Drupal::service('file_system')->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);

    $file = $this->printBuilder->savePrintable($invoice, $print_engine);
    $this->assertEquals('private://invoices/10-fr-paid-altered.pdf', $file->getFileUri());
  }

}
