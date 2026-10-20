<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * The editable part of each AI tool's prompt (Settings > AI Prompts).
 *
 * What is sent to the AI:
 * 1. TimeGrowAI::SAFETY_RULES: always first, not editable.
 * 2. The tool's instructions: the active saved version, else the built-in one (this class).
 * 3. The data, added by TimeGrow in <untrusted_data> blocks (projects, clients, what was said,
 *    receipt categories): not editable, so a prompt can't leave it out or change how it is marked.
 *
 * The code reads the AI's JSON by key, so a version must still ask for the keys in required_keys().
 * One that doesn't can't be activated, and is never used (the built-in prompt is used instead).
 */
class TimeGrowAIPrompts {

    private static $active = [];

    /** The AI tools whose prompt can be edited. */
    public static function tools() {
        return [
            'voice' => [
                'name'  => __('Voice AI', 'timegrow'),
                'icon'  => 'microphone',
                'tab'   => 'voice',
                'adds'  => __('the current state of the chat, the conversation so far, the person\'s projects and clients, and the new message', 'timegrow'),
            ],
            'help' => [
                'name'  => __('Voice AI: help answers', 'timegrow'),
                'icon'  => 'editor-help',
                'tab'   => 'voice',
                'adds'  => __('the person\'s question and the help topics found (Settings > Knowledge Base). {agent} is replaced by the assistant\'s name', 'timegrow'),
            ],
            'receipts' => [
                'name'  => __('Receipt Reader', 'timegrow'),
                'icon'  => 'media-document',
                'tab'   => 'receipts',
                'adds'  => __('the active expense categories and the receipt image', 'timegrow'),
            ],
        ];
    }

    public static function is_tool($feature) {
        return array_key_exists($feature, self::tools());
    }

    /** The prompt built into TimeGrow, used when no saved version is active. */
    public static function default_instructions($feature) {
        if ($feature === 'voice') {
            return class_exists('TimeGrowNexusVoicePrompt') ? TimeGrowNexusVoicePrompt::default_conversation_instructions() : '';
        }
        if ($feature === 'help') {
            return class_exists('TimeGrowNexusVoicePrompt') ? TimeGrowNexusVoicePrompt::default_help_instructions() : '';
        }
        if ($feature === 'receipts') {
            return class_exists('TimeGrowAIReceiptAnalyzer') ? TimeGrowAIReceiptAnalyzer::default_instructions() : '';
        }
        return '';
    }

    /** JSON keys the code reads from the AI's answer; a prompt must still ask for them. */
    public static function required_keys($feature) {
        return [
            'voice'    => ['intent', 'time', 'question', 'new_client', 'new_project', 'client_id', 'project_id', 'date', 'hours', 'billable', 'description'],
            'help'     => ['answered', 'answer', 'topic'],
            'receipts' => ['amount', 'date', 'vendor', 'category_id', 'description', 'confidence'],
        ][$feature] ?? [];
    }

    /** Required keys the text never mentions. Pure. */
    public static function missing_keys($feature, $text) {
        return array_values(array_filter(self::required_keys($feature), function ($key) use ($text) {
            return !preg_match('/(?<![a-z_])' . preg_quote($key, '/') . '(?![a-z_])/i', (string) $text);
        }));
    }

    /** The active saved version for a tool, or null. */
    public static function active($feature) {
        if (!array_key_exists($feature, self::$active)) {
            self::$active[$feature] = class_exists('TimeGrowAIPromptModel') ? (new TimeGrowAIPromptModel())->active($feature) : null;
        }
        return self::$active[$feature];
    }

    /**
     * The instructions to send for a tool: the active version when it still asks for every
     * required key, else the built-in prompt.
     */
    public static function instructions($feature) {
        $row = self::active($feature);
        if ($row && trim($row['content']) !== '' && !self::missing_keys($feature, $row['content'])) {
            return $row['content'];
        }
        return self::default_instructions($feature);
    }

    /** Forget the cached active versions (after a change). */
    public static function flush() {
        self::$active = [];
    }
}
