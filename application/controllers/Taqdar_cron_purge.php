<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * تنظيف بيانات الديمو — أداة سطر أوامر.
 *
 * الحسابات **تبقى**؛ يُمحى ما يجعل المنصّة تبدو ذات عملاء وإيرادات ليست لها.
 *
 * وثلاث حقائق تحكم كل سطر هنا:
 *
 * ١ — `information_schema.referential_constraints` فارغ: لا مفتاح أجنبي واحد
 *     في القاعدة. فلا شيء يمنع الحذف ولا شيء يتتالى، وكل حذف ييتم بصمت. ولهذا
 *     تُسمى كل صفوف الحذف قبل حذفها، ويُحسب اليتم بعده.
 *
 * ٢ — الأشباح ٢٨٧ و٣٠٤ و٣١٥ و٣١٨ حسابات مسحتها اللوحة عبر
 *     `User_model::delete_user()` الذي يمسح صف `users` ولا شيء غيره — فبقيت
 *     اشتراكاتها **نشطة** وفواتيرها **مدفوعة**، وهي تعد عملاء إلى اليوم.
 *
 * ٣ — عملية واحدة في القاعدة كلها حقيقية: `subscriptions#113` وفاتورتها
 *     `invoices#100` — خمسة ريالات من المستخدم ٣٠٦. وهي محروسة بالاسم، ومعها
 *     الباقة ٢١ التي تسندها، والمستخدم ٢٨٨ لأنه **الحساب الإداري الوحيد**
 *     في المنصة: حذفه أو تعطيله يقفل اللوحة على الجميع بلا رجعة.
 *
 * وجانب المعلمين لا يمس: `revenue_shares` و`wallets` و`wallet_entries` تبقى
 * كما هي بقرار صاحب المنصة، ويُسلَّم أثرها الباقي جردًا في `verify`. ويُستثنى
 * المعلم ٢٨٩ وحده — حساب اختبار صريح، فمدفوعاته ومحفظته تُنظَّف معه.
 *
 * الأفعال:
 *   scan            جرد كامل بلا كتابة
 *   purge [apply]   الحذف — معاينة ما لم تكتب apply
 *   verify          ما بقي من أثر، وما يتم
 */
class Taqdar_cron_purge extends CI_Controller
{
    /** حسابات الاختبار — تبقى هي، ويُمحى أثرها المالي.
     *  و٣٣٧ منها: اسمه «testing qa» وبريده على نطاق بريد مؤقت. */
    const TEST_USERS = array(288, 289, 290, 291, 297, 298, 299, 300, 301, 337, 391, 392);

    /** حساب اختبار المعلم وحده: مدفوعاته ومحفظته تنظَّف معه. */
    const TEST_TEACHER = 289;

    /** أرقام معاملات المنح اليدوية العابثة. */
    const JUNK_TXN = array('Test', 'asdasd', 'asdad', '4524', '5848148');

    /** لا يمس تحت أي شرط. */
    const KEEP_SUB     = 113;   // خمسة ريالات حية حقيقية
    const KEEP_INVOICE = 100;   // فاتورتها
    const KEEP_PLAN    = 21;    // الباقة التي تسندها
    const KEEP_USER    = 288;   // الحساب الإداري الوحيد

    public function __construct()
    {
        parent::__construct();
        if (!$this->input->is_cli_request()) show_404();
        /* القاعدة ليست في الأوتولود لسطر الأوامر — وغيابها يُخرج الأداة صامتة. */
        $this->load->database();
    }

    public function index() { $this->scan(); }

    /* =====================================================================
       الجرد
       ===================================================================== */

    public function scan()
    {
        $t = $this->targets();

        echo "جرد بيانات الديمو\n";
        echo str_repeat('─', 62) . "\n";

        $this->row('مدفوعات موروثة (payment)', $t['payment'],
                   $this->money('SELECT COALESCE(SUM(amount),0) FROM `payment`', $t['payment'], 'id') . ' ر.س');
        $this->row('اشتراكات وهمية', $t['subs']);
        $this->row('بنود اشتراك تابعة', $t['sub_items']);
        $this->row('فواتير وهمية', $t['invoices'],
                   number_format($this->sum('invoices', 'total', $t['invoices']) / 100, 2) . ' ر.س');
        $this->row('محاولات دفع تجريبية', $t['attempts_test']);
        $this->row('شهادات عملاء مخترعة', $t['testimonials']);
        $this->row('مقررات «ai animation»', $t['courses']);
        $this->row('مساراتها', $t['paths']);
        $this->row('مدفوعات معلم الاختبار (payout)', $t['payout']);
        $this->row('طلبات انضمام عابثة', $t['applications']);
        $this->row('إشعارات سبام نموذج التواصل', $t['contact_notif']);

        echo str_repeat('─', 62) . "\n";
        echo "الإيراد المعروض اليوم: " . number_format($this->paid_total() / 100, 2) . " ر.س\n";
        echo "وبعد التنظيف:        " . number_format($this->paid_total($t['invoices']) / 100, 2) . " ر.س\n\n";

        echo "محروس بالاسم — لا يمس:\n";
        echo "  الاشتراك #" . self::KEEP_SUB . " والفاتورة #" . self::KEEP_INVOICE
           . " — عملية Tap حية حقيقية\n";
        echo "  الباقة #" . self::KEEP_PLAN . " — تسند تلك العملية\n";
        echo "  المستخدم #" . self::KEEP_USER . " — الحساب الإداري الوحيد في المنصة\n";
        echo "  محافظ المعلمين الحقيقيين وحصص إيرادهم\n\n";
        echo "معاينة — لا شيء كُتب. للتنفيذ:  purge apply\n";
    }

