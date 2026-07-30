<?php

use PHPUnit\Framework\TestCase;

/**
 * Security tests.
 *
 * Two groups:
 *  - Token-integrity tests run everywhere (no server needed) — they prove the
 *    JWT signature/alg checks can't be bypassed.
 *  - Live HTTP probes (SQLi, CORS, unauthorised access, admin lockout) run
 *    against TEST_BASE_URL and skip when it's unreachable.
 *
 * TokenHandler/JWT are loaded by tests/bootstrap.php.
 */
class SecurityTest extends TestCase
{
    // ---------------------------------------------------------------- helpers

    private function http(string $method, string $path, array $fields = [], array $headers = []): array
    {
        $ch = curl_init(TEST_BASE_URL . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        if ($fields) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        }
        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $raw        = curl_exec($ch);
        $code       = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        return [
            'code'    => $code,
            'headers' => $raw === false ? '' : substr($raw, 0, $headerSize),
            'json'    => json_decode($raw === false ? '' : substr($raw, $headerSize), true),
        ];
    }

    private function requireServer(): void
    {
        $res = $this->http('GET', '/api/api_frontend/settings');
        $usable = $res['code'] === 200 && is_array($res['json']) && array_key_exists('status', $res['json']);
        if (!$usable) {
            $this->markTestSkipped('Backend at ' . TEST_BASE_URL . ' not answering as an app — skipping live security probe.');
        }
    }

    // -------------------------------------------------- token integrity (local)

    /** JWT tampering: editing the payload after signing invalidates the token. */
    public function test_jwt_payload_tampering_is_detected(): void
    {
        $th    = new TokenHandler();
        $token = $th->GenerateToken(['user_id' => 10, 'role' => 'student']);

        [$h, , $s] = explode('.', $token);
        $escalated = JWT::urlsafeB64Encode(json_encode([
            'user_id' => 10, 'role' => 'admin', 'exp' => time() + 99999,
        ]));
        $forged = "$h.$escalated.$s";

        $this->expectException(Exception::class);
        $th->DecodeToken($forged);
    }

    /** Signature-stripping / re-signing with a different key is rejected. */
    public function test_jwt_resigned_with_attacker_key_is_rejected(): void
    {
        $forged = JWT::encode(['user_id' => 1, 'role' => 'admin'], 'attacker-key-not-the-server-secret');

        $this->expectException(Exception::class);
        (new TokenHandler())->DecodeToken($forged);
    }

    /** Algorithm-confusion: a token whose alg isn't in the HS256 allowlist is refused. */
    public function test_jwt_algorithm_confusion_is_blocked(): void
    {
        $token = JWT::encode(['user_id' => 1], TEST_JWT_SECRET, 'HS512');

        // TokenHandler::DecodeToken only allows HS256.
        $this->expectException(Exception::class);
        (new TokenHandler())->DecodeToken($token);
    }

    // ------------------------------------------------------- live HTTP probes

    /** SQL injection in the login email must not authenticate anyone. */
    public function test_sql_injection_login_is_neutralised(): void
    {
        $this->requireServer();

        $res = $this->http('POST', '/api/api_frontend/login', [
            'email'    => "' OR '1'='1",
            'password' => "' OR '1'='1",
        ]);

        $this->assertNotSame(500, $res['code'], 'SQLi payload should not cause a server error');
        $this->assertFalse($res['json']['status'] ?? true, 'SQLi must not yield a successful login');
        $this->assertArrayNotHasKey('token', $res['json']['data'] ?? []);
    }

    /** CORS: a request from an origin outside the allowlist is not granted an
     *  Access-Control-Allow-Origin echo for that origin. */
    public function test_cors_rejects_unlisted_origin(): void
    {
        $this->requireServer();

        $evil = 'https://evil.example.com';
        $res  = $this->http('GET', '/api/api_frontend/settings', [], ['Origin: ' . $evil]);

        $this->assertStringNotContainsStringIgnoringCase(
            'access-control-allow-origin: ' . $evil,
            $res['headers'],
            'Backend must not reflect a non-allowlisted Origin'
        );
    }

    /** Unauthorised access: a protected endpoint returns 401 without a token. */
    public function test_unauthorised_access_returns_401(): void
    {
        $this->requireServer();

        $res = $this->http('GET', '/api/api_frontend/my_courses');
        $this->assertSame(401, $res['code']);
    }

    /** Admin endpoints reject a request with no / non-admin token (401 or 403). */
    public function test_admin_endpoint_requires_admin(): void
    {
        $this->requireServer();

        $res = $this->http('GET', '/api/api_admin/dashboard');
        $this->assertContains($res['code'], [401, 403, 404],
            'Admin endpoint must not be open to unauthenticated callers (got ' . $res['code'] . ')');
        if (in_array($res['code'], [401, 403], true) && is_array($res['json'])) {
            $this->assertFalse($res['json']['status'] ?? true);
        }
    }
}
