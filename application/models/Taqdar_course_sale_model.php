<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * TQ-COURSE-SALE — الكورس وحدة بيع ثالثة، لا سلعة ثانية بمحرك ثان.
 *
 * ═══ ما كان ═══
 *
 * الكورس على هذه المنصة **محتوى باقة لا سلعة**: صفحته تقول ذلك حرفا
 * («وحدة البيع هي الباقة»)، و`plans_for_course()` تدل على الباقة التي
 * تفتحه، والكتالوج يكتب `price = -1` أي «ضمن الباقات». وسلة Academy
 * الموروثة (`home/shopping_cart`) بلا قالب في سمة تقدر، فمن بلغها قرأ
 * «Page Not Found 404» — وهو الصواب: لم يكن هناك ما يشترى.
 *
 * وهذا يترك بابا مغلقا: من يريد **مادة واحدة** لا منهج مرحلة كاملا لا
 * يجد ما يشتريه، فيدفع ثمن الباقة أو ينصرف. والثاني هو ما يقع.
 *
 * ═══ والقاعدة الحاكمة: لا محرك ثان ═══
 *
 * الشراء المفرد **ليس نظاما موازيا**. هو صف `subscriptions` كما هو صف
 * المسار المفرد (`subscribe_path`) وصف الباقة: بفاتورته، وبنده في
 * `subscription_items` نوعه `course` — وهو نوع **قائم في المخطط منذ
 * كتب** ويقرؤه `subscription_grants()` و`grantable_course_ids()`
 * و`resync_scope()` بلا سطر يضاف. فتاب تسوي دفعته بالفرع نفسه
 * (`subscription_id > 0`)، والتحويل البنكي يفعله بالزر نفسه في اللوحة،
 * والكرون يجسد استحقاقه في `enrol` بالمرور نفسه، وينتهي أجله بـ
 * `expire_due()` نفسها. ونظام ثان كان يحتاج نسخة ثانية من كل واحد منها.
 *
 * ═══ وهذا الملف يملك **العرض** لا دورة الحياة ═══
 *
 * دورة الحياة في `Taqdar_billing_model` حيث تعيش دورة حياة الباقة
 * والمسار. وهنا سؤال واحد: **أيباع هذا الكورس مفردا، وبكم، ولمن؟** —
 * ومنه تقرأ الشاشات كلها: صفحة الكورس والكتالوج وشاشة التأكيد وبوابة
 * المعلم ولوحة الإدارة. ونسخة ثانية من قواعد السعر تعرض في صفحة رقما
 * وتقيد في القاعدة غيره.
 *
 * ═══ ثلاثة قرارات، ولكل واحد سببه ═══
 *
 * ١ — **السعر عمود واحد لا اثنان.** `course.price` هو المخزن، بالريال
 *     كما تكتبه شاشة التسعير الموروثة منذ كتبت، ومعه `discounted_price`
 *     و`discount_flag`. والتحويل إلى الهللات **هنا وحده** — كما تحول
 *     `tqs_money()` في الاتجاه الآخر في موضع واحد. وعمود ثان بالهللات
 *     يعني رقمين يفترقان عند أول تعديل، وشاشة تسعير قائمة تصير كاذبة.
 *
 * ٢ — **«يباع مفردا» صفة تعلن لا نتيجة سعر.** `course.tq_sell` عمود
 *     صريح. ولو استنتج البيع من `price > 0` لصار كل كورس حمل سعرا من
 *     استيراد قديم أو من شاشة Academy سلعة معروضة في اللحظة التي يفتح
 *     فيها هذا الباب — وهو المبدأ نفسه المكتوب في `subscribe()`:
 *     «مجانية صفة الباقة لا نتيجة خلو سعرها».
 *
 * ٣ — **بلا مفتاح لا شيء يتغير.** `tq_course_sales_enabled` مطفأ
 *     افتراضا، وحينها ترد `offer()` «لا يباع» لكل كورس، فتعرض صفحة
 *     الكورس والكتالوج ما كانا يعرضانه حرفا بحرف. وهي قاعدة تاب نفسها
 *     («بلا مفاتيح لا شيء يتغير») وقاعدة تسعيرة الحصص نفسها.
 *
 * ═══ والنسبة نسبة واحدة لا وعاء ═══
 *
 * `Taqdar_revenue_model` يقسم وعاء مغلقا على معلمين كثر لأن الباقة تفتح
 * محتواهم جميعا. والكورس المفرد **لمعلم واحد** (`course.creator`) —
 * فلا وعاء ولا أوزان ولا أكبر بواق، ونسبة واحدة تكفي. وهو الحكم نفسه
 * الذي حكم به `Taqdar_sessions_model`، وللسبب نفسه: «استدعاء القاسم
 * لصف واحد يقحم أوزان الدروس على بيعة لا وعاء فيها».
 *
 * والنسبة على درجتين: ما كتب للكورس، وإلا العام
 * (`tq_course_teacher_percent`، حده ٦٠). و**الفارغ غير الصفر**: كورس
 * كتب له صفر يعطى معلمه صفرا بقرار، وكورس لم يكتب له شيء يأخذ العام —
 * وعمود لا يفرق بينهما يجعل كل كورس لم يمر عليه المسؤول يحرم معلمه.
 */