    /* =====================================================================
       الحذف
       ===================================================================== */

    public function purge($apply = '')
    {
        $t = $this->targets();

        if ($apply !== 'apply') { $this->scan(); return; }

        /* الحارس الأخير: لو تسلل محروس إلى أي قائمة، تتوقف الأداة كلها. */
        if (in_array(self::KEEP_SUB, $t['subs'], true)
            || in_array(self::KEEP_INVOICE, $t['invoices'], true)) {
            echo "✗ توقّف: صفٌّ محروس في قائمة الحذف. لم يُكتب شيء.\n";
            return;
        }

        /* الترتيب مقصود: التابع قبل متبوعه، فلو انقطع التشغيل في منتصفه
           كان الباقي صفوفًا أبًا بلا أبناء لا أبناء بلا أب. */
        $n = array();
        $n['sub_items']   = $this->kill('subscription_items', 'id', $t['sub_items']);
        $n['invoices']    = $this->kill('invoices',           'id', $t['invoices']);
        $n['subs']        = $this->kill('subscriptions',      'id', $t['subs']);
        $n['payment']     = $this->kill('payment',            'id', $t['payment']);
        $n['attempts_t']  = $this->kill('payment_attempts',   'id', $t['attempts_test']);
        $n['testimonial'] = $this->kill('tq_testimonials',    'id', $t['testimonials']);
        $n['paths']       = $this->kill('paths',              'id', $t['paths']);
        $n['courses']     = $this->kill('course',             'id', $t['courses']);
        $n['payout']      = $this->kill('payout',             'id', $t['payout']);
        $n['apps']        = $this->kill('applications',       'id', $t['applications']);
        $n['contact']     = $this->kill('notifications',      'id', $t['contact_notif']);

        /* محفظة معلم الاختبار وحدها تُصفَّر — لا محفظة معلم حقيقي. */
        $this->db->where('owner_user_id', self::TEST_TEACHER)
                 ->update('wallets', array('balance_available' => 0,
                                           'balance_pending'   => 0,
                                           'balance_locked'    => 0));
        $w = $this->db->affected_rows();

        echo "✓ حُذف:\n";
        foreach ($n as $k => $v) if ($v > 0) echo "    " . str_pad($k, 14) . " $v\n";
        echo "✓ صُفِّرت محفظة معلم الاختبار: $w\n\n";
        echo "الإيراد المعروض الآن: " . number_format($this->paid_total() / 100, 2) . " ر.س\n";
    }

    /* =====================================================================
       ما بقي
       ===================================================================== */

