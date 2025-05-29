Commerce Invoice
================

## Installation instructions

1. Enable invoicing for the desired order type by going to the order type admin edit form
2. From there, the automatic invoice generation (i.e automatic invoice generation when an order is placed) can be turned 
on.
3. The private filesystem path must be configured (See https://www.drupal.org/docs/8/core/modules/file/overview#private-file-system).

## Configuring the invoice types

The module provides a "default" invoice type that can be configured by navigating to /admin/commerce/config/invoice-types.

### Configuring Entity Print

Commerce Invoice depends on the [Entity Print](https://www.drupal.org/project/entity_print) module for the PDF 
generation of Invoices.
Once installed, please set the PDF engine to *Php Wkhtmltopdf* for optimal results.

For more information, please read [PDF Engine support](https://www.drupal.org/docs/8/modules/entity-print/pdf-engine-support).

## Customizing the invoice number

The generated invoice number can be customized by configuring the number pattern used for invoices.
By default the "default" invoice type that ships with the module uses the "infinite" number generation method.

The number patterns can be configured by navigating to /admin/commerce/config/number-patterns.

## Manually generating invoices

In case you need to manually generate invoices, the invoice generator service should be used:

```
$invoice_generator = \Drupal::service('commerce_invoice.invoice_generator');
$invoice_generator->generate($orders, $store, $profile);
```
