---
title: Send invoices with PayPal
roles: admin
page: timegrow-nexus-sync-payments
keywords: paypal, invoice, send invoice, email invoice, paid, payment, status, draft, cancel, refund, order
---
Invoices are WooCommerce orders. To send one through PayPal, set the order's status to "Send Invoice to PayPal". TimeGrow creates the PayPal invoice and, depending on Settings > PayPal Integration, PayPal emails it to the client with a payment link or keeps it as a draft in your PayPal account.

The order status then follows the invoice: Invoice Draft in PayPal, Invoice Sent to PayPal, Partial Paid and Invoice Paid. Payments come in through the PayPal webhook, or the scheduled check, or when you run Sync Payments. Cancelling an order's invoice cancels the PayPal invoice too, and can tell the client no payment is due.
