<?php
/** Offline checks for TQ-GROUP admin fixes (task 5, fix round 1): the module status guard,
 * the refunds-due count from audit_log, the admin close / cancel-without-replacement wiring,
 * the dropped teacher DELETE endpoint, and the cron timezone guard. No DB.
 * Run: php tests/live_group_admin_test.php
 */
define('BASEPATH', __DIR__);
class CI_Model {}
function get_settings($key) { return null; }
function t($ar, $args = null) {
    foreach ((array) $args as $a) $ar = preg_replace('/____/', (string) $a, $ar, 1);
    return $ar;
}
require dirname(__DIR__) . '/application/models/Taqdar_group_model.php';
$checks = 0;
function check($condition, $label) {
    global $checks;
    if (!$condition) throw new RuntimeException('FAIL: ' . $label);
    $checks++;
}
$root = dirname(__DIR__);
$src  = function ($p) use ($root) { return file_get_contents($root . '/' . $p); };

// ---- 3. Module status guard: a NEW transition to closed is refused; a closed row keeps its value.
check(is_string(Taqdar_group_model::module_status_error('published', 'closed')), 'published -> closed refused');
check(is_string(Taqdar_group_model::module_status_error('draft', 'closed')), 'draft -> closed refused');
check(is_string(Taqdar_group_model::module_status_error(null, 'closed')), 'new row as closed refused');
check(Taqdar_group_model::module_status_error('closed', 'closed') === null, 'closed stays closed on save');
check(Taqdar_group_model::module_status_error('draft', 'published') === null, 'other transitions pass');
check(Taqdar_group_model::module_status_error('closed', 'published') === null, 'reopen is not this guard');
check(strpos($src('application/models/Taqdar_admin_model.php'), 'module_status_error') !== false, 'save() calls the guard');

// ---- 4. Refunds due: per group, from audit rows, minus resolved ones.
$rows = array(
    array('action' => 'group.refund_due', 'entity' => 'subscriptions#10', 'after' => '{"group_id":4,"price":20000}'),
    array('action' => 'group.refund_due', 'entity' => 'subscriptions#11', 'after' => '{"group_id":4,"price":20000}'),
    array('action' => 'group.refund_due', 'entity' => 'subscriptions#12', 'after' => '{"group_id":5,"price":9000}'),
    array('action' => 'group.refund_resolved', 'entity' => 'subscriptions#11', 'after' => '{"group_id":4}'),
    array('action' => 'group.refund_due', 'entity' => 'subscriptions#10', 'after' => '{"group_id":4,"price":20000}'),
);
$open = Taqdar_group_model::refund_open($rows);
check(isset($open[4]) && count($open[4]) === 1 && isset($open[4][10]), 'group 4: one open (11 resolved, 10 deduped)');
check($open[4][10] === 20000, 'open refund carries its price');
check(isset($open[5]) && count($open[5]) === 1, 'group 5: one open');
check(!isset($open[6]), 'no refund records -> nothing');
check(Taqdar_group_model::refund_open(array()) === array(), 'empty audit');
$gm = $src('application/models/Taqdar_group_model.php');
check(strpos($gm, "\$a['cancelled'][0]") === false, 'old cancelled-count heuristic gone');

// ---- 1 + 2. Admin wiring: POST routes, controller methods, forms with reason + confirm + manual note.
$routes = $src('application/config/routes.php');
foreach (array('group_close', 'group_session_cancel', 'group_refund_resolved') as $m) {
    check(preg_match("~route\['taqdar_admin/$m'\]~", $routes) === 1, "route $m");
}
$ctl = $src('application/controllers/Taqdar_admin.php');
check(strpos($ctl, 'function group_close') !== false && strpos($ctl, 'close_group(') !== false, 'controller close');
check(strpos($ctl, 'function group_session_cancel') !== false && strpos($ctl, 'cancel_session(') !== false, 'controller cancel');
check(strpos($ctl, 'function group_refund_resolved') !== false, 'controller resolve');
$view = $src('application/views/backend/admin/tqa_groups.php');
check(substr_count($view, 'tq_csrf()') >= 3, 'csrf in every form');
check(strpos($view, 'taqdar_admin/group_close') !== false, 'close form');
check(strpos($view, 'taqdar_admin/group_session_cancel') !== false, 'cancel form');
check(substr_count($view, 'name="reason"') >= 2 && substr_count($view, 'required') >= 2, 'reason required');
check(substr_count($view, 'confirm(') >= 2, 'confirm dialogs');
check(strpos($view, 'خارج المنصة') !== false, 'manual refund note');
check(strpos($gm, 'function close_group($id, $reason') !== false, 'close_group takes a reason');

