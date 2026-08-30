<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * ادفع عن ابنك — `B4.6` و`F4.3`.
 *
 * كان هذا الباب **مغلقا صراحة** لا ناقصا: `Taqdar::checkout()` يرد كل من
 * ليس طالبا برسالة «الاشتراك في الباقات لحسابات الطلاب». فولي الأمر —
 * وهو من يدفع فعلا في أكثر الأسر — لا يستطيع الشراء لابنه بحال، ويبقى
 * أمامه أن يدخل بحساب ابنه أو يعطيه بطاقته.
 *
 * **والاشتراك يكتب باسم الابن لا باسم الأب.** هو صاحب المحتوى، وعليه
 * يقاس التقدم، وله تجسد صفوف `enrol`. وولي الأمر يدفع ولا يملك — وهذا
 * ما تعنيه الوثيقة بـ«صلاحية إنشاء فقط، لا قراءة زيادة»: هذه الشاشة
 * تنشئ ولا تفتح شيئا من بيانات الابن لم يكن مفتوحا قبلها.
 *
 * **وتدفق منفصل واضح**: الشاشة تقول في كل خطوة لمن يشترى، ولا تشبه شاشة
 * شراء الطالب حتى لا يظن الأب أنه يشتري لنفسه.
 */
include 'tq_student_styles.php';

$tq_nav   = 'pay';
$tq_role  = 'parent';
$tq_title = t('ادفع عن ابنك');
$tq_sub   = t('اشتر باقة باسم ابنك. الاشتراك يفتح في حسابه هو، والفاتورة تصلك أنت.');
$tq_icon  = 'wallet';

$CI  = &get_instance();
$pid = isset($user_id) ? (int) $user_id : (int) $CI->session->userdata('user_id');

$CI->load->model('taqdar_parent_model', 'tq_par');
$CI->load->model('taqdar_billing_model', 'tq_bill');
$CI->load->model('taqdar_tap_model', 'tq_tap');

$tq_kids  = $CI->tq_par->children($pid);
$tq_plans = array();
try {
    /* الباقات المعروضة هي `scope = 'grade'` وحدها — القاعدة نفسها التي
       ترشح بها `/plans` و`tqs_bundles()`. وباقة بنطاق آخر تمنح من اللوحة
       ولا تظهر في شاشة شراء. */
    $tq_plans = array_values(array_filter($CI->tq_bill->plans(), function ($p) {
        return ($p['scope'] ?? '') === 'grade' && (int) ($p['active'] ?? 0) === 1;
    }));
} catch (Throwable $e) { $tq_plans = array(); }

$tq_card_ready = false;
try { $tq_card_ready = (bool) $CI->tq_tap->ready(); } catch (Throwable $e) {}

