<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * البريد الذي لا يصل — تنبيه واحد لكل شاشة تنتظر رسالة.
 *
 * رسائل المنصة تصنف عند بعض المزودين رسائل غير مرغوبة فتذهب إلى
 * `Spam` أو `Junk`. وأثر ذلك ليس رسالة تتأخر:
 *
 *   · رمز التأكيد لا يصل، فيظن صاحب الحساب أن التسجيل فشل ويعيده
 *     ببريد آخر — أو يتركه.
 *   · رابط استعادة كلمة المرور لا يصل، فيبقى خارج حسابه.
 *   · وإشعارات الفواتير والاشتراكات تختفي بلا أن يعلم أحد.
 *
 * والحل ليس سطرا يقول «راجع مجلد الرسائل غير المرغوبة». من لم يفتح
 * ذلك المجلد قط لا يعرف أين هو، ومن فتحه ووجد الرسالة يقرؤها ويمضي —
 * فتذهب الرسالة التالية إلى المجلد نفسه. **والضغطة التي تحل المشكلة
 * نهائيا هي «ليس بريدا غير مرغوب»**، وهي التي يعلمها هذا التنبيه
 * ويشدد عليها، لأنها وحدها ما يعيد بقية الإشعارات إلى صندوق الوارد.
 *
 * ومصدر واحد لا نسخ: النص والخطوات والروابط تكتب هنا مرة، ويطبعها
 * الموقع العام والبوابات جميعا من الدالة نفسها. نسختان تفترقان عند
 * أول تعديل، فتقول شاشة التسجيل غير ما تقوله شاشة الإعدادات عن
 * الشيء نفسه.
 *
 * ولا يستعمل `tq_text()` هنا: ذاك سجل نصوص **الموقع العام** وحده
 * (انظر `Taqdar_content_model::registry()`)، وهذا الوسم يخرج في
 * البوابات كذلك — فلو ربط بالسجل لصار نصف مواضعه يحرر من اللوحة
 * ونصفها لا يحرر.
 *
 * والورقة في `site/css/shared.css`: هي الوحيدة التي تحمل في الجهتين
 * وتجسر توكناتها إلى توكنات البوابة.
 */

if (!function_exists('tq_mail_sender')) {
    /**
     * العنوان الذي يراه المستلم في خانة «من».
     *
     * `send_as` لا `from`: حين لا يتحاذى نطاق الاعتماد مع المرسل الظاهر
     * يكتب `Taqdar_mail_model` عنوان الاعتماد في الترويسة ويضع المقصود
     * في «عنوان الرد» — فمن يبحث في بريده عن `from` لا يجد شيئا.
     *
     * ويرد فارغا حين لا يكون البريد مضبوطا: التنبيه حينها يعلم البحث
     * بالاسم لا بالعنوان، ولا يعرض عنوانا مخترعا.
     */
    function tq_mail_sender()
    {
        static $addr = null;
        if ($addr !== null) return $addr;

        $addr = '';
        try {
            $CI = &get_instance();
            $CI->load->model('taqdar_mail_model');
            $c = $CI->taqdar_mail_model->config();

            $pick = '';
            if (!empty($c['send_as'])) {
                $pick = (string) $c['send_as'];
            } elseif (!empty($c['from'])) {
                $pick = (string) $c['from'];
            }
            $addr = (strpos($pick, '@') !== false) ? trim($pick) : '';
        } catch (Throwable $e) {
            $addr = '';
        }
        return $addr;
    }
}

if (!function_exists('tq_inbox_email')) {
    /**
     * بريد من يقرأ الشاشة — ليعرف التنبيه أي مزود يفتح له.
     *
     * الصريح أولا (شاشة التأكيد تعرفه من الجلسة قبل أن يكون له حساب)،
     * ثم بريد صاحب الجلسة. والزائر بلا بريد يرد فارغا، فتعرض له روابط
     * المزودين المشهورين جميعا.
     */
    function tq_inbox_email($explicit = '')
    {
        $explicit = trim((string) $explicit);
        if ($explicit !== '') return $explicit;

        static $mine = null;
        if ($mine !== null) return $mine;

        $mine = '';
        try {
            $CI  = &get_instance();
            $uid = (int) $CI->session->userdata('user_id');
            if ($uid > 0) {
                $row  = $CI->db->select('email')->where('id', $uid)->get('users')->row_array();
                $mine = trim((string) (isset($row['email']) ? $row['email'] : ''));
            }
        } catch (Throwable $e) {
            $mine = '';
        }
        return $mine;
    }
}

