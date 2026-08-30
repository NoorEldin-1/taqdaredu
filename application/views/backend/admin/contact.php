<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * رسائل التواصل.
 *
 * بطاقات لا جدولا: الرسالة نص طويل، والجدول إما يقصها فلا تقرأ، وإما
 * يمدد الصف عشرة أسطر فيتفكك. انظر TQ-DT-GONE في [Admin::contact()].
 *
 * والرد يفتح **داخل البطاقة** لا في نافذة تجلب بـAJAX: كانت
 * `showAjaxModal(...)` تحمل قالبا آخر داخل نافذة Bootstrap، فالرد يحتاج
 * رحلة إلى الخادم قبل أن يظهر الحقل، ويسقط صامتا إن تعثرت.
 */
$tq_kind = array(
    'student' => array('info', t('طالب')),
    'teacher' => array('ok',   t('معلم')),
);

/* رابط يحمل المرشحات القائمة ويبدل واحدا — والقيمة الفارغة تنزع مفتاحها
   بدل أن تكتب `?state=` فارغا يقرأ حالا ثالثا لا وجود له. */
$tq_url = function (array $over = array()) use ($search, $state, $subject) {
    $qs = array_filter(array_merge(
        array('q' => $search, 'state' => $state, 'subject' => $subject),
        $over
    ), function ($v) { return (string) $v !== ''; });
    return site_url('admin/contact') . ($qs ? '?' . http_build_query($qs) : '');
};

/* «قبل ساعتين» يقرأ أسرع من طابع زمني، والتاريخ الكامل يبقى في `title`
   لمن يريد الدقيقة. و`created_at` عمود نصي فيه ثوان — والصف القديم قد
   يأتي فارغا، فلا يطبع سطر تاريخ كاذب. */
$tq_when = function ($ts) {
    $ts = (int) $ts;
    if ($ts <= 0) return array('', '');
    $d = time() - $ts;
    if ($d < 60)      $rel = t('الآن');
    elseif ($d < 3600)   $rel = t('قبل') . (int) ($d / 60) . t('دقيقة');
    elseif ($d < 86400)  $rel = t('قبل') . (int) ($d / 3600) . t('ساعة');
    elseif ($d < 604800) $rel = t('قبل') . (int) ($d / 86400) . t('يوم');
    else                 $rel = date('Y/m/d', $ts);
    return array($rel, date('Y/m/d — H:i', $ts));
};
?>

<?php tqa_head(t('رسائل التواصل'),
    t('ما يرسل من صفحة «تواصل معنا» في الموقع العام — بموضوعه ووقته كاملا. الرد يخرج من بريد المنصة نفسه.'),
    'mail'); ?>

<form class="tqa-toolbar" method="get" action="<?php echo site_url('admin/contact'); ?>">
    <label class="tqa-sr" for="q"><?php echo t('ابحث في الرسائل'); ?></label>
    <input class="tqa-input" type="search" id="q" name="q" placeholder="<?php echo te('ابحث باسم أو بريد أو موضوع أو نص الرسالة…'); ?>"
           value="<?php echo html_escape($search); ?>">
    <?php /* المرشحات القائمة تركب في نموذج البحث، وإلا مسحها أول بحث. */ ?>
    <?php if ($state !== ''): ?><input type="hidden" name="state" value="<?php echo html_escape($state); ?>"><?php endif; ?>
    <?php if ($subject !== ''): ?><input type="hidden" name="subject" value="<?php echo html_escape($subject); ?>"><?php endif; ?>
    <button type="submit" class="tqa-btn tqa-btn--primary"><?php echo tq_icon('search', 16); ?> ابحث</button>
    <?php if ($search !== ''): ?>
        <a class="tqa-btn tqa-btn--ghost" href="<?php echo $tq_url(array('q' => '')); ?>"><?php echo t('مسح البحث'); ?></a>
    <?php endif; ?>
</form>

<?php /* ══════════ مرشحا الحال والموضوع ══════════
         الحال أولا لأنه السؤال الأول كل صباح: «ما الذي ينتظر ردي؟».
         والموضوع بعده لأنه يوجه إلى فريق: الفني غير المالي غير القبول. */ ?>
