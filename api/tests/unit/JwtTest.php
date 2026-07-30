<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the standalone JWT library (application/libraries/JWT.php),
 * the Firebase php-jwt implementation used to sign/verify auth tokens.
 *
 * The class is loaded by tests/bootstrap.php (via TokenHandler's require), so
 * we must NOT require it again here.
 */
class JwtTest extends TestCase
{
    private $key = 'unit-test-signing-key-1234567890';

    protected function setUp(): void
    {
        // Reset library statics so tests don't leak fixed-time / leeway state.
        JWT::$timestamp = null;
        JWT::$leeway    = 0;
    }

    protected function tearDown(): void
    {
        JWT::$timestamp = null;
        JWT::$leeway    = 0;
    }

    /** encode() produces a well-formed 3-segment JWT string. */
    public function test_encode_returns_three_segment_token(): void
    {
        $token = JWT::encode(['user_id' => 7], $this->key);

        $this->assertIsString($token);
        $this->assertCount(3, explode('.', $token), 'A JWT must have header.payload.signature');
    }

    /** decode() round-trips the exact payload that was encoded. */
    public function test_decode_returns_original_payload(): void
    {
        $payload = ['user_id' => 42, 'email' => 'jane@example.com', 'role' => 'student'];
        $token   = JWT::encode($payload, $this->key);

        $decoded = (array) JWT::decode($token, $this->key, ['HS256']);

        $this->assertSame(42, $decoded['user_id']);
        $this->assertSame('jane@example.com', $decoded['email']);
        $this->assertSame('student', $decoded['role']);
    }

    /** The header advertises the HS256 algorithm by default. */
    public function test_encode_uses_hs256_by_default(): void
    {
        $token  = JWT::encode(['a' => 1], $this->key);
        [$headb64] = explode('.', $token);
        $header = json_decode(JWT::urlsafeB64Decode($headb64), true);

        $this->assertSame('HS256', $header['alg']);
        $this->assertSame('JWT', $header['typ']);
    }

    /** A token whose exp is in the past must be rejected with ExpiredException. */
    public function test_expired_token_throws(): void
    {
        $token = JWT::encode(['user_id' => 1, 'exp' => time() - 10], $this->key);

        $this->expectException(ExpiredException::class);
        JWT::decode($token, $this->key, ['HS256']);
    }

    /** A token with a not-before (nbf) in the future must be rejected. */
    public function test_not_yet_valid_token_throws(): void
    {
        $token = JWT::encode(['user_id' => 1, 'nbf' => time() + 3600], $this->key);

        $this->expectException(BeforeValidException::class);
        JWT::decode($token, $this->key, ['HS256']);
    }

    /** Fixing JWT::$timestamp lets an otherwise-expired token validate — proves
     *  expiry is evaluated against the configurable clock, not wall time. */
    public function test_expiry_respects_fixed_timestamp(): void
    {
        $issued = 1_000_000_000;
        $token  = JWT::encode(['user_id' => 1, 'iat' => $issued, 'exp' => $issued + 100], $this->key);

        JWT::$timestamp = $issued + 50; // "now" is inside the window
        $decoded = (array) JWT::decode($token, $this->key, ['HS256']);
        $this->assertSame(1, $decoded['user_id']);
    }

    /** Tampering with the payload invalidates the signature. */
    public function test_tampered_payload_throws_signature_invalid(): void
    {
        $token = JWT::encode(['user_id' => 1, 'role' => 'student'], $this->key);
        [$head, , $sig] = explode('.', $token);

        // Forge an escalated payload but keep the original signature.
        $forgedBody = JWT::urlsafeB64Encode(json_encode(['user_id' => 1, 'role' => 'admin']));
        $forged     = "$head.$forgedBody.$sig";

        $this->expectException(SignatureInvalidException::class);
        JWT::decode($forged, $this->key, ['HS256']);
    }

    /** A token signed with a different key fails signature verification. */
    public function test_wrong_key_throws_signature_invalid(): void
    {
        $token = JWT::encode(['user_id' => 1], $this->key);

        $this->expectException(SignatureInvalidException::class);
        JWT::decode($token, 'a-totally-different-secret-key-999', ['HS256']);
    }

    /** A malformed token (wrong number of segments) is rejected. */
    public function test_malformed_token_throws(): void
    {
        $this->expectException(UnexpectedValueException::class);
        JWT::decode('not-a-valid-token', $this->key, ['HS256']);
    }

    /** An empty key is rejected up front. */
    public function test_empty_key_throws(): void
    {
        $token = JWT::encode(['user_id' => 1], $this->key);

        $this->expectException(InvalidArgumentException::class);
        JWT::decode($token, '', ['HS256']);
    }

    /** The "none" algorithm downgrade attack is blocked: a token whose alg is
     *  not in the allowed list is rejected. */
    public function test_algorithm_not_in_allowlist_throws(): void
    {
        $token = JWT::encode(['user_id' => 1], $this->key, 'HS512');

        // Caller only permits HS256 → HS512 token must be refused.
        $this->expectException(UnexpectedValueException::class);
        JWT::decode($token, $this->key, ['HS256']);
    }

    /** sign() with an unsupported algorithm throws DomainException. */
    public function test_sign_with_unsupported_algorithm_throws(): void
    {
        $this->expectException(DomainException::class);
        JWT::sign('message', $this->key, 'NOPE999');
    }

    /** urlsafeB64 encode/decode is a lossless round-trip and stays URL-safe. */
    public function test_urlsafe_base64_roundtrip(): void
    {
        $data    = "binary\x00\xff+/=data with spaces";
        $encoded = JWT::urlsafeB64Encode($data);

        $this->assertStringNotContainsString('+', $encoded);
        $this->assertStringNotContainsString('/', $encoded);
        $this->assertStringNotContainsString('=', $encoded);
        $this->assertSame($data, JWT::urlsafeB64Decode($encoded));
    }
}