class Taqdar_course_sale_model extends CI_Model
{
    /** إصدار المخطط — يرفع متى أضيف عمود، فيركب عند أول قراءة بعده. */
    const SCHEMA_V = '1';

    /** حد النسبة العامة حين لا يكتب المسؤول شيئا. */
    const DEFAULT_PERCENT = 60;

    /** الافتراضات العامة. تكتب هنا مرة وتقرأ منها الشاشات كلها. */
    public static $DEFAULTS = array(
        /* مطفأ: بلا مفتاح لا شيء يتغير في صفحة واحدة. */
        'tq_course_sales_enabled'   => 0,
        'tq_course_teacher_percent' => self::DEFAULT_PERCENT,
        /* أجل الشراء المفرد حين لا يحدد الكورس مدته بـ`expiry_period`.
           صفر = وصول دائم، وهو ما يتوقعه من اشترى مادة بعينها بثمنها. */
        'tq_course_default_days'    => 0,
    );

    private static $cfg_cache    = null;
    private static $schema_ready = false;

    /* =====================================================================
       المخطط — يركب وقت التشغيل كأخواته
       ===================================================================== */

    /**
     * ثلاثة أعمدة لا جدول: `subscriptions.course_id` كما `path_id`،
     * و`course.tq_sell` و`course.tq_teacher_percent`.
     *
     * ولا جدول `course_purchases`: الشراء اشتراك، وجدول ثان يعني مصدري
     * حقيقة لسؤال «أيملك هذا الطالب هذا المقرر؟» — وهما يفترقان.
     */
    public function install_schema($force = false)
    {
        if (self::$schema_ready && !$force) return false;
        self::$schema_ready = true;

        if (!$force && (string) $this->setting('tq_course_sale_schema_v', '') === self::SCHEMA_V) {
            return false;
        }

        /* CodeIgniter يخبئ أسماء أعمدة كل جدول في الطلب الواحد، فلو فحص
           بعد تعديل في النداء نفسه لقرأ قائمة بائتة وأعاد الإضافة. */
        $this->db->data_cache = array();

        if (!$this->field('course_id', 'subscriptions')) {
            $this->try_sql(
                'ALTER TABLE `subscriptions`'
              . ' ADD COLUMN `course_id` int(10) NOT NULL DEFAULT 0'
              . " COMMENT 'كورس اشتري مفردا — TQ-COURSE-SALE' AFTER `path_id`,"
              . ' ADD KEY `ix_sub_course` (`course_id`)'
            );
        }

        $this->db->data_cache = array();

        $add = array();
        if (!$this->field('tq_sell', 'course')) {
            $add[] = "ADD COLUMN `tq_sell` tinyint(1) NOT NULL DEFAULT 0"
                   . " COMMENT 'يباع مفردا خارج الباقات — TQ-COURSE-SALE'";
        }
        if (!$this->field('tq_teacher_percent', 'course')) {
            /* NULL يعني «خذ العام»، والصفر يعني «صفر بقرار». وعمود
               `NOT NULL DEFAULT 0` يخلطهما فيحرم كل معلم لم يمر عليه
               المسؤول — وهي قاعدة `users.tq_session_price` نفسها. */
            $add[] = "ADD COLUMN `tq_teacher_percent` decimal(5,2) DEFAULT NULL"
                   . " COMMENT 'نصيب المعلم من بيع هذا الكورس — فارغ يعني الافتراض العام'";
        }
        if ($add) $this->try_sql('ALTER TABLE `course` ' . implode(', ', $add));

        foreach (self::$DEFAULTS as $k => $v) $this->seed_setting($k, (string) $v);

        $this->put_setting('tq_course_sale_schema_v', self::SCHEMA_V);
        $this->db->data_cache = array();
        return true;
    }

    /** ينفذ تعديل بنية ولا يسقط الطلب إن كان منفذا من قبل. */
    private function try_sql($sql)
    {
        try { $this->db->query($sql); } catch (Throwable $e) { /* منفذ من قبل */ }
    }

    private function field($col, $table)
    {
        try { return $this->db->field_exists($col, $table); }
        catch (Throwable $e) { return true; }   // لا نضف على المجهول
    }

    private function setting($key, $default = null)
    {
        try {
            $r = $this->db->select('value')->where('key', $key)->get('settings')->row_array();
        } catch (Throwable $e) { return $default; }
        if (!$r) return $default;
        return ($r['value'] === null || trim((string) $r['value']) === '') ? $default : $r['value'];
    }

    /** يكتب المفتاح إن لم يكن — ولا يمس قيمة كتبها مسؤول. */
    private function seed_setting($key, $value)
    {
        try {
            if ($this->db->where('key', $key)->count_all_results('settings') === 0) {
                $this->db->insert('settings', array('key' => $key, 'value' => $value));
            }
        } catch (Throwable $e) { /* لا يسقط طلبا */ }
    }

