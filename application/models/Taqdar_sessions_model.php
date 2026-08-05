<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * محرّك الحصص الخاصّة: إتاحة المعلّم وطلبات الطلاب.
 *
 * الجدولان الحقيقيّان في taqd_lms هما `availability_slots` و`tutoring_sessions`.
 * ولا وجود لـ `teacher_availability` ولا لـ `session_requests` — كانت الشاشتان
 * تنتظران اسمين لم يُنشآ قطّ، والجدولان أمامهما. فالتسمية هنا تتبع القاعدة،
 * والقاعدة وحدها.
 *
 * والشبكة في شاشة المعلّم أسبوعية (٧ أيام × ٣ فترات) بينما العمود في القاعدة
 * `starts_at datetime` — موعد بعينه لا يومَ أسبوع. فالمفتاح «اليوم:الفترة»
 * يُترجَم هنا إلى أقرب وقوع قادم لذلك اليوم في تلك الفترة داخل الأيام السبعة
 * القادمة، ويُقرأ عكسيًّا عند العرض. الترجمة في موضع واحد لا تتكرّر في العرض.
 *
 * الأسبوع يبدأ الأحد — السوق سعودي، و`date('w')` يعطي ٠ للأحد فيوافق الشبكة.
 *
 * دورة حياة الموعد والطلب متلازمتان:
 *   الموعد open ← يطلبه طالب ← held ← يؤكّده المعلّم ← booked
 *                                  ← يعتذر المعلّم ← يعود open
 * والحالة تتغيّر في القاعدة لا في الواجهة، والملكيّة تُفحص في الاستعلام نفسه:
 * `teacher_id = <المعلّم الحالي>` شرطٌ في كل تحديث، لا إخفاءُ زرّ.
 *
 * ولا مال هنا: التأكيد لا يخصم شيئًا. «بانتظار التأكيد» تعني أن شيئًا لم يُدفع،
 * وتأكيد المعلّم يفتح الموعد لا المحفظة — والخصم شأن جدول الفوترة لا هذا.
 */
class Taqdar_sessions_model extends CI_Model
{
    /** طول النافذة التي تُدار فيها الشبكة: أسبوع + يوم احتياط لحدود التوقيت. */
    const WINDOW_DAYS = 8;

    /* =====================================================================
       الفترات والأيام — مصدر واحد للشبكة وللترجمة
       ===================================================================== */

    /** الفترات الثلاث: ساعة البدء ومدّتها بالدقائق ونصّها كما يُعرض. */
    public function periods()
    {
        return [
            'morning' => ['label' => 'صباحًا', 'range' => '8:00 – 12:00',  'hour' => 8,  'duration' => 240],
            'noon'    => ['label' => 'ظهرًا',  'range' => '12:00 – 16:00', 'hour' => 12, 'duration' => 240],
            'evening' => ['label' => 'مساءً',  'range' => '16:00 – 21:00', 'hour' => 16, 'duration' => 300],
        ];
    }

    /** أيام الأسبوع بترتيب `date('w')` نفسه: الأحد أوّلًا. */
    public function days()
    {
        return [
            0 => 'الأحد', 1 => 'الاثنين', 2 => 'الثلاثاء', 3 => 'الأربعاء',
            4 => 'الخميس', 5 => 'الجمعة', 6 => 'السبت',
        ];
    }

    /** أسماء الشهور الميلادية كما تُكتب في السوق السعودي. */
    private function month_name($m)
    {
        static $names = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
                         'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
        $i = ((int) $m) - 1;
        return $names[$i] ?? '';
    }

    /* =====================================================================
       ترجمة الشبكة ↔ الموعد
       ===================================================================== */

    /**
     * «٣:evening» ← «2026-08-06 16:00:00».
     * أقرب وقوع قادم لهذا اليوم في هذه الفترة؛ فما مضى من الأسبوع يُدفع أسبوعًا
     * كاملًا إلى الأمام. وبهذا لا يُحفظ للمعلّم موعدٌ في الماضي يستحيل حجزه.
     */
    public function key_to_datetime($key, $now = null)
    {
        $parts = explode(':', (string) $key);
        if (count($parts) !== 2) return null;

        $dow = (int) $parts[0];
        $pk  = $parts[1];
        $periods = $this->periods();
        if ($dow < 0 || $dow > 6 || !isset($periods[$pk])) return null;

        $now   = $now ? (int) $now : time();
        $delta = ($dow - (int) date('w', $now) + 7) % 7;
        $day   = date('Y-m-d', strtotime('+' . $delta . ' day', $now));
        $ts    = strtotime($day . ' ' . sprintf('%02d:00:00', $periods[$pk]['hour']));
        if ($ts <= $now) $ts = strtotime('+7 day', $ts);

        return date('Y-m-d H:i:s', $ts);
    }

