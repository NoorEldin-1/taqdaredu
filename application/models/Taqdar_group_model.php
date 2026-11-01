<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * TQ-GROUP — الحصص الجماعية المباشرة: وحدة البيع السادسة.
 *
 * المجموعة منتج يباع مرة (صف في `subscriptions` بـ`group_id`)، وحصصها صفوف
 * بمواعيد في `tq_group_sessions`، وحضور كل عضو في `tq_group_attendance`.
 *
 * **والقاعدة الحاكمة هي قاعدة TQ-COURSE-SALE وTQ-BOOK وTQ-FND-PACK: لا محرك
 * ثان.** الشراء `Taqdar_billing_model::subscribe_group()` بفاتورته، تسويه تاب
 * بالفرع نفسه (`activate_from_gateway()` → `activate()`)، ويفعله المسؤول
 * بالزر نفسه، وينتهي اجله بـ`expire_due()` نفسها. وقيد المعلم **عند الدفع**
 * (D7-c) من `Taqdar_wallet_model::credit_group_sale()` بالاصل `groupsub:<id>`.
 *
 * **و`offer()` هي المصدر الواحد** لسؤال «ايباع؟ بكم؟ كم مقعدا بقي؟» —
 * والشاشات (TQ-GROUP، المهمة الخامسة) ومحرك الشراء نفسه يقرأون منها.
 *
 * والقلب **دوال صرفة** (`count_seats` · `seats_left_of` · `lead_of` · `split`
 * · `offer` و`display_state` بسياق محقون): بها يفحص
 * `tests/live_groups_test.php` القواعد بلا قاعدة بيانات، والنسخة الحية تجمع
 * السياق من القاعدة ثم تنادي القلب نفسه — فلا نسختان من القاعدة.
 *
 * **بلا مفتاح لا شيء يتغير**: `tq_group_sales_enabled` مطفأ افتراضا، فترد
 * `offer()` «لا يباع» لكل مجموعة.
 */
class Taqdar_group_model extends CI_Model
{
    const SCHEMA_V = '1';
    const T_GROUPS = 'tq_groups';
    const T_SESS   = 'tq_group_sessions';
    const T_ATT    = 'tq_group_attendance';

    /** الحالات الاربع لصف المجموعة. */
    public static $STATUSES = array('draft', 'review', 'published', 'closed');

    /** مفاتيح حالة العرض التسعة — مفردات المهمة الاولى نفسها. */
    public static $STATES = array('scheduled', 'ready', 'live', 'completed', 'recording_processing',
                                  'recording_available', 'missed', 'rescheduled', 'cancelled');

    /** ايام بقاء «سأرفعه لاحقا» قبل ان يسقط الى «لا تسجيل». */
    const RECORDING_WAIT_DAYS = 7;

    /* =====================================================================
       المخطط
       ===================================================================== */