if (!function_exists('tq_inbox_providers')) {
    /**
     * مجلد الرسائل غير المرغوبة عند كل مزود — رابط مباشر لا شرح.
     *
     * «افتح بريدك ثم ابحث عن المجلد» خطوة يسقط عندها كثيرون: اسم المجلد
     * يختلف (Spam · Junk · البريد المزعج) وموضعه مطوي تحت «المزيد» في
     * Gmail. والرابط يفتحه في نقرة.
     */
    function tq_inbox_providers()
    {
        return array(
            'gmail'   => array('Gmail',             'https://mail.google.com/mail/u/0/#spam'),
            'outlook' => array('Outlook · Hotmail', 'https://outlook.live.com/mail/0/junkemail'),
            'yahoo'   => array('Yahoo Mail',        'https://mail.yahoo.com/d/folders/6'),
            'icloud'  => array('iCloud Mail',       'https://www.icloud.com/mail'),
            'proton'  => array('Proton Mail',       'https://mail.proton.me/u/0/spam'),
            'zoho'    => array('Zoho Mail',         'https://mail.zoho.com/zm/#mail/folder/spam'),
            'aol'     => array('AOL Mail',          'https://mail.aol.com/'),
            'yandex'  => array('Yandex Mail',       'https://mail.yandex.com/#spam'),
        );
    }
}

if (!function_exists('tq_inbox_key')) {
    /**
     * أي مزود يقف خلف هذا البريد؟
     *
     * بالنطاق وحده، والمجهول يرد فارغا. ونطاقات المدارس والشركات مجهولة
     * هنا ولو كانت على Google أو Microsoft — ولا سبيل إلى معرفتها من
     * العنوان، فتعرض لها القائمة المشهورة كاملة.
     */
    function tq_inbox_key($email)
    {
        $at = strrpos((string) $email, '@');
        if ($at === false) return '';

        $d = strtolower(trim(substr((string) $email, $at + 1)));
        if ($d === '') return '';

        static $map = array(
            'gmail.com'      => 'gmail',   'googlemail.com' => 'gmail',
            'hotmail.com'    => 'outlook', 'outlook.com'    => 'outlook',
            'live.com'       => 'outlook', 'msn.com'        => 'outlook',
            'hotmail.co.uk'  => 'outlook', 'live.co.uk'     => 'outlook',
            'outlook.sa'     => 'outlook', 'hotmail.fr'     => 'outlook',
            'yahoo.com'      => 'yahoo',   'ymail.com'      => 'yahoo',
            'rocketmail.com' => 'yahoo',   'yahoo.co.uk'    => 'yahoo',
            'icloud.com'     => 'icloud',  'me.com'         => 'icloud',
            'mac.com'        => 'icloud',
            'proton.me'      => 'proton',  'protonmail.com' => 'proton',
            'pm.me'          => 'proton',
            'zoho.com'       => 'zoho',    'zohomail.com'   => 'zoho',
            'aol.com'        => 'aol',
            'yandex.com'     => 'yandex',  'yandex.ru'      => 'yandex',
        );
        return isset($map[$d]) ? $map[$d] : '';
    }
}

if (!function_exists('tq_inbox_search_url')) {
    /**
     * بحث يجد الرسالة أين كانت — لا فتح مجلد.
     *
     * Gmail وحده يقبل البحث في العنوان، و`in:anywhere` فيه يشمل المهملات
     * والأرشيف والمجلد غير المرغوب معا. وغيره لا رابط بحث ثابتا له، فلا
     * يخترع له واحد.
     */
    function tq_inbox_search_url($provider_key, $sender)
    {
        $sender = trim((string) $sender);
        if ($provider_key !== 'gmail' || $sender === '') return '';

        return 'https://mail.google.com/mail/u/0/#search/'
            . rawurlencode('from:(' . $sender . ') in:anywhere');
    }
}

