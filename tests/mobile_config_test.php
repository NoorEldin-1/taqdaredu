<?php
/** Offline checks for TQ-APP-CONFIG: version parsing, the review build and the payment policy. No DB.
 * Run: php tests/mobile_config_test.php
 */
define('BASEPATH', __DIR__);
class CI_Model {}
$settings = array();
function get_settings($key) { global $settings; return array_key_exists($key, $settings) ? $settings[$key] : null; }
function t($ar, $args = null) {
    foreach ((array) $args as $a) $ar = preg_replace('/____/', (string) $a, $ar, 1);
    return $ar;
}
require dirname(__DIR__) . '/application/models/Taqdar_mobile_model.php';
function model() { return (new ReflectionClass('Taqdar_mobile_model'))->newInstanceWithoutConstructor(); }
$checks = 0;
function check($condition, $label) {
    global $checks;
    if (!$condition) throw new RuntimeException($label);
    $checks++;
}
function methods($platform, $version, $card = true) {
    $m = model();
    return $m->policy($m->client($platform, $version), $card)['methods'];
}

// Versions as the app sends them, as App Store Connect shows them, and as an admin types them.
$v = Taqdar_mobile_model::parse_version('1.2.4+9');
check($v['v'] === '1.2.4' && $v['b'] === '9', 'app form version+build');
check(Taqdar_mobile_model::parse_version('1.2.4 (9)')['b'] === '9', 'App Store Connect form');
check(Taqdar_mobile_model::parse_version(' 1.2.4 ')['b'] === '', 'bare version, no build');
check(Taqdar_mobile_model::parse_version('1.0')['c'] === Taqdar_mobile_model::parse_version('1.0.0')['c'], '1.0 equals 1.0.0');
foreach (array('', 'abc', '1..2', '1.2.4-beta', '1.2.4+') as $bad) {
    check(Taqdar_mobile_model::parse_version($bad) === null, 'rejects ' . var_export($bad, true));
}

// No settings at all: every build pays exactly as before this screen existed.
check(methods('ios', '1.2.4+9') === array('tap', 'manual'), 'no settings: card and bank');
check(methods('ios', '1.2.4+9', false) === array('manual'), 'no settings, card off: bank only');

// A version under review pays cash only; nobody else changes.
$settings['tq_mobile_ios_review'] = '1.2.4';
check(methods('ios', '1.2.4+9') === array('cash'), 'review build: cash only');
check(methods('ios', '1.2.4+10') === array('cash'), 'review version without a build matches every build');
check(methods('ios', '1.2.3+8') === array('tap', 'manual'), 'older iOS build unaffected');
check(methods('android', '1.2.4+9') === array('tap', 'manual'), 'Android with the same number unaffected');
check(methods('', '') === array('tap', 'manual'), 'unidentified client unaffected');
$m = model();
$p = $m->policy($m->client('ios', '1.2.4+9'), true);
check($p['review'] && !$p['card'] && !$p['bank'] && $p['cash'], 'review build: no card, no bank details');

$settings['tq_mobile_ios_review'] = '1.2.4 (9)';
check(methods('ios', '1.2.4+9') === array('cash'), 'review build pinned to build 9');
check(methods('ios', '1.2.4+10') === array('tap', 'manual'), 'another build of the same version unaffected');

$settings['tq_mobile_review_pay'] = 'none';
check(methods('ios', '1.2.4+9') === array(), 'no payment at all');
$settings['tq_mobile_review_pay'] = 'bogus';
check(methods('ios', '1.2.4+9') === array('cash'), 'unknown review payment falls back to cash');

// Update and close: numeric comparison, per platform.
$settings = array('tq_mobile_ios_version' => '1.10.0', 'tq_mobile_ios_force' => '1');
$m = model();
$s = $m->app_state($m->client('ios', '1.9.5+3'), true);
check($s['update_available'] && $s['update_required'], '1.9.5 is older than 1.10.0');
$s = $m->app_state($m->client('ios', '1.10+4'), true);
check(!$s['update_available'] && !$s['update_required'], '1.10 is 1.10.0');
$s = $m->app_state($m->client('android', '1.0.0'), true);
check($s['latest_version'] === null && !$s['update_required'], 'Android has its own settings');
check($s['force_close'] === false, 'not closed by default');
$settings['tq_mobile_force_close'] = '1';
$m = model();
check($m->app_state($m->client('android', '1.0.0'), true)['force_close'], 'close applies to every platform');

// Saving refuses what it cannot read, before touching the database.
$r = model()->save(array('tq_mobile_ios_version' => '1.2.x'));
check(!$r['ok'] && count($r['errors']) === 1, 'unreadable version refused');
$r = model()->save(array('tq_mobile_android_force' => '1'));
check(!$r['ok'], 'forced update without a version refused');
$r = model()->save(array('tq_mobile_ios_review' => 'next'));
check(!$r['ok'], 'unreadable review version refused');

echo "OK ($checks checks)\n";