    /**
     * ينشئ الجداول الثلاثة وعمود `subscriptions.group_id` ويوسع ENUM البنود.
     * تنادى **قبل كل قراءة**: صفحة عامة تقرأ جدولا قبل انشائه تبيض لكل زائر.
     */
    public function ensure_schema($force = false)
    {
        static $done = false;
        if ($done && !$force) return;
        $done = true;

        if (!$force && (string) $this->setting('tq_group_schema_v', '') === self::SCHEMA_V) return;

        $this->try_sql(
            'CREATE TABLE IF NOT EXISTS `' . self::T_GROUPS . '` (
               `id`              INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
               `title`           VARCHAR(190) NOT NULL,
               `slug`            VARCHAR(120) NOT NULL,
               `description`     TEXT NULL DEFAULT NULL,
               `image`           VARCHAR(255) NULL DEFAULT NULL,
               `teacher_id`      INT(10) NOT NULL DEFAULT 0,
               `grade_id`        INT(10) NOT NULL DEFAULT 0,
               `subject_id`      INT(10) NOT NULL DEFAULT 0,
               `course_id`       INT(10) NOT NULL DEFAULT 0,
               `price`           BIGINT NOT NULL DEFAULT 0,
               `teacher_percent` DECIMAL(5,2) NULL DEFAULT NULL,
               `capacity`        INT(10) UNSIGNED NOT NULL DEFAULT 0,
               `access_days`     INT(10) UNSIGNED NOT NULL DEFAULT 0,
               `enroll_until`    DATETIME NULL DEFAULT NULL,
               `status`          ENUM("draft","review","published","closed") NOT NULL DEFAULT "draft",
               `created_at`      DATETIME NULL DEFAULT NULL,
               PRIMARY KEY (`id`),
               UNIQUE KEY `uq_grp_slug` (`slug`),
               KEY `idx_grp_status` (`status`),
               KEY `idx_grp_teacher` (`teacher_id`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->try_sql(
            'CREATE TABLE IF NOT EXISTS `' . self::T_SESS . '` (
               `id`                  INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
               `group_id`            INT(10) UNSIGNED NOT NULL,
               `title`               VARCHAR(190) NOT NULL DEFAULT "",
               `starts_at`           DATETIME NOT NULL,
               `duration_min`        SMALLINT(5) UNSIGNED NOT NULL DEFAULT 60,
               `meet_url`            VARCHAR(500) NOT NULL DEFAULT "",
               `status`              ENUM("scheduled","live","completed","cancelled") NOT NULL DEFAULT "scheduled",
               `cancel_reason`       VARCHAR(255) NULL DEFAULT NULL,
               `rescheduled_from_at` DATETIME NULL DEFAULT NULL,
               `teacher_joined_at`   DATETIME NULL DEFAULT NULL,
               `recording_url`       VARCHAR(500) NOT NULL DEFAULT "",
               `recording_pending`   TINYINT(1) NOT NULL DEFAULT 0,
               `completed_at`        DATETIME NULL DEFAULT NULL,
               PRIMARY KEY (`id`),
               KEY `idx_gs_group` (`group_id`,`starts_at`),
               KEY `idx_gs_status` (`status`,`starts_at`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->try_sql(
            'CREATE TABLE IF NOT EXISTS `' . self::T_ATT . '` (
               `session_id` INT(10) UNSIGNED NOT NULL,
               `student_id` INT(10) NOT NULL,
               `joined_at`  DATETIME NULL DEFAULT NULL,
               `attendance` ENUM("present","absent") NULL DEFAULT NULL,
               UNIQUE KEY `uq_ga_session_student` (`session_id`,`student_id`),
               KEY `idx_ga_student` (`student_id`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->try_sql('ALTER TABLE `subscriptions` ADD COLUMN IF NOT EXISTS `group_id` INT NOT NULL DEFAULT 0');
        $this->try_sql('ALTER TABLE `subscriptions` ADD INDEX `idx_sub_group` (`group_id`,`status`)');

        /* **وبند `group` يضاف الى ENUM قبل ان يكتب** — درس TQ-FND-PACK: قيمة
           خارج السرد تكتب نصا فارغا بلا خطأ. والسرد يقرأ من المخطط ولا يكتب
           بيد: قائمة مكتوبة هنا تسقط قيمة اضافها غيرنا بعدها، و MODIFY الى
           سرد اضيق يحول تلك القيم الى `''` في كل صف قائم. */
        $this->extend_items_enum('group');

        $this->db->data_cache = array();
        $this->put_setting('tq_group_schema_v', self::SCHEMA_V);
    }

    private function extend_items_enum($value)
    {
        try {
            $row = $this->db->query(
                'SELECT `COLUMN_TYPE` t FROM `information_schema`.`COLUMNS`
                  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = "subscription_items"
                    AND `COLUMN_NAME` = "entity_type"'
            )->row_array();
        } catch (Throwable $e) { $this->db->reset_query(); return; }
        if (!$row) return;
        $type = self::enum_with((string) $row['t'], $value);
        if ($type !== null) {
            $this->try_sql("ALTER TABLE `subscription_items` MODIFY COLUMN `entity_type` $type NOT NULL DEFAULT 'all'");
        }
    }

    /**
     * يضيف قيمة الى سرد ENUM ولا ينزع شيئا — صرفة: ترد `null` ان كانت
     * موجودة (او لم يفهم النوع)، والا التعريف الجديد بكل القائم ثم الجديد.
     */
    public static function enum_with($column_type, $value)
    {
        if (!preg_match('/^enum\((.*)\)$/is', trim((string) $column_type), $m)) return null;
        preg_match_all("/'((?:[^']|'')*)'/", $m[1], $mm);
        $vals = array_map(function ($v) { return str_replace("''", "'", $v); }, $mm[1]);
        if (in_array($value, $vals, true)) return null;
        $vals[] = $value;
        return 'ENUM(' . implode(',', array_map(function ($v) {
            return "'" . str_replace("'", "''", $v) . "'";
        }, $vals)) . ')';
    }

    /**
     * حارس `group` في السرد **مستقلا عن رقم الاصدار** — مرة لكل طلب. تجهيز
     * آخر قد يعيد كتابة السرد بعد ان مر تجهيزنا، فيكتب بند `group` نصا
     * فارغا بلا خطأ. فينادى قبل كل كتابة بند (فحص `information_schema` رخيص).
     */
    public function ensure_item_enum()
    {
        static $ok = false;
        if ($ok) return;
        $ok = true;
        $this->extend_items_enum('group');
    }

    /** ريال (نص النموذج) الى هللات — تحويل نوع `money` في `spec()` نفسه. */
    public static function to_halalas($riyal)
    {
        $s = trim(str_replace(',', '', (string) $riyal));
        return max(0, (int) round(((float) $s) * 100));
    }

    /* =====================================================================
       المفاتيح
       ===================================================================== */

    /** `tq_group_sales_enabled` — مطفأ افتراضا. */
    public function enabled()
    {
        return (string) get_settings('tq_group_sales_enabled') === '1';
    }

    /** مهلة حجز المقعد للمعلق بالساعات (`tq_group_hold_hours`، افتراضه ٢). */
    public function hold_hours()
    {
        $v = get_settings('tq_group_hold_hours');
        $v = ($v === null || trim((string) $v) === '') ? 2 : (int) $v;
        return max(1, min(168, $v));
    }

    /** مهلة السماح بعد نهاية الحصة — مفتاح الحصص الفردية نفسه. */
    public function grace_hours()
    {
        $v = get_settings('tq_session_grace_hours');
        $v = ($v === null || trim((string) $v) === '') ? 6 : (int) $v;
        return max(1, min(72, $v));
    }

    /**
     * مهلة الدخول قبل الموعد لدور بعينه — قاعدة المهمة الاولى حرفا، وتقرأ
     * المفاتيح نفسها **بلا نداء نموذج الحصص**: الجديد لطرفه، والا القديم
     * `tq_session_join_lead_min` ان وجد صفه، والا ١٠ للطالب و١٥ للمعلم.
     *
     * @param array  $have مفتاح => قيمة (ما وجد صفه)
     * @param string $actor student|teacher|admin
     */
    public static function lead_of(array $have, $actor)
    {
        $key = in_array($actor, array('teacher', 'admin'), true)
             ? 'tq_session_join_lead_teacher' : 'tq_session_join_lead_student';
        $set = function ($k) use ($have) { return isset($have[$k]) && trim((string) $have[$k]) !== ''; };

        if ($set($key))                            $v = (int) $have[$key];
        elseif ($set('tq_session_join_lead_min'))  $v = (int) $have['tq_session_join_lead_min'];
        else                                       $v = $key === 'tq_session_join_lead_teacher' ? 15 : 10;
        return max(0, min(240, $v));
    }

    /** المهلتان من `settings` الحية. */
    public function leads()
    {
        $have = array();
        foreach (array('tq_session_join_lead_student', 'tq_session_join_lead_teacher', 'tq_session_join_lead_min') as $k) {
            $v = get_settings($k);
            if ($v !== null) $have[$k] = $v;
        }
        return array('student' => self::lead_of($have, 'student'), 'teacher' => self::lead_of($have, 'teacher'));
    }

    /**
     * نصيب المعلم: ما كتب للمجموعة، والا `tq_group_teacher_percent` (٦٠).
     * **والفارغ غير الصفر** (TQ-NULLNUM): صفر قرار، والفراغ «خذ العام».
     */
    public function teacher_percent_of($group)
    {
        $v = is_array($group) && array_key_exists('teacher_percent', $group) ? $group['teacher_percent'] : null;
        if ($v === null || trim((string) $v) === '') {
            $v = get_settings('tq_group_teacher_percent');
            if ($v === null || trim((string) $v) === '') $v = 60;
        }
        return (float) max(0, min(100, (float) $v));
    }

    /**
     * قسمة المبيع بين المعلم والمنصة — التقريب مرة والباقي للمنصة، فالمجموع
     * يساوي المبيع بالهللة. وهي حسبة `credit_course_sale()` نفسها.
     */
    public static function split($gross_halalas, $percent)
    {
        $gross = max(0, (int) $gross_halalas);
        $p     = max(0, min(100, (float) $percent));
        $share = (int) round($gross * $p / 100);
        $share = max(0, min($gross, $share));
        return array('share' => $share, 'cut' => $gross - $share);
    }

    /* =====================================================================
       القراءة
       ===================================================================== */

    public function get($id)
    {
        $this->ensure_schema();
        try {
            return $this->db->where('id', (int) $id)->get(self::T_GROUPS)->row_array() ?: null;
        } catch (Throwable $e) { $this->db->reset_query(); return null; }
    }

    public function by_slug($slug)
    {
        $this->ensure_schema();
        try {
            return $this->db->where('slug', (string) $slug)->get(self::T_GROUPS)->row_array() ?: null;
        } catch (Throwable $e) { $this->db->reset_query(); return null; }
    }

    /** المنشور — بمرشحات `grade_id` · `subject_id` · `teacher_id`. */
    public function published($filters = array())
    {
        $this->ensure_schema();
        try {
            $this->db->where('status', 'published');
            foreach (array('grade_id', 'subject_id', 'teacher_id', 'course_id') as $k) {
                if (!empty($filters[$k])) $this->db->where($k, (int) $filters[$k]);
            }
            return $this->db->order_by('created_at', 'DESC')->get(self::T_GROUPS)->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); return array(); }
    }

    public function for_teacher($teacher_id)
    {
        $this->ensure_schema();
        try {
            return $this->db->where('teacher_id', (int) $teacher_id)->order_by('id', 'DESC')
                            ->get(self::T_GROUPS)->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); return array(); }
    }

    public function sessions_of($group_id)
    {
        $this->ensure_schema();
        try {
            return $this->db->where('group_id', (int) $group_id)->order_by('starts_at', 'ASC')
                            ->get(self::T_SESS)->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); return array(); }
    }

    public function session($gs_id)
    {
        $this->ensure_schema();
        try {
            return $this->db->where('id', (int) $gs_id)->get(self::T_SESS)->row_array() ?: null;
        } catch (Throwable $e) { $this->db->reset_query(); return null; }
    }

    /* =====================================================================
       المقاعد — D7
       ===================================================================== */

    /**
     * يعد المقاعد المشغولة من صفوف `subscriptions` — صرفة.
     *
     * المشغول: سار (`active`، او `cancelled` بعد الدفع ولم يبلغ اجله)، ومعلق
     * لم تمض مهلة حجزه. والمعلق القديم لا يحجز: من ترك فاتورته لا يحبس
     * المقعد عن غيره.
     *
     * @param int|null $before_id ان اعطي عد الصفوف الاقدم منه وحدها — به
     *                            يعاد العد بعد الادراج فياخذ الاسبق المقعد.
     */
    public static function count_seats(array $rows, $now, $hold_hours, $before_id = null)
    {
        $n = 0;
        foreach ($rows as $r) {
            if ($before_id !== null && (int) $r['id'] >= (int) $before_id) continue;
            $status = (string) $r['status'];
            $ends   = !empty($r['ends_at']) ? strtotime((string) $r['ends_at']) : 0;

            if ($status === 'active') {
                if ($ends === 0 || $ends >= $now) $n++;
            } elseif ($status === 'cancelled') {
                if ($ends > 0 && $ends >= $now) $n++;   // ألغي قبل الدفع = بلا اجل
            } elseif ($status === 'pending') {
                $made = !empty($r['created_at']) ? strtotime((string) $r['created_at']) : 0;
                if ($made > 0 && $made + (int) $hold_hours * 3600 > $now) $n++;
            }
        }
        return $n;
    }

    /** المقاعد الباقية — `null` بلا حد (السعة صفر). */
    public static function seats_left_of($group, $taken)
    {
        $cap = (int) (isset($group['capacity']) ? $group['capacity'] : 0);
        if ($cap <= 0) return null;
        return max(0, $cap - (int) $taken);
    }

    /**
     * ايتجاوز تفعيل هذا الصف السعة؟ — صرفة. تعد المقاعد **سواه**: دفعة سويت
     * بعد ان سقطت مهلة حجزها قد يكون مقعدها اخذه غيره.
     */
    public static function over_capacity($group, array $rows, $now, $hold_hours, $sub_id)
    {
        $cap = (int) (isset($group['capacity']) ? $group['capacity'] : 0);
        if ($cap <= 0) return false;
        $others = array_values(array_filter($rows, function ($r) use ($sub_id) { return (int) $r['id'] !== (int) $sub_id; }));
        return self::count_seats($others, $now, $hold_hours) >= $cap;
    }

    /** النسخة الحية من `over_capacity()`. */
    public function activation_over_capacity($group, $sub_id)
    {
        return self::over_capacity($group, $this->seat_rows((int) $group['id']), time(), $this->hold_hours(), $sub_id);
    }

    private function seat_rows($group_id)
    {
        try {
            return $this->db->select('id, status, created_at, ends_at')
                            ->where('group_id', (int) $group_id)
                            ->where_in('status', array('active', 'cancelled', 'pending'))
                            ->get('subscriptions')->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); return array(); }
    }

    public function seats_taken($group_id, $before_id = null)
    {
        $this->ensure_schema();
        return self::count_seats($this->seat_rows($group_id), time(), $this->hold_hours(), $before_id);
    }

    public function seats_left($group)
    {
        if (!is_array($group)) $group = $this->get((int) $group);
        if (!$group) return 0;
        return self::seats_left_of($group, $this->seats_taken((int) $group['id']));
    }

    /* =====================================================================
       العرض — المصدر الواحد
       ===================================================================== */

    /**
     * ايباع؟ بكم؟ لمن؟ — كل الشاشات ومحرك الشراء نفسه تقرأ من هنا.
     *
     * @param array|int  $group
     * @param int        $student_id ان اعطي فحص «اهو عضو قائم؟»
     * @param array|null $ctx        سياق محقون (enabled · seats_taken · member
     *                               · now) — للاختبار؛ والحي يجمعه هنا
     * @return array sellable · reason · why · price_halalas · list_halalas
     *               · seats_left · teacher_percent
     */
    public function offer($group, $student_id = 0, $ctx = null)
    {
        if (!is_array($group)) $group = $this->get((int) $group);

        if ($ctx === null) {
            $gid = $group ? (int) $group['id'] : 0;
            $ctx = array(
                'enabled'     => $this->enabled(),
                'seats_taken' => $gid ? $this->seats_taken($gid) : 0,
                'member'      => ($gid && (int) $student_id > 0) ? $this->has_group((int) $student_id, $gid) : false,
                'now'         => time(),
            );
        }
        $now = isset($ctx['now']) ? (int) $ctx['now'] : time();

        /* `price` بالهللات — نوع `money` في `spec()` يخزن هللات، فلا تقريب
           الى ريال كامل يضيع كسرا. */
        $price = $group ? max(0, (int) $group['price']) : 0;
        $out = array(
            'sellable'        => false,
            'reason'          => 'missing',
            'why'             => t('لا مجموعة بهذا الرقم.'),
            'price_halalas'   => $price,
            'list_halalas'    => $price,
            'seats_left'      => $group ? self::seats_left_of($group, (int) $ctx['seats_taken']) : 0,
            'teacher_percent' => $group ? $this->teacher_percent_of($group) : 0.0,
            'id'              => $group ? (int) $group['id'] : 0,
            'title'           => $group ? (string) $group['title'] : '',
            'teacher_id'      => $group ? (int) $group['teacher_id'] : 0,
        );
        if (!$group) return $out;

        $status = (string) $group['status'];
        $until  = !empty($group['enroll_until']) ? strtotime((string) $group['enroll_until']) : 0;

        if (empty($ctx['enabled'])) {
            $out['reason'] = 'disabled';
            $out['why']    = t('بيع المجموعات المباشرة غير مفعل في المنصة.');
        } elseif ($status === 'closed') {
            $out['reason'] = 'closed';
            $out['why']    = t('اغلقت هذه المجموعة ولا تقبل اعضاء جددا.');
        } elseif ($status !== 'published') {
            $out['reason'] = 'draft';
            $out['why']    = $status === 'review'
                           ? t('المجموعة بانتظار مراجعة الادارة، ولا تباع قبل نشرها.')
                           : t('المجموعة مسودة لم تنشر بعد.');
        } elseif (!empty($ctx['member'])) {
            $out['reason'] = 'member';
            $out['why']    = t('انت عضو في هذه المجموعة بالفعل.');
        } elseif ($until > 0 && $until < $now) {
            $out['reason'] = 'enroll_closed';
            $out['why']    = t('انتهى موعد الانضمام الى هذه المجموعة.');
        } elseif ($out['seats_left'] !== null && $out['seats_left'] <= 0) {
            $out['reason'] = 'full';
            $out['why']    = t('اكتملت مقاعد هذه المجموعة.');
        } elseif ($price <= 0) {
            $out['reason'] = 'no_price';
            $out['why']    = t('لا سعر لهذه المجموعة بعد، فلا تباع.');
        } else {
            $out['sellable'] = true;
            $out['reason']   = 'ok';
            $out['why']      = '';
        }
        return $out;
    }

    /* =====================================================================
       العضوية
       ===================================================================== */

    /** صفوف العضوية السارية لهذا الطالب في هذه المجموعة. */
    private function live_member_rows($student_id, $group_id = 0)
    {
        try {
            $this->db->where('user_id', (int) $student_id)
                     ->where('group_id >', 0)
                     ->where_in('status', array('active', 'cancelled'));
            if ($group_id > 0) $this->db->where('group_id', (int) $group_id);
            $rows = $this->db->get('subscriptions')->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); return array(); }

        $now = time();
        return array_values(array_filter($rows, function ($r) use ($now) {
            $ends = !empty($r['ends_at']) ? strtotime((string) $r['ends_at']) : 0;
            if ($r['status'] === 'cancelled') return $ends > 0 && $ends >= $now;
            return $ends === 0 || $ends >= $now;
        }));
    }

    /**
     * ايملك هذا الطالب عضوية سارية؟ — بقاعدة `active_subscriptions()` نفسها
     * (الملغى بعد الدفع سار حتى اجله). يستعلم حيا كالكتاب: الحصص لا تجسد
     * في `enrol`، ولا يقرؤها غير شاشات المجموعة.
     */
    public function has_group($student_id, $group_id)
    {
        if ((int) $student_id <= 0 || (int) $group_id <= 0) return false;
        $this->ensure_schema();
        return (bool) $this->live_member_rows($student_id, $group_id);
    }

    public function groups_of_student($student_id)
    {
        $this->ensure_schema();
        $ids = array();
        foreach ($this->live_member_rows($student_id) as $r) $ids[(int) $r['group_id']] = 1;
        if (!$ids) return array();
        try {
            return $this->db->where_in('id', array_keys($ids))->get(self::T_GROUPS)->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); return array(); }
    }

