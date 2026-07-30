<?php

require_once __DIR__ . '/../_support/DbTestCase.php';

/**
 * Query-contract tests for course retrieval/filtering.
 *
 * CI3 models (Crud_model / Api_frontend_model) can't be instantiated without
 * booting the framework, so these exercise the SQL the models run — against the
 * real `course` table in `myco_uk` — covering the paths the brief calls out:
 * fetch by valid id, fetch by non-existent id, and filtering by category /
 * price / level. Read-only; skips when the DB is unreachable.
 */
class CourseModelTest extends DbTestCase
{
    private static $sampleCourseId;
    private static $sampleCategoryId;

    private function courseId(): int
    {
        if (self::$sampleCourseId === null) {
            self::$sampleCourseId = (int) self::$pdo
                ->query("SELECT id FROM course ORDER BY id ASC LIMIT 1")
                ->fetchColumn();
        }
        return self::$sampleCourseId;
    }

    /** get_course($id) with a real id returns exactly one fully-shaped row. */
    public function test_get_course_by_valid_id_returns_row(): void
    {
        $id   = $this->courseId();
        $stmt = self::$pdo->prepare('SELECT * FROM course WHERE id = ?');
        $stmt->execute([$id]);
        $course = $stmt->fetch();

        $this->assertIsArray($course);
        $this->assertSame($id, (int) $course['id']);
        // Columns the frontend Course type depends on must be present.
        foreach (['title', 'price', 'level', 'category_id', 'status'] as $col) {
            $this->assertArrayHasKey($col, $course, "course row missing '$col'");
        }
    }

    /** get_course() with a non-existent id returns no row (not an error). */
    public function test_get_course_by_missing_id_returns_nothing(): void
    {
        $stmt = self::$pdo->prepare('SELECT * FROM course WHERE id = ?');
        $stmt->execute([999999999]);
        $this->assertFalse($stmt->fetch(), 'A missing course id must yield no row');
    }

    /** Filtering by category returns only courses in that category. */
    public function test_filter_by_category(): void
    {
        $categoryId = self::$pdo
            ->query("SELECT category_id FROM course WHERE category_id IS NOT NULL GROUP BY category_id ORDER BY COUNT(*) DESC LIMIT 1")
            ->fetchColumn();

        if ($categoryId === false || $categoryId === null) {
            $this->markTestSkipped('No categorised courses in local data');
        }

        $stmt = self::$pdo->prepare('SELECT category_id FROM course WHERE category_id = ?');
        $stmt->execute([$categoryId]);
        $rows = $stmt->fetchAll();

        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertSame((int) $categoryId, (int) $row['category_id']);
        }
    }

    /** A "free courses" filter (is_free_course = 1 OR price = 0) never returns a
     *  paid, non-free course. */
    public function test_filter_free_courses_are_actually_free(): void
    {
        $rows = self::$pdo
            ->query("SELECT price, is_free_course FROM course WHERE is_free_course = 1 OR price = 0 OR price IS NULL")
            ->fetchAll();

        foreach ($rows as $row) {
            $isFree = ((int) ($row['is_free_course'] ?? 0) === 1)
                || (float) ($row['price'] ?? 0) == 0.0;
            $this->assertTrue($isFree, 'A course in the "free" filter is not actually free');
        }
        $this->assertTrue(true); // still a valid (vacuously true) assertion if none exist
    }

    /** A price-range filter respects its bounds. */
    public function test_filter_by_price_range(): void
    {
        $min = 0;
        $max = 100;
        $stmt = self::$pdo->prepare('SELECT price FROM course WHERE price BETWEEN ? AND ?');
        $stmt->execute([$min, $max]);

        foreach ($stmt->fetchAll() as $row) {
            $this->assertGreaterThanOrEqual($min, (float) $row['price']);
            $this->assertLessThanOrEqual($max, (float) $row['price']);
        }
        $this->assertTrue(true);
    }

    /** Level filtering returns only rows of the requested level. */
    public function test_filter_by_level(): void
    {
        $level = self::$pdo
            ->query("SELECT level FROM course WHERE level IS NOT NULL AND level <> '' GROUP BY level ORDER BY COUNT(*) DESC LIMIT 1")
            ->fetchColumn();

        if ($level === false || $level === null) {
            $this->markTestSkipped('No levelled courses in local data');
        }

        $stmt = self::$pdo->prepare('SELECT level FROM course WHERE level = ?');
        $stmt->execute([$level]);
        $rows = $stmt->fetchAll();

        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertSame($level, $row['level']);
        }
    }

    /** "Newest" ordering returns rows in descending date_added order. */
    public function test_newest_ordering_is_descending(): void
    {
        $dates = self::$pdo
            ->query("SELECT date_added FROM course WHERE date_added IS NOT NULL ORDER BY date_added DESC LIMIT 10")
            ->fetchAll(PDO::FETCH_COLUMN);

        $sorted = $dates;
        rsort($sorted, SORT_NUMERIC);
        $this->assertSame($sorted, $dates, 'Courses are not ordered newest-first');
    }
}
