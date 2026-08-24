<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * قسمة إيراد الباقة — وعاء مغلق يقسم بالدرس.
 *
 * الباقة تباع مرة وتفتح محتوى **عدة معلمين**. والدفعة واحدة والمستحقون
 * كثر، فالسؤال الذي يجيبه هذا الملف: كم لكل معلم من هذه البيعة؟
 *
 * وقبله لم يكن يجاب أصلا: `Taqdar_billing_model::activate()` يكتب البنود
 * ويجسد `enrol` ثم ينتهي — فباقة المنصة، وهي وحدة البيع الأساسية، كانت
 * تبيع محتوى سبعة معلمين ولا يقيد لأحدهم ريال. والقيد الوحيد القائم كان
 * `credit_path_sale()` لبيع المسار المنفرد، وهو الأقل وقوعا.
 *
 * ═══════════════════════════════════════════════════════════════════
 *  لماذا وعاء واحد لا نسبة لكل معلم
 * ═══════════════════════════════════════════════════════════════════
 *
 * القاعدة البديهية «كل معلم يأخذ ١٥٪» تنهار عند المعلم السابع: سبعة في
 * ١٥ تساوي ١٠٥٪ من السعر، وبعشرين معلما ٣٠٠٪. وتنهار **بصمت**: كل قيد
 * وحده يبدو صحيحا، ولا شيء في الدفتر يقول إن المنصة صرفت أكثر مما قبضت.
 *
 * فالقسمة هنا قسمتان لا واحدة، ولكل رقم وظيفة تخصه:
 *
 *     سعر الباقة
 *       ├── عمولة المنصة  = السعر − الوعاء     ← مرآة محسوبة لا تخزن
 *       └── وعاء المعلمين = السعر × النسبة      ← الرقم الوحيد المخزن
 *              │
 *              └── يقسم على أوزان الدروس في نطاق الباقة
 *
 * ورقم واحد يخزن لا رقمان: نسبتان في عمودين تفترقان عند أول تعديل، فتحفظ
 * باقة عمولتها ٨٠ ووعاؤها ٣٠ ولا شيء يمنعها. والمجموع هنا مئة **بحكم
 * البناء** لا بحكم الانضباط — كما `sale + commission + retained` في
 * `Taqdar_wallet_model` مجموعها حصة المعلم بحكم البناء.
 *
 * ═══════════════════════════════════════════════════════════════════
 *  وحدة القسمة: الدرس لا الكورس
 * ═══════════════════════════════════════════════════════════════════
 *
 * الكورس وحدة إدارية لا وحدة عمل: معلم يضع منهجه كله في كورس واحد فيه
 * مئة وعشرون درسا، وآخر يفرقه على ثلاثة كورسات فيها ثلاثون. والقسمة
 * بالكورس تعطي الثاني ضعف الأول على ربع العمل.
 *
 * فالوزن بالدرس المنشور. و`paths.teacher_share_percent` — وهو عمود قائم
 * يصف حصة المعلم من سعر مساره المنفرد — يعاد استعماله هنا **معاملا**:
 * مسار نسبته ٣٠ تزن دروسه ضعف مسار نسبته ١٥. فالنسبة تؤدي دورين متسقين:
 * حصة مباشرة في البيع المنفرد، ووزنا نسبيا في قسمة الوعاء. ولا عمود
 * ثالث يضبط ويشرح.
 *
 * ═══════════════════════════════════════════════════════════════════
 *  الأساس: الإتاحة عند التفعيل
 * ═══════════════════════════════════════════════════════════════════
 *
 * القسمة تقع مرة واحدة وقت تفعيل الاشتراك، على الدروس المنشورة في نطاق
 * الباقة **تلك اللحظة** — لا على ما شوهد لاحقا ولا على ما ينشر غدا.
 *
 * وهذا ليس اختصارا بل اتساق: `subscription_items` تنسخ النطاق وقت
 * التفعيل بالمبدأ نفسه، فتعديل الباقة غدا لا يوسع ما دفع ولا يضيقه.
 * وقسمة تتبع أساسا ثانيا (ما شاهده الطالب) تجعل للاشتراك الواحد مصدري
 * حقيقة يفترقان: بنوده تقول «فتح لك هذه الصفوف» وقيوده تقول «دفعنا عن
 * غيرها». ولو أريد الانتقال إلى القسمة بالاستهلاك يوما فمدخله
 * `contributors()` وحدها: تبدل الأوزان من عد الدروس إلى `lesson_progress`
 * ولا يمس شيء آخر في هذا الملف.
 *
 * ═══════════════════════════════════════════════════════════════════
 *  الحساب بالهللات الصحيحة وحدها
 * ═══════════════════════════════════════════════════════════════════
 *
 * لا `float` ولا `round()` في القسمة. الكسر يعالج بـ**أكبر البواقي**
 * (largest remainder): لكل معلم `intdiv` وباقيه الصحيح، ثم توزع الهللات
 * المتبقية على أصحاب أكبر البواقي واحدة واحدة. فمجموع الحصص يساوي الوعاء
 * **بالضبط** — لا هللة تضيع ولا هللة تخترع.
 *
 * وقسمتان مستقلتان على الأوزان نفسها: واحدة على الوعاء وأخرى على
 * `السعر − الوعاء`. فنصيب المعلم من السعر = حصته + عمولة المنصة عنه،
 * ومجموع الأنصبة = السعر بالضبط. وبهذا تبقى أعمدة كشف الحساب الثلاثة
 * (المقبوض · العمولة · حصتك) صحيحة صفا بصف كما هي في بيع الكورس.
 */
