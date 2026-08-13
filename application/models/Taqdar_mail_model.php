<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * البريد الصادر — **الموضع الوحيد الذي يرسل رسالة**.
 *
 * كان الإرسال في ثلاثة مواضع مستقلة، ولكل منها نسخته من الضبط ومن
 * معالجة الفشل:
 *
 *   `Email_model::send_smtp_mail()`      · خمسة وعشرون مستدعيا
 *   `Taqdar_events_model::maybe_email()` · مسار الكرون
 *   `Taqdar_admin::mail_test()`          · شاشة الفحص
 *
 * وثلاثتها تقرأ `settings` بأسماء المفاتيح نفسها، ثم تفترق في أمر واحد
 * هو الأهم: **ماذا يحدث حين لا يكون البريد مضبوطا؟**
 *
 * `send_smtp_mail` لم تكن تسأل. تنادي `$this->email->send()` بمضيف فارغ
 * ومستخدم فارغ، فتحاول مكتبة CI الاتصال بـ`""` على المنفذ `""` وتنتظر
 * إلى `smtp_timeout`. وأثر ذلك ليس رسالة لا تصل — بل:
 *
 *   · **إنشاء الحساب يتوقف ثلاثين ثانية** ثم يكمل، لأن `signup_mail()`
 *     تنادى في مسار التسجيل نفسه لا في مهمة خلفية.
 *   · وفي البيئة التي تطبع التحذيرات يطبع `fsockopen(): unable to
 *     connect` **داخل الصفحة** — وأحيانا داخل رد JSON فيفسده.
 *   · وردها `false` يفسر في مواضع بأنه فشل العملية كلها لا فشل البريد.
 *
 * فالقاعدة هنا صريحة: **بريد غير مضبوط = لا محاولة ولا خطأ.** ترد
 * `false` بهدوء ويكتب سطر في السجل، ويمضي ما استدعاها. الإشعار داخل
 * المنصة (`notifications`) يكتب دائما بمعزل عن هذا، فلا يضيع الخبر.
 *
 * والضبط يقرأ من `settings` لا من ملف: مفاتيح البريد تدار من
 * `taqdar_admin/mail` كما تدار مفاتيح بوابات الدفع.
 */
class Taqdar_mail_model extends CI_Model
{
    /** يقرأ مرة واحدة في الطلب: `get_settings` تستعلم في كل نداء. */
    private static $cfg = null;

    /* =====================================================================
       الضبط
       ===================================================================== */

    public function config()
    {
        if (self::$cfg !== null) {
            return self::$cfg;
        }

        self::$cfg = array(
            'host'   => trim((string) get_settings('smtp_host')),
            'port'   => (int) get_settings('smtp_port'),
            'crypto' => trim((string) get_settings('smtp_crypto')),
            'user'   => trim((string) get_settings('smtp_user')),
            'pass'   => (string) get_settings('smtp_pass'),
            'from'   => trim((string) get_settings('smtp_from_email')),
            'name'   => trim((string) get_settings('system_name')) ?: 'تقدر',
        );

        /* المرسل الظاهر يسقط إلى اسم المستخدم: خادم يقبل الاعتماد ثم يرفض
           `From` فارغا يفشل بسبب لا يفهمه من ضبطه. */
        if (self::$cfg['from'] === '') {
            self::$cfg['from'] = self::$cfg['user'];
        }
        if (self::$cfg['port'] <= 0) {
            self::$cfg['port'] = (self::$cfg['crypto'] === 'ssl') ? 465 : 587;
        }

        return self::$cfg;
    }

    /** ينسى المحفوظ — تنادى بعد الحفظ في شاشة الإعدادات. */
    public function forget()
    {
        self::$cfg = null;
    }

    /**
     * هل يمكن الإرسال فعلا؟
     *
     * أربعة مفاتيح لا ثلاثة: خادم ومستخدم وكلمة مرور ومرسل. ووجود
     * `smtp_host` وحده — وهو مملوء في هذه القاعدة بـ`smtp.gmail.com`
     * منذ التنصيب — لا يعني شيئا: بلا كلمة المرور يرفض Gmail الاعتماد.
     */
    public function configured()
    {
        $c = $this->config();
        return $c['host'] !== '' && $c['user'] !== '' && $c['pass'] !== '' && $c['from'] !== '';
    }

