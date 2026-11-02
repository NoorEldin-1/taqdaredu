<?php
/** Offline checks: Taqdar_wallet_model::reverse_origin() is atomic. No DB.
 * A failure after the first bucket must leave no refund entry behind (so a
 * retry writes all buckets), the builder must be reset, the failure must
 * surface as an exception, and an outer transaction must be respected.
 * Run: php tests/wallet_reverse_test.php
 */
define('BASEPATH', __DIR__);
date_default_timezone_set('Asia/Riyadh');
class CI_Model {}
function t($ar, $args = null) { return $ar; }
function tq_t_deep($x) { return $x; }
function log_message() {}

class FakeResult {
    private $rows;
    public function __construct($rows) { $this->rows = $rows; }
    public function row_array() { return $this->rows ? $this->rows[0] : null; }
    public function result_array() { return $this->rows; }
}

class FakeDb {
    public $_trans_depth = 0;
    public $committed = array();   // refund entries that survived
    public $pending = array();     // written inside an open transaction
    public $fail_on_insert = 0;    // 1-based insert number that fails; 0 = none
    public $throw = false;         // db_debug on: throw instead of returning false
    public $inserts = 0;
    public $status = true;
    public $affected = 0;
    public $resets = 0;
    public $begins = 0;
    public $commits = 0;
    public $rollbacks = 0;

    public function query($sql, $binds = array()) {
        if (strpos($sql, 'COUNT(*)') !== false) {
            $c = 0;
            foreach ($this->committed as $e) if ($e['type'] === 'refund') $c++;
            foreach ($this->pending as $e) if ($e['type'] === 'refund') $c++;
            return new FakeResult(array(array('c' => $c)));
        }
        if (strpos($sql, 'GROUP BY `bucket`') !== false) {
            return new FakeResult(array(
                array('bucket' => 'pending',   'net' => 7000,  'subject' => 'sale'),
                array('bucket' => 'available', 'net' => -1000, 'subject' => 'sale'),
            ));
        }
        if (strpos($sql, 'INSERT IGNORE') !== false) {
            $this->inserts++;
            if ($this->fail_on_insert === $this->inserts) {
                $this->status = false;
                $this->affected = 0;
                if ($this->throw) throw new RuntimeException('insert failed');
                return false;
            }
            $e = array('type' => $binds[1], 'bucket' => $binds[2], 'amount' => $binds[3]);
            if ($this->_trans_depth > 0) $this->pending[] = $e; else $this->committed[] = $e;
            $this->affected = 1;
            return true;
        }
        return new FakeResult(array());
    }
    public function __call($name, $args) { return $this; }   // builder chain (settings lookup)
    public function row_array() { return null; }
    public function result_array() { return array(); }
    public function affected_rows() { return $this->affected; }
    public function reset_query() { $this->resets++; return $this; }
    public function trans_begin() { $this->begins++; $this->_trans_depth++; $this->status = true; return true; }
    public function trans_status() { return $this->status; }
    public function trans_commit() {
        $this->commits++;
        if ($this->_trans_depth === 1) { $this->committed = array_merge($this->committed, $this->pending); $this->pending = array(); }
        $this->_trans_depth = max(0, $this->_trans_depth - 1);
        return true;
    }
    public function trans_rollback() {
        $this->rollbacks++;
        if ($this->_trans_depth === 1) $this->pending = array();
        $this->_trans_depth = max(0, $this->_trans_depth - 1);
        return true;
    }
}

require dirname(__DIR__) . '/application/models/Taqdar_wallet_model.php';

function wallet($db) {
    $m = (new ReflectionClass('Taqdar_wallet_model'))->newInstanceWithoutConstructor();
    $m->db = $db;
    return $m;
}
function reverse($m) {
    $r = new ReflectionMethod('Taqdar_wallet_model', 'reverse_origin');
    $r->setAccessible(true);
    return $r->invoke($m, 9, 'session:5', 'refund');
}
$checks = 0;
function check($condition, $label) {
    global $checks;
    if (!$condition) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
    $checks++;
}

// ---- success: both buckets, one commit ------------------------------------
$db = new FakeDb();
$n = reverse(wallet($db));
check($n === 2, 'success writes both buckets');
check(count($db->committed) === 2, 'both entries committed');
check($db->committed[0]['amount'] === -7000 && $db->committed[1]['amount'] === 1000, 'amounts negate net');
check($db->begins === 1 && $db->commits === 1 && $db->rollbacks === 0, 'own transaction committed');
check($db->_trans_depth === 0, 'depth back to zero');
check(reverse(wallet($db)) === 0, 'second call is idempotent');

// ---- failure on second bucket (query returns false) ------------------------
foreach (array(false, true) as $throw) {
    $tag = $throw ? ' (db_debug throws)' : ' (query returns false)';
    $db = new FakeDb();
    $db->fail_on_insert = 2;
    $db->throw = $throw;
    $thrown = false;
    try { reverse(wallet($db)); } catch (Throwable $e) { $thrown = true; }
    check($thrown, 'failure surfaces as exception' . $tag);
    check(count($db->committed) === 0 && count($db->pending) === 0, 'no partial refund entry' . $tag);
    check($db->rollbacks === 1 && $db->commits === 0, 'rolled back' . $tag);
    check($db->resets >= 1, 'reset_query called' . $tag);
    check($db->_trans_depth === 0, 'depth back to zero after failure' . $tag);

    // retry writes all buckets (the idempotence check does not skip them)
    $db->fail_on_insert = 0;
    $db->status = true;
    check(reverse(wallet($db)) === 2, 'retry writes both buckets' . $tag);
    check(count($db->committed) === 2, 'retry committed both' . $tag);
}

// ---- outer transaction is respected ---------------------------------------
$db = new FakeDb();
$db->trans_begin();
$begins = $db->begins;
check(reverse(wallet($db)) === 2, 'inside outer transaction: writes both');
check($db->begins === $begins && $db->commits === 0, 'does not open/commit its own');
check($db->_trans_depth === 1 && count($db->pending) === 2, 'entries left to the outer transaction');

$db = new FakeDb();
$db->trans_begin();
$db->fail_on_insert = 2;
$thrown = false;
try { reverse(wallet($db)); } catch (Throwable $e) { $thrown = true; }
check($thrown, 'outer: failure rethrown for the outer owner');
check($db->rollbacks === 0 && $db->_trans_depth === 1, 'outer: no inner rollback, outer still open');
check($db->resets >= 1, 'outer: reset_query called');

echo "OK wallet_reverse: $checks checks\n";
