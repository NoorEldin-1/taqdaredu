<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * المفضلة — درسا وملفا وكورسا.
 *
 * كانت شاشة المفضلة تعرض ثلاثة أقسام وقلبا في كل بطاقة، والقلب `type="button"`
 * بلا نموذج ولا معالج: يضغطه الطالب فلا يقع شيء، وتحته سطر يعده صراحة بأن
 * «الضغط على القلب يزيل العنصر». وكان قسما الدروس والملفات مصفوفتين فارغتين
 * ثابتتين لأن لا جدول لهما — فالحالة الفارغة صادقة، لكنها دائمة.
 *
 * ── لماذا جدول واحد وليس عمودا في `users` ──────────────────────────────
 * `users.wishlist` عمود JSON تكتبه شاشات Academy الأصلية (زر القلب في
 * `course_page`). فلو نقلت الكورسات إلى هنا لانقسم المصدر: يضيف الطالب من
 * صفحة الكورس فلا يظهر في مفضلته، أو العكس. ولذلك:
 *
 *   • **الكورسات تبقى في `users.wishlist`** — مصدرها Academy ولا ينازع.
 *   • **الدروس والملفات في `tq_favourites`** — لا مصدر لهما أصلا.
 *
 * فلكل نوع مصدر واحد، ولا تزامن بين اثنين يفترقان عند أول عطل.
 */
