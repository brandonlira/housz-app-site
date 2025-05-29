<?php

namespace Drupal\Tests\commerce_invoice\Kernel;

use Drupal\commerce_invoice\Entity\Invoice;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\user\Entity\User;

/**
 * Tests the invoice tokens.
 *
 * @group commerce
 */
class InvoiceTokensTest extends InvoiceKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'token',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
  }

  /**
   * Tests URL tokens for invoices.
   *
   * @dataProvider tokensTestData
   */
  public function testTokens(string $test_token, string $expected_replacement) {
    /** @var \Drupal\token\Token $token */
    $token = $this->container->get('token');
    $user = User::create([
      'uid' => '456',
      'name' => 'foobar',
      'mail' => 'foobar@example.com',
    ]);
    $user->enforceIsNew(FALSE);
    $invoice = Invoice::create([
      'invoice_id' => '123',
      'type' => 'default',
      'store_id' => $this->store->id(),
      'uid' => $user,
    ]);
    $invoice->enforceIsNew(FALSE);

    $token_data = ['commerce_invoice' => $invoice];
    $bubbleable_metadata = new BubbleableMetadata();
    $this->assertEquals($expected_replacement, $token->replace($test_token, $token_data, [], $bubbleable_metadata));
    $this->assertEquals(['commerce_invoice:123'], $bubbleable_metadata->getCacheTags());
  }

  /**
   * Test data for URL tokens.
   *
   * @return \Generator
   *   The test data.
   */
  public static function tokensTestData(): \Generator {
    yield [
      '[commerce_invoice:invoice_id]',
      '123',
    ];
    yield [
      '[commerce_invoice:url]',
      'http://localhost/user/456/invoices/123',
    ];
    yield [
      '[commerce_invoice:admin-url]',
      'http://localhost/admin/commerce/invoices/123',
    ];
  }

}
