<?php
use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }
}
if ( ! interface_exists( 'TimeGrowReceiptAnalyzerInterface' ) ) {
    require_once dirname( __DIR__, 2 ) . '/modules/core/includes/helpers/TimeGrowReceiptAnalyzerInterface.php';
}
require_once dirname( __DIR__, 2 ) . '/modules/core/includes/helpers/TimeGrowProjectPicker.php';
require_once dirname( __DIR__, 2 ) . '/modules/core/includes/helpers/TimeGrowAI.php';
require_once dirname( __DIR__, 2 ) . '/modules/core/includes/helpers/TimeGrowAIPrompts.php';
require_once dirname( __DIR__, 2 ) . '/modules/core/includes/helpers/TimeGrowAIReceiptAnalyzer.php';
require_once dirname( __DIR__, 2 ) . '/modules/nexus/includes/TimeGrowNexusVoice.php';
require_once dirname( __DIR__, 2 ) . '/modules/nexus/includes/TimeGrowNexusVoiceFormat.php';
require_once dirname( __DIR__, 2 ) . '/modules/nexus/includes/TimeGrowNexusVoiceNames.php';
require_once dirname( __DIR__, 2 ) . '/modules/nexus/includes/TimeGrowNexusVoicePrompt.php';
require_once dirname( __DIR__, 2 ) . '/modules/nexus/includes/TimeGrowNexusVoiceAnswers.php';
require_once dirname( __DIR__, 2 ) . '/modules/nexus/includes/TimeGrowNexusAssistant.php';

/**
 * AI prompt versions: the built-in prompts, and the check that keeps a saved version from
 * dropping the answers TimeGrow reads.
 */
class AIPromptsTest extends TestCase {

    public function test_built_in_prompts_ask_for_every_key_the_code_reads() {
        foreach ( [ 'voice', 'receipts' ] as $feature ) {
            $this->assertNotSame( '', TimeGrowAIPrompts::default_instructions( $feature ) );
            $this->assertSame( [], TimeGrowAIPrompts::missing_keys( $feature, TimeGrowAIPrompts::default_instructions( $feature ) ), $feature );
        }
    }

    public function test_missing_keys_are_found() {
        $this->assertSame( [ 'vendor', 'category_id', 'description', 'confidence' ], TimeGrowAIPrompts::missing_keys( 'receipts', 'Return amount and date.' ) );
        $this->assertContains( 'client_id', TimeGrowAIPrompts::missing_keys( 'voice', 'intent time question new_client new_project project_id date hours billable description' ), 'project_id does not count as client_id' );
    }

    public function test_without_a_saved_version_the_built_in_prompt_is_used() {
        $this->assertSame( TimeGrowAIReceiptAnalyzer::default_instructions(), TimeGrowAIReceiptAnalyzer::instructions() );
        $this->assertSame( TimeGrowNexusVoicePrompt::default_conversation_instructions(), TimeGrowNexusVoicePrompt::conversation_instructions() );
    }

    public function test_only_known_tools() {
        $this->assertTrue( TimeGrowAIPrompts::is_tool( 'voice' ) );
        $this->assertFalse( TimeGrowAIPrompts::is_tool( 'payroll' ) );
        $this->assertSame( '', TimeGrowAIPrompts::default_instructions( 'payroll' ) );
    }
}