    /** الاعضاء الساريون: معرف · اسم · بريد · معرف الاشتراك. */
    public function members_of($group_id)
    {
        $this->ensure_schema();
        try {
            $rows = $this->db->select('s.id AS subscription_id, s.user_id, s.status, s.ends_at, u.first_name, u.last_name, u.email')
                             ->from('subscriptions s')
                             ->join('users u', 'u.id = s.user_id', 'left')
                             ->where('s.group_id', (int) $group_id)
                             ->where_in('s.status', array('active', 'cancelled'))
                             ->get()->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); return array(); }

        $now = time();
        $out = array();
        foreach ($rows as $r) {
            $ends = !empty($r['ends_at']) ? strtotime((string) $r['ends_at']) : 0;
            if ($r['status'] === 'cancelled' ? !($ends > 0 && $ends >= $now) : !($ends === 0 || $ends >= $now)) continue;
            $r['name'] = trim($r['first_name'] . ' ' . $r['last_name']);
            $out[(int) $r['user_id']] = $r;
        }
        return array_values($out);
    }

    /* =====================================================================
       الدخول والحالة — قواعد المهمة الاولى + شرط العضوية
       ===================================================================== */

    private function ctx_for($gsession, $user_id, $actor, $ctx)
    {
        if ($ctx === null) $ctx = array();
        if (!isset($ctx['now']))         $ctx['now'] = time();
        if (!isset($ctx['grace_hours'])) $ctx['grace_hours'] = $this->grace_hours();
        if (!isset($ctx['lead_student']) || !isset($ctx['lead_teacher'])) {
            $l = $this->leads();
            $ctx += array('lead_student' => $l['student'], 'lead_teacher' => $l['teacher']);
        }
        if (!array_key_exists('member', $ctx)) {
            $ctx['member'] = $this->is_party($gsession, $user_id, $actor);
        }
        if (!array_key_exists('attendance', $ctx)) {
            $ctx['attendance'] = $actor === 'student' ? $this->attendance_of((int) $gsession['id'], (int) $user_id) : null;
        }
        return $ctx;
    }

    /** طرف في الحصة؟ الطالب بعضويته، والمعلم بملكيته، والمسؤول دائما. */
    private function is_party($gsession, $user_id, $actor)
    {
        if ($actor === 'admin') return true;
        $g = $this->get((int) $gsession['group_id']);
        if (!$g) return false;
        if ($actor === 'teacher') return (int) $g['teacher_id'] === (int) $user_id;
        return $this->has_group((int) $user_id, (int) $g['id']);
    }

    private function attendance_of($gs_id, $student_id)
    {
        try {
            $r = $this->db->where('session_id', (int) $gs_id)->where('student_id', (int) $student_id)
                          ->get(self::T_ATT)->row_array();
        } catch (Throwable $e) { $this->db->reset_query(); return null; }
        if (!$r) return null;
        if (!empty($r['attendance'])) return (string) $r['attendance'];
        return !empty($r['joined_at']) ? 'present' : null;
    }

    /**
     * ايفتح الآن؟ — بشكل `Taqdar_sessions_model::join_state()` (المهمة الاولى)
     * ومعه `opens_ts`.
     */
    public function join_state($gsession, $user_id, $actor, $ctx = null)
    {
        $d = $this->display_state($gsession, $user_id, $actor, $ctx);
        return array(
            'can_join'     => $d['can_join'],
            'can_complete' => $d['can_complete'],
            'is_over'      => $d['is_over'],
            'starts_ts'    => $d['starts_ts'],
            'ends_ts'      => $d['ends_ts'],
            'opens_ts'     => $d['opens_ts'],
            'note'         => $d['note'],
        );
    }

    /**
     * حالة العرض المشتقة — لا تخزن. ومفاتيحها مفردات المهمة الاولى التسعة
     * التي تعني حصة جماعية؛ وحالات الدفع للعضوية لا للحصة.
     *
     * @param string $actor student|teacher|admin
     * @param array|null $ctx now · grace_hours · lead_student · lead_teacher
     *                        · member · attendance (present|absent|null)
     * @return array state · label · tone · can_join · can_complete · is_over
     *               · note · starts_ts · ends_ts · opens_ts
     */
    public function display_state($gsession, $user_id, $actor, $ctx = null)
    {
        $actor  = in_array($actor, array('teacher', 'admin'), true) ? $actor : 'student';
        $ctx    = $this->ctx_for($gsession, $user_id, $actor, $ctx);
        $now    = (int) $ctx['now'];
        $lead   = $actor === 'student' ? (int) $ctx['lead_student'] : (int) $ctx['lead_teacher'];
        $status = (string) $gsession['status'];
        $starts = !empty($gsession['starts_at']) ? strtotime((string) $gsession['starts_at']) : 0;
        $mins   = max(1, (int) ($gsession['duration_min'] ?? 60));
        $ends   = $starts > 0 ? $starts + $mins * 60 : 0;
        $dead   = $ends > 0 ? $ends + (int) $ctx['grace_hours'] * 3600 : 0;
        $opens  = $starts > 0 ? $starts - $lead * 60 : 0;
        $link   = trim((string) ($gsession['meet_url'] ?? ''));
        $member = !empty($ctx['member']);

        $is_over = in_array($status, array('completed', 'cancelled'), true) || ($dead > 0 && $dead < $now);
        $note    = '';

        if ($status === 'cancelled') {
            $state = 'cancelled';
            $r = trim((string) ($gsession['cancel_reason'] ?? ''));
            $note = $r !== '' ? $r : 'الغيت هذه الحصة.';
        } elseif ($is_over) {
            if (trim((string) ($gsession['recording_url'] ?? '')) !== '') {
                $state = 'recording_available';
            } elseif ((int) ($gsession['recording_pending'] ?? 0) === 1) {
                $state = 'recording_processing';
                $note  = 'جار تجهيز التسجيل.';
            } elseif ($actor === 'student' && $member && $ctx['attendance'] !== 'present') {
                $state = 'missed';
                $note  = 'لم تحضر هذه الحصة.';
            } else {
                $state = 'completed';
                $note  = 'انتهت الحصة، واغلق رابطها.';
            }
        } elseif ($status === 'live' || ($starts > 0 && $starts <= $now)) {
            $state = 'live';
        } elseif ($opens > 0 && $opens <= $now) {
            $state = 'ready';
        } elseif (!empty($gsession['rescheduled_from_at'])) {
            $state = 'rescheduled';
            $note  = 'اجلت هذه الحصة الى موعد جديد.';
        } else {
            $state = 'scheduled';
            $note  = 'الرابط يفتح قبل الموعد بـ' . $lead . ' دقيقة.';
        }

        $open_now = in_array($state, array('ready', 'live'), true);
        if ($open_now && !$member)      $note = 'الدخول لاعضاء المجموعة وحدهم.';
        elseif ($open_now && $link === '') $note = 'لا رابط بعد — راسل معلمك.';

        static $labels = array(
            'scheduled'            => array('مجدولة', 'neutral'),
            'ready'                => array('جاهزة للدخول', 'info'),
            'live'                 => array('مباشرة الآن', 'success'),
            'completed'            => array('انتهت', 'neutral'),
            'recording_processing' => array('جار تجهيز التسجيل', 'warning'),
            'recording_available'  => array('التسجيل متاح', 'success'),
            'missed'               => array('فاتتك', 'danger'),
            'rescheduled'          => array('مؤجلة', 'warning'),
            'cancelled'            => array('ملغاة', 'danger'),
        );

        return array(
            'state'        => $state,
            'label'        => t($labels[$state][0]),
            'tone'         => $labels[$state][1],
            'can_join'     => $open_now && $member && $link !== '',
            'can_complete' => $actor !== 'student' && $member && in_array($status, array('scheduled', 'live'), true)
                              && $starts > 0 && $starts <= $now,
            'is_over'      => $is_over,
            'note'         => $note !== '' ? t($note) : '',
            'starts_ts'    => $starts,
            'ends_ts'      => $ends,
            'opens_ts'     => $opens,
        );
    }

    /**
     * يسجل النقرة على «انضم» — الطالب صف حضور (اول نقرة تبقى)، والمعلم
     * `teacher_joined_at` وينقل الحصة الى `live`. ويرد الرابط ليحول اليه.
     *
     * @return array ok · url · errors
     */
    public function record_join($gsession_id, $user_id, $actor)
    {
        $gs = $this->session($gsession_id);
        if (!$gs) return array('ok' => false, 'errors' => array(t('لا حصة بهذا الرقم.')));

        $j = $this->join_state($gs, $user_id, $actor);
        if (!$j['can_join']) {
            return array('ok' => false, 'errors' => array($j['note'] !== '' ? $j['note'] : t('لا يفتح رابط هذه الحصة الآن.')));
        }

        $now = date('Y-m-d H:i:s');
        try {
            if ($actor === 'student') {
                $this->db->query(
                    'INSERT INTO `' . self::T_ATT . '` (`session_id`,`student_id`,`joined_at`) VALUES (?,?,?)
                     ON DUPLICATE KEY UPDATE `joined_at` = COALESCE(`joined_at`, VALUES(`joined_at`))',
                    array((int) $gs['id'], (int) $user_id, $now)
                );
            } else {
                $this->db->where('id', (int) $gs['id'])->where('teacher_joined_at IS NULL', null, false)
                         ->update(self::T_SESS, array('teacher_joined_at' => $now));
                $this->db->where('id', (int) $gs['id'])->where('status', 'scheduled')
                         ->update(self::T_SESS, array('status' => 'live'));
            }
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-GROUP record_join #' . (int) $gs['id'] . ': ' . $e->getMessage());
        }
        return array('ok' => true, 'url' => (string) $gs['meet_url']);
    }

    /* =====================================================================
       الكتابة — المجموعة
       ===================================================================== */

    /**
     * يحفظ مجموعة (انشاء او تعديل). يكتب ما ارسل وحده.
     *
     * المعلم: `teacher_id` نفسه، ولا يملك `teacher_percent` (قرار مال)،
     * و`published` منه تصير `review` بحكم `may_publish()` — كالدرس والكورس.
     * و`closed` يمر لصاحب المجموعة: الاغلاق لا ينشر شيئا.
     *
     * @return array ok · id · status · staged · errors
     */
    public function save_group($data, $actor_id, $actor_role)
    {
        $this->ensure_schema();
        $admin = $actor_role === 'admin';
        $id    = (int) ($data['id'] ?? 0);
        $old   = $id ? $this->get($id) : null;
        if ($id && !$old) return array('ok' => false, 'errors' => array(t('لا مجموعة بهذا الرقم.')));
        if ($old && !$admin && (int) $old['teacher_id'] !== (int) $actor_id) {
            return array('ok' => false, 'errors' => array(t('هذه المجموعة ليست لك.')));
        }

        $row = array();
        foreach (array('title', 'description', 'image') as $k) {
            if (array_key_exists($k, $data)) $row[$k] = trim((string) $data[$k]);
        }
        if (array_key_exists('slug', $data)) {
            $row['slug'] = trim(preg_replace('/[^a-z0-9-]+/', '-', strtolower((string) $data['slug'])), '-');
        }
        foreach (array('grade_id', 'subject_id', 'course_id', 'capacity', 'access_days') as $k) {
            if (array_key_exists($k, $data)) $row[$k] = max(0, (int) $data[$k]);
        }
        // السعر يدخل بالريال ويخزن بالهللات — تحويل `money` في `spec()` نفسه.
        if (array_key_exists('price', $data)) $row['price'] = self::to_halalas($data['price']);
        if (array_key_exists('enroll_until', $data)) {
            $ts = trim((string) $data['enroll_until']) !== '' ? strtotime((string) $data['enroll_until']) : false;
            $row['enroll_until'] = $ts ? date('Y-m-d H:i:s', $ts) : null;
        }
        if ($admin) {
            if (array_key_exists('teacher_id', $data)) $row['teacher_id'] = (int) $data['teacher_id'];
            if (array_key_exists('teacher_percent', $data)) {
                $tp = trim((string) $data['teacher_percent']);
                $row['teacher_percent'] = $tp === '' ? null : max(0, min(100, (float) $tp));
            }
        } elseif (!$old) {
            $row['teacher_id'] = (int) $actor_id;
        }

        $staged = false;
        if (array_key_exists('status', $data)) {
            $st = in_array($data['status'], self::$STATUSES, true) ? $data['status'] : 'draft';
            if ($st === 'published' && !$this->may_publish($actor_role)) { $st = 'review'; $staged = true; }
            $row['status'] = $st;
        }

        if (!$old) {
            if (($row['title'] ?? '') === '') return array('ok' => false, 'errors' => array(t('عنوان المجموعة مطلوب.')));
            if (empty($row['teacher_id']))    return array('ok' => false, 'errors' => array(t('المجموعة تحتاج معلما.')));
            $row += array('status' => 'draft', 'created_at' => date('Y-m-d H:i:s'));
        } elseif (isset($row['title']) && $row['title'] === '') {
            return array('ok' => false, 'errors' => array(t('عنوان المجموعة مطلوب.')));
        }

        if (array_key_exists('slug', $row) && $row['slug'] === '') unset($row['slug']);
        if (isset($row['slug'])) {
            $dup = $this->db->where('slug', $row['slug'])->where('id !=', $id)->count_all_results(self::T_GROUPS);
            if ($dup > 0) return array('ok' => false, 'errors' => array(t('هذا الاسم في الرابط مستعمل لمجموعة اخرى.')));
        }

        try {
            if ($old) {
                if ($row) $this->db->where('id', $id)->update(self::T_GROUPS, $row);
            } else {
                if (!isset($row['slug'])) $row['slug'] = 'group-' . substr(bin2hex(random_bytes(4)), 0, 8);
                $this->db->insert(self::T_GROUPS, $row);
                $id = (int) $this->db->insert_id();
            }
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-GROUP save_group: ' . $e->getMessage());
            return array('ok' => false, 'errors' => array(t('تعذر حفظ المجموعة.')));
        }

        $g = $this->get($id);
        return array('ok' => true, 'id' => $id, 'status' => $g ? $g['status'] : null, 'staged' => $staged,
                     'message' => $staged ? t('حفظت المجموعة، وهي بانتظار مراجعة الادارة قبل نشرها.') : t('حفظت المجموعة.'));
    }

    /** قاعدة النشر الواحدة — `Taqdar_curriculum_model::may_publish()`. */
    private function may_publish($role)
    {
        $this->load->model('taqdar_curriculum_model');
        return (bool) $this->taqdar_curriculum_model->may_publish(array('role' => (string) $role));
    }

    /** المراجعة: `review` ← `published`، ويبلغ صاحبها. */
    public function approve_group($id)
    {
        $g = $this->get($id);
        if (!$g || $g['status'] !== 'review') return array('ok' => false, 'errors' => array(t('ليست بانتظار المراجعة.')));
        $this->db->where('id', (int) $id)->update(self::T_GROUPS, array('status' => 'published'));
        $this->notify((int) $g['teacher_id'], t('نشرت مجموعتك'),
                      t('اعتمدت الادارة مجموعة «____» ونشرتها.', array($g['title'])), 'system');
        $this->audit('group.approve', self::T_GROUPS . '#' . (int) $id, $g, $this->get($id));
        return array('ok' => true);
    }

    /** الرفض ينزل الى `draft` ويطلب سببه — «مرفوض» وحدها لا تقول ما يصحح. */
    public function reject_group($id, $reason)
    {
        $reason = trim((string) $reason);
        if ($reason === '') return array('ok' => false, 'errors' => array(t('اكتب سبب الرفض — به يعرف المعلم ما يصحح.')));
        $g = $this->get($id);
        if (!$g || $g['status'] !== 'review') return array('ok' => false, 'errors' => array(t('ليست بانتظار المراجعة.')));
        $this->db->where('id', (int) $id)->update(self::T_GROUPS, array('status' => 'draft'));
        $this->notify((int) $g['teacher_id'], t('اعيدت مجموعتك للتعديل'),
                      t('لم تنشر مجموعة «____»: ____', array($g['title'], $reason)), 'system');
        $this->audit('group.reject', self::T_GROUPS . '#' . (int) $id, $g, array('reason' => $reason));
        return array('ok' => true);
    }

    /**
     * يغلق المجموعة: لا تباع بعدها، والاعضاء على ما دفعوا.
     *
     * **والاغلاق قبل اول حصة رد للمال** (D7-c): لم يعط شيء مما بيع، فتعكس
     * قيود المعلم، ويلغى كل اشتراك عضو ويقطع وصوله الآن، ويكتب في
     * `audit_log` (`group.refund_due`) — **ورد المال نفسه خارج المنصة**:
     * محرك الفوترة لا يملك مسار استرداد للاشتراك (`refunded` حالة فاتورة
     * لا اشتراك)، فالصف `cancelled` والسجل يقول ان الرد مستحق.
     * والمعلق يشطب بفاتورته (`cancel_pending_invoice()`).
     *
     * @return array ok · refunded (عدد) · voided (عدد المعلق) · before_start
     */
    public function close_group($id)
    {
        $g = $this->get($id);
        if (!$g) return array('ok' => false, 'errors' => array(t('لا مجموعة بهذا الرقم.')));

        $this->db->where('id', (int) $id)->update(self::T_GROUPS, array('status' => 'closed'));

        $first = null;
        foreach ($this->sessions_of((int) $id) as $s) {
            if ($s['status'] === 'cancelled') continue;
            $first = $s;
            break;
        }
        $before = !$first || (strtotime((string) $first['starts_at']) > time()
                              && empty($first['teacher_joined_at']) && $first['status'] === 'scheduled');

        $refunded = 0;
        $voided   = 0;
        if ($before) {
            $this->load->model('taqdar_billing_model');
            $this->load->model('taqdar_wallet_model');
            $subs = $this->db->where('group_id', (int) $id)
                             ->where_in('status', array('active', 'cancelled', 'pending'))
                             ->get('subscriptions')->result_array();
            foreach ($subs as $s) {
                if ($s['status'] === 'pending') {
                    $inv = $this->taqdar_billing_model->invoice_of_subscription((int) $s['id']);
                    if ($inv && $inv['status'] === 'unpaid') {
                        $this->taqdar_billing_model->cancel_pending_invoice((int) $inv['id']);
                        $voided++;
                    }
                    continue;
                }
                if ($s['status'] === 'cancelled' && empty($s['ends_at'])) continue;   // لم يدفع

                if ((int) $g['teacher_id'] > 0) {
                    try {
                        $this->taqdar_wallet_model->reverse_group_sale((int) $g['teacher_id'], (int) $s['id'],
                                                                       'الغاء المجموعة قبل اول حصة');
                    } catch (Throwable $e) {
                        $this->db->reset_query();
                        log_message('error', 'TQ-GROUP reverse #' . (int) $s['id'] . ': ' . $e->getMessage());
                    }
                }
                $this->taqdar_billing_model->cancel((int) $s['id'], 'الغيت المجموعة قبل اول حصة — يرد المبلغ');
                $this->db->where('id', (int) $s['id'])->update('subscriptions', array('ends_at' => date('Y-m-d H:i:s')));
                $this->audit('group.refund_due', 'subscriptions#' . (int) $s['id'], $s,
                             array('group_id' => (int) $id, 'price' => (int) $s['price'],
                                   'note' => 'refund outside the platform — group cancelled before its first session'));
                $this->notify((int) $s['user_id'], t('الغيت المجموعة'),
                              t('الغيت مجموعة «____» قبل اول حصة، ويرد اليك ما دفعت خارج المنصة.', array($g['title'])),
                              'subscription');
                $refunded++;
            }
        }
        $this->audit('group.close', self::T_GROUPS . '#' . (int) $id, $g,
                     array('before_start' => $before, 'refunded' => $refunded, 'voided' => $voided));
        return array('ok' => true, 'refunded' => $refunded, 'voided' => $voided, 'before_start' => $before);
    }

    /**
     * TQ-GROUP-DELETE — مجموعة بيعت لا تحذف، تغلق. والسبب بالرقم.
     * @return array why (قائمة نصوص — فارغة = يحذف)
     */
    public function delete_blockers($id)
    {
        $this->ensure_schema();
        $why = array();
        try {
            $n = (int) $this->db->where('group_id', (int) $id)
                                ->where_in('status', array('active', 'cancelled', 'expired', 'pending'))
                                ->count_all_results('subscriptions');
        } catch (Throwable $e) { $this->db->reset_query(); $n = 0; }
        if ($n > 0) {
            $why[] = t('بيعت لـ____ اشتراكا، وفواتيرها وقيود معلمها تشير اليها بمعرفها.', array($n));
        }
        return array('why' => $why, 'sold' => $n);
    }

    /* =====================================================================
       الكتابة — الحصص
       ===================================================================== */

    private function can_edit_group($g, $actor)
    {
        if (!$g) return false;
        if (($actor['role'] ?? '') === 'admin') return true;
        return (int) $g['teacher_id'] === (int) ($actor['id'] ?? 0);
    }

    /**
     * يضيف حصة او يعدلها (`$data['id']`).
     * @param array $actor id · role
     */
    public function save_session($group_id, $data, $actor)
    {
        $g = $this->get($group_id);
        if (!$this->can_edit_group($g, $actor)) return array('ok' => false, 'errors' => array(t('هذه المجموعة ليست لك.')));

        $id  = (int) ($data['id'] ?? 0);
        $old = $id ? $this->session($id) : null;
        if ($id && (!$old || (int) $old['group_id'] !== (int) $g['id'])) {
            return array('ok' => false, 'errors' => array(t('لا حصة بهذا الرقم في هذه المجموعة.')));
        }

        $row = array();
        if (array_key_exists('title', $data)) $row['title'] = trim((string) $data['title']);
        if (array_key_exists('meet_url', $data)) {
            $row['meet_url'] = function_exists('tq_clean_url') ? tq_clean_url($data['meet_url']) : trim((string) $data['meet_url']);
        }
        if (array_key_exists('duration_min', $data)) $row['duration_min'] = max(15, min(300, (int) $data['duration_min']));
        if (array_key_exists('starts_at', $data)) {
            $ts = strtotime((string) $data['starts_at']);
            if (!$ts) return array('ok' => false, 'errors' => array(t('موعد الحصة غير مفهوم.')));
            /* تغيير موعد حصة قائمة تأجيل له قاعدته وسببه — لا يمر من هنا. */
            if ($old && strtotime((string) $old['starts_at']) !== $ts) {
                return array('ok' => false, 'errors' => array(t('لتغيير موعد حصة قائمة استعمل «تأجيل» بسببه.')));
            }
            if (!$old && $ts <= time()) return array('ok' => false, 'errors' => array(t('موعد الحصة يجب ان يكون في المستقبل.')));
            $row['starts_at'] = date('Y-m-d H:i:s', $ts);
        }

        if (!$old) {
            if (empty($row['starts_at'])) return array('ok' => false, 'errors' => array(t('موعد الحصة مطلوب.')));
            $row += array('group_id' => (int) $g['id'], 'status' => 'scheduled', 'title' => '',
                          'duration_min' => 60, 'meet_url' => '');
            $this->db->insert(self::T_SESS, $row);
            $id = (int) $this->db->insert_id();
        } elseif ($row) {
            $this->db->where('id', $id)->update(self::T_SESS, $row);
        }
        return array('ok' => true, 'id' => $id);
    }

    /**
     * يؤجل حصة الى موعد جديد بسبب، ويبلغ كل الاعضاء (D7-d).
     * @param array $actor id · role
     */
    public function reschedule_session($gs_id, $new_starts, $reason, $actor)
    {
        $gs = $this->session($gs_id);
        $g  = $gs ? $this->get((int) $gs['group_id']) : null;
        if (!$gs || !$this->can_edit_group($g, $actor)) return array('ok' => false, 'errors' => array(t('هذه الحصة ليست لك.')));
        if ($gs['status'] !== 'scheduled') return array('ok' => false, 'errors' => array(t('لا تؤجل الا حصة مجدولة لم تبدأ.')));
        $reason = trim((string) $reason);
        if ($reason === '') return array('ok' => false, 'errors' => array(t('اكتب سبب التأجيل — يقرؤه كل الاعضاء.')));
        $ts = strtotime((string) $new_starts);
        if (!$ts || $ts <= time()) return array('ok' => false, 'errors' => array(t('الموعد الجديد يجب ان يكون في المستقبل.')));

        $this->db->where('id', (int) $gs['id'])->update(self::T_SESS, array(
            'rescheduled_from_at' => $gs['starts_at'],
            'starts_at'           => date('Y-m-d H:i:s', $ts),
            'cancel_reason'       => mb_substr($reason, 0, 255),
        ));
        $this->audit('group_session.rescheduled', self::T_SESS . '#' . (int) $gs['id'], $gs, $this->session($gs['id']));
        $this->notify_members((int) $g['id'], t('تأجيل حصة'),
            t('اجلت حصة «____» من ____ الى ____. السبب: ____',
              array($g['title'], $gs['starts_at'], date('Y-m-d H:i', $ts), $reason)));
        return array('ok' => true);
    }

    /**
     * الغاء حصة **بلا بديل** — للمسؤول وحده وبقرار صريح (D7-d): العضوية بيعت
     * بجدول، وحصة تسقط بلا بديل نقص فيما دفع. فغير المسؤول يرد الى «تأجيل».
     */
    public function cancel_session($gs_id, $reason, $actor, $admin_no_replacement = false)
    {
        $gs = $this->session($gs_id);
        $g  = $gs ? $this->get((int) $gs['group_id']) : null;
        if (!$gs || !$this->can_edit_group($g, $actor)) return array('ok' => false, 'errors' => array(t('هذه الحصة ليست لك.')));
        if (($actor['role'] ?? '') !== 'admin' || !$admin_no_replacement) {
            return array('ok' => false, 'code' => 'replacement_required',
                         'errors' => array(t('الحصة لا تلغى بلا بديل — اجلها الى موعد جديد. والالغاء بلا بديل قرار للادارة وحدها.')));
        }
        if (!in_array($gs['status'], array('scheduled', 'live'), true)) {
            return array('ok' => false, 'errors' => array(t('هذه الحصة ليست قائمة فتلغى.')));
        }
        $reason = trim((string) $reason);
        if ($reason === '') return array('ok' => false, 'errors' => array(t('اكتب سبب الالغاء — يقرؤه كل الاعضاء.')));

        $this->db->where('id', (int) $gs['id'])->update(self::T_SESS, array(
            'status' => 'cancelled', 'cancel_reason' => mb_substr($reason, 0, 255)));
        $this->audit('group_session.cancelled', self::T_SESS . '#' . (int) $gs['id'], $gs, array('reason' => $reason));
        $this->notify_members((int) $g['id'], t('الغاء حصة'),
            t('الغيت حصة «____» في ____. السبب: ____', array($g['title'], $gs['starts_at'], $reason)));
        return array('ok' => true);
    }

    /**
     * يعلن المعلم انتهاء الحصة: حضور جماعي، ورابط تسجيل او «سأرفعه لاحقا».
     *
     * @param array $attendance student_id => present|absent — ومن لم يذكر
     *                          يحكم بنقرته: دخل = حاضر، والا غائب
     */
    public function complete_session($gs_id, $attendance, $recording_url, $recording_pending, $actor)
    {
        $gs = $this->session($gs_id);
        $g  = $gs ? $this->get((int) $gs['group_id']) : null;
        if (!$gs || !$this->can_edit_group($g, $actor)) return array('ok' => false, 'errors' => array(t('هذه الحصة ليست لك.')));
        if ($gs['status'] === 'cancelled') return array('ok' => false, 'errors' => array(t('الحصة ملغاة.')));
        if (strtotime((string) $gs['starts_at']) > time()) {
            return array('ok' => false, 'errors' => array(t('لم يحن موعد الحصة بعد.')));
        }

        $url = function_exists('tq_clean_url') ? tq_clean_url($recording_url) : trim((string) $recording_url);
        $this->db->where('id', (int) $gs['id'])->update(self::T_SESS, array(
            'status'            => 'completed',
            'completed_at'      => $gs['completed_at'] ?: date('Y-m-d H:i:s'),
            'recording_url'     => $url,
            'recording_pending' => ($url === '' && $recording_pending) ? 1 : 0,
        ));
        $this->write_attendance((int) $gs['id'], (int) $g['id'], (array) $attendance);
        $this->audit('group_session.completed', self::T_SESS . '#' . (int) $gs['id'], $gs, $this->session($gs['id']));
        return array('ok' => true);
    }

    private function write_attendance($gs_id, $group_id, array $given)
    {
        foreach ($this->members_of($group_id) as $m) {
            $sid = (int) $m['user_id'];
            if (isset($given[$sid]) && in_array($given[$sid], array('present', 'absent'), true)) {
                $v = $given[$sid];
            } else {
                $v = $this->attendance_of($gs_id, $sid) === 'present' ? 'present' : 'absent';
            }
            try {
                $this->db->query(
                    'INSERT INTO `' . self::T_ATT . '` (`session_id`,`student_id`,`attendance`) VALUES (?,?,?)
                     ON DUPLICATE KEY UPDATE `attendance` = VALUES(`attendance`)',
                    array($gs_id, $sid, $v)
                );
            } catch (Throwable $e) { $this->db->reset_query(); }
        }
    }

    /* =====================================================================
       الدورة الدورية
       ===================================================================== */

    /**
     * يغلق ما مضى وقته + السماح بلا اعلان (كالفردية)، ويسقط «سأرفعه لاحقا»
     * بعد سبعة ايام. ينادى من `taqdar_cron expire_sessions`.
     * @return array closed · recordings_dropped
     */
    public function lifecycle_tick($now = null)
    {
        $this->ensure_schema();
        $now = $now ? (int) $now : time();
        $out = array('closed' => 0, 'recordings_dropped' => 0);

        try {
            $rows = $this->db->where_in('status', array('scheduled', 'live'))
                             ->where('starts_at <', date('Y-m-d H:i:s', $now))
                             ->get(self::T_SESS)->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); $rows = array(); }

        $grace = $this->grace_hours();
        foreach ($rows as $r) {
            $end = strtotime((string) $r['starts_at']) + max(1, (int) $r['duration_min']) * 60 + $grace * 3600;
            if ($end >= $now) continue;
            $this->db->where('id', (int) $r['id'])->where_in('status', array('scheduled', 'live'))
                     ->update(self::T_SESS, array('status' => 'completed', 'completed_at' => date('Y-m-d H:i:s', $now)));
            $this->write_attendance((int) $r['id'], (int) $r['group_id'], array());
            $out['closed']++;
        }

        try {
            $this->db->where('status', 'completed')->where('recording_pending', 1)
                     ->where('completed_at <', date('Y-m-d H:i:s', $now - self::RECORDING_WAIT_DAYS * 86400))
                     ->update(self::T_SESS, array('recording_pending' => 0));
            $out['recordings_dropped'] = (int) $this->db->affected_rows();
        } catch (Throwable $e) { $this->db->reset_query(); }

        return $out;
    }

    /** نهاية آخر حصة غير ملغاة — اجل العضوية حين `access_days = 0`. */
    public function last_session_end($group_id)
    {
        $last = 0;
        foreach ($this->sessions_of($group_id) as $s) {
            if ($s['status'] === 'cancelled') continue;
            $last = max($last, strtotime((string) $s['starts_at']) + max(1, (int) $s['duration_min']) * 60);
        }
        return $last;
    }

    /* =====================================================================
       مساعدات
       ===================================================================== */

    /**
     * يبلغ كل الاعضاء: صف في `notifications` لكل عضو (`push_notification`
     * بلا بريد متزامن)، ثم البريد في طابور `Taqdar_events_model` فلا حلقة
     * ارسال متزامنة ويرث ساعات الصمت والاعادة.
     */
    private function notify_members($group_id, $title, $text)
    {
        $this->load->model('taqdar_events_model');
        foreach ($this->members_of($group_id) as $m) {
            $this->notify((int) $m['user_id'], $title, $text, 'session', false);
            $this->taqdar_events_model->enqueue((int) $m['user_id'], 'group_session', $title, $text, 'learning');
        }
    }

    private function notify($uid, $title, $text, $type = 'system', $mail = true)
    {
        if ($uid <= 0) return;
        try {
            $this->load->model('taqdar_admin_model');
            $this->taqdar_admin_model->push_notification($uid, $title, $text, $type, $mail);
        } catch (Throwable $e) {
            $this->db->reset_query();
            log_message('error', 'TQ-GROUP notify: ' . $e->getMessage());
        }
    }

    private function audit($action, $entity, $before, $after)
    {
        try {
            $actor = 0;
            if (isset($this->session)) $actor = (int) $this->session->userdata('user_id');
            $this->db->insert('audit_log', array(
                'actor_id' => $actor,
                'action'   => $action,
                'entity'   => $entity,
                'before'   => $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
                'after'    => $after ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
                'ip'       => $this->input->is_cli_request() ? 'cli' : $this->input->ip_address(),
                'at'       => date('Y-m-d H:i:s'),
            ));
        } catch (Throwable $e) { $this->db->reset_query(); }
    }

    private function setting($key, $default)
    {
        $v = get_settings($key);
        return $v === null ? $default : $v;
    }

    private function try_sql($sql)
    {
        try { $this->db->query($sql); } catch (Throwable $e) { $this->db->reset_query(); }
    }

    private function put_setting($key, $value)
    {
        try {
            $exists = $this->db->where('key', $key)->count_all_results('settings') > 0;
            if ($exists) $this->db->where('key', $key)->update('settings', array('value' => $value));
            else         $this->db->insert('settings', array('key' => $key, 'value' => $value));
        } catch (Throwable $e) { $this->db->reset_query(); }
    }
}
