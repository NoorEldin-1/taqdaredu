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
$tq_title = 'ادفع عن ابنك';
$tq_sub   = 'اشتر باقة باسم ابنك. الاشتراك يفتح في حسابه هو، والفاتورة تصلك أنت.';
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
    <?php echo tq_s_empty('users', 'sand', 'لا أبناء مرتبطين بحسابك',
          'اربط ابنك أولا — يصله طلب موافقة، ولا تفتح بياناته ولا يشترى له قبل أن يوافق.',
          'اربط ابنا', base_url('parent'), false, 'primary'); ?>
  </section>

<?php else: ?>

  <?php /* ── فاتورة تنتظر ────────────────────────────────────────────
          تعرض قبل النموذج: من عاد إلى هذه الشاشة بعد الإنشاء يريد أن
          يكمل دفعه لا أن يبدأ شراء ثانيا. */ ?>
  <?php if ($tq_inv_row && $tq_inv_row['status'] !== 'paid'): ?>
    <section class="tq-card tq-pp-due" style="margin-block-end:var(--tq-space-l)">
      <div class="tq-card__head">
        <h2 class="tq-card__title">فاتورة تنتظر الدفع</h2>
        <span class="tq-badge tq-badge--due">غير مدفوعة</span>
      </div>
      <p class="tq-body">
        فاتورة باسم <strong><?php echo html_escape($tq_inv_row['holder'] ?: 'ابنك'); ?></strong>
        بقيمة <?php echo tq_num(number_format((int) $tq_inv_row['total'] / 100, 2)); ?> ريال.
      </p>

      <?php if ($tq_card_ready): ?>
        <form method="post" action="<?php echo base_url('student/pay-invoice'); ?>">
          <?php echo tq_csrf(); ?>
          <input type="hidden" name="invoice_id" value="<?php echo (int) $tq_inv_row['id']; ?>">
          <button class="tq-btn tq-btn--primary" type="submit">ادفع الآن بالبطاقة</button>
        </form>
      <?php else: ?>
        <p class="tq-caption">
          الدفع بالبطاقة غير مفعل حاليا. حول قيمة الفاتورة بنكيا — تعليمات التحويل
          في <a href="<?php echo base_url('parent/payments'); ?>">المدفوعات</a>.
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
        <h2 class="tq-card__title">لمن تشتري؟</h2>
      </div>

      <div class="tq-pp-kids" role="radiogroup" aria-label="اختر الابن">
        <?php foreach ($tq_kids as $i => $k):
          /* معرف الطالب لا معرف الرابط: `links()` ترجع `pl.id` في `id`،
             و`parent_owns_child()` تفحص `student_id` — فقيمة الرابط هنا
             كانت ترد كل شراء برسالة «غير مرتبط بحسابك». */
          $kid = (int) ($k['student_id'] ?? 0);
          if ($kid < 1) continue;
          $name = trim(($k['first_name'] ?? '') . ' ' . ($k['last_name'] ?? '')) ?: 'ابنك';
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
        <h2 class="tq-card__title">أي باقة؟</h2>
        <a class="tq-btn tq-btn--ghost tq-btn--sm" href="<?php echo base_url('plans'); ?>"
           target="_blank" rel="noopener">قارن الباقات</a>
      </div>

      <?php if (!$tq_plans): ?>
        <?php echo tq_s_empty('card', 'sand', 'لا باقات معروضة الآن',
              'تظهر الباقات هنا حالما تنشر. تواصل مع الإدارة إن كنت تنتظر باقة بعينها.', '', '', true); ?>
      <?php else: ?>
        <div class="tq-pp-plans" role="radiogroup" aria-label="اختر الباقة">
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
                   ? tq_num(number_format((int) $p['price'] / 100, 0)) . ' ريال'
                   : 'مجانية'; ?><?php
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

    <?php /* ── 3 · كيف تدفع ─────────────────────────────────────── */ ?>
    <section class="tq-card">
      <div class="tq-card__head">
        <h2 class="tq-card__title">كيف تدفع؟</h2>
      </div>

      <div class="tq-pp-kids" role="radiogroup" aria-label="طريقة الدفع">
        <?php if ($tq_card_ready): ?>
          <label class="tq-pick is-on">
            <input type="radio" name="method" value="card" checked>
            <span class="tq-pick__label">بطاقة</span>
            <span class="tq-pick__note">يفتح الاشتراك فور نجاح الدفع</span>
          </label>
        <?php endif; ?>
        <label class="tq-pick<?php echo $tq_card_ready ? '' : ' is-on'; ?>">
          <input type="radio" name="method" value="bank" <?php echo $tq_card_ready ? '' : ' checked'; ?>>
          <span class="tq-pick__label">تحويل بنكي</span>
          <span class="tq-pick__note">يفتح بعد أن تعتمد الإدارة التحويل</span>
        </label>
      </div>

      <p class="tq-caption" style="margin-block-start:var(--tq-space-l)">
        الاشتراك يفتح في حساب ابنك، ويظهر في تقاريره هو. والفاتورة تسجل في مدفوعاتك.
      </p>

      <div class="tq-formbar">
        <button class="tq-btn tq-btn--primary" type="submit"
                <?php echo $tq_plans ? '' : ' disabled'; ?>>أصدر الفاتورة</button>
      </div>
    </section>
  </form>

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
/* تظليل الاختيار في متصفح بلا `:has`. والنموذج يعمل كاملا بلا هذا. */
(function () {
  var f = document.querySelector('.tq-pp');
  if (!f) return;
  f.addEventListener('change', function (e) {
    var i = e.target;
    if (i.type !== 'radio' || !i.name) return;
    var peers = f.querySelectorAll('input[name="' + i.name + '"]');
    for (var n = 0; n < peers.length; n++) {
      var box = peers[n].closest('.tq-pick, .tq-pp-plan');
      if (box) box.classList.toggle('is-on', peers[n].checked);
    }
  });
})();
</script>

<?php include 'portal_close.php'; ?>
