# AI Receipt Analysis - Technical Reference

**Version:** 2.1.0 | **For:** Developers

---

## Current architecture (2.2.0)

This section replaces the per-provider analyzer description further down, which is kept for history. Since 2.2.0 there is one AI layer, in the core module, with no dependency on other plugins.

| Piece | File | What it does |
|---|---|---|
| `TimeGrowAI` | `modules/core/includes/helpers/TimeGrowAI.php` | The configuration each feature uses (Settings > AI table `timegrow_ai_config_tracker`), the API key (encrypted, see below), a short model list per provider (the recommended model and the lowest-cost one; a configuration using another model keeps it), the key test, one request client for every provider, retries, and per-user rate limits. |
| `TimeGrowAIReceiptAnalyzer` | `modules/core/includes/helpers/TimeGrowAIReceiptAnalyzer.php` | The only receipt reader. Same prompt for every provider, with the real category IDs; the returned category must be an active one (an ID, or a slug, else 0). Reads the image from disk and resizes it to 1,600 px. |
| `TimeGrowReceiptAnalyzerFactory` | same folder | Returns the reader above (kept so older callers work). |
| `TimeGrowNexusVoice` | `modules/nexus/includes/TimeGrowNexusVoice.php` | Voice entry: `merge()` checks, the AI call (`ask_ai()`), AJAX `timegrow_voice_parse`. Uses `TimeGrowAI::request()`. |
| `TimeGrowNexusAssistant` | `modules/nexus/includes/TimeGrowNexusAssistant.php` | Who may do what, reading requests without AI (`local_intent()`), checking the AI's intent (`check_intent()`), the conversation state, and adding clients and projects (AJAX `timegrow_voice_create`). |
| `TimeGrowNexusVoicePrompt` | same folder | Every voice prompt: the conversation, a first request, a time entry answer and help answers (instructions and request text). The conversation and help answer instructions are versioned in Settings > AI Prompts (tools `voice` and `help`; `{agent}` in the help prompt is the assistant's name). A help answer the help doesn't cover comes back as `answered: false` and TimeGrow writes the reply; an answer that copies 6 or more words in a row from its instructions is not shown (`TimeGrowNexusHelp::copies()`). |
| `TimeGrowNexusVoiceAnswers` | same folder | Answers to questions from TimeGrow's data (templates, read-only), the 3-month window and report links, `explain()`. |
| `TimeGrowNexusVoiceNames` | same folder | The client or project must be the one the person named: `check_names()`, `name_said()`, `match_client()`. |
| `TimeGrowNexusVoiceFormat` | same folder | Periods as dates and words, hours and money for answers. |
| Voice chat (browser) | `modules/nexus/assets/js/voice-entry.js` | The chat and its flows (time entry, punching in and out, adding). Helpers loaded first: `voice-text.js` (dates, times, hours), `voice-speech.js` (voices, speaking, listening), `voice-approval.js` (click to approve), `voice-log.js` (saving conversations). |
| `TimeGrowTimeEntryRules` | `modules/core/includes/helpers/TimeGrowTimeEntryRules.php` | Rules for every time entry save (Manual, Clock In / Out, voice): member from the logged-in user, any active project or only the member's assigned ones (Settings > General > Team members can record time on; assignments in `timegrow_team_member_projects_tracker`), hours above 0 and up to 24, no future dates, clock out only your own entry. |

**Settings.** Settings > **AI** holds only the provider configurations (table `timegrow_ai_config_tracker`: provider, model, key, and `is_active`, shown as **Default**). Each feature keeps its own switches and an **AI to use** choice (`ai_config_id`, 0 = the default): **AI Receipt Analysis** (`timegrow_receipt_settings`: `auto_analysis`, `confidence_threshold`, `ai_config_id`; until first saved it carries over the old per-configuration columns) and **AI Voice Entry** (`timegrow_voice_settings`). Both feature pages test the key of the configuration they use on load and show it with `render_ai_status()`, so a broken key only affects the features using it.

**Per-feature choice.** Callers pass the feature: `TimeGrowAI::config('receipts')`, `is_configured('voice')`, `request(['feature' => 'voice', ...])`. `choice($feature)` returns the row plus `fell_back` (the chosen configuration was deleted, so the default is used and the feature page says so). The saved ID is checked against the table on save; an unknown ID becomes 0. `configurations()` lists every configuration (default first) with a label (`Name: Provider · Model`) and its key status for the dropdowns.

**Request client.** `TimeGrowAI::request(['prompt', 'image' => ['mime', 'data'], 'json', 'max_tokens', 'timeout'])` returns `['text', 'usage', 'model']` or a `WP_Error`. `build_request()` and `parse_response()` are pure and unit tested:

| Provider | Endpoint | Key | JSON answers |
|---|---|---|---|
| Google Gemini | `v1beta/models/{model}:generateContent` | `x-goog-api-key` header (never in the URL) | `responseMimeType: application/json` |
| OpenAI | `v1/chat/completions` | `Authorization: Bearer` | `response_format: json_object` |
| Anthropic | `v1/messages` | `x-api-key` | asked in the prompt, read with `json_from_text()` |

It retries twice (after 1 and 2 seconds) on no connection, 408, 429 and 5xx. Failures are logged with provider, model and status only, never the body.

**Key test.** Settings > AI > **Test** and every load of the AI Receipt Analysis and AI Voice Entry pages call `TimeGrowAI::test()`: a model lookup (Gemini, OpenAI) or a 1-token message (Anthropic). 2xx = works; 401/403, a 400 about the key, or 404 (model) = not working, and `is_configured()` turns AI off; anything else (outage, 429, timeout) = could not check, and AI stays on. Results are stored per configuration in the `timegrow_ai_key_status` option, tied to a fingerprint of the stored key, so a new key starts as not tested.

**Keys.** Encrypted with AES-256-CBC, keyed from `AUTH_KEY` and `SECURE_AUTH_KEY` in `wp-config.php`, stored as `tg1:` + base64(iv + ciphertext). Values saved by the Aragrow Voice AI plugin (same method, no prefix) and plain keys are still read. `ARAGROW_AI_API_KEY` in `wp-config.php` is used when no key is saved. Changing the WordPress security keys makes stored AI keys unreadable: enter the key again.

**Prompt injection and permissions.** The AI only reads data and suggests field values; it never decides what a person may do.
- *Instructions apart from data.* `TimeGrowAI::SAFETY_RULES` plus the feature's instructions go in the system message (OpenAI `system` role, Anthropic `system`, Gemini `systemInstruction`). Everything from people, customers or documents goes in the user message inside `TimeGrowAI::untrusted()` blocks (`<untrusted_data name="...">`, angle brackets inside replaced, arrays as JSON), which the rules say never to obey. Client and project names count as untrusted: customers can set their own display name.
- *Direct injection.* `TimeGrowAI::looks_like_injection()` spots text such as "ignore the previous instructions" or "reveal your system prompt". Voice answers like that are not sent to the AI (`code: blocked`, logged with the user ID only); the chat carries on without AI.
- *Indirect injection.* Receipt text that reads like instructions is dropped from vendor, description, notes and CLIENT:/PROJECT:, confidence drops to 0.3 or less and the response has `suspicious: true` with a message to check every field.
- *Answers are checked.* Voice: `merge()` keeps only the time entry fields, only the person's own projects (and their client), valid dates, hours and times; fields the browser sends back get the same checks. Receipts: category from the active list, amount above 0 and up to 1,000,000, text cut to length, CLIENT:/PROJECT: only when exactly one name matches word for word (projects: active and allowed for the person).
- *Permissions are enforced on save, not by the AI.* Voice: the AJAX call needs `access_nexus_manual_entry`, and the save runs `TimeGrowTimeEntryRules` again (member from the login, allowed project, and, when a client is sent, the project must be that client's and both must exist). Receipts: owner capability; the person reviews and saves the form.

**Voice entry from any admin page.** `TimeGrowNexusVoice::enqueue()` loads `voice-entry.js` and `voice-entry.css` on every admin page when AI Voice Entry is on and the user may record time, with the person's projects (only projects whose client exists). A round mic icon opens the chat; away from Manual Time Entry it saves through `save_time_entry` with `client_id`. Speech uses the browser's most natural voice (`voiceScore()`: Natural/neural, Premium, Enhanced, then Google voices; joke voices and other languages never), spoken sentence by sentence at rate 1.03, with shorter spoken wording and a varied acknowledgement before each question. Each person can pick a voice under **Voice** in the chat (saved in the browser).

**Voice questions and adding (`TimeGrowNexusAssistant`).** The chat's first request can also be a question or a request to add a client or project. `local_intent()` reads plain wording without AI ("how many hours this week", "projects for Birchway", "add a client called …"); otherwise the AI gets the allowed intents and questions for this person only and returns an intent, which `check_intent()` limits to that list (anything else = `unknown`, not allowed = `denied`). Questions run as read-only lookups written from templates; the data found never goes to the AI.

| Question | Who |
|---|---|
| `last_entry`, `recent_entries`, `hours` (own), `clients`, `projects_for_client`, `latest_projects`, `clock_status` | Everyone who can record time (own entries, projects they may use) |
| `hours` with `who: team`, `unbilled_hours`, `expenses`, `unpaid_invoices` (WooCommerce) | Owners (`TIMEGROW_OWNER_CAP`) |
| Add a client (Customer user, username `client_MMDD_HHMMSS_xxxx`, no email sent) | Owners who can `create_users` |
| Add a project (with its WooCommerce product, as on Add Project) | Owners |

Adding is a chat form (client: name, email, company; project: client, name, rate, billable), then a summary and **Create**. `timegrow_voice_create` checks the capability again and `client_problem()` / `project_problem()` (name, valid unused email, no duplicate client or project name, rate 0–10,000, no instruction-like text).

**Conversation.** With AI on, every chat message goes to the AI with a short history (what the person said and what the chat asked, at most 8 lines of 200 characters, never the data in answers) and the state (what is being recorded or added, the field being asked, the last question). `TimeGrowNexusAssistant::read_state()` checks that state again on the server. Intents add `time`, `confirm`, `change` and `cancel`. Plain-phrase matching (`local_intent()`) is used only when the AI is off or can't be reached. A conversation lasts from opening the chat until it is closed (mic icon, close button or Esc) or the page reloads; closing clears the history, the last question and anything in progress, and replies that arrive afterwards are ignored.

**AI Prompts (Settings > AI Prompts).** Each tool's instructions can be edited as dated versions in `timegrow_ai_prompt_tracker` (created on admin load, `timegrow_ai_prompt_db` option): Draft (editable, deletable), Active (one per tool), Archived (history; read-only). `TimeGrowAIPrompts::instructions($feature)` returns the active version, else the built-in prompt (`TimeGrowNexusVoicePrompt::default_conversation_instructions()`, `TimeGrowAIReceiptAnalyzer::default_instructions()`). What is sent: `SAFETY_RULES` (not editable), then the instructions, then the data blocks added by code (not editable). A version must mention every key in `required_keys()` to be activated, and an active version that doesn't (edited in the database) is ignored. The AI status cards show the prompt in use and link to the page.

**One record at a time.** The AI never writes to the database; the voice path has no update or delete actions. Guards: `SAFETY_RULES` says at most one record per message, confirmed by a person, and no changing or deleting saved records; `looks_like_bulk()` refuses delete and many-record requests before the AI is asked (not while answering the description); `check_intent()` refuses list-shaped payloads instead of trimming them; `timegrow_voice_create` takes plain values only. Hourly limits: 20 clients/projects added by voice (`voice_create`), 60 voice time saves (`voice_save`, requests with `source=voice`).

**Saved for review (Settings > AI Conversations).** `TimeGrowAILog` saves AI activity when a tool's settings page has "Save for review" on (default on; "Keep for" 90 days, purged daily by the `timegrow_ai_conversations_purge` cron; 0 keeps them). Tables `timegrow_ai_session_tracker` and `timegrow_ai_turn_tracker` (created on admin load). Voice AI: one session per conversation; the chat makes up a session key and sends its messages (`log_turns`, at most 40 per request, 2,000 characters each, 400 per session) with each request, every few seconds, and when it closes (`timegrow_voice_log`, `sendBeacon` on page leave); a key belonging to another person is never written to. Receipt Reader: one session per reading (`TimeGrowAILog::start()`). The server adds "ai" turns (raw answer, what TimeGrow kept, tokens, time, prompt, model) and "system" turns (blocked, refused, read without AI, errors, records saved). Owners read sessions as a chat, rate them (1–5 stars and a note) and each AI reading (👍/👎), and compare prompt versions (sessions, average rating, readings marked right or wrong, tokens per reading). The chat tells people conversations are saved; receipt images are not copied.

**Receipt categories and flags.** `TimeGrowAIReceiptAnalyzer::usable_categories()` leaves out headings (categories other categories sit under, such as "Schedule C - Part II") and reads the section from `schedule_c_part`. The AI also returns `instructions_found` (text addressed to it: marked suspicious, confidence 0.3 or less) and `refund` (or a negative total: amount left blank, description "Refund: …", confidence 0.3 or less, and the person is told to record the refund themselves, since expenses can't be negative).

**Company and filters.** The conversation prompt includes the person's company (`TimeGrowNexusAssistant::company_name()`: their team member's company, else the site name) as its own data block, marked "not a client", so "the entire Aragrow" means the whole team even when a client has a similar name. The built-in prompt's "COMPANY, TEAM AND FILTERS" rules let a person clear a question filter by saying so ("all clients", "X is not a client") and send "which projects did I work on" to `hours`. Answers name a filter explicitly ("on the client X … Say "all clients" to see everything") and team answers always list the asker ("You (name)"), even at 0 h. Found by reviewing a rated conversation; covered by tests in `VoiceAssistantTest`.

**Billed amount.** `billed_amount` (owners, WooCommerce) answers "how much was billed" for a period, by client, with how much is paid. Billed = orders sent to the client (`BILLED_STATUSES`: invoice_sent, inv_sent2paypal, to_be_mailed, partial_paid, invoice_paid) by creation date; paid = invoice_paid. The AI never sees the amounts: it only picks the question, TimeGrow reads the orders. Team members' prompts list the administrators-only question names, so such a question is refused as "only available to administrators" instead of "unknown". A question it has no answer for gets "I can't answer that one yet. Here's what I can answer:" with the question buttons. Found by reviewing a rated conversation (session 42).

**Privacy layer (`TimeGrowAIPrivacy`).** Every voice AI request goes through `TimeGrowNexusVoice::ask_ai()`, which masks the request and unmasks the answer. Client names (display, billing and company names), team member names and the company become stand-ins built from database IDs (`[Client 31]`, `[Person 14]`, `[Your company]`), so they stay the same for a whole conversation; emails and phone numbers become `[Email n]` / `[Phone n]` (restored within the request; dates are never taken for phones). Names are also found inside project names, descriptions and what the person said, by full name or a distinctive word ("Copperleaf"; everyday words in `COMMON` are left alone); a word that fits several clients becomes `[one of Client 23, Client 24]`, and if the AI can't resolve it the person is asked "Which one do you mean?". A client whose name is the company's own ("Aragrow LLC" for "Aragrow, LLC") always means the company and is not offered as a client. Only names, amounts, hours, dates, statuses, project names and descriptions are ever used in requests: addresses, emails, phones, EIN/SSN (`_timegrow_ein_ssn`), the company's tax ID, IP addresses, user agents, payment and PayPal IDs, PINs and keys are not sent.

**Flexible lookup (`TimeGrowNexusLookup`, question `lookup`).** The AI describes a read-only lookup in `question.lookup` (entity: time_entries, projects, clients, expenses, invoices; metric: hours, amount, count, list; group_by; filters; period or from/to; limit ≤ 25). `spec()` checks it against `entities()`, drops anything unknown and applies the role rules; `run()` builds the query and writes the answer. Administrators: everything. Team members: their own time (any member filter is replaced by theirs and the answer says "You can only see your own time"), their projects and clients; expenses and invoices are refused. The AI is told the role (and, for administrators, the team members as stand-ins) only to word things well: TimeGrow enforces it. Order searches use `type => shop_order` so refunds are not mixed in.

**Explanations.** When the person asks why or which is best, the AI sets `question.explain`. `TimeGrowNexusVoiceAnswers::explain()` sends the answer and its lines through the privacy layer to the AI for 1 to 3 sentences (no numbers that are not in the results, none moved between lines), and puts the real names back; on any problem the plain answer is shown. Grouped lines show their own count ("$3,240.00 · 1 invoice").

**Help and how-to questions.** The question `help` (everyone) answers how to use TimeGrow, what a page or feature does and what the assistant can do (`TimeGrowNexusHelp`). Topics are Markdown files in `modules/nexus/help/`, one per task, with a header: `title`, `roles` (`everyone` or `admin`), `page` (the admin page, may include `&tab=`) and `keywords`. `search()` scores topics by the question's words (keyword 3, title 2, text 1, with simple stemming), keeps only close matches and never gives `admin` topics to team members; a team member asking about an administrator task is told so. A second AI call answers from the topics found only (masked like `explain()`); without AI the best topic's first paragraph is shown. "What can you do?" comes from `allowed_questions()` / `allowed_actions()`, so it is always right for the person. `is_how_to()` reads "how do I…", "where do I…", "what does X do" without AI, and keeps "how do I punch in?" from punching in. A unit test checks every menu page and Nexus page has a topic: add one with each new page. The files are the built-in topics; Settings > Knowledge Base (`TimeGrowHelpTopicsPage`) edits, hides and adds topics, saved in the `timegrow_help_topics` option (so plugin updates keep them) and applied by `TimeGrowNexusHelp::merge()`: an edited built-in topic replaces the file's text and can be reset, custom topics are `custom-…`. "Try a question" shows the topics `search()` would use, as an administrator or a team member.

**What a team member can see.** The "hours" question answers for the whole team only for administrators; a team member asking about the team gets their own hours with "You can only see your own time" (as the flexible lookup does). The privacy layer still replaces every name before the AI, but for a team member `TimeGrowAIPrivacy::restrict()` limits the real names it shows back (in answers and in "Which one do you mean?") to their own clients and themselves; others read "another client" / "another person". Report CSV exports go through `TimeGrowCsv::put()`, which neutralizes cells that a spreadsheet would run as a formula.

**Approval and prompt injection.** Every action waits for the person: the chat shows a summary and saves, punches in or out, or creates a client or project only after approval. With "Saving needs a click" (voice setting `confirm_click`, on by default) only a click on the button approves; a spoken or typed "yes" gets "tap Save entry". The chat sends `approved` (click, spoken or typed) with each save; `TimeGrowNexusVoice::approval_problem()` refuses voice saves without a click on the server (`save_time_entry` for every entry type, and `timegrow_voice_create`), and the Time Entry Audit Log shows it ("Voice · clicked to approve"). Prompt injection is found by the AI, which reads every message and returns the intent `injection` (asking for its prompt or instructions, telling it to ignore or change them, a new role, orders addressed to an AI), by meaning and in any language, also mixed with a real request; `TimeGrowAI::looks_like_injection()` is a quick first check for obvious attacks and works without AI. Either way the request stops (nothing else is read, anything in progress is dropped), the person is told it looks like a prompt injection attempt, and `TimeGrowAILog::injection()` records it, even with saving conversations off: a session in Settings > AI Conversations with the result "Prompt Injection Attempt" and what found it, and a line in the server error log (who and when). Receipts with text addressed to the AI are logged the same way. Messages unrelated to TimeGrow are not injections: they get a short, kind "that's outside what I can do" with what it can do.

**Clients and projects must be the ones named.** `TimeGrowNexusVoiceNames::check_names()` checks every client and project the AI picks against the person's words (the name or one of its words, the client of a project they named, a reference back like "the same client", or one already chosen); any other pick is dropped and the name they used is returned as `not_found`, so the chat says it couldn't find it and asks again (administrators get an "Add as a new client / project" button). The AI is told to put names it can't find in `time.unmatched` instead of guessing. A message the AI gives up on that clearly records time ("add 3 hours … today") is still read as a time entry (`time_from_words()`).

**Receipts.** Receipts are stored privately (`uploads/timegrow-receipts/`, random names, deny-all `.htaccess`; on nginx add a `deny all` location) and open only through `TimeGrowExpenseReceiptModel::view_url()` → `handle_view()`: administrators every receipt, a team member only the ones they uploaded (`uploaded_by`), unknown uploaders administrators only. The copy uploaded for AI reading is deleted right after it is read.

**Moving prompts and the knowledge base between sites.** Settings > Knowledge Base and Settings > AI Prompts have Export and Import (`TimeGrowTransfer`). A file is JSON with `type` (`timegrow-knowledge-base` or `timegrow-ai-prompts`), `version`, `site` and `exported_at`. Import checks the file, keeps it for 15 minutes per person (transient) and shows a preview; nothing changes until confirmed. Knowledge base: only topics in the file change (`plan_topics()`: new, changed, hidden, same, skipped), the previous changes are saved in `timegrow_help_topics_backup` for "Undo last import". Prompts: every version comes in as a new draft with an "Imported from…" note, versions with the same text as one here are skipped (`plan_prompts()`), and optionally the versions active on the other site are activated (the ones in use are archived, as usual).

**Name and voice.** The assistant's name is the `agent_name` voice setting (default "Sam Spade", `TimeGrowNexusVoice::agent_name()`, checked by `TimeGrowSettings::valid_agent_name()`). It is sent to the browser as `cfg.agentName` and told to the AI in each request by `conversation_prompt()` ("You are …, TimeGrow's assistant"), so it works with any saved prompt version. A leading name ("Hey Sam,") is removed from the person's words before anything else reads them (`without_address()`). The voice is the browser's speech synthesis: the person's pick in the chat (localStorage `timegrowVoiceName`), else the `voice_name` setting (`cfg.voiceName`) when that device has it, else the highest `voiceScore()` (natural / neural / premium / enhanced / Google voices in the speech language first). Rate 1.03, pitch 1.

**Manual entries and punching in or out.** A manual entry has hours, so the chat never asks for a start time (`ASKED` in `voice-entry.js`); one the person says is kept. The "Ask for a start time" setting was removed. "Punch in / out", "clock in / out" and "start / stop the clock" are the `clock_in` / `clock_out` intents, with a `clock` payload `{client_id, project_id, time}` (`time` is HH:MM or "now") checked by `check_intent()`. `TimeGrowNexusAssistant::clock_action()` reads the words without AI (the `local_intent()` fallback, and when the AI returns `unknown` or `time` without hours). `send_intent()` refuses people without `access_nexus_clock` (administrators always may) and adds `clock_status()`, so the chat can say "already clocked in" or "not clocked in". The chat asks the client and project (clock in only), then the time, refuses a time more than 10 minutes ahead of the site's time (`cfg.now`) or a stop before the clock in, confirms, then posts `save_time_entry` with `entry_type` `CLOCK_IN` / `CLOCK_OUT`, the same save and rules as the Clock page. Conversation modes `clock_in`, `clock_out` and `confirm_clock` carry `clockAction` and `clock` in the state.

**Data sent to the AI, and how much.** Answers go to the AI with later messages: the chat keeps each answer's headline and up to 8 of its lines in the history (600 characters per message, 8 messages), and the server's privacy layer replaces names and contact details first. To keep requests small, time entries and invoices, payments and refunds are read from the last 3 months only (`TimeGrowNexusVoiceAnswers::AI_DATA_MONTHS`, `data_floor()`): a period partly older than that is answered for the last 3 months and `add_window_note()` adds "(I can only look at the last 3 months, so this covers <date> to today. For the full period, use the reports below.)"; a period entirely older (for example `last_year`) is not run, and `out_of_window_reply()` says so instead. Both add links to the reports that cover any period (`reports_for()`): invoices → Client Activity, Yearly Tax, Monthly Profit & Loss; team time → Team Hours Summary, Time Entry Audit Log; own time → My Hours by Project, My Detailed Time Log. Team members only get links to their own reports. Lookups of time entries and invoices default to the last 3 months. Expenses are not limited. Invoice lookups with no status count invoices sent to the client, like `billed_amount`. The question a reply was given for is remembered with its default period filled in (`effective_question()`), and clear follow-ups the AI can't place ("I mean all the team members", "what about me", "X is not a client") are built on the last question without AI (`followup()`).

**Limits.** Per user per hour: 30 receipt reads, 120 voice answers (`timegrow_ai_rate_limit` filter, arguments `$limit, $user_id, $bucket`). Voice sends at most 500 characters per answer and asks for at most 300 output tokens.

---

## Overview
Complete multi-provider AI system for receipt analysis and expense data extraction. Supports Google Gemini, OpenAI GPT-4, and Anthropic Claude with 33 IRS Schedule C categories.

---

## ✅ Completed Features (10 of 11 tasks)

### 1. Modern Settings Page Design
**Files Modified:**
- `modules/core/includes/TimeGrowSettings.php`

**Features:**
- Beautiful card-based overview page
- Shows AI configuration status (Configured/Not Configured)
- Modern gradient header with illustrations
- Settings cards for General and AI Provider configuration
- Help section with documentation links
- Enqueues modern CSS from Nexus module

**Access:** TimeGrow → Settings

---

### 2. Critical Bug Fix
**File:** `modules/core/includes/controllers/TimeGrowExpenseController.php` (Line 101)

**Fix:**
```php
// BEFORE (broken):
if ($enable_analysis && $id != 0) {  // Only analyzed when EDITING

// AFTER (correct):
if ($enable_analysis && $id == 0) {  // Analyzes when CREATING new expenses
```

**Impact:** AI now correctly analyzes receipts when creating new expenses, not when editing existing ones.

---

### 3. Enhanced AI Prompts with IRS Categories
**Files Modified:**
- `modules/core/includes/helpers/TimeGrowGeminiReceiptAnalyzer.php`
- `modules/core/includes/helpers/TimeGrowOpenAIReceiptAnalyzer.php`
- `modules/core/includes/helpers/TimeGrowClaudeReceiptAnalyzer.php`

**Enhancements:**
- **33 IRS Schedule C Categories:**
  - Part II (22 categories): advertising, car_truck_expenses, commissions_fees, etc.
  - Part V (11 categories): online_web_fees, business_telephone, education_training, etc.
- **Vendor-to-Category Hints:**
  - Staples, Office Depot → office_expense
  - Shell, Chevron, BP → car_truck_expenses
  - GoDaddy, Hostinger → online_web_fees
  - FedEx, UPS, USPS → shipping_postage
- **Detailed Category Descriptions** for better AI accuracy
- **Legacy Mapping** for backwards compatibility

---

### 4. Multi-Provider Architecture
**New Files Created:**
- `modules/core/includes/helpers/TimeGrowReceiptAnalyzerInterface.php` - Interface definition
- `modules/core/includes/helpers/TimeGrowReceiptAnalyzerFactory.php` - Factory pattern

**Architecture:**
```
TimeGrowReceiptAnalyzerInterface
    ↓ implements
    ├── TimeGrowGeminiReceiptAnalyzer (Google Gemini)
    ├── TimeGrowOpenAIReceiptAnalyzer (OpenAI GPT-4)
    └── TimeGrowClaudeReceiptAnalyzer (Anthropic Claude)

TimeGrowReceiptAnalyzerFactory
    ↓ creates
    Correct analyzer based on settings
```

**Controller Integration:**
```php
// Old:
$analyzer = new TimeGrowGeminiReceiptAnalyzer();

// New:
$analyzer = TimeGrowReceiptAnalyzerFactory::create();
```

---

### 5. Google Gemini Analyzer (Updated)
**File:** `modules/core/includes/helpers/TimeGrowGeminiReceiptAnalyzer.php`

**Updates:**
- ✅ Implements `TimeGrowReceiptAnalyzerInterface`
- ✅ Enhanced prompts with all 33 IRS categories
- ✅ Vendor-to-category intelligent mapping
- ✅ Client/Project pattern matching
- ✅ Confidence scoring (0.0-1.0)
- ✅ Rate limiting support via VoiceAI Security
- ✅ Encrypted API key storage

**Models Supported:**
- gemini-1.5-flash (Default - Faster, Cheaper)
- gemini-1.5-pro (More Accurate)
- gemini-2.0-flash-exp (Experimental)

---

### 6. OpenAI GPT-4 Analyzer (New)
**File:** `modules/core/includes/helpers/TimeGrowOpenAIReceiptAnalyzer.php`

**Features:**
- ✅ Full parity with Gemini features
- ✅ Base64 image encoding in data URL format
- ✅ All 33 IRS Schedule C categories
- ✅ Client/Project auto-assignment
- ✅ Confidence threshold filtering
- ✅ Error handling and rate limiting

**Models Supported:**
- gpt-4o (Best for vision - Default)
- gpt-4o-mini (Faster, Cheaper)
- gpt-4-turbo

**API Details:**
- Endpoint: `https://api.openai.com/v1/chat/completions`
- Auth: Bearer token
- Timeout: 30 seconds

---

### 7. Anthropic Claude Analyzer (New)
**File:** `modules/core/includes/helpers/TimeGrowClaudeReceiptAnalyzer.php`

**Features:**
- ✅ Full parity with other analyzers
- ✅ Base64 image with media type
- ✅ All 33 IRS categories supported
- ✅ Client/Project pattern matching
- ✅ Confidence scoring

**Models Supported:**
- claude-3-5-sonnet-20241022 (Best for vision - Default)
- claude-3-opus-20240229 (Most capable)
- claude-3-haiku-20240307 (Fastest)

**API Details:**
- Endpoint: `https://api.anthropic.com/v1/messages`
- Auth: x-api-key header
- Version: anthropic-version: 2023-06-01

---

### 8. Visual Loading States
**Files Modified:**
- `modules/core/assets/js/expense.js` - Added loading indicator
- `modules/core/includes/views/TimeGrowExpenseView.php` - Added AI status flag
- `modules/core/includes/TimeGrowExpense.php` - Enqueued AI CSS

**New File:**
- `modules/core/assets/css/expense-ai.css` - AI-specific styling

**Features:**
- ✅ Animated spinner during AI analysis
- ✅ "Analyzing receipt with AI..." message
- ✅ Automatically shows when file uploaded + AI enabled
- ✅ Styled with modern gradient and animation

**CSS Classes:**
- `.confidence-badge` - Success/Warning/Error badges
- `.receipt-ai-info` - AI analysis metadata display
- `.reanalyze-receipt` - Button styling
- `.ai-provider-badge` - Provider identification
- `.bulk-upload-progress` - Progress indicators

---

### 9. Smart Field Population Logic
**Files Modified:**
- `modules/core/includes/controllers/TimeGrowExpenseController.php` - Smart population logic
- `modules/core/includes/views/TimeGrowExpenseView.php` - Warning display

**Features:**
- ✅ **Only populates empty fields** - AI will never overwrite user-entered data
- ✅ **Mismatch detection** - Warns if user data differs from receipt
- ✅ **Field-specific validation:**
  - Amount: Warns if difference > $0.01
  - Date: Warns if dates don't match
  - Vendor: Fuzzy matching with warnings
  - Category: Suggests better category if mismatch
- ✅ **Clear messaging** - Shows which fields were populated and any warnings

**User Documentation:** See `AI-USER-GUIDE.md`

---

### 10. User Approval System
**Files Modified:**
- `modules/core/includes/views/TimeGrowExpenseView.php` - Approval checkbox UI
- `modules/core/assets/js/expense.js` - Show/hide approval checkbox
- `modules/core/includes/controllers/TimeGrowExpenseController.php` - Approval check

**Features:**
- ✅ **Checkbox approval required** - AI only runs if user checks the approval box
- ✅ **Auto-shows when file uploaded** - Checkbox appears automatically with smooth slideDown animation
- ✅ **Auto-checked by default** - For user convenience (can uncheck if desired)
- ✅ **Provider name displayed** - Shows which AI provider will analyze (e.g., "Google Gemini", "OpenAI GPT-4")
- ✅ **Respects user control** - No AI analysis without explicit approval

**User Experience:**
```
1. User uploads receipt file
2. Checkbox appears: "☑ Analyze this receipt with AI (Google Gemini) to auto-populate expense fields"
3. User can check/uncheck before submitting
4. Form submits → AI runs only if checked
```

---

### 11. Configuration Validation & Graceful Degradation
**Files Modified:**
- `modules/core/includes/controllers/TimeGrowExpenseController.php` - Configuration validation

**Features:**
- ✅ **Validates AI configuration** - Checks if API key and provider are set
- ✅ **Graceful degradation** - Expense saves successfully even if AI not configured
- ✅ **Clear warning messages:**
  - "⚠ AI Analysis Skipped: AI is not properly configured. Please add your API key in TimeGrow Settings."
  - "⚠ AI Analysis Skipped: You chose not to analyze this receipt with AI."
- ✅ **No blocking errors** - Users can still create expenses without AI

**Workflow:**
```
If AI not configured:
  → Show warning
  → Skip AI analysis
  → Save expense normally
  → Success!

If user doesn't approve:
  → Skip AI analysis
  → Save expense normally
  → Success!
```

---

## 🎯 What Works Now

### For Users
✅ **Choose AI Provider**
- Settings → AI Provider
- Select: Google Gemini, OpenAI, or Anthropic Claude
- Choose model variant (flash/pro/mini/sonnet/opus)

✅ **Upload Receipt Images**
- Create new expense
- Drag & drop or click to upload receipt (JPEG, PNG)
- Checkbox appears: "Analyze this receipt with AI..."
- Check the box to approve AI analysis (auto-checked by default)
- Submit form → AI analyzes if approved

✅ **Smart Auto-Extraction**
- **Only populates empty fields** - Won't overwrite user-entered data
- **Warns about mismatches** - Alerts if user input differs from receipt
- Amount (e.g., $125.50)
- Date (YYYY-MM-DD format)
- Vendor/Business name
- Category (from 33 IRS Schedule C categories)
- Description/Items purchased

✅ **Smart Category Mapping**
- AI maps vendors to specific categories
- Example: "Staples" → Office Expense
- Example: "Shell Gas Station" → Car and Truck Expenses

✅ **Client/Project Auto-Assignment**
- Add "CLIENT: ABC Corp" to receipt → Auto-assigns to client
- Add "PROJECT: Website Redesign" → Auto-assigns to project

✅ **Confidence Filtering**
- Only auto-fills if AI confidence ≥ threshold (default 70%)
- Prevents low-quality analysis from populating data
- User sees confidence percentage in admin notices

✅ **Visual Feedback**
- Loading spinner during analysis
- Success messages with confidence scores
- Warning messages if AI not configured
- Clear messaging about which fields were populated

✅ **User Control**
- Must approve AI analysis before it runs (checkbox)
- Can create expenses without AI if desired
- Graceful degradation if AI not configured

---

## 📋 Optional Future Enhancements (Not Required)

### Confidence Score Display & Re-Analyze Button
**Status:** Optional Enhancement
**Estimated Effort:** 2-3 hours

**Features:**
- Display confidence badges on receipt list
- Add "Re-Analyze with AI" button to edit expense page
- AJAX handler for re-analysis
- Update expense fields with new analysis

**Files to Modify:**
- `modules/core/includes/views/TimeGrowExpenseView.php`
- `modules/core/assets/js/expense.js`
- `modules/core/includes/controllers/TimeGrowExpenseController.php`

---

### PDF Support with Image Conversion
**Status:** Optional Enhancement
**Estimated Effort:** 2 hours

**Approach:**
Use Imagick to convert PDF to images for analysis

**New File:**
- `modules/core/includes/helpers/TimeGrowPDFExtractor.php`

**Files to Modify:**
- All 3 analyzer classes (Gemini, OpenAI, Claude)
- Update `supports_pdf()` to return `true`

---

### Bulk Upload Support
**Status:** Optional Enhancement
**Estimated Effort:** 3 hours

**Features:**
- Upload multiple receipts at once
- Analyze first receipt to populate expense fields
- Store remaining receipts as additional attachments
- Progress indicator during bulk processing

**Files to Modify:**
- `modules/core/includes/views/TimeGrowExpenseView.php` - Multiple file input
- `modules/core/assets/js/expense.js` - Multi-file handling
- `modules/core/includes/controllers/TimeGrowExpenseController.php` - Loop processing

---

## 🔧 Configuration Guide

### Setup Instructions

1. **Navigate to Settings:**
   - WordPress Admin → TimeGrow → Settings

2. **Configure AI Provider:**
   - Click "AI Receipt Analysis" card
   - Select provider: Google Gemini / OpenAI / Anthropic Claude
   - Choose model variant
   - Enter API Key (will be encrypted)
   - Enable "Automatically analyze receipts when uploaded"
   - Set confidence threshold (0.7 = 70% recommended)
   - Save settings

3. **Get API Keys:**
   - **Google Gemini:** https://aistudio.google.com/apikey
   - **OpenAI:** https://platform.openai.com/api-keys
   - **Anthropic:** https://console.anthropic.com/settings/keys

4. **Test the System:**
   - TimeGrow → Expenses → Add New
   - Upload a receipt image
   - Watch AI analyze and populate fields
   - Verify accuracy and save

---

## 📁 File Structure

### New Files Created
```
modules/core/includes/helpers/
├── TimeGrowReceiptAnalyzerInterface.php (Interface)
├── TimeGrowReceiptAnalyzerFactory.php (Factory)
├── TimeGrowOpenAIReceiptAnalyzer.php (OpenAI)
└── TimeGrowClaudeReceiptAnalyzer.php (Claude)

modules/core/assets/css/
└── expense-ai.css (AI-specific styles)

PLAN-ai-expense-image-reading.md (Implementation plan)
IMPLEMENTATION-SUMMARY.md (This file)
```

### Modified Files
```
modules/core/includes/
├── TimeGrowSettings.php (Modern UI)
├── TimeGrowExpense.php (Enqueue AI CSS)
├── controllers/TimeGrowExpenseController.php (Bug fix + Factory)
├── views/TimeGrowExpenseView.php (AI status flag)
└── helpers/TimeGrowGeminiReceiptAnalyzer.php (Enhanced)

modules/core/assets/js/
└── expense.js (Loading states)
```

---

## 🎨 Design Patterns Used

1. **Interface Segregation:** `TimeGrowReceiptAnalyzerInterface`
2. **Factory Pattern:** `TimeGrowReceiptAnalyzerFactory`
3. **Strategy Pattern:** Swappable AI analyzers
4. **Dependency Injection:** Models passed to controllers
5. **MVC Architecture:** Models, Views, Controllers separation

---

## 🔒 Security Features

- ✅ Encrypted API key storage via VoiceAI Security
- ✅ Rate limiting to prevent abuse
- ✅ Input sanitization on all AI responses
- ✅ Nonce verification on form submissions
- ✅ Capability checks on settings pages
- ✅ WP_Error handling throughout
- ✅ SSL verification on API calls (disabled in WP_DEBUG)

---

## 📊 Success Metrics

- ✅ AI analysis runs only with user approval (checkbox required)
- ✅ Smart field population - only fills empty fields, never overwrites user data
- ✅ Mismatch detection warns about potential data entry errors
- ✅ All 33 IRS Schedule C categories properly mapped
- ✅ Users can choose between 3 AI providers (Google, OpenAI, Anthropic)
- ✅ Visual loading indicators keep users informed
- ✅ Graceful degradation if AI not configured - expenses save successfully
- ✅ Configuration validation prevents silent failures
- 🔮 PDF receipts processing (optional enhancement)
- 🔮 Bulk upload functionality (optional enhancement)
- 🔮 Confidence scores displayed in receipt list (optional enhancement)
- 🔮 Re-analysis feature (optional enhancement)

---

## 🚀 Next Steps

### Immediate (Ready to Use)
The core AI infrastructure is **fully functional** and production-ready:
1. Configure an AI provider in Settings (TimeGrow → Settings → AI Provider)
2. Add your API key (Google Gemini, OpenAI, or Anthropic)
3. Create a new expense (TimeGrow → Expenses → Add New)
4. Upload a receipt image
5. Check the approval box: "☑ Analyze this receipt with AI..."
6. Submit → AI extracts data and populates empty fields
7. Review populated fields and any warnings
8. Save expense

### Optional Future Enhancements
1. Add confidence score badges in receipt list
2. Implement "Re-Analyze with AI" button on edit page
3. Add PDF support with Imagick conversion
4. Enable bulk multi-receipt uploads

### Long-term (Future Considerations)
1. AI training/feedback loop
2. Custom category mapping
3. Multi-language support
4. Receipt template learning

---

---

## 📚 Documentation

- **User Guide:** `AI-USER-GUIDE.md` - Setup, usage, troubleshooting
- **Technical Reference:** This file - Architecture, implementation details
- **Original Plan:** `PLAN-ai-expense-image-reading.md.archive` - Historical reference

---

**Version:** 2.1.0
**Last Updated:** February 13, 2026
**Status:** ✅ Production Ready (10/10 core features complete)