    /** ما ينقص، بعبارة تقرأ في الشاشة لا في السجل. */
    public function missing()
    {
        $c   = $this->config();
        $out = array();
        if ($c['host'] === '') $out[] = 'خادم البريد';
        if ($c['user'] === '') $out[] = 'اسم المستخدم';
        if ($c['pass'] === '') $out[] = 'كلمة المرور';
        if ($c['from'] === '') $out[] = 'المرسل الظاهر';
        return $out;
    }

    /* =====================================================================
       الإرسال
       ===================================================================== */

    /**
     * يرسل رسالة واحدة.
     *
     * @param string|array $to      مستلم أو أكثر. أكثر من واحد يرسل بنسخة
     *                              مخفية — قائمة عناوين ظاهرة تسرب بريد
     *                              كل مستلم إلى الباقين.
     * @param string       $subject
     * @param string       $html    جسم الرسالة (يلف بالقالب ما لم يمنع).
     * @param array        $opts    raw:true  — لا يلف بالقالب
     *                              reply_to  — عنوان الرد
     *                              debug:true — يعيد تشخيص الخادم عند الفشل
     *
     * @return bool نجح الإرسال. و`false` تعني «لم يرسل» لا «وقع خطأ»:
     *              البريد غير المضبوط يرد `false` بلا استثناء ولا مخرج.
     */
    public function send($to, $subject, $html, $opts = array())
    {
        $to = array_values(array_filter(array_map('trim', (array) $to), function ($a) {
            return $a !== '' && filter_var($a, FILTER_VALIDATE_EMAIL);
        }));

        if (!$to) {
            return false;
        }
        if (!$this->configured()) {
            /* السبب يكتب مرة لكل طلب لا لكل رسالة: نشرة إلى ألفي مستلم
               كانت تملأ السجل بألفي سطر متطابق. */
            static $said = false;
            if (!$said) {
                log_message('info', 'tq_mail: البريد الصادر غير مضبوط — لم ترسل الرسائل. '
                    . 'الناقص: ' . implode('، ', $this->missing()));
                $said = true;
            }
            return false;
        }

        $c = $this->config();

        try {
            $this->load->library('email');
            $this->email->clear(true);
            $this->email->initialize(array(
                'protocol'     => 'smtp',
                'smtp_host'    => $c['host'],
                'smtp_port'    => $c['port'],
                'smtp_user'    => $c['user'],
                'smtp_pass'    => $c['pass'],
                'smtp_crypto'  => $c['crypto'],
                'mailtype'     => 'html',
                'charset'      => 'utf-8',
                'newline'      => "\r\n",
                'crlf'         => "\r\n",
                /* عشرون ثانية لا ثلاثون: هذا النداء يقع أحيانا في مسار
                   طلب يراه مستخدم — وثلاثون ثانية من الانتظار الأبيض
                   تقرأ عطلا في الموقع لا تأخرا في البريد. */
                'smtp_timeout' => 20,
                'wordwrap'     => false,
            ));

            $this->email->from($c['from'], $c['name']);
            if (!empty($opts['reply_to']) && filter_var($opts['reply_to'], FILTER_VALIDATE_EMAIL)) {
                $this->email->reply_to($opts['reply_to']);
            }

            if (count($to) === 1) {
                $this->email->to($to[0]);
            } else {
                /* المستلم الظاهر هو المرسل نفسه: بعض الخوادم ترفض رسالة
                   بلا `To`، والبقية في نسخة مخفية. */
                $this->email->to($c['from']);
                $this->email->bcc($to);
            }

            $this->email->subject($subject);
            $this->email->message(empty($opts['raw']) ? $this->wrap($subject, $html) : $html);

            $ok = (bool) $this->email->send(false);

            if (!$ok) {
                $why = $this->email->print_debugger(array('headers'));
                log_message('error', 'tq_mail: تعذر الإرسال إلى ' . implode(', ', $to) . ' — ' . $why);
                if (!empty($opts['debug'])) {
                    $this->last_error = $why;
                }
            }
            return $ok;

        } catch (Throwable $e) {
            /* لا يسمح لفشل بريد أن يسقط تسجيلا ولا حفظا ولا مهمة دورية:
               الرسالة تابع للعملية لا العملية نفسها. */
            log_message('error', 'tq_mail: استثناء أثناء الإرسال — ' . $e->getMessage());
            $this->last_error = $e->getMessage();
            return false;
        }
    }

