<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * TQ-QUICK-BUY — بطاقة «بياناتك» في شاشات الدفع الأربع.
 *
 * كان الزائر يقرأ في شاشة الباقة نموذج تسجيل من ثمانية حقول (اسمان وبريد
 * وكلمة مرور وعمر وصف وجوال ومربع شروط) قبل أن يرى زر الدفع؛ وفي شاشات
 * الكورس والكتاب وباقة الحصص كان يطرد إلى `/login` فلا يرى السعر أصلا.
 * وكل حقل قبل الدفع يسقط نسبة ممن وصلوا إليه — وهم أغلى زوار الموقع.
 *
 * فصارت ثلاثة حقول لا غير: **اسم الطالب والجوال والبريد**. وينشأ الحساب
 * صامتا مع الضغطة نفسها (`Taqdar::quick_buy()`)، ويمضي صاحبه إلى الدفع
 * بلا صفحة بينهما. وما سقط يطلب **بعد** الدفع في `/account/complete`:
 * كلمة المرور والعمر والصف — ولا شيء منها يحتاجه الشراء.
 *
 * ═══ ولا نموذج ثان للشراء ═══
 *
 * الحقول الثلاثة تركب **نموذج الشراء نفسه** (`#tqCheckout`) — بحقوله
 * المعتادة (`plan_id`/`course_id`/`book_id`/`pack_id` و`pay_method` و
 * `coupon`)، والفرق الوحيد وجهته. فالمتحكم ينشئ الحساب ثم يسلم الطلب إلى
 * دالة الشراء القائمة كما هي: ما يرفضه المحرك للداخل يرفضه للزائر حرفا.
 *
 * و«لدي حساب» نموذج مستقل خارج نموذج الشراء (`#tqLogin`) — `<form>` داخل
 * `<form>` وسم فاسد — وحقوله تنتسب إليه بسمة `form=` القياسية.
 */

if (!function_exists('tq_quick_old')) {
    /**
     * ما كتبه الزائر يعود إلى حقله: بعد رفض (من الجلسة) أو بعد «طبق» كود
     * الخصم بلا سكربت (من `$_POST` — الزر يرسل النموذج كله إلى الشاشة).
     */
    function tq_quick_old($key)
    {
        $CI = get_instance();
        $p  = $CI->input->post('qa_' . $key);
        if (is_string($p) && $p !== '') return $p;
        $old = $CI->session->flashdata('tq_qa_old');
        return (is_array($old) && isset($old[$key])) ? (string) $old[$key] : '';
    }
}

