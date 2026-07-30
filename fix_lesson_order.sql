-- ============================================================================
--  fix_lesson_order.sql
--  Normalises the `order` column for course sections + lessons so it is never
--  all-zero (which made ORDER BY `order` nondeterministic and let the website
--  and the mobile/API endpoints disagree). After running this, every lesson in
--  a section has a distinct, sequential order, so ALL surfaces list them in the
--  SAME order.
--
--  Run in phpMyAdmin (or MySQL CLI) against the LIVE database.
--  ⚠️  BACK UP the `lesson` and `section` tables first (Export in phpMyAdmin).
--  It is wrapped in a transaction: review the SELECT at the end, then COMMIT.
-- ============================================================================

START TRANSACTION;

-- ---------------------------------------------------------------------------
-- 1) LESSONS — sequential order (1,2,3,…) within each section, preserving the
--    current effective order (existing `order`, then id). Data-driven: computed
--    from the live rows, so it is correct regardless of which lessons exist.
--
--    (A) MySQL 8.0+ / MariaDB 10.2+  — preferred, uses ROW_NUMBER():
-- ---------------------------------------------------------------------------
UPDATE lesson AS t
JOIN (
    SELECT id,
           ROW_NUMBER() OVER (PARTITION BY section_id ORDER BY `order` ASC, id ASC) AS rn
    FROM lesson
) AS r ON r.id = t.id
SET t.`order` = r.rn;

-- ---------------------------------------------------------------------------
--    (B) FALLBACK for older MySQL 5.6/5.7 (no window functions):
--        comment out block (A) above and use this instead.
-- ---------------------------------------------------------------------------
-- SET @sec := NULL, @rn := 0;
-- UPDATE lesson
--   SET `order` = CASE
--       WHEN section_id = @sec THEN @rn := @rn + 1
--       WHEN (@sec := section_id) IS NOT NULL THEN @rn := 1
--       ELSE @rn := 1
--     END
--   ORDER BY section_id ASC, `order` ASC, id ASC;

-- ---------------------------------------------------------------------------
-- 2) SECTIONS — same normalisation across each course.
-- ---------------------------------------------------------------------------
UPDATE section AS t
JOIN (
    SELECT id,
           ROW_NUMBER() OVER (PARTITION BY course_id ORDER BY `order` ASC, id ASC) AS rn
    FROM section
) AS r ON r.id = t.id
SET t.`order` = r.rn;

-- ---------------------------------------------------------------------------
-- 3) SYLLABUS FIRST — push any "course content" resource lesson to the TOP of
--    its section (order 0 sorts before 1). This reproduces what you see on the
--    website (the syllabus PDF appears first). Remove this block if you would
--    rather keep pure chronological order.
-- ---------------------------------------------------------------------------
UPDATE lesson
SET `order` = 0
WHERE lesson_type = 'other'
  AND LOWER(TRIM(title)) = 'course content';

-- ---------------------------------------------------------------------------
-- 4) REVIEW — run this, confirm the order looks right, THEN commit.
-- ---------------------------------------------------------------------------
SELECT s.course_id, l.section_id, l.`order`, l.id, l.title
FROM lesson l
JOIN section s ON s.id = l.section_id
WHERE s.course_id = 3            -- change / remove to inspect other courses
ORDER BY l.section_id ASC, l.`order` ASC, l.id ASC;

-- If it looks correct:
COMMIT;
-- If NOT, undo everything:
-- ROLLBACK;