    private function put_setting($key, $value)
    {
        try {
            if ($this->db->where('key', $key)->count_all_results('settings') > 0) {
                $this->db->where('key', $key)->update('settings', array('value' => $value));
            } else {
                $this->db->insert('settings', array('key' => $key, 'value' => $value));
            }
        } catch (Throwable $e) { /* لا يسقط طلبا */ }
    }

    /* =====================================================================
       الإعدادات العامة
       ===================================================================== */

    /** كل مفاتيح بيع الكورسات بقيمها المحدودة — استعلام واحد لا ثلاثة. */
    public function config($fresh = false)
    {
        if (self::$cfg_cache !== null && !$fresh) return self::$cfg_cache;

        $have = array();
        try {
            foreach ($this->db->select('key, value')
                              ->where_in('key', array_keys(self::$DEFAULTS))
                              ->get('settings')->result_array() as $r) {
                $have[$r['key']] = $r['value'];
            }
        } catch (Throwable $e) { /* قاعدة لم تركب بعد */ }

        $c = array();
        foreach (self::$DEFAULTS as $k => $def) {
            $c[$k] = (!isset($have[$k]) || trim((string) $have[$k]) === '') ? $def : $have[$k];
        }

        /* الحدود تفرض هنا لا في الشاشة: قيمة كتبت مرة بيد أو بسكربت تبقى
           تقرأ إلى الأبد، ونسبة ١٤٠٪ تعطي المعلم أكثر مما قبضت المنصة. */
        return self::$cfg_cache = array(
            'enabled'      => ((string) $c['tq_course_sales_enabled'] === '1'),
            'percent'      => max(0, min(100, (float) $c['tq_course_teacher_percent'])),
            'default_days' => max(0, min(3650, (int) $c['tq_course_default_days'])),
        );
    }

    /** يحفظ المفاتيح العامة — upsert، فالمفتاح الغائب ينشأ. */
    public function save_config($vals)
    {
        $this->install_schema();
        foreach (self::$DEFAULTS as $k => $_) {
            if (!array_key_exists($k, $vals)) continue;
            $this->put_setting($k, trim((string) $vals[$k]));
        }
        self::$cfg_cache = null;
        return $this->config(true);
    }

    /** أمفتوح باب البيع المفرد أصلا؟ سؤال تسأله الشاشات قبل أن تعرض شيئا. */
    public function enabled()
    {
        $c = $this->config();
        return $c['enabled'];
    }

    /* =====================================================================
       العرض — السؤال الواحد الذي يقرأ منه كل شيء
       ===================================================================== */