class Taqdar_favourites_model extends CI_Model
{
    /** الأنواع التي يملكها هذا الجدول. الكورس ليس منها عمدا — انظر أعلاه. */
    const KINDS = array('lesson', 'material');

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /**
     * ينشئ الجدول عند أول استعمال.
     *
     * لا هجرات في هذا المستودع (CLAUDE.md)، والنمط المتبع هو `ensure_schema`
     * كما في `Taqdar_settings_model` — فيتبع هنا ولا يخترع نمط ثان.
     */
    public function ensure_schema()
    {
        static $done = false;
        if ($done) return;
        $done = true;

        if ($this->db->table_exists('tq_favourites')) return;

        $this->db->query(
            "CREATE TABLE IF NOT EXISTS `tq_favourites` (
                `id`         INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id`    INT(10) UNSIGNED NOT NULL,
                `kind`       VARCHAR(16)      NOT NULL,
                `item_id`    INT(10) UNSIGNED NOT NULL,
                `created_at` INT(11)          NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_user_kind_item` (`user_id`,`kind`,`item_id`),
                KEY `ix_user_kind` (`user_id`,`kind`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /* ================================================================
       قراءة
       ================================================================ */

    /** معرفات ما فضله الطالب من نوع بعينه، الأحدث إضافة أولا. */
    public function ids($user_id, $kind)
    {
        $this->ensure_schema();
        $user_id = (int) $user_id;
        if ($user_id <= 0 || !in_array($kind, self::KINDS, true)) return array();

        $rows = $this->db->select('item_id')
                         ->where('user_id', $user_id)->where('kind', $kind)
                         ->order_by('created_at', 'DESC')->order_by('id', 'DESC')
                         ->get('tq_favourites')->result_array();

        return array_map(static function ($r) { return (int) $r['item_id']; }, $rows);
    }

    /**
     * الدروس المفضلة، بما يكفي لرسم بطاقتها.
     *
     * مقيدة بالتسجيل (`enrol`): درس فضله الطالب ثم انتهى اشتراكه لا يعرض
     * له غلافا وزر تشغيل يقوده إلى قفل. والحذف لا يقع — لو عاد اشتراكه
     * عادت مفضلته كما تركها.
     */
    public function lessons($user_id)
    {
        $ids = $this->ids($user_id, 'lesson');
        if (!$ids) return array();

        $rows = $this->db
            ->select('l.id, l.title, l.duration, l.lesson_type, l.course_id,'
                   . ' c.title AS course_title, c.category_id')
            ->from('lesson l')
            ->join('course c', 'c.id = l.course_id', 'inner')
            ->join('enrol e', 'e.course_id = c.id AND e.user_id = ' . (int) $user_id, 'inner')
            ->where_in('l.id', $ids)
            ->get()->result_array();

        return $this->rank($rows, $ids);
    }

    /**
     * الملفات المفضلة من `resource_files`، بمصدرها ودرسها.
     * ومقيدة بالتسجيل للسبب نفسه.
     */
    public function materials($user_id)
    {
        $ids = $this->ids($user_id, 'material');
        if (!$ids) return array();

        $rows = $this->db
            ->select('rf.id, rf.title, rf.file_name, l.title AS lesson_title,'
                   . ' c.title AS course_title, c.category_id, c.id AS course_id')
            ->from('resource_files rf')
            ->join('lesson l', 'l.id = rf.lesson_id', 'inner')
            ->join('course c', 'c.id = l.course_id', 'inner')
            ->join('enrol e', 'e.course_id = c.id AND e.user_id = ' . (int) $user_id, 'inner')
            ->where_in('rf.id', $ids)
            ->get()->result_array();

        return $this->rank($rows, $ids);
    }

    /** يعيد ترتيب الصفوف على ترتيب الإضافة، فالاستعلام لا يحفظ ترتيب `IN`. */
    private function rank($rows, $ids)
    {
        $rank = array_flip($ids);
        usort($rows, static function ($a, $b) use ($rank) {
            return ($rank[(int) $a['id']] ?? PHP_INT_MAX) <=> ($rank[(int) $b['id']] ?? PHP_INT_MAX);
        });
        return $rows;
    }

    /* ================================================================
       كتابة
       ================================================================ */

    /**
     * يقلب التفضيل: يضيف إن لم يكن، ويحذف إن كان.
     *
     * الملكية تفحص هنا لا في العرض: بلا هذا الفحص يفضل الطالب درس كورس
     * لم يسجل فيه بتخمين معرف، فتصير مفضلته كشافا لمحتوى لا يملكه.
     *
     * @return array{ok:bool,on:bool,msg:string}
     */
    public function toggle($user_id, $kind, $item_id)
    {
        $this->ensure_schema();

        $user_id = (int) $user_id;
        $item_id = (int) $item_id;

        if ($user_id <= 0 || $item_id <= 0 || !in_array($kind, self::KINDS, true)) {
            return array('ok' => false, 'on' => false, 'msg' => 'طلب غير مكتمل.');
        }

        if (!$this->owns($user_id, $kind, $item_id)) {
            return array('ok' => false, 'on' => false, 'msg' => 'هذا العنصر ليس ضمن كورساتك.');
        }

        $exists = $this->db->where('user_id', $user_id)->where('kind', $kind)
                           ->where('item_id', $item_id)
                           ->count_all_results('tq_favourites');

        if ($exists) {
            $this->db->where('user_id', $user_id)->where('kind', $kind)
                     ->where('item_id', $item_id)->delete('tq_favourites');
            return array('ok' => true, 'on' => false, 'msg' => 'أزيل من مفضلتك.');
        }

        $this->db->insert('tq_favourites', array(
            'user_id'    => $user_id,
            'kind'       => $kind,
            'item_id'    => $item_id,
            'created_at' => time(),
        ));
        return array('ok' => true, 'on' => true, 'msg' => 'أضيف إلى مفضلتك.');
    }

    /** هل العنصر داخل كورس مسجل للطالب؟ */
    private function owns($user_id, $kind, $item_id)
    {
        if ($kind === 'lesson') {
            $sql = 'SELECT COUNT(*) AS n FROM `lesson` l
                      JOIN `enrol` e ON e.course_id = l.course_id AND e.user_id = ?
                     WHERE l.id = ?';
        } else {
            $sql = 'SELECT COUNT(*) AS n FROM `resource_files` rf
                      JOIN `lesson` l ON l.id = rf.lesson_id
                      JOIN `enrol` e ON e.course_id = l.course_id AND e.user_id = ?
                     WHERE rf.id = ?';
        }
        return (int) $this->db->query($sql, array((int) $user_id, (int) $item_id))->row('n') > 0;
    }

    /* ================================================================
       الكورسات — من `users.wishlist` لا من هذا الجدول
       ================================================================ */

    /** قائمة تفضيل الكورسات كما يخزنها Academy: مصفوفة معرفات في JSON. */
    public function course_ids($user_id)
    {
        $raw  = $this->db->select('wishlist')->where('id', (int) $user_id)
                         ->get('users')->row('wishlist');
        $list = json_decode((string) $raw, true);
        if (!is_array($list)) return array();

        return array_values(array_unique(array_filter(array_map('intval', $list))));
    }

    /**
     * يقلب تفضيل كورس في `users.wishlist`.
     * الكورس لا يشترط تسجيلا: التفضيل هنا نية شراء لا ملكية.
     */
    public function toggle_course($user_id, $course_id)
    {
        $user_id   = (int) $user_id;
        $course_id = (int) $course_id;

        if ($user_id <= 0 || $course_id <= 0) {
            return array('ok' => false, 'on' => false, 'msg' => 'طلب غير مكتمل.');
        }
        if (!$this->db->where('id', $course_id)->count_all_results('course')) {
            return array('ok' => false, 'on' => false, 'msg' => 'هذا الكورس لم يعد موجودا.');
        }

        $list = $this->course_ids($user_id);
        $at   = array_search($course_id, $list, true);
        $on   = ($at === false);

        if ($on) {
            $list[] = $course_id;
        } else {
            unset($list[$at]);
        }

        $this->db->where('id', $user_id)
                 ->update('users', array('wishlist' => json_encode(array_values($list))));

        return array(
            'ok'  => true,
            'on'  => $on,
            'msg' => $on ? 'أضيف إلى مفضلتك.' : 'أزيل من مفضلتك.',
        );
    }
}
