<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Reads a receipt image with the active AI (Gemini, OpenAI or Claude, through TimeGrowAI) and
 * returns the expense fields: amount, date, vendor, category, description, and a client or
 * project when the receipt says "CLIENT: ..." or "PROJECT: ...".
 *
 * One reader for every provider: the same prompt, with the site's real expense category IDs, and
 * the same checks on the answer (the category must be an active one).
 */
class TimeGrowAIReceiptAnalyzer implements TimeGrowReceiptAnalyzerInterface {

    const MAX_IMAGE_SIDE = 1600; // larger images are resized first: fewer tokens, within provider limits

    /**
     * @param string $image_url URL of the uploaded receipt (used to find the file when no path is given).
     * @param array  $options   { path?: string } The uploaded file on disk.
     * @return array|WP_Error
     */
    public function analyze_receipt($image_url, $options = []) {
        if (!TimeGrowAI::rate_limit_ok(get_current_user_id(), 'receipts')) {
            return new WP_Error('rate_limit', __('Too many receipt analysis requests. Please try again later.', 'timegrow'));
        }

        $image = self::image_for_ai($options['path'] ?? self::path_from_url($image_url));
        if (is_wp_error($image)) {
            return $image;
        }

        $categories = self::active_categories();
        $answer     = TimeGrowAI::request([
            'feature'    => 'receipts',
            'prompt'     => self::prompt($categories),
            'image'      => $image,
            'json'       => true,
            'max_tokens' => 1024,
            'timeout'    => 45,
        ]);
        if (is_wp_error($answer)) {
            return $answer;
        }

        $data = TimeGrowAI::json_from_text($answer['text']);
        if (!$data) {
            return new WP_Error('invalid_response', __('The AI did not return receipt details. Try a clearer photo.', 'timegrow'));
        }

        $fields                = self::fields_from_answer($data, $categories);
        $fields['token_usage'] = $answer['usage'];
        $fields['model_used']  = $answer['model'];
        return array_merge($fields, self::assignment($fields));
    }

    public function supports_pdf() {
        return false;
    }

    // ------------------------------------------------------------------
    // The image
    // ------------------------------------------------------------------

    /** The file for an upload URL, when it is in this site's uploads folder. */
    public static function path_from_url($url) {
        $uploads = wp_get_upload_dir();
        $base    = trailingslashit($uploads['baseurl']);
        if (strpos((string) $url, $base) !== 0) {
            return '';
        }
        $path = trailingslashit($uploads['basedir']) . substr($url, strlen($base));
        $real = realpath($path);
        return ($real && strpos($real, realpath($uploads['basedir'])) === 0) ? $real : '';
    }

    /**
     * Read the receipt from disk (no download from the site's own URL), check it is a JPEG or PNG,
     * and resize it when it is large.
     *
     * @return array|WP_Error { mime, data (base64) }
     */
    public static function image_for_ai($path) {
        if (!$path || !is_readable($path)) {
            return new WP_Error('no_file', __('The receipt file could not be read.', 'timegrow'));
        }
        $mime = function_exists('wp_get_image_mime') ? wp_get_image_mime($path) : mime_content_type($path);
        if ($mime === 'application/pdf' || strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf') {
            return new WP_Error('pdf', __('PDF receipts can\'t be read by AI yet. Upload a photo (JPEG or PNG) to fill in the details.', 'timegrow'));
        }
        if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
            return new WP_Error('invalid_mime_type', __('Only JPEG and PNG receipts can be read by AI.', 'timegrow'));
        }

        $file = $path;
        $temp = '';
        $size = @getimagesize($path);
        if ($size && max($size[0], $size[1]) > self::MAX_IMAGE_SIDE && function_exists('wp_get_image_editor')) {
            $editor = wp_get_image_editor($path);
            if (!is_wp_error($editor) && !is_wp_error($editor->resize(self::MAX_IMAGE_SIDE, self::MAX_IMAGE_SIDE, false))) {
                $saved = $editor->save(wp_tempnam('timegrow-receipt') . ($mime === 'image/png' ? '.png' : '.jpg'), $mime);
                if (!is_wp_error($saved) && !empty($saved['path'])) {
                    $file = $temp = $saved['path'];
                }
            }
        }

