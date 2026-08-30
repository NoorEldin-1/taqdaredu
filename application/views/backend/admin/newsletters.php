<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * النشرة البريدية — القوالب.
 *
 * أعيدت كتابتها بهيكل `tqa-*`. وما تغير:
 *
 * ١ — **الشرح كان بالإنجليزية كاملا** في لوحة عربية: فقرتان مكتوبتان في
 *     القالب مباشرة بلا `get_phrase` أصلا.
 * ٢ — **أزرار الإجراءات كانت داخل رابط الطي.** ثلاثة `<button>` داخل
 *     `<a data-toggle="collapse">`، وزر داخل رابط بناء غير صالح في
 *     HTML — ولذلك كتب `onclick="stopProp(event)"` على غلافها لمنع
 *     الطي عند الضغط. صارت الأزرار خارج الكاشف.
 * ٣ — **الطي بـ`data-toggle="collapse"`** من Bootstrap. صار `<details>`
 *     يعمل بلا مكتبة.
 * ٤ — **`$newsletter['description']` يطبع خاما** — وهو نص يكتبه المسؤول
 *     في محرر HTML، فهذا مقصود؛ لكنه يعرض الآن داخل حاوية محدودة العرض
 *     بدل أن يمتد على البطاقة.
 */
$tq_rows = $this->db->order_by('id', 'DESC')->get('newsletters')->result_array();
$tq_open = (string) $this->input->get('tab', true);

$tq_cron = 'uploads/cronjob/newsletter_cron.php';
$tq_has_cron = is_file(FCPATH . $tq_cron);
?>

<?php tqa_head(t('النشرة البريدية'),
    t('القالب يكتب مرة ويرسل مرات. والإرسال يمر بطابور — عشرون رسالة في الدقيقة.'),
    'send',
    '<a class="tqa-btn tqa-btn--primary" href="' . site_url('admin/newsletter_add_form') . '">'
  . tq_icon('plus', 17) . t(' قالب جديد</a>')
  . '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('admin/subscribed_user') . '">'
  . tq_icon('users', 16) . t(' المشتركون</a>')); ?>

<?php include 'newsletter_statistics.php'; ?>

