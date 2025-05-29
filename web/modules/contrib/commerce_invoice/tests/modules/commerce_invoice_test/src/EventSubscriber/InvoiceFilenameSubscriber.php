<?php

namespace Drupal\commerce_invoice_test\EventSubscriber;

use Drupal\commerce_invoice\Event\InvoiceEvents;
use Drupal\commerce_invoice\Event\InvoiceFilenameEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class InvoiceFilenameSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      InvoiceEvents::INVOICE_FILENAME => 'alterFilename',
    ];
  }

  /**
   * Alters the invoice filename.
   *
   * @param \Drupal\commerce_invoice\Event\InvoiceFilenameEvent $event
   *   The transition event.
   */
  public function alterFilename(InvoiceFilenameEvent $event) {
    $invoice = $event->getInvoice();
    // Alter the filename if the "alter_filename" data flag is present.
    if ($invoice->getData('alter_filename')) {
      $event->setFilename($event->getFilename() . '-altered');
    }
  }

}
