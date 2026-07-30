<?php

require_once __DIR__ . '/../_support/HttpTestCase.php';

/**
 * End-to-end auth-flow integration tests over HTTP:
 *   register → login → access a protected endpoint with the JWT → reject a bad
 *   token.
 *
 * These drive the real CodeIgniter backend at TEST_BASE_URL. They skip (never
 * fail) when no backend is running, so the suite stays green on a code-only
 * machine; point TEST_BASE_URL at a live/staging server to exercise them.
 */
class AuthFlowTest extends HttpTestCase
{
    /** A fresh registration returns the standard envelope. */
    public function test_register_new_user(): void
    {
        $email = 'phpunit_' . substr(md5(uniqid('', true)), 0, 10) . '@example.com';

        $res = $this->request('POST', '/api/api_frontend/register', [
            'first_name' => 'PHPUnit',
            'last_name'  => 'Tester',
            'email'      => $email,
            'password'   => 'Str0ngPass!',
        ]);

        $this->assertContains($res['code'], [200, 201, 400, 409, 422],
            'Register should answer with a known status, got ' . $res['code']);
        $this->assertIsArray($res['json'], 'Register must return a JSON envelope');
        $this->assertArrayHasKey('status', $res['json']);
    }

    /** Login with obviously-wrong credentials never returns a token. */
    public function test_login_with_invalid_credentials_is_rejected(): void
    {
        $res = $this->request('POST', '/api/api_frontend/login', [
            'email'    => 'definitely-not-a-user@example.com',
            'password' => 'wrong-password',
        ]);

        $this->assertIsArray($res['json']);
        $this->assertFalse($res['json']['status'] ?? true, 'Bad credentials must not succeed');
        $this->assertArrayNotHasKey('token', $res['json']['data'] ?? [], 'No token for bad login');
    }

    /** Missing required fields are rejected with a 400 + status:false. */
    public function test_login_requires_email_and_password(): void
    {
        $res = $this->request('POST', '/api/api_frontend/login', ['email' => '']);

        $this->assertSame(400, $res['code']);
        $this->assertFalse($res['json']['status'] ?? true);
    }

    /** A protected endpoint refuses access without a token (401). */
    public function test_protected_endpoint_requires_token(): void
    {
        $res = $this->request('GET', '/api/api_frontend/my_courses');
        $this->assertSame(401, $res['code'], 'Protected endpoint must return 401 without a token');
    }

    /**
     * Full happy path when TEST_LOGIN_EMAIL / TEST_LOGIN_PASSWORD are provided:
     * login yields a JWT, and that JWT unlocks the protected endpoint.
     */
    public function test_login_then_use_token_on_protected_endpoint(): void
    {
        $email    = getenv('TEST_LOGIN_EMAIL');
        $password = getenv('TEST_LOGIN_PASSWORD');
        if (!$email || !$password) {
            $this->markTestSkipped('Set TEST_LOGIN_EMAIL / TEST_LOGIN_PASSWORD to run the full login→protected path');
        }

        $login = $this->request('POST', '/api/api_frontend/login', [
            'email'    => $email,
            'password' => $password,
        ]);
        $this->assertTrue($login['json']['status'] ?? false, 'Valid login should succeed');
        $token = $login['json']['data']['token'] ?? null;
        $this->assertNotEmpty($token, 'Login must return a JWT');

        $protected = $this->request('GET', '/api/api_frontend/my_courses', [], [
            'Authorization: Bearer ' . $token,
        ]);
        $this->assertSame(200, $protected['code'], 'Valid token should unlock the protected endpoint');
    }

    /** A structurally-invalid token is rejected (401 Invalid token). */
    public function test_invalid_token_is_rejected(): void
    {
        $res = $this->request('GET', '/api/api_frontend/my_courses', [], [
            'Authorization: Bearer not.a.valid.jwt',
        ]);
        $this->assertSame(401, $res['code']);
    }
}
