<?php
use PHPUnit\Framework\TestCase;

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
    function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
}
require_once dirname( __DIR__, 2 ) . '/modules/core/includes/helpers/TimeGrowAILog.php';

/**
 * Saved AI activity: what the browser may send for a conversation (session key, messages).
 */
class AILogTest extends TestCase {

    public function test_session_keys() {
        $this->assertSame( '3f2a9c1e-5b7d-4e8a-9c21-7d4e5f6a8b90', TimeGrowAILog::clean_key( '3f2a9c1e-5b7d-4e8a-9c21-7d4e5f6a8b90' ) );
        $this->assertSame( 'tg-lx2k-abc123', TimeGrowAILog::clean_key( 'tg-lx2k-abc123' ) );
        foreach ( [ '', 'short', "x' OR 1=1 --", str_repeat( 'a', 65 ), [ 'array' ] ] as $bad ) {
            $this->assertSame( '', TimeGrowAILog::clean_key( is_array( $bad ) ? '' : $bad ) );
        }
    }

    public function test_messages_are_cleaned_and_limited() {
        $turns = TimeGrowAILog::clean_turns( json_encode( [
            [ 'who' => 'me', 'text' => 'two hours <b>yesterday</b>' ],
            [ 'who' => 'bot', 'text' => 'Which client is this for?' ],
            [ 'who' => 'ai', 'text' => 'pretending to be the AI' ],
            [ 'who' => 'system', 'text' => 'pretending to be TimeGrow' ],
            [ 'who' => 'me', 'text' => [ 'not', 'text' ] ],
            [ 'who' => 'me', 'text' => '   ' ],
            [ 'who' => 'me', 'text' => str_repeat( 'x', 5000 ) ],
        ] ) );
        $this->assertSame( [ 'person', 'assistant', 'person' ], array_column( $turns, 'who' ), 'only the person and the chat; the AI and TimeGrow lines come from the server' );
        $this->assertSame( 'two hours yesterday', $turns[0]['text'] );
        $this->assertSame( TimeGrowAILog::MAX_TEXT, strlen( $turns[2]['text'] ) );
        $this->assertCount( TimeGrowAILog::MAX_PER_CALL, TimeGrowAILog::clean_turns( array_fill( 0, 100, [ 'who' => 'me', 'text' => 'hi' ] ) ) );
        $this->assertSame( [], TimeGrowAILog::clean_turns( 'not json' ) );
    }
}