if (!function_exists('tq_quick_card')) {
    /**
     * البطاقة كاملة، وتطبع **داخل** نموذج الشراء.
     *
     * @param array $o kind (plan · course · book · pack) · back (مسار الشاشة
     *                 نسبيا، بدورتها) · title · total (HTML الإجمالي) ·
     *                 note (سطر ما يفتحه الشراء، اختياري)
     */
    function tq_quick_card(array $o)
    {
        $CI    = get_instance();
        $kind  = (string) $o['kind'];
        $back  = (string) $o['back'];
        $errs  = $CI->session->flashdata('tq_qa_errs');
        $errs  = is_array($errs) ? $errs : array();
        $have  = (string) $CI->session->flashdata('tq_qa_have');
        /* بلا سكربت: «ادخل من هنا» رابط إلى الشاشة نفسها بـ`?acct=have`،
           فيطبع الخادم لوح الدخول مفتوحا. */
        $mode  = ($have !== '' || (string) $CI->input->get('acct') === 'have') ? 'have' : 'new';

        $err = function ($f) use ($errs) {
            if (empty($errs[$f])) return '';
            return '<p class="field-err" role="alert">' . html_escape((string) $errs[$f][0]) . '</p>';
        };
        $bad = function ($f) use ($errs) { return empty($errs[$f]) ? '' : ' form-field--invalid'; };

        $sep   = (strpos($back, '?') === false) ? '?' : '&';
        $to_have = base_url($back) . $sep . 'acct=have#tqAcct';
        $to_new  = base_url(preg_replace('/([?&])acct=have(&|$)/', '$1', $back)) . '#tqAcct';

        ob_start(); ?>
<section class="icard qa" id="tqAcct" data-tq-qa data-mode="<?php echo $mode; ?>"
         aria-labelledby="tqQaH">
  <input type="hidden" name="buy_kind" value="<?php echo html_escape($kind); ?>">

  <div class="qa__band">
    <?php /* الشراء تسلسل حقيقي — فالترقيم هنا خبر لا زينة: أين أنت، وكم
             بقي. والخطوة الثالثة وعد يقال قبل الدفع: المحتوى يفتح في
             اللحظة، لا بعد مراجعة. */ ?>
    <ol class="qa__steps" aria-label="خطوات الشراء">
      <li class="is-on" aria-current="step"><b>١</b><span>بياناتك</span></li>
      <li><b>٢</b><span>الدفع</span></li>
      <li><b>٣</b><span>تبدأ فورا</span></li>
    </ol>
    <p class="qa__what">
      <span><?php echo html_escape((string) $o['title']); ?></span>
      <b><?php echo $o['total']; ?></b>
    </p>
  </div>

  <div class="qa__pane" data-qa-pane="new"<?php echo $mode === 'new' ? '' : ' hidden'; ?>>
    <h2 class="qa__h" id="tqQaH">بياناتك، ثم الدفع مباشرة</h2>
    <p class="qa__lede">ثلاثة حقول فقط. لا كلمة مرور الآن — تضعها بعد الدفع في ثوان.</p>

    <div class="qa__fields">
      <div class="qa__cell">
        <label class="qa__lbl" for="qaName">اسم الطالب</label>
        <div class="form-field<?php echo $bad('full_name'); ?>">
          <svg aria-hidden="true"><use href="#i-user"></use></svg>
          <input type="text" id="qaName" name="qa_full_name" required minlength="2" maxlength="80"
                 autocomplete="name" placeholder="مثال: سارة محمد"
                 value="<?php echo html_escape(tq_quick_old('full_name')); ?>">
        </div>
        <?php echo $err('full_name'); ?>
      </div>

      <div class="qa__cell">
        <span class="qa__lbl" id="qaPhoneL">رقم الجوال</span>
        <?php
        $ph = tq_phone_field('qa_phone', array(
            'required' => true,
            'value'    => tq_quick_old('phone'),
            'iso'      => tq_quick_old('phone_cc'),
            'id'       => 'qaPhone',
            'picker'   => true,
        ));
        /* حد الخطأ على الحقل كأخويه — والصنف يضاف هنا لأن الحقل يطبع من
           مساعد مشترك لا يعرف أخطاء هذا النموذج. */
        if (!empty($errs['phone'])) {
            $ph = preg_replace('/class="form-field form-phone"/', 'class="form-field form-phone form-field--invalid"', $ph, 1);
        }
        echo $ph;
        ?>
        <?php echo $err('phone'); ?>
      </div>

      <div class="qa__cell">
        <label class="qa__lbl" for="qaEmail">البريد الإلكتروني</label>
        <div class="form-field<?php echo $bad('email'); ?>">
          <svg aria-hidden="true"><use href="#i-mail"></use></svg>
          <input type="email" id="qaEmail" name="qa_email" required maxlength="50" dir="ltr"
                 autocomplete="email" autocapitalize="off" spellcheck="false"
                 placeholder="name@example.com" aria-describedby="qaEmailHint"
                 value="<?php echo html_escape(tq_quick_old('email')); ?>">
        </div>
        <?php echo $err('email'); ?>
        <p class="qa__hint" id="qaEmailHint">تصلك عليه الفاتورة، ومنه تعود إلى حسابك متى شئت.</p>
      </div>
    </div>

    <p class="qa__assure">
      <svg aria-hidden="true"><use href="#i-shield"></use></svg>
      <span>ننشئ حسابك في تقدر بهذه البيانات مع ضغطة الدفع، ونحفظ مشترياتك فيه.</span>
    </p>

    <?php /* الزر في آخر الحقول نفسها: من كتب بريده ينتهي عنده، ولا يبحث عن
             زر في عمود آخر. وهو زر النموذج نفسه (`#tqCheckout`)، فيرسل
             طريقة الدفع والكود كما يرسلهما زر البطاقة اللاصقة. */
    ?>
    <button type="submit" class="btn btn--primary btn--block qa__go" data-qa-go
            data-tq-label-tap="أنشئ حسابي وتابع إلى الدفع"
            data-tq-label-bank="أنشئ حسابي وأصدر فاتورة التحويل">
      <span data-qa-go-label>أنشئ حسابي وتابع إلى الدفع</span>
      <span class="qa__go-sum"><?php echo $o['total']; ?></span>
    </button>

    <?php
    /* TQ-SOCIAL — جوجل وأبل بضغطة، والوجهة هذه الشاشة نفسها بدورتها. */
    $social = $CI->load->view('frontend/taqdar/site/site_social_buttons', array(
        'tq_soc_gate' => 'student',
        'tq_soc_next' => $back,
        'tq_soc_lead' => 'أو بحساب جاهز',
    ), true);
    echo $social;
    ?>

    <p class="qa__switch">
      لديك حساب في تقدر؟
      <a href="<?php echo html_escape($to_have); ?>" data-qa-to="have">ادخل إليه</a>
    </p>
  </div>

  <div class="qa__pane" data-qa-pane="have"<?php echo $mode === 'have' ? '' : ' hidden'; ?>>
    <h2 class="qa__h">ادخل إلى حسابك</h2>
    <p class="qa__lede">ثم تكمل الدفع من هذه الصفحة نفسها.</p>

    <?php if ($have !== ''): ?>
      <p class="qa__note" role="status">
        <svg aria-hidden="true"><use href="#i-user"></use></svg>
        هذا البريد له حساب في تقدر. اكتب كلمة المرور لتدخل، ويبقى طلبك كما هو.
      </p>
    <?php endif; ?>

    <div class="qa__fields">
      <div class="qa__cell">
        <label class="qa__lbl" for="qaInEmail">البريد الإلكتروني</label>
        <div class="form-field">
          <svg aria-hidden="true"><use href="#i-mail"></use></svg>
          <input type="email" id="qaInEmail" name="email" form="tqLogin" required dir="ltr"
                 autocomplete="email" autocapitalize="off" spellcheck="false"
                 value="<?php echo html_escape($have); ?>">
        </div>
      </div>
      <div class="qa__cell">
        <label class="qa__lbl" for="qaInPw">كلمة المرور</label>
        <div class="form-field">
          <svg aria-hidden="true"><use href="#i-lock"></use></svg>
          <input type="password" id="qaInPw" name="password" form="tqLogin" required
                 autocomplete="current-password"<?php echo $have !== '' ? ' autofocus' : ''; ?>>
          <button class="pw-eye" type="button" data-tq-pw="qaInPw"
                  aria-label="إظهار كلمة المرور" aria-pressed="false">
            <svg aria-hidden="true"><use href="#i-eye"></use></svg>
          </button>
        </div>
      </div>
    </div>

    <button class="btn btn--primary btn--block" type="submit" form="tqLogin">ادخل وتابع الدفع</button>

    <p class="qa__switch">
      <a href="<?php echo base_url('forgot_password'); ?>">نسيت كلمة المرور؟</a>
      <span aria-hidden="true">·</span>
      <a href="<?php echo html_escape($to_new); ?>" data-qa-to="new">أنا جديد — أكمل بلا كلمة مرور</a>
    </p>
  </div>
</section>
<?php
        return ob_get_clean();
    }
}

