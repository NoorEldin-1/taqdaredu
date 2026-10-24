<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * TQ-APP-CONFIG — تطبيق الجوال كما تضبطه اللوحة.
 *
 * خمسة أسئلة يسألها التطبيق الخادم ولا يحملها في شيفرته: ما آخر إصدار
 * لمنصتي؟ أيجب التحديث؟ أالتطبيق مغلق؟ وأي إصدار iOS تحت مراجعة أبل؟
 * وبأي وسيلة يدفع ذلك الإصدار؟ — فتبدل من اللوحة بلا إصدار جديد.
 *
 * والقرار هنا وحده، والشاشة والواجهة تعرضان. و`policy()` هي المصدر
 * الواحد لسؤال «بأي وسيلة يدفع هذا العميل؟»: تقرؤها كل نقطة تعلن وسائل
 * الدفع **وكل نقطة تبدأ دفعا** — فلا يعرض الزر ما يرده الخادم، ولا يقبل
 * الخادم ما أخفاه الزر. وإخفاء خيار في التطبيق وحده ليس حراسة: النقطة
 * تبقى ترد رابط تاب لمن يسألها.
 */
class Taqdar_mobile_model extends CI_Model
{
    /** وسائل الدفع كما تسميها الواجهة في `pay_method` و`pay_methods`. */
    const PAY_CARD = 'tap';
    const PAY_BANK = 'manual';
    const PAY_CASH = 'cash';

    /** المفاتيح في `settings` وافتراض كل منها — وبلا صف يبقى التطبيق كما كان. */
    private static $DEFAULTS = array(
        'tq_mobile_android_version' => '',
        'tq_mobile_ios_version'     => '',
        'tq_mobile_android_store'   => '',
        'tq_mobile_ios_store'       => '',
        'tq_mobile_android_force'   => '0',
        'tq_mobile_ios_force'       => '0',
        'tq_mobile_force_close'     => '0',
        'tq_mobile_ios_review'      => '',
        'tq_mobile_review_pay'      => 'cash',
    );

    /** وسيلة الدفع للإصدار الذي تحت المراجعة: كاش، أو لا دفع أصلا. */
    private static $REVIEW_PAY = array('cash', 'none');

    private $cfg = null;

    /** القيم المحفوظة، مرة لكل طلب: `policy()` تنادى من كل نقطة دفع. */
    public function config()
    {
        if ($this->cfg !== null) return $this->cfg;

        $out = array();
        foreach (self::$DEFAULTS as $k => $def) {
            $v = get_settings($k);
            $out[$k] = ($v === null) ? $def : trim((string) $v);
        }
        if (!in_array($out['tq_mobile_review_pay'], self::$REVIEW_PAY, true)) {
            $out['tq_mobile_review_pay'] = 'cash';
        }
        return $this->cfg = $out;
    }

