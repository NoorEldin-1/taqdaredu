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

        /* ------------------------------------------------------------------
           الهوية: من يوقع الرسالة، ومن تقول الرسالة إنها منه؟

           هذان سؤالان لا واحد، والخلط بينهما هو سبب وصول كل رسائل المنصة
           إلى مجلد المهملات. الخادم الذي تعتمد عليه يوقع باسم **نطاقه هو**
           (`DKIM`) ويرسل من عناوينه هو (`SPF`)، فإن كتبت في `From` نطاقا
           آخر لم يأذن لذلك الخادم، رأى المستلم رسالة تدعي أنها من
           `taqdaredu.com` ولا `taqdaredu.com` يعرفها. و`DMARC` اسم هذا
           الفحص، ونتيجته `fail`، وأثرها المهملات.

           فيحسب هنا نطاقان: نطاق من نعتمد به (`auth`) ونطاق من ندعي أننا
           منه (`from`). ومطابقتهما هي `aligned`.

           وحين لا يكون اسم المستخدم بريدا أصلا — وهو حال خدمات الإرسال
           مثل SES و Mailgun، فمستخدمها رمز لا عنوان — فلا حكم: النطاق
           موثق عندهم بطريقة أخرى، والقرار لمن ضبطه.
           ------------------------------------------------------------------ */
        self::$cfg['auth_domain'] = self::domain_of(self::$cfg['user']);
        self::$cfg['from_domain'] = self::domain_of(self::$cfg['from']);

        self::$cfg['judgeable'] = self::$cfg['auth_domain'] !== '';
        self::$cfg['aligned']   = !self::$cfg['judgeable']
            || self::same_org(self::$cfg['auth_domain'], self::$cfg['from_domain']);

        /* **العنوان الذي يوضع فعلا في `From`.** غير المطابق يستبدل بعنوان
           الاعتماد ويوضع المقصود في `Reply-To`: رسالة تصل باسم صندوق آخر
           والرد يذهب حيث يجب، أهون من رسالة لا تصل. */
        self::$cfg['send_as']  = self::$cfg['aligned'] ? self::$cfg['from'] : self::$cfg['user'];
        self::$cfg['rewrites'] = !self::$cfg['aligned'];

        return self::$cfg;
    }

    /** الجزء الذي بعد `@`، صغيرا. وما ليس بريدا يرد فارغا. */
    private static function domain_of($addr)
    {
        $at = strrpos((string) $addr, '@');
        return ($at === false) ? '' : strtolower(trim(substr($addr, $at + 1)));
    }

    /**
     * نطاقان لمؤسسة واحدة؟
     *
     * التطابق التام، أو أن يكون أحدهما نطاقا فرعيا من الآخر
     * (`mail.taqdaredu.com` و `taqdaredu.com`) — وهي محاذاة مرنة يقبلها
     * `DMARC` افتراضيا.
     */
    private static function same_org($a, $b)
    {
        if ($a === '' || $b === '') return false;
        if ($a === $b)              return true;
        return substr($a, -strlen('.' . $b)) === '.' . $b
            || substr($b, -strlen('.' . $a)) === '.' . $a;
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
                /* **لا تعلن أن المرسل إطار PHP.** مكتبة CI تكتب اسمها في
                   `X-Mailer` و`User-Agent` في كل رسالة، فتخرج رسالة تأكيد
                   الحساب موقعة `CodeIgniter`. والمرشحات تقرأ هذه الترويسة:
                   خدمات البريد المعروفة تكتب اسم منتجها، ومن يكتب اسم إطار
                   عام يقع في خانة السكربتات المستضافة — وهي خانة يخرج منها
                   أكثر البريد المزعج. والاسم هنا اسم المنصة. */
                'useragent'    => $c['name'],
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

            /* `send_as` لا `from`: انظر شرح الهوية في `config()`. الاسم
               الظاهر يبقى «تقدر» في الحالين، فالمستلم يرى المرسل نفسه. */
            $this->email->from($c['send_as'], $c['name']);

            /* عنوان الرد: ما طلبه المستدعي، وإلا المرسل المقصود حين نكون
               قد بدلناه — فيرد المستلم على `info@` لا على صندوق الاعتماد. */
            $reply = '';
            if (!empty($opts['reply_to']) && filter_var($opts['reply_to'], FILTER_VALIDATE_EMAIL)) {
                $reply = $opts['reply_to'];
            } elseif ($c['rewrites'] && $c['from'] !== $c['send_as']) {
                $reply = $c['from'];
            }
            if ($reply !== '') {
                $this->email->reply_to($reply);
            }

            if ($c['rewrites']) {
                static $warned = false;
                if (!$warned) {
                    log_message('info', 'tq_mail: المرسل الظاهر (' . $c['from'] . ') لا يطابق نطاق '
                        . 'الاعتماد (' . $c['auth_domain'] . ') — أرسلت باسم ' . $c['send_as']
                        . ' لئلا تسقط في المهملات. راجع شاشة البريد الصادر.');
                    $warned = true;
                }
            }

            if (count($to) === 1) {
                $this->email->to($to[0]);
            } else {
                /* المستلم الظاهر هو المرسل نفسه: بعض الخوادم ترفض رسالة
                   بلا `To`، والبقية في نسخة مخفية. */
                $this->email->to($c['send_as']);
                $this->email->bcc($to);
            }

            $body = empty($opts['raw']) ? $this->wrap($subject, $html) : $html;

            $this->email->subject($subject);
            $this->email->message($body);

            /* **نسخة نصية صالحة إلى جانب المزخرفة.**

               مكتبة CI ترسل `multipart/alternative` من تلقائها، فالنسخة
               النصية موجودة أصلا — ولكنها تصنع بـ`strip_tags` وحدها،
               فتخرج «افتح المنصة» بلا منصة تفتح: كل رابط يضيع. ونسخة نصية
               تذكر دعوة ولا تذكر وجهتها أسوأ من لا شيء عند المرشح وعند من
               يقرؤها (ساعة · قارئ شاشة · عميل نصي).

               فتكتب هنا بدلها نسخة تحفظ العناوين. */
            $this->email->set_alt_message($this->text_of($body));

            /* **ترويسات تقول ما هذه الرسالة.**

               `Auto-Submitted` تعلن أنها آلية فلا يرد عليها برد آلي فتدور
               حلقة، و`List-Unsubscribe` تعطي المستلم زر «إلغاء الاشتراك»
               داخل Gmail نفسه — ووجوده يخفض بلاغات «هذه مزعجة» التي هي
               أسرع طريق إلى المهملات لكل رسائل النطاق لا هذه وحدها.

               والوجهة `mailto:` لا رابط نقرة واحدة: المنصة لا تملك مسار
               إلغاء اشتراك بعد، واختراع رابط لا يعمل أسوأ من عنوان يصل. */
            $this->email->set_header('Auto-Submitted', 'auto-generated');

            if (!empty($opts['bulk'])) {
                $box = $c['from'] ?: $c['send_as'];
                $this->email->set_header('List-Unsubscribe',
                    '<mailto:' . $box . '?subject=unsubscribe>');
                $this->email->set_header('Precedence', 'bulk');
            }

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

        /* **أصول الرسالة تجلب من عنوان عام لا من `base_url()`.**

           الروابط تتبع `base_url()` عمدا — رسالة فحص من المحلي يجب أن
           تفتح المحلي. أما الصورة فيجلبها **خادم Gmail** لا المتصفح،
           وهو لا يعرف `localhost`، فتصل الترويسة بشعار مكسور في كل فحص
           محلي. ونطاق المنصة مكتوب هنا لأن الشعار أصل عام لا سرا. */
        $assets = $site;
        if (preg_match('~^https?://(localhost|127\.0\.0\.1|\[::1\])~i', $assets)) {
            $assets = 'https://taqdaredu.com';
        }

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

      /* الشعار صورة، والنص `alt` يحمل الاسم بالنمط نفسه: Gmail يحجب
         الصور افتراضيا لمن لم يراسله المستلم من قبل، فترويسة بلا بديل
         تصل خالية. والمقاسان مكتوبان في السمة وفي النمط معا — Outlook
         يتجاهل النمط. */
      . '<tr><td dir="rtl" style="padding:22px 28px;background:#023331;color:#ffffff">'
      . '<a href="' . html_escape($site) . '" style="text-decoration:none">'
      . '<img src="' . html_escape($assets) . '/assets/taqdar/brand/wordmark_light.png"'
      . ' width="69" height="40" alt="' . $name . '"'
      . ' style="display:block;border:0;width:69px;height:40px;'
      . 'font:700 19px Tahoma,Arial,sans-serif;color:#ffffff;text-decoration:none">'
      . '</a>'
      . '</td></tr>'

      . '<tr><td dir="rtl" style="padding:26px 28px 6px">'
      . '<h1 style="margin:0 0 12px;font:700 20px Tahoma,Arial,sans-serif;color:#023331">'
      . html_escape($title) . '</h1>'
      . '<div style="font:400 15px/1.9 Tahoma,Arial,sans-serif;color:#1A2430">'
      . $body_html . '</div>'
      . '</td></tr>'

      . $button

      /* **TQ-SPAM — التعليمة توضع حيث يقرؤها من يحتاجها.**

         كل شاشة في المنصة تقول لمن لم تصله الرسالة: ابحث في «الرسائل غير
         المرغوبة» ثم اضغط «ليس بريدا غير مرغوب». وذلك نصف الحل: من فتح
         المجلد ووجد الرسالة قرأ ما فيها ومضى، فذهبت الرسالة التالية إلى
         المجلد نفسه. **والسطر هنا يقرؤه وهو داخل المجلد**، فيقول له
         الضغطة في موضع الضغطة.

         وهو لكل رسالة لا لرسالة: القالب واحد، فما يضاف هنا يخرج مع رمز
         التأكيد والفاتورة والتقرير الأسبوعي جميعا.

         وبأسلوب السطور لا بلوح ملون: لوح تحذير في كل رسالة يقرأ إلحاحا،
         والمرشحات نفسها تنظر إليه بريبة. */
      . '<tr><td dir="rtl" style="padding:14px 28px 0">'
      . '<table role="presentation" cellpadding="0" cellspacing="0" border="0"'
      . ' style="width:100%;background:#FBF3E4;border-radius:12px">'
      . '<tr><td dir="rtl" style="padding:12px 14px;'
      . 'font:400 12px/1.85 Tahoma,Arial,sans-serif;color:#5A4A2E">'
      . '<b style="color:#8A5D18">وصلتك هذه الرسالة في «الرسائل غير المرغوبة»؟</b><br>'
      . 'اضغط <b>«ليس بريدا غير مرغوب»</b> (Not spam) وأضف '
      . '<span style="direction:ltr;unicode-bidi:isolate">'
      . html_escape($this->config()['from'] ?: $this->config()['send_as'])
      . '</span> إلى جهات اتصالك — فتصلك بقية رسائلنا في صندوق الوارد.'
      . '</td></tr></table>'
      . '</td></tr>'

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

    /**
     * النسخة النصية من جسم الرسالة.
     *
     * لا تكفي `strip_tags`: تبتلع الروابط فتصير جملة «افتح المنصة» بلا
     * منصة تفتح، وتلصق الفقرات ببعضها سطرا واحدا. فالروابط تكتب بعناوينها،
     * ومغلقات الفقرات تصير أسطرا.
     */
    private function text_of($html)
    {
        $t = preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', '', (string) $html);

        $t = preg_replace_callback(
            '~<a\b[^>]*href=(["\'])(.*?)\1[^>]*>(.*?)</a>~is',
            function ($m) {
                $url   = trim(html_entity_decode($m[2], ENT_QUOTES, 'UTF-8'));
                $label = trim(preg_replace('~\s+~u', ' ', strip_tags($m[3])));
                if ($label === '' || $label === $url) return $url;
                return $label . ' — ' . $url;
            },
            $t
        );

        $t = preg_replace('~<br\s*/?>~i', "\n", $t);
        $t = preg_replace('~</(p|div|tr|li|h[1-6]|table)\s*>~i', "\n", $t);

        $t = html_entity_decode(strip_tags($t), ENT_QUOTES, 'UTF-8');

        $t = preg_replace('~[ \t\x{00A0}]+~u', ' ', $t);
        $t = preg_replace('~ *\n *~u', "\n", $t);
        $t = preg_replace('~\n{3,}~u', "\n\n", $t);

        return trim($t);
    }

    /* =====================================================================
       الفحص — لماذا تذهب الرسائل إلى المهملات؟
       =====================================================================

       رسالة تصل ورسالة تصل إلى **الوارد** ليستا شيئا واحدا، وزر «أرسل
       رسالة فحص» يجيب عن الأول وحده: خادم قبل الرسالة، وهذا كل ما يعرفه.
       أما القرار الثاني فيتخذه المستلم بعد فحص ثلاثة سجلات في نطاقك،
       ولا يخبرك بنتيجته — يضعها في المهملات بصمت.

       فهذه الدالة تقرأ تلك السجلات من DNS مباشرة وتقارنها بالضبط المحفوظ،
       فيصير السؤال «هل ستصل؟» مجابا **قبل** الإرسال لا بعد شكوى مستخدم.
       ===================================================================== */

    /**
     * تشخيص كامل: كل فحص بحاله وسببه وإصلاحه.
     *
     * @return array بنود، لكل بند: key · label · state (ok|warn|fail|unknown)
     *               · says (ما الحال) · fix (ما العمل) · record (سجل ينسخ)
     */
    public function diagnose()
    {
        $c   = $this->config();
        $out = array();

        /* ١ — الضبط أصلا */
        $out[] = $this->configured()
            ? $this->check('config', 'بيانات الخادم', 'ok', 'الحقول الأربعة مملوءة.')
            : $this->check('config', 'بيانات الخادم', 'fail',
                'ناقص: ' . implode('، ', $this->missing()) . '.',
                'أكمل الحقول في البطاقة المجاورة. لا يرسل شيء قبلها.');

        if ($c['from_domain'] === '') {
            return $out;   /* بلا نطاق مرسل لا معنى لبقية الفحوص */
        }

        $host_key = $this->provider_of($c['host']);

        /* ٢ — المحاذاة: أهم بند في الشاشة كلها */
        if (!$c['judgeable']) {
            $out[] = $this->check('align', 'مطابقة الهوية', 'unknown',
                'اسم المستخدم ليس بريدا — فالخادم خدمة إرسال توثق النطاق عندها.',
                'تأكد أن ' . $c['from_domain'] . ' موثق (verified) في لوحة مزود الإرسال.');
        } elseif ($c['aligned']) {
            $out[] = $this->check('align', 'مطابقة الهوية', 'ok',
                'المرسل الظاهر ونطاق الاعتماد كلاهما ' . $c['from_domain'] . '.');
        } else {
            $out[] = $this->check('align', 'مطابقة الهوية', 'fail',
                'تعتمد بحساب على ' . $c['auth_domain'] . ' وتكتب في المرسل '
                . $c['from'] . '. النطاقان مختلفان، فالتوقيع يخرج باسم '
                . $c['auth_domain'] . ' والرسالة تدعي ' . $c['from_domain']
                . ' — وهذا بعينه ما يفشل فحص DMARC ويرسلها إلى المهملات.',
                'الحل الصحيح: اعتمد بصندوق نطاقك نفسه — اسم المستخدم '
                . $c['from'] . ' على خادم نطاقك (' . $this->suggest_host($c['from_domain'])
                . '). وحتى تفعل، ترسل المنصة باسم ' . $c['send_as']
                . ' ويوضع ' . $c['from'] . ' في «عنوان الرد» — تصل الرسالة، لكن '
                . 'باسم صندوق آخر.');
        }

        /* ٣ — SPF: هل يأذن نطاقك لهذا الخادم بالإرسال باسمه؟ */
        $spf = $this->spf_of($c['from_domain']);
        if ($spf === null) {
            $out[] = $this->check('spf', 'سجل SPF', 'unknown', 'تعذر سؤال DNS من هذا الخادم.');
        } elseif ($spf === '') {
            $out[] = $this->check('spf', 'سجل SPF', 'fail',
                'لا سجل SPF على ' . $c['from_domain'] . '.',
                'أضف سجل TXT على الجذر:',
                'v=spf1 include:' . $this->spf_include($host_key) . ' ~all');
        } else {
            $senders = $this->spf_senders($spf);
            if ($host_key !== '' && in_array($host_key, $senders, true)) {
                $out[] = $this->check('spf', 'سجل SPF', 'ok',
                    'السجل يأذن لـ' . $host_key . ' بالإرسال باسم ' . $c['from_domain'] . '.');
            } else {
                $out[] = $this->check('spf', 'سجل SPF', 'fail',
                    'السجل موجود ' . ($senders
                        ? 'ويأذن لـ' . implode('، ', $senders)
                        : 'ولا يأذن لأي مزود')
                    . ' — ولا يذكر ' . ($host_key ?: $c['host']) . ' الذي ترسل منه. '
                    . 'فرسائلك تصل من خادم لم يأذن له نطاقك، وهذا يفشل فحص SPF.',
                    'إما أن ترسل من الخادم المأذون له (وهو الأصح)، وإما أن تضيف '
                    . 'الخادم الحالي إلى السجل:',
                    $this->spf_merged($spf, $host_key));
            }
        }

        /* ٤ — DKIM: هل يوقع نطاقك؟

           تجرب محددات الخادم الحالي **ومحددات مزود بريد النطاق** معا: من
           يرسل عبر Gmail بنطاق مستضاف عند غيره، سؤاله عن محددات Google
           وحدها يرجع «لا مفتاح» — وهو جواب يخفي أن النطاق موقع أصلا وأن
           العطل في الخادم المختار لا في السجلات. */
        $dkim = $this->dkim_of($c['from_domain'],
                    array($host_key, $this->mx_provider($c['from_domain'])));
        if ($dkim === null) {
            $out[] = $this->check('dkim', 'توقيع DKIM', 'unknown', 'تعذر سؤال DNS من هذا الخادم.');
        } elseif ($dkim) {
            $out[] = $this->check('dkim', 'توقيع DKIM', 'ok',
                'منشور بالمحدد ' . implode('، ', $dkim) . '.');
        } else {
            $out[] = $this->check('dkim', 'توقيع DKIM', 'warn',
                'لم يعثر على مفتاح بالمحددات المعروفة لهذا المزود. قد يكون '
                . 'بمحدد آخر، وقد لا يكون منشورا أصلا.',
                'افتح لوحة النطاق عند مزود بريدك وفعل DKIM، ثم انشر السجلات '
                . 'التي يعطيك — وهي CNAME غالبا.');
        }

        /* ٥ — DMARC: السياسة، ومن يقرأ التقارير */
        $dmarc = $this->txt_of('_dmarc.' . $c['from_domain']);
        $rec   = '';
        if (is_array($dmarc)) {
            foreach ($dmarc as $t) {
                if (stripos($t, 'v=DMARC1') === 0) { $rec = $t; break; }
            }
        }
        if ($dmarc === null) {
            $out[] = $this->check('dmarc', 'سياسة DMARC', 'unknown', 'تعذر سؤال DNS من هذا الخادم.');
        } elseif ($rec === '') {
            $out[] = $this->check('dmarc', 'سياسة DMARC', 'warn',
                'لا سجل DMARC. بلا سياسة، كل مرشح يجتهد في رسائلك كما يشاء.',
                'أضف سجل TXT على ‎_dmarc.' . $c['from_domain'] . ' — ابدأ بـ p=none '
                . 'حتى تصلك التقارير، ثم شدد:',
                'v=DMARC1; p=none; rua=mailto:' . ($c['from'] ?: 'postmaster@' . $c['from_domain']) . '; fo=1');
        } elseif (stripos($rec, 'rua=') === false) {
            $out[] = $this->check('dmarc', 'سياسة DMARC', 'warn',
                'السجل موجود بلا عنوان تقارير (rua) — فأنت لا ترى من ينتحل نطاقك '
                . 'ولا كم رسالة تفشل.',
                'أضف rua ليصلك تقرير يومي:',
                rtrim($rec, '; ') . '; rua=mailto:' . ($c['from'] ?: 'postmaster@' . $c['from_domain']) . '; fo=1');
        } else {
            $out[] = $this->check('dmarc', 'سياسة DMARC', 'ok', 'منشور: ' . $rec);
        }

        /* ٦ — الروابط داخل الرسالة */
        $base = rtrim(base_url(), '/');
        $hostname = strtolower((string) parse_url($base, PHP_URL_HOST));
        $local = $hostname === '' || $hostname === 'localhost' || $hostname === '127.0.0.1'
              || substr($hostname, -6) === '.local' || filter_var($hostname, FILTER_VALIDATE_IP);

        if ($local) {
            $out[] = $this->check('links', 'روابط الرسائل', 'fail',
                'روابط الرسائل تشير إلى ' . $base . ' — عنوان لا يفتح عند أحد. '
                . 'ورابط كهذا وحده كاف ليصنف المرشح الرسالة مزعجة.',
                'هذا متوقع على جهازك. تحقق من الشاشة نفسها على الموقع المنشور، '
                . 'فهناك يقرأ base_url العنوان الحقيقي.');
        } elseif (stripos($base, 'https://') !== 0) {
            $out[] = $this->check('links', 'روابط الرسائل', 'warn',
                'الروابط تخرج بـ http لا https.',
                'اضبط base_url على https في config.php.');
        } else {
            $out[] = $this->check('links', 'روابط الرسائل', 'ok', 'تشير إلى ' . $base . '.');
        }

        return $out;
    }

    private function check($key, $label, $state, $says, $fix = '', $record = '')
    {
        return compact('key', 'label', 'state', 'says', 'fix', 'record');
    }

    /* ------------------------------- DNS ------------------------------- */

    /**
     * سجلات TXT لاسم.
     *
     * `null` تعني **تعذر السؤال** (الدالة معطلة أو الشبكة مغلقة)، و`array()`
     * الفارغة تعني **لا سجل**. والفرق بينهما هو الفرق بين «لا نعرف» و«ناقص»،
     * وخلطهما يجعل الشاشة تتهم إعدادا سليما.
     */
    private function txt_of($name)
    {
        if (!function_exists('dns_get_record')) {
            return null;
        }
        try {
            $recs = @dns_get_record($name, DNS_TXT);
        } catch (Throwable $e) {
            return null;
        }
        if ($recs === false) {
            return null;
        }

        $out = array();
        foreach ($recs as $r) {
            if (!empty($r['entries'])) {
                $out[] = implode('', $r['entries']);
            } elseif (isset($r['txt'])) {
                $out[] = $r['txt'];
            }
        }
        return $out;
    }

    /** سجل SPF وحده: `''` لا سجل، `null` تعذر الفحص. */
    private function spf_of($domain)
    {
        $txt = $this->txt_of($domain);
        if ($txt === null) return null;
        foreach ($txt as $t) {
            if (stripos($t, 'v=spf1') === 0) return $t;
        }
        return '';
    }

    /** المزودون المأذون لهم في سجل SPF — مطوية إلى مفاتيح كمفتاح الخادم. */
    private function spf_senders($spf)
    {
        $out = array();
        /* الفاصل `#` لا `~`: المؤهل `~` من مؤهلات SPF نفسها (`~all`) فيقع
           داخل صنف الحروف، ويغلق النمط قبل أوانه فلا يطابق شيء أبدا —
           وأثر ذلك سجل سليم يقرأ «لا يأذن لأحد». */
        foreach (preg_split('#\s+#', trim($spf)) as $tok) {
            if (preg_match('#^[+~?-]?(?:include|a|mx|exists|redirect|ptr)[:=](.+)$#i', $tok, $m)) {
                $k = $this->provider_of($m[1]);
                if ($k !== '' && !in_array($k, $out, true)) $out[] = $k;
            }
        }
        return $out;
    }

    /** السجل نفسه بعد إضافة المزود الحالي — ينسخ كما هو. */
    private function spf_merged($spf, $host_key)
    {
        $add = 'include:' . $this->spf_include($host_key);
        if (stripos($spf, $add) !== false) return $spf;

        $parts = preg_split('#\s+#', trim($spf));
        $tail  = array();
        foreach ($parts as $i => $p) {
            if (preg_match('#^[+~?-]?all$#i', $p)) {
                $tail = array_splice($parts, $i);
                break;
            }
        }
        return implode(' ', array_merge($parts, array($add), $tail ?: array('~all')));
    }

    /**
     * هل ينشر النطاق مفتاح DKIM؟
     *
     * تجرب المحددات المعروفة للمزود. و`CNAME` تحسب عثورا كـ`TXT`: مزودون
     * كثيرون — Hostinger منهم — ينشرون المفتاح عندهم ويطلبون منك CNAME
     * إليه، فسؤال TXT وحده يرجع فارغا على نطاق موقع تماما.
     */
    private function dkim_of($domain, $keys)
    {
        $map = array(
            'hostinger' => array('hostingermail-a', 'hostingermail-b', 'hostingermail-c'),
            'google'    => array('google'),
            'microsoft' => array('selector1', 'selector2'),
            'zoho'      => array('zoho', 'zmail'),
            'mailgun'   => array('mailo', 'smtp', 'k1'),
            'sendgrid'  => array('s1', 's2'),
            'amazonses' => array('ses'),
            'titan'     => array('titan1', 'titan2'),
        );

        $sel = array();
        foreach (array_unique(array_filter((array) $keys)) as $k) {
            if (isset($map[$k])) $sel = array_merge($sel, $map[$k]);
        }
        if (!$sel) {
            $sel = array('default', 'mail', 'dkim', 'k1', 'selector1', 's1');
        }
        $sel = array_values(array_unique($sel));

        $found   = array();
        $checked = false;

        foreach ($sel as $s) {
            $name = $s . '._domainkey.' . $domain;

            $txt = $this->txt_of($name);
            if ($txt === null) continue;
            $checked = true;
            if ($txt) { $found[] = $s; continue; }

            if (function_exists('dns_get_record')) {
                try {
                    $cn = @dns_get_record($name, DNS_CNAME);
                } catch (Throwable $e) {
                    $cn = false;
                }
                if (!empty($cn)) $found[] = $s;
            }
        }

        return $checked ? $found : null;
    }

    /* ---------------------------- المزودون ---------------------------- */

    /**
     * مفتاح المزود من أي اسم مضيف.
     *
     * `smtp.gmail.com` و `_spf.google.com` مؤسسة واحدة باسمين، و
     * `email-smtp.eu-central-1.amazonaws.com` لا يشبه `amazonses.com`.
     * فالمقارنة الحرفية بين اسمي مضيف تكذب في الحالين، والطي إلى مفتاح
     * واحد هو ما يجعل فحص SPF يقول الصدق.
     */
    private function provider_of($host)
    {
        $h = strtolower(trim((string) $host, ". \t"));
        if ($h === '') return '';

        $known = array(
            'hostinger'  => 'hostinger',
            'google'     => 'google',
            'gmail'      => 'google',
            'outlook'    => 'microsoft',
            'office365'  => 'microsoft',
            'protection.outlook' => 'microsoft',
            'amazonses'  => 'amazonses',
            'amazonaws'  => 'amazonses',
            'mailgun'    => 'mailgun',
            'sendgrid'   => 'sendgrid',
            'zoho'       => 'zoho',
            'mailchimp'  => 'mailchimp',
            'mandrill'   => 'mailchimp',
            'postmark'   => 'postmark',
            'brevo'      => 'brevo',
            'sendinblue' => 'brevo',
            'titan'      => 'titan',
            'yandex'     => 'yandex',
        );
        foreach ($known as $needle => $key) {
            if (strpos($h, $needle) !== false) return $key;
        }

        $labels = explode('.', $h);
        return (count($labels) >= 2)
            ? implode('.', array_slice($labels, -2))
            : $h;
    }

    /** ما يكتب بعد `include:` لمزود معروف. */
    private function spf_include($key)
    {
        $map = array(
            'hostinger' => '_spf.mail.hostinger.com',
            'google'    => '_spf.google.com',
            'microsoft' => 'spf.protection.outlook.com',
            'amazonses' => 'amazonses.com',
            'mailgun'   => 'mailgun.org',
            'sendgrid'  => 'sendgrid.net',
            'zoho'      => 'zohomail.com',
            'brevo'     => 'spf.brevo.com',
            'titan'     => 'spf.titan.email',
        );
        return isset($map[$key]) ? $map[$key] : ($key ?: 'المزود');
    }

    /** مفتاح مزود بريد النطاق كما يقوله سجل MX — `''` إن تعذر. */
    private function mx_provider($domain)
    {
        static $seen = array();
        if (isset($seen[$domain])) {
            return $seen[$domain];
        }
        $seen[$domain] = '';

        if (!function_exists('dns_get_record')) {
            return '';
        }
        try {
            $mx = @dns_get_record($domain, DNS_MX);
        } catch (Throwable $e) {
            return '';
        }
        if (empty($mx)) {
            return '';
        }

        usort($mx, function ($a, $b) {
            return $a['pri'] - $b['pri'];
        });
        return $seen[$domain] = $this->provider_of($mx[0]['target']);
    }

    /** خادم SMTP المرجح لنطاق — يقرأ من MX فلا يخمن. */
    private function suggest_host($domain)
    {
        $known = array(
            'hostinger' => 'smtp.hostinger.com — المنفذ 465 مع SSL',
            'google'    => 'smtp.gmail.com — المنفذ 587 مع TLS',
            'microsoft' => 'smtp.office365.com — المنفذ 587 مع TLS',
            'zoho'      => 'smtp.zoho.com — المنفذ 465 مع SSL',
            'titan'     => 'smtp.titan.email — المنفذ 465 مع SSL',
            'yandex'    => 'smtp.yandex.com — المنفذ 465 مع SSL',
        );
        $k = $this->mx_provider($domain);
        return isset($known[$k]) ? $known[$k] : 'خادم بريد نطاقك';
    }
}
