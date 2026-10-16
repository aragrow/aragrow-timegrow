<?php
use PHPUnit\Framework\TestCase;

defined( 'AUTH_KEY' ) || define( 'AUTH_KEY', 'test-auth-key' );
defined( 'SECURE_AUTH_KEY' ) || define( 'SECURE_AUTH_KEY', 'test-secure-auth-key' );
if ( ! function_exists( 'wp_json_encode' ) ) {
    function wp_json_encode( $data ) { return json_encode( $data ); }
}
if ( ! function_exists( 'is_wp_error' ) ) {
    function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
}
if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error { private $m; public function __construct( $c = '', $m = '' ) { $this->m = $m; } public function get_error_message() { return $this->m; } }
}
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
    function wp_remote_retrieve_response_code( $r ) { return $r['response']['code'] ?? 0; }
    function wp_remote_retrieve_body( $r ) { return $r['body'] ?? ''; }
}
require_once dirname( __DIR__, 2 ) . '/modules/core/includes/helpers/TimeGrowAI.php';

/**
 * TimeGrow's AI key handling: encryption (own and older Voice AI format) and checking a key.
 */
class TimeGrowAITest extends TestCase {

    /** How the Aragrow Voice AI plugin encrypted keys (no prefix). */
    private function voice_ai_encrypt( $plain ) {
        $key = hash( 'sha256', AUTH_KEY . SECURE_AUTH_KEY, true );
        $iv  = random_bytes( openssl_cipher_iv_length( 'aes-256-cbc' ) );
        return base64_encode( $iv . openssl_encrypt( $plain, 'aes-256-cbc', $key, 0, $iv ) );
    }

    public function test_encrypt_and_decrypt_round_trip() {
        $stored = TimeGrowAI::encrypt( 'AIzaTestKey123' );

        $this->assertStringStartsWith( 'tg1:', $stored );
        $this->assertStringNotContainsString( 'AIzaTestKey123', $stored );
        $this->assertSame( 'AIzaTestKey123', TimeGrowAI::decrypt( $stored ) );
    }

    public function test_each_encryption_is_different() {
        $this->assertNotSame( TimeGrowAI::encrypt( 'sk-same' ), TimeGrowAI::encrypt( 'sk-same' ) );
    }

    public function test_keys_saved_by_voice_ai_still_decrypt() {
        $legacy = $this->voice_ai_encrypt( 'AIzaOldKey' );

        $this->assertTrue( TimeGrowAI::is_encrypted( $legacy ) );
        $this->assertSame( 'AIzaOldKey', TimeGrowAI::decrypt( $legacy ) );
    }

    public function test_plain_keys_are_used_as_they_are() {
        $this->assertFalse( TimeGrowAI::is_encrypted( 'sk-ant-plain' ) );
        $this->assertSame( 'sk-ant-plain', TimeGrowAI::decrypt( 'sk-ant-plain' ) );
        $this->assertSame( '', TimeGrowAI::decrypt( '' ) );
    }

    public function test_encrypted_value_is_never_encrypted_twice() {
        $once = TimeGrowAI::encrypt( 'AIzaKey' );
        $this->assertSame( $once, TimeGrowAI::encrypt( $once ) );

        $legacy = $this->voice_ai_encrypt( 'AIzaKey' );
        $this->assertSame( $legacy, TimeGrowAI::encrypt( $legacy ) );
    }

    public function test_damaged_timegrow_value_gives_no_key_not_the_ciphertext() {
        $this->assertSame( '', TimeGrowAI::decrypt( 'tg1:' . base64_encode( str_repeat( 'x', 40 ) ) ) );
    }

