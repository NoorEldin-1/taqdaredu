<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * محرك الاشتراكات: الباقات ودورة حياة الاشتراك والفواتير والاستحقاق.
 *
 * المبدأ الحاكم: **الاشتراك سجل لما حدث، لا انعكاس لما هو معروض اليوم.**
 * السعر ينسخ وقت الشراء، والنطاق ينسخ وقت التفعيل — فتغيير الباقة غدا
 * لا يرفع ما دفعه طالب أمس ولا يقلص ما اشتراه. الجداول القديمة لا تمس.
 *
 * والنقود هللات صحيحة في كل هذا الملف. لا `float` ولا `round()` إلا عند
 * التحويل الواحد من مدخل المستخدم بالريال.
 */
class Taqdar_billing_model extends CI_Model
{
    /** الضريبة كنسبة مئوية من الإعدادات — صفر ما لم تضبط. */
    private function tax_percent()
    {
        return (float) (get_settings('taqdar_vat_percent') ?: 0);
    }

    /* =====================================================================
       الباقات
       ===================================================================== */

    public function plans($only_active = true)
    {
        if ($only_active) $this->db->where('active', 1);
        return $this->db->order_by('order', 'ASC')->order_by('price', 'ASC')
                        ->get('plans')->result_array();
    }

    public function plan($id)
    {
        return $this->db->where('id', (int) $id)->get('plans')->row_array();
    }

    public function plan_by_code($code)
    {
        return $this->db->where('code', $code)->get('plans')->row_array();
    }

    /* =====================================================================
       دورات الشراء — TQ-CYCLE-BUY
       ===================================================================== */

    /**
     * الدورات التي **تشترى** بها هذه الباقة — لا التي تعرض بها.
     *
     * ═══ لماذا هنا لا في صف ثان ═══
     *
     * كان الشهري رقما يعرض ولا يباع: `subscribe()` تقرأ `plans.price`
     * فيدفع من ضغط «شهري» السعر السنوي. والمخرج الظاهر ان يكتب صف باقة
     * شهري بجوار السنوي — وهو ما يفسده شرط المالك ان **السعر يشتق**:
     * صفان يحملان رقمين لحقيقة واحدة يفترقان اول ما يعدل السنوي، ثم لا
     * شيء يقول ايهما الصحيح. فالدورة **معامل في الشراء** والصف واحد.
     *
     * ═══ ثلاثة قرارات ═══
     *
     * ١ — **الشهري للسنوية وحدها.** الخصم عشرون بالمئة قرار تسعير معلن
     *     على الدفع السنوي، واسقاطه على الربع سنوية يخترع توفيرا لا
     *     وجود له (وهو ما ترفضه `tqs_plan_price()` منذ كتبت). والباقة
     *     الشهرية شهرية اصلا فلا بديل لها.
     *
     * ٢ — **الاشتقاق من `tqs_plan_cycle()` وحدها.** هي الموضع الواحد
     *     لنسبة الخصم منذ كتبت، ومساعد `taqdar_site` محمل تلقائيا
     *     (`autoload.php`) فينادى من النموذج كما ينادى من القالب.
     *     ورقم يحسب هنا مرة وهناك مرة يعني ان الشاشة تعد بـ42 والفاتورة
     *     تطلب 43 — والفارق ريال لا يراه احد حتى يشتكي مشتر.
     *
     * ٣ — **`days` تخزن ولا تشتق وقت التفعيل.** التحويل البنكي يفعل بعد
     *     ايام، و`duration_days` قد تكون تغيرت. والمشتري وافق على «30
     *     يوما بـ42»، فالرقمان يجمدان معا — وهو مبدأ `subscription_items`
     *     نفسه.
     *
     * @return array مفتاحها اسم الدورة: key · price (هللات) · days ·
     *               label · unit · default
     */
    public function plan_cycles($plan)
    {
        $period = (string) (isset($plan['period']) ? $plan['period'] : '');
        $price  = max(0, (int) (isset($plan['price']) ? $plan['price'] : 0));
        $days   = max(1, (int) (isset($plan['duration_days']) ? $plan['duration_days'] : 365));

        /* المجانية: لا دورة تشترى، وصف واحد يقول ذلك بدل مصفوفة فارغة
           يفحصها كل مستدع بنفسه. */
        if ($period === 'free' || $price <= 0) {
            /* TQ-I18N — التسمية والوحدة تعرضان في البطاقة والمبدل، ولا
               تخزنان. والمفتاح (`free`/`annual`) يشترى به فيمر كما هو. */
            return array('free' => array(
                'key' => 'free', 'price' => 0, 'days' => $days,
                'label' => t('مجانا'), 'unit' => '', 'default' => true,
            ));
        }

        $own = array(
            'annual'    => array('label' => t('سنوي'),      'unit' => t('سنويا')),
            'quarterly' => array('label' => t('ربع سنوي'),  'unit' => t('كل ثلاثة أشهر')),
            'monthly'   => array('label' => t('شهري'),      'unit' => t('شهريا')),
        );
        /* صف قديم بلا `period`: يقرأ من مدته لا يرد بخطأ — والقاعدة فيها
           صفوف كتبت قبل ان يوجد العمود. */
        if (!isset($own[$period])) {
            $period = ($days >= 300) ? 'annual' : (($days >= 80) ? 'quarterly' : 'monthly');
        }

        $out = array($period => array(
            'key'   => $period, 'price' => $price, 'days' => $days,
            'label' => $own[$period]['label'], 'unit' => $own[$period]['unit'],
            'default' => true,
        ));

        if ($period === 'annual') {
            $c = tqs_plan_cycle($price);              // الاشتقاق الواحد
            $m = ((int) $c['month']) * 100;
            if ($m > 0) {
                $out['monthly'] = array(
                    'key' => 'monthly', 'price' => $m, 'days' => 30,
                    'label' => t('شهري'), 'unit' => t('شهريا'), 'default' => false,
                );
            }
        }

        return $out;
    }

    /**
     * دورة واحدة بمفتاحها — **والمجهول يرد الى دورة الباقة لا الى الارخص.**
     *
     * `cycle` يصل من `$_POST` ومن `?cycle=` في الرابط، وكلاهما يكتبه من
     * يشاء. فمفتاح لا تعرفه الباقة (شهري على باقة ربع سنوية، او كلمة
     * مخترعة) يقع على الافتراضي — وهو دورتها هي واعلى سعرا. والعكس —
     * السقوط الى ارخص ما وجد — يجعل تعديل حرف في الرابط يشتري باقة
     * السنة بسعر الشهر.
     */
    public function cycle_of($plan, $key = null)
    {
        $cy = $this->plan_cycles($plan);
        $k  = trim((string) $key);

        /* مرادفات العرض: مبدل الصفحة يكتب `month`/`year` في وسمه منذ
           كتب، فيفهمان هنا بدل ان يعاد تسمية كل `data-cycle` في اربعة
           اكسية وسكربت. و`year` تعني «دورة الباقة» لا السنوية حرفا:
           على باقة ربع سنوية هي الربع سنوية. */
        if ($k === 'month') $k = 'monthly';
        if ($k === 'year')  $k = '';

        if ($k !== '' && isset($cy[$k])) return $cy[$k];
        foreach ($cy as $c) if (!empty($c['default'])) return $c;
        return reset($cy);
    }

