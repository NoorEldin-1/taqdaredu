<?php
/**
 * PHPUnit bootstrap for the Academy LMS (CodeIgniter 3) backend.
 *
 * We deliberately do NOT boot the full CI3 kernel here — CI3 calls exit(),
 * relies on superglobals, and cannot be instantiated cleanly under PHPUnit.
 * Instead we define the framework path constants that the library files guard
 * on (`defined('BASEPATH') or exit`) and load the standalone auth libraries so
 * the JWT / TokenHandler / Api_security units can be exercised in isolation.
 *
 * DB-backed tests (Course/Payment/Enrollment) connect directly to the local
 * MySQL/MariaDB instance via PDO (see DbTestCase) and skip when unreachable.
 * HTTP integration/security tests hit a running backend and skip when it is
 * down. Nothing here mutates source or the vendor tree.
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

define('BASEPATH', __DIR__ . '/../system/');
define('APPPATH', __DIR__ . '/../application/');
define('VIEWPATH', APPPATH . 'views/');
defined('ENVIRONMENT') or define('ENVIRONMENT', 'testing');

/*
 * TokenHandler resolves its signing secret from getenv('JWT_SECRET') /
 * $_SERVER['JWT_SECRET'] before falling back to the CI config. Supplying it
 * here lets us construct the REAL TokenHandler without booting CodeIgniter.
 * (A dedicated ≥32-char test secret — not the production one.)
 */
$__jwt_secret = getenv('JWT_SECRET') ?: 'test_secret_key_for_phpunit_0123456789abcdef_do_not_use_in_prod';
putenv('JWT_SECRET=' . $__jwt_secret);
$_SERVER['JWT_SECRET'] = $__jwt_secret;
define('TEST_JWT_SECRET', $__jwt_secret);

/*
 * Load TokenHandler ONCE. Its top line `require APPPATH.'/libraries/JWT.php'`
 * (a plain require, not require_once) pulls in the JWT class exactly once here,
 * so individual test files must NOT require JWT.php again — that would trigger
 * a "cannot redeclare class JWT" fatal. Both JWT and TokenHandler are available
 * globally to every test after this line.
 */
require_once APPPATH . 'libraries/TokenHandler.php';

/*
 * Local test database connection details. Override any of these via environment
 * variables in CI. Defaults match the local XAMPP install (root / no password),
 * NOT the app's own DB user (which only exists on the production server).
 */
defined('TEST_DB_HOST') or define('TEST_DB_HOST', getenv('TEST_DB_HOST') ?: '127.0.0.1');
defined('TEST_DB_NAME') or define('TEST_DB_NAME', getenv('TEST_DB_NAME') ?: 'myco_uk');
defined('TEST_DB_USER') or define('TEST_DB_USER', getenv('TEST_DB_USER') ?: 'root');
defined('TEST_DB_PASS') or define('TEST_DB_PASS', getenv('TEST_DB_PASS') ?: '');

/*
 * Base URL for HTTP integration/security tests. Points at the local XAMPP
 * install by default; set TEST_BASE_URL to run against another environment.
 * Tests auto-skip when this host is unreachable.
 */
defined('TEST_BASE_URL') or define('TEST_BASE_URL', getenv('TEST_BASE_URL') ?: 'http://localhost/MyCommcation');

require_once __DIR__ . '/_support/DbTestCase.php';
require_once __DIR__ . '/_support/HttpTestCase.php';
