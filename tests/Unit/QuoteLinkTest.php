<?php

namespace Tests\Unit;

use App\Support\QuoteLink;
use Tests\TestCase;

class QuoteLinkTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'quote.link_secret' => 'test-secret',
            'quote.signature_length' => 64,
            'quote.base_url' => 'https://zoho.trackingmouthefirst.com',
        ]);
    }

    public function test_signature_is_hmac_sha256_hex_of_id(): void
    {
        $expected = hash_hmac('sha256', '6123456000000123456', 'test-secret');

        $this->assertSame($expected, QuoteLink::sign('6123456000000123456'));
    }

    public function test_verify_accepts_valid_and_rejects_invalid(): void
    {
        $sig = QuoteLink::sign('42');

        $this->assertTrue(QuoteLink::verify('42', $sig));
        $this->assertTrue(QuoteLink::verify('42', strtoupper($sig)), 'uppercase hex should still verify');
        $this->assertFalse(QuoteLink::verify('42', substr($sig, 0, -1).'0'));
        $this->assertFalse(QuoteLink::verify('43', $sig));
    }

    public function test_signature_can_be_truncated_to_match_legacy_tracking_links(): void
    {
        config(['quote.signature_length' => 32]);

        $sig = QuoteLink::sign('42');

        $this->assertSame(32, strlen($sig));
        $this->assertTrue(QuoteLink::verify('42', $sig));
    }

    public function test_url_follows_tracking_style(): void
    {
        $sig = QuoteLink::sign('42');

        $this->assertSame("https://zoho.trackingmouthefirst.com/quote/42.{$sig}", QuoteLink::url('42'));
        $this->assertSame("https://zoho.trackingmouthefirst.com/quote/42.{$sig}?download=1", QuoteLink::url('42', true));
    }

    public function test_verify_fails_when_secret_missing(): void
    {
        config(['quote.link_secret' => '']);

        $this->assertFalse(QuoteLink::verify('42', str_repeat('a', 64)));
    }
}
