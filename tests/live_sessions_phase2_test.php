<?php
/** Offline checks for TQ-LIVE task 2: reschedule rules, refund rule, teacher absence,
 * recording/attendance follow-ups, display_state with the v9 columns. No DB.
 * Run: php tests/live_sessions_phase2_test.php
 */
define('BASEPATH', __DIR__);
date_default_timezone_set('Asia/Riyadh');
class CI_Model {}
function t($ar, $args = null) {
    foreach ((array) $args as $a) $ar = preg_replace('/____/', (string) $a, $ar, 1);
    return $ar;
}
function tq_t_deep($x) { return $x; }
function log_message() {}

$settings = array();
class FakeDb {
    public function __call($name, $args) { return $this; }
    public function result_array() {
        global $settings;
        $out = array();
        foreach ($settings as $k => $v) $out[] = array('key' => $k, 'value' => $v);
        return $out;
    }
    public function row_array() { return null; }
}

require dirname(__DIR__) . '/application/models/Taqdar_sessions_model.php';

function model() {
    $m = (new ReflectionClass('Taqdar_sessions_model'))->newInstanceWithoutConstructor();
    $m->db = new FakeDb();
    $m->config(true);
    return $m;
}
$checks = 0;
function check($condition, $label) {
    global $checks;
    if (!$condition) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    $checks++;
}

$S = 'Taqdar_sessions_model';
$starts = strtotime('2026-11-10 17:00:00');
$H = 3600;

// ---- schema + config ------------------------------------------------------
check($S::SCHEMA_V === '9', 'schema version 9');
$settings = array();
$m = model();
check($m->config()['reschedule_hours'] === 12, 'default reschedule window 12h');
check($m->config()['refund_hours'] === 24, 'default refund window 24h');
$settings = array('tq_session_reschedule_hours' => '6');
$m = model();
check($m->config()['reschedule_hours'] === 6, 'reschedule window from settings');
$settings = array();
$m = model();

// ---- reschedule_block: roles, window, count, status ------------------------
check($S::reschedule_block('student', 'confirmed', $starts, $starts - 13 * $H, 0, 12) === '', 'student 13h before, first time: ok');
check($S::reschedule_block('student', 'confirmed', $starts, $starts - 12 * $H, 0, 12) === '', 'student exactly 12h: ok');
check($S::reschedule_block('student', 'confirmed', $starts, $starts - 11 * $H, 0, 12) === 'window', 'student 11h before: window');
check($S::reschedule_block('student', 'confirmed', $starts, $starts - 48 * $H, 1, 12) === 'count', 'student second time: count');
check($S::reschedule_block('student', 'awaiting_payment', $starts, $starts - 48 * $H, 0, 12) === '', 'student unpaid: ok');
check($S::reschedule_block('teacher', 'confirmed', $starts, $starts - 60, 3, 12) === '', 'teacher any time, any count');
check($S::reschedule_block('admin', 'confirmed', $starts, $starts - 60, 9, 12) === '', 'admin any time');
check($S::reschedule_block('teacher', 'confirmed', $starts, $starts + 60, 0, 12) === 'past', 'nobody moves a started session');
foreach (array('requested', 'live', 'completed', 'declined', 'expired', 'refunded') as $st) {
    check($S::reschedule_block('admin', $st, $starts, $starts - 48 * $H, 0, 12) === 'status', 'status blocks: ' . $st);
}
check($S::reschedule_block('student', 'confirmed', 0, $starts, 0, 12) === 'past', 'no slot time: blocked');

// ---- same_scope ------------------------------------------------------------
$old = array('teacher_id' => 5, 'kind' => 'curriculum', 'track_id' => 0, 'grade_id' => 3, 'subject_id' => 7);
check($S::same_scope($old, $old), 'identical scope');
check(!$S::same_scope($old, array('teacher_id' => 6) + $old), 'other teacher refused');
check(!$S::same_scope($old, array('grade_id' => 4) + $old), 'other grade refused');
check(!$S::same_scope($old, array('subject_id' => 8) + $old), 'other subject refused');
check($S::same_scope($old, array('grade_id' => 0, 'subject_id' => 0) + $old), 'open-to-all slot accepted');
check(!$S::same_scope($old, array('kind' => 'foundation') + $old), 'other kind refused');
$f = array('teacher_id' => 5, 'kind' => 'foundation', 'track_id' => 2, 'grade_id' => 0, 'subject_id' => 0);
check($S::same_scope($f, $f) && !$S::same_scope($f, array('track_id' => 3) + $f), 'foundation: same track only');

// ---- refund_decision (D5-b) ------------------------------------------------
check($S::refund_decision($starts, $starts - 25 * $H, 24, true, false) === 'refund', '25h before: refund');
check($S::refund_decision($starts, $starts - 24 * $H, 24, true, false) === 'refund', 'exactly 24h: refund');
check($S::refund_decision($starts, $starts - 23 * $H, 24, true, false) === 'no_refund', '23h before: no refund');
check($S::refund_decision($starts, $starts - 48 * $H, 24, true, true) === 'pack', 'pack sessions unchanged');
check($S::refund_decision($starts, $starts - 48 * $H, 24, false, false) === 'unpaid', 'unpaid: nothing to refund');

// ---- absence_stage (D10) ---------------------------------------------------
$live = array('status' => 'live', 'starts_at' => date('Y-m-d H:i:s', $starts), 'duration_min' => 60,
              'meet_url' => 'https://meet.google.com/abc-defg-hij', 'teacher_joined_at' => null);