if (!function_exists('tq_spam_notice')) {
    /**
     * التنبيه نفسه.
     *
     * @param array $o
     *   email    بريد المستلم — يشتق منه رابط مزوده. يقرأ من الجلسة إن ترك.
     *   compact  يطوى في `<details>` بسطر واحد ظاهر — للشاشات التي ليس
     *            انتظار الرسالة موضوعها (التسجيل · الشراء · الفواتير).
     *   what     ما ينتظره: «رمز التأكيد» · «رابط استعادة كلمة المرور» …
     *            يدخل في العنوان فيقرأ التنبيه في سياقه لا عاما.
     *   id       معرف الوسم — مرساة للروابط. الافتراضي `tq-spam`.
     *   class    أصناف تضاف على الجذر.
     */
    function tq_spam_notice($o = array())
    {
        static $js_done = false;

        $compact = !empty($o['compact']);
        $id      = (isset($o['id']) && $o['id'] !== '') ? (string) $o['id'] : 'tq-spam';
        $extra   = isset($o['class']) ? ' ' . (string) $o['class'] : '';
        $what    = (isset($o['what']) && $o['what'] !== '') ? (string) $o['what'] : t('رسالتنا');

        $email  = tq_inbox_email(isset($o['email']) ? $o['email'] : '');
        $key    = tq_inbox_key($email);
        $sender = tq_mail_sender();

        $all = tq_inbox_providers();
        /* مزود المستلم وحده حين يعرف: أربعة أزرار ثلاثة منها لا تعنيه
           تجعله يقرأ قبل أن ينقر. والمجهول يعرض له المشهور. */
        $show = ($key !== '' && isset($all[$key]))
            ? array($key => $all[$key])
            : array_intersect_key($all, array_flip(array('gmail', 'outlook', 'yahoo', 'icloud')));

        $search = tq_inbox_search_url($key, $sender);

        /* الأزرار تسمي نفسها حين تكون لمزود واحد، ويسميها السطر الذي
           يسبقها حين تكون قائمة. و«افتح المجلد مباشرة: [ابحث في Gmail]
           [Gmail]» كانت تعرض زرين متجاورين أحدهما اسم مزود عاريا بعد زر
           يذكر المزود نفسه — فيقرأ الثاني كأنه تكرار للأول. */
        $one     = (count($show) === 1);
        $lead_go = $one ? t('افتح بريدك مباشرة:') : t('افتح مجلد «غير المرغوبة» في:');

        /* عنوان المرسل حين يعرف، واسم المنصة حين لا يعرف: البحث بالاسم
           يجد الرسالة كذلك، وعنوان مخترع لا يجد شيئا. */
        $needle = ($sender !== '') ? $sender : t('تقدر');

        $h = static function ($s) { return html_escape((string) $s); };

        $addr = '<span class="tq-spam__addr"><b class="tq-ltr" dir="ltr">' . $h($needle) . '</b>';
        if ($sender !== '') {
            $addr .= '<button type="button" class="tq-spam__copy" data-tq-copy="' . $h($sender) . '"'
                   . t(' aria-label="انسخ عنوان المرسل">')
                   . tq_icon('copy', 14) . t('<span>انسخ</span></button>');
        }
        $addr .= '</span>';

        /* الخطوة الثالثة هي المقصودة من التنبيه كله، وتعلم بـ`is-key`:
           الأولى والثانية تجدان رسالة واحدة، وهي وحدها تصلح ما بعدها. */
        $steps = array(
            array(t('١'),
                t('افتح بريدك ثم افتح مجلد <b>«الرسائل غير المرغوبة»</b> — واسمه عند ')
                . t('بعض المزودين <span class="tq-ltr" dir="ltr">Spam</span> أو ')
                . t('<span class="tq-ltr" dir="ltr">Junk</span> أو «البريد المزعج»، ')
                . t('وقد يكون مطويا تحت «المزيد».'),
                false),
            array(t('٢'),
                t('ابحث فيه عن رسالة من ') . $addr . t(' — أو اكتب <b>تقدر</b> في خانة البحث.'),
                false),
            array(t('٣'),
                t('افتح الرسالة ثم اضغط <b>«ليس بريدا غير مرغوب»</b> ')
                . '(<span class="tq-ltr" dir="ltr">Report not spam</span> · '
                . t('<span class="tq-ltr" dir="ltr">Not junk</span> · «ليس مزعجا»). ')
                . t('<b>هذه الضغطة هي المهمة</b> — بها تعود بقية إشعاراتنا كلها ')
                . t('(الرموز والفواتير وتنبيهات حصصك ودرجاتك) إلى صندوق الوارد مباشرة، ')
                . t('ولا تعود إلى هذا المجلد.'),
                true),
            array(t('٤'),
                t('وليطمئن قلبك: أضف ')
                . ($sender !== ''
                    ? '<span class="tq-ltr" dir="ltr">' . $h($sender) . '</span>'
                    : t('عنوان مراسلات تقدر'))
                . t(' إلى جهات الاتصال عندك، فلا يعاد تصنيفه بعد اليوم.'),
                false),
        );

        ob_start();
        ?>
<?php if ($compact): ?>
<details class="tq-spam tq-spam--compact<?php echo $extra; ?>" id="<?php echo $h($id); ?>">
  <summary class="tq-spam__sum">
    <span class="tq-spam__chip" aria-hidden="true"><?php echo tq_icon('mail', 18); ?></span>
    <span class="tq-spam__sumt"><?php echo t('لم تصلك رسالتنا بالبريد؟ ابحث عنها في «الرسائل غير المرغوبة»'); ?></span>
    <span class="tq-spam__more" aria-hidden="true"><?php echo t('الخطوات'); ?></span>
  </summary>
  <div class="tq-spam__body">
<?php else: ?>
<section class="tq-spam<?php echo $extra; ?>" id="<?php echo $h($id); ?>"
         role="note" aria-labelledby="<?php echo $h($id); ?>-t">
  <div class="tq-spam__top">
    <span class="tq-spam__chip" aria-hidden="true"><?php echo tq_icon('alert', 22); ?></span>
    <div class="tq-spam__intro">
      <p class="tq-spam__eyebrow"><?php echo t('تنبيه مهم'); ?></p>
      <h2 class="tq-spam__title" id="<?php echo $h($id); ?>-t">
        <?php echo t('لم تجد ____ في صندوق الوارد؟ ابحث في «الرسائل غير المرغوبة»', array($h($what))); ?>
      </h2>
      <p class="tq-spam__lead">
        <?php echo t('قد يضع مزود بريدك رسائل تقدر — رمز التأكيد، ورابط استعادة كلمة المرور، وإشعارات الفواتير والاشتراكات — في مجلد'); ?>
        <span class="tq-ltr" dir="ltr">Spam</span> <?php echo t('أو'); ?>
        <span class="tq-ltr" dir="ltr">Junk</span> <?php echo t('بدل صندوق الوارد. وهذه أربع خطوات تحلها في دقيقة، ونهائيا.'); ?>
      </p>
    </div>
  </div>
  <div class="tq-spam__body">
<?php endif; ?>

    <ol class="tq-spam__steps">
      <?php foreach ($steps as $s): ?>
        <li class="tq-spam__step<?php echo $s[2] ? ' is-key' : ''; ?>">
          <span class="tq-spam__n" aria-hidden="true"><?php echo $s[0]; ?></span>
          <span class="tq-spam__tx"><?php echo $s[1]; ?></span>
        </li>
      <?php endforeach; ?>
    </ol>

    <div class="tq-spam__go">
      <span class="tq-spam__gol"><?php echo tq_icon('external', 16); ?> <?php echo $h($lead_go); ?></span>
      <?php if ($search !== ''): ?>
        <a class="tq-spam__btn tq-spam__btn--key" href="<?php echo $h($search); ?>"
           target="_blank" rel="noopener noreferrer"><?php echo t('ابحث عن رسالتنا في Gmail'); ?></a>
      <?php endif; ?>
      <?php foreach ($show as $p): ?>
        <a class="tq-spam__btn" href="<?php echo $h($p[1]); ?>"
           target="_blank" rel="noopener noreferrer"><?php
             echo $h($one ? t('مجلد «غير المرغوبة» في ') . $p[0] : $p[0]); ?></a>
      <?php endforeach; ?>
    </div>

    <p class="tq-spam__fine">
      <?php echo t('الروابط تفتح بريدك في تبويب جديد. وإن كان بريدك على تطبيق الجوال فافتح القائمة الجانبية ثم «الرسائل غير المرغوبة». ولم تجدها هناك أيضا؟ راجع «كل الرسائل» وتبويبي «العروض» و«الاجتماعية»، ثم'); ?>
      <a href="<?php echo base_url('contact'); ?>"><?php echo t('تواصل معنا'); ?></a> <?php echo t('ونساعدك.'); ?>
    </p>

<?php if ($compact): ?>
  </div>
</details>
<?php else: ?>
  </div>
</section>
<?php endif; ?>
<?php if (!$js_done): $js_done = true; ?>
<?php /* النسخ: مستمع واحد مفوض يخدم كل تنبيه في الصفحة لا مستمعا لكل
        زر. و`clipboard.writeText` لا تعمل إلا على اتصال آمن، فالنسخة
        الاحتياطية تبقي الزر عاملا على المضيف المحلي. */ ?>
<script>
(function () {
  if (window.tqSpamCopy) return;
  window.tqSpamCopy = 1;

  document.addEventListener('click', function (e) {
    var b = (e.target && e.target.closest) ? e.target.closest('[data-tq-copy]') : null;
    if (!b) return;
    e.preventDefault();

    var txt = b.getAttribute('data-tq-copy') || '';
    var lab = b.querySelector('span');

    var done = function () {
      if (!lab) return;
      if (!b.getAttribute('data-was')) b.setAttribute('data-was', lab.textContent);
      lab.textContent = 'نسخ';
      b.classList.add('is-done');
      setTimeout(function () {
        lab.textContent = b.getAttribute('data-was') || 'انسخ';
        b.classList.remove('is-done');
      }, 2000);
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(txt).then(done, function () {});
      return;
    }
    try {
      var t = document.createElement('textarea');
      t.value = txt;
      t.setAttribute('readonly', '');
      t.style.position = 'fixed';
      t.style.opacity = '0';
      document.body.appendChild(t);
      t.select();
      document.execCommand('copy');
      document.body.removeChild(t);
      done();
    } catch (err) {}
  });
})();
</script>
<?php endif; ?>
<?php
        return ob_get_clean();
    }
}
