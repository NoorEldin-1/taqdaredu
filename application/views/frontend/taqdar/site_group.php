<?php
defined('BASEPATH') or exit('No direct script access allowed');
/**
 * TQ-GROUP — `/group/<slug>`: صفحة مجموعة بجدولها ومقاعدها وزر الانضمام.
 *
 * الجدول يعرض كاملا **قبل** الشراء: العضوية تباع بجدول، ومن لم يره يشتري
 * مواعيد لا يعرف أنها تناسبه. ورابط اللقاء لا يطبع هنا أبدا — الدخول من
 * بوابة العضو وحده. والزر يقرأ `offer()` نفسها التي يقرؤها محرك الشراء.
 */
$g    = isset($tq_group) ? $tq_group : null;
$sum  = isset($tq_sum) ? $tq_sum : null;
$rows = isset($tq_sessions) ? (array) $tq_sessions : array();
if (!$g || !$sum) return;
$member = !empty($tq_member);
$CI = get_instance();

$tq_h1   = $sum['title'];
$tq_lead = $sum['description'] !== '' ? $sum['description'] : t('مجموعة مباشرة بجدول معلن مع معلمك.');
include __DIR__ . '/site/site_pagehero.php';
?>

<section class="section section--tight">
  <div class="shell">
    <?php if ($m = $CI->session->flashdata('error_message')): ?>
      <p class="tq-flash tq-flash--err" role="alert"><?php echo html_escape($m); ?></p>
    <?php endif; ?>

    <div class="icard">
      <p class="grp-facts">
        <span><b><?php echo tqs_money((int) $sum['price_halalas']); ?></b> <?php echo t('للجدول كله'); ?></span>
        <span><?php echo html_escape(tq_count_units((int) $sum['sessions_total'], 'حصة', 'حصتان', 'حصتين', 'حصص', 'حصة', '', 'nom')); ?></span>
        <span><?php echo html_escape($sum['seats_text']); ?></span>
        <?php if ($sum['enroll_until']): ?>
          <span><?php echo t('الانضمام حتى'); ?> <b class="tq-ltr"><?php echo html_escape(substr($sum['enroll_until'], 0, 10)); ?></b></span>
        <?php endif; ?>
      </p>

      <?php if ($member): ?>
        <a class="btn btn--primary" href="<?php echo base_url('student/groups'); ?>"><?php echo t('أنت عضو — افتح مجموعاتي'); ?></a>
      <?php elseif ($sum['sellable']): ?>
        <a class="btn btn--primary" href="<?php echo base_url('group-checkout/' . (int) $sum['id']); ?>"><?php echo t('انضم الآن'); ?></a>
      <?php else: ?>
        <p class="tq-caption" role="status"><?php echo html_escape($sum['why']); ?></p>
      <?php endif; ?>
    </div>

    <h2><?php echo t('الجدول'); ?></h2>
    <?php if (!$rows): ?>
      <p class="tq-caption"><?php echo t('لم تجدول حصص هذه المجموعة بعد. تصلك مواعيدها حين تجدول.'); ?></p>
    <?php else: ?>
      <ol class="grp-sched">
        <?php foreach ($rows as $s): $off = $s['status'] === 'cancelled'; ?>
          <li class="<?php echo $off ? 'is-off' : ''; ?>">
            <b class="tq-ltr"><?php echo html_escape(date('Y-m-d H:i', strtotime($s['starts_at']) + 3 * 3600)); ?></b>
            <span><?php echo t('(توقيت مكة)'); ?> · <?php echo (int) $s['duration_min']; ?> <?php echo t('دقيقة'); ?></span>
            <?php if (trim((string) $s['title']) !== ''): ?><span><?php echo html_escape($s['title']); ?></span><?php endif; ?>
            <?php if ($off): ?><span><?php echo t('ملغاة'); ?></span><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>

    <aside class="fndp-note">
      <span class="fndp-note__ico" aria-hidden="true"><svg><use href="#i-shield"></use></svg></span>
      <div class="fndp-note__txt">
        <h3><?php echo t('إن ألغيت المجموعة قبل أول حصة'); ?></h3>
        <p><?php echo t('تلغى عضويتك ويرد إليك ما دفعت — والرد يتم يدويا خارج المنصة بعد التواصل معك، لا تلقائيا إلى بطاقتك.'); ?></p>
      </div>
    </aside>
  </div>
</section>