    /**
     * عمودا الدورة على `subscriptions` — ينشآن وقت التشغيل كاخوتهما.
     *
     * لا هجرات في هذا المستودع، والقراءة قبل الانشاء ترد «عمود مجهول».
     * فينادى من مسار الكتابة (`subscribe()`) لا من مسار العرض.
     */
    public function install_cycle_schema()
    {
        static $done = false;
        if ($done) return true;
        $done = true;
        try {
            $this->db->query("ALTER TABLE `subscriptions`
                ADD COLUMN IF NOT EXISTS `cycle` VARCHAR(12) NOT NULL DEFAULT '' AFTER `price`,
                ADD COLUMN IF NOT EXISTS `days`  INT(10) NOT NULL DEFAULT 0 AFTER `cycle`");
            return true;
        } catch (Throwable $e) {
            log_message('error', 'TQ-CYCLE-BUY: تعذر انشاء عمودي الدورة — ' . $e->getMessage());
            return false;
        }
    }

    /* =====================================================================
       الاشتراك النشط
       ===================================================================== */

    /**
     * الاشتراك الساري للمستخدم، أو null.
     *
     * ينتهي بالتاريخ لا بالحالة وحدها: قد يمر الوقت قبل أن يمر الكرون،
     * فمن يقرأ الآن يجب ألا يرى منتهيا على أنه نشط.
     *
     * والملغى الذي لم يبلغ أجله سار: `cancel()` يوقف التجديد ولا يصادر
     * المدفوع، و`expire_due()` يعده جاريا حتى `ends_at` — فلو استثني هنا
     * لانقطع الوصول لحظة الإلغاء، وهو نقض لما تعد به صفحتا الاشتراك
     * والباقات نصا («يبقى صالحا حتى تاريخ انتهائه — ولا يصادر ما دفع عنه»).
     * ومنع شراء اشتراك فوق اشتراك يبقى على «النشط» وحده — انظر `subscribe()`.
     */
    public function active_subscription($user_id)
    {
        foreach ($this->active_subscriptions($user_id) as $row) {
            /* TQ-COURSE-SALE — شراء كورس مفرد **ليس «اشتراكك»**.
               أحد عشر مستدعيا يسألون هذه الدالة سؤالا واحدا: «ما باقة
               هذا الطالب؟» — يطبعون اسمها، ويقيسون أجلها، ويمنعون شراء
               ثان فوقها. ومن اشترى مادة بمئة وتسعين يصير صفه هو الجواب
               (والترتيب على `ends_at` يقدمه متى كان وصوله دائما)، فتقرأ
               شاشته «باقتك: —» ويرد عليه شراء الباقة بـ«لديك اشتراك
               نشط بالفعل». والاستحقاق لا يمس: `subscription_grants()`
               تقرأ **كل** الصفوف السارية أدناه. */
            if ((int) (isset($row['course_id']) ? $row['course_id'] : 0) > 0) continue;
            /* TQ-BOOK — وشراء كتاب مفرد مثله وللعلة نفسها: من اشترى
               كتابا بخمسة وعشرين لا تصير «باقته» كتابا، ولا يرد عليه
               شراء الباقة بـ«لديك اشتراك نشط بالفعل». */
            if ((int) (isset($row['book_id']) ? $row['book_id'] : 0) > 0) continue;
            return $row;
        }
        return null;
    }

    /**
     * TQ-COURSE-SALE — **كل** ما يسري للمستخدم الآن، لا أوله.
     *
     * الاستحقاق كان يقرأ صفا واحدا (`active_subscription()`)، وكان ذلك
     * كافيا ما دام الطالب لا يملك إلا اشتراكا واحدا. وبيع الكورس المفرد
     * يكسر ذلك: من له باقة صف واشترى فوقها مادة إثرائية يملك صفين، وصف
     * واحد يقرأ يعني أن **أحد الشراءين لا يفتح شيئا** — لا خطأ ولا
     * رسالة، بابا مقفلا على من دفع ثمنه.
     *
     * والترتيب `ends_at DESC` يبقى: من يقرأ صفا واحدا يقرأ أبعدها أجلا.
     */
    public function active_subscriptions($user_id)
    {
        $rows = $this->db->where('user_id', (int) $user_id)
                         ->where_in('status', array('active', 'cancelled'))
                         ->order_by('ends_at', 'DESC')
                         ->get('subscriptions')->result_array();

        $out = array();
        foreach ($rows as $row) {
            // مضى أجله وإن لم يمر عليه الكرون بعد
            if (!empty($row['ends_at']) && strtotime($row['ends_at']) < time()) continue;
            // ألغي قبل أن يفعل أصلا: لا مدة له تكمل
            if ($row['status'] === 'cancelled' && empty($row['ends_at']))         continue;
            $out[] = $row;
        }
        return $out;
    }

    public function subscriptions_of($user_id)
    {
        return $this->db->where('user_id', (int) $user_id)
                        ->order_by('id', 'DESC')->get('subscriptions')->result_array();
    }

    public function subscription($id)
    {
        return $this->db->where('id', (int) $id)->get('subscriptions')->row_array();
    }

    public function items_of($subscription_id)
    {
        return $this->db->where('subscription_id', (int) $subscription_id)
                        ->get('subscription_items')->result_array();
    }

    /* =====================================================================
       TQ-SOLD-NAME — ما بيع في هذا الصف: نوعه واسمه
       ===================================================================== */

    /**
     * اسم ما بيع ونوعه، من الصف نفسه.
     *
     * ═══ ولماذا دالة واحدة لا سطر في كل موضع ═══
     *
     * سؤال «ماذا اشترى هذا الصف؟» يسأل في **ستة** مواضع لا واحد: قائمة
     * الاشتراكات في اللوحة، وإشعار إصدار الفاتورة، وإشعار نجاح الدفع،
     * وإشعار التفعيل اليدوي، ومدفوعات ولي الأمر، ومشتريات التطبيق. وكان
     * كل واحد منها يكتب جوابه بيده: `if (plan) … elseif (path) … elseif
     * (course)`.
     *
     * وست نسخ من قاعدة واحدة تعني أن **وحدة البيع الرابعة تضاف في بعضها
     * وتنسى في بقيتها** — وهو ما وقع حرفا: بيع الكتاب كتب كاملا (شراء
     * وفاتورة وتفعيل واستحقاق وقيد في دفتر معلم)، ولم يبلغ واحدا من
     * الستة. فمن اشترى كتابا بمئتي ريال يقرأ في اللوحة «—» في عمود «ما
     * اشترى»، ويصله إشعار يقول «قيمة الاشتراك في الباقة»، ويرى في
     * التطبيق «باقة #0». والمال صحيح والوصول صحيح، والذي يكذب هو الاسم
     * وحده — فلا شيء يخطئ، ولا أحد يعرف ما باع.
     *
     * فالجواب هنا مرة، ووحدة البيع الخامسة يوما تضاف في هذا `switch`
     * وحده فتصل الستة معا.
     *
     * ═══ والترتيب مقصود ═══
     *
     * المفرد قبل الباقة: صف الشراء المفرد يحمل `plan_id = 0`، وقراءة
     * الباقة أولا ترد «باقة #0» على كل كتاب وكورس بيع.
     *
     * ═══ وما حذف يقال بالرقم ═══
     *
     * TQ-PLAN-DELETE — صف يشير إلى معرف لا يقابله شيء يقال «باقة #8»
     * بالرقم لا «شراء»: بالرقم يقابل السجل المالي، والكلمة العامة لا
     * تقابل شيئا.
     *
     * @param  array|int $sub صف `subscriptions` أو معرفه
     * @return array kind · id · title · code · label (اسم النوع للعرض)
     *               · noun (نكرة: «قيمة كتاب») · noun_def (معرفة: «فتح الكتاب
     *                 الذي اشتريته» — والنكرة هناك تقرأ «فتح كتاب الذي»)
     */
    public function sold($sub)
    {
        if (!is_array($sub)) $sub = $this->subscription((int) $sub);

        $none = array('kind' => 'plan', 'id' => 0, 'title' => t('اشتراك'),
                      'code' => null,  'label' => t('باقة'), 'noun' => t('باقة'), 'noun_def' => t('الباقة'));
        if (!$sub) return $none;

        $of = function ($table, $col, $id) {
            $id = (int) $id;
            if ($id <= 0) return '';
            try {
                $row = $this->db->select($col)->where('id', $id)
                                ->get($table)->row_array();
            } catch (Throwable $e) {
                /* TQ-BUILDER-DIRTY — جدول أو عمود لم ينشأ بعد يترك بناء
                   الاستعلام موصولا خلفه، فيرث كل استعلام تال ضمومه. */
                $this->db->reset_query();
                return '';
            }
            return $row ? trim((string) $row[$col]) : '';
        };

        $book = (int) (isset($sub['book_id'])   ? $sub['book_id']   : 0);
        $crs  = (int) (isset($sub['course_id']) ? $sub['course_id'] : 0);
        $path = (int) (isset($sub['path_id'])   ? $sub['path_id']   : 0);
        $plan = (int) (isset($sub['plan_id'])   ? $sub['plan_id']   : 0);

        if ($book > 0) {
            $t = $of('books', 'title', $book);
            return array('kind' => 'book', 'id' => $book, 'code' => null,
                         'label' => t('كتاب'), 'noun' => t('كتاب'), 'noun_def' => t('الكتاب'),
                         'title' => $t !== '' ? $t : t('كتاب') . ' #' . $book);
        }
        if ($crs > 0) {
            $t = $of('course', 'title', $crs);
            return array('kind' => 'course', 'id' => $crs, 'code' => null,
                         'label' => t('كورس مفرد'), 'noun' => t('كورس'), 'noun_def' => t('الكورس'),
                         'title' => $t !== '' ? $t : t('كورس') . ' #' . $crs);
        }
        if ($path > 0) {
            $t = $of('paths', 'title', $path);
            return array('kind' => 'path', 'id' => $path, 'code' => null,
                         'label' => t('مسار'), 'noun' => t('مسار'), 'noun_def' => t('المسار'),
                         'title' => $t !== '' ? $t : t('مسار') . ' #' . $path);
        }
        if ($plan > 0) {
            $row = $this->plan($plan);
            return array('kind' => 'plan', 'id' => $plan,
                         'code'  => $row ? (string) $row['code'] : null,
                         'label' => t('باقة'), 'noun' => t('باقة'), 'noun_def' => t('الباقة'),
                         'title' => $row ? (string) $row['name_ar']
                                         : t('باقة') . ' #' . $plan);
        }
        return $none;
    }

    /* =====================================================================
       الاستحقاق
       ===================================================================== */

    /**
     * هل يمنح اشتراك المستخدم الساري الوصول إلى هذا المقرر؟
     *
     * المقرر يبلغ من المسار: `paths.course_id`. فبند النطاق `all` يمنح كل
     * شيء، و`path` يمنح مقرر مسار بعينه، و`subject` يمنح مقررات كل مسارات
     * تلك المادة، و`course` يمنح مقررا مباشرة.
     *
     * و`trial` نطاق من رتبة أخرى: وحدته الدرس لا المقرر. ولا يعبر عنه
     * بمنح مقرر — فمنح المقرر يفتح دروسه كلها، وهو عين ما جاء `trial`
     * ليمنعه. فالسؤال بلا درس بعينه جوابه **لا**، والسؤال عن درس معلم
     * `is_free = 1` في هذا المقرر جوابه نعم. ولهذا وسع التوقيع بمعامل
     * ثالث اختياري: المستدعي الذي يعرف الدرس يذكره، ومن لا يعرفه لا يمنح.
     *
     * @param int      $user_id
     * @param int      $course_id
     * @param int|null $lesson_id درس بعينه — يلزم وحده لنطاق `trial`
     */
    public function subscription_grants($user_id, $course_id, $lesson_id = null)
    {
        /* TQ-COURSE-SALE — **كل** اشتراك ساري لا أوله.
           كان السطر `active_subscription()` وحده، وهو صواب ما دام
           للطالب صف واحد. ومع بيع الكورس المفرد صار يملك اثنين: باقة
           صفه، ومادة اشتراها فوقها. فأيهما قرئ، سقط الآخر — والوصول
           الذي دفع ثمنه يرد بـ«هذا الدرس يفتح بالاشتراك» وهو مشترك.
           والبنود تجمع من الصفوف كلها: أول بند يمنح يكفي. */
        $subs = $this->active_subscriptions($user_id);
        if (!$subs) return false;

        $course_id = (int) $course_id;
        $items     = array();
        foreach ($subs as $s) {
            foreach ($this->items_of($s['id']) as $it) $items[] = $it;
        }
        if (!$items) return false;

        foreach ($items as $it) {
            switch ($it['entity_type']) {

                case 'all':
                    return true;

                case 'course':
                    if ((int) $it['entity_id'] === $course_id) return true;
                    break;

                case 'path':
                    $p = $this->db->select('course_id')->where('id', (int) $it['entity_id'])
                                  ->get('paths')->row_array();
                    if ($p && (int) $p['course_id'] === $course_id) return true;
                    break;

                case 'subject':
                    $n = $this->db->where('subject_id', (int) $it['entity_id'])
                                  ->where('course_id', $course_id)
                                  ->count_all_results('paths');
                    if ($n > 0) return true;
                    break;

                /* TQ-GRADE-GRANT — برنامج ينتمي إلى صف في الباقة يفتح.
                   والجسر `paths` لأنه الوحيد الذي يربط الصف بالدورة. */
                case 'grade':
                    $n = $this->db->where('grade_id', (int) $it['entity_id'])
                                  ->where('course_id', $course_id)
                                  ->count_all_results('paths');
                    if ($n > 0) return true;
                    break;

                case 'trial':
                    // لا منح على مستوى المقرر البتة
                    if ($lesson_id === null || (int) $lesson_id <= 0) break;
                    $n = $this->db->where('id', (int) $lesson_id)
                                  ->where('course_id', $course_id)
                                  ->where('is_free', 1)
                                  ->count_all_results('lesson');
                    if ($n > 0) return true;
                    break;
            }
        }
        return false;
    }

    /**
     * هل يمنح الاشتراك هذا الدرس بعينه؟
     *
     * وجه `subscription_grants` الذي يعرف الدرس: يستخرج مقرره من الجدول
     * ثم يسأل السؤال كاملا. وهو المدخل الذي ينبغي أن ينادى من طبقة الدرس
     * حتى يقرأ نطاق `trial` كما وضع.
     */
    public function subscription_grants_lesson($user_id, $lesson_id)
    {
        $l = $this->db->select('id, course_id')->where('id', (int) $lesson_id)
                      ->get('lesson')->row_array();
        if (!$l) return false;

        return $this->subscription_grants($user_id, (int) $l['course_id'], (int) $l['id']);
    }


    /* =====================================================================
       البيع بالمسار — وحدة البيع الأساسية
       ===================================================================== */

    /** المسار مع سعره ونسبة معلمه. */
    public function path($path_id)
    {
        return $this->db->where("id", (int) $path_id)->get("paths")->row_array();
    }

    /** النسبة الفعلية للمسار: ما ضبط له، وإلا الافتراض العام. */
    public function path_share_percent($path)
    {
        if (isset($path["teacher_share_percent"]) && $path["teacher_share_percent"] !== null
            && $path["teacher_share_percent"] !== "") {
            return max(0, min(100, (float) $path["teacher_share_percent"]));
        }
        return max(0, min(100, (float) (get_settings("taqdar_teacher_share_default") ?: 15)));
    }

    /**
     * يشتري الطالب مسارا: اشتراك معلق وفاتورته. لا يمنح شيئا قبل التفعيل.
     *
     * المسار غير المسعر يرفض كما ترفض الباقة غير المسعرة — فمنصة لم
     * تضبط أسعارها لا يجوز أن تفتح محتواها بضغطة.
     */
    public function subscribe_path($user_id, $path_id, $method = "manual")
    {
        $user_id = (int) $user_id;
        $path    = $this->path($path_id);

        if (!$user_id)  return array("ok" => false, "errors" => array("لا مستخدم."));
        if (!$path)     return array("ok" => false, "errors" => array("المسار غير موجود."));
        if ($path["status"] !== "published") {
            return array("ok" => false, "errors" => array("هذا المسار غير منشور."));
        }
        if ((int) $path["course_id"] <= 0) {
            return array("ok" => false, "errors" => array(
                "هذا المسار غير مرتبط بمحتوى بعد، فلا يشترى."
            ), "code" => "PATH_NO_COURSE");
        }
        if ((int) $path["price"] <= 0) {
            return array("ok" => false, "errors" => array("هذا المسار لم يسعر بعد، فلا يمكن شراؤه."),
                         "code" => "PATH_NOT_PRICED");
        }
        if ($this->has_path($user_id, (int) $path["id"])) {
            return array("ok" => false, "errors" => array("هذا المسار مفتوح لك بالفعل."));
        }

        /* TQ-PLACEMENT — شراء المسار المفرد يمر بالحارس نفسه.
           بابان للشراء وحارس على أحدهما ليس حارسا: من رد عن الباقة يشتري
           مسارا بالثمن نفسه ويفتح المحتوى نفسه. */
        $this->load->model('taqdar_diag_model');
        if ($this->taqdar_diag_model->gate($user_id)) {
            return array("ok" => false, "code" => "PLACEMENT_REQUIRED", "errors" => array(
                "قبل الشراء: اختبار قصير يحدد موضعك فنرشح لك ما يناسبك. لا رسوب فيه."
            ));
        }

        $this->db->insert("subscriptions", array(
            "user_id"    => $user_id,
            "plan_id"    => 0,
            "path_id"    => (int) $path["id"],
            "status"     => "pending",
            "price"      => (int) $path["price"],
            "auto_renew" => 0,
            "method"     => $method,
            "created_at" => date("Y-m-d H:i:s"),
        ));
        $sid = (int) $this->db->insert_id();
        $inv = $this->issue_invoice($sid, $user_id, (int) $path["price"], $method);
        $this->notify_invoice_issued($inv, $method);

        return array("ok" => true, "subscription_id" => $sid, "invoice_id" => $inv, "free" => false);
    }

    /** هل للمستخدم اشتراك نشط في هذا المسار بعينه؟ */
    public function has_path($user_id, $path_id)
    {
        $rows = $this->db->where("user_id", (int) $user_id)
                         ->where("path_id", (int) $path_id)
                         ->where_in("status", array("active", "cancelled"))
                         ->get("subscriptions")->result_array();
        foreach ($rows as $r) {
            if (empty($r["ends_at"]) || strtotime($r["ends_at"]) >= time()) return true;
        }
        return false;
    }

    /* =====================================================================
       البيع بالكورس المفرد — TQ-COURSE-SALE
       ===================================================================== */

    /**
     * يشتري الطالب كورسا بعينه: اشتراك معلق وفاتورته. لا يمنح قبل التفعيل.
     *
     * وهي أخت `subscribe_path()` حرفا بحرف في بنيتها، وللسبب نفسه: صف
     * `subscriptions` ببنده وفاتورته، فتسويه تاب بالفرع نفسه ويفعله
     * المسؤول بالزر نفسه ويجسده الكرون بالمرور نفسه.
     *
     * وثلاثة فروق عن الباقة، ولكل واحد سببه:
     *
     * ١ — **لا يمنع باقة قائمة ولا يمنعه اشتراك.** الباقة تمنع الباقة
     *     لأنهما شيء واحد يشترى مرتين؛ والكورس المفرد شيء آخر. ومن له
     *     باقة صفه واشترى مادة إثرائية فوقها اشترى شيئين لا شيئا مكررا.
     *     ومنع ذلك يعني رفض مال يريد صاحبه أن يدفعه.
     *
     * ٢ — **يمنع تكرار نفسه.** الكورس نفسه لا يشترى مرتين، ولا يشترى
     *     كورس تفتحه باقة سارية: من دفع مرة يقرأ «هذا مفتوح لك» لا
     *     يدفع ثانية. والفحص على `subscription_grants()` نفسها التي
     *     تحرس المشغل، فلا تعد الشاشة بما يمنعه الحارس.
     *
     * ٣ — **لا حارس تشخيصي.** `TQ-PLACEMENT` يحرس الباقة لأنها تفتح
     *     منهج مرحلة، والاختبار يقول أي مرحلة تناسب. والمادة الواحدة
     *     يختارها صاحبها بعينها، فاعتراض اختبار في طريقها اعتراض بلا
     *     سؤال يجاب.
     *
     * @return array ok · subscription_id · invoice_id · errors · code
     */
    public function subscribe_course($user_id, $course_id, $method = 'manual')
    {
        $this->load->model('taqdar_course_sale_model', 'tq_cs');
        $this->tq_cs->install_schema();

        $user_id   = (int) $user_id;
        $course_id = (int) $course_id;

        if (!$user_id)   return array('ok' => false, 'errors' => array('لا مستخدم.'));
        if (!$course_id) return array('ok' => false, 'errors' => array('لا كورس.'));

        /* العرض من مصدره الواحد: السعر والأجل والنسبة كلها من `offer()`،
           فما تعد به الشاشة هو ما تقيده الفاتورة بالهللة. */
        $offer = $this->tq_cs->offer($course_id);
        if (!$offer['sellable']) {
            return array('ok' => false, 'code' => 'NOT_SELLABLE',
                         'errors' => array($offer['reason'] === 'free'
                             ? 'هذا الكورس مجاني — افتحه بلا دفع.'
                             : 'هذا الكورس لا يباع مفردا الآن.'));
        }

        if ($this->has_course($user_id, $course_id)) {
            return array('ok' => false, 'code' => 'ALREADY_OWNED',
                         'errors' => array('هذا الكورس مفتوح لك بالفعل.'));
        }

        /* TQ-SUB-REUSE بوجهه الثاني: معلق بفاتورة لم تدفع يعاد استعماله.
           من أكد ثم تردد ثم أكد مرة أخرى كان يخرج بصفين وفاتورتين،
           تسدد إحداهما وتبقى الأخرى «غير مدفوعة» في سجل مالي أبدا.
           والسعر يفحص مع الرقم: من عدل سعر الكورس بعد إصدارها لا يشترى
           بسعر أمس. */
        $pend = $this->db->where('user_id', $user_id)
                         ->where('course_id', $course_id)
                         ->where('status', 'pending')
                         ->where('price', (int) $offer['price'])
                         ->order_by('id', 'DESC')->limit(1)
                         ->get('subscriptions')->row_array();

        if ($pend) {
            $old = $this->invoice_of_subscription((int) $pend['id']);
            if ($old && $old['status'] === 'unpaid' && (int) $old['amount'] === (int) $offer['price']) {
                $this->db->where('id', (int) $pend['id'])
                         ->update('subscriptions', array('method' => $method));
                $this->db->where('id', (int) $old['id'])
                         ->update('invoices', array('method' => $method));

                return array('ok' => true, 'subscription_id' => (int) $pend['id'],
                             'invoice_id' => (int) $old['id'], 'reused' => true,
                             'offer' => $offer);
            }
        }

        $this->db->insert('subscriptions', array(
            'user_id'    => $user_id,
            'plan_id'    => 0,
            'path_id'    => 0,
            'course_id'  => $course_id,
            'status'     => 'pending',
            'price'      => (int) $offer['price'],   // السعر وقت الشراء
            'auto_renew' => 0,
            'method'     => $method,
            'created_at' => date('Y-m-d H:i:s'),
        ));
        $sid = (int) $this->db->insert_id();

        $inv = $this->issue_invoice($sid, $user_id, (int) $offer['price'], $method);
        $this->notify_invoice_issued($inv, $method);

        return array('ok' => true, 'subscription_id' => $sid,
                     'invoice_id' => $inv, 'free' => false, 'offer' => $offer);
    }

    /**
     * هل هذا الكورس مفتوح لهذا الطالب الآن — بأي سبب؟
     *
     * والسببان يقرآن معا عمدا: شراء مفرد سار، **أو** اشتراك يمنحه.
     * ولو قرئ الأول وحده لبيع لمن يملك: يفتح صفحة مادة في باقته
     * فيقرأ سعرا وزر شراء، ويدفع ثمن ما يشاهده اليوم.
     */
    public function has_course($user_id, $course_id)
    {
        $user_id   = (int) $user_id;
        $course_id = (int) $course_id;
        if ($user_id <= 0 || $course_id <= 0) return false;

        if ($this->subscription_grants($user_id, $course_id)) return true;

        try {
            $rows = $this->db->where('user_id', $user_id)
                             ->where('course_id', $course_id)
                             ->where_in('status', array('active', 'cancelled'))
                             ->get('subscriptions')->result_array();
        } catch (Throwable $e) { return false; }

        foreach ($rows as $r) {
            if (empty($r['ends_at']) || strtotime($r['ends_at']) >= time()) return true;
        }
        return false;
    }


    /* =====================================================================
       البيع بالكتاب المفرد — TQ-BOOK
       ===================================================================== */

    /**
     * يشتري الطالب كتابا بعينه: اشتراك معلق وفاتورته. لا يمنح قبل التفعيل.
     *
     * وهي أخت `subscribe_course()` حرفا بحرف في بنيتها، وللسبب نفسه: صف
     * `subscriptions` ببنده وفاتورته، فتسويه تاب بالفرع نفسه ويفعله
     * المسؤول بالزر نفسه وينتهي أجله بـ`expire_due()` نفسها.
     *
     * وثلاثة فروق عن الباقة، ولكل واحد سببه — وهي فروق الكورس نفسها:
     *
     * ١ — **لا يمنع باقة قائمة ولا تمنعه.** من له باقة صفه واشترى كتابا
     *     إثرائيا فوقها اشترى شيئين لا شيئا مكررا.
     *
     * ٢ — **يمنع تكرار نفسه.** والفحص على `has_book()` وهي تسأل ما
     *     يسأله الحارس نفسه — فلا يباع لمن يملك، ولا تعد الشاشة بما
     *     يمنعه الحارس.
     *
     * ٣ — **لا حارس تشخيصي.** TQ-PLACEMENT يحرس الباقة لأنها تفتح منهج
     *     مرحلة؛ والكتاب الواحد يختاره صاحبه بعينه.
     *
     * @return array ok · subscription_id · invoice_id · errors · code
     */
    public function subscribe_book($user_id, $book_id, $method = 'manual')
    {
        $this->load->model('taqdar_book_model', 'tq_bk');
        $this->tq_bk->install_schema();

        $user_id = (int) $user_id;
        $book_id = (int) $book_id;

        if (!$user_id) return array('ok' => false, 'errors' => array(t('لا مستخدم.')));
        if (!$book_id) return array('ok' => false, 'errors' => array(t('لا كتاب.')));

        /* العرض من مصدره الواحد: السعر والأجل والنسبة كلها من `offer()`،
           فما تعد به الشاشة هو ما تقيده الفاتورة بالهللة. */
        $offer = $this->tq_bk->offer($book_id);
        if (!$offer['sellable']) {
            return array('ok' => false, 'code' => 'NOT_SELLABLE',
                         'errors' => array($offer['free']
                             ? t('هذا الكتاب مجاني — حمله بلا دفع.')
                             : t('هذا الكتاب لا يباع مفردا الآن.')));
        }

        if ($this->has_book($user_id, $book_id)) {
            return array('ok' => false, 'code' => 'ALREADY_OWNED',
                         'errors' => array(t('هذا الكتاب مفتوح لك بالفعل.')));
        }

        /* TQ-SUB-REUSE بوجهه الثاني: معلق بفاتورة لم تدفع يعاد استعماله.
           من أكد ثم تردد ثم أكد مرة أخرى كان يخرج بصفين وفاتورتين،
           تسدد إحداهما وتبقى الأخرى «غير مدفوعة» في سجل مالي أبدا.
           والسعر يفحص مع الرقم: من عدل سعر الكتاب بعد إصدارها لا يشترى
           بسعر أمس. */
        $pend = $this->db->where('user_id', $user_id)
                         ->where('book_id', $book_id)
                         ->where('status', 'pending')
                         ->where('price', (int) $offer['price'])
                         ->order_by('id', 'DESC')->limit(1)
                         ->get('subscriptions')->row_array();

        if ($pend) {
            $old = $this->invoice_of_subscription((int) $pend['id']);
            if ($old && $old['status'] === 'unpaid' && (int) $old['amount'] === (int) $offer['price']) {
                $this->db->where('id', (int) $pend['id'])
                         ->update('subscriptions', array('method' => $method));
                $this->db->where('id', (int) $old['id'])
                         ->update('invoices', array('method' => $method));

                return array('ok' => true, 'subscription_id' => (int) $pend['id'],
                             'invoice_id' => (int) $old['id'], 'reused' => true,
                             'offer' => $offer);
            }
        }

        $this->db->insert('subscriptions', array(
            'user_id'    => $user_id,
            'plan_id'    => 0,
            'path_id'    => 0,
            'course_id'  => 0,
            'book_id'    => $book_id,
            'status'     => 'pending',
            'price'      => (int) $offer['price'],   // السعر وقت الشراء
            'auto_renew' => 0,
            'method'     => $method,
            'created_at' => date('Y-m-d H:i:s'),
        ));
        $sid = (int) $this->db->insert_id();

        $inv = $this->issue_invoice($sid, $user_id, (int) $offer['price'], $method);
        $this->notify_invoice_issued($inv, $method);

        return array('ok' => true, 'subscription_id' => $sid,
                     'invoice_id' => $inv, 'free' => false, 'offer' => $offer);
    }

    /**
     * هل هذا الكتاب مفتوح لهذا الطالب الآن — بأي سبب؟
     *
     * وثلاثة أسباب تقرأ معا عمدا:
     *   · **الكتاب مجاني** — لم يعلن للبيع، فهو مفتوح لكل أحد كما كان
     *     منذ كتبت المنصة. وبلا هذا الفرع يعرض على الطالب زر شراء لكتاب
     *     يحمله الزائر بلا تسجيل.
     *   · **شراء مفرد سار.**
     *   · **باقة تفتح صفه** (TQ-BOOK-GRADE).
     *
     * ولو قرئ الثاني وحده لبيع لمن يملك: يفتح كتابا في باقته فيقرأ
     * سعرا وزر شراء، ويدفع ثمن ما يقرؤه اليوم.
     */
    public function has_book($user_id, $book_id)
    {
        $user_id = (int) $user_id;
        $book_id = (int) $book_id;
        if ($book_id <= 0) return false;

        $this->load->model('taqdar_book_model', 'tq_bk');
        $offer = $this->tq_bk->offer($book_id);

        /* المجاني مفتوح ولو لم يسجل صاحبه — والسؤال هنا «أيفتح؟» لا
           «أدفع؟». */
        if ($offer['free'] && (string) $offer['reason'] !== 'unpublished') return true;

        if ($user_id <= 0) return false;

        try {
            $rows = $this->db->where('user_id', $user_id)
                             ->where('book_id', $book_id)
                             ->where_in('status', array('active', 'cancelled'))
                             ->get('subscriptions')->result_array();
        } catch (Throwable $e) { $this->db->reset_query(); $rows = array(); }

        foreach ($rows as $r) {
            if (empty($r['ends_at']) || strtotime($r['ends_at']) >= time()) return true;
        }

        return in_array($book_id, $this->granted_book_ids($user_id), true);
    }

    /**
     * كل كتاب يفتحه اشتراك سار لهذا الطالب — TQ-BOOK-GRADE.
     *
     * ═══ ولماذا يستعلم حيا ولا يجسد ═══
     *
     * الكورس يجسد في `enrol` لأن جداول موروثة كثيرة تقرؤه (كورساتي ·
     * طلاب المعلم · التقارير · الشهادات)، والكتاب لا يقرؤه إلا مكتبة
     * الطالب. فالاستعلام الحي يكفي — **ويغلق TQ-ENROL-STALE من أصله**:
     * كتاب ينشر اليوم في صف باقة اشتريت في رمضان يفتح لصاحبها في
     * اللحظة، بلا كرون ولا زر «أعد التجسيد».
     *
     * والبنود مصدر الحقيقة كما هي للكورس: `book` بند شراء مفرد،
     * و`grade` بند باقة صفوف، و`all` نطاق شامل. و`subject`/`path`
     * لا يمنحان كتبا — الكتاب لا يربط بمادة ولا بمسار، وربطه بهما
     * تخمين لا حكم.
     *
     * @return array معرفات الكتب، بلا تكرار
     */
    public function granted_book_ids($user_id)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) return array();