        $bytes = file_get_contents($file);
        if ($temp) {
            @unlink($temp);
        }
        return $bytes === false
            ? new WP_Error('no_file', __('The receipt file could not be read.', 'timegrow'))
            : ['mime' => $mime, 'data' => base64_encode($bytes)];
    }

    // ------------------------------------------------------------------
    // Prompt and answer (pure)
    // ------------------------------------------------------------------

    /**
     * Active expense categories.
     *
     * @return array[] { id, slug, name, description, section }
     */
    public static function active_categories() {
        if (!class_exists('TimeGrowExpenseCategoryModel')) {
            return [];
        }
        try {
            $rows = (new TimeGrowExpenseCategoryModel())->get_all(['is_active' => 1]);
        } catch (Exception $e) {
            timegrow_log('Expense categories could not be read: ' . $e->getMessage());
            return [];
        }
        return array_map(function ($c) {
            return ['id' => (int) $c->ID, 'slug' => (string) $c->slug, 'name' => (string) $c->name,
                    'description' => (string) ($c->description ?? ''), 'section' => (string) ($c->irs_section ?? 'part_v')];
        }, (array) $rows);
    }

    /** The instructions, with the categories as "ID|slug: Name (description)". Pure. */
    public static function prompt(array $categories) {
        $lines = ['part_ii' => [], 'part_v' => []];
        foreach ($categories as $c) {
            $line = "- {$c['id']}|{$c['slug']}: {$c['name']}" . ($c['description'] !== '' ? " ({$c['description']})" : '');
            $lines[$c['section'] === 'part_ii' ? 'part_ii' : 'part_v'][] = $line;
        }
        $list = '';
        if ($lines['part_ii']) {
            $list .= "Part II categories (main business expenses):\n" . implode("\n", $lines['part_ii']) . "\n\n";
        }
        if ($lines['part_v']) {
            $list .= "Part V categories (other expenses):\n" . implode("\n", $lines['part_v']) . "\n\n";
        }
        if ($list === '') {
            $list = "No categories are set up: use null for category_id.\n\n";
        }

        return "Read this receipt image and return its details.\n\n"
            . "Expense categories, each as \"ID|slug: Name (description)\":\n{$list}"
            . "Examples: Staples, Office Depot = office expense. Shell, Chevron, BP = car and truck. Hotels, airlines = travel. "
            . "Restaurants, cafes = meals. GoDaddy, Hostinger = online and web fees. FedEx, UPS, USPS = shipping and postage. "
            . "Square, Stripe = credit card fees.\n\n"
            . "Return only a JSON object with these keys:\n"
            . "- amount: the total paid, a number without currency symbols\n"
            . "- date: YYYY-MM-DD\n"
            . "- vendor: the business name\n"
            . "- category_id: the number before the | of the most specific matching category above\n"
            . "- description: the items or services bought\n"
            . "- handwritten_notes: any handwritten text on the receipt, word for word, or null\n"
            . "- client_pattern: text like \"CLIENT: name\" if printed or written on the receipt, or null\n"
            . "- project_pattern: text like \"PROJECT: name\" if printed or written on the receipt, or null\n"
            . "- confidence: 0.0 to 1.0, how sure you are (0.9+ clear, 0.7-0.9 readable, below 0.7 poor)\n"
            . "Use null for anything you can't read.";
    }

    /**
     * Expense fields from the AI's answer. The category must be one of $categories: a number from
     * the list, or a slug the AI returned instead; anything else becomes 0. Pure.
     */
    public static function fields_from_answer(array $data, array $categories) {
        $by_id   = [];
        $by_slug = [];
        foreach ($categories as $c) {
            $by_id[$c['id']]     = $c;
            $by_slug[$c['slug']] = $c;
        }
        $category_id = 0;
        $given       = $data['category_id'] ?? ($data['category'] ?? null);
        if (is_numeric($given) && isset($by_id[(int) $given])) {
            $category_id = (int) $given;
        } elseif (is_string($given) && $given !== '') {
            $slug        = strtolower(str_replace([' ', '-'], '_', trim(preg_replace('/^\d+\|/', '', $given))));
            $category_id = isset($by_slug[$slug]) ? $by_slug[$slug]['id'] : 0;
        }

        $description = sanitize_text_field((string) ($data['description'] ?? ''));
        $notes       = sanitize_text_field((string) ($data['handwritten_notes'] ?? ''));
        if ($notes !== '') {
            $description = trim($description . "\n\nHANDWRITTEN: " . $notes);
        }

        $date = (string) ($data['date'] ?? '');
        $d    = DateTime::createFromFormat('!Y-m-d', $date);

        return [
            'amount'              => is_numeric($data['amount'] ?? null) ? round((float) $data['amount'], 2) : 0.0,
            'expense_date'        => ($d && $d->format('Y-m-d') === $date) ? $date : '',
            'expense_name'        => sanitize_text_field((string) ($data['vendor'] ?? '')),
            'category_id'         => $category_id,
            'expense_description' => $description,
            'confidence'          => max(0.0, min(1.0, (float) ($data['confidence'] ?? 0))),
            'client_pattern'      => is_string($data['client_pattern'] ?? null) ? $data['client_pattern'] : null,
            'project_pattern'     => is_string($data['project_pattern'] ?? null) ? $data['project_pattern'] : null,
        ];
    }

    // ------------------------------------------------------------------
    // CLIENT: / PROJECT: on the receipt
    // ------------------------------------------------------------------

    /** Assign the expense to the project, else the client, named on the receipt; else general. */
    public static function assignment(array $fields) {
        if (!empty($fields['project_pattern']) && preg_match('/PROJECT:\s*(.+?)(?:\n|$)/i', $fields['project_pattern'], $m)) {
            $found = (new TimeGrowProjectModel())->search_by_name(trim($m[1]));
            if ($found) {
                return ['assigned_to' => 'project', 'assigned_to_id' => (int) $found[0]->ID];
            }
        }
        if (!empty($fields['client_pattern']) && preg_match('/CLIENT:\s*(.+?)(?:\n|$)/i', $fields['client_pattern'], $m)) {
            $found = (new TimeGrowClientModel())->search_by_name(trim($m[1]));
            if ($found) {
                return ['assigned_to' => 'client', 'assigned_to_id' => (int) $found[0]->ID];
            }
        }
        return ['assigned_to' => 'general', 'assigned_to_id' => 0];
    }
}
