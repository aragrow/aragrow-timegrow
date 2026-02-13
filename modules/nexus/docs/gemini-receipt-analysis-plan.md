# Implementation Plan: Google Gemini Receipt Analysis for TimeGrow Expenses

**Status:** Not Started
**Priority:** Future Enhancement
**Last Updated:** 2025-02-11

---

## Overview

Add AI-powered receipt analysis to the TimeGrow expense plugin. When a receipt image is uploaded, Google Gemini Vision API will automatically analyze it and extract expense data (amount, date, vendor, category, etc.). The system will also parse text patterns in receipts to automatically assign expenses to clients or projects.

### Pattern Recognition

- **"CLIENT: Company Name"** → assigns to that client (case insensitive)
- **"PROJECT: Project Name"** → assigns to that project (case insensitive)
- **No pattern found** → assigns to "general"

---

## Architecture Decision

Create a new helper class `TimeGrowGeminiReceiptAnalyzer` within the existing TimeGrow plugin structure, following the patterns established by the `aragrow-voice-ai` plugin. This approach:

1. **Reuses existing infrastructure**: Leverages the Voice AI plugin's security, encryption, and API patterns
2. **Keeps code organized**: Separates Gemini logic from core expense handling
3. **Maintains plugin architecture**: Follows TimeGrow's MVC-like pattern (Model, View, Controller)
4. **Allows for future expansion**: Easy to add more AI features later

---

## Implementation Steps

### Phase 1: Create Gemini Analyzer Helper Class

**File to create:** `/includes/helpers/TimeGrowGeminiReceiptAnalyzer.php`

**Responsibilities:**
- Communicate with Google Gemini Vision API
- Extract expense data from receipt images (amount, date, vendor, category, description)
- Parse CLIENT: and PROJECT: patterns from receipt text
- Match client/project names to database records using fuzzy search
- Return structured data compatible with TimeGrowExpenseModel

**Key Methods:**
```php
class TimeGrowGeminiReceiptAnalyzer {
    public function __construct()
    public function analyze_receipt($image_url, $options = [])
    private function call_gemini_vision_api($image_data, $prompt)
    private function prepare_image_for_api($image_url)
    private function get_extraction_prompt()
    private function parse_gemini_response($response)
    private function match_client_from_text($text)
    private function match_project_from_text($text)
    private function map_category_to_expense_type($category_text)
}
```

**Pattern to follow:** Voice AI's `/includes/class-aragrow-mcp-client.php` for API communication structure

---

### Phase 2: Add Gemini API Settings to TimeGrow

