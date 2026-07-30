<?php

use PHPUnit\Framework\TestCase;

/**
 * Base class for tests that exercise real query logic against the local
 * MySQL/MariaDB database (`myco_uk`).
 *
 * These validate the SQL contract the CodeIgniter models depend on — schema
 * shape, filtering, ordering, uniqueness — against real data (26 courses,
 * 128 users at time of writing). We cannot instantiate CI3 CI_Model subclasses
 * without booting the framework, so we assert on the underlying queries the
 * models run. Every write happens inside an InnoDB transaction that is rolled
 * back in tearDown, so the database is never mutated.
 *
 * If the DB is unreachable the whole case skips (never fails) — matching the
 * "use mocks / skip when the server isn't running" rule from the brief.
 */
abstract class DbTestCase extends TestCase
{
    /** @var PDO|null Shared connection for the process. */
    protected static $pdo;

    /** @var bool Whether a transaction is currently open for this test. */
    private $inTransaction = false;

    public static function setUpBeforeClass(): void
    {
        if (self::$pdo !== null) {
            return;
        }
        try {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8', TEST_DB_HOST, TEST_DB_NAME);
            self::$pdo = new PDO($dsn, TEST_DB_USER, TEST_DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT           => 4,
            ]);
        } catch (Throwable $e) {
            self::$pdo = null;
        }
    }

    protected function setUp(): void
    {
        if (self::$pdo === null) {
            $this->markTestSkipped(
                'Local database ' . TEST_DB_NAME . ' unreachable — skipping DB-backed test. '
                . 'Set TEST_DB_HOST/USER/PASS to enable.'
            );
        }
    }

    /** Open a transaction for a write test; automatically rolled back in tearDown. */
    protected function beginRollbackTransaction(): void
    {
        self::$pdo->beginTransaction();
        $this->inTransaction = true;
    }

    protected function tearDown(): void
    {
        if ($this->inTransaction && self::$pdo && self::$pdo->inTransaction()) {
            self::$pdo->rollBack();
        }
        $this->inTransaction = false;
    }

    /** True if a table exists in the current database. */
    protected function tableExists(string $table): bool
    {
        $stmt = self::$pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?'
        );
        $stmt->execute([$table]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
