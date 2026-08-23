<?php
/**
 * تأكيد الحساب بالرمز.
 *
 * الشاشة تجيب عن ثلاثة أسئلة يسألها من يقف هنا، وكانت لا تجيب إلا عن
 * الأول:
 *
 *   **أين أرسل الرمز؟** — الوجهة تعرض ملثمة (`ah•••@…` ·
 *   `+966••••••567`)، فيتعرف عليها صاحبها ولا يقرأها من ينظر إلى شاشته.
 *   وهذا ليس زينة: الطالب دون الخامسة عشرة يذهب رمزه إلى **بريد ولي
 *   أمره**، وشاشة تقول «راجع بريدك» تجعله يفتح صندوقه ولا يجد شيئا.
 *
 *   **ولماذا هناك؟** — سطر يقوله صراحة حين تكون الوجهة ولي الأمر.
 *
 *   **وإن كان الرقم خطأ؟** — يبدل القناة من هنا. والرقم الخاطئ لا يكتشف
 *   إلا بعد الإرسال، فشاشة بلا مخرج تعني حسابا لا يفتح أبدا وتسجيلا
 *   يعاد ببريد آخر.
 *
 * والنقاط الثلاث ترد **JSON** لا `true`/`false` نصا كما كان: رسالة
 * الخطأ هي ما يصلح، و«الرمز غير صحيح» عامة تخفي «انتهت صلاحيته» و
 * «تجاوزت المحاولات» و«انتهت الجلسة» — وكلها تعالج بغير ما يعالج به
 * الخطأ الحقيقي.
 *
 * ولا jQuery في هذا الثيم، فالطلب بـ`fetch`. ورمز CSRF يرسل يدويا:
 * الحقن التلقائي يمس `<form>` وحدها، وهذه طلبات لا تمر بها.
 */
$tq_ci   = &get_instance();
$tq_otp  = $tq_ci->session->userdata('tq_otp');
if (!is_array($tq_otp)) { $tq_otp = array(); }

$tq_ident = (string) (isset($tq_otp['identity']) ? $tq_otp['identity'] : $tq_ci->session->userdata('register_email'));
$tq_gate  = (string) (isset($tq_otp['gate']) ? $tq_otp['gate'] : 'student');
$tq_why   = (string) (isset($tq_otp['why']) ? $tq_otp['why'] : '');
$tq_chans = (isset($tq_otp['channels']) && is_array($tq_otp['channels'])) ? $tq_otp['channels'] : array();

/* حال الرمز الجاري: على أي قناة خرج، وإلى أين، ومتى يسمح بإعادة إرساله.
   يقرأ من الجدول لا من الجلسة — فمن أعاد تحميل الصفحة بعد دقيقة يرى
   العداد وقد نقص، لا وقد بدأ من جديد. */
$tq_state = null;
if ($tq_ident !== '') {
    $tq_ci->load->model('taqdar_otp_model');
    $tq_state = $tq_ci->taqdar_otp_model->state('signup', $tq_ident);
}

$tq_chan_now = $tq_state ? (string) $tq_state['channel'] : '';
$tq_shown    = $tq_state ? (string) $tq_state['shown']   : '';
$tq_is_wa    = ($tq_chan_now === 'whatsapp');

/* الوصف الذي يقرؤه صاحب الحساب: «بريد ولي أمرك» غير «بريدك». */
$tq_where = 'وجهتك';
if ($tq_chan_now !== '' && isset($tq_chans[$tq_chan_now]['label'])) {
    $tq_where = (string) $tq_chans[$tq_chan_now]['label'];
}

$tq_h1   = 'أكد حسابك';
$tq_lead = $tq_is_wa
    ? 'أرسلنا رمزا إلى واتساب. اكتبه هنا ليفتح حسابك.'
    : 'أرسلنا رمزا إلى ' . $tq_where . '. اكتبه هنا ليفتح حسابك.';