<div class="tqa-cols">

    <div class="tqa-stack">
        <?php if (empty($tq_rows)): ?>

            <div class="tqa-card tqa-card--flush">
                <?php tqa_empty(t('لا قوالب نشرة بعد'),
                    t('القالب نص واحد يرسل إلى كل المشتركين. اكتب أولها.'),
                    t('أنشئ قالبا'), site_url('admin/newsletter_add_form'), 'send'); ?>
            </div>

        <?php else: ?>
            <?php foreach ($tq_rows as $tq_n): $tq_id = (int) $tq_n['id']; ?>
                <div class="tqa-card">
                    <div class="tqa-row tqa-row--between" style="align-items:flex-start">
                        <strong style="color:var(--tq-navy);font:var(--tq-type-bodyStrong);min-inline-size:0">
                            <?php echo html_escape($tq_n['subject']); ?>
                        </strong>

                        <?php /* الأزرار خارج الكاشف: كانت داخل رابط الطي،
                                 وزر داخل رابط بناء غير صالح — ولذلك كانت
                                 تحتاج `stopPropagation` لتعمل أصلا. */ ?>
                        <div class="tqa-rowacts">
                            <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                               href="<?php echo site_url('admin/newsletter_send_form/' . $tq_id); ?>">
                                <?php echo tq_icon('send', 14); ?> <?php echo t('أرسل'); ?>
                            </a>

                            <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                               href="<?php echo site_url('admin/newsletter_edit_form/' . $tq_id); ?>">
                                <?php echo tq_icon('edit', 14); ?> <?php echo t('تعديل'); ?>
                            </a>

                            <form method="post" action="<?php echo site_url('admin/newsletters/delete/' . $tq_id); ?>"
                                  data-tqa-confirm-title="<?php echo te('حذف القالب'); ?>"
                                  data-tqa-confirm="<?php echo te('سيحذف «____». والرسائل المرسلة منه تبقى في السجل.', array(html_escape($tq_n['subject']))); ?>"
                                  data-tqa-confirm-ok="<?php echo te('نعم، احذف'); ?>"
                                  data-tqa-confirm-tone="danger">
                                <?php echo tq_csrf(); ?>
                                <button type="submit" class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                                        style="color:var(--tq-danger)">
                                    <?php echo tq_icon('trash', 14); ?>
                                    <span class="tqa-sr"><?php echo t('حذف'); ?></span>
                                </button>
                            </form>
                        </div>
                    </div>

                    <details style="margin-block-start:var(--tq-space-m)"
                             <?php echo $tq_open === (string) $tq_id ? 'open' : ''; ?>>
                        <summary class="tqa-btn tqa-btn--ghost tqa-btn--sm" style="display:inline-flex">
                            <?php echo tq_icon('eye', 14); ?> <?php echo t('اعرض نص القالب'); ?>
                        </summary>
                        <div style="margin-block-start:var(--tq-space-m);padding:var(--tq-space-l);
                                    background:var(--tq-ground);border-radius:var(--tqa-radius-sm);
                                    font:var(--tq-type-caption);overflow-wrap:anywhere">
                            <?php echo $tq_n['description']; ?>
                        </div>
                    </details>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <aside>
        <div class="tqa-note">
            <span aria-hidden="true"><?php echo tq_icon('clock', 18); ?></span>
            <span>
                <strong><?php echo t('الإرسال على دفعات.'); ?></strong>
                <?php echo t('أكثر من عشرين مشتركا يقسم إلى دفعات من عشرين، ودفعة كل دقيقة. وما دام الإرسال يعتمد على المتصفح وجب إبقاء هذه الصفحة مفتوحة حتى تنتهي.'); ?>
            </span>
        </div>

        <div class="tqa-card" style="margin-block-start:var(--tq-space-l)">
            <div class="tqa-card__head" style="padding:0 0 var(--tq-space-m);margin-block-end:var(--tq-space-m)">
                <span class="tqa-iconbox tqa-sky" aria-hidden="true" style="inline-size:36px;block-size:36px">
                    <?php echo tq_icon('refresh', 18); ?>
                </span>
                <h2 style="font:var(--tq-type-bodyStrong);font-family:var(--tq-font-title)"><?php echo t('إرسال بلا متصفح'); ?></h2>
            </div>

            <p style="margin:0 0 var(--tq-space-m);font:var(--tq-type-caption);color:var(--tq-text2)">
                <?php echo t('مهمة دورية على الخادم ترسل الطابور وحدها، فلا حاجة إلى إبقاء الصفحة مفتوحة.'); ?>
            </p>

            <?php if ($tq_has_cron): ?>
                <p class="tqa-debug" style="margin-block-end:var(--tq-space-m)"><?php
                    echo html_escape(realpath(FCPATH . $tq_cron)); ?></p>

                <form method="post" action="<?php echo site_url('admin/cronjob/stop'); ?>"
                      data-tqa-confirm-title="<?php echo te('حذف ملف المهمة'); ?>"
                      data-tqa-confirm="<?php echo te('سيعود الإرسال معتمدا على إبقاء الصفحة مفتوحة.'); ?>"
                      data-tqa-confirm-ok="<?php echo te('نعم، احذف'); ?>"
                      data-tqa-confirm-tone="danger">
                    <?php echo tq_csrf(); ?>
                    <button type="submit" class="tqa-btn tqa-btn--ghost tqa-btn--block" style="color:var(--tq-danger)">
                        <?php echo tq_icon('trash', 15); ?> <?php echo t('احذف ملف المهمة'); ?>
                    </button>
                </form>
            <?php else: ?>
                <form method="post" action="<?php echo site_url('admin/cronjob/start'); ?>">
                    <?php echo tq_csrf(); ?>
                    <button type="submit" class="tqa-btn tqa-btn--primary tqa-btn--block">
                        <?php echo tq_icon('plus', 15); ?> <?php echo t('أنشئ ملف المهمة'); ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </aside>
</div>