    /** «2026-08-06 16:00:00» ← «3:evening»، وإلّا null لموعد خارج الفترات. */
    public function datetime_to_key($starts_at)
    {
        $ts = strtotime((string) $starts_at);
        if (!$ts) return null;

        $hour = (int) date('G', $ts);
        foreach ($this->periods() as $pk => $p) {
            if ((int) $p['hour'] === $hour) return date('w', $ts) . ':' . $pk;
        }
        return null;
    }

    /** مدّة الفترة التي يقع فيها الموعد — نصف ساعة افتراض القاعدة لا يعبّر عنها. */
    private function duration_of($starts_at)
    {
        $hour = (int) date('G', strtotime((string) $starts_at));
        foreach ($this->periods() as $p) {
            if ((int) $p['hour'] === $hour) return (int) $p['duration'];
        }
        return 60;
    }

    /** «الأحد 3 أغسطس · صباحًا 8:00 – 12:00» — نصّ واحد يُعرض كما هو. */
    public function when_text($starts_at)
    {
        $ts = strtotime((string) $starts_at);
        if (!$ts) return '';

        $days = $this->days();
        $out  = ($days[(int) date('w', $ts)] ?? '') . ' ' . date('j', $ts) . ' ' . $this->month_name(date('n', $ts));

        $hour = (int) date('G', $ts);
        foreach ($this->periods() as $p) {
            if ((int) $p['hour'] === $hour) {
                return $out . ' · ' . $p['label'] . ' ' . $p['range'];
            }
        }
        return $out . ' · ' . date('G:i', $ts);
    }

    /** حدّا النافذة التي تُدار فيها الشبكة. */
    private function window($now = null)
    {
        $now = $now ? (int) $now : time();
        return [
            date('Y-m-d H:i:s', $now),
            date('Y-m-d H:i:s', strtotime('+' . self::WINDOW_DAYS . ' day', $now)),
        ];
    }

    /* =====================================================================
       إتاحة المعلّم
       ===================================================================== */

    /**
     * مفاتيح الشبكة المحفوظة للمعلّم — ما يُعاد وضع علامته عند إعادة التحميل.
     * تشمل المحجوز والمعلَّق: الفترة التي عليها حصّة مؤكّدة ما زالت فترة عمله.
     */
    public function week_keys($teacher_id)
    {
        $teacher_id = (int) $teacher_id;
        if ($teacher_id <= 0) return [];

        list($from, $to) = $this->window();
        $rows = $this->db->select('starts_at')
            ->where('teacher_id', $teacher_id)
            ->where('starts_at >', $from)
            ->where('starts_at <=', $to)
            ->get('availability_slots')->result_array();

        $out = [];
        foreach ($rows as $r) {
            $k = $this->datetime_to_key($r['starts_at']);
            if ($k !== null) $out[$k] = true;
        }
        return array_keys($out);
    }

    /**
     * يحفظ الشبكة: ما اختير يُنشأ، وما رُفع اختياره يُحذف.
     *
     * ولا يُحذف موعد عليه طلب حيّ — ولو رفع المعلّم علامته: الطالب طلبه فعلًا،
     * وحذفه يترك طلبًا معلّقًا بلا موعد. من أراد إلغاءه فليعتذر عنه أوّلًا.
     * أمّا ما اعتُذر عنه أو انتهى فطلبٌ مغلق، ولا يُقيّد جدول المعلّم إلى الأبد.
     *
     * @return int عدد الفترات المتاحة بعد الحفظ
     */
    public function save_week($teacher_id, $keys)
    {
        $teacher_id = (int) $teacher_id;
        if ($teacher_id <= 0) return 0;

        $now = time();
        list($from, $to) = $this->window($now);

        $want = [];
        foreach ((array) $keys as $k) {
            $dt = $this->key_to_datetime($k, $now);
            if ($dt !== null) $want[$dt] = $this->duration_of($dt);
        }

        $this->db->where('teacher_id', $teacher_id)
                 ->where('status', 'open')
                 ->where('starts_at >', $from)
                 ->where('starts_at <=', $to)
                 ->where("id NOT IN (SELECT slot_id FROM tutoring_sessions
                          WHERE slot_id IS NOT NULL AND status IN ('requested','confirmed','live'))", null, false);
        if ($want) $this->db->where_not_in('starts_at', array_keys($want));
        $this->db->delete('availability_slots');

        // المفتاح الفريد (معلّم، موعد) يمنع التكرار، فإعادة الحفظ لا تُضاعف شيئًا
        // ولا تُرجِع محجوزًا إلى open.
        foreach ($want as $dt => $dur) {
            $this->db->query(
                'INSERT IGNORE INTO availability_slots (teacher_id, starts_at, duration_min, status) VALUES (?, ?, ?, ?)',
                [$teacher_id, $dt, $dur, 'open']
            );
        }

        return count($this->week_keys($teacher_id));
    }

