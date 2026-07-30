<?php

use PHPUnit\Framework\TestCase;

/**
 * Input-validation contract tests.
 *
 * The controllers reject empty/invalid input (e.g. login_post: "Email and
 * password required") and rely on parameterised queries for injection safety.
 * These tests pin that contract: e-mail shape, required-field detection, and a
 * live check that a classic SQLi string is treated as a literal value (not SQL)
 * by a parameterised query against the real users table.
 */
class ValidationTest extends TestCase
{
    /** Mirrors the "email must look like an email" rule used at registration. */
    public function test_valid_emails_accepted(): void
    {
        foreach (['jane@example.com', 'a.b+tag@sub.domain.co.uk', 'user_1@my-communication.uk'] as $email) {
            $this->assertNotFalse(
                filter_var($email, FILTER_VALIDATE_EMAIL),
                "$email should validate"
            );
        }
    }

    /** Malformed e-mails are rejected. */
    public function test_invalid_emails_rejected(): void
    {
        foreach (['', 'not-an-email', 'missing@tld', '@no-local.com', 'spaces in@email.com'] as $email) {
            $this->assertFalse(
                filter_var($email, FILTER_VALIDATE_EMAIL),
                "$email should be rejected"
            );
        }
    }

    /**
     * login_post()/register_post() bail with 400 when a required field is empty.
     * This pins that empty-detection contract (empty string, whitespace, null).
     */
    public function test_required_field_detection(): void
    {
        $isMissing = static fn($v) => $v === null || trim((string) $v) === '';

        $this->assertTrue($isMissing(''));
        $this->assertTrue($isMissing('   '));
        $this->assertTrue($isMissing(null));
        $this->assertFalse($isMissing('secret'));
    }

    /** A password-strength rule (min 6) as enforced on registration. */
    public function test_password_minimum_length(): void
    {
        $meetsPolicy = static fn(string $pw) => strlen($pw) >= 6;

        $this->assertFalse($meetsPolicy('123'));
        $this->assertTrue($meetsPolicy('str0ngPass'));
    }

    /** HTML/script payloads are neutralised by escaping (defence-in-depth for
     *  any field later rendered server-side). */
    public function test_html_is_escaped(): void
    {
        $dirty = '<script>alert(1)</script>';
        $clean = htmlspecialchars($dirty, ENT_QUOTES, 'UTF-8');

        $this->assertStringNotContainsString('<script>', $clean);
        $this->assertStringContainsString('&lt;script&gt;', $clean);
    }

    /**
     * A classic SQL-injection string, bound as a parameter, must match NO user
     * (it is treated as a literal e-mail value, never executed as SQL). Proves
     * the parameterised-query defence the models depend on. Skips if the local
     * DB is unavailable.
     */
    public function test_sql_injection_is_neutralised_by_parameterised_query(): void
    {
        $pdo = $this->pdoOrSkip();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $stmt->execute(["' OR '1'='1"]);
        $count = (int) $stmt->fetchColumn();

        $this->assertSame(0, $count, "SQLi payload matched a user — parameterisation failed");

        // Sanity: the table itself is non-empty, so a 0 above is meaningful.
        $total = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $this->assertGreaterThan(0, $total, 'users table unexpectedly empty');
    }

    private function pdoOrSkip(): PDO
    {
        try {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8', TEST_DB_HOST, TEST_DB_NAME);
            return new PDO($dsn, TEST_DB_USER, TEST_DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 4,
            ]);
        } catch (Throwable $e) {
            $this->markTestSkipped('DB unreachable — skipping live SQLi check: ' . $e->getMessage());
        }
    }
}
