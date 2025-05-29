<?php

namespace Drupal\Tests\commerce_invoice\Kernel;

use Drupal\Component\FileSystem\FileSystem;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;

/**
 * Provides a base class for invoice kernel tests.
 */
abstract class InvoiceKernelTestBase extends OrderKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'entity_print',
    'entity_print_test',
    'file',
    'commerce_invoice',
    'commerce_invoice_test',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('file', ['file_usage']);
    $this->installEntitySchema('commerce_invoice_item');
    $this->installEntitySchema('commerce_invoice');
    $this->installEntitySchema('file');
    $this->installConfig([
      'commerce_invoice',
      'entity_print',
      'entity_print_test',
      'commerce_order',
      'system',
    ]);
    $this->container->get('theme_installer')->install(['stark']);

    // Set the default print engine.
    $config = $this->container->get('config.factory')->getEditable('entity_print.settings');
    $config
      ->set('print_engines.pdf_engine', 'testprintengine')
      ->save();

    $private = FileSystem::getOsTemporaryDirectory();
    $this->setSetting('file_private_path', $private);
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);

    $container->register('stream_wrapper.private', 'Drupal\Core\StreamWrapper\PrivateStream')
      ->addTag('stream_wrapper', ['scheme' => 'private']);
  }

}