    /**
     * ما عرض هذا الكورس؟ — الجواب الكامل في مصفوفة واحدة.
     *
     * وهو **المصدر الواحد**: صفحة الكورس وشاشة التأكيد والكتالوج وبوابة
     * المعلم ولوحة الإدارة كلها تقرأ منه. ومحرك الشراء
     * (`Taqdar_billing_model::subscribe_course()`) يقرأ منه هو نفسه لا
     * من نسخة ثانية — فما يعد به الزر هو ما يقيد في الفاتورة بالهللة.
     *
     * @param  array|int $course صف الكورس أو معرفه
     * @return array
     *   sellable   — أيشترى مفردا الآن؟
     *   reason     — ولماذا لا، إن لم يكن (مفتاح ثابت يفرع عليه)
     *   why        — ونصها العربي، لشاشة الإدارة وبوابة المعلم
     *   price      — المخصوم بالهللات (بعد الخصم إن فعل)
     *   list_price — السعر قبل الخصم بالهللات (0 إن لا خصم)
     *   off        — نسبة الخصم المئوية (0 إن لا خصم)
     *   free       — كورس مجاني: لا يشترى ولا يمنع
     *   days       — أجل الوصول بالأيام (0 = دائم)
     *   percent    — نصيب المعلم %
     *   share      — نصيبه بالهللات · platform — عمولة المنصة
     *   teacher_id — صاحب الكورس · marked — أعلن للبيع؟
     */
    public function offer($course)
    {
        $this->install_schema();

        if (!is_array($course)) $course = $this->course((int) $course);

        $out = array(
            'course_id' => 0, 'title' => '', 'sellable' => false, 'reason' => 'no_course',
            'why' => 'لا كورس بهذا المعرف.', 'price' => 0, 'list_price' => 0, 'off' => 0,
            'free' => false, 'days' => 0, 'percent' => 0.0, 'share' => 0, 'platform' => 0,
            'teacher_id' => 0, 'marked' => false,
        );
        if (!$course) return $out;

        $cfg = $this->config();

        $out['course_id']  = (int) $course['id'];
        $out['title']      = (string) $course['title'];
        $out['teacher_id'] = (int) $course['creator'];
        $out['free']       = ((int) (isset($course['is_free_course']) ? $course['is_free_course'] : 0) === 1);
        $out['marked']     = ((int) (isset($course['tq_sell']) ? $course['tq_sell'] : 0) === 1);
        $out['days']       = $this->access_days($course);
        $out['percent']    = $this->teacher_percent($course);

        /* السعر يحسب دائما ولو لم يبع: شاشة الإدارة تريه لتقول «سعرته
           ولم تعلنه»، وهي أنفع من صفر لا يفسر. */
        $list = $this->to_halalas(isset($course['price']) ? $course['price'] : 0);
        $sale = ((int) (isset($course['discount_flag']) ? $course['discount_flag'] : 0) === 1)
              ? $this->to_halalas(isset($course['discounted_price']) ? $course['discounted_price'] : 0)
              : 0;

        /* الخصم يقبل إن كان **أقل** من الأصل وأكبر من صفر: سعر خصم صفر أو
           أعلى من الأصل يعني حقلا ترك لا خصما قصد، والبيع به يفتح الكورس
           بلا ثمن أو يبيعه أغلى مما أعلن. */
        if ($sale > 0 && $sale < $list) {
            $out['price']      = $sale;
            $out['list_price'] = $list;
            $out['off']        = (int) round((($list - $sale) / $list) * 100);
        } else {
            $out['price'] = $list;
        }

        $split           = $this->split($out['price'], $out['percent']);
        $out['share']    = $split['share'];
        $out['platform'] = $split['platform'];

        /* ترتيب الأسباب هو ترتيب معالجتها: المسؤول يقرأ **أول** ما يمنع
           لا آخره، فيصلحه ثم يقرأ ما بعده. */
        if (!$cfg['enabled']) {
            $out['reason'] = 'disabled';
            $out['why']    = 'بيع الكورسات المفردة مطفأ في شاشة «بيع الكورسات».';
            return $out;
        }
        if ($out['free']) {
            $out['reason'] = 'free';
            $out['why']    = 'هذا الكورس مجاني، فلا يباع — يفتح لكل مسجل بلا دفع.';
            return $out;
        }
        if (!$out['marked']) {
            $out['reason'] = 'not_marked';
            $out['why']    = 'لم يعلن للبيع المفرد — علم «يباع مفردا» في تبويب التسعير.';
            return $out;
        }
        if ((string) $course['status'] !== 'active') {
            $out['reason'] = 'unpublished';
            $out['why']    = 'الكورس غير منشور، فلا يشترى — ولا يباع ما لا يفتح.';
            return $out;
        }
        if ($out['price'] <= 0) {
            $out['reason'] = 'unpriced';
            $out['why']    = 'أعلن للبيع ولم يسعر — وكورس بسعر صفر يفتح بلا ثمن.';
            return $out;
        }
        if ($out['teacher_id'] <= 0) {
            $out['reason'] = 'no_teacher';
            $out['why']    = 'لا معلم مسند إليه، فلا محفظة يقيد فيها نصيبه.';
            return $out;
        }
        if (!$this->has_content((int) $course['id'])) {
            $out['reason'] = 'empty';
            $out['why']    = 'لا درس واحد فيه — لا يباع محتوى لم يرفع.';
            return $out;
        }

        $out['sellable'] = true;
        $out['reason']   = 'ok';
        $out['why']      = 'معروض للبيع المفرد.';
        return $out;
    }