class Taqdar_revenue_model extends CI_Model
{
    /** إصدار البنية — يمنع إعادة فحص الأعمدة في كل طلب. */
    const SCHEMA_V = '1';

    /** نسبة الوعاء الافتراضية حين لا تضبط للباقة ولا في الإعدادات. */
    const DEFAULT_POOL = 15;

    /** معامل الوزن الافتراضي لمسار لم تضبط نسبته. */
    const DEFAULT_WEIGHT = 15;

    private $schema_checked = false;
    private static $settings_cache = array();

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /* ================================================================
     *  البنية — إضافية ومتكررة الأمان
     * ================================================================ */

    /**
     * عمود واحد على `plans` وجدول واحد جديد. ولا هجرات في هذا المستودع،
     * فالبنية تكتمل وقت التشغيل كما تفعل `site_content` و`tq_otp`.
     */
    public function install_schema($force = false)
    {
        if ($this->schema_checked && !$force) return false;
        $this->schema_checked = true;

        if (!$force && (string) $this->setting('tq_revenue_schema_v', '') === self::SCHEMA_V) {
            return false;
        }

        $this->db->data_cache = array();

        /* NULL لا صفر: الصفر نسبة صريحة تعني «لا شيء للمعلمين»، وهي قرار
           قد يتخذ. والفارغ يعني «خذ الافتراض العام» — ومعنيان في عمود
           واحد يحتاجان قيمتين مختلفتين لا قيمة واحدة تفسر مرتين. */
        if (!$this->db->field_exists('teacher_pool_percent', 'plans')) {
            $this->db->query(
                "ALTER TABLE `plans`
                 ADD COLUMN `teacher_pool_percent` decimal(5,2) DEFAULT NULL
                 COMMENT 'وعاء المعلمين من سعر الباقة — فارغ يعني الافتراض العام'
                 AFTER `duration_days`"
            );
        }

        $this->db->query(
            "CREATE TABLE IF NOT EXISTS `revenue_shares` (
               `id`              int(10) unsigned NOT NULL AUTO_INCREMENT,
               `subscription_id` int(10) unsigned NOT NULL,
               `plan_id`         int(10) unsigned NOT NULL DEFAULT 0,
               `teacher_id`      int(10) unsigned NOT NULL,
               `basis`           varchar(20)  NOT NULL DEFAULT 'availability',
               `gross_halalas`   bigint(20)   NOT NULL DEFAULT 0,
               `pool_percent`    decimal(5,2) NOT NULL DEFAULT 0,
               `pool_halalas`    bigint(20)   NOT NULL DEFAULT 0,
               `lessons`         int(11)      NOT NULL DEFAULT 0,
               `lessons_total`   int(11)      NOT NULL DEFAULT 0,
               `weight`          bigint(20)   NOT NULL DEFAULT 0,
               `weight_total`    bigint(20)   NOT NULL DEFAULT 0,
               `attributed_halalas` bigint(20) NOT NULL DEFAULT 0,
               `amount_halalas`  bigint(20)   NOT NULL DEFAULT 0,
               `paths_json`      text         DEFAULT NULL,
               `created_at`      datetime     NOT NULL DEFAULT current_timestamp(),
               PRIMARY KEY (`id`),
               UNIQUE KEY `uq_rs` (`subscription_id`,`teacher_id`),
               KEY `idx_rs_teacher` (`teacher_id`),
               KEY `idx_rs_plan` (`plan_id`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $this->seed_setting('taqdar_teacher_pool_percent', (string) self::DEFAULT_POOL);
        $this->seed_setting('tq_revenue_schema_v', self::SCHEMA_V);
        self::$settings_cache = array();
        return true;
    }

    /**
     * الاسم الذي تناديه به وحدات اللوحة (`'ensure'` في `spec()`) — وهو
     * الاسم نفسه في `Taqdar_diag_model` و`Taqdar_content_model`. واسمان
     * لفعل واحد يجعل من يضيف وحدة غدا يبحث عن أيهما لهذا النموذج.
     */
    public function ensure_schema()
    {
        return $this->install_schema();
    }

    private function seed_setting($key, $value)
    {
        $row = $this->db->select('id')->where('key', $key)->get('settings')->row_array();
        if (!$row) $this->db->insert('settings', array('key' => $key, 'value' => $value));
    }

    private function setting($key, $default = null)
    {
        if (!array_key_exists($key, self::$settings_cache)) {
            $row = $this->db->select('value')->where('key', $key)
                            ->get('settings')->row_array();
            self::$settings_cache[$key] = $row ? $row['value'] : null;
        }
        $v = self::$settings_cache[$key];
        return ($v === null || $v === '') ? $default : $v;
    }

    /* ================================================================
     *  النسب
     * ================================================================ */

    /** نسبة الوعاء الافتراضية العامة — تضبط من إعدادات المنصة. */
    public function default_pool_percent()
    {
        return $this->clamp_percent(
            $this->setting('taqdar_teacher_pool_percent', self::DEFAULT_POOL),
            self::DEFAULT_POOL
        );
    }

    /**
     * نسبة الوعاء لباقة بعينها: ما ضبط لها، وإلا الافتراض العام.
     * والصفر الصريح يحترم — باقة كل إيرادها للمنصة قرار لا خطأ.
     */
    public function pool_percent($plan)
    {
        if (is_array($plan) && array_key_exists('teacher_pool_percent', $plan)
            && $plan['teacher_pool_percent'] !== null
            && $plan['teacher_pool_percent'] !== '') {
            return $this->clamp_percent($plan['teacher_pool_percent'], $this->default_pool_percent());
        }
        return $this->default_pool_percent();
    }

    /** نسبة المنصة — مرآة محسوبة، لا تخزن أبدا. */
    public function platform_percent($plan)
    {
        return round(100 - $this->pool_percent($plan), 2);
    }

    private function clamp_percent($v, $fallback)
    {
        if ($v === null || $v === '' || !is_numeric($v)) return (float) $fallback;
        return (float) max(0, min(100, round((float) $v, 2)));
    }

    /* ================================================================
     *  من يستحق: الأوزان
     * ================================================================ */

    /**
     * معرفات صفوف الباقة — `scope_ids` أولا و`scope_id` احتياطا،
     * كما تقرؤها `Taqdar_admin_model::plan_grade_ids()` حرفا بحرف.
     *
     * **ولباقة الصفوف وحدها.** `scope_id` عمود واحد يفسر بحسب `scope`
     * (`refswitch` في وصف الوحدة): في باقة المادة رقم مادة، وفي باقة
     * المسار رقم مسار. فقراءته صفا في نطاق آخر تقسم إيراد باقة مادة
     * على معلمي صف لا علاقة له بها — ورقم يقابل صفا موجودا فعلا، فلا
     * خطأ يظهر ولا نتيجة فارغة تنبه. ولذلك الحارس هنا لا عند النداء:
     * من يضيف مناديا غدا لا يعيد قراءة هذا التعليق.
     */
    public function plan_grade_ids($plan)
    {
        if (!$plan) return array();
        $scope = isset($plan['scope']) ? (string) $plan['scope'] : 'grade';
        if ($scope !== 'grade') return array();

        $ids = array_filter(array_map('intval',
                   explode(',', (string) (isset($plan['scope_ids']) ? $plan['scope_ids'] : ''))));
        if (!$ids && (int) (isset($plan['scope_id']) ? $plan['scope_id'] : 0) > 0) {
            $ids = array((int) $plan['scope_id']);
        }
        return array_values(array_unique($ids));
    }

    /**
     * المعلمون المستحقون في باقة، أيا كان نطاقها.
     *
     * `contributors()` تقرأ الصفوف، والنطاق ليس صفوفا دائما: باقة المادة
     * تفتح مسارات مادة واحدة عبر كل الصفوف، وباقة المسار مسارا بعينه.
     * وهذه توجه كل نطاق إلى مسارات نطاقه هو.
     *
     * و`trial` بلا مستحق عمدا: الدروس التجريبية مفتوحة للمعاينة أصلا،
     * وباقة تبيعها تبيع ما لا يملكه أحد بعينه.
     */
    public function plan_contributors($plan)
    {
        $scope = isset($plan['scope']) ? (string) $plan['scope'] : 'grade';

        if ($scope === 'grade') return $this->contributors($this->plan_grade_ids($plan));
        if ($scope === 'all')   return $this->contributors(array(), true);
        if ($scope === 'trial') return $this->contributors(array());

        $sid = (int) (isset($plan['scope_id']) ? $plan['scope_id'] : 0);
        if ($sid <= 0) return $this->contributors(array());

        if ($scope === 'path')    return $this->contributors(array(), false, array('id' => $sid));
        if ($scope === 'subject') return $this->contributors(array(), false, array('subject_id' => $sid));

        return $this->contributors(array());
    }

    /**
     * المعلمون المستحقون وأوزانهم — الأساس الذي تقوم عليه القسمة كلها.
     *
     * تعد الدروس المنشورة في كل مسار منشور داخل نطاق الباقة، وتنسبها إلى
     * معلم مساره، وتضربها في معامل ذلك المسار.
     *
     * **الدورة تعد مرة واحدة.** مساران يشيران إلى `course_id` واحد يشيران
     * إلى الدروس نفسها لا إلى ضعفها؛ وعدها مرتين يضاعف وزن صاحبها بلا
     * أن ينشر درسا. فالدورة تنسب إلى أول مسار يذكرها (الأقدم معرفا،
     * فالترتيب ثابت لا يتغير بين نداءين) ويتجاهل ما بعده.
     *
     * والمسار بلا معلم أو بلا دورة يسقط من القسمة ويعد في `orphans` —
     * محتوى تفتحه الباقة ولا يستحق عنه أحد شيئا، وهو رقم يجب أن يراه
     * المسؤول في شاشة الباقة لا أن يكتشفه من شكوى معلم.
     *
     * @param  array $grade_ids  صفوف النطاق
     * @param  bool  $all_grades كل الصفوف — نطاق `all`
     * @param  array $only       قيد إضافي للنطاقات غير الصفية:
     *                           `['id' => n]` مسار بعينه، `['subject_id' => n]` مادة
     * @return array teachers · weight_total · lessons_total · orphans
     */
    public function contributors($grade_ids = array(), $all_grades = false, $only = array())
    {
        $grade_ids = array_values(array_unique(array_map('intval', (array) $grade_ids)));
        $out = array('teachers' => array(), 'weight_total' => 0,
                     'lessons_total' => 0, 'quizzes_total' => 0,
                     'orphans' => array('paths' => 0, 'lessons' => 0));

        if (!$all_grades && !$grade_ids && !$only) return $out;

        $where = 'p.`status` = "published" AND p.`grade_id` > 0';
        if ($only) {
            /* القيد الصريح يسبق الصفوف: باقة المسار مسارها هو نطاقها كله،
               وباقة المادة مساراتها عبر كل الصفوف. */
            if (isset($only['id'])) {
                $where .= ' AND p.`id` = ' . (int) $only['id'];
            } elseif (isset($only['subject_id'])) {
                $where .= ' AND p.`subject_id` = ' . (int) $only['subject_id'];
            } else {
                return $out;
            }
        } elseif (!$all_grades) {
            $where .= ' AND p.`grade_id` IN (' . implode(',', array_map('intval', $grade_ids)) . ')';
        }

        $paths = $this->db->query(
            'SELECT p.`id`, p.`title`, p.`teacher_id`, p.`course_id`, p.`grade_id`,
                    p.`subject_id`, p.`teacher_share_percent`
               FROM `paths` p
              WHERE ' . $where . '
              ORDER BY p.`id` ASC'
        )->result_array();
        if (!$paths) return $out;

        $cids = array();
        foreach ($paths as $p) if ((int) $p['course_id'] > 0) $cids[] = (int) $p['course_id'];
        $cids = array_values(array_unique($cids));

        $per_course = array();
        if ($cids) {
            $rows = $this->db->query(
                'SELECT `course_id`, COUNT(*) n,
                        SUM(CASE WHEN `lesson_type` = "quiz" THEN 1 ELSE 0 END) q
                   FROM `lesson`
                  WHERE `course_id` IN (' . implode(',', $cids) . ')
                    AND COALESCE(`tq_status`, "published") = "published"
                  GROUP BY `course_id`'
            )->result_array();
            foreach ($rows as $r) {
                $per_course[(int) $r['course_id']] = array(
                    'lessons' => (int) $r['n'], 'quizzes' => (int) $r['q'],
                );
            }
        }

        $seen_course = array();
        foreach ($paths as $p) {
            $cid = (int) $p['course_id'];
            $tid = (int) $p['teacher_id'];
            $n   = isset($per_course[$cid]) ? $per_course[$cid]['lessons'] : 0;
            $q   = isset($per_course[$cid]) ? $per_course[$cid]['quizzes'] : 0;

            if ($cid <= 0 || $n <= 0) { $out['orphans']['paths']++; continue; }
            if (isset($seen_course[$cid])) continue;   // الدورة تعد مرة
            $seen_course[$cid] = true;

            if ($tid <= 0) {
                $out['orphans']['paths']++;
                $out['orphans']['lessons'] += $n;
                continue;
            }

            $factor = $this->path_weight_factor($p);
            $weight = $n * $factor;

            if (!isset($out['teachers'][$tid])) {
                $out['teachers'][$tid] = array(
                    'teacher_id' => $tid, 'lessons' => 0, 'quizzes' => 0,
                    'weight' => 0, 'paths' => array(),
                );
            }
            $out['teachers'][$tid]['lessons'] += $n;
            $out['teachers'][$tid]['quizzes'] += $q;
            $out['teachers'][$tid]['weight']  += $weight;
            $out['teachers'][$tid]['paths'][] = array(
                'id' => (int) $p['id'], 'title' => (string) $p['title'],
                'lessons' => $n, 'factor' => $factor,
            );

            $out['lessons_total'] += $n;
            $out['quizzes_total'] += $q;
            $out['weight_total']  += $weight;
        }

        /* ترتيب ثابت: الأثقل أولا ثم بالمعرف. القسمة بأكبر البواقي تكسر
           التعادل بالترتيب، فترتيب غير ثابت يعني أن الهللة الأخيرة تذهب
           إلى معلم مختلف بين نداءين — والقسمة يجب أن تكون دالة. */
        uasort($out['teachers'], function ($a, $b) {
            if ($a['weight'] !== $b['weight']) return $b['weight'] - $a['weight'];
            return $a['teacher_id'] - $b['teacher_id'];
        });

        return $out;
    }

    /**
     * خريطة الأوزان لكل صف — تنقل كما هي إلى المتصفح فيعيد القسمة وأنت
     * تعلم الصفوف، قبل أن تحفظ.
     *
     * وترد **قيود دورات** لا مجاميع صفوف: الدورة الواحدة قد تظهر في
     * صفين، ودمج مجاميعهما يعدها مرتين فيتضخم وزن صاحبها بلا أن ينشر
     * درسا. والمتصفح يزيل التكرار بـ`course_id` بالقاعدة نفسها التي
     * تزيله بها `contributors()` — فما يعرض وأنت تختار هو ما سيقيد.
     *
     * @return array grade_id => [ [c, t, n, f], ... ]
     *               c=الدورة · t=المعلم · n=دروسها · f=معامل مسارها
     */
    public function grade_weight_map()
    {
        static $cache = null;
        if ($cache !== null) return $cache;

        $map   = array();
        $paths = $this->db->query(
            'SELECT `id`, `grade_id`, `teacher_id`, `course_id`, `teacher_share_percent`
               FROM `paths`
              WHERE `status` = "published" AND `grade_id` > 0 AND `teacher_id` > 0
              ORDER BY `id` ASC'
        )->result_array();
        if (!$paths) return $cache = $map;

        $cids = array();
        foreach ($paths as $p) if ((int) $p['course_id'] > 0) $cids[] = (int) $p['course_id'];
        $cids = array_values(array_unique($cids));
        if (!$cids) return $cache = $map;

        $per_course = array();
        foreach ($this->db->query(
            'SELECT `course_id`, COUNT(*) n FROM `lesson`
              WHERE `course_id` IN (' . implode(',', $cids) . ')
                AND COALESCE(`tq_status`, "published") = "published"
              GROUP BY `course_id`'
        )->result_array() as $r) {
            $per_course[(int) $r['course_id']] = (int) $r['n'];
        }

        foreach ($paths as $p) {
            $cid = (int) $p['course_id'];
            $n   = isset($per_course[$cid]) ? $per_course[$cid] : 0;
            if ($cid <= 0 || $n <= 0) continue;
            $g = (int) $p['grade_id'];
            if (!isset($map[$g])) $map[$g] = array();
            $map[$g][] = array(
                'c' => $cid,
                't' => (int) $p['teacher_id'],
                'n' => $n,
                'f' => $this->path_weight_factor($p),
            );
        }
        return $cache = $map;
    }

    /**
     * معامل وزن المسار — نسبة معلمه، وإلا الافتراض.
     *
     * القيمة المطلقة لا تعني شيئا: القسمة نسبية، فما يهم هو أن مسارا
     * نسبته ٣٠ تزن دروسه ضعف مسار نسبته ١٥. ولذلك لا يقسم على الافتراض
     * ولا يعاد إلى واحد — يؤخذ كما هو، والصفر يعني «هذا المسار لا يورث
     * صاحبه شيئا من الباقة».
     */
    public function path_weight_factor($path)
    {
        $v = isset($path['teacher_share_percent']) ? $path['teacher_share_percent'] : null;
        if ($v === null || $v === '' || !is_numeric($v)) return (int) self::DEFAULT_WEIGHT;
        return max(0, min(100, (int) round((float) $v)));
    }

    /* ================================================================
     *  القسمة
     * ================================================================ */

    /**
     * يقسم مبلغا صحيحا على أوزان صحيحة — بأكبر البواقي، بلا كسر عشري.
     *
     * لكل وزن `intdiv(المبلغ × وزنه، مجموع الأوزان)` وباقيه الصحيح، ثم
     * توزع الهللات المتبقية واحدة واحدة على أصحاب أكبر البواقي. فالمجموع
     * يساوي المبلغ **بالضبط** مهما كانت الأوزان — لا `round()` تجمع
     * فتتجاوز، ولا `floor()` تجمع فتترك بقية معلقة لا صاحب لها.
     *
     * @param  int   $amount  بالهللات
     * @param  array $weights مفتاح => وزن صحيح
     * @return array مفتاح => نصيب بالهللات
     */
    public function allocate($amount, $weights)
    {
        $amount = (int) $amount;
        $total  = 0;
        foreach ($weights as $w) $total += (int) $w;

        $out = array();
        foreach ($weights as $k => $w) $out[$k] = 0;
        if ($amount <= 0 || $total <= 0) return $out;

        $rem = array();
        $sum = 0;
        foreach ($weights as $k => $w) {
            $num     = $amount * (int) $w;
            $out[$k] = intdiv($num, $total);
            $rem[$k] = $num % $total;
            $sum    += $out[$k];
        }

        $left = $amount - $sum;
        if ($left > 0) {
            /* الترتيب بالباقي نازلا، والتعادل يكسر بترتيب الدخول —
               وهو ترتيب `contributors()` الثابت. */
            arsort($rem, SORT_NUMERIC);
            foreach (array_keys($rem) as $k) {
                if ($left <= 0) break;
                $out[$k]++;
                $left--;
            }
        }
        return $out;
    }

    /**
     * القسمة الكاملة لبيعة باقة — بلا كتابة، فتصلح للمعاينة وللتنفيذ معا.
     *
     * قسمتان مستقلتان على الأوزان نفسها: واحدة على الوعاء (حصص المعلمين)
     * وأخرى على `السعر − الوعاء` (عمولة المنصة موزعة). ومجموعهما لكل
     * معلم نصيبه من السعر، ومجموع الأنصبة السعر بالضبط.
     *
     * @return array gross · pool · platform · rows · orphans · unallocated
     */
    public function split($plan, $gross_halalas = null)
    {
        $gross = $gross_halalas === null ? (int) $plan['price'] : (int) $gross_halalas;
        $gross = max(0, $gross);

        $pct = $this->pool_percent($plan);

        /* الوعاء يقرب مرة واحدة، وعمولة المنصة **الباقي** لا حاصل ضرب
           ثان — فمجموعهما السعر ولو كانت النسبة ٣٣.٣٣٪. */
        $pool     = (int) round($gross * $pct / 100);
        $platform = $gross - $pool;

        $c = $this->plan_contributors($plan);

        $out = array(
            'gross'            => $gross,
            'pool_percent'     => $pct,
            'pool'             => $pool,
            'platform'         => $platform,
            'platform_percent' => round(100 - $pct, 2),
            'lessons_total'    => $c['lessons_total'],
            'quizzes_total'    => $c['quizzes_total'],
            'weight_total'     => $c['weight_total'],
            'orphans'          => $c['orphans'],
            'teachers'         => count($c['teachers']),
            'rows'             => array(),
            'unallocated'      => 0,
        );

        if (!$c['teachers'] || $c['weight_total'] <= 0) {
            /* لا معلم مستحقا: الوعاء لا يوزع، ويقال ذلك صراحة بدل أن
               يبتلع في عمولة المنصة صامتا. الباقة تباع ولا يقيد لأحد
               شيء — وهذا خبر يخص المسؤول. */
            $out['unallocated'] = $pool;
            return $out;
        }

        $weights = array();
        foreach ($c['teachers'] as $tid => $t) $weights[$tid] = (int) $t['weight'];

        $shares = $this->allocate($pool, $weights);
        $cuts   = $this->allocate($platform, $weights);

        foreach ($c['teachers'] as $tid => $t) {
            $share = (int) $shares[$tid];
            $cut   = (int) $cuts[$tid];
            $out['rows'][$tid] = array(
                'teacher_id' => $tid,
                'lessons'    => $t['lessons'],
                'quizzes'    => $t['quizzes'],
                'weight'     => $t['weight'],
                'paths'      => $t['paths'],
                'share'      => $share,
                'commission' => $cut,
                'attributed' => $share + $cut,
                'weight_pct' => $c['weight_total'] > 0
                                ? round($t['weight'] * 100 / $c['weight_total'], 2) : 0,
                'lesson_pct' => $c['lessons_total'] > 0
                                ? round($t['lessons'] * 100 / $c['lessons_total'], 2) : 0,
            );
        }
        return $out;
    }

    /* ================================================================
     *  التنفيذ: من القسمة إلى الدفاتر
     * ================================================================ */

    /**
     * يقيد حصص معلمي باقة في دفاترهم — مرة واحدة لكل اشتراك.
     *
     * متكرر الأمان من وجهين: `uq_rs` يمنع صف قسمة ثانيا، و`ref` الفريد
     * في `wallet_entries` يمنع قيدا ثانيا. فتفعيل مكرر — من ويبهوك تاب
     * ثم من المصالحة ثم من زر «اسأل تاب» — لا يضاعف مالا.
     *
     * والقسمة **تجمد** في `revenue_shares` وقت التفعيل: نشر عشرين درسا
     * غدا لا يعيد حساب بيعة أمس، تماما كما لا يوسع `subscription_items`.
     *
     * @return array ok · credited · pool · rows
     */
    public function credit_plan_sale($subscription_id, $plan = null, $gross_halalas = null)
    {
        $this->install_schema();
        $sid = (int) $subscription_id;
        if ($sid < 1) return array('ok' => false, 'errors' => array('لا اشتراك.'));

        $sub = $this->db->where('id', $sid)->get('subscriptions')->row_array();
        if (!$sub) return array('ok' => false, 'errors' => array('الاشتراك غير موجود.'));

        if ($plan === null) {
            $plan = $this->db->where('id', (int) $sub['plan_id'])->get('plans')->row_array();
        }
        if (!$plan) return array('ok' => false, 'errors' => array('باقة الاشتراك غير موجودة.'));

        $gross = $gross_halalas === null ? (int) $sub['price'] : (int) $gross_halalas;
        if ($gross <= 0) {
            return array('ok' => true, 'credited' => 0, 'pool' => 0, 'rows' => array(),
                         'note' => 'اشتراك بلا مبلغ — لا قسمة.');
        }

        // قسم مسبقا: لا يعاد الحساب ولا يقيد ثانية.
        if ((int) $this->db->where('subscription_id', $sid)
                           ->count_all_results('revenue_shares') > 0) {
            return array('ok' => true, 'credited' => 0, 'pool' => 0,
                         'rows' => $this->shares_of($sid), 'note' => 'قسمت من قبل.');
        }

        $split = $this->split($plan, $gross);
        if (!$split['rows']) {
            $this->log_unallocated($sid, $plan, $split);
            return array('ok' => true, 'credited' => 0, 'pool' => $split['pool'], 'rows' => array(),
                         'note' => 'لا معلم مستحقا في نطاق هذه الباقة — لم يوزع الوعاء.');
        }

        $this->load->model('taqdar_wallet_model');
        $subject = $this->plan_subject($plan);
        $n = 0;

        foreach ($split['rows'] as $tid => $r) {
            $this->db->query(
                'INSERT IGNORE INTO `revenue_shares`
                    (`subscription_id`,`plan_id`,`teacher_id`,`basis`,`gross_halalas`,
                     `pool_percent`,`pool_halalas`,`lessons`,`lessons_total`,
                     `weight`,`weight_total`,`attributed_halalas`,`amount_halalas`,`paths_json`)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                array($sid, (int) $plan['id'], $tid, 'availability', $split['gross'],
                      $split['pool_percent'], $split['pool'], $r['lessons'], $split['lessons_total'],
                      $r['weight'], $split['weight_total'], $r['attributed'], $r['share'],
                      json_encode($r['paths'], JSON_UNESCAPED_UNICODE))
            );

            $res = $this->taqdar_wallet_model->credit_plan_share(
                $tid, $sid, $r['attributed'], $r['share'], $subject
            );
            if (!empty($res['ok'])) $n++;
        }

        return array('ok' => true, 'credited' => $n, 'pool' => $split['pool'],
                     'rows' => $split['rows'], 'lessons_total' => $split['lessons_total']);
    }

    /** وصف البيعة كما يقرؤه المعلم في كشفه — يجمد وقت القيد. */
    private function plan_subject($plan)
    {
        $name = trim((string) $plan['name_ar']);
        if ($name === '') $name = (string) $plan['code'];
        return 'باقة — ' . $name;
    }

    /**
     * باقة بيعت ولا معلم في نطاقها: يكتب في السجل لا في الصمت.
     * وعاء لم يوزع مال احتفظت به المنصة بلا قرار، وهو أول ما يسأل عنه
     * حين يقول معلم «باعوا صفي ولم يصلني شيء».
     */
    private function log_unallocated($sid, $plan, $split)
    {
        if ((int) $split['pool'] <= 0) return;
        log_message('error',
            'TQ-REVENUE: وعاء غير موزع — اشتراك #' . (int) $sid
            . ' باقة ' . (string) $plan['code']
            . ' وعاء ' . number_format($split['pool'] / 100, 2) . ' ر.س'
            . ' — لا مسار منشور بمعلم في نطاقها.');
    }

    /**
     * يعكس قسمة اشتراك استرد — قيد عكسي في كل دفتر، ولا يحذف صف قسمة.
     *
     * الحذف يمحو الدليل: الرقم الذي حسب والوقت الذي حسب فيه هما ما يجاب
     * به المعلم حين يسأل «لم نقص رصيدي؟». فالصف يبقى، والعكس يقيد.
     */
    public function reverse_plan_sale($subscription_id, $reason = '')
    {
        $sid  = (int) $subscription_id;
        $rows = $this->db->where('subscription_id', $sid)->get('revenue_shares')->result_array();
        if (!$rows) return 0;

        $this->load->model('taqdar_wallet_model');
        $n = 0;
        foreach ($rows as $r) {
            if ($this->taqdar_wallet_model->reverse_plan_share(
                    (int) $r['teacher_id'], $sid, $reason)) $n++;
        }
        return $n;
    }

    /**
     * يعيد قسمة بيعة على المستحقين **الآن** — TQ-REVENUE-RESPLIT.
     *
     * ═══ لماذا وجد ═══
     *
     * القسمة تجمد وقت التفعيل، وهذا صواب: نشر عشرين درسا غدا لا يعيد
     * حساب بيعة أمس. لكن الحال التي تفضح الجمود هي التي وقعت فعلا:
     *
     *   • باعت المنصة باقة صف، فقسمت على مساري ذلك الصف يومها.
     *   • ثم **حذفت الكورسات** التي خلف المسارين من اللوحة.
     *   • ثم أنشأ معلم كورسه ونشر فيه دروسه في الصف نفسه.
     *
     * فالقيد قائم لمن لم يعد له محتوى، ومن يخدم المشتركين اليوم محفظته
     * صفر — ولا شيء في المنصة يستطيع تصحيح ذلك: `credit_plan_sale()`
     * ترد «قسمت من قبل»، و`reverse_plan_sale()` تعكس القيود **وتترك
     * صفوف `revenue_shares` قائمة**، فلا يعاد الحساب بعدها أبدا.
     *
     * ═══ ولا يفعل نفسه ═══
     *
     * لا تنادى من مسار تلقائي واحد. هي **قرار إداري صريح** يضغطه
     * مسؤول على بيعة بعينها، ويترك أثره في `audit_log` — لأن نقل مال
     * من محفظة إلى أخرى بعد أن قيد ليس تصحيح رقم.
     *
     * @return array عكس · قسمة جديدة · وعاء
     */
    public function resplit_plan_sale($subscription_id, $reason = '')
    {
        $this->install_schema();
        $sid = (int) $subscription_id;
        if ($sid < 1) return array('ok' => false, 'errors' => array('لا اشتراك.'));

        $sub = $this->db->where('id', $sid)->get('subscriptions')->row_array();
        if (!$sub) return array('ok' => false, 'errors' => array('الاشتراك غير موجود.'));

        $before = $this->shares_of($sid);

        /* ١ — تعكس القيود في المحافظ */
        $reversed = $this->reverse_plan_sale($sid, $reason !== '' ? $reason : 'إعادة قسمة');

        /* ٢ — تمحى الصفوف المجمدة، وإلا ردت `credit_plan_sale` «قسمت
               من قبل» ولم يحدث شيء. والمحو بعد العكس لا قبله: العكس
               يقرأ منها من له قيد. */
        $this->db->where('subscription_id', $sid)->delete('revenue_shares');

        /* ٣ — تقسم من جديد على المستحقين الآن */
        $r = $this->credit_plan_sale($sid, null, (int) $sub['price']);

        $after = $this->shares_of($sid);

        try {
            $this->load->model('taqdar_repo_model');
            $this->taqdar_repo_model->audit(
                (int) $this->tq_actor(), 'revenue.resplit', 'subscriptions:' . $sid,
                array('shares' => $before),
                array('shares' => $after, 'reversed' => $reversed, 'reason' => $reason)
            );
        } catch (Throwable $e) {
            log_message('error', 'TQ-REVENUE resplit audit: ' . $e->getMessage());
        }

        return array('ok' => true, 'reversed' => (int) $reversed,
                     'credited' => (int) ($r['credited'] ?? 0),
                     'pool' => (int) ($r['pool'] ?? 0),
                     'note' => isset($r['note']) ? $r['note'] : '',
                     'rows' => $after);
    }

    /** من ينفذ — للسجل. */
    private function tq_actor()
    {
        $CI = get_instance();
        return isset($CI->session) ? (int) $CI->session->userdata('user_id') : 0;
    }

    /* ================================================================
     *  القراءة — للشاشات
     * ================================================================ */

    /**
     * قراءة لا تبيض شاشة.
     *
     * TQ-REVENUE-READ — `revenue_shares` ينشأ وقت التشغيل كما تنشأ
     * `site_content` و`tq_otp`، ومنشئه `install_schema()`. وكان ينادى
     * من طريق الكتابة وحده (`credit_plan_sale`)، فأول من يقرأ قبل أول
     * بيعة يقرأ **جدولا غير موجود**: يرد التعريف `FALSE`، ثم
     * `result_array()` عليه خطأ قاتل — وصفحة بيضاء على شاشة مال أسوأ
     * ما يعرض لمعلم يسأل «كم لي؟».
     *
     * فالبنية تركب أولا هنا كما تركب هناك، وما بقي من عطل يسجل ويرد
     * فارغا: كشف حساب بلا سطور شرح أهون من كشف لا يفتح. وهي القاعدة
     * نفسها التي تكتب بها استعلامات اللوحة على الجداول الوليدة
     * (`safe_rows` في `Taqdar_admin_model`).
     */
    private function read_rows($sql, $args = array())
    {
        try {
            $this->install_schema();
            $q = $this->db->query($sql, $args);
            return $q instanceof CI_DB_result ? $q->result_array() : array();
        } catch (Throwable $e) {
            log_message('error', 'TQ-REVENUE read: ' . $e->getMessage());
            return array();
        }
    }

    /** صفوف قسمة اشتراك، بأسماء معلميها. */
    public function shares_of($subscription_id)
    {
        return $this->read_rows(
            'SELECT r.*,
                    TRIM(CONCAT(COALESCE(u.`first_name`,""), " ", COALESCE(u.`last_name`,""))) teacher_name,
                    u.`email` teacher_email
               FROM `revenue_shares` r
               LEFT JOIN `users` u ON u.`id` = r.`teacher_id`
              WHERE r.`subscription_id` = ?
              ORDER BY r.`amount_halalas` DESC, r.`teacher_id` ASC',
            array((int) $subscription_id)
        );
    }

    /**
     * قسمات معلم واحد، مفهرسة بمفتاح المستند — تقرؤها شاشة المحفظة
     * لتشرح كل سطر باقة بدل أن تعرض رقما بلا سبب.
     */
    public function shares_for_teacher($teacher_id, $limit = 200)
    {
        $rows = $this->read_rows(
            'SELECT r.*, p.`name_ar` plan_name, p.`code` plan_code
               FROM `revenue_shares` r
               LEFT JOIN `plans` p ON p.`id` = r.`plan_id`
              WHERE r.`teacher_id` = ?
              ORDER BY r.`id` DESC LIMIT ' . (int) $limit,
            array((int) $teacher_id)
        );

        $by = array();
        foreach ($rows as $r) $by['plansub:' . (int) $r['subscription_id']] = $r;
        return $by;
    }

    /**
     * ما بيع فعلا من باقة: كم اشتراكا، وكم وزع على معلميها.
     * يفرق بين المعاينة (ما سيقسم) والتاريخ (ما قسم) في شاشة الباقة.
     */
    public function plan_sales($plan_id)
    {
        $rows = $this->read_rows(
            'SELECT COUNT(DISTINCT `subscription_id`) n,
                    COALESCE(SUM(`amount_halalas`),0) s
               FROM `revenue_shares` WHERE `plan_id` = ?',
            array((int) $plan_id)
        );
        $r = $rows ? $rows[0] : null;
        return array('count' => (int) ($r ? $r['n'] : 0),
                     'paid'  => (int) ($r ? $r['s'] : 0));
    }

    /** إجماليات معلم من قسمات الباقات — للوحة وللمحفظة. */
    public function teacher_totals($teacher_id)
    {
        $rows = $this->read_rows(
            'SELECT COUNT(*) n, COALESCE(SUM(`amount_halalas`),0) s,
                    COALESCE(SUM(`gross_halalas`),0) g
               FROM `revenue_shares` WHERE `teacher_id` = ?',
            array((int) $teacher_id)
        );
        $r = $rows ? $rows[0] : null;
        return array(
            'sales'  => (int) ($r ? $r['n'] : 0),
            'earned' => (int) ($r ? $r['s'] : 0),
            'gross'  => (int) ($r ? $r['g'] : 0),
        );
    }
}
