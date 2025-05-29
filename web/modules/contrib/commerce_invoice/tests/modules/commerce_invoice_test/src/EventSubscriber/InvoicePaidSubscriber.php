<?php

namespace Drupal\commerce_invoice_test\EventSubscriber;

use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class InvoicePaidSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'commerce_invoice.pay.pre_transition' => 'onPaid',
    ];
  }

  /**
   * Increments an invoice flag each time the paid transition is applied.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function onPaid(WorkflowTransitionEvent $event) {
    /** @var \Drupal\commerce_invoice\Entity\InvoiceInterface $invoice */
    $invoice = $event->getEntity();
    $flag = $invoice->getData('invoice_test_called', 0);
    $flag++;
    $invoice->setData('invoice_test_called', $flag);
  }

}
