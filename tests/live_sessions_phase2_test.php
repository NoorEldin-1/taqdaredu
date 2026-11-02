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
check($d['key'] === 'cancelled' && strpos($d['hint'], '5') !== false && mb_strlen($d['label']) < 25, 'refund pending: short badge, five working days in the hint');
check($m->display_state(array('status' => 'completed', 'attendance' => 'present', 'recording_url' => 'https://youtu.be/x') + $live,
      $starts + 7200)['key'] === 'recording_available', 'present + recording');
check($m->display_state(array('status' => 'completed', 'attendance' => 'absent', 'recording_url' => 'https://youtu.be/x') + $live,
      $starts + 7200)['key'] === 'missed', 'absent wins');
$keys = array('key', 'label', 'tone', 'button', 'hint', 'starts_ts', 'deadline_ts');
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

// ---- fix round 1 -----------------------------------------------------------
// #5 attendance editable strictly within 24h of completion, set or not
$cmp = array('status' => 'completed', 'completed_at' => date('Y-m-d H:i:s', $c0), 'attendance' => null);
check($S::attendance_editable($cmp, $c0 + 23 * $H) === true, 'attendance editable before 24h');
check($S::attendance_editable($cmp, $c0 + 25 * $H) === false, 'attendance locked after 24h even when unset');
check($S::attendance_editable(array('attendance' => 'present') + $cmp, $c0 + 25 * $H) === false, 'locked after 24h when set');
// #6 auto-complete never credits an absent teacher or a session under review
check($S::may_auto_credit(array('teacher_absent_at' => null, 'refund_state' => null)) === true, 'normal: credit');
check($S::may_auto_credit(array('teacher_absent_at' => '2026-11-10 17:10:00', 'teacher_joined_at' => null)) === false, 'absent teacher: no credit');
check($S::may_auto_credit(array('teacher_absent_at' => '2026-11-10 17:10:00', 'teacher_joined_at' => '2026-11-10 17:12:00')) === true, 'late but joined: credit');
check($S::may_auto_credit(array('refund_state' => 'review')) === false, 'under review: no credit');
// #2 no-show refund goes to admin review, no refund promise
$rev = array('status' => 'declined', 'refund_state' => 'review') + $live;
$d = $m->display_state($rev, $starts + 3600, 'student');
check($d['key'] === 'cancelled' && $d['button'] === '' && strpos($d['label'], 'مراجعة') !== false, 'review: under admin review, no button');
check(strpos($d['label'], 'يرد') === false, 'review: no refund promise');

// ---- fix round 2: refund is atomic or compensated --------------------------
class ScriptDb {
    public $tbl = ''; public $wheres = array(); public $updates = array(); public $resets = 0;
    public $session = null; public $throw_on = array(); public $aff = 1; public $last_aff = 1;
    public function __call($name, $args) {
        if (in_array($name, array('get', 'from', 'count_all_results'), true) && isset($args[0])) $this->tbl = (string) $args[0];
        if ($name === 'count_all_results') { $this->wheres = array(); return 0; }
        if (in_array($name, array('where', 'where_in'), true)) $this->wheres[] = $args[0];
        if ($name === 'table_exists') return false;
        return $this;
    }
    public function update($t, $d) {
        $w = $this->wheres; $this->wheres = array();
        if (in_array($t, $this->throw_on, true)) { throw new RuntimeException('boom ' . $t); }
        $this->updates[] = array($t, $d, $w);
        $this->last_aff = $this->aff;
        return true;
    }
    public function affected_rows() { return $this->last_aff; }
    public function reset_query() { $this->resets++; $this->wheres = array(); return $this; }
    public function insert() { return true; }
    public function row_array() {
        if (strpos($this->tbl, 'tutoring_sessions') !== false) return $this->session;
        if ($this->tbl === 'invoices') return array('status' => 'unpaid');
        return null;
    }
    public function result_array() { return $this->tbl === 'users' ? array(array('id' => 1)) : array(); }
}
class FakeLoad { public function model() {} }
class FakeWallet { public $throw = false; public $calls = 0;
    public function reverse_session() { $this->calls++; if ($this->throw) throw new RuntimeException('wallet down'); return true; } }
class FakeAdmin { public $sent = array();
    public function push_notification($uid, $title, $text) { $this->sent[] = array($uid, $title, $text); } }
function smodel($session, $opts = array()) {
    $m = (new ReflectionClass('Taqdar_sessions_model'))->newInstanceWithoutConstructor();
    $db = new ScriptDb(); $db->session = $session; $db->throw_on = $opts['throw_on'] ?? array();
    $m->db = $db; $m->load = new FakeLoad(); $m->input = new class { public function is_cli_request() { return true; } };
    $m->taqdar_wallet_model = new FakeWallet(); $m->taqdar_wallet_model->throw = !empty($opts['wallet_throw']);
    $m->taqdar_admin_model = new FakeAdmin();
    $m->install_schema();
    $m->config(true);
    return $m;
}
function ups($m, $table) { return array_values(array_filter($m->db->updates, function ($u) use ($table) { return $u[0] === $table; })); }
function admin_told($m, $needle) {
    foreach ($m->taqdar_admin_model->sent as $s) if ($s[0] === 1 && strpos($s[2], $needle) !== false) return true;
    return false;
}
$mr = new ReflectionMethod('Taqdar_sessions_model', 'mark_refunded'); $mr->setAccessible(true);
$paidrow = array('id' => 77, 'teacher_id' => 5, 'student_id' => 9, 'invoice_id' => 40, 'price_halalas' => 12000,
                 'paid_at' => '2026-11-01 10:00:00', 'slot_id' => 3, 'meet_url' => 'https://meet.google.com/x',
                 'status' => 'declined', 'refund_state' => 'review', 'pack_sub_id' => 0, 'reschedule_count' => 0);

