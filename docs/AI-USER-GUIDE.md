# AI Receipt Analysis - User Guide

**Version:** 2.1.0 | **Status:** Production Ready

---

## Quick Start

### 1. Configure AI Provider
1. **TimeGrow → Settings → AI Receipt Analysis**
2. Choose provider: **Google Gemini** / **OpenAI** / **Anthropic Claude**
3. Enter your **API Key** (get from provider's website)
4. Click **Save Settings**

**Get API Keys:**
- Google Gemini: https://aistudio.google.com/apikey
- OpenAI: https://platform.openai.com/api-keys
- Anthropic Claude: https://console.anthropic.com/settings/keys

### 2. Use AI on Receipts
1. **TimeGrow → Expenses → Add New**
2. Upload receipt image (JPEG/PNG)
3. Checkbox appears: "☑ Analyze this receipt with AI..."
4. Submit → AI populates empty fields
5. Review & save

---

## How It Works

### User Approval Required
**AI only runs when you check the approval box.**

- Upload receipt → Checkbox appears (auto-checked)
- Uncheck if you don't want AI for this receipt
- No approval = No AI analysis

### Smart Field Population
**AI never overwrites your data.**

**Populates only empty fields:**
- Amount, Date, Vendor, Category, Description
- Client/Project (if pattern found on receipt)

**Warns about mismatches:**
- Amount differs by >$0.01
- Date doesn't match
- Vendor name differs
- Wrong category selected

**Example:**
```
You enter:
- Vendor: "Office Store"
- Amount: (empty)

Receipt shows:
- Vendor: "Office Depot"
- Amount: $45.99

Result:
- Vendor: "Office Store" (kept)
- Amount: $45.99 (populated by AI)
- Warning: "Vendor mismatch detected"
```

### Graceful Degradation
**AI not configured? No problem.**

- Missing API key → Warning shown, expense saves normally
- Unchecked approval box → Expense saves normally
- AI timeout/error → Warning shown, expense saves normally

---

## Features

### 33 IRS Schedule C Categories
AI maps receipts to official tax categories:

**Common Examples:**
- Staples, Office Depot → `office_expense`
- Shell, Chevron → `car_truck_expenses`
- GoDaddy, Hostinger → `online_web_fees`
- FedEx, UPS → `shipping_postage`
- Restaurants → `meals` (50% deductible)

**All Categories:**
advertising, car_truck_expenses, commissions_fees, contract_labor, depreciation, employee_benefit_programs, insurance, interest_mortgage, interest_other, legal_professional, office_expense, pension_profit_sharing, rent_vehicles, rent_property, repairs_maintenance, supplies, taxes_licenses, travel, meals, utilities, wages, depletion, online_web_fees, business_telephone, education_training, membership_dues, books_publications, photography_stock, marketing_materials, shipping_postage, bank_fees, credit_card_fees, other

### Client/Project Auto-Assignment
Add text to receipt images:

- `CLIENT: ABC Corporation` → Auto-assigns to client
- `PROJECT: Website Redesign` → Auto-assigns to project

### Confidence Scoring
- AI provides confidence score (0-100%)
- Default threshold: 70%
- Below threshold → AI analyzes but doesn't auto-fill

---

## What You'll See

### Success
```
✓ Receipt analyzed successfully!
AI auto-populated: Amount ($45.99), Date (2026-02-10), Category (Office Expense)
Confidence: 92%
```

### Warnings
```
⚠ Potential mismatches detected:
• Amount: You entered $50.00, but receipt shows $45.99
• Vendor: You entered "Staples", but receipt shows "Office Depot"
```

### AI Not Configured
```
⚠ AI Analysis Skipped: AI not configured.
Add your API key in TimeGrow Settings → AI Receipt Analysis.
```

---

## Troubleshooting

**Checkbox doesn't appear**
- Check Settings → AI Receipt Analysis is enabled
- Verify API key is entered

**"Invalid API key" error**
- Verify API key is correct (copy/paste carefully)
- Check provider's billing/credits are active
- Try generating a new API key

**Wrong category selected**
- AI uses vendor name + receipt items
- Manually change category after AI analysis
- Some vendors may be ambiguous (e.g., Costco)

**Low confidence scores**
- Use clearer, well-lit receipt photos
- Avoid blurry or cropped images
- Lower threshold in Settings if acceptable

---

## Best Practices

✅ **Upload clear photos** - Better quality = higher confidence
✅ **Review AI suggestions** - Check populated fields before saving
✅ **Pay attention to warnings** - They catch typos and errors
✅ **Choose right provider** - Gemini (fast), GPT-4 (accurate), Claude (smart)

---

**Need technical details?** See `AI-TECHNICAL.md`
