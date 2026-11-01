<?php
/** Offline checks for TQ-LIVE phase 1: join leads, join window, display state, ICS. No DB.
 * Run: php tests/live_sessions_test.php
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

/** Settings rows the fake DB returns for `config()`. */
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

// ---- join_lead_min: defaults, new keys, legacy fallback ----------------
$settings = array();
$m = model();
check($m->join_lead_min('student') === 10, 'default student lead 10');
check($m->join_lead_min('teacher') === 15, 'default teacher lead 15');

$settings = array('tq_session_join_lead_min' => '30');
$m = model();
check($m->join_lead_min('student') === 30 && $m->join_lead_min('teacher') === 30, 'legacy key applies to both');

$settings = array('tq_session_join_lead_min' => '30', 'tq_session_join_lead_student' => '5',
                  'tq_session_join_lead_teacher' => '20');
$m = model();
check($m->join_lead_min('student') === 5 && $m->join_lead_min('teacher') === 20, 'new keys win over legacy');
check($m->join_lead_min('bogus') === 5, 'unknown actor reads as student');

// ---- join_state: T-10 student, T-15 teacher, closed after end+grace ----
$settings = array();
$m      = model();
$starts = strtotime('2026-11-10 17:00:00');
$row    = array('status' => 'confirmed', 'starts_at' => date('Y-m-d H:i:s', $starts),
                'duration_min' => 60, 'meet_url' => 'https://meet.google.com/abc-defg-hij');

check(!$m->join_state($row, $starts - 11 * 60, 'student')['can_join'], 'student closed at T-11');
check($m->join_state($row, $starts - 10 * 60, 'student')['can_join'], 'student open at T-10');
check($m->join_state($row, $starts - 15 * 60, 'teacher')['can_join'], 'teacher open at T-15');
check(!$m->join_state($row, $starts - 16 * 60, 'teacher')['can_join'], 'teacher closed at T-16');
check($m->join_state($row, $starts, 'student')['opens_at'] === $starts - 600, 'opens_at for student');
check($m->join_state($row, $starts, 'teacher')['opens_at'] === $starts - 900, 'opens_at for teacher');
$after = $starts + 60 * 60 + 6 * 3600 + 1;
check(!$m->join_state($row, $after, 'student')['can_join'] && $m->join_state($row, $after)['is_over'],
      'closed after end + grace');
$row2 = $row; $row2['meet_url'] = ''; $row2['teacher_meet_url'] = 'https://zoom.us/j/123';
check($m->join_state($row2, $starts, 'student')['can_join'], 'teacher default meet url opens the door');

// ---- display_state ----------------------------------------------------
$ds = function ($r, $now, $actor = 'student') use ($m) { return $m->display_state($r, $now, $actor)['key']; };
$future = date('Y-m-d H:i:s', time() + 3600);
$past   = date('Y-m-d H:i:s', time() - 60);

check($ds(array('status' => 'awaiting_payment', 'pay_deadline' => $future) + $row, time()) === 'pending_payment', 'pending_payment');
$d = $m->display_state(array('status' => 'awaiting_payment', 'pay_deadline' => $future) + $row, time());
check($d['deadline_ts'] === strtotime($future) && $d['button'] === 'pay', 'pending_payment deadline + pay button');
check($ds(array('status' => 'awaiting_payment', 'pay_deadline' => $future, 'last_attempt' => 'failed') + $row, time()) === 'payment_failed', 'payment_failed');
check($m->display_state(array('status' => 'awaiting_payment', 'last_attempt' => 'mismatch', 'pay_deadline' => $future) + $row, time())['button'] === 'retry_pay', 'retry button');
check($ds(array('status' => 'awaiting_payment', 'pay_deadline' => $past) + $row, time()) === 'expired', 'unpaid past deadline reads expired');
check($ds(array('status' => 'expired') + $row, time()) === 'expired', 'expired');
check($ds($row, $starts - 3600) === 'scheduled', 'scheduled before the window');
check($ds($row, $starts - 5 * 60) === 'ready', 'ready inside the window');
check($m->display_state($row, $starts - 5 * 60)['button'] === 'join', 'ready shows join');
check($ds($row, $starts - 12 * 60, 'teacher') === 'ready', 'teacher ready at T-12');
check($ds($row, $starts + 60) === 'live', 'confirmed past start reads live');
check($ds(array('status' => 'live') + $row, $starts - 60) === 'live', 'live');
check($ds(array('status' => 'completed') + $row, $starts + 7200) === 'completed', 'completed');
check($ds(array('status' => 'completed', 'recording_pending' => 1) + $row, $starts + 7200) === 'recording_processing', 'recording_processing');
check($ds(array('status' => 'completed', 'recording_url' => 'https://youtu.be/x') + $row, $starts + 7200) === 'recording_available', 'recording_available');
check($ds(array('status' => 'completed', 'attendance' => 'absent') + $row, $starts + 7200) === 'missed', 'missed');
check($ds(array('status' => 'completed', 'attended_at' => null) + $row, $starts + 7200) === 'completed', 'no attendance column: not missed');
check($ds(array('rescheduled_from' => 44) + $row, $starts - 3600) === 'rescheduled', 'rescheduled');
foreach (array('declined', 'refunded') as $s) {
    check($ds(array('status' => $s) + $row, time()) === 'cancelled', 'cancelled from ' . $s);
}
check($ds(array('status' => 'requested') + $row, $starts - 3600) === 'scheduled', 'requested reads scheduled');
$keys = array('key', 'label', 'tone', 'button', 'starts_ts', 'deadline_ts');
check(array_keys($m->display_state($row, time())) === $keys, 'display_state shape');

