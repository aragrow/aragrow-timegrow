<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Receipt reader for the active AI configuration. One reader (TimeGrowAIReceiptAnalyzer) works
 * with every provider through TimeGrowAI.
 */
class TimeGrowReceiptAnalyzerFactory {

    /**
     * Per-user cap on AI receipt reading (kept for older callers; see TimeGrowAI::rate_limit_ok()).
     */
    public static function rate_limit_ok($user_id) {
        return TimeGrowAI::rate_limit_ok($user_id, 'receipts');
    }

    /**
     * @return TimeGrowReceiptAnalyzerInterface
     */
    public static function create() {
        return new TimeGrowAIReceiptAnalyzer();
    }

    /**
     * Providers receipts can be read with.
     *
     * @return string[]
     */
    public static function get_available_analyzers() {
        return array_keys(TimeGrowAI::providers());
    }

    public static function is_provider_available($provider) {
        return in_array($provider, self::get_available_analyzers(), true);
    }
}