    /** صف الكورس بالأعمدة التي يحتاجها العرض وحدها. */
    public function course($course_id)
    {
        $this->install_schema();
        try {
            return $this->db->select('id, title, creator, status, price, discount_flag,'
                                   . ' discounted_price, is_free_course, expiry_period,'
                                   . ' tq_sell, tq_teacher_percent, thumbnail, short_description')
                            ->where('id', (int) $course_id)->get('course')->row_array();
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * نصيب المعلم من هذا الكورس: ما كتب له، وإلا العام.
     * والفارغ غير الصفر — انظر رأس الملف.
     */
    public function teacher_percent($course)
    {
        if (!is_array($course)) $course = $this->course((int) $course);
        $cfg = $this->config();
        if (!$course) return $cfg['percent'];

        $v = isset($course['tq_teacher_percent']) ? $course['tq_teacher_percent'] : null;
        if ($v === null || trim((string) $v) === '') return $cfg['percent'];

        return max(0, min(100, (float) $v));
    }

    /**
     * يقسم سعرا على نسبة. التقريب مرة واحدة والباقي للمنصة، فلا تضيع
     * هللة ولا تخترع — وهي القاعدة نفسها في `credit_path_sale()` وفي
     * `Taqdar_sessions_model::split()`.
     */
    public function split($price_halalas, $percent)
    {
        $price   = max(0, (int) $price_halalas);
        $percent = max(0, min(100, (float) $percent));
        $share   = (int) round($price * $percent / 100);
        return array('price' => $price, 'percent' => $percent,
                     'share' => $share, 'platform' => $price - $share);
    }

    /**
     * أجل الوصول بالأيام.
     *
     * `course.expiry_period` أشهر — وهو الحقل الذي تكتبه شاشة التسعير
     * منذ كتبت («مدة الوصول: وصول دائم / مدة محدودة»). فلا حقل ثالث
     * يخترع لشيء له حقله. وصفر يعني دائما، فلا `ends_at` على الاشتراك.
     */
    public function access_days($course)
    {
        if (!is_array($course)) $course = $this->course((int) $course);
        $months = $course ? (int) (isset($course['expiry_period']) ? $course['expiry_period'] : 0) : 0;
        if ($months > 0) return $months * 30;

        $c = $this->config();
        return $c['default_days'];   // صفر افتراضا = دائم
    }

    /** أفيه درس واحد على الأقل؟ لا يباع محتوى لم يرفع. */
    private function has_content($course_id)
    {
        try {
            return $this->db->where('course_id', (int) $course_id)
                            ->count_all_results('lesson') > 0;
        } catch (Throwable $e) { return true; }
    }

    /** من الريال إلى الهللات — التحويل الواحد، وفي هذا الملف وحده. */
    private function to_halalas($sar)
    {
        return max(0, (int) round(((float) $sar) * 100));
    }

    /* =====================================================================
       القوائم — للكتالوج وللوحة
       ===================================================================== */

    /**
     * عروض الكورسات المعلنة للبيع، مفهرسة بالمعرف.
     *
     * الكتالوج يعرض عشرات البطاقات، ونداء `offer()` لكل واحدة يعني
     * استعلامين لكل بطاقة. فهذه تقرأ الصفوف وعدد الدروس دفعة، وتشكل
     * كل صف من `offer()` **نفسها** — لا من نسخة ثانية من قواعدها.
     *
     * @param bool $only_sellable ما يشترى فعلا وحده، أو كل ما علم
     */
    public function offers($only_sellable = true)
    {
        $this->install_schema();
        if ($only_sellable && !$this->enabled()) return array();

        try {
            $rows = $this->db->select('id, title, creator, status, price, discount_flag,'
                                    . ' discounted_price, is_free_course, expiry_period,'
                                    . ' tq_sell, tq_teacher_percent, thumbnail, short_description')
                             ->from('course')->where('tq_sell', 1)
                             ->order_by('id', 'DESC')->get()->result_array();
        } catch (Throwable $e) {
            return array();
        }
        if (!$rows) return array();

        $ids  = array_map('intval', array_column($rows, 'id'));
        $have = array();
        try {
            foreach ($this->db->select('course_id, COUNT(*) n', false)->from('lesson')
                              ->where_in('course_id', $ids)->group_by('course_id')
                              ->get()->result_array() as $r) {
                $have[(int) $r['course_id']] = (int) $r['n'];
            }
        } catch (Throwable $e) { /* بلا عد: `offer()` تسأل بنفسها */ }

        $out = array();
        foreach ($rows as $r) {
            $o = $this->offer($r);
            $o['lessons'] = isset($have[(int) $r['id']]) ? $have[(int) $r['id']] : 0;
            if ($only_sellable && !$o['sellable']) continue;
            $out[(int) $r['id']] = $o;
        }
        return $out;
    }

    /** معرفات ما يشترى الآن — للكتالوج ولمرشحاته. */
    public function sellable_ids()
    {
        return array_keys($this->offers(true));
    }

    /* =====================================================================
       الملكية — «أاشترى هذا الطالب هذا الكورس مفردا؟»
       ===================================================================== */

    /**
     * الكورسات التي اشتراها هذا الطالب مفردة وما زالت سارية.
     *
     * والسؤال يقرأ من `subscriptions` لا من `enrol`: `enrol` يمتلئ من
     * الباقة أيضا، فمن اشترك في باقة صفه له صف فيه لكل كورس فيها — وقول
     * «اشتريت هذا الكورس» عن كورس فتحته باقته خطأ في **العرض** وإن كان
     * الوصول صحيحا. وهذه الدالة تجيب سؤال العرض وحده؛ وسؤال الوصول
     * يجيبه `subscription_grants()` كما كان.
     *
     * @return array معرف الكورس => صف الاشتراك
     */
    public function owned_by($user_id)
    {
        $this->install_schema();
        $user_id = (int) $user_id;
        if ($user_id <= 0) return array();

        try {
            $rows = $this->db->where('user_id', $user_id)
                             ->where('course_id >', 0)
                             ->where_in('status', array('active', 'cancelled'))
                             ->order_by('id', 'DESC')->get('subscriptions')->result_array();
        } catch (Throwable $e) { return array(); }

        $out = array();
        foreach ($rows as $r) {
            if (!empty($r['ends_at']) && strtotime($r['ends_at']) < time()) continue;
            if ($r['status'] === 'cancelled' && empty($r['ends_at']))       continue;
            $cid = (int) $r['course_id'];
            if (!isset($out[$cid])) $out[$cid] = $r;
        }
        return $out;
    }

    /** أاشترى هذا الكورس بعينه مفردا؟ */
    public function owns($user_id, $course_id)
    {
        $own = $this->owned_by($user_id);
        return isset($own[(int) $course_id]);
    }

    /**
     * اشتراك معلق لهذا الكورس بفاتورة لم تدفع — إن وجد.
     *
     * تقرأ منه صفحة الكورس فتقول «فاتورتك صدرت ولم تسدد» وتدل على
     * سدادها، بدل أن تعرض زر شراء ثانيا لمن اشترى قبل دقيقة ولم يحول
     * بعد — فيصدر صفان وفاتورتان، وتبقى إحداهما «غير مدفوعة» في سجل
     * مالي أبدا. وهو ما يمنعه TQ-SUB-REUSE في الباقة.
     */
    public function pending_of($user_id, $course_id)
    {
        $this->install_schema();
        try {
            $s = $this->db->where('user_id', (int) $user_id)
                          ->where('course_id', (int) $course_id)
                          ->where('status', 'pending')
                          ->order_by('id', 'DESC')->limit(1)
                          ->get('subscriptions')->row_array();
        } catch (Throwable $e) { return null; }
        if (!$s) return null;

        $CI = get_instance();
        $CI->load->model('taqdar_billing_model');
        $inv = $CI->taqdar_billing_model->invoice_of_subscription((int) $s['id']);
        if (!$inv || $inv['status'] !== 'unpaid') return null;

        $s['invoice'] = $inv;
        return $s;
    }

    /* =====================================================================
       المبيعات — للوحة ولبوابة المعلم
       ===================================================================== */

    /**
     * مبيعات الكورسات المفردة صفا بصف — مشتريها وحالها وفاتورتها.
     *
     * @param int $teacher_id قصرها على كورسات معلم بعينه، أو 0 لكلها
     */
    public function sales($teacher_id = 0, $limit = 200)
    {
        $this->install_schema();

        $where = 's.`course_id` > 0';
        $args  = array();
        if ((int) $teacher_id > 0) { $where .= ' AND c.`creator` = ?'; $args[] = (int) $teacher_id; }

        try {
            return $this->db->query(
                'SELECT s.`id`, s.`user_id`, s.`course_id`, s.`status`, s.`price`,
                        s.`method`, s.`started_at`, s.`ends_at`, s.`created_at`,
                        c.`title` AS course_title, c.`creator` AS teacher_id,
                        c.`tq_teacher_percent`,
                        TRIM(CONCAT(COALESCE(b.`first_name`,""), " ",
                                    COALESCE(b.`last_name`,""))) AS buyer_name,
                        TRIM(CONCAT(COALESCE(t.`first_name`,""), " ",
                                    COALESCE(t.`last_name`,""))) AS teacher_name,
                        i.`id` AS invoice_id, i.`invoice_no`,
                        i.`status` AS invoice_status, i.`total` AS invoice_total
                   FROM `subscriptions` s
                   JOIN `course` c ON c.`id` = s.`course_id`
              LEFT JOIN `users` b ON b.`id` = s.`user_id`
              LEFT JOIN `users` t ON t.`id` = c.`creator`
              LEFT JOIN `invoices` i ON i.`id` = (
                        SELECT MAX(`id`) FROM `invoices` WHERE `subscription_id` = s.`id`)
                  WHERE ' . $where . '
               ORDER BY s.`id` DESC
                  LIMIT ' . (int) $limit, $args
            )->result_array();
        } catch (Throwable $e) {
            log_message('error', 'TQ-COURSE-SALE sales(): ' . $e->getMessage());
            return array();
        }
    }

    /**
     * ملخص مبيعات الكورسات — للوحة ولبطاقة المعلم.
     *
     * المحصل من **الاشتراكات التي فعلت** لا من الفواتير التي صدرت:
     * فاتورة صدرت ولم تحول ليست إيرادا، وعدها يجعل اللوحة تعد مالا لم
     * يصل. وهي تعد وحدها في `pending` فيعرف المسؤول كم ينتظر حوالة.
     */
    public function totals($teacher_id = 0)
    {
        $this->install_schema();

        $where = 's.`course_id` > 0';
        $args  = array();
        if ((int) $teacher_id > 0) { $where .= ' AND c.`creator` = ?'; $args[] = (int) $teacher_id; }

        $zero = array('sold' => 0, 'gross' => 0, 'pending' => 0,
                      'pending_amount' => 0, 'courses' => 0);
        try {
            $r = $this->db->query(
                'SELECT COUNT(CASE WHEN s.`status` IN ("active","cancelled","expired") THEN 1 END) sold,
                        COALESCE(SUM(CASE WHEN s.`status` IN ("active","cancelled","expired")
                                          THEN s.`price` ELSE 0 END), 0) gross,
                        COUNT(CASE WHEN s.`status` = "pending" THEN 1 END) pending,
                        COALESCE(SUM(CASE WHEN s.`status` = "pending" THEN s.`price` ELSE 0 END), 0) pending_amount,
                        COUNT(DISTINCT CASE WHEN s.`status` IN ("active","cancelled","expired")
                                            THEN s.`course_id` END) courses
                   FROM `subscriptions` s
                   JOIN `course` c ON c.`id` = s.`course_id`
                  WHERE ' . $where, $args
            )->row_array();
        } catch (Throwable $e) { return $zero; }

        return $r ? array_map('intval', $r) : $zero;
    }

    /** عدد مبيعات كل كورس ومحصلها — لعمود في قائمة اللوحة. */
    public function sold_counts()
    {
        $this->install_schema();
        $out = array();
        try {
            foreach ($this->db->query(
                'SELECT `course_id`, COUNT(*) n, COALESCE(SUM(`price`),0) g
                   FROM `subscriptions`
                  WHERE `course_id` > 0 AND `status` IN ("active","cancelled","expired")
               GROUP BY `course_id`')->result_array() as $r) {
                $out[(int) $r['course_id']] = array('n' => (int) $r['n'], 'gross' => (int) $r['g']);
            }
        } catch (Throwable $e) { /* لا مبيعات بعد */ }
        return $out;
    }

    /* =====================================================================
       الحفظ من اللوحة
       ===================================================================== */

    /**
     * يحفظ عرض كورس واحد — يناديها صف شاشة «بيع الكورسات».
     *
     * وهي الباب الواحد الذي يكتب أعمدة العرض، فتبويب التسعير في شاشة
     * الكورس يمر بها كذلك. ونسختان من قاعدة «لا بيع بلا سعر» تفترقان،
     * فتقبل إحداهما ما ترفضه الأخرى.
     */
    public function save_offer($course_id, $sell, $price_sar, $discount_sar, $discount_on, $percent)
    {
        $this->install_schema();
        $course_id = (int) $course_id;
        if ($course_id <= 0) return array('ok' => false, 'errors' => array('لا كورس.'));

        $cur = $this->course($course_id);
        if (!$cur) return array('ok' => false, 'errors' => array('لا كورس بهذا المعرف.'));

        $sell         = (bool) $sell;
        $discount_on  = (bool) $discount_on;
        $price_sar    = trim((string) $price_sar);
        $discount_sar = trim((string) $discount_sar);
        $percent      = trim((string) $percent);

        $data = array('tq_sell' => $sell ? 1 : 0);

        if ($price_sar !== '') $data['price'] = max(0, (float) $price_sar);
        $data['discount_flag'] = $discount_on ? 1 : 0;
        if ($discount_sar !== '')  $data['discounted_price'] = max(0, (float) $discount_sar);
        elseif (!$discount_on)     $data['discounted_price'] = null;

        /* الفارغ NULL لا صفر — «خذ العام» غير «صفر بقرار». */
        $data['tq_teacher_percent'] = ($percent === '') ? null : max(0, min(100, (float) $percent));

        /* بيع بلا سعر يرفض **هنا** لا عند المشتري: كورس معلن بصفر يفتح
           بلا ثمن، ولا شيء في الشاشة يقول ذلك لمن حفظه. */
        $base = array_key_exists('price', $data) ? $data['price'] : (float) $cur['price'];
        $disc = array_key_exists('discounted_price', $data)
              ? (float) $data['discounted_price'] : (float) $cur['discounted_price'];
        $eff  = ($data['discount_flag'] && $disc > 0 && $disc < $base) ? $disc : $base;

        if ($sell && $eff <= 0 && (int) $cur['is_free_course'] !== 1) {
            return array('ok' => false, 'errors' => array(
                'كورس معلن للبيع بلا سعر يفتح بلا ثمن. اكتب سعره أو ارفع الإعلان.'));
        }

        $this->db->where('id', $course_id)->update('course', $data);
        $this->audit('course.sale.offer', 'course#' . $course_id, $cur, $this->course($course_id));

        return array('ok' => true, 'offer' => $this->offer($course_id));
    }

    /**
     * TQ-COURSE-SALE-DELETE — **كورس بيع مفردا لا يحذف، يرفع إعلانه.**
     *
     * المبدأ مبدأ TQ-PLAN-DELETE نفسه: `subscriptions.course_id` و
     * `invoices` و`wallet_entries` تشير إليه بمعرفه، وحذف الصف يترك
     * كشف حساب معلم يقول «كورس #106» لا يعرف أحد ما كان، وفاتورة بمبلغ
     * لا يقابله شيء، **ومشتريا دفع ثمن محتوى اختفى**. والضرر كله في
     * القراءة وهو لا يرجع.
     *
     * والفرق عن الباقة أن الوصول ينقطع هنا فعلا: بند `course` يشير إلى
     * صف محذوف، و`grantable_course_ids()` تسقطه بضمها على `course` —
     * فمن دفع يفتح «كورساتي» ولا يجد ما اشترى. فالمنع أشد لزوما.
     *
     * ويرد بالرقم لا بـ«غير مسموح»: من قرأ «لا يحذف» بلا سبب يظن الشاشة
     * معطلة، ومن قرأ «بيع اثنتي عشرة مرة» يعرف ما يفعل. والبديل يقال
     * معه — رفع الإعلان يوقف البيع ويخفيه من كل صفحة عامة، وهو ما
     * يريده من ضغط «حذف».
     *
     * @return array سطور المنع — فارغة تعني «يحذف»
     */
    public function delete_blockers($course_id)
    {
        $this->install_schema();
        $course_id = (int) $course_id;
        $out = array();
        if ($course_id <= 0) return $out;

        try {
            $r = $this->db->query(
                'SELECT COUNT(*) n, COALESCE(SUM(`price`),0) g
                   FROM `subscriptions`
                  WHERE `course_id` = ? AND `status` IN ("active","cancelled","expired")',
                array($course_id))->row_array();
            if ($r && (int) $r['n'] > 0) {
                $out[] = 'بيع مفردا ' . (int) $r['n'] . ' مرة بمحصل '
                       . number_format(((int) $r['g']) / 100) . ' ر.س';
            }

            /* والمعلق يعد كذلك: فاتورة صدرت ولم تحول بعد، وحذف الكورس
               تحتها يترك مشتريا يحول ثمن شيء لم يعد موجودا. */
            $p = $this->db->where('course_id', $course_id)
                          ->where('status', 'pending')
                          ->count_all_results('subscriptions');
            if ($p > 0) $out[] = $p . ' فاتورة صدرت وتنتظر السداد';
        } catch (Throwable $e) {
            /* عمود لم ينشأ بعد = لا بيع مفرد وقع = لا مانع. */
        }
        return $out;
    }

    /**
     * يحفظ **علمي البيع وحدهما** — لتبويب التسعير في شاشة الكورس.
     *
     * والفرق عن `save_offer()` أن السعر والخصم كتبا للتو بمسار الحفظ
     * الموروث (`Crud_model::update_course()`)، فلا يكتبان هنا ثانية —
     * كتابة ثانية لرقم واحد في نداء واحد تعني أن أحدهما يفوز، وأيهما
     * يفوز يتغير مع أول تعديل في ترتيب النداء.
     *
     * ويقرأ السعر من الصف **بعد** أن كتب، فيفحص «لا بيع بلا سعر» على
     * الرقم الفعلي لا على رقم النموذج — والقاعدة نفسها في الموضعين.
     *
     * @param bool|null $sell null = لم يعرض الحقل، فلا يمس العلم
     * @return array ok · note (تحذير يقال ولا يمنع) · offer
     */
    public function save_flags($course_id, $sell, $percent)
    {
        $this->install_schema();
        $course_id = (int) $course_id;
        if ($course_id <= 0) return array('ok' => false, 'errors' => array('لا كورس.'));

        $cur = $this->course($course_id);
        if (!$cur) return array('ok' => false, 'errors' => array('لا كورس بهذا المعرف.'));

        $data = array();
        if ($sell !== null) $data['tq_sell'] = $sell ? 1 : 0;

        if ($percent !== null) {
            $percent = trim((string) $percent);
            /* الفارغ NULL لا صفر — «خذ العام» غير «صفر بقرار». */
            $data['tq_teacher_percent'] = ($percent === '') ? null : max(0, min(100, (float) $percent));
        }
        if (!$data) return array('ok' => true, 'offer' => $this->offer($cur));

        $this->db->where('id', $course_id)->update('course', $data);
        $this->db->data_cache = array();

        $offer = $this->offer($course_id);
        $this->audit('course.sale.flags', 'course#' . $course_id, $cur, $this->course($course_id));

        /* **يقال ولا يمنع.** الحفظ هنا يحمل تبويبا كاملا، ورده كله لأن
           سعرا نسي يفقد ما كتب معه. فيحفظ العلم ويقال إنه لا أثر له
           بعد — وهو ما تريه `offer()['why']` بالحرف. وصمت هنا يعني
           مسؤولا يظن أنه عرض كورسه للبيع وهو غير معروض. */
        $note = '';
        if ($sell && !$offer['sellable']) $note = $offer['why'];

        return array('ok' => true, 'note' => $note, 'offer' => $offer);
    }

    private function audit($action, $entity, $before, $after)
    {
        try {
            $CI  = function_exists('get_instance') ? get_instance() : null;
            $uid = ($CI && isset($CI->session) && is_object($CI->session))
                 ? (int) $CI->session->userdata('user_id') : 0;
            $this->db->insert('audit_log', array(
                'actor_id' => $uid,
                'action'   => $action,
                'entity'   => $entity,
                'before'   => $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
                'after'    => $after  ? json_encode($after,  JSON_UNESCAPED_UNICODE) : null,
                'ip'       => ($CI && $CI->input->is_cli_request()) ? 'cli' : $this->input->ip_address(),
                'at'       => date('Y-m-d H:i:s'),
            ));
        } catch (Throwable $e) { /* سجل يفشل لا يبطل حفظا */ }
    }
}
