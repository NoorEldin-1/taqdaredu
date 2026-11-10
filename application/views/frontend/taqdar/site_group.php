<?php
defined('BASEPATH') or exit('No direct script access allowed');
/**
 * TQ-GROUP — `/group/<slug>`: صفحة مجموعة بجدولها ومقاعدها وزر الانضمام.
 *
 * الجدول يعرض كاملا **قبل** الشراء: العضوية تباع بجدول، ومن لم يره يشتري
 * مواعيد لا يعرف أنها تناسبه. ورابط اللقاء لا يطبع هنا أبدا — الدخول من
 * بوابة العضو وحده. والزر يقرأ `offer()` نفسها التي يقرؤها محرك الشراء.
 *
 * والهيكل هيكل صفحة مسار التأسيس (`fndp-*`): لوحات في العمود الرئيسي،
 * وبطاقة حجز لاصقة بقوسها الذهبي في الجانب — فالقسمان المتجاوران في
 * الرئيسية يفتحان على صفحتين من عائلة واحدة.
 */
$g    = isset($tq_group) ? $tq_group : null;
$sum  = isset($tq_sum) ? $tq_sum : null;
$rows = isset($tq_sessions) ? (array) $tq_sessions : array();
if (!$g || !$sum) return;
$member = !empty($tq_member);
$CI = get_instance();

$tq_h1   = $sum['title'];
$tq_lead = t('حصص مباشرة مع معلمك ومجموعة صغيرة من زملائك، بجدول معلن قبل أن تنضم.');
include __DIR__ . '/site/site_pagehero.php';

$tq_days = array(t('الأحد'), t('الاثنين'), t('الثلاثاء'), t('الأربعاء'), t('الخميس'), t('الجمعة'), t('السبت'));
$tq_per  = (int) ($sum['per_session_halalas'] ?? 0);
$tq_now  = time();
?>