    /**
     * يحفظ الشاشة كلها في نداء واحد.
     *
     * والحقل الفارغ يكتب صفه فارغا: «لا إصدار تحت المراجعة» قرار يكتب
     * لا غياب. والمفتاح غير المعلم يصل غائبا من النموذج فيقرأ «لا».
     *
     * @return array {ok, errors[], before, after}
     */
    public function save(array $in)
    {
        $errors = array();
        $vals   = array();

        foreach (array('android' => t('أندرويد'), 'ios' => 'iOS') as $p => $label) {
            $v = trim((string) ($in['tq_mobile_' . $p . '_version'] ?? ''));
            if ($v !== '' && !self::parse_version($v)) {
                $errors[] = t('إصدار ____ غير مفهوم: «____». اكتبه أرقاما بنقاط مثل 1.2.3.', array($label, $v));
            }
            $vals['tq_mobile_' . $p . '_version'] = $v;
            $vals['tq_mobile_' . $p . '_force']   = empty($in['tq_mobile_' . $p . '_force']) ? '0' : '1';

            $url = trim((string) ($in['tq_mobile_' . $p . '_store'] ?? ''));
            if ($url !== '' && !self::store_url($url)) {
                $errors[] = t('رابط المتجر (____) غير صالح: «____». الصقه كاملا يبدأ بـ https://', array($label, $url));
            }
            $vals['tq_mobile_' . $p . '_store'] = $url;

            /* تحديث إجباري بلا رقم لا يقول إلى أي إصدار يحدث، فلا يقفل
               التطبيق على أحد — وهو يبدو مفعلا في الشاشة. وبلا رابط متجر
               يقفله ولا يدل على باب: شاشة «حدث التطبيق» بلا زر يحدث به. */
            if ($vals['tq_mobile_' . $p . '_force'] === '1' && $v === '') {
                $errors[] = t('التحديث الإجباري (____) يحتاج رقم آخر إصدار.', $label);
            }
            if ($vals['tq_mobile_' . $p . '_force'] === '1' && $url === '') {
                $errors[] = t('التحديث الإجباري (____) يحتاج رابط المتجر.', $label);
            }
        }

        $vals['tq_mobile_force_close'] = empty($in['tq_mobile_force_close']) ? '0' : '1';

        $review = trim((string) ($in['tq_mobile_ios_review'] ?? ''));
        if ($review !== '' && !self::parse_version($review)) {
            $errors[] = t('إصدار المراجعة غير مفهوم: «____». اكتبه كما في App Store Connect، مثل 1.2.4.', $review);
        }
        $vals['tq_mobile_ios_review'] = $review;

        $pay = (string) ($in['tq_mobile_review_pay'] ?? 'cash');
        $vals['tq_mobile_review_pay'] = in_array($pay, self::$REVIEW_PAY, true) ? $pay : 'cash';

        if ($errors) return array('ok' => false, 'errors' => $errors);

        $before = $this->config();
        foreach ($vals as $k => $v) $this->put($k, $v);
        $this->cfg = null;

        return array('ok' => true, 'errors' => array(), 'before' => $before, 'after' => $vals);
    }

    /**
     * من يسأل: منصته وإصداره.
     *
     * والمنصة المجهولة ترد فارغة، وتعامل معاملة الموقع: لا مراجعة ولا
     * تحديث. فطلب لا يقول من هو لا يقع في وضع لم يقصد له.
     *
     * @param string $platform `ios` · `android`
     * @param string $version  `1.2.4+9` كما يرسله التطبيق، أو `1.2.4`
     */
    public function client($platform, $version)
    {
        $p = strtolower(trim((string) $platform));
        if (!in_array($p, array('android', 'ios'), true)) $p = '';

        $v = self::parse_version($version);
        return array(
            'platform' => $p,
            'version'  => $v ? $v['v'] : '',
            'canon'    => $v ? $v['c'] : '',
            'build'    => $v ? $v['b'] : '',
        );
    }

    /**
     * أهذا العميل هو إصدار iOS الذي تحت المراجعة؟
     *
     * والبناء يقارن متى كتبه المسؤول وحده: «1.2.4» تطابق كل بناء منه،
     * و«1.2.4 (9)» تطابق التاسع وحده — فبناء ثان يرفع للإصدار نفسه
     * بعد رفض لا يحتاج حقلا آخر.
     */
    public function in_review(array $c)
    {
        if ($c['platform'] !== 'ios' || $c['canon'] === '') return false;

        $r = self::parse_version($this->config()['tq_mobile_ios_review']);
        if (!$r || $r['c'] !== $c['canon']) return false;

        return $r['b'] === '' || $r['b'] === $c['build'];
    }

    /**
     * بأي وسيلة يدفع هذا العميل؟
     *
     * - **إصدار المراجعة**: كاش وحده، أو لا شيء — لا بطاقة ولا تحويل.
     * - **غيره**: ما كان قبل هذه الشاشة حرفا بحرف — البطاقة متى عملت
     *   تاب، والتحويل البنكي دائما (وبياناته تقول إن كانت مضبوطة).
     *
     * @param array $c          من `client()`
     * @param bool  $card_ready `Taqdar_tap_model::ready()` — تمرر ولا تقرأ
     *                          هنا، فيبقى هذا الملف بلا نموذج ثان
     * @return array {review, card, bank, cash, methods[]}
     */
    public function policy(array $c, $card_ready)
    {
        if ($this->in_review($c)) {
            $cash = $this->config()['tq_mobile_review_pay'] === 'cash';
            return array(
                'review'  => true,
                'card'    => false,
                'bank'    => false,
                'cash'    => $cash,
                'methods' => $cash ? array(self::PAY_CASH) : array(),
            );
        }

        $card = (bool) $card_ready;
        return array(
            'review'  => false,
            'card'    => $card,
            'bank'    => true,
            'cash'    => false,
            'methods' => $card ? array(self::PAY_CARD, self::PAY_BANK) : array(self::PAY_BANK),
        );
    }