// ---- 5. Teacher DELETE on a group session is gone.
$api = $src('application/controllers/Api_v1.php');
$p = strpos($api, 'public function teacher_group_session($id');
$body = substr($api, $p, strpos($api, 'public function', $p + 10) - $p);
check(strpos($body, 'DELETE') === false, 'Api_v1 has no DELETE branch');
$spec = $src('application/config/taqdar_api_spec.php');
$sp = strpos($spec, "'/api/v1/teacher/group-sessions/{id}' =>");
$blk = substr($spec, $sp, strpos($spec, "'/api/v1/teacher/group-sessions/{id}/complete'") - $sp);
check(strpos($blk, "'delete'") === false, 'spec has no delete');

// ---- 6. Cron timezone guard.
$cron = $src('application/controllers/Taqdar_cron.php');
check(strpos($cron, 'DateTimeZone::listIdentifiers()') !== false, 'cron validates timezone');
check(strpos($cron, "date_default_timezone_set(get_settings('timezone'))") === false, 'raw setting not used');

// ---- 7. Group-session module guard: cancel / un-cancel / starts_at change go through their paths.
$o = array('status' => 'scheduled', 'starts_at' => '2026-12-01 18:00:00');
check(is_string(Taqdar_group_model::module_session_error($o, array('status' => 'cancelled', 'starts_at' => $o['starts_at']))), 'into cancelled refused');
check(is_string(Taqdar_group_model::module_session_error(array('status' => 'cancelled', 'starts_at' => $o['starts_at']), array('status' => 'scheduled', 'starts_at' => $o['starts_at']))), 'out of cancelled refused');
check(is_string(Taqdar_group_model::module_session_error($o, array('status' => 'scheduled', 'starts_at' => '2026-12-02 18:00:00'))), 'starts_at change refused');
check(Taqdar_group_model::module_session_error($o, array('status' => 'scheduled', 'starts_at' => '2026-12-01 18:00')) === null, 'same moment, other format passes');
check(Taqdar_group_model::module_session_error(array('status' => 'cancelled', 'starts_at' => $o['starts_at']), array('status' => 'cancelled', 'starts_at' => $o['starts_at'])) === null, 'cancelled row saves unchanged');
check(Taqdar_group_model::module_session_error(null, array('status' => 'cancelled', 'starts_at' => '2026-12-05 10:00:00')) === null, 'insert unaffected');
check(Taqdar_group_model::module_session_error($o, array('status' => 'completed', 'starts_at' => $o['starts_at'])) === null, 'other transitions pass');
check(strpos($src('application/models/Taqdar_admin_model.php'), 'module_session_error') !== false, 'save() calls the session guard');
check(preg_match("~route\['taqdar_admin/group_session_reschedule'\]~", $routes) === 1, 'reschedule route');
check(strpos($routes, "taqdar_admin/group_session_reschedule") < strpos($routes, "\$route['groups']"), 'reschedule route before view routes');
check(strpos($ctl, 'function group_session_reschedule') !== false && strpos($ctl, 'reschedule_session(') !== false, 'controller reschedule');
check(strpos($view, 'taqdar_admin/group_session_reschedule') !== false, 'reschedule form');
check(substr_count($view, 'tq_csrf()') >= 4 && substr_count($view, 'confirm(') >= 3, 'reschedule form has csrf + confirm');

// ---- 8. resolve_refund resets the builder on failure; admin timezone guard.
$rp = strpos($gm, 'public function resolve_refund');
$rb = substr($gm, $rp, strpos($gm, 'public static function module_status_error') - $rp);
check(strpos($rb, 'reset_query()') !== false && strpos($rb, 'catch') !== false && strpos($rb, 'log_message') !== false, 'resolve_refund try/catch + reset');
check(strpos($ctl, 'DateTimeZone::listIdentifiers()') !== false, 'admin validates timezone');
check(strpos($ctl, "date_default_timezone_set(get_settings('timezone'))") === false, 'admin raw setting not used');

echo "OK $checks checks\n";