<div class="tqa-toolbar tqa-toolbar--wrap" style="gap:var(--tq-space-xs)">
    <?php
    $tq_states = array(
        array('',        t('الكل'),       (int) $all_n),
        array('open',    t('بلا رد'),     (int) $open_n),
        array('replied', t('رد عليها'),   max(0, (int) $all_n - (int) $open_n)),
    );
    foreach ($tq_states as $tq_s): ?>
        <a class="tqa-chip<?php echo $state === $tq_s[0] ? ' is-on' : ''; ?>"
           <?php echo $state === $tq_s[0] ? 'aria-current="true"' : ''; ?>
           href="<?php echo $tq_url(array('state' => $tq_s[0], 'page' => '')); ?>">
            <?php echo $tq_s[1]; ?>
            <span class="tqa-num">(<?php echo $tq_s[2]; ?>)</span>
        </a>
    <?php endforeach; ?>

    <?php if (!empty($subjects)): ?>
        <span class="tqa-chipsep" aria-hidden="true"></span>
        <?php if ($subject !== ''): ?>
            <a class="tqa-chip is-on" href="<?php echo $tq_url(array('subject' => '', 'page' => '')); ?>">
                <?php echo html_escape($subject); ?>
                <?php echo tq_icon('close', 12); ?>
            </a>
        <?php else: foreach ($subjects as $tq_sub => $tq_n): ?>
            <a class="tqa-chip" href="<?php echo $tq_url(array('subject' => $tq_sub, 'page' => '')); ?>">
                <?php echo html_escape($tq_sub); ?>
                <span class="tqa-num">(<?php echo (int) $tq_n; ?>)</span>
            </a>
        <?php endforeach; endif; ?>
    <?php endif; ?>
</div>

<?php if (empty($rows)): ?>

    <?php /* الفراغ ثلاثة أحوال لا حالان: لا رسائل أصلا · لا رسالة تطابق
             البحث · لا رسالة في هذا المرشح. والثالث كان يقول «لا رسائل
             بعد» وفي الجدول مئة — فيظن قارئه أن شيئا ضاع. */
    $tq_filtered = ($state !== '' || $subject !== '');
    ?>
    <div class="tqa-card tqa-card--flush">
        <?php if ($search !== ''): ?>
            <?php tqa_empty(t('لا رسالة تطابق هذا البحث'),
                t('جرب كلمة واحدة بدل الجملة كاملة.'),
                t('مسح البحث'), $tq_url(array('q' => '')), 'mail'); ?>
        <?php elseif ($tq_filtered): ?>
            <?php tqa_empty(t('لا رسالة في هذا المرشح'),
                t('الجدول فيه') . (int) $all_n . t('رسالة — لكن لا شيء يطابق ما اخترت.'),
                t('اعرض الكل'), site_url('admin/contact'), 'mail'); ?>
        <?php else: ?>
            <?php tqa_empty(t('لا رسائل بعد'),
                t('تمتلئ هذه الشاشة وحدها حين يرسل زائر من صفحة «تواصل معنا» — ولا يضاف إليها بيد.'),
                '', '', 'mail'); ?>
        <?php endif; ?>
    </div>

