<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Factory for creating receipt analyzer instances
 *
 * Instantiates the correct analyzer based on plugin settings
 * Supports Google Gemini, OpenAI, and Anthropic Claude
 */
class TimeGrowReceiptAnalyzerFactory {

    /**
     * Create analyzer instance based on current settings
     *
     * @return TimeGrowReceiptAnalyzerInterface Analyzer instance
     * @throws Exception If invalid provider is configured
     */
    public static function create() {
        if(WP_DEBUG) error_log(__CLASS__.'::'.__FUNCTION__);

        // Get active configuration from the new database table
        if (class_exists('TimeGrowSettings')) {
            $settings = TimeGrowSettings::get_active_ai_config();
            if(WP_DEBUG) {
                error_log('=== FACTORY CONFIG DEBUG ===');
                error_log('Factory using active config from database: ' . ($settings['config_name'] ?? 'Unknown'));
                error_log('Provider: ' . ($settings['ai_provider'] ?? 'Not set'));
                error_log('Model: ' . ($settings['ai_model'] ?? 'Not set'));
                error_log('Has API key: ' . (!empty($settings['ai_api_key']) ? 'YES' : 'NO'));
                error_log('API key length: ' . strlen($settings['ai_api_key'] ?? ''));
            }
        } else {
            // Fallback to legacy settings if TimeGrowSettings not loaded
            $settings = get_option('aragrow_timegrow_ai_settings', [
                'ai_provider' => 'google_gemini',
                'ai_model' => 'gemini-1.5-flash',
            ]);
            if(WP_DEBUG) error_log('Factory using fallback settings (TimeGrowSettings class not found)');
        }

        $provider = $settings['ai_provider'] ?? 'google_gemini';

        if(WP_DEBUG) error_log('Creating analyzer for provider: ' . $provider);

        switch ($provider) {
            case 'openai':
                if (!class_exists('TimeGrowOpenAIReceiptAnalyzer')) {
                    throw new Exception('OpenAI analyzer class not found');
                }
                return new TimeGrowOpenAIReceiptAnalyzer();

            case 'anthropic':
                if (!class_exists('TimeGrowClaudeReceiptAnalyzer')) {
                    throw new Exception('Claude analyzer class not found');
                }
                return new TimeGrowClaudeReceiptAnalyzer();

            case 'google_gemini':
            default:
                if (!class_exists('TimeGrowGeminiReceiptAnalyzer')) {
                    throw new Exception('Gemini analyzer class not found');
                }
                return new TimeGrowGeminiReceiptAnalyzer();
        }
    }

    /**
     * Get list of available analyzers
     *
     * @return array Array of provider keys that have analyzer classes
     */
    public static function get_available_analyzers() {
        $available = [];

        if (class_exists('TimeGrowGeminiReceiptAnalyzer')) {
            $available[] = 'google_gemini';
        }

        if (class_exists('TimeGrowOpenAIReceiptAnalyzer')) {
            $available[] = 'openai';
        }

        if (class_exists('TimeGrowClaudeReceiptAnalyzer')) {
            $available[] = 'anthropic';
        }

        return $available;
    }

    /**
     * Check if a specific provider is available
     *
     * @param string $provider Provider key (google_gemini, openai, anthropic)
     * @return bool True if provider analyzer exists
     */
    public static function is_provider_available($provider) {
        return in_array($provider, self::get_available_analyzers());
    }
}