// ---- ics_for -----------------------------------------------------------
$ics = $m->ics_for(array('id' => 7, 'status' => 'awaiting_payment', 'paid_at' => null) + $row);
check(strpos($ics, "BEGIN:VCALENDAR\r\n") === 0, 'starts with VCALENDAR + CRLF');
check(strpos($ics, 'DTSTART:' . gmdate('Ymd\THis\Z', $starts)) !== false, 'UTC DTSTART');
check(strpos($ics, 'DTSTART:20261110T140000Z') !== false, 'Riyadh 17:00 is 14:00Z');
check(strpos($ics, 'UID:tq-session-7@taqdaredu.com') !== false, 'UID');
check(strpos($ics, 'meet.google.com') === false, 'no meet url when unpaid');
check(strpos(str_replace("\r\n", '', $ics), "\n") === false, 'every line ends CRLF');
$paid = $m->ics_for(array('id' => 7, 'status' => 'confirmed', 'paid_at' => '2026-11-01 10:00:00') + $row);
check(strpos($paid, 'meet.google.com') !== false, 'meet url once paid');
check(strpos($m->google_calendar_url(array('id' => 7) + $row), 'https://calendar.google.com/calendar/render?action=TEMPLATE') === 0,
      'google calendar url');

// ---- D2-b: no-link alert predicate ------------------------------------
$now0 = $starts - 30 * 60;
$bare = array('status' => 'confirmed', 'meet_url' => '', 'teacher_meet_url' => '') + $row;
check($m->nolink_alert_due($bare, $now0), 'due: confirmed, no link, starts within the hour');
check(!$m->nolink_alert_due($bare, $starts - 2 * 3600), 'not due two hours out');
check(!$m->nolink_alert_due($bare, $starts + 60), 'not due once started');
check(!$m->nolink_alert_due(array('nolink_alerted_at' => '2026-11-10 16:20:00') + $bare, $now0), 'once only');
check(!$m->nolink_alert_due(array('teacher_meet_url' => 'https://zoom.us/j/1') + $bare, $now0), 'teacher link covers it');
check(!$m->nolink_alert_due($row, $now0), 'own link covers it');
check(!$m->nolink_alert_due(array('status' => 'awaiting_payment') + $bare, $now0), 'unpaid not alerted');

// ---- ENUM extension is additive ------------------------------------------
check(Taqdar_sessions_model::enum_with_closed("enum('open','held','booked')") === "enum('open','held','booked','closed')",
      'closed appended, order kept');
check(Taqdar_sessions_model::enum_with_closed("enum('open','closed')") === null, 'already there: no change');
check(Taqdar_sessions_model::enum_with_closed('varchar(16)') === null, 'not an enum: no change');

// ---- Fix round: instant gate, cleaned teacher link, hold notice ---------
check(Taqdar_sessions_model::instant_allowed(true, false, 12000, true), 'instant: on, priced, single, card payable');
check(!Taqdar_sessions_model::instant_allowed(true, false, 12000, false), 'no card for this client: old flow');
check(!Taqdar_sessions_model::instant_allowed(false, false, 12000, true), 'switch off: old flow');
check(!Taqdar_sessions_model::instant_allowed(true, true, 12000, true), 'pack booking: old flow');
check(!Taqdar_sessions_model::instant_allowed(true, false, 0, true), 'free: old flow');
$evil = array('meet_url' => '', 'teacher_meet_url' => 'http://evil.example/room') + $row;
check(!$m->join_state($evil, $starts, 'student')['can_join'], 'invalid legacy teacher link does not open the door');
check(!Taqdar_sessions_model::hold_notifies_teacher(array('confirmed_at' => null)), 'instant hold: teacher never told');
check(Taqdar_sessions_model::hold_notifies_teacher(array('confirmed_at' => '2026-11-01 10:00:00')), 'confirmed then unpaid: teacher told');

echo "OK — $checks checks\n";