include __DIR__ . '/site/site_pagehero.php';
?>
<section class="section">
  <div class="shell shell--auth">
    <div class="auth-wrap">
      <div class="form-card">
      <a class="auth-brand" href="<?php echo base_url(); ?>" aria-label="منصة تقدر">
        <img src="<?php echo tq_site_asset('img/logo.webp'); ?>" alt="منصة تقدر" width="280" height="157">
      </a>

      <?php if ($tq_e = $this->session->flashdata('error_message')): ?>
        <p class="tq-flash tq-flash--err" role="alert"><?php echo html_escape($tq_e); ?></p>
      <?php endif; ?>
      <?php if ($tq_f = $this->session->flashdata('flash_message')): ?>
        <p class="tq-flash tq-flash--ok" role="status"><?php echo html_escape($tq_f); ?></p>
      <?php endif; ?>
      <?php if ($tq_i = $this->session->flashdata('info_message')): ?>
        <p class="tq-flash tq-flash--ok" role="status"><?php echo html_escape($tq_i); ?></p>
      <?php endif; ?>

      <?php /* الوجهة ملثمة: يتعرف عليها صاحبها ولا يقرأها من ينظر. */ ?>
      <?php if ($tq_shown !== ''): ?>
        <p class="form-hint form-hint--box" id="tqDest">
          <svg aria-hidden="true"><use href="#<?php echo $tq_is_wa ? 'i-whatsapp' : 'i-mail'; ?>"></use></svg>
          <span>الرمز أرسل إلى <b class="tq-ltr" dir="ltr"><?php echo html_escape($tq_shown); ?></b>
            <?php if (!$tq_is_wa): ?><span class="tq-dest-what"> — <?php echo html_escape($tq_where); ?></span><?php endif; ?>
          </span>
        </p>
      <?php endif; ?>

      <?php /* الطالب القاصر: يقال له لماذا ذهب رمزه إلى غيره، وإلا فتح
               صندوقه وانتظر ما لا يجيء. */ ?>
      <?php if ($tq_why !== ''): ?>
        <p class="form-hint form-hint--box">
          <svg aria-hidden="true"><use href="#i-users"></use></svg>
          <span><?php echo html_escape($tq_why); ?> اطلب الرمز منه.</span>
        </p>
      <?php endif; ?>

      <form id="verify-form" data-tq-auth novalidate>
        <div class="form-cell">
          <label class="form-field">
            <svg aria-hidden="true"><use href="#i-shield"></use></svg>
            <span class="sr-only">رمز التأكيد</span>
            <?php /* `one-time-code` يجعل هاتف iOS و Android يعرضان الرمز
                     فوق لوح المفاتيح مباشرة من الرسالة. و`numeric` لا
                     `tel`: لوح الهاتف يحمل رموزا لا مكان لها هنا. */ ?>
            <input type="text" name="code" id="tqVcode" required
                   inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*"
                   placeholder="رمز التأكيد" maxlength="6" minlength="6"
                   dir="ltr" autofocus
                   data-msg="الرمز ست خانات.">
          </label>
        </div>
        <p class="field-err" id="vcodeErr" role="alert" hidden></p>
        <button class="btn btn--primary btn--block" type="submit">تأكيد</button>
      </form>

      <p class="form-alt">
        لم يصلك الرمز؟
        <a href="#" id="tqResend">أعد الإرسال</a>
        <span id="tqResendState" role="status"></span>
      </p>

      <?php /* TQ-SPAM — أشيع سبب لرمز «لم يصل».
               الرمز خرج فعلا (`$tq_state` يقول متى وإلى أين)، وصنفه مزود
               البريد رسالة غير مرغوبة. ومن لا يعرف ذلك يعيد الإرسال مرتين
               ثم يسجل ببريد آخر — فيبقى الرمز الثاني في المجلد نفسه.
               ويعرض تحت «أعد الإرسال» لا فوق الحقل: من معه الرمز يكتبه ولا
               يقرأ شيئا، ومن ليس معه ينزل بصره إلى هنا مباشرة.
               ولا يعرض لقناة واتساب: لا مجلد مهملات فيها. */ ?>
      <?php if (!$tq_is_wa): ?>
        <?php echo tq_spam_notice(array(
            'email' => isset($tq_chans['email']['to']) ? $tq_chans['email']['to'] : $tq_ident,
            'what'  => 'رمز التأكيد',
        )); ?>
      <?php endif; ?>

      <?php /* تبديل القناة: المخرج الوحيد لمن كتب رقما خطأ. ولا يعرض
               إلا حين تكون هناك قناة ثانية فعلا. */ ?>
      <?php if (count($tq_chans) > 1): ?>
        <div class="otp-switch">
          <span class="otp-switch__t">أو أرسله إلى:</span>
          <?php foreach ($tq_chans as $tq_k => $tq_c):
            if ($tq_k === $tq_chan_now) continue; ?>
            <button type="button" class="otp-switch__b" data-chan="<?php echo html_escape($tq_k); ?>">
              <svg aria-hidden="true"><use href="#<?php
                echo $tq_k === 'whatsapp' ? 'i-whatsapp' : 'i-mail'; ?>"></use></svg>
              <span><?php echo html_escape($tq_c['label']); ?>
                <b class="tq-ltr" dir="ltr"><?php echo html_escape($tq_c['shown']); ?></b></span>
            </button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <p class="form-alt"><a href="<?php echo base_url('login'); ?>">عودة إلى تسجيل الدخول</a></p>
      </div>

      <?php
      $tq_aside_h2 = 'خطوة واحدة تفصلك';
      $tq_aside_points = $tq_is_wa
        ? array(
            array('i-whatsapp', 'راجع واتساب',   'الرمز من رقم منصة تقدر'),
            array('i-shield',   'الرمز لك وحدك', 'لا تشاركه مع أحد — ولا يطلبه منك موظف'),
            array('i-clock',    'صالح عشر دقائق', 'وبعدها اطلب رمزا جديدا'),
          )
        : array(
            array('i-mail',   'راجع بريدك',    'وربما مجلد الرسائل غير المرغوبة'),
            array('i-shield', 'الرمز لك وحدك', 'لا تشاركه مع أحد — ولا يطلبه منك موظف'),
            array('i-clock',  'صالح عشر دقائق', 'وبعدها اطلب رمزا جديدا'),
          );
      include __DIR__ . '/site/site_authaside.php';
      ?>
    </div>
  </div>
