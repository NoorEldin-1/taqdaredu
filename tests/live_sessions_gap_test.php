<?php
/** Offline checks for the TQ-LIVE gap pass (sessions side): teacher-not-joined hint,
 * admin meet-link edit, teacher_cancel admin notice, notification bodies as single
 * placeholder keys. No DB.
 * Run: php tests/live_sessions_gap_test.php
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

$GLOBALS['mode'] = 'cfg';
$GLOBALS['row'] = null;
$GLOBALS['writes'] = array();
class FakeDb {
    public function __call($name, $args) {
        if ($name === 'update' || $name === 'insert') $GLOBALS['writes'][] = array($name, $args);
        return $this;
    }
    public function table_exists($t) { return false; }
    public function count_all_results($t = '') { return 0; }
    public function affected_rows() { return 1; }
    public function result_array() { return $GLOBALS['mode'] === 'admin' ? array(array('id' => 1)) : array(); }
    public function row_array() { return $GLOBALS['row']; }
}
class FakeInput { public function ip_address() { return '127.0.0.1'; } public function is_cli_request() { return true; } }
class FakeLoad { public function model($m) {} }
class FakeAdminModel {
    public $sent = array();
    public function push_notification($to, $title, $text, $type) { $this->sent[] = array($to, $title, $text); return true; }
}

require dirname(__DIR__) . '/application/models/Taqdar_sessions_model.php';

function model() {
    $m = (new ReflectionClass('Taqdar_sessions_model'))->newInstanceWithoutConstructor();
    $m->db = new FakeDb();
    $m->input = new FakeInput();
    $m->load = new FakeLoad();
    $m->taqdar_admin_model = new FakeAdminModel();
    $m->config(true);
    $GLOBALS['mode'] = 'admin';
    return $m;
}
$checks = 0;
function check($condition, $label) {
    global $checks;
    if (!$condition) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    $checks++;
}

$m = model();
$starts = time() + 3 * 60;           // opens in the future, lead 10 min -> ready
$base = array('status' => 'confirmed', 'starts_at' => date('Y-m-d H:i:s', $starts), 'duration_min' => 60,
              'meet_url' => 'https://meet.google.com/abc-defg-hij', 'teacher_id' => 5, 'student_id' => 9);

// ---- gap 14: "the teacher has not joined yet" ------------------------------
$d = $m->display_state($base, null, 'student');
check($d['key'] === 'ready' && $d['hint'] === 'المعلم لم يدخل بعد', 'ready, teacher not joined: hint for student');
$d = $m->display_state($base, null, 'teacher');
check($d['hint'] === '', 'teacher actor: no hint');
$joined = $base + array('teacher_joined_at' => date('Y-m-d H:i:s'));
check($m->display_state($joined, null, 'student')['hint'] === '', 'teacher joined: no hint');
$liveRow = array('status' => 'live', 'starts_at' => date('Y-m-d H:i:s', time() - 120)) + $base;
$d = $m->display_state($liveRow, null, 'student');
check($d['key'] === 'live' && $d['hint'] === 'المعلم لم يدخل بعد', 'live +2 min, not joined: hint');
$lateRow = array('status' => 'live', 'starts_at' => date('Y-m-d H:i:s', time() - 11 * 60)) + $base;
$d = $m->display_state($lateRow, null, 'student');
check($d['label'] === 'المعلم تأخر' && $d['hint'] === '', 'live +11 min: late label replaces the hint');
$pending = array('status' => 'awaiting_payment', 'starts_at' => $base['starts_at']) + $base;
check($m->display_state($pending, null, 'student')['hint'] === '', 'unpaid: no hint');

// ---- gap 2: admin edits a session meet link -------------------------------
$GLOBALS['row'] = array('id' => 7, 'status' => 'confirmed', 'meet_url' => '');
$GLOBALS['writes'] = array();
$r = $m->admin_set_meet_url(7, 'https://meet.google.com/xyz-abcd-efg', 3);
check(!empty($r['ok']) && $r['url'] === 'https://meet.google.com/xyz-abcd-efg', 'admin saves a valid link');
$upd = null; $aud = null;
foreach ($GLOBALS['writes'] as $w) { if ($w[0] === 'update') $upd = $w[1]; if ($w[0] === 'insert') $aud = $w[1]; }
check($upd && $upd[1]['meet_url'] === 'https://meet.google.com/xyz-abcd-efg', 'link written to the session');
check($aud && $aud[1]['action'] === 'session.meet_url_admin', 'audit row written');
$r = $m->admin_set_meet_url(7, 'http://evil.example/x', 3);
check(empty($r['ok']), 'rejected link refused');
$GLOBALS['row'] = array('id' => 7, 'status' => 'completed', 'meet_url' => '');
check(empty($m->admin_set_meet_url(7, 'https://meet.google.com/xyz-abcd-efg', 3)['ok']), 'finished session link not editable');
$GLOBALS['row'] = null;
check(empty($m->admin_set_meet_url(7, '', 3)['ok']), 'missing session refused');

// ---- gap 4 + 15: teacher_cancel tells admins, body is one placeholder key ---
$GLOBALS['row'] = array('id' => 11, 'status' => 'confirmed', 'teacher_id' => 5, 'student_id' => 9, 'slot_id' => 1,
    'starts_at' => date('Y-m-d H:i:s', time() + 5 * 3600), 'duration_min' => 60,
    'paid_at' => date('Y-m-d H:i:s'), 'price_halalas' => 10000, 'pack_sub_id' => 0, 'invoice_id' => 4);
$m->taqdar_admin_model->sent = array();
$r = $m->teacher_cancel(11, 5, 'ظرف طارئ');
check(!empty($r['ok']), 'teacher_cancel ok');
$to = array_column($m->taqdar_admin_model->sent, 0);
check(in_array(9, $to, true), 'student told');
check(in_array(1, $to, true), 'admin told of the apology');
$adm = null;
foreach ($m->taqdar_admin_model->sent as $s) if ($s[0] === 1) $adm = $s;
check(strpos($adm[2], '11') !== false && strpos($adm[2], 'ظرف طارئ') !== false, 'admin notice carries session id and reason');
$stu = null;
foreach ($m->taqdar_admin_model->sent as $s) if ($s[0] === 9) $stu = $s;
check(strpos($stu[2], 'ظرف طارئ') !== false && strpos($stu[2], '____') === false, 'student body filled');

// ---- gap 15: no notification body is built by concatenation ---------------
$src = file_get_contents(dirname(__DIR__) . '/application/models/Taqdar_sessions_model.php');
check(!preg_match("/\\\$this->tell\\([^;]*'\\s*\\.\\s*\\\$/s", $src), 'tell() bodies carry no string concatenation with variables');
check(strpos($src, "'تغير موعد الحصة من ' .") === false, 'reschedule text is a single key');
check(strpos($src, "'ألغى المعلم الحصة ' .") === false, 'teacher_cancel text is a single key');

echo "OK $checks checks\n";
