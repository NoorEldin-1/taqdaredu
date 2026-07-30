<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the pure, DB-free methods of the Api_security library
 * (application/libraries/Api_security.php): webhook signature verification,
 * API-key permission matching, and client-IP extraction.
 *
 * Api_security::__construct() calls get_instance()->load->database(), which
 * needs a booted framework, so we build the instance WITHOUT the constructor
 * (ReflectionClass::newInstanceWithoutConstructor) and exercise only the
 * methods that don't touch $this->CI.
 */
class ApiSecurityTest extends TestCase
{
    /** @var Api_security */
    private $sec;

    public static function setUpBeforeClass(): void
    {
        // Api_security.php guards on BASEPATH (defined in bootstrap).
        require_once APPPATH . 'libraries/Api_security.php';
    }

    protected function setUp(): void
    {
        $this->sec = (new ReflectionClass('Api_security'))->newInstanceWithoutConstructor();
    }

    /** generate_signature/verify_signature form a matched HMAC-SHA256 pair. */
    public function test_generate_and_verify_signature_match(): void
    {
        $payload = '{"event":"payment.success","amount":100}';
        $secret  = 'whsec_test_secret';

        $sig = $this->sec->generate_signature($payload, $secret);

        $this->assertStringStartsWith('sha256=', $sig);
        $this->assertTrue($this->sec->verify_signature($payload, $sig, $secret));
    }

    /** A signature made with the wrong secret is rejected. */
    public function test_verify_signature_rejects_wrong_secret(): void
    {
        $payload = 'body';
        $good    = $this->sec->generate_signature($payload, 'real-secret');

        $this->assertFalse($this->sec->verify_signature($payload, $good, 'attacker-secret'));
    }

    /** A signature is rejected when the payload is altered (tamper detection). */
    public function test_verify_signature_rejects_tampered_payload(): void
    {
        $sig = $this->sec->generate_signature('amount=100', 'secret');
        $this->assertFalse($this->sec->verify_signature('amount=999999', $sig, 'secret'));
    }

    /** A wildcard permission grants access to any endpoint. */
    public function test_permission_wildcard_allows_all(): void
    {
        $key = ['permissions' => json_encode(['*'])];
        $this->assertTrue($this->sec->check_permission($key, 'courses/list'));
        $this->assertTrue($this->sec->check_permission($key, 'admin/delete_user'));
    }

    /** An exact-match permission grants only that endpoint. */
    public function test_permission_exact_match(): void
    {
        $key = ['permissions' => json_encode(['courses/list'])];
        $this->assertTrue($this->sec->check_permission($key, 'courses/list'));
        $this->assertFalse($this->sec->check_permission($key, 'courses/delete'));
    }

    /** A prefix pattern ("courses/*") matches endpoints under it but not others. */
    public function test_permission_wildcard_pattern(): void
    {
        $key = ['permissions' => json_encode(['courses/*'])];
        $this->assertTrue($this->sec->check_permission($key, 'courses/detail'));
        $this->assertFalse($this->sec->check_permission($key, 'payment/refund'));
    }

    /** No permissions → nothing is allowed. */
    public function test_permission_empty_denies(): void
    {
        $key = ['permissions' => json_encode([])];
        $this->assertFalse($this->sec->check_permission($key, 'courses/list'));
    }

    /** get_client_ip returns the first valid public IP from X-Forwarded-For. */
    public function test_get_client_ip_parses_forwarded_for(): void
    {
        $saved = $_SERVER;
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.5, 10.0.0.1';
        try {
            $this->assertSame('203.0.113.5', $this->sec->get_client_ip());
        } finally {
            $_SERVER = $saved;
        }
    }

    /** get_client_ip falls back to 0.0.0.0 when no valid IP header is present. */
    public function test_get_client_ip_falls_back(): void
    {
        $saved = $_SERVER;
        unset(
            $_SERVER['HTTP_CF_CONNECTING_IP'],
            $_SERVER['HTTP_X_REAL_IP'],
            $_SERVER['HTTP_X_FORWARDED_FOR'],
            $_SERVER['REMOTE_ADDR']
        );
        try {
            $this->assertSame('0.0.0.0', $this->sec->get_client_ip());
        } finally {
            $_SERVER = $saved;
        }
    }
}
