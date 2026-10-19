---
title: Set up PayPal (credentials and key)
roles: admin
page: paypal-integration
keywords: paypal, key, keys, api key, client id, client secret, secret, credentials, api, setup, set up, connect, sandbox, live, webhook
---
Yes, PayPal needs its own credentials, separate from the AI key: a Client ID and a Client Secret. Get them in the PayPal developer dashboard (developer.paypal.com) under Apps & Credentials: create an app and copy its Client ID and Secret. Sandbox credentials are for testing; switch to Live credentials for real invoices.

In TimeGrow, open Settings > PayPal Integration. Turn on "Send orders to PayPal", pick the environment (Sandbox or Live), paste the Client ID and Client Secret, and choose Invoice Delivery: email the invoice to the client, or save it as a draft in PayPal to send yourself. The merchant email is optional.

For payments to show up right away, add a webhook in the PayPal developer dashboard with the URL shown under Webhook ID, and the events INVOICING.INVOICE.PAID, INVOICING.INVOICE.CANCELLED and INVOICING.INVOICE.REFUNDED, then paste its Webhook ID in TimeGrow. Without a webhook, "Check for payments" asks PayPal every hour, twice a day or once a day.
