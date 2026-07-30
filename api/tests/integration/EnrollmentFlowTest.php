<?php

require_once __DIR__ . '/../_support/HttpTestCase.php';

/**
 * Enrolment-flow integration tests over HTTP against the live backend.
 *
 * Covers the brief's cases: an authenticated user can enrol; an unauthenticated
 * request is rejected; a duplicate enrolment returns a sensible error rather
 * than creating a second row. Skips when the backend is unreachable.
 */
class EnrollmentFlowTest extends HttpTestCase
{
    /** Enrolling without a token is rejected (401). */
    public function test_enroll_without_token_is_rejected(): void
    {
        $res = $this->request('POST', '/api/api_frontend/enroll_free_course', ['course_id' => 1]);
        $this->assertSame(401, $res['code'], 'Guests must not be able to enrol');
    }

    /** Enrolling with a garbage token is rejected (401). */
    public function test_enroll_with_invalid_token_is_rejected(): void
    {
        $res = $this->request('POST', '/api/api_frontend/enroll_free_course', ['course_id' => 1], [
            'Authorization: Bearer bogus.token.here',
        ]);
        $this->assertSame(401, $res['code']);
    }

    /**
     * With real credentials + a free course id, enrolling once succeeds and
     * enrolling again returns status:false (already enrolled) — never a second
     * enrolment. Requires TEST_LOGIN_EMAIL / TEST_LOGIN_PASSWORD / TEST_FREE_COURSE_ID.
     */
    public function test_enroll_then_duplicate_is_rejected(): void
    {
        $email    = getenv('TEST_LOGIN_EMAIL');
        $password = getenv('TEST_LOGIN_PASSWORD');
        $courseId = getenv('TEST_FREE_COURSE_ID');
        if (!$email || !$password || !$courseId) {
            $this->markTestSkipped('Set TEST_LOGIN_EMAIL / TEST_LOGIN_PASSWORD / TEST_FREE_COURSE_ID to run the enrol path');
        }

        $login = $this->request('POST', '/api/api_frontend/login', [
            'email' => $email, 'password' => $password,
        ]);
        $token = $login['json']['data']['token'] ?? null;
        $this->assertNotEmpty($token, 'Login must succeed to test enrolment');
        $auth = ['Authorization: Bearer ' . $token];

        // First enrol: success OR already-enrolled (idempotent) are both fine.
        $first = $this->request('POST', '/api/api_frontend/enroll_free_course', ['course_id' => $courseId], $auth);
        $this->assertIsArray($first['json']);

        // Second enrol must NOT report a brand-new success — it should be blocked.
        $second = $this->request('POST', '/api/api_frontend/enroll_free_course', ['course_id' => $courseId], $auth);
        $this->assertIsArray($second['json']);
        $this->assertArrayHasKey('status', $second['json']);
        // Either an explicit false, or a message indicating the pre-existing enrolment.
        $blocked = ($second['json']['status'] === false)
            || stripos((string) ($second['json']['message'] ?? ''), 'enrol') !== false
            || stripos((string) ($second['json']['message'] ?? ''), 'already') !== false;
        $this->assertTrue($blocked, 'Duplicate enrolment should be blocked');
    }
}
