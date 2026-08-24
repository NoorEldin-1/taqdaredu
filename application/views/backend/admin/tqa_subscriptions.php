<?php
defined('BASEPATH') or exit('No direct script access allowed');
$M = &get_instance()->taqdar_admin_model;

$labels = array('pending' => 'معلق', 'active' => 'نشط', 'cancelled' => 'ملغى', 'expired' => 'منته');
$tones  = array('pending' => 'warning', 'active' => 'success', 'cancelled' => 'danger', 'expired' => 'danger');
?>

<div class="tqa-head">
    <div>
        <h1>الاشتراكات</h1>
        <p>حالة كل مشترك، وتفعيل التحويلات البنكية بعد التحقق منها.</p>
    </div>
</div>

<?php
/* اشتراك نشط بلا بنود = طالب دفع وبوابته فارغة، بلا خطأ في أي سجل.
   يقع حين يفعل الاشتراك بـUPDATE مباشر بدل المرور بـ`activate()`.
   والعدد يقاس هنا لا يفترض: تنبيه دائم يتجاهل بعد أسبوع. */
$tq_broken = (int) get_instance()->db->query(
    'SELECT COUNT(*) AS n FROM `subscriptions` s
      WHERE s.`status` IN ("active","cancelled")
        AND NOT EXISTS (SELECT 1 FROM `subscription_items` i
                         WHERE i.`subscription_id` = s.`id`)'
)->row()->n;
?>
<?php
/* اشتراك نشط ينقصه تسجيل — TQ-ENROL-STALE.
   البنود تجيب `is_entitled()`، وصفوف `enrol` تجيب عشر شاشات لا تسأل
   غيرها: كورساتي ودروسي وطلاب المعلم والمواد والتقارير. وهي تكتب مرة
   واحدة عند التفعيل، فكل ما ينشر بعد البيع لا يبلغ مشتركا قائما —
   يشاهد دروسه ويقرأ «لا كورسات بعد» في الشاشة المجاورة.
   والعد هنا بالفرق نفسه الذي يجسده الإصلاح، لا بتقدير. */
$tq_stale = (int) get_instance()->db->query(
    'SELECT COUNT(DISTINCT s.`id`) AS n
       FROM `subscriptions` s
       JOIN `subscription_items` si ON si.`subscription_id` = s.`id`
       JOIN `paths` p ON p.`status` = "published" AND p.`course_id` > 0
            AND ( (si.`entity_type` = "grade"   AND p.`grade_id`   = si.`entity_id`)
               OR (si.`entity_type` = "subject" AND p.`subject_id` = si.`entity_id`)
               OR (si.`entity_type` = "path"    AND p.`id`         = si.`entity_id`)
               OR  si.`entity_type` = "all" )
       JOIN `course` c ON c.`id` = p.`course_id`
       LEFT JOIN `enrol` e ON e.`course_id` = p.`course_id` AND e.`user_id` = s.`user_id`
      WHERE s.`status` IN ("active","cancelled")
        AND e.`id` IS NULL'
)->row()->n;
?>
<?php if ($tq_broken > 0 || $tq_stale > 0): ?>
    <div class="tqa-note tqa-note--warn">
        <?php if ($tq_broken > 0): ?>
            <p style="margin:0 0 6px">
                <strong><?php echo $tq_broken; ?> اشتراكا نشطا بلا بنود.</strong>
                هذه الاشتراكات مدفوعة وحالتها نشطة، لكن محتواها <strong>لا يفتح للطالب</strong>
                لأن نطاقها لم ينسخ بنودا. يقع هذا حين يفعل الاشتراك من خارج زر التفعيل.
            </p>
        <?php endif; ?>
        <?php if ($tq_stale > 0): ?>
            <p style="margin:0">
                <strong><?php echo $tq_stale; ?> اشتراكا نشطا ينقصه تسجيل.</strong>
                محتوى نشر <strong>بعد</strong> شرائهم، فهو يفتح لهم فعلا ولا يظهر في
                «كورساتي» ولا «دروسي» ولا في قوائم طلاب معلمه — لأن تلك الشاشات تقرأ
                جدول التسجيل، وهو يكتب مرة واحدة يوم التفعيل.
            </p>
        <?php endif; ?>
        <form method="post" action="<?php echo site_url('taqdar_admin/subscriptions_repair'); ?>"
              style="margin-block-start:10px">
            <?php echo tq_csrf(); ?>
            <button type="submit" class="tqa-btn tqa-btn--primary">أعد بناء البنود والتسجيلات</button>
        </form>
    </div>
<?php endif; ?>


