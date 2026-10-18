<?php
use PHPUnit\Framework\TestCase;

require_once dirname( __DIR__, 2 ) . '/modules/core/includes/helpers/TimeGrowAIPrivacy.php';

/**
 * The privacy layer: names and contact details are replaced before anything goes to the AI, and
 * put back before anything is shown.
 */
class AIPrivacyTest extends TestCase {

    private function layer() {
        return new TimeGrowAIPrivacy(
            [ 19 => 'Aragrow LLC', 23 => [ 'Copperleaf Studio 7Y3R', 'Grace Lindqvist' ], 24 => 'Copperleaf Studio DUWV', 40 => [ 'Birchway', 'Birchway Holdings' ], 17 => 'Content Cucumber' ],
            [ 13 => 'David Arago', 14 => 'Maria Gomez' ],
            [],
            'Aragrow, LLC'
        );
    }

    public function test_names_become_stand_ins() {
        $p = $this->layer();
        $this->assertSame( 'Two hours for [Client 40], keyword research with [Person 14]', $p->mask( 'Two hours for Birchway, keyword research with Maria Gomez' ) );
        $this->assertSame( 'billed to [Client 23]', $p->mask( 'billed to Grace Lindqvist' ), 'a billing name is the same client' );
        $this->assertSame( 'hours for [Client 40]', $p->mask( 'hours for birchway' ), 'spoken names are lower case' );
    }

    public function test_a_word_that_fits_several_clients_says_so() {
        $this->assertSame( 'the audit for [one of Client 23, Client 24]', $this->layer()->mask( 'the audit for copperleaf' ) );
    }

    public function test_the_company_is_not_mistaken_for_the_client_with_a_similar_name() {
        $p = $this->layer();
        $this->assertSame( 'what about the entire [Your company]?', $p->mask( 'what about the entire aragrow?' ) );
        $this->assertSame( 'billed to [Your company]', $p->mask( 'billed to Aragrow LLC' ), 'Aragrow always means the company, never the client' );
        $this->assertTrue( TimeGrowAIPrivacy::same_as_company( 'Aragrow LLC', 'Aragrow, LLC' ) );
        $this->assertFalse( TimeGrowAIPrivacy::same_as_company( 'Aragrow Labs', 'Aragrow, LLC' ) );
    }

    public function test_everyday_words_are_left_alone() {
        $this->assertSame( 'wrote content for the website', $this->layer()->mask( 'wrote content for the website' ) );
        $this->assertSame( 'call with [Client 17]', $this->layer()->mask( 'call with Content Cucumber' ) );
    }

    public function test_emails_and_phones_are_hidden_and_restored() {
        $p = $this->layer();
        $masked = $p->mask( 'Email jane@acme.com or call 952 228 1752' );
        $this->assertSame( 'Email [Email 1] or call [Phone 1]', $masked );
        $this->assertSame( 'Email jane@acme.com or call 952 228 1752', $p->unmask( $masked ) );
        $this->assertSame( 'Invoice 1078 for 3.5 hours', $p->mask( 'Invoice 1078 for 3.5 hours' ), 'numbers that are not phones stay' );
        $this->assertSame( 'Today is 2026-10-17 (Saturday).', $p->mask( 'Today is 2026-10-17 (Saturday).' ), 'dates are not phones' );
        $this->assertSame( 'from 2026-10-12 16:20 to 10/17/2026', $p->mask( 'from 2026-10-12 16:20 to 10/17/2026' ) );
        $this->assertSame( 'call +1 (952) 228-1752', $p->unmask( $p->mask( 'call +1 (952) 228-1752' ) ) );
    }

    public function test_json_sent_to_the_ai_stays_valid_and_holds_no_names() {
        $p = $this->layer();
        $json = json_encode( [ [ 'client_id' => 40, 'client' => 'Birchway', 'project' => 'Birchway Holdings - SEO Audit' ], [ 'member' => 'David Arago' ] ] );
        $masked = $p->mask( $json );
        $this->assertNotNull( json_decode( $masked ) );
        $this->assertFalse( $p->leaks( $masked ) );
        $this->assertStringContainsString( '"client_id":40', $masked, 'IDs stay' );
    }

    public function test_answers_from_the_ai_are_put_back() {
        $p = $this->layer();
        $p->mask( 'reach jane@acme.com' );
        $this->assertSame( 'Birchway brought in the most; Maria Gomez logged 3 h. Aragrow, LLC total: 10 h.', $p->unmask( '[Client 40] brought in the most; [Person 14] logged 3 h. [Your company] total: 10 h.' ) );
        $this->assertSame( 'Copperleaf Studio 7Y3R / Copperleaf Studio DUWV', $p->unmask( '[one of Client 23, Client 24]' ) );
        $this->assertSame( 'Birchway', $p->unmask( 'Client 40' ), 'also without brackets' );
        $this->assertSame( [ 'name' => 'Jane', 'email' => 'jane@acme.com' ], $p->unmask( [ 'name' => 'Jane', 'email' => '[Email 1]' ] ) );
        $this->assertSame( '[Client 999]', $p->unmask( '[Client 999]' ), 'unknown stand-ins are left as they are' );
    }

    public function test_ambiguous_names_can_be_asked_about() {
        $p = $this->layer();
        $this->assertSame( [ [ 'Copperleaf Studio 7Y3R', 'Copperleaf Studio DUWV' ] ], $p->ambiguous( $p->mask( 'hours for copperleaf' ) ) );
        $this->assertSame( [], $p->ambiguous( $p->mask( 'hours for birchway' ) ) );
    }

    public function test_a_team_member_who_is_also_a_billing_contact_stays_a_person() {
        $p = new TimeGrowAIPrivacy( [ 19 => [ 'Aragrow LLC', 'David Arago' ] ], [ 13 => 'David Arago' ], [], 'Aragrow, LLC' );
        $this->assertSame( 'You ([Person 13]): 7 h', $p->mask( 'You (David Arago): 7 h' ) );
        $this->assertSame( 'billed to [Your company]', $p->mask( 'billed to Aragrow LLC' ) );
    }
}