if (!function_exists('tq_quick_login_form')) {
    /**
     * نموذج «لدي حساب» — **خارج** نموذج الشراء، وتنتسب حقوله إليه بـ`form=`.
     *
     * والوجهة حقل لا جلسة وحدها (`tq_next` → `url_history` في
     * `validate_login()`)، و`qa=in` يقول للشاشة عند العودة إن صاحبها دخل
     * للتو — فتقول له ما بقي بدل أن تبدو كما كانت.
     */
    function tq_quick_login_form($back)
    {
        $back = (string) $back;
        $sep  = (strpos($back, '?') === false) ? '?' : '&';
        ob_start(); ?>
<form id="tqLogin" hidden action="<?php echo site_url('login/validate_login'); ?>" method="post">
  <?php echo tq_csrf(); ?>
  <input type="hidden" name="tq_gate" value="student">
  <input type="hidden" name="remember_me" value="1">
  <input type="hidden" name="tq_next" value="<?php echo html_escape($back . $sep . 'qa=in'); ?>">
</form>
<?php
        return ob_get_clean();
    }
}

if (!function_exists('tq_quick_side_button')) {
    /**
     * زر البطاقة اللاصقة — للزائر وللداخل، بنص يوافق الطريقة المختارة.
     *
     * والزائر يقرأ «تابع إلى الدفع الآمن» لا «أنشئ حسابي»: ما يريده هو
     * الدفع، والحساب ينشأ في الطريق. ومع أزرار الدفع المباشر يعود إلى
     * هنا بعد إنشائه، فيقال ذلك (TQ-EXPRESS-PAY).
     */
    function tq_quick_side_button($guest, $card, $bank_label)
    {
        $tap  = 'تابع إلى الدفع الآمن';
        $bank = (string) $bank_label;
        $attr = '';
        if ($guest) {
            if (!empty(tq_express()['any'])) $tap = t('أنشئ حسابي وتابع إلى الدفع');
            $bank = 'أصدر فاتورة التحويل';
            $attr = ' data-qa-new-only';
        }
        $h = '<button type="submit" class="btn btn--primary btn--block" data-tq-submit' . $attr
           . ' data-tq-label-tap="' . html_escape($tap) . '"'
           . ' data-tq-label-bank="' . html_escape($bank) . '">'
           . html_escape($card ? $tap : $bank) . '</button>';
        if ($guest) {
            $h .= '<p class="co-side__guest" data-qa-have-only hidden>'
                . '<svg aria-hidden="true"><use href="#i-lock"></use></svg>'
                . 'ادخل من بطاقة «ادخل إلى حسابك» أعلاه، وتكمل الدفع مباشرة.</p>';
        }
        return $h;
    }
}