/* فاتورة تنتظر الدفع بالبطاقة — تعود إليها الشاشة بعد الإنشاء. */
$tq_inv = (int) $CI->input->get('invoice');
$tq_inv_row = null;
if ($tq_inv > 0) {
    try {
        $tq_inv_row = $CI->db->query(
            'SELECT i.`id`, i.`total`, i.`status`, i.`user_id`,
                    TRIM(CONCAT(COALESCE(u.`first_name`,""), " ", COALESCE(u.`last_name`,""))) AS holder
               FROM `invoices` i
               JOIN `parent_links` pl ON pl.`student_id` = i.`user_id`
                                     AND pl.`parent_user_id` = ? AND pl.`status` = "active"
               LEFT JOIN `users` u ON u.`id` = i.`user_id`
              WHERE i.`id` = ? LIMIT 1', array($pid, $tq_inv))->row_array();
    } catch (Throwable $e) { $tq_inv_row = null; }
}

include 'portal_open.php';
?>

<?php if (!$tq_kids): ?>

  <section class="tq-card">
    <?php echo tq_s_empty('users', 'sand', t('لا أبناء مرتبطين بحسابك'),
          t('اربط ابنك أولا — يصله طلب موافقة، ولا تفتح بياناته ولا يشترى له قبل أن يوافق.'),
          t('اربط ابنا'), base_url('parent'), false, 'primary'); ?>
  </section>

<?php else: ?>

  <?php /* ── فاتورة تنتظر ────────────────────────────────────────────
          تعرض قبل النموذج: من عاد إلى هذه الشاشة بعد الإنشاء يريد أن
          يكمل دفعه لا أن يبدأ شراء ثانيا. */ ?>
  <?php if ($tq_inv_row && $tq_inv_row['status'] !== 'paid'): ?>
    <section class="tq-card tq-pp-due" style="margin-block-end:var(--tq-space-l)">
      <div class="tq-card__head">
        <h2 class="tq-card__title"><?php echo t('فاتورة تنتظر الدفع'); ?></h2>
        <span class="tq-badge tq-badge--due"><?php echo t('غير مدفوعة'); ?></span>
      </div>
      <p class="tq-body">
        <?php echo t('فاتورة باسم'); ?> <strong><?php echo html_escape($tq_inv_row['holder'] ?: t('ابنك')); ?></strong>
        بقيمة <?php echo tq_num(number_format((int) $tq_inv_row['total'] / 100, 2)); ?> ريال.
      </p>

      <?php if ($tq_card_ready): ?>
        <form method="post" action="<?php echo base_url('student/pay-invoice'); ?>">
          <?php echo tq_csrf(); ?>
          <input type="hidden" name="invoice_id" value="<?php echo (int) $tq_inv_row['id']; ?>">
          <button class="tq-btn tq-btn--primary" type="submit"><?php echo t('ادفع الآن بالبطاقة'); ?></button>
        </form>
      <?php else: ?>
        <p class="tq-caption">
          <?php echo t('الدفع بالبطاقة غير مفعل حاليا. حول قيمة الفاتورة بنكيا — تعليمات التحويل في'); ?> <a href="<?php echo base_url('parent/payments'); ?>"><?php echo t('المدفوعات'); ?></a>.
        </p>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <form method="post" action="<?php echo base_url('parent/pay/start'); ?>" class="tq-pp">
    <?php echo tq_csrf(); ?>

    <?php /* ── 1 · لمن ─────────────────────────────────────────────
            أول سؤال وأوضحه. وشاشة شراء لا تقول لمن تشتري هي بعينها ما
            يجعل الأب يشتري باقة ابنه الأصغر لابنه الأكبر. */ ?>
    <section class="tq-card">
      <div class="tq-card__head">
        <h2 class="tq-card__title"><?php echo t('لمن تشتري؟'); ?></h2>
      </div>

      <div class="tq-pp-kids" role="radiogroup" aria-label="<?php echo te('اختر الابن'); ?>">
        <?php foreach ($tq_kids as $i => $k):
          /* معرف الطالب لا معرف الرابط: `links()` ترجع `pl.id` في `id`،
             و`parent_owns_child()` تفحص `student_id` — فقيمة الرابط هنا
             كانت ترد كل شراء برسالة «غير مرتبط بحسابك». */
          $kid = (int) ($k['student_id'] ?? 0);
          if ($kid < 1) continue;
          $name = trim(($k['first_name'] ?? '') . ' ' . ($k['last_name'] ?? '')) ?: t('ابنك');
        ?>
          <label class="tq-pick<?php echo $i === 0 ? ' is-on' : ''; ?>">
            <input type="radio" name="child_id" value="<?php echo $kid; ?>"
                   <?php echo $i === 0 ? ' checked' : ''; ?> required>
            <span class="tq-pick__label"><?php echo html_escape($name); ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </section>

    <?php /* ── 2 · أي باقة ──────────────────────────────────────── */ ?>
    <section class="tq-card">
      <div class="tq-card__head">
        <h2 class="tq-card__title"><?php echo t('أي باقة؟'); ?></h2>
        <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('plans'); ?>"
           target="_blank" rel="noopener"><?php echo t('قارن الباقات'); ?></a>
      </div>

      <?php if (!$tq_plans): ?>
        <?php echo tq_s_empty('card', 'sand', t('لا باقات معروضة الآن'),
              t('تظهر الباقات هنا حالما تنشر. تواصل مع الإدارة إن كنت تنتظر باقة بعينها.'), '', '', true); ?>
      <?php else: ?>
        <div class="tq-pp-plans" role="radiogroup" aria-label="<?php echo te('اختر الباقة'); ?>">
          <?php foreach ($tq_plans as $i => $p): ?>
            <label class="tq-pp-plan<?php echo $i === 0 ? ' is-on' : ''; ?>">
              <input type="radio" name="plan_id" value="<?php echo (int) $p['id']; ?>"
                     <?php echo $i === 0 ? ' checked' : ''; ?> required>
              <span class="tq-pp-plan__t"><?php echo html_escape($p['name_ar']); ?></span>
              <?php /* المرحلة باسمها لا بمسماها: `plans.stage` يحمل `primary`،
                       وطباعته خاما تضع كلمة لاتينية وسط شاشة عربية —
                       و`tqs_stage_label()` هي الموضع الواحد لأسمائها. */ ?>
              <?php if (!empty($p['stage'])): ?>
                <span class="tq-pp-plan__s"><?php echo html_escape(tqs_stage_label((string) $p['stage'])); ?></span>
              <?php endif; ?>
              <?php /* السعر هنا **ما يدفع فعلا** لا معادلا شهريا: هذه شاشة
                       إصدار فاتورة، والرقم فيها هو رقم الفاتورة. ومعه
                       دورته — «٩٩٩ ريال» بلا «كل ٣٠ يوما» تقرأ سنويا. */ ?>
              <span class="tq-pp-plan__p"><?php
                echo (int) $p['price'] > 0
                   ? tq_num(number_format((int) $p['price'] / 100, 0)) . t('ريال')
                   : t('مجانية'); ?><?php
                $tq_pp = tqs_plan_price(array('price' => (int) $p['price'],
                                              'period' => (string) $p['period'],
                                              'days' => (int) $p['duration_days']));
                if (!$tq_pp['free'] && $tq_pp['unit'] !== ''):
                ?><small> / <?php echo html_escape($tq_pp['unit']); ?></small><?php endif; ?></span>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <?php /* ── 2ب · لكم مدة ────────────────────────────────────── */ ?>
    <?php
    /* TQ-CYCLE-BUY — **باب الشراء الثاني يعرف الدورة كما يعرفها الأول.**
       `parent_pay_start()` تمرر `cycle` إلى `subscribe()`، وشاشة بلا هذا
       الحقل ترسل فارغا — فيرتد إلى دورة الباقة، ويبقى ولي الأمر لا يملك
       شراء الشهري أبدا مهما عرضته عليه صفحة الباقات.

       والمعروض هنا الدورات الممكنة لكل باقة معا، ويخفي السكربت ما لا
       يخص المختارة. وبلا سكربت تظهر كلها بأسماء باقاتها — أطول قليلا
       ولا يضل أحد: الاسم مكتوب في كل خيار. */
    $tq_cyc_map = array();
    if (!empty($tq_plans)) {
        $tq_bci = &get_instance();
        $tq_bci->load->model('taqdar_billing_model');
        foreach ($tq_plans as $p) {
            $tq_cyc_map[(int) $p['id']] = $tq_bci->taqdar_billing_model->plan_cycles($p);
        }
    }
    $tq_any_alt = false;
    foreach ($tq_cyc_map as $cs) { if (count($cs) > 1) { $tq_any_alt = true; break; } }
    ?>
    <?php if ($tq_any_alt): ?>
    <section class="tq-card" data-tq-cycles>
      <div class="tq-card__head">
        <h2 class="tq-card__title"><?php echo t('لكم مدة؟'); ?></h2>
      </div>
      <div class="tq-pp-kids" role="radiogroup" aria-label="<?php echo te('مدة الاشتراك'); ?>">
        <?php $tq_first = true; foreach ($tq_cyc_map as $tq_pid => $tq_cs): ?>
          <?php foreach ($tq_cs as $tq_k => $tq_c): ?>
            <label class="tq-pick<?php echo $tq_first ? ' is-on' : ''; ?>"
                   data-tq-for-plan="<?php echo (int) $tq_pid; ?>">
              <input type="radio" name="cycle" value="<?php echo html_escape((string) $tq_k); ?>"
                     <?php echo $tq_first ? ' checked' : ''; ?>>
              <span class="tq-pick__label"><?php echo html_escape((string) $tq_c['label']); ?></span>
              <span class="tq-pick__note">
                <?php echo tq_num(number_format($tq_c['price'] / 100, 0)); ?> ريال —
                يفتح <?php echo (int) $tq_c['days']; ?> يوما، بلا تجديد تلقائي
              </span>
            </label>
          <?php $tq_first = false; endforeach; ?>
        <?php endforeach; ?>
      </div>
    </section>
    <script>
    /* تحسين تدريجي: يخفي دورات الباقات غير المختارة ويعلم أول ما بقي.
       وبلا هذا الملف تظهر الدورات كلها ويختار ولي الأمر بنفسه — والخادم
       يحرس على كل حال (`cycle_of()` ترتد إلى دورة الباقة). */
    (function () {
      var form = document.querySelector('form');
      var box  = document.querySelector('[data-tq-cycles]');
      if (!form || !box) return;
      var opts = box.querySelectorAll('[data-tq-for-plan]');

      function sync() {
        var p = form.querySelector('input[name="plan_id"]:checked');
        var id = p ? p.value : '';
        var first = null, hasChecked = false;
        Array.prototype.forEach.call(opts, function (o) {
          var on = (o.getAttribute('data-tq-for-plan') === id);
          o.hidden = !on;
          var r = o.querySelector('input');
          if (!on) { r.checked = false; return; }
          if (!first) first = r;
          if (r.checked) hasChecked = true;
        });
        if (first && !hasChecked) first.checked = true;
        Array.prototype.forEach.call(opts, function (o) {
          o.classList.toggle('is-on', o.querySelector('input').checked);
        });
        box.hidden = !first || box.querySelectorAll('[data-tq-for-plan]:not([hidden])').length < 2;
      }

      form.addEventListener('change', function (e) {
        if (e.target && e.target.name === 'plan_id') sync();
        if (e.target && e.target.name === 'cycle')  sync();
      });
      sync();
    })();
    </script>
    <?php endif; ?>

    <?php /* ── 3 · كيف تدفع ─────────────────────────────────────── */ ?>
    <section class="tq-card">
      <div class="tq-card__head">
        <h2 class="tq-card__title"><?php echo t('كيف تدفع؟'); ?></h2>
      </div>

      <div class="tq-pp-kids" role="radiogroup" aria-label="<?php echo te('طريقة الدفع'); ?>">
        <?php if ($tq_card_ready): ?>
          <label class="tq-pick is-on">
            <input type="radio" name="method" value="card" checked>
            <span class="tq-pick__label"><?php echo t('بطاقة'); ?></span>
            <span class="tq-pick__note"><?php echo t('يفتح الاشتراك فور نجاح الدفع'); ?></span>
          </label>
        <?php endif; ?>
        <label class="tq-pick<?php echo $tq_card_ready ? '' : ' is-on'; ?>">
          <input type="radio" name="method" value="bank" <?php echo $tq_card_ready ? '' : ' checked'; ?>>
          <span class="tq-pick__label"><?php echo t('تحويل بنكي'); ?></span>
          <span class="tq-pick__note"><?php echo t('يفتح بعد أن تعتمد الإدارة التحويل'); ?></span>
        </label>
      </div>

      <p class="tq-caption" style="margin-block-start:var(--tq-space-l)">
        <?php echo t('الاشتراك يفتح في حساب ابنك، ويظهر في تقاريره هو. والفاتورة تسجل في مدفوعاتك.'); ?>
      </p>

      <div class="tq-formbar">
        <button class="tq-btn tq-btn--primary" type="submit"
                <?php echo $tq_plans ? '' : ' disabled'; ?>><?php echo t('أصدر الفاتورة'); ?></button>
      </div>
    </section>
  </form>

  <?php
  /* ══ TQ-COURSE-SALE — كورس مفرد لابنك ═══════════════════════════════
     **نموذج ثان مستقل، لا خطوة في الأول.** والسبب أن المشترى مختلف
     اختلافا يغير كل خطوة بعده: الباقة لها دورة ومدة وصفوف، والكورس
     المفرد ليس له إلا سعر وأجل. وحشرهما في نموذج واحد يعني حقولا تظهر
     وتختفي بحسب اختيار أول — وهي الشاشة التي يضغط فيها الأب «أصدر
     الفاتورة» فيشتري غير ما ظن.

     ولا يعرض إلا إن كان في المنصة كورس معروض فعلا: قسم فارغ يقول «لا
     كورسات تباع مفردة» يزحم شاشة شراء ليعتذر. */
  $tq_pc_ci = &get_instance();
  $tq_pc_ci->load->model('taqdar_course_sale_model', 'tq_cs');
  $tq_pc_offers = $tq_pc_ci->tq_cs->offers(true);
  if ($tq_pc_offers):
  ?>
  <form method="post" action="<?php echo base_url('parent/pay/course'); ?>" class="tq-pp"
        style="margin-block-start:var(--tq-space-xl)">
    <?php echo tq_csrf(); ?>

    <section class="tq-card">
      <div class="tq-card__head">
        <h2 class="tq-card__title"><?php echo t('أو اشتر كورسا مفردا'); ?></h2>
        <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('catalog?type=course'); ?>"
           target="_blank" rel="noopener"><?php echo t('تصفح الكورسات'); ?></a>
      </div>

      <p class="tq-caption">
        <?php echo t('الكورس المفرد يفتح'); ?> <strong><?php echo t('هذه المادة وحدها'); ?></strong> <?php echo t('لا منهج المرحلة، ولا يجدد تلقائيا. وهو مستقل عن الباقة: من له باقة سارية يشتريه فوقها، ومن لا باقة له يشتريه بلا اشتراك.'); ?>
      </p>

      <?php /* «لمن» يسأل هنا كذلك لا يقرأ من النموذج الأول: نموذجان
               منفصلان لا يتشاركان حقلا، وقراءة اختيار من نموذج آخر تعني
               أن الأب يبدل الابن في الأعلى ويشتري للأول في الأسفل. */ ?>
      <div class="tq-pp-kids" role="radiogroup" aria-label="<?php echo te('اختر الابن'); ?>">
        <?php foreach ($tq_kids as $i => $k):
          $kid = (int) ($k['student_id'] ?? 0);
          if ($kid < 1) continue;
          $name = trim(($k['first_name'] ?? '') . ' ' . ($k['last_name'] ?? '')) ?: t('ابنك');
        ?>
          <label class="tq-pick<?php echo $i === 0 ? ' is-on' : ''; ?>">
            <input type="radio" name="child_id" value="<?php echo $kid; ?>"
                   <?php echo $i === 0 ? ' checked' : ''; ?> required>
            <span class="tq-pick__label"><?php echo html_escape($name); ?></span>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="tq-pp-plans" role="radiogroup" aria-label="<?php echo te('اختر الكورس'); ?>"
           style="margin-block-start:var(--tq-space-l)">
        <?php $tq_pc_first = true; foreach ($tq_pc_offers as $tq_pc_id => $tq_pc_o): ?>
          <label class="tq-pp-plan<?php echo $tq_pc_first ? ' is-on' : ''; ?>">
            <input type="radio" name="course_id" value="<?php echo (int) $tq_pc_id; ?>"
                   <?php echo $tq_pc_first ? ' checked' : ''; ?> required>
            <span class="tq-pp-plan__t"><?php echo html_escape($tq_pc_o['title']); ?></span>
            <span class="tq-pp-plan__s"><?php echo (int) $tq_pc_o['days'] > 0
                ? t('وصول') . (int) round($tq_pc_o['days'] / 30) . t('شهرا')
                : t('وصول دائم'); ?></span>
            <?php /* السعر هنا **ما يدفع فعلا** لا معادلا: هذه شاشة إصدار
                     فاتورة، والرقم فيها هو رقم الفاتورة. */ ?>
            <span class="tq-pp-plan__p"><?php
              echo tq_num(number_format($tq_pc_o['price'] / 100, 0)); ?> ريال</span>
          </label>
        <?php $tq_pc_first = false; endforeach; ?>
      </div>

      <div class="tq-pp-kids" role="radiogroup" aria-label="<?php echo te('طريقة الدفع'); ?>"
           style="margin-block-start:var(--tq-space-l)">
        <?php if ($tq_card_ready): ?>
          <label class="tq-pick is-on">
            <input type="radio" name="method" value="card" checked>
            <span class="tq-pick__label"><?php echo t('بطاقة'); ?></span>
            <span class="tq-pick__note"><?php echo t('يفتح الكورس فور نجاح الدفع'); ?></span>
          </label>
        <?php endif; ?>
        <label class="tq-pick<?php echo $tq_card_ready ? '' : ' is-on'; ?>">
          <input type="radio" name="method" value="bank" <?php echo $tq_card_ready ? '' : ' checked'; ?>>
          <span class="tq-pick__label"><?php echo t('تحويل بنكي'); ?></span>
          <span class="tq-pick__note"><?php echo t('يفتح بعد أن تعتمد الإدارة التحويل'); ?></span>
        </label>
      </div>

      <p class="tq-caption" style="margin-block-start:var(--tq-space-l)">
        <?php echo t('الكورس يفتح في حساب ابنك، ويظهر في تقاريره هو. والفاتورة تسجل في مدفوعاتك.'); ?>
      </p>

      <div class="tq-formbar">
        <button class="tq-btn tq-btn--primary" type="submit"><?php echo t('أصدر فاتورة الكورس'); ?></button>
      </div>
    </section>
  </form>
  <?php endif; ?>

<?php endif; ?>

<style>
.tq-pp { display: flex; flex-direction: column; gap: var(--tq-space-l); }
.tq-pp-due { border-color: color-mix(in srgb, var(--tq-amber) 40%, transparent); }

.tq-pp-kids { display: flex; flex-wrap: wrap; gap: var(--tq-space-s); }

.tq-pp-plans {
  display: grid; gap: var(--tq-space-s);
  grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
}
.tq-pp-plan {
  position: relative; display: flex; flex-direction: column; gap: 3px;
  padding: var(--tq-space-l);
  border: 1px solid var(--tq-line); border-radius: var(--tq-radius-small);
  background: var(--tq-surface); cursor: pointer;
  transition: border-color 140ms, background 140ms;
}
.tq-pp-plan input { position: absolute; opacity: 0; inset-block-start: 0; inset-inline-start: 0; }
.tq-pp-plan__t { font-weight: 700; }
.tq-pp-plan__s { font-size: .78rem; color: var(--tq-text3); }
.tq-pp-plan__p { font-weight: 800; color: var(--tq-teal); margin-block-start: var(--tq-space-xs); }
.tq-pp-plan:hover { border-color: var(--tq-tealLift); }
.tq-pp-plan:has(input:checked) { border-color: var(--tq-teal); background: var(--tq-mint-fill); }
.tq-pp-plan.is-on { border-color: var(--tq-teal); background: var(--tq-mint-fill); }
.tq-pp-plan:has(input:focus-visible) { outline: 2px solid var(--tq-focusRing); outline-offset: 2px; }
</style>

<script>
/* تظليل الاختيار في متصفح بلا `:has`. والنموذج يعمل كاملا بلا هذا.
   و**كل** نموذج `.tq-pp` لا أوله: صار في الصفحة نموذجان — الباقة والكورس
   المفرد (TQ-COURSE-SALE) — واسما `child_id` و`method` مكرران بينهما.
   و`querySelector` يمسك الأول وحده، فيبقى الثاني بلا تظليل؛ والأسوأ أن
   بحث الأقران داخل النموذج نفسه لا خارجه، وإلا ظلل اختيار أحدهما نظيره
   في الآخر — فيقرأ الأب أنه اختار ابنا في نموذج لم يلمسه. */
(function () {
  var forms = document.querySelectorAll('.tq-pp');
  for (var k = 0; k < forms.length; k++) {
    (function (f) {
      f.addEventListener('change', function (e) {
        var i = e.target;
        if (i.type !== 'radio' || !i.name) return;
        var peers = f.querySelectorAll('input[name="' + i.name + '"]');
        for (var n = 0; n < peers.length; n++) {
          var box = peers[n].closest('.tq-pick, .tq-pp-plan');
          if (box) box.classList.toggle('is-on', peers[n].checked);
        }
      });
    })(forms[k]);
  }
})();
</script>

<?php include 'portal_close.php'; ?>
