<?php
/** Offline checks for TQ-GROUP (group live sessions engine): seats, offer reasons, teacher
 * percent, join windows, display state, credit split. No DB — every DB-backed method of
 * Taqdar_group_model takes an injectable context, and these checks drive the pure core.
 * Run: php tests/live_groups_test.php
 */
define('BASEPATH', __DIR__);
class CI_Model {}
$settings = array();
function get_settings($key) { global $settings; return array_key_exists($key, $settings) ? $settings[$key] : null; }
function t($ar, $args = null) {
    foreach ((array) $args as $a) $ar = preg_replace('/____/', (string) $a, $ar, 1);
    return $ar;
}
require dirname(__DIR__) . '/application/models/Taqdar_group_model.php';
function model() { return (new ReflectionClass('Taqdar_group_model'))->newInstanceWithoutConstructor(); }
$checks = 0;
function check($condition, $label) {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
$now = strtotime('2026-11-10 12:00:00');
$at  = function ($ts) { return date('Y-m-d H:i:s', $ts); };

// ---- Seats: capacity 0 = unlimited; live pending within hold counts; expired pending does not.
$rows = array(
    array('id' => 1, 'status' => 'active',    'created_at' => $at($now - 86400), 'ends_at' => null),
    array('id' => 2, 'status' => 'active',    'created_at' => $at($now - 86400), 'ends_at' => $at($now + 86400)),
    array('id' => 3, 'status' => 'pending',   'created_at' => $at($now - 3600),  'ends_at' => null),   // inside 2h hold
    array('id' => 4, 'status' => 'pending',   'created_at' => $at($now - 3 * 3600), 'ends_at' => null), // hold expired
    array('id' => 5, 'status' => 'active',    'created_at' => $at($now - 99 * 86400), 'ends_at' => $at($now - 60)), // lapsed
    array('id' => 6, 'status' => 'cancelled', 'created_at' => $at($now - 86400), 'ends_at' => null),  // cancelled before pay
    array('id' => 7, 'status' => 'cancelled', 'created_at' => $at($now - 86400), 'ends_at' => $at($now + 600)), // paid, runs out later
    array('id' => 8, 'status' => 'expired',   'created_at' => $at($now - 86400), 'ends_at' => $at($now - 60)),
);
check(Taqdar_group_model::count_seats($rows, $now, 2) === 4, 'active x2 + live pending + paid-cancelled = 4');
check(Taqdar_group_model::count_seats($rows, $now, 4) === 5, 'longer hold keeps the 3h pending');
check(Taqdar_group_model::count_seats($rows, $now, 2, 3) === 2, 'rank recount: only rows before id 3 count');
check(Taqdar_group_model::seats_left_of(array('capacity' => 0), 99) === null, 'capacity 0 = unlimited');
check(Taqdar_group_model::seats_left_of(array('capacity' => 5), 4) === 1, 'one seat left');
check(Taqdar_group_model::seats_left_of(array('capacity' => 5), 7) === 0, 'never negative');

// ---- teacher_percent_of: NULL -> setting (default 60), 0 -> 0 by decision.
$m = model();
check($m->teacher_percent_of(array('teacher_percent' => null)) === 60.0, 'NULL takes the default 60');
check($m->teacher_percent_of(array('teacher_percent' => '')) === 60.0, 'empty takes the default');
check($m->teacher_percent_of(array('teacher_percent' => '0')) === 0.0, 'zero is zero, not the default');
check($m->teacher_percent_of(array('teacher_percent' => '72.5')) === 72.5, 'explicit percent');
$settings['tq_group_teacher_percent'] = '55';
check($m->teacher_percent_of(array('teacher_percent' => null)) === 55.0, 'NULL takes the setting');
$settings = array();

// ---- offer() reasons.
$g = array('id' => 9, 'status' => 'published', 'price' => '15000', 'capacity' => 3, 'teacher_id' => 7,
           'teacher_percent' => null, 'enroll_until' => null, 'title' => 'G');
$ctx = array('enabled' => true, 'seats_taken' => 1, 'member' => false, 'now' => $now);
$o = $m->offer($g, 0, $ctx);
check($o['sellable'] && $o['reason'] === 'ok', 'sellable published group');
check($o['price_halalas'] === 15000 && $o['list_halalas'] === 15000, 'price in halalas');
check($o['seats_left'] === 2 && $o['teacher_percent'] === 60.0, 'seats and percent in offer');
foreach (array('sellable','reason','why','price_halalas','list_halalas','seats_left','teacher_percent') as $k) {
    check(array_key_exists($k, $o), 'offer key ' . $k);
}
check($m->offer($g, 0, array('enabled' => false) + $ctx)['reason'] === 'disabled', 'switch off: disabled');
check($m->offer(array('status' => 'draft') + $g, 0, $ctx)['reason'] === 'draft', 'draft not sold');
check($m->offer(array('status' => 'review') + $g, 0, $ctx)['reason'] === 'draft', 'review not sold');
check($m->offer(array('status' => 'closed') + $g, 0, $ctx)['reason'] === 'closed', 'closed not sold');
check($m->offer($g, 0, array('seats_taken' => 3) + $ctx)['reason'] === 'full', 'full');
check($m->offer(array('capacity' => 0) + $g, 0, array('seats_taken' => 300) + $ctx)['sellable'], 'unlimited never full');
check($m->offer(array('enroll_until' => $at($now - 1)) + $g, 0, $ctx)['reason'] === 'enroll_closed', 'enroll_until passed');
check($m->offer(array('enroll_until' => $at($now + 3600)) + $g, 0, $ctx)['sellable'], 'enroll_until ahead');
check($m->offer($g, 5, array('member' => true) + $ctx)['reason'] === 'member', 'already member');
check($m->offer(array('price' => '0') + $g, 0, $ctx)['reason'] === 'no_price', 'no price, no sale');
check(!$m->offer($g, 0, array('enabled' => false) + $ctx)['sellable'], 'unsellable flag');
check($m->offer($g, 0, array('enabled' => false) + $ctx)['why'] !== '', 'why is said');

// ---- Join windows 10 / 15 and the legacy fallback.
check(Taqdar_group_model::lead_of(array(), 'student') === 10, 'student default 10');
check(Taqdar_group_model::lead_of(array(), 'teacher') === 15, 'teacher default 15');
check(Taqdar_group_model::lead_of(array('tq_session_join_lead_min' => '30'), 'student') === 30, 'legacy for student');
check(Taqdar_group_model::lead_of(array('tq_session_join_lead_min' => '30'), 'teacher') === 30, 'legacy for teacher');
check(Taqdar_group_model::lead_of(array('tq_session_join_lead_min' => '30', 'tq_session_join_lead_student' => '5'), 'student') === 5, 'new key wins');
check(Taqdar_group_model::lead_of(array('tq_session_join_lead_teacher' => '20'), 'teacher') === 20, 'teacher key');

// ---- display_state over time, member vs non-member vs teacher.
$start = $now + 3600;
$gs = array('id' => 1, 'group_id' => 9, 'status' => 'scheduled', 'starts_at' => $at($start), 'duration_min' => 60,
            'meet_url' => 'https://meet.example/x', 'recording_url' => '', 'recording_pending' => 0,
            'rescheduled_from_at' => null);
$base = array('grace_hours' => 6, 'lead_student' => 10, 'lead_teacher' => 15, 'member' => true, 'attendance' => null);
$st = function ($row, $when, $actor = 'student', $extra = array()) use ($m, $base) {
    return $m->display_state($row, 5, $actor, array('now' => $when) + $extra + $base);
};
$keys = array('scheduled','ready','live','completed','recording_processing','recording_available','missed','rescheduled','cancelled');
check($st($gs, $start - 11 * 60)['state'] === 'scheduled', 'student 11 min before: scheduled');
check(!$st($gs, $start - 11 * 60)['can_join'], 'student 11 min before: no join');
check($st($gs, $start - 10 * 60)['state'] === 'ready' && $st($gs, $start - 10 * 60)['can_join'], 'student at 10: ready + join');
check($st($gs, $start - 14 * 60, 'teacher')['can_join'], 'teacher at 14: join');
check(!$st($gs, $start - 16 * 60, 'teacher')['can_join'], 'teacher at 16: not yet');
$nm = $st($gs, $start - 5 * 60, 'student', array('member' => false));
check($nm['state'] === 'ready' && !$nm['can_join'], 'non-member never joins');
check($st($gs, $start + 60)['state'] === 'live', 'started: live');
check($st(array('status' => 'live') + $gs, $start - 5 * 60)['state'] === 'live', 'teacher in early: live');
check($st($gs, $start + 7 * 3600 + 60, 'teacher')['state'] === 'completed' && $st($gs, $start + 7 * 3600 + 60)['state'] === 'missed' && !$st($gs, $start + 8 * 3600)['can_join'], 'past grace: completed, closed');
$done = array('status' => 'completed') + $gs;
check($st($done, $start + 7200, 'student', array('attendance' => 'present'))['state'] === 'completed', 'attended: completed');
check($st($done, $start + 7200, 'student', array('attendance' => 'absent'))['state'] === 'missed', 'absent: missed');
check($st($done, $start + 7200, 'student', array('attendance' => null))['state'] === 'missed', 'no row: missed');
check($st($done, $start + 7200, 'student', array('member' => false))['state'] === 'completed', 'non-member never missed');
check($st($done, $start + 7200, 'teacher')['state'] === 'completed', 'teacher never missed');
check($st(array('recording_pending' => 1) + $done, $start + 7200)['state'] === 'recording_processing', 'recording pending');
check($st(array('recording_url' => 'https://youtu.be/x') + $done, $start + 7200)['state'] === 'recording_available', 'recording ready');
check($st(array('status' => 'cancelled') + $gs, $start - 3600)['state'] === 'cancelled', 'cancelled');
$rs = array('rescheduled_from_at' => $at($start - 86400)) + $gs;
check($st($rs, $start - 3600)['state'] === 'rescheduled', 'rescheduled before new time');
check($st($rs, $start - 5 * 60)['state'] === 'ready', 'rescheduled turns ready in window');
$all = array();
foreach (array(array($gs, $start - 3600, array()), array($gs, $start - 300, array()), array($gs, $start + 60, array()),
               array($done, $start + 7200, array('attendance' => 'present')), array($done, $start + 7200, array()),
               array(array('recording_pending' => 1) + $done, $start + 7200, array('attendance' => 'present')),
               array(array('recording_url' => 'u') + $done, $start + 7200, array()),
               array($rs, $start - 3600, array()), array(array('status' => 'cancelled') + $gs, $now, array())) as $c) {
    $s = $st($c[0], $c[1], 'student', $c[2]);
    check(in_array($s['state'], $keys, true), 'state in vocabulary: ' . $s['state']);
    foreach (array('state','label','tone','can_join','note','starts_ts','opens_ts') as $k) check(array_key_exists($k, $s), 'display key ' . $k);
    $all[$s['state']] = 1;
}
check(count($all) === count($keys), 'all nine states reachable');

// ---- Credit split sums to the price exactly, every percent, odd amounts.
foreach (array(0, 1, 33.33, 50, 60, 66.67, 99.99, 100) as $p) {
    foreach (array(1, 7, 9999, 15000, 39900, 12345) as $gross) {
        $s = Taqdar_group_model::split($gross, $p);
        check($s['share'] + $s['cut'] === $gross && $s['share'] >= 0 && $s['cut'] >= 0, "split $gross @ $p");
    }
}
check(Taqdar_group_model::split(15000, 60)['share'] === 9000, '60% of 150 SAR');

// ---- Fix round 1: ENUM guard (pure), over-capacity on late activation, riyal -> halala.
$cur = "enum('all','subject','path','course','trial','grade','book','foundation')";
$new = Taqdar_group_model::enum_with($cur, 'group');
check($new === "ENUM('all','subject','path','course','trial','grade','book','foundation','group')", 'group appended, all kept');
check(Taqdar_group_model::enum_with($new, 'group') === null, 'already present: no ALTER');
check(Taqdar_group_model::enum_with("enum('all','group','zz')", 'group') === null, 'values added by others kept, no ALTER');
check(Taqdar_group_model::enum_with('varchar(20)', 'group') === null, 'not an enum: leave it');
check(Taqdar_group_model::enum_with("enum('a''b')", 'group') === "ENUM('a''b','group')", 'quotes survive');

$cap2 = array('id' => 9, 'capacity' => 2);
$late = array(
    array('id' => 20, 'status' => 'active',  'created_at' => $at($now - 86400), 'ends_at' => null),
    array('id' => 21, 'status' => 'active',  'created_at' => $at($now - 86400), 'ends_at' => null),
    array('id' => 22, 'status' => 'pending', 'created_at' => $at($now - 5 * 3600), 'ends_at' => null), // hold lapsed, now paying
);
check(Taqdar_group_model::over_capacity($cap2, $late, $now, 2, 22), 'late payment over capacity is flagged');
check(!Taqdar_group_model::over_capacity($cap2, array_slice($late, 1), $now, 2, 22), 'room left: not flagged');
check(!Taqdar_group_model::over_capacity(array('capacity' => 0) + $cap2, $late, $now, 2, 22), 'unlimited never over');
check(!Taqdar_group_model::over_capacity($cap2, array($late[0], $late[2]), $now, 2, 22), 'own row not counted');

check(Taqdar_group_model::to_halalas('149.99') === 14999, 'riyal with halalas kept');
check(Taqdar_group_model::to_halalas('1,250.5') === 125050, 'thousands separator');
check(Taqdar_group_model::to_halalas('0.1') === 10, 'float rounding');
check(Taqdar_group_model::to_halalas('') === 0 && Taqdar_group_model::to_halalas('-5') === 0, 'empty/negative');
check($m->offer(array('price' => 14999) + $g, 0, $ctx)['price_halalas'] === 14999, 'offer reads halalas as stored');

echo "OK {$checks} checks\n";
