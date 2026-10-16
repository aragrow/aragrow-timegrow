<?php
use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
    function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
}
require_once dirname( __DIR__, 2 ) . '/modules/core/includes/helpers/TimeGrowProjectPicker.php';
require_once dirname( __DIR__, 2 ) . '/modules/core/includes/helpers/TimeGrowAI.php';
require_once dirname( __DIR__, 2 ) . '/modules/nexus/includes/TimeGrowNexusVoice.php';

/**
 * Voice entry: checking what the AI heard before it fills the Manual form.
 */
class VoiceEntryTest extends TestCase {

    const TODAY = '2026-10-16';

    private $candidates = [
        [ 'id' => 13, 'name' => 'Website Redesign', 'client_id' => 27, 'client_name' => 'Frank Ruiz', 'billable' => 1 ],
        [ 'id' => 14, 'name' => 'SEO Audit', 'client_id' => 27, 'client_name' => 'Frank Ruiz', 'billable' => 1 ],
        [ 'id' => 20, 'name' => 'Website Redesign', 'client_id' => 31, 'client_name' => 'Copperleaf', 'billable' => 0 ],
    ];

    private function merge( array $heard, array $known = [] ) {
        return TimeGrowNexusVoice::merge( $known, $heard, $this->candidates, self::TODAY );
    }

    public function test_a_full_sentence_fills_every_field() {
        $f = $this->merge( [ 'project_id' => 14, 'date' => '2026-10-15', 'start_time' => '9:00', 'hours' => 2.5, 'billable' => true, 'description' => 'Keyword research' ] );

        $this->assertSame( 27, $f['client_id'] ); // from the project
        $this->assertSame( 14, $f['project_id'] );
        $this->assertSame( '2026-10-15', $f['date'] );
        $this->assertSame( '09:00', $f['start_time'] );
        $this->assertSame( 2.5, $f['hours'] );
        $this->assertSame( 1, $f['billable'] );
        $this->assertSame( 'Keyword research', $f['description'] );
    }

    public function test_project_or_client_not_in_the_persons_list_is_ignored() {
        $f = $this->merge( [ 'project_id' => 999, 'client_id' => 555 ] );

        $this->assertNull( $f['project_id'] );
        $this->assertNull( $f['client_id'] );
    }

    public function test_a_new_client_clears_a_project_of_another_client() {
        $f = $this->merge( [ 'client_id' => 31 ], [ 'client_id' => 27, 'project_id' => 14 ] );

        $this->assertSame( 31, $f['client_id'] );
        $this->assertNull( $f['project_id'] );
    }

    public function test_future_and_invalid_dates_are_ignored() {
        $this->assertNull( $this->merge( [ 'date' => '2026-10-17' ] )['date'] );
        $this->assertNull( $this->merge( [ 'date' => '2026-02-30' ] )['date'] );
        $this->assertNull( $this->merge( [ 'date' => 'yesterday' ] )['date'] );
    }

    public function test_hours_round_to_quarters_and_stay_in_range() {
        $this->assertSame( 0.75, $this->merge( [ 'hours' => 0.7 ] )['hours'] );
        $this->assertNull( $this->merge( [ 'hours' => 0 ] )['hours'] );
        $this->assertNull( $this->merge( [ 'hours' => 30 ] )['hours'] );
    }

    public function test_start_time_can_be_skipped_or_must_be_valid() {
        $this->assertSame( 'skip', $this->merge( [ 'start_time' => 'Skip' ] )['start_time'] );
        $this->assertSame( '14:30', $this->merge( [ 'start_time' => '14:30' ] )['start_time'] );
        $this->assertNull( $this->merge( [ 'start_time' => '25:00' ] )['start_time'] );
    }

    public function test_billable_no_and_known_fields_kept() {
        $f = $this->merge( [ 'billable' => false ], [ 'hours' => 2, 'description' => 'Call' ] );

        $this->assertSame( 0, $f['billable'] );
        $this->assertSame( 2.0, $f['hours'] ); // known fields are checked too
        $this->assertSame( 'Call', $f['description'] );
    }

    public function test_json_is_found_in_a_wrapped_ai_answer() {
        $this->assertSame( [ 'hours' => 2 ], TimeGrowNexusVoice::json_from_text( "```json\n{\"hours\": 2}\n```" ) );
        $this->assertSame( [], TimeGrowNexusVoice::json_from_text( 'Sorry, I could not tell.' ) );
    }

    public function test_prompt_lists_only_the_persons_projects_and_today() {
        $prompt = TimeGrowNexusVoice::build_prompt( 'two hours yesterday', [], '', $this->candidates, self::TODAY );

        $this->assertStringContainsString( 'Today is 2026-10-16 (Friday)', $prompt );
        $this->assertStringContainsString( '"project_id":20', $prompt );
        $this->assertStringContainsString( '"client":"Copperleaf"', $prompt );
        $this->assertStringContainsString( "<untrusted_data name=\"what_the_person_said\">\ntwo hours yesterday\n</untrusted_data>", $prompt );
    }
}