// mark_refunded reports success
$m = smodel($paidrow);
$r = $mr->invoke($m, $paidrow, 'test');
check(is_array($r) && !empty($r['ok']), 'mark_refunded: ok result');
check(count(ups($m, 'invoices')) === 1 && $m->taqdar_wallet_model->calls === 1, 'mark_refunded: invoice + reversal done');
// reversal failure: reported, builder reset, invoice put back
$m = smodel($paidrow, array('wallet_throw' => true));
$r = $mr->invoke($m, $paidrow, 'test');
check(is_array($r) && empty($r['ok']) && ($r['failed'] ?? '') === 'reversal', 'mark_refunded: reversal failure reported');
check($m->db->resets >= 1, 'mark_refunded: reset_query on failure');
$inv = ups($m, 'invoices');
check(count($inv) === 2 && $inv[1][1]['status'] === 'unpaid', 'mark_refunded: invoice restored after reversal failure');
// invoice failure: reported, no reversal
$m = smodel($paidrow, array('throw_on' => array('invoices')));
$r = $mr->invoke($m, $paidrow, 'test');
check(empty($r['ok']) && ($r['failed'] ?? '') === 'invoice' && $m->taqdar_wallet_model->calls === 0, 'mark_refunded: invoice failure reported');

// admin_noshow_decide approve: failure compensates back to review and tells admins
$m = smodel($paidrow, array('wallet_throw' => true));
$r = $m->admin_noshow_decide(77, 'approve', 'ثبت', 1);
check(empty($r['ok']), 'noshow approve: failure returns not ok');
$ts = ups($m, 'tutoring_sessions');
$lastS = end($ts);
check($lastS && $lastS[1]['status'] === 'declined' && $lastS[1]['refund_state'] === 'review', 'noshow approve: session restored to review');
check(admin_told($m, '#77'), 'noshow approve: admins told with session id');
$m = smodel($paidrow);
$r = $m->admin_noshow_decide(77, 'approve', 'ثبت', 1);
check(!empty($r['ok']), 'noshow approve: success');

// student_cancel refund branch: failure compensates back to confirmed, slot kept
$cf = array('status' => 'confirmed', 'refund_state' => null, 'starts_at' => date('Y-m-d H:i:s', time() + 72 * $H)) + $paidrow;
$m = smodel($cf, array('wallet_throw' => true));
$r = $m->student_cancel(77, 9, '');
check(empty($r['ok']), 'student cancel: failure returns not ok');
$ts = ups($m, 'tutoring_sessions'); $lastS = end($ts);
check($lastS && $lastS[1]['status'] === 'confirmed' && $lastS[1]['meet_url'] === $cf['meet_url'], 'student cancel: session restored');
check(count(ups($m, 'availability_slots')) === 0, 'student cancel: slot not released on failure');
check(admin_told($m, '#77'), 'student cancel: admins told with session id');
$m = smodel($cf);
$r = $m->student_cancel(77, 9, '');
check(!empty($r['ok']) && !empty($r['refunded']), 'student cancel: success');

// choose_after_teacher_fault refund (student_choice): failure compensates back to choice
$ch = array('status' => 'declined', 'refund_state' => 'choice', 'cancelled_by' => 'teacher') + $paidrow;
$m = smodel($ch, array('wallet_throw' => true));
$r = $m->choose_after_teacher_fault(77, 9, 'refund');
$ts = ups($m, 'tutoring_sessions'); $lastS = end($ts);
check(empty($r['ok']) && $lastS[1]['status'] === 'declined' && $lastS[1]['refund_state'] === 'choice', 'choice refund: restored on failure');

// no-show reschedule guards teacher_joined_at IS NULL in SQL
$ns = array('status' => 'confirmed', 'refund_state' => null, 'teacher_joined_at' => null, 'duration_min' => 60,
            'starts_at' => date('Y-m-d H:i:s', time() - 30 * 60), 'kind' => 'curriculum', 'track_id' => 0,
            'grade_id' => 0, 'subject_id' => 0) + $paidrow;
$m = smodel($ns);
$m->db->session = $ns;
$slotDb = $m->db;
// the new slot row is read from availability_slots: return an open future slot there
$slotDb2 = new class extends ScriptDb {
    public function row_array() {
        if ($this->tbl === 'availability_slots') return array('id' => 4, 'status' => 'open', 'starts_at' => date('Y-m-d H:i:s', time() + 86400),
            'duration_min' => 60, 'teacher_id' => 5, 'kind' => 'curriculum', 'track_id' => 0, 'grade_id' => 0, 'subject_id' => 0);
        return parent::row_array();
    }
};
$slotDb2->session = $ns; $m->db = $slotDb2;
$r = $m->choose_after_teacher_fault(77, 9, 'reschedule', 4);
$ts = ups($m, 'tutoring_sessions');
check(count($ts) === 1 && in_array('teacher_joined_at IS NULL', $ts[0][2], true), 'no-show reschedule: teacher_joined_at IS NULL guard');

echo "OK — $checks checks\n";