</section>

<script>
(function () {
  var form = document.getElementById('verify-form');
  if (!form) return;

  var input  = document.getElementById('tqVcode');
  var err    = document.getElementById('vcodeErr');
  var resend = document.getElementById('tqResend');
  var state  = document.getElementById('tqResendState');
  var dest   = document.getElementById('tqDest');
  var CSRF   = <?php echo json_encode(array(
      $this->security->get_csrf_token_name() => $this->security->get_csrf_hash())); ?>;

  var URLS = {
    verify:  <?php echo json_encode(site_url('login/otp/verify')); ?>,
    resend:  <?php echo json_encode(site_url('login/otp/resend')); ?>,
    channel: <?php echo json_encode(site_url('login/otp/channel')); ?>,
    login:   <?php echo json_encode(site_url('login')); ?>
  };

  function say(msg) { err.textContent = msg || ''; err.hidden = !msg; }

  /* `csrf_regenerate` مطفأ في هذا المستودع، فالرمز الواحد يصلح لطلبات
     الصفحة كلها. وقراءة الترويسة احتياط لو فعل يوما: هذه الشاشة ترسل
     ثلاثة طلبات أو أربعة (تأكيد · إعادة · تبديل)، ومع التدوير يرد الثاني
     منها 403 — فيبدو «أعد الإرسال» كأنه يعمل مرة ثم يصمت. */
  function post(url, data) {
    var body = new URLSearchParams(CSRF);
    Object.keys(data || {}).forEach(function (k) { body.set(k, data[k]); });

    return fetch(url, {
      method: 'POST', body: body, credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    }).then(function (r) {
      var fresh = r.headers.get('X-CSRF-Token');
      if (fresh) { Object.keys(CSRF).forEach(function (k) { CSRF[k] = fresh; }); }
      return r.json().catch(function () {
        return { ok: false, error: 'رد غير مفهوم من الخادم. أعد تحميل الصفحة.' };
      });
    });
  }

  /* ── التأكيد ───────────────────────────────────────────────────── */
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    say('');

    var code = (input.value || '').replace(/[^0-9]/g, '');
    if (code.length !== 6) { say('الرمز ست خانات رقمية.'); input.focus(); return; }

    var btn = form.querySelector('[type=submit]');
    btn.disabled = true; btn.classList.add('is-busy');

    post(URLS.verify, { code: code })
      .then(function (j) {
        if (j && j.ok) { location.href = j.next || URLS.login; return; }
        say((j && j.error) || 'تعذر التأكيد.');
        /* جلسة انتهت: البقاء في الصفحة عبث — لا شيء يقبل بعدها. */
        if (j && j.expired) { setTimeout(function () { location.href = URLS.login; }, 2500); }
        input.select();
      })
      .catch(function () { say('تعذر الاتصال. حاول مرة أخرى.'); })
      .finally(function () { btn.disabled = false; btn.classList.remove('is-busy'); });
  });

  /* لصق الرمز من الرسالة: يقبل ما فيه فراغات أو محارف عربية. */
  input.addEventListener('input', function () {
    var v = (input.value || '').replace(/[^0-9٠-٩]/g, '')
              .replace(/[٠-٩]/g, function (d) {
                return String.fromCharCode(d.charCodeAt(0) - 0x0660 + 48);
              });
    if (v !== input.value) input.value = v;
  });

  /* ── إعادة الإرسال وتبديل القناة ───────────────────────────────
     الزر يقفل مدة يقولها الخادم لا مدة يخمنها المتصفح: الخنق في
     `Taqdar_otp_model`، ومهلة في الصفحة تخالفه تجعل النقر يصطدم برفض
     صامت. */
  var cooling = 0, timer = null;

  function cool(seconds) {
    cooling = Math.max(0, seconds | 0);
    if (timer) clearInterval(timer);
    if (!cooling) { free(); return; }

    lock();
    timer = setInterval(function () {
      cooling--;
      if (cooling <= 0) { clearInterval(timer); free(); return; }
      state.textContent = ' — بعد ' + cooling + ' ثانية';
    }, 1000);
    state.textContent = ' — بعد ' + cooling + ' ثانية';
  }

  function lock() {
    resend.setAttribute('aria-disabled', 'true');
    resend.style.pointerEvents = 'none';
    resend.style.opacity = '.55';
    document.querySelectorAll('.otp-switch__b').forEach(function (b) { b.disabled = true; });
  }
  function free() {
    resend.removeAttribute('aria-disabled');
    resend.style.pointerEvents = '';
    resend.style.opacity = '';
    state.textContent = '';
    document.querySelectorAll('.otp-switch__b').forEach(function (b) { b.disabled = false; });
  }

  function sent(j) {
    if (j && j.ok) {
      say('');
      state.textContent = ' — أرسلنا رمزا جديدا.';
      if (dest && j.shown) {
        var b = dest.querySelector('b');
        if (b) b.textContent = j.shown;
        var what = dest.querySelector('.tq-dest-what');
        if (what) what.remove();
        var use = dest.querySelector('use');
        if (use) use.setAttribute('href', j.channel === 'whatsapp' ? '#i-whatsapp' : '#i-mail');
      }
      cool(j.retry_after || 60);
      input.value = ''; input.focus();
    } else {
      say((j && j.error) || 'تعذر الإرسال.');
      if (j && j.expired) { setTimeout(function () { location.href = URLS.login; }, 2500); }
      cool((j && j.retry_after) || 0);
    }
  }

  resend.addEventListener('click', function (e) {
    e.preventDefault();
    if (cooling) return;
    lock();
    state.textContent = ' — جار الإرسال…';
    post(URLS.resend, {})
      .then(sent)
      .catch(function () { say('تعذر الاتصال.'); free(); });
  });

  document.querySelectorAll('.otp-switch__b').forEach(function (b) {
    b.addEventListener('click', function () {
      if (cooling) return;
      lock();
      state.textContent = ' — جار الإرسال…';
      post(URLS.channel, { channel: b.dataset.chan })
        .then(function (j) {
          sent(j);
          /* القناة تبدلت فعلا: الصفحة تعاد لتبنى أزرار التبديل من جديد
             — وإلا بقي الزر يعرض القناة التي صرنا عليها. */
          if (j && j.ok) setTimeout(function () { location.reload(); }, 1200);
        })
        .catch(function () { say('تعذر الاتصال.'); free(); });
    });
  });

  <?php if ($tq_state && (int) $tq_state['resend_in'] > 0): ?>
  cool(<?php echo (int) $tq_state['resend_in']; ?>);
  <?php endif; ?>
})();
</script>