    /** تشخيص آخر فشل — تقرؤه شاشة الفحص وحدها. */
    public $last_error = '';

    /* =====================================================================
       القالب
       ===================================================================== */

    /**
     * يلف النص بقالب بهوية المنصة.
     *
     * كل التنسيق داخل السمات لا في `<style>`: عملاء البريد — وGmail
     * أولهم — يحذفون الترويسة كلها، فورقة أنماط خارجية أو داخلية تسقط
     * وتصل الرسالة عارية.
     *
     * والاتجاه `rtl` على الجذر وعلى كل خلية: Outlook لا يرث الاتجاه.
     */
    public function wrap($title, $body_html, $cta = null)
    {
        $name = html_escape($this->config()['name']);
        $site = rtrim(base_url(), '/');

        $button = '';
        if (is_array($cta) && !empty($cta['href']) && !empty($cta['label'])) {
            $button =
                '<tr><td dir="rtl" align="center" style="padding:8px 28px 24px">'
              . '<a href="' . html_escape($cta['href']) . '"'
              . ' style="display:inline-block;padding:12px 28px;border-radius:12px;'
              . 'background:#023331;color:#ffffff;font:700 15px Tahoma,Arial,sans-serif;'
              . 'text-decoration:none">' . html_escape($cta['label']) . '</a>'
              . '</td></tr>';
        }

        return
        '<div dir="rtl" style="margin:0;padding:24px 12px;background:#F5F7F8;'
      . 'font-family:Tahoma,Arial,sans-serif;color:#1A2430">'
      . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center"'
      . ' style="width:100%;max-width:560px;background:#ffffff;border-radius:18px;'
      . 'border:1px solid #E6EAED;overflow:hidden">'

      . '<tr><td dir="rtl" style="padding:22px 28px;background:#023331;color:#ffffff">'
      . '<span style="font:700 19px Tahoma,Arial,sans-serif">' . $name . '</span>'
      . '</td></tr>'

      . '<tr><td dir="rtl" style="padding:26px 28px 6px">'
      . '<h1 style="margin:0 0 12px;font:700 20px Tahoma,Arial,sans-serif;color:#023331">'
      . html_escape($title) . '</h1>'
      . '<div style="font:400 15px/1.9 Tahoma,Arial,sans-serif;color:#1A2430">'
      . $body_html . '</div>'
      . '</td></tr>'

      . $button

      . '<tr><td dir="rtl" style="padding:16px 28px 22px;border-top:1px solid #E6EAED;'
      . 'font:400 12px/1.8 Tahoma,Arial,sans-serif;color:#5A6672">'
      . 'هذه رسالة آلية من ' . $name . '. '
      . '<a href="' . html_escape($site) . '" style="color:#0C786C">' . html_escape($site) . '</a>'
      . '</td></tr>'

      . '</table></div>';
    }

    /**
     * يرسل رسالة من فقرات — أكثر ما تحتاجه المنصة.
     *
     * @param array $lines فقرات نصية (تهرب هنا، فلا يمرر HTML من مستخدم).
     */
    public function send_lines($to, $subject, $lines, $cta = null, $opts = array())
    {
        /* الفحص أولا: بناء القالب لمن لا بريد له عمل بلا مقابل، وهو
           يتكرر في نشرة إلى ألفي مستلم. */
        if (!$this->configured()) {
            return $this->send($to, $subject, '');    // ترد false وتكتب سطر السجل مرة
        }

        $body = '';
        foreach ((array) $lines as $line) {
            $line = trim((string) $line);
            if ($line === '') continue;
            $body .= '<p style="margin:0 0 12px">' . html_escape($line) . '</p>';
        }

        /* القالب يبنى هنا، فيمرر `raw` لئلا يلف مرتين. */
        $opts['raw'] = true;
        return $this->send($to, $subject, $this->wrap($subject, $body, $cta), $opts);
    }
}