check($S::absence_stage($live, $starts + 9 * 60) === 0, '+9 min: nothing');
check($S::absence_stage($live, $starts + 10 * 60) === 1, '+10 min: late');
check($S::absence_stage($live, $starts + 20 * 60) === 2, '+20 min: no-show');
check($S::absence_stage(array('teacher_joined_at' => '2026-11-10 16:55:00') + $live, $starts + 30 * 60) === 0, 'teacher joined: nothing');
check($S::absence_stage(array('status' => 'confirmed') + $live, $starts + 25 * 60) === 2, 'confirmed counts too');
check($S::absence_stage(array('status' => 'completed') + $live, $starts + 25 * 60) === 0, 'completed: nothing');
check($S::absence_stage($live, $starts + 61 * 60 + 6 * $H + 1) === 0, 'after the session closed: nothing');

// ---- display_state with v9 columns ----------------------------------------
$d = $m->display_state($live, $starts + 21 * 60, 'student');
check($d['key'] === 'live' && $d['button'] === 'teacher_no_show', 'student sees teacher_no_show at +21');
check($m->display_state($live, $starts + 21 * 60, 'teacher')['button'] !== 'teacher_no_show', 'teacher never sees it');
check($m->display_state($live, $starts + 11 * 60, 'student')['button'] === 'join', '+11 min: still join');
check($m->display_state(array('teacher_joined_at' => '2026-11-10 16:50:00') + $live, $starts + 25 * 60, 'student')['button'] === 'join',
      'teacher came: join');
$choice = array('status' => 'declined', 'refund_state' => 'choice') + $live;
$d = $m->display_state($choice, $starts - 3600, 'student');
check($d['key'] === 'cancelled' && $d['button'] === 'teacher_fault', 'teacher fault: student picks');
check($m->display_state($choice, $starts - 3600, 'teacher')['button'] === '', 'teacher fault: no button for the teacher');
$d = $m->display_state(array('status' => 'refunded', 'refund_state' => 'pending') + $live, time(), 'student');
check($d['key'] === 'cancelled' && strpos($d['label'], '5') !== false, 'refund pending says five working days');
check($m->display_state(array('status' => 'completed', 'attendance' => 'present', 'recording_url' => 'https://youtu.be/x') + $live,
      $starts + 7200)['key'] === 'recording_available', 'present + recording');
check($m->display_state(array('status' => 'completed', 'attendance' => 'absent', 'recording_url' => 'https://youtu.be/x') + $live,
      $starts + 7200)['key'] === 'missed', 'absent wins');
$keys = array('key', 'label', 'tone', 'button', 'starts_ts', 'deadline_ts');
check(array_keys($m->display_state($choice, time())) === $keys, 'display_state shape unchanged');

// ---- recording + attendance follow-ups (D3, D4) ---------------------------
$done = array('status' => 'completed', 'completed_at' => date('Y-m-d H:i:s', $starts + 3600),
              'recording_pending' => 1, 'recording_url' => null, 'recording_reminded_at' => null);
$c0 = $starts + 3600;
check($S::recording_step($done, $c0 + 47 * $H) === '', 'before 48h: nothing');
check($S::recording_step($done, $c0 + 48 * $H) === 'remind', '48h: remind');
check($S::recording_step(array('recording_reminded_at' => '2026-11-12 18:00:00') + $done, $c0 + 50 * $H) === '', 'remind once');
check($S::recording_step($done, $c0 + 7 * 24 * $H) === 'drop', '7 days: drop');
check($S::recording_step(array('recording_url' => 'https://youtu.be/x') + $done, $c0 + 8 * 24 * $H) === '', 'has url: nothing');
check($S::recording_step(array('recording_pending' => 0) + $done, $c0 + 8 * 24 * $H) === '', 'not pending: nothing');

$att = array('status' => 'completed', 'completed_at' => date('Y-m-d H:i:s', $c0), 'attendance' => null,
             'attended_at' => '2026-11-10 16:58:00', 'teacher_joined_at' => '2026-11-10 16:55:00');
check($S::attendance_final($att, $c0 + 23 * $H) === null, 'teacher may still correct before 24h');
check($S::attendance_final($att, $c0 + 24 * $H) === 'present', 'after 24h: join click stands as present');
check($S::attendance_final(array('attended_at' => null) + $att, $c0 + 25 * $H) === 'absent', 'no click, tracked era: absent');
check($S::attendance_final(array('attended_at' => null, 'teacher_joined_at' => null) + $att, $c0 + 25 * $H) === null,
      'no tracking at all: left unknown');
check($S::attendance_final(array('attendance' => 'present') + $att, $c0 + 30 * $H) === null, 'already set: untouched');

// ---- clean attendance input ------------------------------------------------
check($S::clean_attendance('present') === 'present' && $S::clean_attendance('absent') === 'absent', 'attendance values');
check($S::clean_attendance('x') === null && $S::clean_attendance('') === null, 'unknown attendance: null');

// ---- recording host whitelist (D4) -----------------------------------------
check($S::recording_host_ok('https://youtu.be/abc') && $S::recording_host_ok('https://www.youtube.com/watch?v=abc'), 'youtube ok');
check($S::recording_host_ok('https://vimeo.com/123456/abcd') && $S::recording_host_ok('https://drive.google.com/file/d/x/view'), 'vimeo + drive ok');
check(!$S::recording_host_ok('http://youtu.be/abc'), 'http refused');
check(!$S::recording_host_ok('https://evil.com/youtube.com') && !$S::recording_host_ok('https://notyoutube.com/x'), 'look-alike hosts refused');
check(!$S::recording_host_ok('javascript:alert(1)'), 'script refused');

echo "OK — $checks checks\n";