        $this->load->model('taqdar_book_model', 'tq_bk');
        $this->tq_bk->install_schema();

        $subs = $this->active_subscriptions($user_id);
        if (!$subs) return array();

        $direct = array();
        $grades = array();
        $all    = false;

        foreach ($subs as $s) {
            foreach ($this->items_of($s['id']) as $it) {
                switch ($it['entity_type']) {
                    case 'book':  $direct[] = (int) $it['entity_id']; break;
                    case 'grade': $grades[] = (int) $it['entity_id']; break;
                    case 'all':   $all = true; break;
                }
            }
        }

        $ids = $direct;

        if ($all) {
            foreach ($this->tq_bk->all_published() as $b) $ids[] = (int) $b['id'];
        } elseif ($grades) {
            foreach ($this->tq_bk->books_for_grades($grades) as $b) $ids[] = (int) $b['id'];
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    /**
     * TQ-BOOK — تفعيل شراء كتاب مفرد.
     *
     * ثلاثة أشياء لا أكثر: أجل من `offer()`، وبند `book` واحد، وقيد في
     * دفتر صاحبه. وكلها بالمبادئ التي تحكم أخويه:
     *
     * · **الأجل ينسخ ولا يشتق كل مرة.** صفر يعني وصولا دائما، فـ
     *   `ends_at` تبقى `NULL` — و`expire_due()` تشترط `IS NOT NULL`
     *   فلا تلمسه.
     * · **البند ينظف قبل أن يكتب.** تفعيل ثان لا يضيف بندا فوق بند.
     * · **وفشل القيد لا يبطل التفعيل.** الطالب دفع واستحق كتابه، ودفتر
     *   المعلم يصالح لاحقا.
     * · **ولا تجسيد في `enrol`**: الكتاب ليس كورسا، ولا صف فيه يشير
     *   إليه. والمكتبة تقرأ حيا.
     */
    private function activate_book_subscription($sub, $method = null, $transaction_id = null)
    {
        $bid = (int) $sub['book_id'];

        $this->load->model('taqdar_book_model', 'tq_bk');
        $book = $this->tq_bk->book($bid);
        if (!$book) return false;

        $offer = $this->tq_bk->offer($book);
        $days  = (int) $offer['days'];
        $start = time();

        $data = array(
            'status'     => 'active',
            'started_at' => date('Y-m-d H:i:s', $start),
            /* الوصول الدائم `NULL` لا تاريخ بعيد: تاريخ مخترع ينتهي يوما
               ويقفل ما بيع على أنه دائم، ولا أحد يذكر لماذا. */
            'ends_at'    => $days > 0
                          ? date('Y-m-d H:i:s', strtotime('+' . $days . ' days', $start))
                          : null,
        );
        if ($method)         $data['method']         = $method;
        if ($transaction_id) $data['transaction_id'] = $transaction_id;

        $this->db->where('id', (int) $sub['id'])->update('subscriptions', $data);

        $this->db->where('subscription_id', (int) $sub['id'])->delete('subscription_items');
        $this->db->insert('subscription_items', array(
            'subscription_id' => (int) $sub['id'],
            'entity_type'     => 'book',
            'entity_id'       => $bid,
        ));

        /* نصيب المعلم — نسبة واحدة لا وعاء: الكتاب لصاحب واحد
           (`books.teacher_id`)، فلا أوزان ولا أكبر بواق. وكتاب المنصة
           بلا معلم لا يقيد له شيء، والسعر كله للمنصة. */
        if ((int) $sub['price'] > 0 && (int) $offer['teacher_id'] > 0) {
            try {
                $this->load->model('taqdar_wallet_model');
                $this->taqdar_wallet_model->credit_book_sale(
                    (int) $offer['teacher_id'], $bid, (int) $sub['id'],
                    (int) $sub['price'], $offer['percent'], (string) $book['title']
                );
            } catch (Exception $e) {
                log_message('error', 'TQ-BOOK: تعذر قيد بيع كتاب #' . $bid
                    . ' لاشتراك #' . (int) $sub['id'] . ' — ' . $e->getMessage());
            }
        }

        $this->audit('subscription_activate_book', 'subscriptions#' . (int) $sub['id'],
                     $sub, $this->subscription($sub['id']));
        return true;
    }

    /* =====================================================================
       الشراء والتفعيل
       ===================================================================== */

    /**
     * ينشئ اشتراكا معلقا وفاتورته. لا يمنح شيئا بعد — المنح في التفعيل.
     *
     * الباقة المجانية تفعل فورا: لا فاتورة تدفع فلا معنى لتعليقها.
     *
     * TQ-CYCLE-BUY — **والدورة معامل هنا، وهي التي تحدد المبلغ والمدة.**
     * `plan_cycles()` تقول ما يشترى وبكم، و`cycle_of()` تحرس المفتاح
     * الوارد. والمكتوب في الصف هو **المحصل** لا المعروض: `price` مبلغ
     * هذه الدورة، و`cycle` اسمها، و`days` مدتها — ثلاثتها تجمد وقت
     * الشراء فلا يغيرها تعديل على الباقة بعد ذلك.
     *
     * وما بعد هذه الدالة يتبعها بلا تعديل: `issue_invoice()` تأخذ المبلغ
     * معاملا، و`Taqdar_tap_model::start()` تقرأ `invoices.total`، و
     * `Taqdar_revenue_model::credit_plan_sale()` تقسم `subscriptions.price`
     * — فبيعة الشهر تقسم على الشهر لا على السنة.
     *
     * @param string|null $cycle مفتاح الدورة — وما لا تعرفه الباقة يرد
     *                           الى دورتها هي، لا الى الارخص.
     * @return array ok · subscription_id · invoice_id · errors · cycle
     */
    public function subscribe($user_id, $plan_id, $method = 'manual', $cycle = null)
    {
        $this->install_cycle_schema();
        $user_id = (int) $user_id;
        $plan    = $this->plan($plan_id);

        if (!$user_id)                return array('ok' => false, 'errors' => array('لا مستخدم.'));
        if (!$plan)                   return array('ok' => false, 'errors' => array('الباقة غير موجودة.'));
        if ((int) $plan['active'] !== 1) return array('ok' => false, 'errors' => array('الباقة غير متاحة.'));

        /* TQ-PLACEMENT — الاختبار التشخيصي قبل الشراء.
           **هذا هو المنع الفعلي.** الشاشات تخفي الزر وتحول قبل بلوغه، وهي
           تحسين في الطريق لا حاجز: من يرسل النموذج بيده يصل إلى هنا. وكل
           طرق الشراء — البطاقة والتحويل والباقة المجانية — تمر بهذه الدالة
           وحدها، فالحارس فيها يحرس الثلاثة.
           و`gate()` ترد `null` لمن لا صف له، أو لا اختبار منشور لصفه، أو
           أداه — فهذا الشرط لا يمس شيئا في منصة بلا اختبارات تشخيصية. */
        $this->load->model('taqdar_diag_model');
        if ($this->taqdar_diag_model->gate($user_id)) {
            return array('ok' => false, 'code' => 'PLACEMENT_REQUIRED', 'errors' => array(
                'قبل الاشتراك: اختبار قصير يحدد موضعك فنرشح لك الباقة المناسبة. لا رسوب فيه.'
            ));
        }

        // «مجانية» صفة الباقة لا نتيجة خلو سعرها.
        // لولا هذا التمييز لصارت كل باقة لم تسعر بعد بابا لفتح المحتوى
        // مجانا — وهو ما يحدث تحديدا في منصة لم تضبط أسعارها بعد.
        //
        /* TQ-CYCLE-BUY — والدورة تحل **قبل** كل فحص يمس المال أو الحال:
           فحص التجديد أدناه يقرأ `$free`، وما بعده يقرأ `$gross` مكان
           `plans.price`. وقراءة متغير قبل اسناده لا ترمي في PHP — تقرأ
           `null` فيمر الحارس صامتا، وهو أسوأ من خطأ يظهر. */
        $cy    = $this->cycle_of($plan, $cycle);
        $gross = (int) $cy['price'];
        $free  = ($plan['period'] === 'free');

        if (!$free && (int) $plan['price'] <= 0) {
            return array('ok' => false, 'errors' => array(
                'هذه الباقة لم تسعر بعد، فلا يمكن الاشتراك فيها.'
            ), 'code' => 'PLAN_NOT_PRICED');
        }

        // اشتراك نشط قائم: لا يشترى فوقه اشتراك ثان صامتا.
        // و«النشط» هنا بالحرف: من أوقف التجديد فقد أعلن انصرافه عن هذه
        // الباقة، فلا يحبس عن غيرها إلى أن يمضي أجل ما ألغاه.
        //
        // TQ-CYCLE-RENEW — **الا الباقة نفسها: فذاك تجديد لا شراء ثان.**
        // الشرط كان يمنع كل شراء فوق نشط، وهو محتمل على باقة سنوية —
        // يشترى مرة في العام. اما الشهري فيشترى اثنتي عشرة مرة، ومن يجدد
        // لا يستطيع الا بعد ان **ينقطع** وصوله: ينتظر انتهاء شهره، ثم
        // يتذكر ان يعود (ولا تجديد تلقائي ولا تذكير كان)، ويدرس في
        // الفجوة بلا محتوى. فمنتج شهري لا يجدد ليس منتجا شهريا.
        //
        // والتجديد يشترط الباقة نفسها: الترقية الى باقة اخرى فوق نشطة
        // مسألة اخرى (احتساب ما دفع)، وخلطها بالتجديد يجعل الشراء
        // الخاطئ يمر صامتا.
        $current = $this->active_subscription($user_id);
        $renew   = false;
        if ($current && $current['status'] === 'active') {
            $renew = ((int) $current['plan_id'] === (int) $plan['id'])
                  && ((int) $current['path_id'] === 0);
            if (!$renew) {
                return array('ok' => false, 'errors' => array('لديك اشتراك نشط بالفعل. ألغه أو انتظر انتهاءه.'));
            }
            if ($free) {
                return array('ok' => false, 'errors' => array('باقتك المجانية سارية بالفعل.'));
            }
        }

        $now = date('Y-m-d H:i:s');

        /* TQ-SUB-REUSE — اشتراك معلق لنفس الباقة وفاتورته لم تدفع: يعاد
           استعماله لا يصدر ثان.
           والموضع الذي يظهر فيه هذا: الطالب يؤكد، فتفتح صفحة البوابة،
           فيتردد ويرجع، فيؤكد مرة أخرى. بلا هذا الفرع يخرج من ذلك صفان
           في `subscriptions` وفاتورتان برقمين متسلسلين — ثم تسدد إحداهما
           وتبقى الأخرى «غير مدفوعة» في سجل مالي إلى الأبد. والسعر يفحص
           مع الرقم: من عدلت الباقة بعد إصداره لا يشترى بسعر أمس.

           TQ-CYCLE-BUY — **والدورة تفحص مع السعر.** من ترك معلقا شهريا
           ثم عاد فاختار السنوي يجب ان يصدر له صف جديد لا ان يعاد اليه
           صف الشهر بسعره: `price` وحده كان يميزهما، وهو يميزهما فعلا
           اليوم — لكنه يستوي متى تساوى الرقمان (باقة سنوية سعرها 12
           ضعف شهريها بالحرف)، فيشترط الاسم معه صراحة. */
        /* والمجانية مستثناة: لا فاتورة تدفع فيها، وإعادة استعمال صفها
           تعود بلا تفعيل — والتفعيل هو كل ما تفعله الباقة المجانية. */
        $pend = $free ? null : $this->db->where('user_id', $user_id)
                         ->where('plan_id', (int) $plan['id'])
                         ->where('path_id', 0)
                         ->where('status', 'pending')
                         ->where('price', $gross)
                         ->where('cycle', (string) $cy['key'])
                         ->order_by('id', 'DESC')->limit(1)
                         ->get('subscriptions')->row_array();

        if ($pend) {
            $old = $this->invoice_of_subscription((int) $pend['id']);
            if ($old && $old['status'] === 'unpaid' && (int) $old['amount'] === $gross) {
                $this->db->where('id', (int) $pend['id'])
                         ->update('subscriptions', array('method' => $method));
                $this->db->where('id', (int) $old['id'])
                         ->update('invoices', array('method' => $method));

                return array('ok' => true, 'subscription_id' => (int) $pend['id'],
                             'invoice_id' => (int) $old['id'], 'free' => false,
                             'reused' => true, 'cycle' => $cy);
            }
        }

        $this->db->insert('subscriptions', array(
            'user_id'    => $user_id,
            'plan_id'    => (int) $plan['id'],
            'status'     => 'pending',
            'price'      => $gross,                 // مبلغ هذه الدورة وقت الشراء
            'cycle'      => (string) $cy['key'],    // واسمها — بها يقرأ السجل
            'days'       => (int) $cy['days'],      // ومدتها، تجمد ولا تشتق بعد
            'auto_renew' => 0,
            'method'     => $method,
            'created_at' => $now,
        ));
        $sid = (int) $this->db->insert_id();

        $inv = $this->issue_invoice($sid, $user_id, $gross, $method);

        if ($free) {
            $this->activate($sid, $method, 'free');
            $this->mark_invoice_paid($inv, 'free');
        } else {
            $this->notify_invoice_issued($inv, $method);
        }

        return array('ok' => true, 'subscription_id' => $sid, 'invoice_id' => $inv,
                     'free' => $free, 'cycle' => $cy);
    }

    /**
     * TQ-INVOICE-TOLD — يخبر صاحب الفاتورة أنها صدرت وينتظر تحويلها.
     *
     * وهذا الطرف كان صامتا وحده من أطراف الدفع الأربعة: نجاح البطاقة
     * يخبر (`Taqdar_pay::notify_paid`)، والتفعيل اليدوي يخبر، وقرار
     * السحب يخبر — **وإصدار الفاتورة لا يخبر بشيء**. فمن اختار التحويل
     * البنكي يرى سطرا في الصفحة ثم يغلقها، ولا يبقى معه رقم الفاتورة
     * ولا المبلغ ولا الآيبان إلا أن يعود ويبحث.
     *
     * والبطاقة مستثناة عمدا: صاحبها ينتقل إلى صفحة الدفع في اللحظة
     * نفسها، ورسالة «صدرت فاتورتك» تصله وهو يدفعها.
     */
    public function notify_invoice_issued($invoice_id, $method = 'manual')
    {
        if ((string) $method !== 'manual') return false;

        try {
            /* TQ-SOLD-NAME — الاسم من `sold()` وحدها.
               كان هنا ضم ثلاثي (`plans` · `paths` · `course`) يقرأ ثلاثة
               أعمدة ويأخذ أولها غير الفارغ — وهو الضم نفسه المكتوب بيده
               في خمسة مواضع أخرى. فبيع الكتاب وصل الشراء والتفعيل
               والاستحقاق، ولم يبلغ واحدا منها: يصل مشتري الكتاب إشعار
               يقول «قيمة الاشتراك في «الباقة»» عن كتاب اشتراه بعينه.
               والحل موضع واحد يجيب لا ضم رابع يضاف هنا وينسى هناك.

               TQ-INVOICE-COL — **والعمود يفحص قبل أن يقرأ.**
               `subscriptions.course_id` و`book_id` ينشآن وقت التشغيل لا
               بهجرة، فقراءتهما على قاعدة لم يمر عليها بيع مفرد ترمي
               «Unknown column» يبتلعه `catch` أدناه — **فلا إشعار فاتورة
               يخرج لأحد قط**. و`sold()` تقرأ الصف نفسه (`SELECT *`) فلا
               تسمي عمودا قد لا يوجد، وتغيب المفاتيح بلا خطأ. */
            $inv = $this->db->select('i.invoice_no, i.total, i.user_id, i.subscription_id')
                            ->from('invoices i')
                            ->where('i.id', (int) $invoice_id)->get()->row_array();
            if (!$inv || empty($inv['user_id'])) return false;

            $sold = $this->sold((int) $inv['subscription_id']);
            $what = (string) $sold['title'];
            $iban = trim((string) get_settings('tq_bank_iban'));

            /* المبلغ **هللات** في العمود، والرسالة تقول «ر.س».
               `number_format(59900)` كان يطبع «59,900 ر.س» على فاتورة
               قيمتها ٥٩٩ — فيقرأ من ينتظر التحويل رقما أكبر مئة مرة،
               ويحول به أو يتصل. و`tqs_money()` هي القاسم الواحد ولكنها
               ترد وسما، والإشعار نص. */
            $sar = number_format(((int) $inv['total']) / 100, 0, '.', ',');

            $this->load->model('taqdar_admin_model');
            return (bool) $this->taqdar_admin_model->push_notification(
                (int) $inv['user_id'],
                'صدرت فاتورتك ' . $inv['invoice_no'],
                /* والنوع يقال مع الاسم: «قيمة الاشتراك في …» على كتاب
                   اشتراه صاحبه بعينه تجعله يظن أنه اشترك في باقة.
                   والجملة مفتاح واحد ببدائل `____` لا قطع تلصق: «قيمة»
                   وحدها لا تترجم — المترجم لا يعرف موقعها من الجملة،
                   ولغة أخرى قد ترتبها غير هذا الترتيب. */
                t('قيمة ____ «____» هي ____ ر.س.', array(
                    $sold['noun'], ($what !== '' ? $what : t('الباقة')), $sar))
                . ' ' . t('حول المبلغ')
                . ($iban !== '' ? ' ' . t('إلى الآيبان') . ' ' . $iban : '')
                . ' ' . t('واذكر رقم الفاتورة في التحويل، ويفعل اشتراكك بعد التحقق من الحوالة.'),
                'invoice'
            );
        } catch (Throwable $e) {
            /* إخطار يفشل لا يمنع فاتورة صدرت.

               TQ-BUILDER-DIRTY — **ويترك بناء الاستعلام نظيفا خلفه.**
               استثناء يقع **وسط** سلسلة `select()->from()->join()` يترك
               حالتها في `CI_DB_query_builder` كما هي: `_reset_select()`
               لا ينادى إلا بعد `get()` الناجحة. فكل استعلام تال في الطلب
               نفسه يرث `from('invoices i')` وضمومها الثلاثة — و
               `$this->db->where('id', $x)->get('subscriptions')` يرد
               «Column 'id' is ambiguous» في موضع لا علاقة له بالفاتورة.
               والذي يظهره: الطلب الذي يشتري ثم يفعل (الباقة المجانية،
               والتجديد) — يسقط تفعيله على خطأ منشؤه إشعار ابتلع. */
            $this->db->reset_query();
            log_message('error', 'TQ-BILLING: تعذر إخطار إصدار الفاتورة — ' . $e->getMessage());
            return false;
        }
    }

    /**
     * يفعل اشتراكا معلقا: يضبط المدة، وينسخ نطاق الباقة بنودا.
     *
     * النسخ هنا مقصود — البنود صورة النطاق وقت التفعيل. ولو قرئ النطاق من
     * الباقة عند كل فحص لتغير ما يملكه الطالب كلما حررت الباقة.
     */
    public function activate($subscription_id, $method = null, $transaction_id = null)
    {
        $sub = $this->subscription($subscription_id);
        if (!$sub) return false;
        if ($sub['status'] === 'active') return true;   // التفعيل مرتين لا يمدد المدة

        // اشتراك مسار: مدته وبنده من المسار لا من باقة، وحصة معلمه تقيد
        if ((int) $sub['path_id'] > 0) {
            return $this->activate_path_subscription($sub, $method, $transaction_id);
        }

        /* TQ-COURSE-SALE — واشتراك كورس مفرد: بنده `course` ونصيب معلمه
           نسبة واحدة. والفرع هنا **قبل** قراءة الباقة: `plan_id = 0`
           فترد `plan()` فارغا و`activate()` ترد `false` — أي أن الطالب
           يدفع، وتحصل تاب المال، ولا يفتح شيء، وتقرأ الشاشة «حصل المال
           ولم يفعل الاشتراك». */
        if ((int) (isset($sub['course_id']) ? $sub['course_id'] : 0) > 0) {
            return $this->activate_course_subscription($sub, $method, $transaction_id);
        }

        /* TQ-BOOK — واشتراك كتاب مفرد، بالعلة نفسها وفي الموضع نفسه. */
        if ((int) (isset($sub['book_id']) ? $sub['book_id'] : 0) > 0) {
            return $this->activate_book_subscription($sub, $method, $transaction_id);
        }

        $plan = $this->plan($sub['plan_id']);
        if (!$plan) return false;

        /* TQ-CYCLE-BUY — المدة من الصف لا من الباقة.
           المشتري وافق على «كذا يوما بكذا» وقت الشراء، والتحويل البنكي
           يفعل بعد ايام قد تكون `duration_days` تغيرت فيها. والصفر يعني
           صفا كتب قبل العمود، فيرتد الى الباقة كما كان. */
        $days  = (int) (isset($sub['days']) ? $sub['days'] : 0);
        if ($days < 1) $days = max(1, (int) $plan['duration_days']);

        /* TQ-CYCLE-RENEW — التجديد يبدأ من حيث ينتهي السابق لا من الان.
           لو بدأ من الان لضاع ما بقي من الشهر الجاري: من جدد قبل انتهائه
           بعشرة ايام دفع عن ثلاثين وأخذ عشرين. والصف الجاري يبقى كما هو
           ويطفئه `expire_due()` في وقته — فلا سجل يعدل بأثر رجعي. */
        $start = time();
        $prev  = $this->db->select('ends_at')
                          ->where('user_id', (int) $sub['user_id'])
                          ->where('plan_id', (int) $sub['plan_id'])
                          ->where('id !=', (int) $subscription_id)
                          ->where_in('status', array('active', 'cancelled'))
                          ->where('ends_at >', date('Y-m-d H:i:s'))
                          ->order_by('ends_at', 'DESC')->limit(1)
                          ->get('subscriptions')->row_array();
        if ($prev && !empty($prev['ends_at'])) {
            $start = max($start, (int) strtotime($prev['ends_at']));
        }
        $data  = array(
            'status'     => 'active',
            'started_at' => date('Y-m-d H:i:s', $start),
            'ends_at'    => date('Y-m-d H:i:s', strtotime('+' . $days . ' days', $start)),
        );
        if ($method)         $data['method']         = $method;
        if ($transaction_id) $data['transaction_id'] = $transaction_id;

        $this->db->where('id', (int) $subscription_id)->update('subscriptions', $data);

        // البنود تكتب مرة واحدة.
        // و`all` و`trial` نطاقان لا يشيران إلى كيان بعينه، فرقمهما صفر —
        // ورقم منقول من `scope_id` هنا يوهم بمرجع لا وجود له.
        $scope   = $plan['scope'];
        $unbound = in_array($scope, array('all', 'trial'), true);

        /* TQ-GRADE-SCOPE — الباقة تشمل عدة صفوف، و`scope_id` رقم واحد.
           فتوسع هنا إلى بند لكل صف. والتوسيع عند **التفعيل** لا عند
           الفحص: النطاق ينسخ بنودا وقت الشراء، فتعديل الباقة لاحقا
           لا يوسع ما دفع ولا يضيقه. */
        /* التنظيف **قبل** الكتابة ولكل نطاق: كان فرع `grade` يعود من
           هنا قبل أن يبلغ `DELETE`، فتفعيل ثان يضيف ستة بنود فوق
           ستة — والاستحقاق يصح ظاهرا بينما الجدول يتضخم بلا حد.
           وكان يعود قبل `audit()` أيضا، فلا أثر في السجل لأكثر
           التفعيلات وقوعا. والسجل الذي يسقط منه الشائع لا يدقق. */
        $this->db->where('subscription_id', (int) $subscription_id)->delete('subscription_items');

        if ($scope === 'grade') {
            $ids = array_filter(array_map('intval',
                       explode(',', (string) $plan['scope_ids'])));
            if (!$ids) $ids = array((int) $plan['scope_id']);
            foreach (array_unique($ids) as $gid) {
                if ($gid <= 0) continue;
                $this->db->insert('subscription_items', array(
                    'subscription_id' => (int) $subscription_id,
                    'entity_type'     => 'grade',
                    'entity_id'       => $gid,
                ));
            }
        } else {
            $this->db->insert('subscription_items', array(
                'subscription_id' => (int) $subscription_id,
                'entity_type'     => $scope,
                'entity_id'       => $unbound ? 0 : (int) $plan['scope_id'],
            ));
        }

        /* التجسيد بعد البنود مباشرة: البنود تجيب `is_entitled()`،
           وصفوف `enrol` تجيب الشاشات التي لا تسأل غيرها. */
        $this->sync_enrolments((int) $subscription_id);

        /* حصص معلمي الباقة — وعاء مغلق يقسم بالدرس.
           TQ-PLAN-NOSHARE: كانت الدالة تنتهي هنا، فالباقة تبيع محتوى
           سبعة معلمين ولا يقيد لأحدهم ريال. وبيع المسار المنفرد وحده
           كان يقيد (`activate_path_subscription`) — وهو الأقل وقوعا.
           والقسمة كلها في `Taqdar_revenue_model`: لا تحسب هنا لأنها
           تحتاج أوزان كل المعلمين لا وزن من نمر عليه.

           وفشل القسمة لا يبطل التفعيل، بالقاعدة نفسها التي في اشتراك
           المسار: الطالب دفع واستحق وصوله، ودفتر المعلم يصالح لاحقا —
           ومنع الطالب لأن دفترا لم يكتب يعاقب من لا ذنب له. */
        if ((int) $sub['price'] > 0) {
            try {
                $this->load->model('taqdar_revenue_model');
                $this->taqdar_revenue_model->credit_plan_sale(
                    (int) $subscription_id, $plan, (int) $sub['price']
                );
            } catch (Exception $e) {
                log_message('error', 'TQ-REVENUE: تعذرت قسمة اشتراك #'
                    . (int) $subscription_id . ' — ' . $e->getMessage());
            }
        }

        $this->audit('subscription_activate', 'subscriptions#' . (int) $subscription_id,
                     $sub, $this->subscription($subscription_id));
        return true;
    }

    public function cancel($subscription_id, $reason = '')
    {
        $sub = $this->subscription($subscription_id);
        if (!$sub || $sub['status'] === 'cancelled') return false;

        // الإلغاء يوقف التجديد ولا يصادر المدفوع: المدة تكمل حتى نهايتها
        $this->db->where('id', (int) $subscription_id)->update('subscriptions', array(
            'status'        => 'cancelled',
            'auto_renew'    => 0,
            'cancelled_at'  => date('Y-m-d H:i:s'),
            'cancel_reason' => mb_substr((string) $reason, 0, 255),
        ));

        $this->audit('subscription_cancel', 'subscriptions#' . (int) $subscription_id,
                     $sub, $this->subscription($subscription_id));
        return true;
    }

    /**
     * ينهي الاشتراكات التي مضى أجلها. ينادى من الكرون.
     * والملغى الذي لم ينته أجله يبقى صالحا حتى `ends_at` — دفع ثمنه.
     */
    public function expire_due()
    {
        $now  = date('Y-m-d H:i:s');
        $rows = $this->db->where_in('status', array('active', 'cancelled'))
                         ->where('ends_at <', $now)
                         ->where('ends_at IS NOT NULL', null, false)
                         ->get('subscriptions')->result_array();

        foreach ($rows as $r) {
            $this->db->where('id', (int) $r['id'])->update('subscriptions', array('status' => 'expired'));
        }
        if ($rows) {
            $this->audit('subscription_expire_batch', 'subscriptions', null, array('count' => count($rows)));
        }
        return count($rows);
    }

    /* =====================================================================
       الفواتير
       ===================================================================== */

    public function issue_invoice($subscription_id, $user_id, $amount, $method = null)
    {
        $amount = (int) $amount;
        $tax    = (int) round($amount * $this->tax_percent() / 100);

        $this->db->insert('invoices', array(
            'invoice_no'      => $this->next_invoice_no(),
            'subscription_id' => (int) $subscription_id,
            'user_id'         => (int) $user_id,
            'amount'          => $amount,
            'tax'             => $tax,
            'total'           => $amount + $tax,
            'status'          => 'unpaid',
            'method'          => $method,
            'issued_at'       => date('Y-m-d H:i:s'),
        ));
        return (int) $this->db->insert_id();
    }

    /**
     * رقم فاتورة متسلسل داخل السنة.
     * يشتق من أعلى رقم قائم لا من عد الصفوف: الحذف يجعل العد يكرر رقما،
     * ورقم فاتورة مكرر عطب محاسبي لا عطب عرض.
     */
    private function next_invoice_no()
    {
        $year   = date('Y');
        $prefix = 'TQ-' . $year . '-';

        $row = $this->db->select('invoice_no')->like('invoice_no', $prefix, 'after')
                        ->order_by('invoice_no', 'DESC')->limit(1)
                        ->get('invoices')->row_array();

        $seq = $row ? ((int) substr($row['invoice_no'], strlen($prefix))) + 1 : 1;
        return $prefix . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    public function mark_invoice_paid($invoice_id, $transaction_id = null)
    {
        $this->db->where('id', (int) $invoice_id)->update('invoices', array(
            'status'         => 'paid',
            'paid_at'        => date('Y-m-d H:i:s'),
            'transaction_id' => $transaction_id,
        ));
        return true;
    }

    public function invoices_of($user_id)
    {
        return $this->db->where('user_id', (int) $user_id)
                        ->order_by('id', 'DESC')->get('invoices')->result_array();
    }

    public function invoice_of_subscription($subscription_id)
    {
        return $this->db->where('subscription_id', (int) $subscription_id)
                        ->order_by('id', 'DESC')->limit(1)
                        ->get('invoices')->row_array();
    }

    /**
     * تفعيل يدوي — التحويل البنكي. تنادى من اللوحة بعد التحقق من الحوالة.
     *
     * ويبقى مسارا قائما لا بديلا مؤقتا: بوابة تاب تفعل الاشتراك بنفسها
     * (`activate_from_gateway()` أدناه)، والحوالة البنكية تبقى خيارا
     * معروضا لمن لا يدفع بالبطاقة — فهذه الدالة لا تستغني عنها البوابة.
     */
    public function activate_manually($subscription_id, $reference = '')
    {
        $ok = $this->activate($subscription_id, 'manual', $reference);
        if ($ok) {
            $inv = $this->invoice_of_subscription($subscription_id);
            if ($inv && $inv['status'] !== 'paid') {
                $this->mark_invoice_paid($inv['id'], $reference);
            }
        }
        return $ok;
    }

    /**
     * نقطة الوصل للبوابات — تنادى من `Taqdar_tap_model::settle()` وحدها.
     *
     * و`$invoice_id` يمرر صريحا حين يعرف: `invoice_of_subscription()` ترد
     * **آخر** فاتورة للاشتراك، وهي الصواب في الحالة الشائعة. لكن الدفعة
     * تخص فاتورة بعينها، ولو صدرت للاشتراك فاتورتان لسدد الأحدث بدفعة
     * الأقدم — أي أن ما دفع ثمنه يبقى «غير مدفوع» في السجل.
     */
    public function activate_from_gateway($subscription_id, $gateway, $transaction_id, $invoice_id = 0)
    {
        $ok = $this->activate($subscription_id, $gateway, $transaction_id);
        if ($ok) {
            $inv = (int) $invoice_id > 0
                 ? $this->db->where('id', (int) $invoice_id)
                            ->where('subscription_id', (int) $subscription_id)
                            ->get('invoices')->row_array()
                 : $this->invoice_of_subscription($subscription_id);
            if ($inv && $inv['status'] !== 'paid') $this->mark_invoice_paid($inv['id'], $transaction_id);
        }
        return $ok;
    }

    /* =====================================================================
       أدوات
       ===================================================================== */

    private function audit($action, $entity, $before, $after)
    {
        $this->db->insert('audit_log', array(
            'actor_id' => $this->tq_actor_id(),
            'action'   => $action,
            'entity'   => $entity,
            'before'   => $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
            'after'    => $after ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
            'ip'       => $this->input->is_cli_request() ? 'cli' : $this->input->ip_address(),
            'at'       => date('Y-m-d H:i:s'),
        ));
    }

    /** ملخص للوحة. */
    public function stats()
    {
        $now = date('Y-m-d H:i:s');
        return array(
            'plans'    => (int) $this->db->count_all_results('plans'),
            'active'   => (int) $this->db->where('status', 'active')->where('ends_at >=', $now)
                                         ->count_all_results('subscriptions'),
            'pending'  => (int) $this->db->where('status', 'pending')->count_all_results('subscriptions'),
            'unpaid'   => (int) $this->db->where('status', 'unpaid')->count_all_results('invoices'),
            'revenue'  => (int) ($this->db->select_sum('total')->where('status', 'paid')
                                          ->get('invoices')->row()->total ?: 0),
        );
    }


    /**
     * الفاعل: المستخدم في الطلب الوبي، و0 (النظام) في المهام الدورية.
     * مكتبة الجلسة غير محملة في سطر الأوامر، وقراءتها هناك تسقط العملية.
     *
     * والفحص على `get_instance()` لا على `$this`: النموذج في CodeIgniter
     * يخدم خصائصه بـ`__get` وحدها ولا يعرف `__isset`، فـ`isset($this->session)`
     * كاذبة **دائما** وإن كانت الجلسة محملة — القراءة تنجح والفحص يفشل.
     * ولهذا كان كل قيد اشتراك أو فوترة ينسب إلى «النظام» بدل صاحبه.
     * أما `get_instance()` فمتحكم بخصائص حقيقية، فـ`isset` عليه تصدق.
     */
    private function tq_actor_id()
    {
        $CI = function_exists('get_instance') ? get_instance() : null;
        if (!$CI || !isset($CI->session) || !is_object($CI->session)) return 0;
        return (int) $CI->session->userdata('user_id');
    }



    /**
     * TQ-COURSE-SALE — تفعيل شراء كورس مفرد.
     *
     * ثلاثة أشياء لا أكثر: أجل من `offer()`، وبند `course` واحد، وقيد
     * في دفتر معلمه. وكلها بالمبادئ التي تحكم أخويه:
     *
     * · **الأجل ينسخ ولا يشتق كل مرة.** `expiry_period` قد تتغير بين
     *   إصدار الفاتورة وتحويل الحوالة، والمشتري وافق على ما قرأ. وصفر
     *   يعني وصولا دائما، فـ`ends_at` تبقى `NULL` — و`expire_due()`
     *   تشترط `ends_at IS NOT NULL` فلا تلمسه، و`active_subscriptions()`
     *   تعده ساريا أبدا كما تعد اشتراكا بلا أجل.
     *
     * · **البند ينظف قبل أن يكتب.** تفعيل ثان كان يضيف بندا فوق بند في
     *   فرع الصفوف حتى أصلح (TQ-GRADE-SCOPE)، والعلة واحدة هنا.
     *
     * · **وفشل القيد لا يبطل التفعيل.** الطالب دفع واستحق وصوله، ودفتر
     *   المعلم يصالح لاحقا — ومنع الطالب لأن دفترا لم يكتب يعاقب من لا
     *   ذنب له. وهي القاعدة نفسها في المسار والباقة.
     */
    private function activate_course_subscription($sub, $method = null, $transaction_id = null)
    {
        $cid = (int) $sub['course_id'];

        $this->load->model('taqdar_course_sale_model', 'tq_cs');
        $course = $this->tq_cs->course($cid);
        if (!$course) return false;

        $offer = $this->tq_cs->offer($course);
        $days  = (int) $offer['days'];
        $start = time();

        $data = array(
            'status'     => 'active',
            'started_at' => date('Y-m-d H:i:s', $start),
            /* الوصول الدائم `NULL` لا تاريخ بعيد: تاريخ مخترع ينتهي يوما
               ويقفل ما بيع على أنه دائم، ولا أحد يذكر لماذا. */
            'ends_at'    => $days > 0
                          ? date('Y-m-d H:i:s', strtotime('+' . $days . ' days', $start))
                          : null,
        );
        if ($method)         $data['method']         = $method;
        if ($transaction_id) $data['transaction_id'] = $transaction_id;

        $this->db->where('id', (int) $sub['id'])->update('subscriptions', $data);

        $this->db->where('subscription_id', (int) $sub['id'])->delete('subscription_items');
        $this->db->insert('subscription_items', array(
            'subscription_id' => (int) $sub['id'],
            'entity_type'     => 'course',
            'entity_id'       => $cid,
        ));

        /* التجسيد بعد البند مباشرة: البند يجيب الاستحقاق، وصفوف `enrol`
           تجيب الشاشات التي لا تسأل غيره — «كورساتي» و«طلابي» والتقارير
           والشهادات. */
        $this->sync_enrolments((int) $sub['id']);

        /* نصيب المعلم — نسبة واحدة لا وعاء: الكورس لمعلم واحد
           (`course.creator`)، فلا أوزان ولا أكبر بواق. والنسبة تقرأ من
           `offer()` نفسها التي عرضت السعر، فما وعد به المعلم في شاشته
           هو ما يقيد في دفتره. */
        if ((int) $sub['price'] > 0 && (int) $offer['teacher_id'] > 0) {
            try {
                $this->load->model('taqdar_wallet_model');
                $this->taqdar_wallet_model->credit_course_sale(
                    (int) $offer['teacher_id'], $cid, (int) $sub['id'],
                    (int) $sub['price'], $offer['percent'], (string) $course['title']
                );
            } catch (Exception $e) {
                log_message('error', 'TQ-COURSE-SALE: تعذر قيد بيع كورس #' . $cid
                    . ' لاشتراك #' . (int) $sub['id'] . ' — ' . $e->getMessage());
            }
        }

        $this->audit('subscription_activate_course', 'subscriptions#' . (int) $sub['id'],
                     $sub, $this->subscription($sub['id']));
        return true;
    }

    /**
     * تفعيل اشتراك مسار: المدة من `expected_weeks`، والبند `path`،
     * وحصة المعلم تقيد في دفتره — **من هذا المسار وحده**.
     *
     * فشل قيد المحفظة لا يبطل التفعيل: الطالب دفع واستحق وصوله، ودفتر
     * المعلم يصالح لاحقا. والعكس — منع الطالب لأن دفترا لم يكتب —
     * يعاقب من لا ذنب له.
     */
    private function activate_path_subscription($sub, $method = null, $transaction_id = null)
    {
        $path = $this->path($sub['path_id']);
        if (!$path) return false;

        $weeks = (int) $path['expected_weeks'];
        $days  = $weeks > 0 ? $weeks * 7 : (int) (get_settings('taqdar_path_default_days') ?: 180);
        $start = time();

        $data = array(
            'status'     => 'active',
            'started_at' => date('Y-m-d H:i:s', $start),
            'ends_at'    => date('Y-m-d H:i:s', strtotime('+' . max(1, $days) . ' days', $start)),
        );
        if ($method)         $data['method']         = $method;
        if ($transaction_id) $data['transaction_id'] = $transaction_id;

        $this->db->where('id', (int) $sub['id'])->update('subscriptions', $data);

        $this->db->where('subscription_id', (int) $sub['id'])->delete('subscription_items');
        $this->db->insert('subscription_items', array(
            'subscription_id' => (int) $sub['id'],
            'entity_type'     => 'path',
            'entity_id'       => (int) $path['id'],
        ));

        // حصة معلم هذا المسار
        if ((int) $path['teacher_id'] > 0 && (int) $sub['price'] > 0) {
            $this->load->model('taqdar_wallet_model');
            $this->taqdar_wallet_model->credit_path_sale(
                (int) $path['teacher_id'], (int) $path['id'], (int) $sub['id'],
                (int) $sub['price'], $this->path_share_percent($path)
            );
        }

        $this->audit('subscription_activate_path', 'subscriptions#' . (int) $sub['id'],
                     $sub, $this->subscription($sub['id']));
        return true;
    }

    /**
     * يعيد بناء بنود الاشتراكات النشطة من نطاق باقاتها.
     *
     * البنود تكتب في `activate()` وحدها. واشتراك فعل بـ`UPDATE` مباشر
     * على الجدول — من لوحة قديمة أو من يد في قاعدة البيانات — يصير
     * `active` بلا بند واحد. و`subscription_grants()` تخرج عندها بـ
     * `if (!$items) return false;` فيسقط الاستحقاق إلى `enrol`: الطالب
     * دفع، واشتراكه نشط، وبوابته فارغة — ولا خطأ يظهر في أي سجل.
     *
     * والإصلاح **لا يمس اشتراكا له بنود**: البنود صورة النطاق وقت
     * الشراء، وإعادة بنائها من الباقة اليوم تغير ما اشتري بالأمس.
     *
     * @return array fixed · skipped · errors
     */
    public function repair_items()
    {
        $subs = $this->db->select('id, plan_id, path_id, status')
                         ->from('subscriptions')
                         ->where_in('status', array('active', 'cancelled'))
                         ->get()->result_array();

        $fixed = 0; $skipped = 0; $errors = array();

        foreach ($subs as $sub) {
            $sid = (int) $sub['id'];

            $n = (int) $this->db->where('subscription_id', $sid)
                                ->count_all_results('subscription_items');
            if ($n > 0) { $skipped++; continue; }

            /* اشتراك مسار: بنده المسار نفسه لا نطاق باقة. */
            if ((int) $sub['path_id'] > 0) {
                $this->db->insert('subscription_items', array(
                    'subscription_id' => $sid,
                    'entity_type'     => 'path',
                    'entity_id'       => (int) $sub['path_id'],
                ));
                $fixed++;
                continue;
            }

            $plan = $this->plan($sub['plan_id']);
            if (!$plan) { $errors[] = 'اشتراك #' . $sid . ': باقته غير موجودة.'; continue; }

            $scope   = $plan['scope'];
            $unbound = in_array($scope, array('all', 'trial'), true);

            if ($scope === 'grade') {
                $ids = array_filter(array_map('intval',
                           explode(',', (string) $plan['scope_ids'])));
                if (!$ids) $ids = array((int) $plan['scope_id']);
                $wrote = 0;
                foreach (array_unique($ids) as $gid) {
                    if ($gid <= 0) continue;
                    $this->db->insert('subscription_items', array(
                        'subscription_id' => $sid,
                        'entity_type'     => 'grade',
                        'entity_id'       => $gid,
                    ));
                    $wrote++;
                }
                if (!$wrote) { $errors[] = 'اشتراك #' . $sid . ': الباقة بلا صفوف.'; continue; }
            } else {
                $this->db->insert('subscription_items', array(
                    'subscription_id' => $sid,
                    'entity_type'     => $scope,
                    'entity_id'       => $unbound ? 0 : (int) $plan['scope_id'],
                ));
            }

            $this->sync_enrolments($sid);
            $this->audit('subscription_repair_items', 'subscriptions#' . $sid, null,
                         array('plan' => $plan['code'], 'scope' => $scope));
            $fixed++;
        }

        return array('fixed' => $fixed, 'skipped' => $skipped, 'errors' => $errors);
    }

    /**
     * يجسد ما يمنحه الاشتراك صفوف `enrol` تنتهي بانتهائه.
     *
     * `is_entitled()` تعرف الاشتراكات، لكن عشر شاشات في بوابة الطالب
     * لا تعرفها: المواد التعليمية والتقارير والمهام والتقويم وقوائم
     * المعلم كلها تضم `enrol` ضما داخليا مباشرا. فالمشترك النشط
     * يقرأ «لا مواد بعد» في شاشة ويشاهد دروسه في أخرى — والخلل غير
     * مرئي لأن كلتيهما تعمل بلا خطأ.
     *
     * والعلاج تجسيد لا إعادة كتابة عشر شاشات: `enrol` هي علامة
     * Academy على «هذا الطالب يملك هذا المقرر»، و`expiry_date` فيها
     * تجعل الصف يسقط وحده يوم ينتهي الاشتراك.
     *
     * وقاعدتان تحرسان ما اشتري قبل الاشتراكات:
     *   • لا ينقص أجل قائم — من اشترى دورة بلا أجل يبقى مالكا لها
     *   • لا يحذف صف لم نكتبه
     *
     * @return int عدد المقررات التي صار الطالب مسجلا فيها
     */

    /**
     * المقررات التي تجسد من بنود اشتراك — **وهي موجودة فعلا**.
     *
     * TQ-ORPHAN-ENROL: حذف الكورس من اللوحة يمحو `course` و`enrol`
     * و`lesson` و`section` ولا يمس `paths`. فيبقى في الجدول مسار
     * منشور يشير إلى كورس غير موجود — خمسة منها في القاعدة الآن —
     * وكل نداء تجسيد كان يكتب لها صف `enrol` معلقا: صف يضم إلى
     * `course` فلا يطابق شيئا، فيعد الطالب مسجلا في لا شيء.
     *
     * فالضم هنا `inner` على `course`: ما لا وعاء له لا يجسد.
     *
     * @param array $items بنود الاشتراك من `items_of()`
     * @return int[] معرفات مقررات فريدة
     */
    private function grantable_course_ids($items)
    {
        $direct = array();
        $where  = array();

        foreach ((array) $items as $it) {
            $type = $it['entity_type'];
            $eid  = (int) $it['entity_id'];

            if ($type === 'course')       { $direct[] = $eid; continue; }
            if ($type === 'grade')        { $where[] = 'p.`grade_id` = '   . $eid; continue; }
            if ($type === 'subject')      { $where[] = 'p.`subject_id` = ' . $eid; continue; }
            if ($type === 'path')         { $where[] = 'p.`id` = '         . $eid; continue; }
            if ($type === 'all')          { $where[] = '1 = 1'; continue; }
            /* `trial` وحدته الدرس لا المقرر، فلا يجسد شيئا. */
        }

        $courses = array();

        if ($where) {
            $rows = $this->db->query(
                'SELECT DISTINCT p.`course_id`
                   FROM `paths` p
                   JOIN `course` c ON c.`id` = p.`course_id`
                  WHERE p.`status` = "published"
                    AND p.`course_id` > 0
                    AND (' . implode(' OR ', $where) . ')'
            )->result_array();
            foreach ($rows as $r) $courses[] = (int) $r['course_id'];
        }

        /* المقرر الممنوح مباشرة يفحص وجوده كذلك: باقة مقرر حذف وعاؤه
           لا تسجل أحدا في شبح. */
        $direct = array_values(array_unique(array_filter(array_map('intval', $direct))));
        if ($direct) {
            $rows = $this->db->query(
                'SELECT `id` FROM `course` WHERE `id` IN (' . implode(',', $direct) . ')'
            )->result_array();
            foreach ($rows as $r) $courses[] = (int) $r['id'];
        }

        return array_values(array_unique(array_filter($courses)));
    }

    /**
     * يعيد تجسيد كل الاشتراكات النشطة — TQ-ENROL-STALE.
     *
     * `sync_enrolments()` تنادى من `activate()` وحدها، فهي تكتب صورة
     * **لحظة الشراء**. وما ينشر بعدها — مسار جديد، كورس اعتمدته
     * الإدارة، درس أضافه معلم في صف الباقة — لا يصل إلى مشترك قائم
     * أبدا: يفتح «كورساتي» فيقرأ «لا كورسات بعد» وهو يشاهد دروسها
     * في الشاشة المجاورة، لأن الوصول يستعلم حيا والقوائم تقرأ الجدول.
     *
     * فهذه هي شبكة الأمان: تنادى من المهمة الدورية ومن زر في اللوحة،
     * وتنادى فورا عند كل نشر (`resync_scope()`) فلا ينتظر الطالب ربع
     * ساعة. وهي مأمونة التكرار: `sync_enrolments()` تدرج ما ينقص
     * وتمدد الأجل ولا تحذف صفا لم تكتبه.
     *
     * @param int $limit سقف الاشتراكات في النداء الواحد (0 = بلا سقف)
     * @return array عدد ما مر وعدد ما تغير
     */
    public function sync_active_enrolments($limit = 0)
    {
        $q = $this->db->select('id')->from('subscriptions')
                      ->where_in('status', array('active', 'cancelled'))
                      ->order_by('id', 'DESC');
        if ((int) $limit > 0) $q->limit((int) $limit);

        $seen = 0; $changed = 0; $rows = 0;
        foreach ($q->get()->result_array() as $s) {
            $seen++;
            $n = (int) $this->sync_enrolments((int) $s['id']);
            if ($n > 0) { $changed++; $rows += $n; }
        }

        return array('subscriptions' => $seen, 'changed' => $changed, 'enrolments' => $rows);
    }

    /**
     * نشر شيء في نطاق ما: من يملكه الآن يملكه فورا.
     *
     * تنادى من مسارات النشر (اعتماد كورس، مزامنة برنامج، حفظ مسار في
     * اللوحة) فتصيب الاشتراكات التي يعنيها ذلك النطاق وحدها — لا كل
     * اشتراك في المنصة. وفشلها لا يبطل النشر: المهمة الدورية تلحقه.
     *
     * @param int $grade_id   الصف المعني، أو 0
     * @param int $subject_id المادة المعنية، أو 0
     * @param int $course_id  المقرر المعني، أو 0
     * @return int عدد الاشتراكات التي تغيرت
     */
    public function resync_scope($grade_id = 0, $subject_id = 0, $course_id = 0)
    {
        $grade_id   = (int) $grade_id;
        $subject_id = (int) $subject_id;
        $course_id  = (int) $course_id;

        $or = array("si.`entity_type` = 'all'");
        if ($grade_id > 0)   $or[] = "(si.`entity_type` = 'grade'   AND si.`entity_id` = " . $grade_id . ")";
        if ($subject_id > 0) $or[] = "(si.`entity_type` = 'subject' AND si.`entity_id` = " . $subject_id . ")";
        if ($course_id > 0) {
            $or[] = "(si.`entity_type` = 'course' AND si.`entity_id` = " . $course_id . ")";
            /* وبند المسار يصيب مقرره: الباقة اشترت مسارا، والمقرر خلفه. */
            $or[] = "(si.`entity_type` = 'path' AND si.`entity_id` IN ("
                  . "SELECT `id` FROM `paths` WHERE `course_id` = " . $course_id . "))";
        }

        try {
            $rows = $this->db->query(
                'SELECT DISTINCT s.`id`
                   FROM `subscriptions` s
                   JOIN `subscription_items` si ON si.`subscription_id` = s.`id`
                  WHERE s.`status` IN ("active", "cancelled")
                    AND (' . implode(' OR ', $or) . ')'
            )->result_array();
        } catch (Throwable $e) {
            log_message('error', 'TQ-ENROL resync_scope: ' . $e->getMessage());
            return 0;
        }

        $n = 0;
        foreach ($rows as $r) {
            if ($this->sync_enrolments((int) $r['id']) > 0) $n++;
        }
        return $n;
    }

    public function sync_enrolments($subscription_id)
    {
        $sub = $this->subscription($subscription_id);
        if (!$sub) return 0;
        if (!in_array($sub['status'], array('active', 'cancelled'), true)) return 0;

        $uid = (int) $sub['user_id'];
        if ($uid <= 0) return 0;

        /* الأجل: نهاية الاشتراك. و`0` تعني بلا أجل — وهي ما يكتب حين
           لا تاريخ انتهاء، فلا يحبس الطالب بأجل مخترع. */
        $exp = !empty($sub['ends_at']) ? (int) strtotime($sub['ends_at']) : 0;

        $courses = $this->grantable_course_ids($this->items_of((int) $sub['id']));
        if (!$courses) return 0;

        $have = array();
        foreach ($this->db->select('id, course_id, expiry_date')->from('enrol')
                          ->where('user_id', $uid)->where_in('course_id', $courses)
                          ->get()->result_array() as $r) {
            $have[(int) $r['course_id']] = $r;
        }

        $now = time();
        $n   = 0;
        foreach ($courses as $cid) {
            if (!isset($have[$cid])) {
                $this->db->insert('enrol', array(
                    'user_id'       => $uid,
                    'course_id'     => $cid,
                    'gifted_by'     => 0,
                    'expiry_date'   => $exp > 0 ? (string) $exp : null,
                    'date_added'    => $now,
                    'last_modified' => $now,
                ));
                $n++;
                continue;
            }

            /* صف قائم: يمدد أجله ولا يقصر. والصفر «بلا أجل» فلا
               يستبدل به تاريخ ينتهي. */
            $cur = (int) $have[$cid]['expiry_date'];
            if ($cur > 0 && $exp > $cur) {
                $this->db->where('id', (int) $have[$cid]['id'])
                         ->update('enrol', array('expiry_date' => (string) $exp,
                                                 'last_modified' => $now));
                $n++;
            }
        }
        return $n;
    }

    /* =====================================================================
       TQ-SUB-DETAIL — ملف البيعة الواحدة
       ===================================================================== */

    /**
     * كل ما يقال عن اشتراك واحد، مجموعا في نداء واحد.
     *
     * ═══ لماذا شاشة ثانية للاشتراك ═══
     *
     * قائمة `taqdar_admin/subscriptions` تجيب سؤال المسح: «من اشترك،
     * وبكم، وما حاله؟» — وهي تصلح لذلك. ولا تجيب سؤال **الحادثة**:
     * «هذا الصف بعينه، ماذا جرى فيه؟» — وهو ما يسأل حين يتصل مشتر يقول
     * «دفعت ولم يفتح»، أو معلم يقول «باعوا صفي ولم يصلني شيء»، أو
     * محاسب يقول «هذه الفاتورة لم تسدد».
     *
     * وكان جواب الثلاثة **مبعثرا في خمس شاشات** لا يربط بينها شيء:
     * الفاتورة في «الفواتير»، ومحاولات البطاقة في «الدفع بالبطاقة»،
     * والقسمة في قائمة الصف، والقيد في «قيود المحافظ»، والأثر في سجل
     * التدقيق — ولا واحدة منها تعرف رقم الاشتراك. فيبحث المسؤول بالاسم
     * في كل واحدة ويجمع الجواب بيده، أو لا يجمعه.
     *
     * ═══ وما يجمع هنا ═══
     *
     * `sold` ما بيع (TQ-SOLD-NAME) · `user` من اشترى · `invoices`
     * فواتيره · `attempts` محاولات بطاقته · `items` **ما يفتحه فعلا**
     * (وهو غير ما بيع: النطاق ينسخ بنودا وقت التفعيل) · `shares` قسمة
     * إيراده · `entries` ما قيد في دفاتر المعلمين · `audit` أثره.
     *
     * ═══ وكل استعلام ملفوف ═══
     *
     * `revenue_shares` و`payment_attempts` و`wallet_entries` تنشأ وقت
     * التشغيل، وجدول لم يستعمل بعد يرمي استثناء **يبيض الشاشة كلها** —
     * فيقرأ المسؤول صفحة فارغة عن بيعة صحيحة. وقسم ناقص أهون، وهي قاعدة
     * `safe_rows` في اللوحة نفسها.
     *
     * @return array|null null إن لا صف بهذا الرقم
     */
    public function detail($id)
    {
        $id  = (int) $id;
        $sub = $this->subscription($id);
        if (!$sub) return null;

        /* كل استعلام ملفوف: `revenue_shares` و`payment_attempts` و
           `wallet_entries` تنشأ وقت التشغيل، وجدول لم يستعمل بعد يرمي
           استثناء **يبيض الشاشة كلها** — وقسم ناقص أهون.
           **والخطأ يسجل ولا يبتلع**: `catch` صامتة تخفي عمودا كتب بخطأ
           فيقرأ القسم فارغا وهو واثق — «لم يقيد لأحد» عن قيد قائم في
           الدفتر أسوأ من خطأ يظهر. */
        $rows = function ($sql, $args = array()) {
            try { return $this->db->query($sql, $args)->result_array(); }
            catch (Throwable $e) {
                $this->db->reset_query();
                log_message('error', 'TQ-SUB-DETAIL: ' . $e->getMessage());
                return array();
            }
        };

        $out = array('sub' => $sub, 'sold' => $this->sold($sub));

        /* من اشترى — والبريد معه: من يفعل حوالة يتحقق ممن يفعل لها،
           والأسماء تتشابه في قاعدة من ثلاثمئة والبريد لا يتشابه. */
        $out['user'] = $this->db->select('id, email, image, tq_gate,'
                . ' TRIM(CONCAT(COALESCE(first_name,""), " ", COALESCE(last_name,""))) AS name', false)
            ->where('id', (int) $sub['user_id'])->get('users')->row_array();

        $out['invoices'] = $rows(
            'SELECT * FROM `invoices` WHERE `subscription_id` = ? ORDER BY `id` DESC',
            array($id));

        /* محاولات البطاقة — بالاشتراك لا بالفاتورة: `payment_attempts`
           يحمل العمودين، والفاتورة قد تعاد (TQ-SUB-REUSE) فتبقى محاولات
           معلقة على رقم فاتورة سابق. */
        $out['attempts'] = $rows(
            'SELECT * FROM `payment_attempts` WHERE `subscription_id` = ? ORDER BY `id` DESC',
            array($id));

        /* ما يفتحه فعلا — وهو **غير ما بيع**: `subscription_items` صورة
           النطاق وقت التفعيل، فباقة صفين تفتح صفين وإن عدلت بعدها.
           والصف المعلق بلا بنود: لم يفعل بعد، فلا يفتح شيئا. */
        $out['items'] = $this->items_of($id);
        foreach ($out['items'] as $i => $it) {
            $out['items'][$i]['name'] = $this->entity_name(
                (string) $it['entity_type'], (int) $it['entity_id']);
        }

        $out['shares'] = $rows(
            'SELECT r.*, TRIM(CONCAT(COALESCE(u.`first_name`,""), " ",
                                     COALESCE(u.`last_name`,""))) AS teacher_name
               FROM `revenue_shares` r
          LEFT JOIN `users` u ON u.`id` = r.`teacher_id`
              WHERE r.`subscription_id` = ?
           ORDER BY r.`amount_halalas` DESC',
            array($id));

        /* قيود الدفاتر — الأصل مفتاحه رقم الاشتراك في الأربعة كلها
           (`plansub:` · `pathsub:` · `coursesub:` · `booksub:`)، فتقرأ
           بضربة واحدة. وبها يجاب «قسمت ولم يصل» — وهما سؤالان لا واحد:
           صف قسمة بلا قيد يعني أن القيد فشل، وهو غير ألا تكون قسمت. */
        /* و`wallets.owner_user_id` لا `user_id`: الاسم يخطئ الظن فيه،
           وعمود مجهول هنا يبتلعه `catch` أعلاه فيرد القسم فارغا — قسم
           يقول «لم يقيد لأحد» عن قيد قائم في الدفتر، وهو أسوأ من خطأ
           يظهر. */
        $out['entries'] = $rows(
            'SELECT e.*, w.`owner_user_id` AS teacher_id,
                    TRIM(CONCAT(COALESCE(u.`first_name`,""), " ",
                                COALESCE(u.`last_name`,""))) AS teacher_name
               FROM `wallet_entries` e
          LEFT JOIN `wallets` w ON w.`id` = e.`wallet_id`
          LEFT JOIN `users`   u ON u.`id` = w.`owner_user_id`
              WHERE e.`origin` IN (?, ?, ?, ?)
           ORDER BY e.`id` DESC',
            array('plansub:' . $id, 'pathsub:' . $id,
                  'coursesub:' . $id, 'booksub:' . $id));

        /* الأثر — و`entity` يكتب بصورتين في المستودع: `subscriptions#12`
           في أكثر المواضع، و`subscriptions:12` في `revenue.resplit`.
           فيبحث بالاثنتين لا بواحدة — وشاشة تقرأ صورة واحدة تعرض سجلا
           ناقصا وهي واثقة، وهو أسوأ من ألا تعرضه. */
        $out['audit'] = $rows(
            'SELECT a.*, TRIM(CONCAT(COALESCE(u.`first_name`,""), " ",
                                     COALESCE(u.`last_name`,""))) AS actor_name
               FROM `audit_log` a
          LEFT JOIN `users` u ON u.`id` = a.`actor_id`
              WHERE a.`entity` IN (?, ?)
           ORDER BY a.`id` DESC LIMIT 40',
            array('subscriptions#' . $id, 'subscriptions:' . $id));

        return $out;
    }

    /**
     * اسم ما يشير إليه بند استحقاق — بنوعه.
     *
     * و«صف #7» بالرقم حين لا يقابله شيء: TQ-PLAN-DELETE نفسها — بالرقم
     * يقابل السجل، والشرطة لا تقابل شيئا.
     */
    public function entity_name($type, $entity_id)
    {
        $entity_id = (int) $entity_id;
        if ($type === 'all')   return t('كل المحتوى');
        if ($type === 'trial') return t('تجربة');
        if ($entity_id <= 0)   return '—';

        $map = array(
            'grade'   => array('grades',   'name_ar', t('صف')),
            'subject' => array('subjects', 'name_ar', t('مادة')),
            'path'    => array('paths',    'title',   t('مسار')),
            'course'  => array('course',   'title',   t('كورس')),
            'book'    => array('books',    'title',   t('كتاب')),
        );
        if (!isset($map[$type])) return '#' . $entity_id;

        list($table, $col, $noun) = $map[$type];
        try {
            $row = $this->db->select($col)->where('id', $entity_id)
                            ->get($table)->row_array();
        } catch (Throwable $e) { $this->db->reset_query(); $row = null; }

        $name = $row ? trim((string) $row[$col]) : '';
        return $name !== '' ? $name : ($noun . ' #' . $entity_id);
    }

}
