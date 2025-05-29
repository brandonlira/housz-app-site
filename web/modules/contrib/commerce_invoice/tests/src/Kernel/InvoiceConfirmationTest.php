<?php

namespace Drupal\Tests\commerce_invoice\Kernel;

use Drupal\commerce_invoice\Entity\Invoice;
use Drupal\commerce_invoice\Entity\InvoiceType;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Site\Settings;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\language\Entity\ContentLanguageSettings;
use Drupal\profile\Entity\Profile;

/**
 * Tests the sending of multilingual invoice confirmation emails.
 *
 * @group commerce_invoice
 */
class InvoiceConfirmationTest extends InvoiceKernelTestBase {

  use AssertMailTrait;

  /**
   * A sample order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

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
   * Translated strings used in the invoice confirmation.
   *
   * @var array
   */
  protected $translations = [
    'fr' => [
      'Invoice #@number' => 'Facture #@number',
      'A new invoice has been created for you.' => 'Une nouvelle facture a été créée pour vous.',
      'Default store' => 'Magasin par défaut',
    ],
    'es' => [
      'Invoice #@number' => 'Factura #@number',
      'A new invoice has been created for you.' => 'Se ha creado una nueva factura para usted.',
      'Default store' => 'Tienda por defecto',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'locale',
    'content_translation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['language']);
    $this->installSchema('locale', ['locales_source', 'locales_target', 'locales_location']);

    foreach (array_keys($this->translations) as $langcode) {
      ConfigurableLanguage::createFromLangcode($langcode)->save();
    }
    // Provide the translated strings by overriding in-memory settings.
    $settings = Settings::getAll();
    foreach ($this->translations as $langcode => $custom_translation) {
      foreach ($custom_translation as $untranslated => $translated) {
        $settings['locale_custom_strings_' . $langcode][''][$untranslated] = $translated;
      }
    }
    new Settings($settings);

    /** @var \Drupal\commerce_price\CurrencyImporterInterface $currency_importer */
    $currency_importer = $this->container->get('commerce_price.currency_importer');
    $currency_importer->importTranslations(array_keys($this->translations));
    /** @var \Drupal\language\ConfigurableLanguageManagerInterface $language_manager */
    $language_manager = $this->container->get('language_manager');
    // The translated USD symbol is $US in both French and Spanish.
    // Invent a new symbol translation for French, to test translations.
    $fr_usd = $language_manager->getLanguageConfigOverride('fr', 'commerce_price.commerce_currency.USD');
    $fr_usd->set('symbol', 'U$D');
    $fr_usd->save();

    $this->store = $this->reloadEntity($this->store);
    $this->store->addTranslation('es', [
      'name' => $this->translations['es']['Default store'],
    ]);
    $this->store->addTranslation('fr', [
      'name' => $this->translations['fr']['Default store'],
    ]);
    $this->store->save();

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
    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 1,
      'title' => $this->randomString(),
      'unit_price' => new Price('12.00', 'USD'),
    ]);
    $order_item->save();

    $this->order = Order::create([
      'type' => 'default',
      'mail' => $this->user->getEmail(),
      'state' => 'completed',
      'store_id' => $this->store,
      'billing_profile' => $this->profile,
      'uid' => $this->user->id(),
      'order_items' => [$order_item],
    ]);
    $this->order->save();
    $this->order = $this->reloadEntity($this->order);
  }

  /**
   * Tests default disabled invoice confirmation.
   */
  public function testInvoiceConfirmationDisabled() {
    $invoice = Invoice::create([
      'type' => 'default',
      'mail' => $this->user->getEmail(),
      'invoice_number' => '10',
      'orders' => [$this->order],
      'store_id' => $this->store->id(),
      'billing_profile' => $this->profile,
      'uid' => $this->user->id(),
    ]);
    $invoice->getState()->applyTransitionById('confirm');
    $invoice->save();

    // Confirm that pdf file has not been created for the invoice.
    $this->assertEmpty($invoice->getFile());
    $this->assertCount(0, $this->getMails());
  }

  /**
   * Tests that the email is sent and translated to the customer's language.
   *
   * The email is sent in the customer's langcode  if the user is not anonymous,
   * otherwise it is the site's default langcode. In #2603482 this could
   * be changed to use the invoice's langcode.
   *
   * @param string $langcode
   *   The langcode to test with.
   * @param string $expected_langcode
   *   The expected langcode.
   *
   * @dataProvider providerInvoiceConfirmationMultilingualData
   */
  public function testInvoiceConfirmation($langcode, $expected_langcode) {
    $customer = $this->order->getCustomer();
    $customer->set('preferred_langcode', $langcode);
    $customer->save();

    $invoice_type = InvoiceType::load('default');
    $invoice_type->setSendConfirmation(TRUE);
    $invoice_type->setConfirmationBcc('bcc@example.com');
    $invoice_type->save();

    if ($langcode) {
      $config = ContentLanguageSettings::loadByEntityTypeBundle('commerce_invoice', 'default');
      $config->setDefaultLangcode($langcode);
      $config->setLanguageAlterable(FALSE);
      $config->save();
    }

    $invoice = Invoice::create([
      'type' => 'default',
      'mail' => $this->user->getEmail(),
      'invoice_number' => '10',
      'orders' => [$this->order],
      'store_id' => $this->store->id(),
      'billing_profile' => $this->profile,
      'state' => 'draft',
      'uid' => $this->user->id(),
    ]);
    $invoice->getState()->applyTransitionById('confirm');
    $invoice->save();
    $invoice = $this->reloadEntity($invoice);
    $this->assertTrue($invoice->getData('confirmation_email_queued'));
    $this->assertEquals('confirm', $invoice->getData('confirmation_email_transition'));

    if (isset($this->translations[$expected_langcode])) {
      $strings = $this->translations[$expected_langcode];
    }
    else {
      // Use the untranslated strings.
      $strings = array_keys($this->translations['fr']);
      $strings = array_combine($strings, $strings);
    }
    $subject = new FormattableMarkup($strings['Invoice #@number'], [
      '@number' => $invoice->getInvoiceNumber(),
    ]);

    $emails = $this->getMails();
    $email = reset($emails);
    $this->assertEquals($invoice->getEmail(), $email['to']);
    $this->assertEquals('bcc@example.com', $email['headers']['Bcc']);
    $this->assertEquals($expected_langcode, $email['langcode']);

    $this->assertEquals((string) $subject, $email['subject']);
    $this->assertStringContainsString('A new invoice has been created for you.', $email['body']);
    $this->assertNotEmpty($email['params']['attachments']);

    // Confirm that pdf file has been created for the invoice.
    $file = $invoice->getFile();
    $this->assertNotEmpty($file);
    $this->assertMatchesRegularExpression('#private://(.*)\.pdf#', $file->getFileUri());
    $this->assertEquals('10-' . $expected_langcode . '-pending.pdf', $file->getFilename());
    $this->assertEquals('application/pdf', $file->getMimeType());

    $attachments = $email['params']['attachments'];
    $attachment = reset($attachments);
    /** @var \Drupal\Core\File\FileSystemInterface $file_system */
    $file_system = \Drupal::service('file_system');
    $this->assertEquals($file_system->realpath($file->getFileUri()), $attachment['filepath']);
  }

  /**
   * Provides data for the multilingual email confirmation test.
   *
   * @return array
   *   The data.
   */
  public static function providerInvoiceConfirmationMultilingualData() {
    return [
      [NULL, 'en'],
      ['es', 'es'],
      ['fr', 'fr'],
      ['en', 'en'],
    ];
  }

}
