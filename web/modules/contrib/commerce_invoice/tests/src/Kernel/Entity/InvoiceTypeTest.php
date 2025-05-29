<?php

namespace Drupal\Tests\commerce_invoice\Kernel\Entity;

use Drupal\commerce_invoice\Entity\InvoiceType;
use Drupal\commerce_number_pattern\Entity\NumberPatternInterface;
use Drupal\file\Entity\File;
use Drupal\Tests\commerce_invoice\Kernel\InvoiceKernelTestBase;

/**
 * Tests the invoice type entity.
 *
 * @coversDefaultClass \Drupal\commerce_invoice\Entity\InvoiceType
 *
 * @group commerce_invoice
 */
class InvoiceTypeTest extends InvoiceKernelTestBase {

  /**
   * @covers ::id
   * @covers ::label
   * @covers ::getNumberPattern
   * @covers ::getNumberPatternId
   * @covers ::setNumberPatternId
   * @covers ::getLogoUrl
   * @covers ::getLogoFile
   * @covers ::setLogo
   * @covers ::getFooterText
   * @covers ::setFooterText
   * @covers ::getDueDays
   * @covers ::setDueDays
   * @covers ::getPaymentTerms
   * @covers ::setPaymentTerms
   * @covers ::getPrivateSubdirectory
   * @covers ::setPrivateSubdirectory
   */
  public function testInvoiceType() {
    $file = File::create([
      'fid' => 1,
      'filename' => 'test.png',
      'filesize' => 100,
      'uri' => 'public://images/test.png',
      'filemime' => 'image/png',
    ]);
    $file->save();
    $file = $this->reloadEntity($file);
    $values = [
      'id' => 'test_id',
      'label' => 'Test label',
      'footerText' => $this->randomString(),
      'paymentTerms' => $this->randomString(),
      'numberPattern' => 'invoice_default',
      'logo' => $file->uuid(),
      'dueDays' => 10,
      'workflow' => 'invoice_default',
    ];
    $invoice_type = InvoiceType::create($values);
    $invoice_type->save();
    $this->assertEquals('test_id', $invoice_type->id());
    $this->assertEquals('Test label', $invoice_type->label());

    $this->assertEquals($values['numberPattern'], $invoice_type->getNumberPatternId());
    $this->assertInstanceOf(NumberPatternInterface::class, $invoice_type->getNumberPattern());
    $invoice_type->setNumberPatternId('test');
    $this->assertEquals('test', $invoice_type->getNumberPatternId());

    $this->assertEquals($file->createFileUrl(FALSE), $invoice_type->getLogoUrl());
    $this->assertEquals($file, $invoice_type->getLogoFile());

    $this->assertEquals($values['footerText'], $invoice_type->getFooterText());
    $invoice_type->setFooterText('Footer text (modified)');
    $this->assertEquals('Footer text (modified)', $invoice_type->getFooterText());

    $this->assertEquals($values['dueDays'], $invoice_type->getDueDays());
    $invoice_type->setDueDays(15);
    $this->assertEquals(15, $invoice_type->getDueDays());

    $this->assertEquals($values['paymentTerms'], $invoice_type->getPaymentTerms());
    $invoice_type->setPaymentTerms('Payment terms (modified)');
    $this->assertEquals('Payment terms (modified)', $invoice_type->getPaymentTerms());

    $invoice_type->setPrivateSubdirectory('invoices');
    $this->assertEquals('invoices', $invoice_type->getPrivateSubdirectory());
    $invoice_type->setPrivateSubdirectory(NULL);
    $this->assertNull($invoice_type->getPrivateSubdirectory());
  }

}
