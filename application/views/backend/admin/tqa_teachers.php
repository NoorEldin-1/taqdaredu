<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/**
 * اعتماد المعلّمين.
 *
 * من يتقدّم معلّمًا **لا يُفتح له حساب** — يُنشأ طلبٌ ينتظر مراجعتك.
 * ومنصّةٌ يدرّس فيها الغرباء بلا تحقّق ليست منصّة تعليمية.
 *
 * والرفض يُسجَّل كما يُسجَّل القبول: قرارٌ بلا أثر لا يُراجَع ولا يُفسَّر.
 */
?>
<div class="tqa-wrap">
  <?php echo tqa_flash(); ?>
  <div class="tqa-head">
    <h1>طلبات المعلّمين</h1>
    <p class="tqa-lead">
      تُراجَع هنا طلبات الانضمام. والاعتماد يفتح حساب المعلّم ولوحته؛
      وقبله لا يستطيع الدخول.
    </p>
  </div>

  <?php if (empty($apps)): ?>
    <div class="tqa-card"><p class="tqa-empty">لا طلبات معلّقة.</p></div>
  <?php else: ?>
    <div class="tqa-card">
      <table class="tqa-table">
        <thead><tr>
          <th>المعلّم</th><th>البريد</th><th>الجوال</th><th>النبذة</th>
          <th>المستند</th><th>الحالة</th><th>الإجراء</th>
        </tr></thead>
        <tbody>
        <?php foreach ($apps as $a): ?>
          <tr>
            <td><strong><?php echo html_escape(trim($a['first_name'] . ' ' . $a['last_name'])); ?></strong></td>
            <td dir="ltr"><?php echo html_escape($a['email']); ?></td>
            <td dir="ltr"><?php echo html_escape($a['phone'] ?: '—'); ?></td>
            <td><?php echo html_escape(mb_substr((string) $a['message'], 0, 90)); ?></td>
            <td>
              <?php if (!empty($a['document'])): ?>
                <a href="<?php echo base_url('uploads/document/' . rawurlencode($a['document'])); ?>"
                   target="_blank" rel="noopener">عرض</a>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td>
              <?php
              $st = (int) $a['status'];
              echo $st === 1 ? '<span class="tqa-pill tqa-pill--ok">معتمَد</span>'
                 : ($st === 2 ? '<span class="tqa-pill tqa-pill--no">مرفوض</span>'
                              : '<span class="tqa-pill">بانتظار المراجعة</span>');
              ?>
            </td>
            <td>
              <?php if ((int) $a['status'] === 0): ?>
                <form method="post" action="<?php echo site_url('taqdar_admin/teacher_review'); ?>" class="tqa-inline">
                  <input type="hidden" name="app_id" value="<?php echo (int) $a['id']; ?>">
                  <input type="hidden" name="act" value="approve">
                  <button type="submit" class="tqa-btn tqa-btn--primary">اعتماد</button>
                </form>
                <form method="post" action="<?php echo site_url('taqdar_admin/teacher_review'); ?>" class="tqa-inline">
                  <input type="hidden" name="app_id" value="<?php echo (int) $a['id']; ?>">
                  <input type="hidden" name="act" value="reject">
                  <button type="submit" class="tqa-btn">رفض</button>
                </form>
              <?php else: ?>—<?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
