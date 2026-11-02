<?php
/** Offline checks for TQ-GROUP screens (task 5): calendar file of one session and of a whole
 * schedule, the seats sentence, the "one hour before" reminder window. Pure static helpers of
 * Taqdar_group_model — no DB.
 * Run: php tests/live_group_screens_test.php
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

$g = array('id' => 4, 'title' => 'رياضيات, الصف الثالث; مراجعة', 'slug' => 'math-3');
$s = array(
    array('id' => 11, 'group_id' => 4, 'title' => 'الكسور', 'starts_at' => '2026-11-10 15:00:00',
          'duration_min' => 60, 'status' => 'scheduled', 'meet_url' => 'https://zoom.us/j/1'),
    array('id' => 12, 'group_id' => 4, 'title' => '', 'starts_at' => '2026-11-12 15:00:00',
          'duration_min' => 90, 'status' => 'cancelled', 'meet_url' => ''),
    array('id' => 13, 'group_id' => 4, 'title' => 'الهندسة', 'starts_at' => '2026-11-14 15:00:00',
          'duration_min' => 45, 'status' => 'scheduled', 'meet_url' => ''),
);

// ---- Whole schedule: one VEVENT per live session, cancelled dropped, CRLF lines, escaped text.
$ics = Taqdar_group_model::ics_schedule($g, $s);
check(strpos($ics, "BEGIN:VCALENDAR\r\n") === 0, 'starts with VCALENDAR + CRLF');
check(substr_count($ics, 'BEGIN:VEVENT') === 2, 'two events (cancelled dropped)');
check(strpos($ics, 'UID:tq-gsession-11@taqdaredu.com') !== false, 'stable UID per session');
check(strpos($ics, 'UID:tq-gsession-12@') === false, 'no cancelled UID');
check(strpos($ics, 'DTSTART:' . gmdate('Ymd\THis\Z', strtotime('2026-11-10 15:00:00'))) !== false, 'DTSTART UTC');
check(strpos($ics, 'DTEND:' . gmdate('Ymd\THis\Z', strtotime('2026-11-14 15:45:00'))) !== false, 'DTEND = start + duration');
check(strpos($ics, 'رياضيات\, الصف الثالث\; مراجعة') !== false, 'comma and semicolon escaped');
check(strpos($ics, 'zoom.us') === false, 'meeting link never leaks into the file');
check(substr($ics, -15) === "END:VCALENDAR\r\n", 'ends with END:VCALENDAR');

// ---- One session: the same builder with a single row.
$one = Taqdar_group_model::ics_schedule($g, array($s[0]));
check(substr_count($one, 'BEGIN:VEVENT') === 1, 'single session file');
check(strpos($one, 'الكسور') !== false, 'session title in summary');

// ---- Seats sentence.
check(Taqdar_group_model::seats_text(null) === 'مقاعد مفتوحة', 'unlimited');
check(Taqdar_group_model::seats_text(0) === 'اكتملت المقاعد', 'full');
check(Taqdar_group_model::seats_text(1) === 'بقي مقعد واحد', 'one');
check(Taqdar_group_model::seats_text(2) === 'بقي مقعدان', 'two');
check(Taqdar_group_model::seats_text(5) === 'بقيت 5 مقاعد', 'three to ten');
check(Taqdar_group_model::seats_text(14) === 'بقي 14 مقعدا', 'eleven and more');

// ---- Reminder window: scheduled, not reminded, starts within the next hour (+5 min slack).
$now = strtotime('2026-11-10 14:10:00');
$row = array('status' => 'scheduled', 'starts_at' => '2026-11-10 15:00:00', 'reminded_at' => null);
check(Taqdar_group_model::reminder_due($row, $now), '50 min ahead is due');
check(!Taqdar_group_model::reminder_due(array('reminded_at' => '2026-11-10 14:00:00') + $row, $now), 'reminded once');
check(!Taqdar_group_model::reminder_due(array('status' => 'cancelled') + $row, $now), 'not for cancelled');
check(!Taqdar_group_model::reminder_due($row, strtotime('2026-11-10 13:00:00')), 'two hours ahead not yet');
check(!Taqdar_group_model::reminder_due($row, strtotime('2026-11-10 15:01:00')), 'started already');

// ---- summary(): the one shape screens and the API read (no meet link, price in halalas).
$offer = array('sellable' => true, 'reason' => 'ok', 'why' => '', 'price_halalas' => 19950, 'seats_left' => 2);
$g2 = $g + array('description' => 'وصف', 'price' => 19950, 'capacity' => 10, 'status' => 'published',
                 'grade_id' => 3, 'subject_id' => 5, 'teacher_id' => 9, 'enroll_until' => null, 'image' => '');
$sum = Taqdar_group_model::summary($g2, $offer, $s, strtotime('2026-11-11 10:00:00'));
check($sum['id'] === 4 && $sum['slug'] === 'math-3', 'id and slug');
check($sum['price_halalas'] === 19950, 'price stays halalas');
check($sum['seats_left'] === 2 && $sum['seats_text'] === 'بقي مقعدان', 'seats in summary');
check($sum['sessions_total'] === 2, 'cancelled not counted in total');
check($sum['sessions_left'] === 1, 'one upcoming after now');
check($sum['next_starts_at'] === '2026-11-14 15:00:00', 'next session is the first upcoming');
check($sum['first_starts_at'] === '2026-11-10 15:00:00', 'first session');
check($sum['sellable'] === true && $sum['reason'] === 'ok', 'offer carried');
check(strpos(json_encode($sum), 'zoom') === false, 'no meeting link in summary');
$none = Taqdar_group_model::summary($g2, array('sellable' => false, 'reason' => 'full', 'why' => 'x',
                                                'price_halalas' => 19950, 'seats_left' => 0), array(), time());
check($none['next_starts_at'] === null && $none['sessions_total'] === 0, 'no sessions → null next');
check($none['seats_text'] === 'اكتملت المقاعد', 'full text');

echo "OK — {$checks} checks\n";
