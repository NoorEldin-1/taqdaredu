<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * إعدادات الطالب — القراءة والكتابة في موضع واحد.
 *
 * الصفحة قبل هذا الملفّ كانت تعرض قيمًا حرفية مكتوبة في العرض («مفعّل»،
 * «مطفأ»، «العربية»، «ساعات صمت من 10 مساءً إلى 7 صباحًا») كأنها إعدادات
 * صاحب الحساب. وهذا أسوأ من نقص: النقص يُرى فيُطلب، والقيمة المفبركة تُصدَّق
 * فلا تُطلب. فكل قيمة تظهر الآن إمّا من `users` أو من جدولَي التفضيلات
 * أدناه، وما لا مصدر له حُذف ولم يُلوَّن بلون الإعداد.
 *
 * الجدولان من نصيب هذا الملفّ (بادئة tq_prefs_):
 *   tq_prefs_user    صفّ واحد لكل مستخدم: الوجه واللغة وساعات الصمت.
 *   tq_prefs_notify  صفّ لكل (مستخدم، نوع، قناة).
 *
 * القناتان اثنتان لا ثلاث: `inapp` لها جدول `notifications`، و`email` له
 * `Email_model`. ولا قناة «إشعار الجهاز» لأن المنصّة لا تملك بنية دفع —
 * ومفتاحٌ لقناة غير موجودة وعدٌ لا إعداد.
 */
class Taqdar_settings_model extends CI_Model
{
    /** الحدّ الأقصى لصورة الحساب — نفس ما تعلنه الصفحة للمستخدم. */
    const IMAGE_MAX_BYTES = 2097152; // 2 ميجابايت

    /* ================================================================
       المخطّط
       ================================================================ */