    public function test_test_request_for_each_provider() {
        $g = TimeGrowAI::test_request( 'google_gemini', 'gemini-2.5-flash-lite', 'K' );
        $this->assertSame( 'GET', $g['method'] );
        $this->assertSame( 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite', $g['url'] );
        $this->assertSame( 'K', $g['headers']['x-goog-api-key'] );
        $this->assertStringNotContainsString( 'K', $g['url'] ); // the key never goes in the URL

        $o = TimeGrowAI::test_request( 'openai', 'gpt-4o', 'K' );
        $this->assertSame( 'https://api.openai.com/v1/models/gpt-4o', $o['url'] );
        $this->assertSame( 'Bearer K', $o['headers']['Authorization'] );

        $a = TimeGrowAI::test_request( 'anthropic', 'claude-3-haiku-20240307', 'K' );
        $this->assertSame( 'POST', $a['method'] );
        $this->assertSame( 1, json_decode( $a['body'], true )['max_tokens'] ); // smallest possible call
    }

    public function test_reading_test_answers() {
        $ok = TimeGrowAI::read_test_response( [ 'response' => [ 'code' => 200 ], 'body' => '{}' ] );
        $this->assertTrue( $ok['ok'] );

        $bad_key = TimeGrowAI::read_test_response( [ 'response' => [ 'code' => 400 ], 'body' => '{"error":{"message":"API key not valid. Please pass a valid API key."}}' ] );
        $this->assertFalse( $bad_key['ok'] );
        $this->assertStringContainsString( 'not valid', $bad_key['message'] );

        $unauthorized = TimeGrowAI::read_test_response( [ 'response' => [ 'code' => 401 ], 'body' => '{"error":{"message":"invalid x-api-key"}}' ] );
        $this->assertStringContainsString( 'not valid', $unauthorized['message'] );

        $no_model = TimeGrowAI::read_test_response( [ 'response' => [ 'code' => 404 ], 'body' => '{}' ] );
        $this->assertStringContainsString( 'model is not available', $no_model['message'] );

        $down = TimeGrowAI::read_test_response( new WP_Error( 'http', 'Could not resolve host' ) );
        $this->assertNull( $down['ok'] ); // unknown: AI stays on
        $this->assertStringContainsString( 'Could not reach', $down['message'] );

        $busy = TimeGrowAI::read_test_response( [ 'response' => [ 'code' => 503 ], 'body' => '{"error":{"message":"overloaded"}}' ] );
        $this->assertNull( $busy['ok'] );
        $this->assertNull( TimeGrowAI::read_test_response( [ 'response' => [ 'code' => 429 ], 'body' => '{}' ] )['ok'] );
    }

    public function test_requests_for_each_provider_with_an_image() {
        $img = [ 'mime' => 'image/png', 'data' => 'QUJD' ];

        $g = TimeGrowAI::build_request( 'google_gemini', 'gemini-2.5-flash', 'K', [ 'prompt' => 'Read', 'image' => $img, 'json' => true, 'max_tokens' => 300 ] );
        $gb = json_decode( $g['body'], true );
        $this->assertStringEndsWith( '/models/gemini-2.5-flash:generateContent', $g['url'] );
        $this->assertStringNotContainsString( 'K', $g['url'] );
        $this->assertSame( 'K', $g['headers']['x-goog-api-key'] );
        $this->assertSame( 'image/png', $gb['contents'][0]['parts'][1]['inline_data']['mime_type'] );
        $this->assertSame( 'application/json', $gb['generationConfig']['responseMimeType'] );
        $this->assertSame( 300 + TimeGrowAI::THINKING_HEADROOM, $gb['generationConfig']['maxOutputTokens'] ); // room for thinking

        $o = json_decode( TimeGrowAI::build_request( 'openai', 'gpt-4o-mini', 'K', [ 'prompt' => 'Read', 'image' => $img, 'json' => true ] )['body'], true );
        $this->assertSame( 'data:image/png;base64,QUJD', $o['messages'][0]['content'][1]['image_url']['url'] );
        $this->assertSame( 'json_object', $o['response_format']['type'] );
        $this->assertArrayHasKey( 'max_completion_tokens', $o ); // what current OpenAI models accept

        $a = TimeGrowAI::build_request( 'anthropic', 'claude-sonnet-5-5', 'K', [ 'prompt' => 'Read', 'image' => $img ] );
        $ab = json_decode( $a['body'], true );
        $this->assertSame( 'K', $a['headers']['x-api-key'] );
        $this->assertSame( 'image', $ab['messages'][0]['content'][0]['type'] );
        $this->assertSame( 'Read', $ab['messages'][0]['content'][1]['text'] );
    }

    public function test_reading_answers_and_tokens_for_each_provider() {
        $g = TimeGrowAI::parse_response( 'google_gemini', [ 'candidates' => [ [ 'content' => [ 'parts' => [ [ 'text' => 'Let me think', 'thought' => true ], [ 'text' => '{"a":' ], [ 'text' => '1}' ] ] ] ] ], 'usageMetadata' => [ 'promptTokenCount' => 10, 'candidatesTokenCount' => 5 ] ] );
        $this->assertSame( '{"a":1}', $g['text'] );
        $this->assertSame( 15, $g['usage']['total_tokens'] );

        $o = TimeGrowAI::parse_response( 'openai', [ 'choices' => [ [ 'message' => [ 'content' => 'hi' ] ] ], 'usage' => [ 'prompt_tokens' => 3, 'completion_tokens' => 1 ] ] );
        $this->assertSame( 'hi', $o['text'] );

        $a = TimeGrowAI::parse_response( 'anthropic', [ 'content' => [ [ 'type' => 'text', 'text' => 'ok' ] ], 'usage' => [ 'input_tokens' => 2, 'output_tokens' => 2 ] ] );
        $this->assertSame( 'ok', $a['text'] );
        $this->assertSame( 4, $a['usage']['total_tokens'] );

        $this->assertSame( '', TimeGrowAI::parse_response( 'openai', null )['text'] );
    }

    public function test_only_busy_or_failing_providers_are_retried() {
        foreach ( [ 0, 408, 429, 500, 503 ] as $code ) {
            $this->assertTrue( TimeGrowAI::should_retry( $code ), (string) $code );
        }
        foreach ( [ 400, 401, 403, 404 ] as $code ) {
            $this->assertFalse( TimeGrowAI::should_retry( $code ), (string) $code );
        }
    }

    public function test_current_models_and_defaults() {
        $this->assertSame( 'gemini-3.8-flash', TimeGrowAI::default_model( 'google_gemini' ) );
        $this->assertSame( 'claude-sonnet-5-5', TimeGrowAI::default_model( 'anthropic' ) );
        $this->assertSame( 'gpt-6.1-sol', TimeGrowAI::default_model( 'openai' ) );
        // Retired or retiring models are not offered for new configurations
        $this->assertArrayNotHasKey( 'claude-3-5-sonnet-20241022', TimeGrowAI::curated_models( 'anthropic' ) );
        $this->assertArrayNotHasKey( 'gpt-4o-mini', TimeGrowAI::curated_models( 'openai' ) );
        $this->assertArrayNotHasKey( 'gemini-2.0-flash', TimeGrowAI::curated_models( 'google_gemini' ) );
        // A short list: the recommended model and the lowest-cost one per provider
        foreach ( [ 'google_gemini', 'openai', 'anthropic' ] as $provider ) {
            $this->assertCount( 2, TimeGrowAI::model_options( $provider ), $provider );
        }
        $this->assertSame( 'Gemini 3.5 Flash-Lite', TimeGrowAI::model_name( 'google_gemini', 'gemini-3.5-flash-lite' ) );
        $this->assertSame( 'gemini-2.5-flash-lite', TimeGrowAI::model_name( 'google_gemini', 'gemini-2.5-flash-lite' ) ); // older: id as is
    }
}
