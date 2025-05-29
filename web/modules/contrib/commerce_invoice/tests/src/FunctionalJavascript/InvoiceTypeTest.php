<?php

namespace Drupal\Tests\commerce_invoice\FunctionalJavascript;

use Drupal\commerce_invoice\Entity\InvoiceType;
use Drupal\Tests\commerce\FunctionalJavascript\CommerceWebDriverTestBase;

/**
 * Tests the invoice type UI.
 *
 * @group commerce_invoice
 */
class InvoiceTypeTest extends CommerceWebDriverTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'commerce_invoice',
    'commerce_invoice_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'administer commerce_invoice_type',
    ], parent::getAdministratorPermissions());
  }

  /**
   * Tests whether the default invoice type was created.
   */
  public function testDefault() {
    $invoice_type = InvoiceType::load('default');
    $this->assertNotEmpty($invoice_type);

    $this->drupalGet('admin/commerce/config/invoice-types');
    $rows = $this->getSession()->getPage()->findAll('css', 'table tbody tr');
    $this->assertGreaterThan(1, $rows);
  }

  /**
   * Tests adding an invoice type.
   */
  public function testAdd() {
    $this->drupalGet('admin/commerce/config/invoice-types/add');
    $edit = [
      'label' => 'Foo',
      'footerText' => $this->randomString(),
      'paymentTerms' => 'payment terms!',
      'dueDays' => 20,
    ];
    $this->getSession()->getPage()->fillField('label', $edit['label']);
    $this->assertJsCondition('jQuery(".machine-name-value:visible").length > 0');
    $this->submitForm($edit, t('Save'));
    $this->assertSession()->pageTextContains('Saved the Foo invoice type.');

    $invoice_type = InvoiceType::load('foo');
    $this->assertEquals($edit['footerText'], $invoice_type->getFooterText());
    $this->assertEquals($edit['paymentTerms'], $invoice_type->getPaymentTerms());
    $this->assertEquals($edit['dueDays'], $invoice_type->getDueDays());
    $this->assertNotEmpty($invoice_type);
  }

  /**
   * Tests editing an invoice type.
   */
  public function testEdit() {
    $this->drupalGet('admin/commerce/config/invoice-types/default/edit');
    $edit = [
      'label' => 'Default!',
      'footerText' => $this->randomString(),
      'paymentTerms' => $this->randomString(),
      'dueDays' => 15,
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('Saved the Default! invoice type.');

    $invoice_type = InvoiceType::load('default');
    $this->assertNotEmpty($invoice_type);
    $this->assertEquals($edit['label'], $invoice_type->label());
    $this->assertEquals($edit['footerText'], $invoice_type->getFooterText());
    $this->assertEquals($edit['paymentTerms'], $invoice_type->getPaymentTerms());
    $this->assertEquals($edit['dueDays'], $invoice_type->getDueDays());
  }

  /**
   * Tests deleting an invoice type.
   */
  public function testDelete() {
    /** @var \Drupal\commerce_invoice\Entity\InvoiceTypeInterface $invoice_type */
    $invoice_type = $this->createEntity('commerce_invoice_type', [
      'id' => 'foo',
      'label' => 'Label for foo',
      'workflow' => 'invoice_default',
    ]);
    $this->drupalGet($invoice_type->toUrl('delete-form'));
    $this->assertSession()->pageTextContains(t('Are you sure you want to delete the invoice type @label?', ['@label' => $invoice_type->label()]));
    $this->assertSession()->pageTextContains(t('This action cannot be undone.'));
    $this->submitForm([], t('Delete'));
    $invoice_type_exists = (bool) InvoiceType::load($invoice_type->id());
    $this->assertEmpty($invoice_type_exists);
  }

  /**
   * Tests invoice type dependencies.
   */
  public function testInvoiceTypeDependencies() {
    $this->drupalGet('admin/commerce/config/invoice-types/default/edit');
    $this->submitForm(['workflow' => 'invoice_test_workflow'], t('Save'));

    $invoice_type = InvoiceType::load('default');
    $this->assertEquals('invoice_test_workflow', $invoice_type->getWorkflowId());
    $dependencies = $invoice_type->getDependencies();
    $this->assertArrayHasKey('module', $dependencies);
    $this->assertContains('commerce_invoice_test', $dependencies['module']);
  }

}