if (!function_exists('tq_quick_express_note')) {
    /** مكان أزرار الدفع المباشر للزائر: أين يجدها، لا فراغ. */
    function tq_quick_express_note()
    {
        if (empty(tq_express()['any'])) return '';
        return '<p class="tq-caption co-pay__x">'
             . t('بعد إنشاء حسابك تعود إلى هذه الصفحة وتدفع مباشرة من هنا: Apple Pay أو Google Pay أو بطاقتك.')
             . '</p>';
    }
}

if (!function_exists('tq_quick_welcome')) {
    /**
     * سطر «دخلت» فوق شاشة الدفع لمن عاد إليها من لوح «لدي حساب».
     * بلاه تبدو الشاشة كما تركها، فيظن أن الدخول لم يقع.
     */
    function tq_quick_welcome()
    {
        $CI = get_instance();
        if ((string) $CI->input->get('qa') !== 'in' || !$CI->session->userdata('user_id')) return '';
        return '<p class="tq-flash tq-flash--ok qa-in" role="status">'
             . 'دخلت إلى حسابك. راجع طريقة الدفع ثم اضغط زر المتابعة.</p>';
    }
}

if (!function_exists('tq_quick_script')) {
    /**
     * تحسين لا شرط: تبديل اللوحين بلا إعادة تحميل، وإلزام الجوال، ومنع
     * النقرة الثانية على «ادفع» وهي تنشئ حسابا.
     *
     * وبلاه تعمل الشاشة كلها: التبديل رابط يعيد بناءها، والجوال يفحصه
     * الخادم، والنقرة الثانية يردها `uk_email` إلى باب «لدي حساب».
     */
    function tq_quick_script()
    {
        $picker = 'assets/taqdar/js/tq-cc-picker.js';
        $v = is_file(FCPATH . $picker) ? filemtime(FCPATH . $picker) : 1;
        ob_start(); ?>
<script src="<?php echo base_url($picker) . '?v=' . $v; ?>" defer></script>
<script>
(function () {
  var card = document.querySelector('[data-tq-qa]');
  if (!card) return;
  var form = document.getElementById('tqCheckout');

  /* الجوال يحمل `data-req` لا `required` (عرف حقل الهاتف مع سكربت
     بوابات التسجيل)، ولا بوابات هنا — فيثبت مباشرة. */
  Array.prototype.forEach.call(card.querySelectorAll('[data-req]'), function (el) {
    el.setAttribute('required', '');
  });

  var newOnly  = document.querySelectorAll('[data-qa-new-only]');
  var haveOnly = document.querySelectorAll('[data-qa-have-only]');

  function mode(m, focus) {
    card.setAttribute('data-mode', m);
    Array.prototype.forEach.call(card.querySelectorAll('[data-qa-pane]'), function (p) {
      p.hidden = (p.getAttribute('data-qa-pane') !== m);
    });
    /* حقول اللوح المطوي لا تمنع نموذجها: `required` على بريد لا يرى
       يجعل «ادفع» لا يفعل شيئا ولا يقول لماذا. */
    Array.prototype.forEach.call(card.querySelectorAll('[data-qa-pane="new"] [required]'), function (el) {
      el.disabled = (m !== 'new');
    });
    Array.prototype.forEach.call(newOnly, function (el) { el.hidden = (m !== 'new'); });
    Array.prototype.forEach.call(haveOnly, function (el) { el.hidden = (m !== 'have'); });
    if (focus) {
      var first = card.querySelector('[data-qa-pane="' + m + '"] input:not([type=hidden])');
      if (first) first.focus();
    }
  }
  Array.prototype.forEach.call(card.querySelectorAll('[data-qa-to]'), function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      mode(a.getAttribute('data-qa-to'), true);
    });
  });
  mode(card.getAttribute('data-mode') || 'new', false);

  /* زر آخر الحقول يقول ما يقع بالطريقة المختارة، كزر البطاقة اللاصقة. */
  var go = card.querySelector('[data-qa-go]');
  function goLabel() {
    if (!go || !form) return;
    var picked = form.querySelector('input[name="pay_method"]:checked');
    var l = go.getAttribute('data-tq-label-' + (picked ? picked.value : 'tap'));
    var slot = go.querySelector('[data-qa-go-label]');
    if (l && slot) slot.textContent = l;
  }
  if (form) form.addEventListener('change', function (e) {
    if (e.target && e.target.name === 'pay_method') goLabel();
  });
  goLabel();

  /* نقرة ثانية على «ادفع» بعد أن مضت الأولى تنشئ طلبا ثانيا. ويستثنى
     «طبق» الكود: زر إرسال إلى الشاشة نفسها (`formaction`) لا شراء. */
  if (form) form.addEventListener('submit', function (e) {
    var by = e.submitter;
    if (by && by.hasAttribute('formaction')) return;
    Array.prototype.forEach.call(document.querySelectorAll('[form="tqCheckout"][type=submit], #tqCheckout [type=submit]:not([formaction])'), function (b) {
      b.disabled = true;
      b.setAttribute('aria-busy', 'true');
    });
    if (by) {
      by.setAttribute('data-qa-label', by.innerHTML);
      by.textContent = 'ننشئ حسابك ونمضي إلى الدفع…';
    }
  });

  /* الرجوع من صفحة تاب يعيد الصفحة من ذاكرة المتصفح كما تركت — والزر
     معطل. فمن ألغى الدفع ورجع يجد زرا لا يضغط. */
  window.addEventListener('pageshow', function (e) {
    if (!e.persisted) return;
    Array.prototype.forEach.call(document.querySelectorAll('[aria-busy="true"]'), function (b) {
      b.disabled = false;
      b.removeAttribute('aria-busy');
      var l = b.getAttribute('data-qa-label');
      if (l) b.innerHTML = l;
    });
  });
})();
</script>
<?php
        return ob_get_clean();
    }
}