<?php else: ?>

    <?php /* الحذف الجماعي نموذج واحد يلف البطاقات كلها: مربعات الاختيار
             بداخله، وزره يظهر حين يحدد شيء. ولا جافاسكربت شرط للحذف —
             الزر ظاهر دائما، والسكربت يخفيه حين لا تحديد فقط. */ ?>
    <form method="post" action="<?php echo site_url('admin/contact/delete_selected_contact'); ?>"
          data-tqa-bulk
          data-tqa-confirm-title="<?php echo te('حذف الرسائل المحددة'); ?>"
          data-tqa-confirm="لا رجعة في هذا الحذف."
          data-tqa-confirm-ok="نعم، احذف"
          data-tqa-confirm-tone="danger">
        <?php echo tq_csrf(); ?>

        <div class="tqa-toolbar" data-tqa-bulkbar>
            <label class="tqa-check">
                <input type="checkbox" data-tqa-bulk-all>
                <span><?php echo t('حدد كل المعروض'); ?></span>
            </label>
            <button type="submit" class="tqa-btn tqa-btn--ghost" style="color:var(--tq-danger)">
                <?php echo tq_icon('trash', 15); ?>
                احذف المحدد (<span data-tqa-bulk-count class="tqa-num">0</span>)
            </button>
        </div>

        <div class="tqa-stack">
        <?php foreach ($rows as $tq_r):
            $tq_id      = (int) $tq_r['id'];
            $tq_name    = trim($tq_r['first_name'] . ' ' . $tq_r['last_name']);
            if ($tq_name === '') $tq_name = $tq_r['email'];
            $tq_replied = (int) $tq_r['replied'] === 1;
            $tq_role    = $known[strtolower((string) $tq_r['email'])] ?? null;
            $tq_subj    = trim((string) $tq_r['subject']);
            list($tq_rel, $tq_abs) = $tq_when($tq_r['created_at']);
        ?>
            <article class="tqa-card">

                <div class="tqa-row tqa-row--between" style="align-items:flex-start">
                    <div class="tqa-row" style="align-items:flex-start">
                        <label class="tqa-check" style="padding:0">
                            <input type="checkbox" name="ids[]" value="<?php echo $tq_id; ?>" data-tqa-bulk-item>
                            <span class="tqa-sr">حدد رسالة <?php echo html_escape($tq_name); ?></span>
                        </label>

                        <div>
                            <?php /* الموضوع أولا والاسم تحته: الشاشة تفرز بالموضوع
                                     («الدعم الفني» لفريق، «الاشتراكات» لآخر)، ومن
                                     يمسح عشرين بطاقة يبحث عن الموضوع لا عن الاسم. */ ?>
                            <strong style="color:var(--tq-navy);font:var(--tq-type-bodyStrong)">
                                <?php echo $tq_subj !== '' ? html_escape($tq_subj) : t('بلا موضوع'); ?>
                            </strong>
                            <div style="margin-block-start:2px;font:var(--tq-type-caption);color:var(--tq-text2)">
                                <?php echo html_escape($tq_name); ?>
                                <?php if ($tq_rel !== ''): ?>
                                    · <time datetime="<?php echo date('c', (int) $tq_r['created_at']); ?>"
                                            title="<?php echo html_escape($tq_abs); ?>"><?php echo $tq_rel; ?></time>
                                <?php endif; ?>
                            </div>
                            <div class="tqa-row" style="gap:var(--tq-space-xs);margin-block-start:var(--tq-space-xs)">
                                <?php if ($tq_role !== null): ?>
                                    <span class="tqa-badge tqa-badge--<?php echo $tq_kind[$tq_role][0]; ?>">
                                        <?php echo $tq_kind[$tq_role][1]; ?> مسجل
                                    </span>
                                <?php else: ?>
                                    <span class="tqa-badge tqa-badge--muted"><?php echo t('غير مسجل في المنصة'); ?></span>
                                <?php endif; ?>

                                <?php if ($tq_replied): ?>
                                    <span class="tqa-badge tqa-badge--ok">
                                        <?php echo tq_icon('check', 12); ?> رد عليها
                                    </span>
                                <?php else: ?>
                                    <span class="tqa-badge tqa-badge--warn"><?php echo t('بانتظار الرد'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="tqa-rowacts">
                        <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                           href="mailto:<?php echo html_escape($tq_r['email']); ?>">
                            <?php echo tq_icon('mail', 14); ?> راسله مباشرة
                        </a>
                    </div>
                </div>

                <p style="margin:var(--tq-space-l) 0;padding:var(--tq-space-m) var(--tq-space-l);
                          background:var(--tq-ground);border-radius:var(--tqa-radius-sm);
                          font:var(--tq-type-body);color:var(--tq-text);overflow-wrap:anywhere">
                    <?php echo nl2br(html_escape($tq_r['message'])); ?>
                </p>

                <dl class="tqa-kv" style="margin-block-end:var(--tq-space-l)">
                    <div>
                        <dt><?php echo t('البريد'); ?></dt>
                        <dd><a class="tq-ltr" dir="ltr" href="mailto:<?php echo html_escape($tq_r['email']); ?>">
                            <?php echo html_escape($tq_r['email']); ?></a></dd>
                    </div>
                    <?php if (trim((string) $tq_r['phone']) !== ''): ?>
                        <div>
                            <dt><?php echo t('الهاتف'); ?></dt>
                            <dd><a class="tq-ltr" dir="ltr" href="tel:<?php echo html_escape($tq_r['phone']); ?>">
                                <?php echo html_escape($tq_r['phone']); ?></a></dd>
                        </div>
                    <?php endif; ?>
                    <?php if (trim((string) $tq_r['address']) !== ''): ?>
                        <div>
                            <dt><?php echo t('العنوان'); ?></dt>
                            <dd><?php echo html_escape($tq_r['address']); ?></dd>
                        </div>
                    <?php endif; ?>
                    <div>
                        <dt><?php echo t('الموضوع'); ?></dt>
                        <dd><?php echo $tq_subj !== '' ? html_escape($tq_subj) : '—'; ?></dd>
                    </div>
                    <?php if ($tq_abs !== ''): ?>
                        <div>
                            <dt><?php echo t('وصلت'); ?></dt>
                            <dd><span class="tq-ltr" dir="ltr"><?php echo html_escape($tq_abs); ?></span></dd>
                        </div>
                    <?php endif; ?>
                </dl>

                <div class="tqa-row">
                    <?php /* الرد نموذجه هنا لا في نافذة تجلب: `<details>`
                             يفتح ويغلق بلا سطر جافاسكربت واحد. */ ?>
                    <details style="flex:1;min-inline-size:0">
                        <summary class="tqa-btn tqa-btn--ghost tqa-btn--sm" style="display:inline-flex;list-style:none">
                            <?php echo tq_icon('send', 14); ?>
                            <?php echo $tq_replied ? t('رد مرة أخرى') : t('رد على الرسالة'); ?>
                        </summary>

                        <div style="margin-block-start:var(--tq-space-m)">
                            <label class="tqa-field__label" for="reply-<?php echo $tq_id; ?>">
                                <?php echo t('نص الرد — يرسل إلى'); ?>
                                <span class="tq-ltr" dir="ltr"><?php echo html_escape($tq_r['email']); ?></span>
                            </label>
                            <textarea class="tqa-textarea" id="reply-<?php echo $tq_id; ?>"
                                      form="reply-form-<?php echo $tq_id; ?>"
                                      name="reply_message" rows="4" required></textarea>
                            <div class="tqa-actions">
                                <button type="submit" form="reply-form-<?php echo $tq_id; ?>"
                                        class="tqa-btn tqa-btn--primary tqa-btn--sm">
                                    <?php echo tq_icon('send', 15); ?> أرسل الرد
                                </button>
                            </div>
                        </div>
                    </details>

                    <?php /* نموذج الحذف مستقل — النماذج لا تتداخل في HTML،
                             فحذف رسالة واحدة لا يكتب داخل نموذج الحذف الجماعي. */ ?>
                    <button type="submit" form="del-form-<?php echo $tq_id; ?>"
                            class="tqa-btn tqa-btn--ghost tqa-btn--sm" style="color:var(--tq-danger)">
                        <?php echo tq_icon('trash', 14); ?> احذفها
                    </button>
                </div>
            </article>
        <?php endforeach; ?>
        </div>
    </form>

    <?php /* نماذج الرد والحذف تكتب خارج النموذج الجامع: عنصر `<form>` لا
             يتداخل مع آخر في HTML، وسمة `form=` تربط الزر بنموذجه أينما كان. */ ?>
    <?php foreach ($rows as $tq_r): $tq_id = (int) $tq_r['id']; ?>
        <form id="reply-form-<?php echo $tq_id; ?>" method="post" hidden
              action="<?php echo site_url('admin/contact/send_reply/' . $tq_id); ?>">
            <?php echo tq_csrf(); ?>
        </form>
        <form id="del-form-<?php echo $tq_id; ?>" method="post" hidden
              action="<?php echo site_url('admin/contact/delete/' . $tq_id); ?>"
              data-tqa-confirm-title="<?php echo te('حذف الرسالة'); ?>"
              data-tqa-confirm="لا رجعة في هذا الحذف."
              data-tqa-confirm-ok="نعم، احذف"
              data-tqa-confirm-tone="danger">
            <?php echo tq_csrf(); ?>
        </form>
    <?php endforeach; ?>

    <?php
    $tq_from = max(1, $page_no - 2);
    $tq_last = min($page_count, $tq_from + 4);
    $tq_from = max(1, $tq_last - 4);
    /* صفحات المرشح لا صفحات الجدول: الصفحة الثانية كانت تنسى الحال
       والموضوع فتعود إلى «الكل» بلا أن يطلب أحد. */
    $tq_page = function ($p) use ($tq_url) {
        return $tq_url(array('page' => $p > 1 ? (string) $p : ''));
    };
    ?>
    <nav class="tqa-pager" aria-label="<?php echo te('صفحات الرسائل'); ?>" style="border:0">
        <span class="tqa-pager__info">
            <?php echo t('المعروض'); ?> <span class="tqa-num"><?php echo count($rows); ?></span>
            <?php echo t('من'); ?> <span class="tqa-num"><?php echo (int) $total; ?></span> <?php echo t('رسالة'); ?>
        </span>

        <?php if ($page_no > 1): ?>
            <a href="<?php echo $tq_page($page_no - 1); ?>" rel="prev" aria-label="<?php echo te('الصفحة السابقة'); ?>">
                <?php echo tq_icon('chev-prev', 16); ?>
            </a>
        <?php endif; ?>

        <?php for ($tq_p = $tq_from; $tq_p <= $tq_last; $tq_p++): ?>
            <?php if ($tq_p === (int) $page_no): ?>
                <span aria-current="page"><?php echo $tq_p; ?></span>
            <?php else: ?>
                <a href="<?php echo $tq_page($tq_p); ?>"><?php echo $tq_p; ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page_no < $page_count): ?>
            <a href="<?php echo $tq_page($page_no + 1); ?>" rel="next" aria-label="<?php echo te('الصفحة التالية'); ?>">
                <?php echo tq_icon('chev-next', 16); ?>
            </a>
        <?php endif; ?>
    </nav>

    <script>
    /**
     * شريط التحديد الجماعي.
     *
     * كان المنطق السابق يحتفظ بالمعرفات في مصفوفة جافاسكربت عبر الصفحات،
     * ويفرغها كاملة عند إلغاء تحديد واحد (`selectedRows = []` في فرع
     * `else`) — فمن حدد عشرة وألغى واحدا فقد التسعة. وهنا لا حالة يحتفظ
     * بها أصلا: مربعات الاختيار نفسها هي الحالة، وهي داخل النموذج فترسل
     * كما هي.
     */
    (function () {
        'use strict';

        var form = document.querySelector('[data-tqa-bulk]');
        if (!form) return;

        var all   = form.querySelector('[data-tqa-bulk-all]');
        var count = form.querySelector('[data-tqa-bulk-count]');
        var items = form.querySelectorAll('[data-tqa-bulk-item]');
        var btn   = form.querySelector('[data-tqa-bulkbar] button[type="submit"]');

        var sync = function () {
            var n = 0;
            Array.prototype.forEach.call(items, function (i) { if (i.checked) n++; });

            if (count) count.textContent = n;

            /* زر الحذف يعطل حين لا تحديد — يبقى ظاهرا فيقرأ ما يفعله،
               ولا يرسل نموذجا فارغا. وكان الزر السابق يخفى تماما، فلا
               يعرف من يفتح الشاشة أن الحذف الجماعي موجود أصلا. */
            if (btn) btn.disabled = n === 0;

            if (all) {
                all.checked = n > 0 && n === items.length;
                all.indeterminate = n > 0 && n < items.length;
            }
        };

        if (all) {
            all.addEventListener('change', function () {
                Array.prototype.forEach.call(items, function (i) { i.checked = all.checked; });
                sync();
            });
        }

        Array.prototype.forEach.call(items, function (i) {
            i.addEventListener('change', sync);
        });

        sync();
    })();
    </script>

<?php endif; ?>