    /* =====================================================================
       المعلّمون المتاحون — لشاشة الطالب
       ===================================================================== */

    /**
     * اشتقاق المعلّم كما في `taqdar_role_helper`: مفعَّل، `is_instructor`، وليس
     * أدمن. الأدمن لا تُفتح له بوّابة المعلّم فلا يُعرض معلّمًا للطلاب.
     */
    private function teacher_filter($alias = 'u')
    {
        $this->db->where($alias . '.status', 1)
                 ->where($alias . '.is_instructor', 1)
                 ->where($alias . '.role_id !=', 1);
    }

    /**
     * المعلّمون الذين لهم مواعيد مفتوحة قادمة، ومعهم مواعيدهم.
     * لا تقييم ولا سعر ولا سنوات خبرة: لا جدول لها في taqd_lms، ولا تُخترَع.
     *
     * @param int $limit_teachers أقصى عدد معلّمين
     * @param int $limit_slots    أقصى عدد مواعيد لكل معلّم
     * @param int $category_id    تصفية بمادة (٠ = الكل)
     */
    public function available_teachers($limit_teachers = 12, $limit_slots = 6, $category_id = 0)
    {
        $now = date('Y-m-d H:i:s');

        $this->db->select('s.id AS slot_id, s.starts_at, s.duration_min,
                           u.id AS teacher_id, u.first_name, u.last_name, u.image, u.title')
                 ->from('availability_slots s')
                 ->join('users u', 'u.id = s.teacher_id', 'inner')
                 ->where('s.status', 'open')
                 ->where('s.starts_at >', $now);
        $this->teacher_filter('u');
        $rows = $this->db->order_by('s.starts_at', 'ASC')->limit(400)->get()->result_array();

        $subjects = $this->teacher_subjects();

        $out = [];
        foreach ($rows as $r) {
            $tid = (int) $r['teacher_id'];

            if ($category_id > 0) {
                $cats = $subjects['cats'][$tid] ?? [];
                if (!in_array((int) $category_id, $cats, true)) continue;
            }

            if (!isset($out[$tid])) {
                if (count($out) >= (int) $limit_teachers) continue;
                $name = trim((string) $r['first_name'] . ' ' . (string) $r['last_name']);
                $out[$tid] = [
                    'id'      => $tid,
                    'name'    => $name !== '' ? $name : 'معلّم',
                    'image'   => (string) $r['image'],
                    'title'   => trim((string) $r['title']),
                    'subject' => $subjects['name'][$tid] ?? '',
                    'slots'   => [],
                ];
            }
            if (count($out[$tid]['slots']) >= (int) $limit_slots) continue;

            $out[$tid]['slots'][] = [
                'id'        => (int) $r['slot_id'],
                'starts_at' => $r['starts_at'],
                'when_text' => $this->when_text($r['starts_at']),
                'minutes'   => (int) $r['duration_min'],
            ];
        }

        return array_values($out);
    }

    /**
     * مادّة كل معلّم — من كورساته المنشورة (`course.user_id` نصّ في Academy).
     * وهذا كل ما تعرفه القاعدة عن تخصّصه؛ من لا كورس له لا مادّة له، فلا يُنسب
     * إلى مادة لم يدرّسها.
     */
    public function teacher_subjects()
    {
        static $cache = null;
        if ($cache !== null) return $cache;

        $rows = $this->db->select('user_id, category_id')
            ->from('course')
            ->where('status', 'active')
            ->where('category_id >', 0)
            ->get()->result_array();

        $cats = $this->db->select('id, name')->get('category')->result_array();
        $names = [];
        foreach ($cats as $c) $names[(int) $c['id']] = (string) $c['name'];

        $by_teacher = [];
        $first      = [];
        foreach ($rows as $r) {
            $tid = (int) $r['user_id'];
            $cid = (int) $r['category_id'];
            if ($tid <= 0 || $cid <= 0) continue;
            $by_teacher[$tid][] = $cid;
            if (!isset($first[$tid]) && isset($names[$cid])) $first[$tid] = $names[$cid];
        }

        return $cache = ['cats' => $by_teacher, 'name' => $first];
    }

    /* =====================================================================
       الطلبات: إنشاؤها وقراءتها وحسمها
       ===================================================================== */

    /**
     * طلب حصّة على موعد بعينه.
     * كل شرط هنا يُفحص في الخادم: الموعد قائم، ومفتوح، ولم يمضِ، وليس للطالب
     * طلب قائم عليه. والموعد يصير `held` لا `booked` — الحجز لا يتمّ إلّا بتأكيد.
     *
     * @return array ['ok'=>bool, 'msg'=>string, 'id'=>int]
     */
    public function request_session($student_id, $slot_id)
    {
        $student_id = (int) $student_id;
        $slot_id    = (int) $slot_id;
        if ($student_id <= 0 || $slot_id <= 0) {
            return ['ok' => false, 'msg' => 'طلب غير مكتمل.', 'id' => 0];
        }

        $slot = $this->db->where('id', $slot_id)->get('availability_slots')->row_array();
        if (!$slot) {
            return ['ok' => false, 'msg' => 'هذا الموعد لم يعد موجودًا.', 'id' => 0];
        }
        if ($slot['status'] !== 'open') {
            return ['ok' => false, 'msg' => 'سبقك غيرك إلى هذا الموعد. اختر موعدًا آخر.', 'id' => 0];
        }
        if (strtotime($slot['starts_at']) <= time()) {
            return ['ok' => false, 'msg' => 'هذا الموعد مضى.', 'id' => 0];
        }
        if ((int) $slot['teacher_id'] === $student_id) {
            return ['ok' => false, 'msg' => 'لا تُحجز حصّة مع نفسك.', 'id' => 0];
        }

        $dup = $this->db->where('slot_id', $slot_id)
                        ->where('student_id', $student_id)
                        ->where_in('status', ['requested', 'confirmed', 'live'])
                        ->count_all_results('tutoring_sessions');
        if ($dup > 0) {
            return ['ok' => false, 'msg' => 'طلبك على هذا الموعد قائم بالفعل.', 'id' => 0];
        }

        $this->db->insert('tutoring_sessions', [
            'slot_id'    => $slot_id,
            'student_id' => $student_id,
            'teacher_id' => (int) $slot['teacher_id'],
            'status'     => 'requested',
        ]);
        $id = (int) $this->db->insert_id();
        if ($id <= 0) {
            return ['ok' => false, 'msg' => 'تعذّر حفظ الطلب. حاول مرّة أخرى.', 'id' => 0];
        }

        // شرط `status = open` في التحديث نفسه: طلبان متزامنان لا يعلّقان موعدًا واحدًا مرّتين.
        $this->db->where('id', $slot_id)->where('status', 'open')
                 ->update('availability_slots', ['status' => 'held']);

        return ['ok' => true, 'msg' => 'أُرسل طلبك إلى المعلّم. لم يُخصم شيء بعد.', 'id' => $id];
    }

    /**
     * طلبات معلّم بعينه. الشرط في الاستعلام لا في العرض.
     *
     * @param array $statuses حالات مطلوبة (فارغ = المعلّقة وحدها)
     */
    public function requests_for_teacher($teacher_id, $statuses = ['requested'], $limit = 50)
    {
        $teacher_id = (int) $teacher_id;
        if ($teacher_id <= 0) return [];

        $this->db->select('t.id, t.status, t.slot_id, t.student_id,
                           a.starts_at, a.duration_min,
                           u.first_name, u.last_name, u.image')
                 ->from('tutoring_sessions t')
                 ->join('availability_slots a', 'a.id = t.slot_id', 'left')
                 ->join('users u', 'u.id = t.student_id', 'left')
                 ->where('t.teacher_id', $teacher_id);
        if ($statuses) $this->db->where_in('t.status', $statuses);

        $rows = $this->db->order_by('a.starts_at', 'ASC')->order_by('t.id', 'ASC')
                         ->limit((int) $limit)->get()->result_array();

        $out = [];
        foreach ($rows as $r) {
            $name = trim((string) $r['first_name'] . ' ' . (string) $r['last_name']);
            $out[] = [
                'id'           => (int) $r['id'],
                'status'       => $r['status'],
                'student_id'   => (int) $r['student_id'],
                'student_name' => $name !== '' ? $name : 'طالب',
                'image'        => (string) $r['image'],
                'starts_at'    => $r['starts_at'],
                'when_text'    => $r['starts_at'] ? $this->when_text($r['starts_at']) : 'بلا موعد',
                'minutes'      => (int) $r['duration_min'],
            ];
        }
        return $out;
    }

    /**
     * يحسم المعلّم طلبًا: تأكيدًا أو اعتذارًا.
     * الملكيّة والحالة شرطان في الاستعلام: لا يُحسم طلب غيره، ولا يُحسم محسوم.
     *
     * @param string $decision confirm|decline
     */
    public function decide($session_id, $teacher_id, $decision)
    {
        $session_id = (int) $session_id;
        $teacher_id = (int) $teacher_id;
        if ($session_id <= 0 || $teacher_id <= 0) {
            return ['ok' => false, 'msg' => 'طلب غير مكتمل.'];
        }
        if (!in_array($decision, ['confirm', 'decline'], true)) {
            return ['ok' => false, 'msg' => 'إجراء غير معروف.'];
        }

        $row = $this->db->where('id', $session_id)->where('teacher_id', $teacher_id)
                        ->get('tutoring_sessions')->row_array();
        if (!$row) {
            return ['ok' => false, 'msg' => 'هذا الطلب ليس لك أو لم يعد موجودًا.'];
        }
        if ($row['status'] !== 'requested') {
            return ['ok' => false, 'msg' => 'هذا الطلب حُسم من قبل.'];
        }

        $new = ($decision === 'confirm') ? 'confirmed' : 'declined';
        $this->db->where('id', $session_id)->where('teacher_id', $teacher_id)
                 ->where('status', 'requested')
                 ->update('tutoring_sessions', ['status' => $new]);
        if ($this->db->affected_rows() < 1) {
            return ['ok' => false, 'msg' => 'تعذّر تحديث الطلب. حاول مرّة أخرى.'];
        }

        if (!empty($row['slot_id'])) {
            // التأكيد يحجز الموعد، والاعتذار يعيده مفتوحًا لطالب آخر.
            $this->db->where('id', (int) $row['slot_id'])
                     ->where('teacher_id', $teacher_id)
                     ->update('availability_slots', ['status' => ($new === 'confirmed' ? 'booked' : 'open')]);
        }

        return [
            'ok'  => true,
            'msg' => $new === 'confirmed' ? 'أُكِّد الطلب، وصار الموعد محجوزًا.' : 'اعتُذر عن الطلب، وعاد الموعد متاحًا.',
        ];
    }

    /* =====================================================================
       حجوزات الطالب
       ===================================================================== */

    /** حجوزات الطالب بمواعيدها ومعلّميها وحالتها كما في القاعدة. */
    public function bookings_for_student($student_id, $limit = 20)
    {
        $student_id = (int) $student_id;
        if ($student_id <= 0) return [];

        $rows = $this->db->select('t.id, t.status, t.slot_id,
                                   a.starts_at, a.duration_min,
                                   u.id AS teacher_id, u.first_name, u.last_name, u.image')
            ->from('tutoring_sessions t')
            ->join('availability_slots a', 'a.id = t.slot_id', 'left')
            ->join('users u', 'u.id = t.teacher_id', 'left')
            ->where('t.student_id', $student_id)
            ->order_by('a.starts_at', 'ASC')->order_by('t.id', 'DESC')
            ->limit((int) $limit)->get()->result_array();

        $subjects = $this->teacher_subjects();

        $out = [];
        foreach ($rows as $r) {
            $name = trim((string) $r['first_name'] . ' ' . (string) $r['last_name']);
            $out[] = [
                'id'        => (int) $r['id'],
                'status'    => $r['status'],
                'tutor'     => $name !== '' ? $name : 'معلّم',
                'image'     => (string) $r['image'],
                'subject'   => $subjects['name'][(int) $r['teacher_id']] ?? 'حصّة خاصّة',
                'starts_at' => $r['starts_at'],
                'when_text' => $r['starts_at'] ? $this->when_text($r['starts_at']) : 'بلا موعد',
            ];
        }
        return $out;
    }

    /** شارة الحالة: نصّها ونوعها. الحالات السبع كما في `tutoring_sessions.status`. */
    public function status_badge($status)
    {
        $map = [
            'requested' => ['due',      'بانتظار التأكيد'],
            'confirmed' => ['mastered', 'مؤكّد'],
            'live'      => ['progress', 'جارية الآن'],
            'completed' => ['idle',     'منتهية'],
            'declined'  => ['late',     'اعتذر المعلّم'],
            'expired'   => ['late',     'انتهت مهلته'],
            'refunded'  => ['idle',     'مُسترَدّ'],
        ];
        return $map[$status] ?? ['idle', $status];
    }
}
