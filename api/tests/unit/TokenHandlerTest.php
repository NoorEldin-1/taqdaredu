<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for TokenHandler (application/libraries/TokenHandler.php) — the
 * project's own wrapper around JWT that every controller uses to mint and
 * verify auth tokens (GenerateToken/DecodeToken).
 *
 * TokenHandler is loaded by tests/bootstrap.php, which also seeds a ≥32-char
 * JWT_SECRET into the environment so the real constructor runs without booting
 * CodeIgniter.
 */
class TokenHandlerTest extends TestCase
{
    private function handler(): TokenHandler
    {
        return new TokenHandler();
    }

    /** A generated token decodes back to the original user data. */
    public function test_generate_then_decode_roundtrip(): void
    {
        $th      = $this->handler();
        $token   = $th->GenerateToken(['user_id' => 99, 'email' => 'x@y.z']);
        $decoded = $th->DecodeToken($token);

        $this->assertSame(99, $decoded['user_id']);
        $this->assertSame('x@y.z', $decoded['email']);
    }

    /** GenerateToken stamps issued-at and a ~30-day expiry so tokens are not
     *  valid forever. */
    public function test_generate_stamps_iat_and_30_day_exp(): void
    {
        $before  = time();
        $decoded = $this->handler()->DecodeToken($this->handler()->GenerateToken(['user_id' => 1]));
        $after   = time();

        $this->assertArrayHasKey('iat', $decoded);
        $this->assertArrayHasKey('exp', $decoded);
        $this->assertGreaterThanOrEqual($before, $decoded['iat']);
        $this->assertLessThanOrEqual($after, $decoded['iat']);

        $ttl = $decoded['exp'] - $decoded['iat'];
        $this->assertSame(2592000, $ttl, 'Token TTL should be exactly 30 days');
    }

    /** DecodeToken returns an array (controllers cast it and read user_id). */
    public function test_decode_returns_array(): void
    {
        $decoded = $this->handler()->DecodeToken($this->handler()->GenerateToken(['user_id' => 5]));
        $this->assertIsArray($decoded);
    }

    /** A token forged/tampered after signing is rejected (throws). */
    public function test_decode_rejects_tampered_token(): void
    {
        $token = $this->handler()->GenerateToken(['user_id' => 1, 'role' => 'student']);
        [$h, , $s] = explode('.', $token);
        $forgedBody = JWT::urlsafeB64Encode(json_encode(['user_id' => 1, 'role' => 'admin', 'exp' => time() + 999]));
        $forged     = "$h.$forgedBody.$s";

        $this->expectException(Exception::class); // SignatureInvalidException extends Exception
        $this->handler()->DecodeToken($forged);
    }

    /** Garbage input throws rather than silently returning a truthy identity. */
    public function test_decode_rejects_garbage(): void
    {
        $this->expectException(Exception::class);
        $this->handler()->DecodeToken('garbage.token.value');
    }

    /** The constructor refuses a secret shorter than 32 chars — the guard that
     *  prevents shipping the forgeable Academy-LMS template default. */
    public function test_constructor_rejects_short_secret(): void
    {
        $saved = getenv('JWT_SECRET');
        putenv('JWT_SECRET=too-short');
        try {
            $this->expectException(RuntimeException::class);
            new TokenHandler();
        } finally {
            // Restore for the remaining tests in the process.
            putenv('JWT_SECRET=' . $saved);
            $_SERVER['JWT_SECRET'] = $saved;
        }
    }
}