    /**
     * ينشئ الجدولين إن غابا. يُستدعى من كل مدخل قراءة أو كتابة، ويحرسه
     * علم ساكن فلا يتكرّر في الطلب الواحد.
     */
    public function ensure_schema()
    {
        static $done = false;
        if ($done) return;
        $done = true;

        if (!$this->db->table_exists('tq_prefs_user')) {
            $this->db->query(
                "CREATE TABLE IF NOT EXISTS `tq_prefs_user` (
                    `user_id`     INT(11) UNSIGNED NOT NULL,
                    `theme`       VARCHAR(10)  NOT NULL DEFAULT 'auto',
                    `language`    VARCHAR(32)  NOT NULL DEFAULT 'arabic',
                    `quiet_on`    TINYINT(1)   NOT NULL DEFAULT 0,
                    `quiet_from`  TINYINT(2)   NOT NULL DEFAULT 22,
                    `quiet_to`    TINYINT(2)   NOT NULL DEFAULT 7,
                    `updated_at`  INT(11)      NOT NULL DEFAULT 0,
                    PRIMARY KEY (`user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (!$this->db->table_exists('tq_prefs_notify')) {
            $this->db->query(
                "CREATE TABLE IF NOT EXISTS `tq_prefs_notify` (
                    `id`          INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `user_id`     INT(11) UNSIGNED NOT NULL,
                    `notify_type` VARCHAR(48)  NOT NULL,
                    `channel`     VARCHAR(16)  NOT NULL,
                    `enabled`     TINYINT(1)   NOT NULL DEFAULT 1,
                    `updated_at`  INT(11)      NOT NULL DEFAULT 0,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uq_user_type_channel` (`user_id`,`notify_type`,`channel`),
                    KEY `ix_user` (`user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    }

    /* ================================================================
       القوائم المرجعية
       ================================================================ */

    /** أنواع التنبيه المعروضة — رمزها هو ما يمرّره المرسِل إلى allows(). */
    public function notify_types()
    {
        return array(
            'review_due'        => array('تذكير المراجعة',   'حين يحين موعد مراجعة درس سبق'),
            'station_unlocked'  => array('فتح محطة جديدة',   'حين تُفتح محطة تالية في مسارك'),
            'quiz_result'       => array('نتيجة اختبار',     'حين تُرصد نتيجة اختبار أدّيته'),
            'purchase_confirmed'=> array('تأكيد الشراء',     'حين يُسجَّل اشتراك أو دفعة على حسابك'),
            'session_confirmed' => array('تأكيد حصة',        'حين تُثبَّت حصّة بالطلب أو يتغيّر موعدها'),
        );
    }

    /** القناتان القائمتان فعلًا. */
    public function notify_channels()
    {
        return array(
            'inapp' => 'داخل المنصّة',
            'email' => 'بريد إلكتروني',
        );
    }

    /**
     * الافتراض حين لا يكون للمستخدم صفّ محفوظ.
     * وهو إعداده الساري فعلًا حتى يغيّره — لا قيمة تزيينية.
     */
    public function notify_defaults()
    {
        return array(
            'review_due'         => array('inapp' => 1, 'email' => 0),
            'station_unlocked'   => array('inapp' => 1, 'email' => 0),
            'quiz_result'        => array('inapp' => 1, 'email' => 1),
            'purchase_confirmed' => array('inapp' => 1, 'email' => 1),
            'session_confirmed'  => array('inapp' => 1, 'email' => 1),
        );
    }

    /** الوجوه الثلاثة: يتبع الجهاز، أو يُثبَّت فاتحًا، أو داكنًا. */
    public function themes()
    {
        return array(
            'auto'  => 'يتبع جهازي',
            'light' => 'فاتح دائمًا',
            'dark'  => 'داكن دائمًا',
        );
    }

    /** اللغات = أعمدة جدول language فعلًا، لا قائمة مكتوبة يدويًّا. */
    public function languages()
    {
        $labels = array('arabic' => 'العربية', 'english' => 'English', 'hindi' => 'हिन्दी');
        $out = array();
        foreach ($this->db->list_fields('language') as $col) {
            if ($col === 'phrase_id' || $col === 'phrase' || $col === '') continue;
            $out[$col] = isset($labels[$col]) ? $labels[$col] : ucfirst($col);
        }
        if (!$out) $out = array('arabic' => 'العربية');
        return $out;
    }

    /* ================================================================
       القراءة
       ================================================================ */

    /** تفضيلات المستخدم العامّة، مع الافتراضات حين لا صفّ له. */
    public function prefs($user_id)
    {
        $this->ensure_schema();

        $row = $this->db->where('user_id', (int) $user_id)
                        ->get('tq_prefs_user')->row_array();

        $langs = $this->languages();
        $site  = function_exists('get_settings') ? (get_settings('language') ?: 'arabic') : 'arabic';
        if (!isset($langs[$site])) $site = key($langs);

        $out = array(
            'theme'      => 'auto',
            'language'   => $site,
            'quiet_on'   => 0,
            'quiet_from' => 22,
            'quiet_to'   => 7,
            'saved'      => false,
        );

        if ($row) {
            $out['theme']      = isset($this->themes()[$row['theme']]) ? $row['theme'] : 'auto';
            $out['language']   = isset($langs[$row['language']]) ? $row['language'] : $out['language'];
            $out['quiet_on']   = (int) $row['quiet_on'];
            $out['quiet_from'] = (int) $row['quiet_from'];
            $out['quiet_to']   = (int) $row['quiet_to'];
            $out['saved']      = true;
        }
        return $out;
    }

    /** مصفوفة [نوع][قناة] => 0|1 — المحفوظ يعلو الافتراض. */
    public function notify_matrix($user_id)
    {
        $this->ensure_schema();

        $matrix   = $this->notify_defaults();
        $channels = array_keys($this->notify_channels());

        foreach (array_keys($this->notify_types()) as $type) {
            if (!isset($matrix[$type])) $matrix[$type] = array();
            foreach ($channels as $ch) {
                if (!isset($matrix[$type][$ch])) $matrix[$type][$ch] = 0;
            }
        }

        $rows = $this->db->where('user_id', (int) $user_id)->get('tq_prefs_notify')->result_array();
        foreach ($rows as $r) {
            if (isset($matrix[$r['notify_type']][$r['channel']])) {
                $matrix[$r['notify_type']][$r['channel']] = (int) $r['enabled'];
            }
        }
        return $matrix;
    }

    /**
     * هل يسمح المستخدم بهذا التنبيه على هذه القناة؟
     *
     * هذه هي البوّابة التي يجب أن يمرّ بها كل مرسِل (المُنشئ في
     * `notifications` ومرسِل البريد في `Email_model`) وإلّا بقي المفتاح
     * تفضيلًا محفوظًا لا أثر له. النوع المجهول يُسمح به — فالإعداد يمنع
     * ما عُرض على صاحبه، لا ما لم يُعرض عليه قطّ.
     */
    public function allows($user_id, $notify_type, $channel)
    {
        $types = $this->notify_types();
        if (!isset($types[$notify_type])) return true;

        $matrix = $this->notify_matrix($user_id);
        if (!isset($matrix[$notify_type][$channel])) return true;

        return (bool) $matrix[$notify_type][$channel];
    }

    /**
     * هل نحن داخل ساعات صمت المستخدم الآن؟ يُستعمل مع allows() عند الإرسال.
     * النافذة قد تعبر منتصف الليل، فمن 22 إلى 7 نافذة واحدة لا نافذتان.
     */
    public function in_quiet_hours($user_id, $hour = null)
    {
        $p = $this->prefs($user_id);
        if (empty($p['quiet_on'])) return false;

        if ($hour === null) $hour = (int) date('G');
        $from = (int) $p['quiet_from'];
        $to   = (int) $p['quiet_to'];
        if ($from === $to) return false;

        return ($from < $to) ? ($hour >= $from && $hour < $to)
                             : ($hour >= $from || $hour < $to);
    }

    /* ================================================================
       الكتابة — كل دالّة تُعيد ok/errors/message
       ================================================================ */

    /**
     * موزّع واحد لكل النماذج، فيبقى المتحكّم سطورًا معدودة والقرار هنا.
     * @return array('ok'=>bool,'message'=>string,'errors'=>array,'section'=>string)
     */
    public function handle($user_id, $action)
    {
        switch ($action) {
            case 'profile':  return $this->save_profile($user_id);
            case 'password': return $this->save_password($user_id);
            case 'alerts':   return $this->save_alerts($user_id);
            case 'prefs':    return $this->save_prefs($user_id);
        }
        return $this->fail('طلب غير معروف.', 'profile');
    }

    private function ok($message, $section)
    {
        return array('ok' => true, 'message' => $message, 'errors' => array(), 'section' => $section);
    }

    private function fail($errors, $section)
    {
        if (!is_array($errors)) $errors = array($errors);
        return array('ok' => false, 'message' => '', 'errors' => $errors, 'section' => $section);
    }

    /* ---- الملف الشخصي ---------------------------------------------- */

    public function save_profile($user_id)
    {
        $user_id = (int) $user_id;
        $in      = $this->input;

        $first = trim((string) $in->post('first_name', true));
        $last  = trim((string) $in->post('last_name', true));
        $email = trim((string) $in->post('email', true));
        $phone = trim((string) $in->post('phone', true));

        $errors = array();
        if ($first === '')                          $errors[] = 'اكتب اسمك الأول.';
        if (mb_strlen($first) > 120)                $errors[] = 'الاسم الأول أطول من المسموح.';
        if (mb_strlen($last) > 120)                 $errors[] = 'الاسم الأخير أطول من المسموح.';
        if ($email === '')                          $errors[] = 'اكتب بريدك الإلكتروني.';
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'صيغة البريد غير صحيحة — مثال: name@example.com';
        elseif (mb_strlen($email) > 50)             $errors[] = 'البريد أطول من الحقل المتاح (50 حرفًا).';
        if ($phone !== '' && !preg_match('/^[0-9+()\-\s]{6,25}$/u', $phone)) {
            $errors[] = 'رقم الجوّال يقبل الأرقام و + و - والمسافات فقط.';
        }

        if (!$errors) {
            $taken = $this->db->where('email', $email)->where('id !=', $user_id)
                              ->count_all_results('users');
            if ($taken > 0) $errors[] = 'هذا البريد مسجَّل لحساب آخر — اختر بريدًا غيره.';
        }

        $image_code = null;
        if (!$errors && !empty($_FILES['user_image']['name'])) {
            $img = $this->store_image($user_id);
            if (!$img['ok']) $errors[] = $img['error'];
            else             $image_code = $img['code'];
        }

        if ($errors) return $this->fail($errors, 'profile');

        $data = array(
            'first_name'    => $first,
            'last_name'     => $last,
            'email'         => $email,
            'phone'         => $phone,
            'last_modified' => time(),
        );
        if ($image_code !== null) $data['image'] = $image_code;

        $this->db->where('id', $user_id)->update('users', $data);

        return $this->ok('حُفظت بيانات ملفك.', 'profile');
    }

    /**
     * صورة الحساب. تُفحص كصورة حقيقية لا بامتدادها، وتُعاد ترميزًا إلى JPEG
     * فلا يمرّ ملفّ يحمل شفرة داخل ترويسة صورة. والاسم رمز عشوائي كما يفعل
     * باقي السكربت (uploads/user_image/<code>.jpg).
     */
    private function store_image($user_id)
    {
        $f = $_FILES['user_image'];

        if (!empty($f['error']) && $f['error'] !== UPLOAD_ERR_OK) {
            return array('ok' => false, 'error' => 'تعذّر رفع الصورة — أعد المحاولة.');
        }
        if ($f['size'] > self::IMAGE_MAX_BYTES) {
            return array('ok' => false, 'error' => 'الصورة أكبر من 2 ميجابايت — اختر صورة أصغر.');
        }
        if (!is_uploaded_file($f['tmp_name'])) {
            return array('ok' => false, 'error' => 'ملفّ الصورة غير صالح.');
        }

        $info = @getimagesize($f['tmp_name']);
        $allowed = array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP);
        if (!$info || !in_array($info[2], $allowed, true)) {
            return array('ok' => false, 'error' => 'الصورة يجب أن تكون JPG أو PNG أو WebP.');
        }

        $dir = rtrim(FCPATH, '/') . '/uploads/user_image/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        $code = md5($user_id . '-' . microtime(true) . '-' . mt_rand());
        $dest = $dir . $code . '.jpg';

        $made = false;
        if (function_exists('imagecreatefromstring') && function_exists('imagejpeg')) {
            $raw = @file_get_contents($f['tmp_name']);
            $im  = $raw ? @imagecreatefromstring($raw) : false;
            if ($im) {
                $w = imagesx($im); $h = imagesy($im);
                $flat = imagecreatetruecolor($w, $h);
                $white = imagecolorallocate($flat, 255, 255, 255);
                imagefilledrectangle($flat, 0, 0, $w, $h, $white);
                imagecopy($flat, $im, 0, 0, 0, 0, $w, $h);
                $made = @imagejpeg($flat, $dest, 88);
                imagedestroy($im);
                imagedestroy($flat);
            }
        }
        if (!$made && !@move_uploaded_file($f['tmp_name'], $dest)) {
            return array('ok' => false, 'error' => 'تعذّر حفظ الصورة على الخادم.');
        }
        @chmod($dest, 0644);

        // الصورة القديمة تُحذف بنسختيها حتى لا تبقى نسخة مصغّرة لصورة استُبدلت.
        $old = (string) $this->db->select('image')->where('id', (int) $user_id)
                                 ->get('users')->row('image');
        if ($old !== '' && $old !== $code) {
            @unlink($dir . $old . '.jpg');
            @unlink($dir . 'optimized/' . $old . '.jpg');
        }

        return array('ok' => true, 'code' => $code);
    }

    /* ---- كلمة المرور ------------------------------------------------ */

    public function save_password($user_id)
    {
        $user_id = (int) $user_id;
        $cur     = (string) $this->input->post('current_password');
        $new     = (string) $this->input->post('new_password');
        $again   = (string) $this->input->post('confirm_password');

        $errors = array();
        if ($cur === '')                 $errors[] = 'اكتب كلمة المرور الحالية.';
        if (mb_strlen($new) < 8)         $errors[] = 'اجعل كلمة المرور الجديدة ثمانية محارف فأكثر.';
        if ($new !== $again)             $errors[] = 'الحقلان لا يتطابقان — أعد كتابة التأكيد.';
        if ($new !== '' && $new === $cur) $errors[] = 'الجديدة مطابقة للحالية — اختر غيرها.';

        /* التحقّق يمرّ بدالّة المنصّة لا بمقارنة sha1 مباشرة: الدخول يُرقّي
           كل تلبيدة قديمة إلى password_hash عند أوّل نجاح، فمقارنة sha1
           الصريحة تفشل لكل من سجّل دخوله مرّة — ويقال له إن كلمته خطأ وهي
           صحيحة. والكتابة بالتلبيدة الحديثة نفسها لا بالقديمة. */
        if (!$errors) {
            $row = $this->db->select('password')->where('id', $user_id)->get('users')->row_array();
            $good = $row && (function_exists('tq_password_matches')
                ? tq_password_matches($cur, $row['password'])
                : hash_equals((string) $row['password'], sha1($cur)));
            if (!$good) $errors[] = 'كلمة المرور الحالية غير صحيحة.';
        }
        if ($errors) return $this->fail($errors, 'security');

        $this->db->where('id', $user_id)->update('users', array(
            'password'      => function_exists('tq_password_hash') ? tq_password_hash($new) : sha1($new),
            'last_modified' => time(),
        ));

        return $this->ok('غُيّرت كلمة مرورك.', 'security');
    }

    /* ---- التنبيهات --------------------------------------------------- */

    public function save_alerts($user_id)
    {
        $this->ensure_schema();

        $user_id  = (int) $user_id;
        $posted   = $this->input->post('notify');
        if (!is_array($posted)) $posted = array();
        $now      = time();
        $channels = array_keys($this->notify_channels());

        foreach (array_keys($this->notify_types()) as $type) {
            foreach ($channels as $ch) {
                $on = !empty($posted[$type][$ch]) ? 1 : 0;
                $this->db->replace('tq_prefs_notify', array(
                    'user_id'     => $user_id,
                    'notify_type' => $type,
                    'channel'     => $ch,
                    'enabled'     => $on,
                    'updated_at'  => $now,
                ));
            }
        }

        $quiet_on   = $this->input->post('quiet_on') ? 1 : 0;
        $quiet_from = (int) $this->input->post('quiet_from');
        $quiet_to   = (int) $this->input->post('quiet_to');
        if ($quiet_from < 0 || $quiet_from > 23) $quiet_from = 22;
        if ($quiet_to   < 0 || $quiet_to   > 23) $quiet_to   = 7;

        $this->upsert_prefs($user_id, array(
            'quiet_on'   => $quiet_on,
            'quiet_from' => $quiet_from,
            'quiet_to'   => $quiet_to,
        ));

        return $this->ok('حُفظت تفضيلات تنبيهاتك.', 'alerts');
    }

    /* ---- التفضيلات العامة -------------------------------------------- */

    public function save_prefs($user_id)
    {
        $this->ensure_schema();

        $user_id = (int) $user_id;
        /* الوضع الليليّ أُزيل — الوجه واحد فاتح. يُثبَّت ولا يُقرأ من المُدخَل. */
        $theme   = 'auto';
        $lang    = (string) $this->input->post('language', true);

        $langs = $this->languages();
        if (!isset($this->themes()[$theme])) $theme = 'auto';
        if (!isset($langs[$lang]))           return $this->fail('لغة غير متاحة.', 'prefs');

        $this->upsert_prefs($user_id, array('theme' => $theme, 'language' => $lang));

        // اللغة إعداد يسري فورًا: الجلسة هي ما يقرؤه get_phrase واشتقاق dir.
        $this->session->set_userdata('language', $lang);

        return $this->ok('حُفظت تفضيلاتك.', 'prefs');
    }

    /** كتابة جزئية في صفّ التفضيلات — تُنشئه إن لم يكن. */
    private function upsert_prefs($user_id, $data)
    {
        $this->ensure_schema();
        $user_id = (int) $user_id;
        $data['updated_at'] = time();

        $exists = $this->db->where('user_id', $user_id)->count_all_results('tq_prefs_user');
        if ($exists) {
            $this->db->where('user_id', $user_id)->update('tq_prefs_user', $data);
        } else {
            $base = array(
                'user_id'    => $user_id,
                'theme'      => 'auto',
                'language'   => function_exists('get_settings') ? (get_settings('language') ?: 'arabic') : 'arabic',
                'quiet_on'   => 0,
                'quiet_from' => 22,
                'quiet_to'   => 7,
            );
            $this->db->insert('tq_prefs_user', array_merge($base, $data));
        }
    }
}