<?php if (!$gateway_active): ?>
    <div class="tqa-note tqa-note--warn">
        <span aria-hidden="true"><?php echo tq_icon('alert', 18); ?></span>
        <span>
            <strong>لا دفع أونلاين اليوم.</strong>
            بوابة تاب غير مفعلة أو بلا مفتاح سري، فالمسار العامل هو التحويل البنكي وحده:
            يشترك الطالب فينشأ اشتراك «معلق» وفاتورة، ثم تفعله من هنا بعد أن تتحقق من الحوالة.
            <a href="<?php echo site_url('taqdar_admin/tap'); ?>">اضبط بوابة تاب</a>
            ليصير الاشتراك يفعل نفسه عند الدفع.
        </span>
    </div>
<?php else: ?>
    <div class="tqa-note">
        <span aria-hidden="true"><?php echo tq_icon('card', 18); ?></span>
        <span>
            الدفع بالبطاقة مفعل: من يدفع يفعل اشتراكه بنفسه ولا ينتظر تفعيلا يدويا.
            وما يظهر «معلقا» هنا هو من اختار التحويل البنكي أو من لم يكمل دفعته —
            وحال كل دفعة في <a href="<?php echo site_url('taqdar_admin/tap'); ?>">شاشة بوابة تاب</a>.
        </span>
    </div>
<?php endif; ?>

