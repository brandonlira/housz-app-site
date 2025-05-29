<?php

namespace Drupal\Tests\commerce_invoice\Kernel;

use Drupal\commerce_invoice\Entity\InvoiceType;
use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_product\Entity\ProductVariationType;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\profile\Entity\Profile;

/**
 * Tests the invoice generator service.
 *
 * @coversDefaultClass \Drupal\commerce_invoice\InvoiceGenerator
 * @group commerce_invoice
 */
class InvoiceGeneratorTest extends InvoiceKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'language',
    'content_translation',
    'commerce_product',
  ];

  /**
   * The invoice generator service.
   *
   * @var \Drupal\commerce_invoice\InvoiceGeneratorInterface
   */
  protected $invoiceGenerator;

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['commerce_product']);
    $this->installConfig(['language']);
    $this->installEntitySchema('commerce_product_variation');
    // Turn off title generation to allow explicit values to be used.
    $variation_type = ProductVariationType::load('default');
    $variation_type->setGenerateTitle(FALSE);
    $variation_type->save();
    ConfigurableLanguage::createFromLangcode('fr')->save();
    $this->container->get('content_translation.manager')
      ->setEnabled('commerce_product_variation', 'default', TRUE);

    $this->invoiceGenerator = $this->container->get('commerce_invoice.invoice_generator');
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

    $invoice_type = InvoiceType::load('default');
    $invoice_type->setPaymentTerms('Payment terms');
    $invoice_type->setFooterText('Footer text');
    $invoice_type->save();

    $language_manager = \Drupal::languageManager();
    $config_translation = $language_manager->getLanguageConfigOverride('fr', 'commerce_invoice.commerce_invoice_type.default');
    $config_translation->setData([
      'paymentTerms' => 'Termes de paiement',
      'footerText' => 'Texte pied de page',
    ]);
    $config_translation->save();
  }

  /**
   * Tests generating invoices.
   *
   * @covers ::generate
   */
  public function testGenerate() {
    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 1,
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
      'adjustments' => [
        new Adjustment([
          'type' => 'custom',
          'label' => '10% off',
          'amount' => new Price('-1.20', 'USD'),
          'percentage' => '0.1',
        ]),
      ],
      'total_paid' => new Price('5.00', 'USD'),
    ]);
    $order->save();
    $order = $this->reloadEntity($order);
    $another_order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 3,
      'unit_price' => new Price('10.00', 'USD'),
    ]);
    $another_order_item->save();
    $another_order = Order::create([
      'type' => 'default',
      'state' => 'completed',
      'store_id' => $this->store,
      'uid' => $this->user->id(),
      'billing_profile' => $this->profile,
      'order_items' => [$another_order_item],
      'adjustments' => [
        new Adjustment([
          'type' => 'fee',
          'label' => 'Random fee',
          'amount' => new Price('2.00', 'USD'),
        ]),
      ],
    ]);
    $another_order->save();
    $another_order = $this->reloadEntity($another_order);
    $invoice = $this->invoiceGenerator->generate([$order, $another_order], $this->store, $this->profile, ['uid' => $this->user->id()]);
    $invoice_billing_profile = $invoice->getBillingProfile();
    $this->assertNotEmpty($invoice->getBillingProfile());
    $this->assertTrue($this->profile->equalToProfile($invoice_billing_profile));
    $this->assertEquals($this->user->id(), $invoice->getCustomerId());

    $this->assertEquals([$order, $another_order], $invoice->getOrders());
    $this->assertEquals($this->store, $invoice->getStore());
    $this->assertEquals(new Price('42.8', 'USD'), $invoice->getTotalPrice());
    $this->assertCount(2, $invoice->getItems());
    $this->assertCount(2, $invoice->getAdjustments());
    $this->assertEquals(new Price('5.00', 'USD'), $invoice->getTotalPaid());
  }

  /**
   * Tests generating invoices in multiple languages.
   */
  public function testMultilingual() {
    $variation = ProductVariation::create([
      'type' => 'default',
      'title' => 'Version one',
      'price' => new Price('12.00', 'USD'),
    ]);
    $variation->addTranslation('fr', [
      'title' => 'Version une',
    ]);
    $variation->save();
    /** @var \Drupal\commerce_order\OrderItemStorageInterface $order_item_storage */
    $order_item_storage = $this->container->get('entity_type.manager')->getStorage('commerce_order_item');

    $order_item = $order_item_storage->createFromPurchasableEntity($variation);
    $order_item->save();
    $order = Order::create([
      'type' => 'default',
      'store_id' => $this->store->id(),
      'state' => 'draft',
      'mail' => $this->user->getEmail(),
      'uid' => $this->user->id(),
      'billing_profile' => $this->profile,
      'order_items' => [$order_item],
    ]);
    $order->save();
    $invoice = $this->invoiceGenerator->generate([$order], $this->store, $this->profile, ['uid' => $this->user->id()]);
    $this->assertNotNull($invoice);
    $this->assertFalse($invoice->hasTranslation('fr'));
    $invoice->delete();

    $config = ContentLanguageSettings::loadByEntityTypeBundle('commerce_invoice', 'default');
    $config->setDefaultLangcode('en');
    $config->setThirdPartySetting('commerce_invoice', 'generate_translations', TRUE);
    $config->setLanguageAlterable(FALSE);
    $config->save();

    $invoice = $this->invoiceGenerator->generate([$order], $this->store, $this->profile, ['uid' => $this->user->id()]);
    $this->assertEquals('Version one', $invoice->getItems()[0]->label());
    $this->assertEquals('en', $invoice->language()->getId());
    $this->assertNotEmpty($invoice->getData('invoice_type'));
    $expected = [
      'paymentTerms' => 'Payment terms',
      'footerText' => 'Footer text',
    ];
    $this->assertEquals($expected, $invoice->getData('invoice_type'));
    $this->assertTrue($invoice->hasTranslation('fr'));
    $fr_translation = $invoice->getTranslation('fr');

    $this->assertEquals('fr', $fr_translation->language()->getId());
    $this->assertNotEmpty($fr_translation->getData('invoice_type'));
    $expected = [
      'paymentTerms' => 'Termes de paiement',
      'footerText' => 'Texte pied de page',
    ];
    $this->assertEquals($expected, $fr_translation->getData('invoice_type'));
    $fr_invoice_item = $fr_translation->getItems()[0]->getTranslation('fr');
    $this->assertEquals('Version une', $fr_invoice_item->label());
  }

}
