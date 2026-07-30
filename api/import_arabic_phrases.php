<?php
/**
 * One-off: seed the `language` table's `arabic` column from languages/arabic.json.
 *
 * Why this is needed: get_phrase() (application/helpers/multi_language_helper.php)
 * reads translations from the `language` DB table, NOT from the shipped JSON
 * files. The vendor ships ~1,088 real Arabic phrases in languages/arabic.json
 * that were never imported, so the admin rendered English while a full Arabic
 * translation sat unused on disk.
 *
 * Worse, on a miss get_phrase() WRITES the humanised English key back into the
 * arabic column — so browsing the admin in Arabic silently fills the column
 * with English. This script also clears those poisoned rows (arabic == english)
 * so they can be retranslated rather than being treated as done.
 *
 * Safe to re-run. Back up the table first:
 *   mysqldump -u USER -p DB language > language.bak-<label>-<date>.sql
 *
 * Run:  php import_arabic_phrases.php
 * Delete this file once the client's translation workflow is settled.
 */

// CLI only. This file lives inside the web root and touches the database, so it
// must never be reachable over HTTP.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Credentials are read from CodeIgniter's own config rather than duplicated
// here — one place to rotate them, and no second copy of the password on disk.
define('BASEPATH', __DIR__ . '/system/');
defined('ENVIRONMENT') or define('ENVIRONMENT', 'production');
require __DIR__ . '/application/config/database.php';
$config = [
    'host' => $db['default']['hostname'],
    'user' => $db['default']['username'],
    'pass' => $db['default']['password'],
    'name' => $db['default']['database'],
];

$json = json_decode(file_get_contents(__DIR__ . '/languages/arabic.json'), true);
if (!is_array($json)) {
    fwrite(STDERR, "Could not read languages/arabic.json\n");
    exit(1);
}

$db = new mysqli($config['host'], $config['user'], $config['pass'], $config['name']);
if ($db->connect_error) {
    fwrite(STDERR, "DB connect failed: {$db->connect_error}\n");
    exit(1);
}
$db->set_charset('utf8mb4');

// 1. Clear rows the fallback poisoned with English, so they are visibly untranslated.
$cleared = 0;
if ($db->query("UPDATE language SET arabic = NULL WHERE arabic IS NOT NULL AND arabic = english")) {
    $cleared = $db->affected_rows;
}

// 2. Seed from the shipped JSON.
$stmt = $db->prepare("UPDATE language SET arabic = ? WHERE phrase = ?");
$imported = 0;
$skipped = 0;

foreach ($json as $phrase => $arabic) {
    $arabic = trim((string) $arabic);
    if ($arabic === '') { $skipped++; continue; }
    $stmt->bind_param('ss', $arabic, $phrase);
    $stmt->execute();
    if ($db->affected_rows > 0) $imported++;
}
$stmt->close();

$total = $db->query("SELECT COUNT(*) c FROM language")->fetch_assoc()['c'];
$done  = $db->query("SELECT COUNT(*) c FROM language WHERE arabic IS NOT NULL AND arabic <> '' AND arabic <> english")->fetch_assoc()['c'];

printf("cleared English-poisoned rows : %d\n", $cleared);
printf("phrases imported from JSON    : %d (skipped %d empty)\n", $imported, $skipped);
printf("translated now                : %d / %d (%.1f%%)\n", $done, $total, $total ? $done / $total * 100 : 0);

$db->close();