<section class="section fndp-track" id="group">
  <div class="shell">
    <?php if ($m = $CI->session->flashdata('error_message')): ?>
      <p class="tq-flash tq-flash--err" role="alert"><?php echo html_escape($m); ?></p>
    <?php endif; ?>

    <div class="fndp-cols">
      <div class="fndp-main">

        <?php if ($sum['description'] !== ''): ?>
        <article class="fndp-panel reveal" aria-labelledby="grp-about">
          <header class="fndp-panel__head">
            <span class="fndp-panel__ico" aria-hidden="true"><svg><use href="#i-book"></use></svg></span>
            <h2 id="grp-about"><?php echo t('عن المجموعة'); ?></h2>
          </header>
          <p class="fndp-lede"><?php echo html_escape($sum['description']); ?></p>
        </article>
        <?php endif; ?>

        <section class="fndp-panel reveal" aria-labelledby="grp-sched">
          <header class="fndp-panel__head">
            <span class="fndp-panel__ico" aria-hidden="true"><svg><use href="#i-calendar"></use></svg></span>
            <h2 id="grp-sched"><?php echo t('الجدول'); ?></h2>
          </header>
          <?php if (!$rows): ?>
            <p class="fndp-lede"><?php echo t('لم تجدول حصص هذه المجموعة بعد. تصلك مواعيدها حين تجدول.'); ?></p>
          <?php else: ?>
            <p class="grp26-tz"><?php echo t('المواعيد بتوقيت مكة'); ?></p>
            <ol class="grp26-sched">
              <?php $tq_n = 0; foreach ($rows as $s):
                  $ts  = strtotime($s['starts_at']);
                  $off = $s['status'] === 'cancelled';
                  $past = !$off && $ts + 60 * (int) $s['duration_min'] < $tq_now;
                  $tq_n++; ?>
                <li class="grp26-sched__row<?php echo $off ? ' is-off' : ($past ? ' is-past' : ''); ?>">
                  <span class="grp26-sched__n" aria-hidden="true"><?php echo $tq_n; ?></span>
                  <span class="grp26-sched__day"><?php echo html_escape($tq_days[(int) date('w', $ts)]); ?></span>
                  <b class="grp26-sched__date tq-ltr"><?php echo date('Y-m-d', $ts); ?></b>
                  <b class="grp26-sched__time tq-ltr"><?php echo date('H:i', $ts); ?></b>
                  <span class="grp26-sched__len"><?php echo (int) $s['duration_min']; ?> <?php echo t('دقيقة'); ?></span>
                  <?php if ($off): ?><span class="grp26-sched__tag"><?php echo t('ملغاة'); ?></span>
                  <?php elseif ($past): ?><span class="grp26-sched__tag"><?php echo t('انتهت'); ?></span><?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ol>
          <?php endif; ?>
        </section>

        <section class="fndp-panel fndp-panel--flat reveal" aria-labelledby="grp-how">
          <header class="fndp-panel__head">
            <span class="fndp-panel__ico" aria-hidden="true"><svg><use href="#i-clipboard"></use></svg></span>
            <h2 id="grp-how"><?php echo t('كيف تعمل المجموعة'); ?></h2>
          </header>
          <ol class="fndp-mini">
            <li><b><?php echo t('تنضم مرة'); ?></b><span><?php echo t('تدفع ثمن المقعد مرة واحدة، فتصير عضوا في الجدول كله.'); ?></span></li>
            <li><b><?php echo t('تدخل من بوابتك'); ?></b><span><?php echo t('يفتح زر الدخول قبل كل حصة بدقائق.'); ?></span></li>
            <li><b><?php echo t('التسجيل يبقى لك'); ?></b><span><?php echo t('فاتتك حصة؟ تسجيلها في «مجموعاتي».'); ?></span></li>
          </ol>
        </section>

        <aside class="fndp-note">
          <span class="fndp-note__ico" aria-hidden="true"><svg><use href="#i-shield"></use></svg></span>
          <div class="fndp-note__txt">
            <h3><?php echo t('إن ألغيت المجموعة قبل أول حصة'); ?></h3>
            <p><?php echo t('تلغى عضويتك ويرد إليك ما دفعت — والرد يتم يدويا خارج المنصة بعد التواصل معك، لا تلقائيا إلى بطاقتك.'); ?></p>
          </div>
        </aside>
      </div>

      <aside class="fndp-side">
        <div class="fndp-book">
          <?php echo tqs_group_media($sum, 'fnd26-card__media fndp-book__media'); ?>
          <h2 class="fndp-book__name"><?php echo html_escape($sum['title']); ?></h2>

          <p class="fndp-book__price">
            <?php echo tqs_money($tq_per > 0 ? $tq_per : (int) $sum['price_halalas']); ?>
            <?php if ($tq_per > 0): ?><small><?php echo t('للحصة الواحدة'); ?></small><?php endif; ?>
          </p>
          <?php if ($tq_per > 0): ?>
            <p class="fndp-book__pack">
              <b><?php echo tqs_money((int) $sum['price_halalas']); ?> <?php echo t('للجدول كله'); ?></b>
              <span><?php echo t('تدفع مرة واحدة'); ?></span>
            </p>
          <?php endif; ?>

          <ul class="fndp-book__facts">
            <li><svg aria-hidden="true"><use href="#i-calendar"></use></svg>
              <span><?php echo html_escape(tq_count_units((int) $sum['sessions_total'], 'حصة', 'حصتان', 'حصتين', 'حصص', 'حصة', '', 'nom')); ?></span></li>
            <?php if (!empty($sum['next_starts_at'])): $nx = strtotime($sum['next_starts_at']); ?>
              <li><svg aria-hidden="true"><use href="#i-clock"></use></svg>
                <span><?php echo t('أقرب حصة'); ?> <b class="tq-ltr"><?php echo date('Y-m-d H:i', $nx); ?></b></span></li>
            <?php endif; ?>
            <li><svg aria-hidden="true"><use href="#i-users"></use></svg><span><?php echo html_escape($sum['seats_text']); ?></span></li>
          </ul>

          <p class="fndp-book__status<?php echo $sum['sellable'] ? ' is-open' : ''; ?>">
            <i aria-hidden="true"></i>
            <span><?php echo $sum['sellable']
                ? ($sum['enroll_until'] ? t('الانضمام مفتوح حتى') . ' <b class="tq-ltr">' . html_escape(substr($sum['enroll_until'], 0, 10)) . '</b>' : t('الانضمام مفتوح'))
                : html_escape($sum['why']); ?></span>
          </p>

          <?php if ($member): ?>
            <a class="fndp-book__cta" href="<?php echo base_url('student/groups'); ?>"><?php echo t('أنت عضو — افتح مجموعاتي'); ?>
              <svg class="dir-icon" aria-hidden="true"><use href="#i-arrow"></use></svg></a>
          <?php elseif ($sum['sellable']): ?>
            <a class="fndp-book__cta" href="<?php echo base_url('group-checkout/' . (int) $sum['id']); ?>"><?php echo t('انضم الآن'); ?>
              <svg class="dir-icon" aria-hidden="true"><use href="#i-arrow"></use></svg></a>
          <?php endif; ?>

          <p class="fndp-book__note">
            <?php echo t('المجموعة مستقلة عن الباقات: لا تشترط اشتراكا.'); ?>
            <a href="<?php echo base_url('groups'); ?>"><?php echo t('كل المجموعات'); ?></a>
          </p>
        </div>
      </aside>
    </div>
  </div>
</section>
