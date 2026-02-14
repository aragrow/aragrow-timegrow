<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface for receipt analyzers
 *
 * Defines the contract for AI-powered receipt analysis implementations
 * Supports Google Gemini, OpenAI, and Anthropic Claude
 */
interface TimeGrowReceiptAnalyzerInterface {

    /**
     * Analyze receipt image and extract expense data
     *
     * @param string $image_url URL of the uploaded receipt image
     * @param array $options Additional options for analysis
     * @return array|WP_Error Extracted data array with keys:
     *                        - amount (float)
     *                        - expense_date (string YYYY-MM-DD)
     *                        - expense_name (string vendor name)
     *                        - category (string category slug)
     *                        - expense_description (string)
     *                        - confidence (float 0.0-1.0)
     *                        - assigned_to (string: 'client', 'project', or 'general')
     *                        - assigned_to_id (int or 0)
     *                        - raw_response (string original AI response)
     *                        Or WP_Error on failure
     */
    public function analyze_receipt($image_url, $options = []);

    /**
     * Check if this analyzer supports PDF files
     *
     * @return bool True if PDF analysis is supported
     */
    public function supports_pdf();
}