    /**
     * ما يقرؤه التطبيق عند التشغيل — `GET api/v1/app/config`.
     *
     * والتحديث الإجباري يحسب هنا لا في التطبيق: مقارنة الإصدارات نصا
     * تجعل «1.10» أقدم من «1.9»، ونسخة ثانية من المقارنة في Dart تفترق
     * عن هذه عند أول إصدار برقمين.
     */
    public function app_state(array $c, $card_ready)
    {
        $cfg    = $this->config();
        $latest = '';
        $store  = '';
        $force  = false;

        if ($c['platform'] !== '') {
            $latest = $cfg['tq_mobile_' . $c['platform'] . '_version'];
            $store  = $cfg['tq_mobile_' . $c['platform'] . '_store'];
            $force  = $cfg['tq_mobile_' . $c['platform'] . '_force'] === '1';
        }

        $l      = self::parse_version($latest);
        $behind = $l && $c['canon'] !== '' && version_compare($c['canon'], $l['c'], '<');
        $pol    = $this->policy($c, $card_ready);

        /* والإجبار يشترط بابا: `save()` ترد التحديث الإجباري بلا رابط، وهذا
           حزام ثان لصف كتب في القاعدة بيد — قفل بلا زر يحدث به لا يرجع
           منه أحد. */
        if (!self::store_url($store)) $force = false;

        return array(
            'platform'         => $c['platform'] !== '' ? $c['platform'] : null,
            'version'          => $c['version'] !== '' ? $c['version'] : null,
            'latest_version'   => $l ? $l['v'] : null,
            'store_url'        => self::store_url($store) ? $store : null,
            'update_available' => (bool) $behind,
            'force_update'     => $force,
            'update_required'  => $force && $behind,
            'force_close'      => $cfg['tq_mobile_force_close'] === '1',
            'pay_methods'      => $pol['methods'],
        );
    }

    /**
     * «1.2.4» · «1.2.4+9» · «1.2.4 (9)» — الثالثة صورة App Store Connect،
     * والثانية ما يرسله التطبيق (`version+buildNumber`).
     *
     * @return array|null {v: كما كتب, c: للمقارنة, b: البناء أو ''}
     */
    public static function parse_version($s)
    {
        $s = trim((string) $s);
        if (!preg_match('/^v?(\d+(?:\.\d+){0,3})\s*(?:\+\s*(\d+)|\(\s*(\d+)\s*\))?$/i', $s, $m)) {
            return null;
        }

        $build = (isset($m[2]) && $m[2] !== '') ? $m[2] : (isset($m[3]) ? $m[3] : '');
        return array('v' => $m[1], 'c' => self::canon($m[1]),
                     'b' => $build === '' ? '' : (string) (int) $build);
    }

    /**
     * رابط متجر يفتحه التطبيق: `https` وحده. فرابط بلا بروتوكول يكتب في
     * الحقل يصل التطبيق نصا لا يفتح، فيبقى زر «حدث الآن» صامتا.
     */
    public static function store_url($u)
    {
        $u = trim((string) $u);
        return $u !== '' && stripos($u, 'https://') === 0 && filter_var($u, FILTER_VALIDATE_URL) !== false;
    }

    /** «1.2» و«1.2.0» إصدار واحد: الأصفار الطرفية تحذف قبل كل مقارنة. */
    private static function canon($v)
    {
        $parts = array_map('intval', explode('.', $v));
        while (count($parts) > 1 && end($parts) === 0) array_pop($parts);
        return implode('.', $parts);
    }

    private function put($key, $val)
    {
        $exists = $this->db->where('key', $key)->count_all_results('settings') > 0;
        if ($exists) $this->db->where('key', $key)->update('settings', array('value' => (string) $val));
        else         $this->db->insert('settings', array('key' => $key, 'value' => (string) $val));
    }
}