<div class="tqa-stack">
    <?php foreach (array(
        'الباقات' => $stats['plans'], 'اشتراكات نشطة' => $stats['active'],
        'بانتظار التفعيل' => $stats['pending'], 'فواتير غير مدفوعة' => $stats['unpaid'],
    ) as $label => $num): ?>
        <div>
            <div class="tqa-stat">
                <span class="tqa-stat-label"><?php echo $label; ?></span>
                <span class="tqa-stat-num tq-ltr" dir="ltr"><?php echo (int) $num; ?></span>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="tqa-card">
    <div class="tqa-card__head">
        <h4 class="header-title">
            إجمالي المحصل: <?php echo tqa_money($stats['revenue']); ?>
        </h4>
    </div>
    <div class="tqa-card__body">
        <?php if (empty($rows)): ?>

            <div class="tqa-empty">
                <h3>لا اشتراكات بعد</h3>
                <p>أضف الباقات أولا من <a href="<?php echo site_url('taqdar_admin/module/plans'); ?>">شاشة الباقات</a>،
                   ثم ستظهر هنا اشتراكات الطلاب.</p>
            </div>

        <?php else: ?>

            <div class="tqa-table__wrap">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>المشترك</th>
                            <th>الباقة</th>
                            <th>المدفوع</th>
                            <th>الحالة</th>
                            <th>يبدأ</th>
                            <th>ينتهي</th>
                            <th>الوسيلة</th>
                            <th style="inline-size:180px">إجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r):
                        $st = $r['status'];
                        // منته فعليا وإن لم يمر الكرون بعد
                        if ($st === 'active' && !empty($r['ends_at']) && strtotime($r['ends_at']) < time()) {
                            $st = 'expired';
                        }
                    ?>
                        <tr>
                            <td><span class="tq-ltr" dir="ltr"><?php echo (int) $r['id']; ?></span></td>
                            <td><?php echo html_escape($r['user_name'] ?: ('#' . $r['user_id'])); ?></td>
                            <td><?php echo html_escape($r['plan_name'] ?: '—'); ?></td>
                            <td><?php echo tqa_money($r['price']); ?></td>
                            <td><span class="badge badge-<?php echo $tones[$st]; ?>"><?php echo $labels[$st]; ?></span></td>
                            <td><?php echo $r['started_at'] ? tqa_ltr(date('Y-m-d', strtotime($r['started_at']))) : '<span class="tqa-dim">—</span>'; ?></td>
                            <td><?php echo $r['ends_at'] ? tqa_ltr(date('Y-m-d', strtotime($r['ends_at']))) : '<span class="tqa-dim">—</span>'; ?></td>
                            <td><?php echo $r['method'] ? tqa_ltr($r['method']) : '<span class="tqa-dim">—</span>'; ?></td>
                            <td>
                                <?php if ($r['status'] === 'pending'): ?>
                                    <?php /* التوكن يكتب صراحة: الحقن العام يعمل بجافاسكربت،
                                             ونموذج يعتمد عليه ليحفظ يسقط صامتا متى تعثر ملف. */ ?>
                                    <form method="post" class="tqa-activate"
                                          action="<?php echo site_url('taqdar_admin/subscription_activate/' . (int) $r['id']); ?>"
                                          data-tqa-confirm-title="تفعيل الاشتراك"
                                          data-tqa-confirm="سيسدد الاشتراك وتفتح باقته للطالب فورا. تأكد من وصول الحوالة أولا."
                                          data-tqa-confirm-ok="فعل الاشتراك">
                                        <?php echo tq_csrf(); ?>
                                        <input type="text" name="reference" class="tqa-input tq-ltr" dir="ltr"
                                               placeholder="مرجع الحوالة" required>
                                        <button type="submit" class="tqa-btn tqa-btn--mastery tqa-btn--sm">فعل</button>
                                    </form>
                                <?php elseif (in_array($r['status'], array('active'), true)): ?>
                                    <?php /* الإلغاء لا يصادر المدفوع — المدة تكمل. يقال ذلك
                                             في نص التأكيد نفسه لا بعده. */ ?>
                                    <form method="post" class="tqa-cancel"
                                          action="<?php echo site_url('taqdar_admin/subscription_cancel/' . (int) $r['id']); ?>"
                                          data-tqa-confirm-title="إلغاء التجديد"
                                          data-tqa-confirm="يبقى الاشتراك صالحا حتى تاريخ انتهائه، ولا يجدد بعده."
                                          data-tqa-confirm-ok="ألغ التجديد"
                                          data-tqa-confirm-tone="danger">
                                        <?php echo tq_csrf(); ?>
                                        <button type="submit" class="tqa-btn tqa-btn--ghost tqa-btn--sm" style="color:var(--tq-danger)">إلغاء</button>
                                    </form>
                                <?php else: ?>
                                    <span class="tqa-dim">—</span>
                                <?php endif; ?>

                                <?php /* ── قسمة هذه البيعة — TQ-REVENUE-RESPLIT ──────
                                        «باعوا صفي ولم يصلني شيء» أول ما يسأل عنه
                                        معلم، ولم يكن في اللوحة موضع واحد يجيب.
                                        والقسمة تجمد وقت التفعيل بالقاعدة — ونشر
                                        عشرين درسا غدا لا يعيد حساب بيعة أمس — لكن
                                        حين يحذف المحتوى المقسوم عليه ثم ينشر غيره
                                        في الصف نفسه يبقى القيد لمن لا محتوى له.
                                        وهذا الزر هو المخرج، بقرار مسؤول لا تلقائيا. */ ?>
                                <?php $tq_sh = isset($shares[(int) $r['id']]) ? $shares[(int) $r['id']] : array(); ?>
                                <?php if ($tq_sh || (int) $r['price'] > 0 && in_array($r['status'], array('active','cancelled'), true)): ?>
                                    <details style="margin-block-start:8px">
                                        <summary class="tqa-dim" style="cursor:pointer;font-size:12px">قسمة الإيراد</summary>
                                        <?php if ($tq_sh): ?>
                                            <ul style="margin:6px 0;padding-inline-start:16px;font-size:12px">
                                                <?php foreach ($tq_sh as $tq_s): ?>
                                                    <li>
                                                        <?php echo html_escape($tq_s['teacher_name'] ?: ('#' . $tq_s['teacher_id'])); ?>
                                                        — <?php echo tqa_money($tq_s['amount_halalas']); ?>
                                                        <span class="tqa-dim">(<?php echo (int) $tq_s['lessons']; ?>
                                                        من <?php echo (int) $tq_s['lessons_total']; ?> درسا)</span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <p class="tqa-dim" style="margin:6px 0;font-size:12px">
                                                لم يقيد لأحد. لا مسار منشور بمعلم في نطاق الباقة وقت البيع.
                                            </p>
                                        <?php endif; ?>
                                        <form method="post"
                                              action="<?php echo site_url('taqdar_admin/subscription_resplit/' . (int) $r['id']); ?>"
                                              data-tqa-confirm-title="إعادة قسمة الإيراد"
                                              data-tqa-confirm="تعكس القيود القائمة على هذه البيعة وتقسمها من جديد على المستحقين الآن. ينقل مال بين المحافظ، ويسجل في سجل التدقيق."
                                              data-tqa-confirm-ok="أعد القسمة"
                                              data-tqa-confirm-tone="danger">
                                            <?php echo tq_csrf(); ?>
                                            <input type="text" name="reason" class="tqa-input tq-ltr" dir="auto"
                                                   placeholder="السبب" maxlength="200" required
                                                   style="font-size:12px">
                                            <button type="submit" class="tqa-btn tqa-btn--ghost tqa-btn--sm">أعد القسمة</button>
                                        </form>
                                    </details>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </div>
</div>