**Files to modify:**
1. `/includes/TimeGrowSettings.php` (or create if doesn't exist)
2. `/aragrow-timegrow.php`

**Add settings for:**
- Google Gemini API Key (encrypted storage)
- Enable/disable auto-analysis toggle
- Confidence threshold (0.0-1.0) - only auto-populate if Gemini is confident
- Optional: Model selection (gemini-1.5-pro vs gemini-1.5-flash)

**Storage:**
- Option name: `aragrow_timegrow_gemini_settings`
- Encrypt API key using Voice AI's `Security::encrypt()` method
- Settings fields:
  ```php
  [
      'gemini_api_key' => '',  // encrypted
      'enable_auto_analysis' => true,
      'confidence_threshold' => 0.7,
      'model' => 'gemini-1.5-flash'
  ]
  ```

---

### Phase 3: Integrate Analysis into Expense Upload Flow

**File to modify:** `/includes/controllers/TimeGrowExpenseController.php`

**Changes in `handle_form_submission()` method:**

```php
if (!empty($_FILES['file_upload']['name'])) {
    $file = $_FILES['file_upload'];

    // 1. Upload file first (get URL)
    $upload_result = $this->receipt_model->upload_file($file);

    if (!is_wp_error($upload_result)) {
        // 2. Analyze with Gemini (if enabled and this is a new expense)
        $settings = get_option('aragrow_timegrow_gemini_settings', []);
        if ($settings['enable_auto_analysis'] && $id == 0) {
            $analyzer = new TimeGrowGeminiReceiptAnalyzer();
            $analysis = $analyzer->analyze_receipt($upload_result['url']);

            // 3. If analysis successful, update expense data
            if (!is_wp_error($analysis) && $analysis['confidence'] >= $settings['confidence_threshold']) {
                $data = array_merge($data, $analysis['expense_data']);

                // Re-save expense with Gemini-extracted data
                $this->expense_model->update($id, $data, $format);
            }
        }

        // 4. Save receipt record to database
        $this->receipt_model->save_receipt_record($id, $upload_result['url']);
    }
}
```

**Note:** This requires splitting `TimeGrowExpenseReceiptModel::update()` into two methods:
- `upload_file($file)` - handles file upload only, returns URL
- `save_receipt_record($expense_id, $file_url)` - saves to database

---

### Phase 4: Update Receipt Model to Support Analysis

**File to modify:** `/includes/models/TimeGrowExpenseReceiptModel.php`

**Add database columns** (optional but recommended for audit trail):
```sql
ALTER TABLE {$table_name} ADD COLUMN extracted_data LONGTEXT;
ALTER TABLE {$table_name} ADD COLUMN gemini_confidence DECIMAL(3,2);
ALTER TABLE {$table_name} ADD COLUMN analyzed_at DATETIME;
```

**Refactor methods:**
1. `upload_file($file)` - Upload file, return result array
2. `save_receipt_record($expense_id, $file_url, $gemini_data = null)` - Insert DB record
3. `save_analysis_data($receipt_id, $analysis_data)` - Update with Gemini results

---

### Phase 5: Add Client and Project Name Matching

**Files to modify:**
1. `/includes/models/TimeGrowClientModel.php`
2. `/includes/models/TimeGrowProjectModel.php`

**TimeGrowClientModel.php:**
```php
public function search_by_name($company_name) {
    // Fuzzy search for client by display_name
    // Return array of matches with ID and name
    // Used by Gemini analyzer to match "CLIENT: XYZ Corp"
}
```

**TimeGrowProjectModel.php:**
```php
public function search_by_name($project_name) {
    // Fuzzy search for project by name
    // Return array of matches with ID and name
    // Used by Gemini analyzer to match "PROJECT: Website Redesign"
}
```

---

### Phase 6: Update Expense Form View (Optional Enhancement)

**File to modify:** `/includes/views/TimeGrowExpenseView.php`

**Add visual indicator:**
```html
<div id="gemini-analysis-status" style="display:none;">
    <div class="notice notice-info">
        <p>🤖 Analyzing receipt with AI...</p>
    </div>
</div>

<div id="gemini-analysis-results" style="display:none;">
    <div class="notice notice-success">
        <p>✅ Receipt analyzed! Fields auto-populated. Please review before saving.</p>
    </div>
</div>
```

**JavaScript enhancement** in `/assets/js/expense.js`:
- Show loading indicator when file uploaded
- Optionally submit form via AJAX to get Gemini analysis
- Auto-populate form fields with extracted data
- Highlight auto-filled fields for user review

---

### Phase 7: Error Handling and Logging

**Use existing patterns from Voice AI:**

1. **Security logging:**
```php
\AraGrow\VoiceAI\Security::log_security_event('gemini_analysis_failed', [
    'expense_id' => $id,
    'error' => $error_message,
]);
```

2. **Rate limiting:**
```php
if (!\AraGrow\VoiceAI\Security::check_rate_limit('gemini_analysis_' . $user_id)) {
    return new WP_Error('rate_limit', 'Too many analysis requests');
}
```

3. **Graceful fallback:**
   - If Gemini API fails, continue with normal upload (don't block user)
   - Display notice: "AI analysis unavailable, please enter details manually"
   - Log error for admin review

---

## Gemini API Integration Details

### API Endpoint
```
POST https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=API_KEY
```

### Request Body Structure
```json
{
  "contents": [{
    "parts": [
      {"text": "Extract expense data from this receipt..."},
      {
        "inline_data": {
          "mime_type": "image/jpeg",
          "data": "base64_encoded_image_data"
        }
      }
    ]
  }]
}
```

### Extraction Prompt
```
Analyze this receipt image and extract the following information:

1. Total amount (number only, no currency symbols)
2. Date (in YYYY-MM-DD format)
3. Vendor/Business name
4. Category (one of: office_supplies, travel, meals, utilities, rent, transportation, marketing, other)
5. Description/Items purchased
6. Look for text patterns "CLIENT: [name]" or "PROJECT: [name]" (case insensitive)

Return the data as JSON in this exact format:
{
  "amount": 125.50,
  "date": "2024-01-15",
  "vendor": "Office Depot",
  "category": "office_supplies",
  "description": "Printer paper and ink cartridges",
  "client_pattern": "CLIENT: ABC Corp",
  "project_pattern": "PROJECT: Website Redesign",
  "confidence": 0.95
}

If any field cannot be determined, use null. If no CLIENT or PROJECT pattern is found, omit those fields.
```

---

## Assignment Logic Implementation

### CLIENT Pattern
```php
private function match_client_from_text($text) {
    // 1. Extract pattern: "CLIENT: Company Name" (case insensitive)
    if (preg_match('/CLIENT:\s*(.+?)(?:\n|$)/i', $text, $matches)) {
        $client_name = trim($matches[1]);

        // 2. Search in TimeGrowClientModel
        $client_model = new TimeGrowClientModel();
        $results = $client_model->search_by_name($client_name);

        // 3. Return best match or null
        if (!empty($results)) {
            return [
                'assigned_to' => 'client',
                'assigned_to_id' => $results[0]->ID
            ];
        }
    }
    return null;
}
```

### PROJECT Pattern
```php
private function match_project_from_text($text) {
    // 1. Extract pattern: "PROJECT: Project Name" (case insensitive)
    if (preg_match('/PROJECT:\s*(.+?)(?:\n|$)/i', $text, $matches)) {
        $project_name = trim($matches[1]);

        // 2. Search in TimeGrowProjectModel
        $project_model = new TimeGrowProjectModel();
        $results = $project_model->search_by_name($project_name);

        // 3. Return best match or null
        if (!empty($results)) {
            return [
                'assigned_to' => 'project',
                'assigned_to_id' => $results[0]->ID
            ];
        }
    }
    return null;
}
```

### Assignment Priority
1. If PROJECT pattern found → assign to project
2. Else if CLIENT pattern found → assign to client
3. Else → assign to "general" (assigned_to_id = 0)

---

## Testing & Verification Plan

### Manual Testing
1. **Upload receipt with clear data**
   - Verify amount extracted correctly
   - Verify date extracted correctly
   - Verify vendor name extracted

2. **Upload receipt with CLIENT pattern**
   - Add text "CLIENT: ABC Company" to receipt
   - Verify assigned_to = 'client'
   - Verify assigned_to_id matches client ID

3. **Upload receipt with PROJECT pattern**
   - Add text "PROJECT: Website Build" to receipt
   - Verify assigned_to = 'project'
   - Verify assigned_to_id matches project ID

4. **Upload receipt with no pattern**
   - Verify assigned_to = 'general'
   - Verify assigned_to_id = 0

5. **Test error cases**
   - Invalid API key → graceful fallback
   - Network timeout → graceful fallback
   - Malformed image → graceful fallback

---

## Security Considerations

1. **API Key Storage**: Encrypt using `Security::encrypt()` from Voice AI plugin
2. **Rate Limiting**: 10 requests per minute per user (prevent abuse)
3. **File Validation**: Already handled by existing receipt upload (512KB max, image/pdf only)
4. **Nonce Verification**: Already in place in expense form
5. **Error Logging**: Use Voice AI's security logging infrastructure
6. **No direct user input to Gemini**: All prompts hardcoded, no injection risk

---

## Dependencies & Requirements

### Required
- Google Gemini API key (user must provide)
- Voice AI plugin's Security class (for encryption/logging)
- WordPress 5.0+ (for wp_remote_post)
- PHP 7.4+ (for arrow functions, null coalescing)

### Optional
- wp-config.php constant for API key (alternative to database storage):
  ```php
  define('ARAGROW_GEMINI_API_KEY', 'your-key-here');
  ```

---

## Future Enhancements (Out of Scope)

1. **Multi-language support** for receipt text
2. **Batch processing** of multiple receipts
3. **Receipt categorization training** (learn from user corrections)
4. **Export training data** for custom model fine-tuning
5. **Mobile app integration** (scan receipts on phone)
6. **OCR fallback** if Gemini unavailable (Tesseract, AWS Textract)

---

## Implementation Notes

**Complexity:** Medium

**Key Risks:**
- Gemini API changes or rate limits
- Fuzzy matching may not find correct client/project (user review required)
- Image quality may affect extraction accuracy

**Mitigation:**
- Graceful fallback if API fails
- Allow user to override AI suggestions
- Show confidence score to user
- Log all analyses for quality review

---

## Related Documentation

- [Voice AI Plugin Security Patterns](./voice-ai-security.md)
- [TimeGrow Expense System](./expense-system.md)
- [Google Gemini API Documentation](https://ai.google.dev/docs)
