<?php

namespace Drupal\Tests\commerce_invoice\Functional;

use Drupal\Tests\commerce\Functional\CommerceBrowserTestBase;

/**
 * Defines base class for commerce_invoice test cases.
 */
abstract class InvoiceBrowserTestBase extends CommerceBrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'commerce_cart',
    'commerce_product',
    'commerce_invoice',
    'commerce_invoice_test',
    'entity_print_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'administer commerce_invoice',
      'administer commerce_invoice_type',
      'access commerce_invoice overview',
    ], parent::getAdministratorPermissions());
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set the default engine.
    $config = $this->container->get('config.factory')->getEditable('entity_print.settings');
    $config
      ->set('print_engines.pdf_engine', 'testprintengine')
      ->save();
  }

}