    public function verify()
    {
        echo "فحص بعد التنظيف\n" . str_repeat('─', 62) . "\n";

        $paid = $this->paid_total();
        $this->check('الإيراد المعروض', number_format($paid / 100, 2) . ' ر.س', $paid === 500);
        $this->check('الاشتراك الحقيقي #' . self::KEEP_SUB,
                     $this->one('SELECT status FROM `subscriptions` WHERE id=' . self::KEEP_SUB) ?: 'مفقود',
                     $this->one('SELECT COUNT(*) FROM `subscriptions` WHERE id=' . self::KEEP_SUB) == 1);
        $this->check('الفاتورة الحقيقية #' . self::KEEP_INVOICE,
                     $this->one('SELECT COUNT(*) FROM `invoices` WHERE id=' . self::KEEP_INVOICE),
                     $this->one('SELECT COUNT(*) FROM `invoices` WHERE id=' . self::KEEP_INVOICE) == 1);
        $this->check('الحساب الإداري #' . self::KEEP_USER,
                     $this->one('SELECT COUNT(*) FROM `users` WHERE id=' . self::KEEP_USER
                                . ' AND role_id=1 AND status=1'),
                     $this->one('SELECT COUNT(*) FROM `users` WHERE id=' . self::KEEP_USER
                                . ' AND role_id=1 AND status=1') == 1);

        $ids = implode(',', self::TEST_USERS);
        $this->check('حسابات الاختبار باقية',
                     $this->one("SELECT COUNT(*) FROM `users` WHERE id IN ($ids)") . ' من ' . count(self::TEST_USERS),
                     $this->one("SELECT COUNT(*) FROM `users` WHERE id IN ($ids)") == count(self::TEST_USERS));
        $this->check('اشتراكاتها',
                     $this->one("SELECT COUNT(*) FROM `subscriptions` WHERE user_id IN ($ids)"),
                     $this->one("SELECT COUNT(*) FROM `subscriptions` WHERE user_id IN ($ids)") == 0);
        $this->check('مدفوعاتها',
                     $this->one("SELECT COUNT(*) FROM `payment` WHERE user_id IN ($ids)"),
                     $this->one("SELECT COUNT(*) FROM `payment` WHERE user_id IN ($ids)") == 0);
        $this->check('شهادات مخترعة', $this->one('SELECT COUNT(*) FROM `tq_testimonials`'),
                     $this->one('SELECT COUNT(*) FROM `tq_testimonials`') == 0);

        $ghost = $this->one(
            'SELECT COUNT(*) FROM `subscriptions` s
              WHERE NOT EXISTS (SELECT 1 FROM `users` u WHERE u.id = s.user_id)');
        $this->check('اشتراكات لحسابات غير موجودة', $ghost, $ghost == 0);

        /* ما تركناه عمدًا — يُسلَّم جردًا لا يُصلَح. */
        echo "\nأثرٌ باقٍ عمدًا (جانب المعلمين لم يُمس):\n";
        $rs = $this->one(
            'SELECT COUNT(*) FROM `revenue_shares` r
              WHERE NOT EXISTS (SELECT 1 FROM `subscriptions` s WHERE s.id = r.subscription_id)');
        echo "  حصص إيراد تشير إلى اشتراك محذوف: $rs\n";
        $q = $this->db->query(
            'SELECT w.owner_user_id AS uid, u.first_name AS nm,
                    (w.balance_available + w.balance_pending + w.balance_locked) AS bal
               FROM `wallets` w JOIN `users` u ON u.id = w.owner_user_id
              WHERE (w.balance_available + w.balance_pending + w.balance_locked) > 0
              ORDER BY bal DESC')->result_array();
        $tot = 0;
        foreach ($q as $r) {
            $tot += (int) $r['bal'];
            echo '  محفظة #' . $r['uid'] . '  ' . mb_substr((string) $r['nm'], 0, 18)
               . '  ' . number_format($r['bal'] / 100, 2) . " ر.س\n";
        }
        echo '  المجموع: ' . number_format($tot / 100, 2) . " ر.س — تُسوّى مع المعلمين يدويًّا.\n";
    }

    /* =====================================================================
       الداخل
       ===================================================================== */

    /** كل ما يُحذف، محسوبًا بقواعد لا بقوائم — فيبقى صحيحًا لو أُعيد التشغيل. */
    private function targets()
    {
        $tu    = implode(',', self::TEST_USERS);
        $junk  = "'" . implode("','", self::JUNK_TXN) . "'";

        /* الوصول والإيراد منفصلان: `subscriptions` يقود الوصول، و
           `invoices.status='paid'` يقود لوحة الإيرادات. وهذا الفصل هو ما يسمح
           بإزالة كل ريال وهمي بلا قطع خدمة إنسان.

           فالاشتراك لا يُحذف إلا لحساب اختبار أو لشبح — أما مستخدم موجود
           فاشتراكه يبقى **مهما بدا مرجعه عابثًا**. والحالة التي علّمتنا هذا:
           الاشتراك ١١٠ مرجعه اليدوي حرفيًّا «Test»، وصاحبه الطالب ٣٣٨ له اثنا
           عشر تسجيلا وخمسة دروس متقدمة وولي أمر مرتبط — منحةٌ حقيقية كتب
           مرجعها بإهمال، لا بيانات ديمو. وحذفها يقطع خدمة طالب يدرس. */
        $subs = $this->col(
            "SELECT DISTINCT s.id FROM `subscriptions` s
              WHERE s.id <> " . self::KEEP_SUB . "
                AND ( s.user_id IN ($tu)
                   OR NOT EXISTS (SELECT 1 FROM `users` u WHERE u.id = s.user_id) )");

        $in = $subs ? implode(',', $subs) : '0';

        /* الفواتير على النقيض: تُحذف الوهمية أيًّا كان صاحبها — لأنها لا تمنح
           وصولا، وهي وحدها ما يجعل اللوحة تقول ستة آلاف ريال. و`100` مستثناة.

           ولا يُشترط في المرجع العابث وسيلةٌ بعينها: الفاتورة ٩٧ مسجل فيها
           `method='tap'` بينما اشتراكها يدوي، ومرجعها نصه «Test». فاشتراط
           `manual` أسقطها وأبقى ستمئة وتسعة وتسعين ريالا وهمية في اللوحة. */
        $invoices = $this->col(
            "SELECT DISTINCT i.id FROM `invoices` i
              WHERE i.id <> " . self::KEEP_INVOICE . "
                AND ( i.subscription_id IN ($in)
                   OR i.user_id IN ($tu)
                   OR NOT EXISTS (SELECT 1 FROM `users` u WHERE u.id = i.user_id)
                   OR i.transaction_id IN ($junk)
                   OR EXISTS (SELECT 1 FROM `payment_attempts` pa
                               WHERE pa.invoice_id = i.id AND pa.mode = 'test') )");

        return array(
            'payment'       => $this->col(
                "SELECT id FROM `payment`
                  WHERE user_id IN ($tu) OR transaction_id = 'tq-seed-teacher'"),
            'subs'          => $subs,
            'sub_items'     => $this->col("SELECT id FROM `subscription_items` WHERE subscription_id IN ($in)"),
            'invoices'      => $invoices,
            'attempts_test' => $this->col("SELECT id FROM `payment_attempts` WHERE mode = 'test'"),
            'testimonials'  => $this->col('SELECT id FROM `tq_testimonials`'),
            'courses'       => $this->col(
                "SELECT id FROM `course`
                  WHERE (title LIKE '%ai animation%' OR title LIKE '%ai- تعلم%')
                    AND NOT EXISTS (SELECT 1 FROM `lesson` l WHERE l.course_id = `course`.id)
                    AND NOT EXISTS (SELECT 1 FROM `enrol`  e WHERE e.course_id  = `course`.id)"),
            'paths'         => $this->col(
                "SELECT id FROM `paths`
                  WHERE title LIKE '%ai animation%' OR title LIKE '%ai- تعلم%'"),
            'payout'        => $this->col('SELECT id FROM `payout` WHERE user_id = ' . self::TEST_TEACHER),
            'applications'  => $this->col(
                "SELECT id FROM `applications`
                  WHERE message REGEXP '^[a-z]{8,}$' OR message LIKE '%asdasd%'"),
            'contact_notif' => $this->col(
                "SELECT id FROM `notifications` WHERE type = 'contact'"),
        );
    }

    private function kill($table, $key, $ids)
    {
        if (!$ids) return 0;
        /* دفعات من ألف: جدول الإشعارات فيه إحدى وعشرون ألف صف. */
        $n = 0;
        foreach (array_chunk($ids, 1000) as $chunk) {
            $this->db->where_in($key, $chunk)->delete($table);
            $n += $this->db->affected_rows();
        }
        return $n;
    }

    private function col($sql)
    {
        $out = array();
        foreach ($this->db->query($sql)->result_array() as $r) $out[] = (int) reset($r);
        return $out;
    }

    private function one($sql)
    {
        $r = $this->db->query($sql)->row_array();
        return $r ? reset($r) : null;
    }

    private function sum($table, $col, $ids)
    {
        if (!$ids) return 0;
        return (int) $this->one("SELECT COALESCE(SUM(`$col`),0) FROM `$table`
                                  WHERE id IN (" . implode(',', $ids) . ')');
    }

    private function money($sql, $ids, $key)
    {
        if (!$ids) return '0';
        return number_format((float) $this->one($sql . " WHERE `$key` IN (" . implode(',', $ids) . ')'), 2);
    }

    /** مجموع ما تعرضه المنصة مدفوعًا — اختياريًّا باستثناء ما سيُحذف. */
    private function paid_total($exclude = array())
    {
        $w = $exclude ? ' AND id NOT IN (' . implode(',', $exclude) . ')' : '';
        return (int) $this->one("SELECT COALESCE(SUM(total),0) FROM `invoices` WHERE status = 'paid'" . $w);
    }

    private function row($label, $ids, $extra = '')
    {
        echo '  ' . str_pad($label, 34) . str_pad((string) count($ids), 6, ' ', STR_PAD_LEFT)
           . ($extra !== '' ? '   ' . $extra : '') . "\n";
    }

    private function check($label, $val, $ok)
    {
        echo '  ' . ($ok ? '✓' : '✗') . ' ' . str_pad($label, 34) . $val . "\n";
    }
}
