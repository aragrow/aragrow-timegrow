<?php
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/**
 * Each AI feature (AI Receipt Analysis, AI Voice Entry) picks its own AI configuration under
 * "AI to use", or uses the default. Runs in separate processes because it fakes the
 * configuration table and the settings classes.
 */
#[RunTestsInSeparateProcesses]
class AIFeatureChoiceTest extends TestCase {

    protected function setUp(): void {
        if ( ! class_exists( 'TimeGrowAIConfigModel', false ) ) {
            eval( '
                class TimeGrowAIConfigModel {
                    public static $rows = [];
                    public function get_all() { return array_values( self::$rows ); }
                    public function get_by_id( $id ) { return self::$rows[ $id ] ?? null; }
                    public function get_active() {
                        foreach ( self::$rows as $r ) { if ( ! empty( $r["is_active"] ) ) { return $r; } }
                        return null;
                    }
                }
                class TimeGrowSettings {
                    public static $choice = [];
                    public static function feature_ai_config_id( $feature ) { return (int) ( self::$choice[ $feature ] ?? 0 ); }
                }
            ' );
        }
        if ( ! function_exists( '__' ) ) {
            function __( $text, $domain = 'default' ) { return $text; }
        }
        require_once dirname( __DIR__, 2 ) . '/modules/core/includes/helpers/TimeGrowAI.php';

        TimeGrowAIConfigModel::$rows = [
            1 => [ 'id' => 1, 'config_name' => 'Main', 'ai_provider' => 'google_gemini', 'ai_model' => 'gemini-3.8-flash', 'ai_api_key' => 'k1', 'is_active' => 1 ],
            2 => [ 'id' => 2, 'config_name' => 'Cheap', 'ai_provider' => 'openai', 'ai_model' => 'gpt-6-luna', 'ai_api_key' => 'k2', 'is_active' => 0 ],
        ];
        TimeGrowSettings::$choice = [];
    }

    public function test_features_use_the_default_until_they_pick_one() {
        $this->assertSame( 1, TimeGrowAI::config( 'receipts' )['id'] );
        $this->assertSame( 1, TimeGrowAI::config( 'voice' )['id'] );
        $this->assertSame( 1, TimeGrowAI::config()['id'] );
    }

    public function test_each_feature_uses_its_own_choice() {
        TimeGrowSettings::$choice = [ 'voice' => 2 ];

        $this->assertSame( 'openai', TimeGrowAI::config( 'voice' )['ai_provider'] );
        $this->assertSame( 'k2', TimeGrowAI::api_key( TimeGrowAI::config( 'voice' ) ) );
        $this->assertSame( 'google_gemini', TimeGrowAI::config( 'receipts' )['ai_provider'], 'receipts keep the default' );
        $this->assertFalse( TimeGrowAI::choice( 'voice' )['fell_back'] );
    }

    public function test_a_deleted_choice_falls_back_to_the_default_and_says_so() {
        TimeGrowSettings::$choice = [ 'receipts' => 9 ];

        $choice = TimeGrowAI::choice( 'receipts' );
        $this->assertSame( 1, (int) $choice['row']['id'] );
        $this->assertTrue( $choice['fell_back'] );
    }

    public function test_no_configurations_means_not_configured() {
        TimeGrowAIConfigModel::$rows = [];

        $this->assertFalse( TimeGrowAI::is_configured( 'voice' ) );
        $this->assertSame( [], TimeGrowAI::configurations() );
    }

    public function test_the_list_puts_the_default_first_with_readable_labels() {
        TimeGrowAIConfigModel::$rows = [ 2 => TimeGrowAIConfigModel::$rows[2], 1 => TimeGrowAIConfigModel::$rows[1] ];

        $list = TimeGrowAI::configurations();
        $this->assertSame( [ 1, 2 ], array_column( $list, 'id' ) );
        $this->assertTrue( $list[0]['is_default'] );
        $this->assertSame( 'Cheap: OpenAI · GPT-6 Luna', $list[1]['label'] );
    }
}
