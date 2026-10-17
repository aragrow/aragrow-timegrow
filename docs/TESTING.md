# Testing

TimeGrow has unit tests for the billing math. They run without WordPress or a database, in well under a second.

## Run the tests

From the plugin folder:

```bash
composer install     # first time only: installs PHPUnit into vendor/
composer test        # or: vendor/bin/phpunit
```

`vendor/` is not committed. `composer.lock` is, so everyone gets the same PHPUnit version.

## What is covered

| File | Covers |
|---|---|
| `tests/unit/HourlyRateTest.php` | Rate order: project, then client, then company. A line's own rate: stored rate, then price / hours, then the rate order, then unit price for a hand-added line. |
| `tests/unit/HoursLinesTest.php` | Saving hours invoice lines: repricing only when hours change, quantity stays 1, discounts kept, hand-edited prices become the line rate, rounding is not an edit, 0 hours keeps the rate, regular orders untouched. |
| `tests/unit/InvoiceLinesTest.php` | PayPal quantity and rate for hours and regular lines, shipping and tax lines, and the PDF invoice line numbers. |
| `tests/unit/PayPalPaymentSyncTest.php` | What each PayPal invoice status does to the order: paid, marked paid, partial payments (and not recording the same amount twice), cancelled and refunded notes (once), and open invoices changing nothing. |
| `tests/unit/InvoiceDeliveryTest.php` | Each client's invoice delivery: Email by default and for unknown values, the saved choice, and orders following their customer. |
| `tests/unit/OrderCancellationTest.php` | Cancel invoice: which statuses can be cancelled, refusal when a payment is recorded, paid orders only moving to Refunded (Partial Paid also to Invoice Paid) and refunded invoices only to Cancelled, the amount PayPal shows as refunded, and what each PayPal status means (draft deleted, sent cancelled, paid stops the cancel). |
| `tests/unit/TimeGrowAITest.php` | The AI layer: key encryption (own and older Voice AI format, plain keys, never twice, damaged values), the key test for each provider and reading its answer (works, invalid key, missing model, provider busy or down = could not check), requests with an image and JSON for each provider, reading answers and token use, which failures are retried, the short model list and defaults. |
| `tests/unit/AIFeatureChoiceTest.php` | Which AI configuration each feature uses: the default until it picks one, its own choice, falling back (and saying so) when the choice was deleted, none saved, and the dropdown list (default first, readable labels). |
| `tests/unit/VoiceAssistantTest.php` | Voice questions and adding: plain wording read without AI (and time entries not mistaken for questions), team members refused owner questions and adding, the AI limited to listed intents and the person's clients and projects, periods and hour wording, client and project checks before creating. |
| `tests/unit/AIPromptsTest.php` | AI prompt versions: the built-in prompts ask for every key the code reads, missing keys are found, the built-in prompt is used when none is active, only known tools. |
| `tests/unit/AILogTest.php` | Saved AI activity: session keys the browser may send, messages cleaned and limited, and only person and chat lines accepted from the browser (AI and TimeGrow lines come from the server). |
| `tests/unit/PromptSafetyTest.php` | Prompt injection: spotting instructions to the AI (and not flagging normal work notes), data blocks that can't be closed from inside, the safety rules in each provider's system message, voice answers limited to the person's projects and time entry fields (also what the browser sends back), instructions on receipts dropped and flagged, believable amounts, exact CLIENT:/PROJECT: matches. |
| `tests/unit/ReceiptReaderTest.php` | The shared receipt reader: the prompt lists the real category IDs, headings left out of the categories, the category must be an active one (ID or slug), handwritten notes, bad dates, amounts and confidence range, refunds and text addressed to the AI. |
| `tests/unit/TimeEntryRulesTest.php` | Time entry save rules: hours above 0 and up to 24, real dates not in the future, clock times at most 10 minutes ahead, the project in TimeGrow, active and belonging to the client named. |
| `tests/unit/VoiceEntryTest.php` | Voice entry: checking what the AI heard (only the person's projects, client from the project, no future or invalid dates, hours in quarters, start time or "skip", billable), reading JSON from an AI answer, and the prompt. |
| `tests/unit/ProjectPickerTest.php` | Projects grouped by client for the time entry pickers: same-named projects kept apart, sorting, inactive projects left out unless the entry uses one, projects with no client. |
| `tests/unit/OrderCreatorTest.php` | Time entries to invoice lines: which entries count, hours for manual and clock entries (open clock entries skipped), rate order with the 75 fallback, line rounding, and which pending order is reused. |

Not covered yet: the PayPal API calls, the order creator's database and WooCommerce steps, and the reports (their SQL and HTML).

## How it works

- `tests/bootstrap.php` defines small stand-ins for the WordPress and WooCommerce functions the billing code calls (hooks do nothing, formatting matches WooCommerce for these inputs), then loads the WooCommerce integration and PayPal modules.
- `tests/fakes.php` has fake orders and line items, and `TimeGrow_Test_State`, which holds the data the stand-ins return (projects, user meta, company rate, orders). Each test resets it in `setUp()`.

## Adding a test

1. Build the data with the fakes, for example:

   ```php
   $item  = new WC_Order_Item_Product( 1, 300, 300, 10, [ '_timegrow_project_hours' => '2.00' ] );
   $order = TimeGrow_Fake_Order::hours_invoice( 1, [ 101 => $item ] );
   ```

2. Call the method and assert on the result.
3. If the code needs a WordPress function that is not stubbed yet, add it to `tests/bootstrap.php`.

Keep billing math in small methods (like `invoice_line_values()` and `line_quantity_and_rate()`) so it can be tested this way.

## End-to-end test (real site, PayPal sandbox)

`tests/e2e/paypal-flow.php` runs the whole billing flow inside your WordPress site and stops after each phase so you can look at what it made. It creates real records, so use it on your Local site, never on the live site.

| Phase | What it creates |
|---|---|
| Team members | Maria, Santiago and Eva (users + team member records), created on the first run and reused after. Each run picks one, two or all three at random to log the time |
| 1. Client | A new random client (WooCommerce customer): sometimes a person, sometimes a company, with billing details and a client rate |
| 2. Projects | Two projects: one with its own rate (120), one using the client rate, each with its WooCommerce product |
| 3. Time | 30 hours on weekdays in the last 14 days (18 + 12), mixed manual and clock entries |
| 4. Invoice | Runs Process Time for the client and checks the order: one hours invoice, quantity 1, total = hours x rate, every entry billed |
| 5. Send | Runs the **Send invoice** action for the client's delivery method. PayPal: reads the invoice back from the sandbox (status, total, quantities, view links). Email: checks one client email with the PDF attached and the order in Invoice Sent to Client. Print and mail: checks no email went out and the order is To Be Mailed and listed on Print & Mail |
| 6. Payment (optional, PayPal only) | After you pay as the sandbox buyer, syncs the payment back to the order |

Run it from the plugin folder in Local's site shell (Local > right-click the site > Open site shell):

```bash
wp eval-file tests/e2e/paypal-flow.php            # pause after each phase (Enter = next, q = stop)
wp eval-file tests/e2e/paypal-flow.php auto       # no pauses
wp eval-file tests/e2e/paypal-flow.php no-paypal  # stop after the invoice
wp eval-file tests/e2e/paypal-flow.php delivery=mail  # client delivery: paypal (default), email, mail or random
```

Options can be combined, for example `auto delivery=email`. It refuses to run against PayPal unless **Settings > PayPal Integration** is set to Sandbox, with sending on and keys saved. Everything a run creates is tagged with its run ID (user meta `_timegrow_e2e_run`, project descriptions, an order note).
