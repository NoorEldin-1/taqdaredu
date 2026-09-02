<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * اللغات والترجمة.
 *
 * أعيدت كتابتها بهيكل `tqa-*`. وما تغير:
 *
 * ١ — **تبويب رابع لا رابط إليه.** كان في الملف `.tab-pane#add` — نموذج
 *     «إضافة عبارة جديدة» — وليس في شريط التبويبات بند يفتحه. أي أن
 *     النموذج مرسوم في الصفحة ولا سبيل إلى رؤيته. صار قسما ظاهرا.
 * ٢ — **`<form action="#">` داخل خلية جدول** لأزرار الاتجاه، وهي لا
 *     ترسل شيئا أصلا (الحفظ بـAJAX). فالنموذج زينة تكسر شجرة المستند.
 * ٣ — **اسم الحقل `direction` مكرر في كل صف.** المتصفح يجمع أزرار
 *     الراديو بالاسم داخل النموذج الواحد؛ وهي هنا في نماذج منفصلة
 *     صدفة — فأي إعادة ترتيب تجعل اختيار اتجاه لغة يلغي اختيار الأخرى.
 *     صار الاسم فريدا لكل لغة.
 * ٤ — **`success_notify(response)`** يعرض في كل حال، ولا معالج خطأ:
 *     يقال «حدث الاتجاه» ولو رد الخادم 500.
 * ٥ — **حذف اللغة برابط GET** ينفذ بمجرد جلبه. صار نموذج POST بتوكن.
 * ٦ — **محرر العبارات كان أربعة أعمدة من البطاقات**، بطاقة كاملة لكل
 *     عبارة — وملف الترجمة فيه ألوف. صار جدولا، وفيه بحث في المتصفح.
 */
$tq_dirs = get_settings('language_dirs')
    ? json_decode(get_settings('language_dirs'), true)
    : array('english' => 'ltr');
if (!is_array($tq_dirs)) $tq_dirs = array();

$tq_active = get_settings('language');
$tq_editing = isset($edit_profile) ? $edit_profile : '';
?>

<?php if ($tq_editing !== ''): ?>

    <?php tqa_head(t('ترجمة ') . ucwords($tq_editing), t('العبارة تحفظ فور تعديلها — لا زر حفظ عام.'), 'translate',
        '<a class="tqa-btn tqa-btn--ghost" href="' . site_url('admin/manage_language') . '">'
      . tq_icon('chev-prev', 16) . t(' كل اللغات</a>')); ?>

    <?php $tq_phrases = openJSONFile($tq_editing); ?>

    <div class="tqa-toolbar">
        <label class="tqa-sr" for="phrase_filter"><?php echo t('ابحث في العبارات'); ?></label>
        <input class="tqa-input" type="search" id="phrase_filter" data-tqa-filter
               placeholder="<?php echo te('ابحث في المفاتيح والترجمات…'); ?>" style="min-inline-size:320px">
        <span style="font:var(--tq-type-caption);color:var(--tq-text2)">
            <span class="tqa-num" data-tqa-filter-count><?php echo count($tq_phrases); ?></span>
            <?php echo t('من'); ?> <span class="tqa-num"><?php echo count($tq_phrases); ?></span> <?php echo t('عبارة'); ?>
        </span>
    </div>

    <div class="tqa-card tqa-card--flush">
        <div class="tqa-table__wrap">
            <table class="tqa-table">
                <caption class="tqa-sr"><?php echo t('مفاتيح الترجمة وقيمها في لغة'); ?> <?php echo html_escape($tq_editing); ?></caption>
                <thead>
                    <tr>
                        <th style="inline-size:34%"><?php echo t('المفتاح'); ?></th>
                        <th><?php echo t('الترجمة'); ?></th>
                    </tr>
                </thead>
                <tbody data-tqa-filter-body>
                <?php foreach ($tq_phrases as $tq_key => $tq_val): $tq_slug = slugify($tq_key); ?>
                    <tr>
                        <td data-label="المفتاح">
                            <span class="tq-ltr" dir="ltr" style="font:var(--tq-type-micro);color:var(--tq-text2)">
                                <?php echo html_escape($tq_key); ?>
                            </span>
                        </td>
                        <td data-label="الترجمة">
                            <div class="tqa-row" style="flex-wrap:nowrap">
                                <input class="tqa-input" type="text" id="p-<?php echo $tq_slug; ?>"
                                       value="<?php echo html_escape($tq_val); ?>"
                                       data-tqa-phrase="<?php echo html_escape($tq_key); ?>"
                                       aria-label="<?php echo te('ترجمة ____', array(html_escape($tq_key))); ?>">
                                <span class="tqa-badge tqa-badge--ok" hidden data-tqa-phrase-ok>
                                    <?php echo tq_icon('check', 12); ?> <?php echo t('حفظ'); ?>
                                </span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    /**
     * حفظ العبارة عند مغادرة الحقل.
     *
     * كان الحفظ بزر لكل عبارة، والزر يعرض «تم» في كل حال — ولا معالج
     * خطأ في النداء إطلاقا. وهنا: الحفظ عند التغيير الفعلي وحده (لا عند
     * مرور المؤشر)، والشارة تظهر عند النجاح فقط، والفشل يعلن.
     */
    (function () {
        'use strict';

        var URL  = <?php echo json_encode(site_url('admin/update_phrase_with_ajax')); ?>;
        var LANG = <?php echo json_encode($tq_editing); ?>;
        var CSRF = window.TQ_CSRF || null;

        Array.prototype.forEach.call(document.querySelectorAll('[data-tqa-phrase]'), function (input) {
            var was = input.value;
            var ok  = input.parentNode.querySelector('[data-tqa-phrase-ok]');

            input.addEventListener('change', function () {
                if (input.value === was) return;

                var body = new URLSearchParams();
                body.set('updatedValue', input.value);
                body.set('currentEditingLanguage', LANG);
                body.set('key', input.getAttribute('data-tqa-phrase'));
                if (CSRF && CSRF.name) body.set(CSRF.name, CSRF.hash);

                input.disabled = true;

                fetch(URL, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString()
                }).then(function (r) {
                    if (!r.ok) throw new Error(r.status);
                    was = input.value;
                    if (ok) {
                        ok.hidden = false;
                        setTimeout(function () { ok.hidden = true; }, 1600);
                    }
                }).catch(function () {
                    input.value = was;
                    if (window.TQA) TQA.error('لم تحفظ العبارة. حدث الصفحة وأعد المحاولة.');
                }).then(function () {
                    input.disabled = false;
                });
            });
        });

        /* ترشيح في المتصفح: ملف الترجمة فيه ألوف من المفاتيح، والبحث
           فيها بالتمرير غير ممكن. */
        var filter = document.querySelector('[data-tqa-filter]');
        var body   = document.querySelector('[data-tqa-filter-body]');
        var count  = document.querySelector('[data-tqa-filter-count]');

        if (filter && body) {
            filter.addEventListener('input', function () {
                var q = filter.value.trim().toLowerCase();
                var n = 0;

                Array.prototype.forEach.call(body.rows, function (row) {
                    var hit = q === '' || row.textContent.toLowerCase().indexOf(q) !== -1
                           || row.querySelector('input').value.toLowerCase().indexOf(q) !== -1;
                    row.hidden = !hit;
                    if (hit) n++;
                });

                if (count) count.textContent = n;
            });
        }
    })();
    </script>

<?php else: ?>

    <?php tqa_head(t('اللغات والترجمة'),
        t('كل لغة ملف ترجمة واحد. واتجاه الكتابة يتبع اللغة ولا يضبط لكل صفحة.'),
        'translate'); ?>

    <div class="tqa-card tqa-card--flush tqa-section">
        <div class="tqa-card__head">
            <span class="tqa-iconbox" aria-hidden="true"><?php echo tq_icon('globe', 20); ?></span>
            <h2><?php echo t('اللغات المركبة'); ?></h2>
        </div>

        <div class="tqa-table__wrap">
            <table class="tqa-table">
                <caption class="tqa-sr"><?php echo t('اللغات المركبة واتجاه كل منها'); ?></caption>
                <thead>
                    <tr>
                        <th><?php echo t('اللغة'); ?></th>
                        <th style="inline-size:220px"><?php echo t('اتجاه الكتابة'); ?></th>
                        <th style="inline-size:320px"><span class="tqa-sr"><?php echo t('إجراءات'); ?></span></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($languages as $tq_l):
                    $tq_dir = $tq_dirs[$tq_l] ?? 'ltr';
                    $tq_is  = $tq_active === $tq_l;
                ?>
                    <tr>
                        <td data-label="اللغة">
                            <span class="tqa-media__title"><?php echo html_escape(ucwords($tq_l)); ?></span>
                            <?php if ($tq_is): ?>
                                <span class="tqa-badge tqa-badge--ok"><?php echo t('لغة النظام'); ?></span>
                            <?php endif; ?>
                        </td>

                        <td data-label="اتجاه الكتابة">
                            <div class="tqa-checkrow">
                                <?php foreach (array('ltr' => t('من اليسار'), 'rtl' => t('من اليمين')) as $tq_d => $tq_dl): ?>
                                    <label class="tqa-check">
                                        <?php /* الاسم فريد لكل لغة: الاسم المشترك يجمع الأزرار
                                                 عبر الصفوف، فاختيار اتجاه لغة يلغي اختيار جارتها. */ ?>
                                        <input type="radio" name="dir_<?php echo html_escape($tq_l); ?>"
                                               value="<?php echo $tq_d; ?>"
                                               data-tqa-dir="<?php echo html_escape($tq_l); ?>"
                                               <?php echo $tq_dir === $tq_d ? 'checked' : ''; ?>>
                                        <span><?php echo $tq_dl; ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </td>

                        <td data-label="إجراءات">
                            <div class="tqa-rowacts">
                                <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                                   href="<?php echo site_url('admin/manage_language/edit_phrase/' . rawurlencode($tq_l)); ?>">
                                    <?php echo tq_icon('edit', 14); ?> <?php echo t('تحرير العبارات'); ?>
                                </a>

                                <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                                   href="<?php echo site_url('admin/export_language/' . rawurlencode($tq_l)); ?>">
                                    <?php echo tq_icon('download', 14); ?> <?php echo t('تصدير'); ?>
                                </a>

                                <?php if (!$tq_is): ?>
                                    <form method="post"
                                          action="<?php echo site_url('admin/manage_language/delete_language/' . rawurlencode($tq_l)); ?>"
                                          data-tqa-confirm-title="<?php echo te('حذف اللغة'); ?>"
                                          data-tqa-confirm="<?php echo te('سيحذف ملف ترجمة «____» كاملا. لا رجعة في هذا.', array(html_escape(ucwords($tq_l)))); ?>"
                                          data-tqa-confirm-ok="<?php echo te('نعم، احذف'); ?>"
                                          data-tqa-confirm-tone="danger">
                                        <?php echo tq_csrf(); ?>
                                        <button type="submit" class="tqa-btn tqa-btn--ghost tqa-btn--sm tqa-btn--quiet-danger">
                                            <?php echo tq_icon('trash', 14); ?> <?php echo t('حذف'); ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="tqa-badge tqa-badge--muted"><?php echo t('لا تحذف لغة النظام'); ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="tqa-grid tqa-grid--3">

        <form class="tqa-card" method="post" action="<?php echo site_url('admin/manage_language/add_language'); ?>">
            <?php echo tq_csrf(); ?>
            <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
                <span class="tqa-iconbox tqa-mint" aria-hidden="true"><?php echo tq_icon('plus', 20); ?></span>
                <h2><?php echo t('لغة جديدة'); ?></h2>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="language"><?php echo t('اسم اللغة'); ?></label>
                <input class="tqa-input tqa-input--ltr" type="text" id="language" name="language" dir="ltr"
                       required pattern="[A-Za-z]+" placeholder="French">
                <span class="tqa-field__hint"><?php echo t('حروف لاتينية بلا مسافات ولا رموز.'); ?></span>
            </div>

            <div class="tqa-actions">
                <button type="submit" class="tqa-btn tqa-btn--primary">
                    <?php echo tq_icon('plus', 16); ?> <?php echo t('أضف اللغة'); ?>
                </button>
            </div>
        </form>

        <?php /* كان هذا النموذج مرسوما في الصفحة بلا رابط يفتحه (تبويب
                 `#add` غير مذكور في شريط التبويبات) — ميزة كاملة كتبت
                 ولم تعرض قط. */ ?>
        <form class="tqa-card" method="post" action="<?php echo site_url('admin/manage_language/add_phrase'); ?>">
            <?php echo tq_csrf(); ?>
            <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
                <span class="tqa-iconbox tqa-sky" aria-hidden="true"><?php echo tq_icon('edit', 20); ?></span>
                <h2><?php echo t('عبارة جديدة'); ?></h2>
            </div>

            <div class="tqa-field">
                <label class="tqa-field__label" for="phrase"><?php echo t('مفتاح العبارة'); ?></label>
                <input class="tqa-input tqa-input--ltr" type="text" id="phrase" name="phrase" dir="ltr"
                       required placeholder="course_completed">
                <span class="tqa-field__hint"><?php echo t('تضاف إلى كل اللغات بقيمة فارغة.'); ?></span>
            </div>

            <div class="tqa-actions">
                <button type="submit" class="tqa-btn tqa-btn--primary">
                    <?php echo tq_icon('plus', 16); ?> <?php echo t('أضف العبارة'); ?>
                </button>
            </div>
        </form>

        <form class="tqa-card" method="post" enctype="multipart/form-data"
              action="<?php echo site_url('admin/language_import'); ?>">
            <?php echo tq_csrf(); ?>
            <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
                <span class="tqa-iconbox tqa-peach" aria-hidden="true"><?php echo tq_icon('import', 20); ?></span>
                <h2><?php echo t('استيراد ملف'); ?></h2>
            </div>

            <div class="tqa-field">
                <span class="tqa-field__label"><?php echo t('ملفات JSON'); ?></span>
                <div class="tqa-file">
                    <input type="file" id="language_files" name="language_files[]" accept=".json" multiple required
                           data-tqa-file>
                    <label class="tqa-file__btn" for="language_files">
                        <?php echo tq_icon('upload', 16); ?> <?php echo t('اختر ملفات'); ?>
                    </label>
                    <span class="tqa-file__name" data-tqa-file-name><?php echo t('مثال: english.json'); ?></span>
                </div>
                <span class="tqa-field__hint"><?php echo t('اسم الملف هو اسم اللغة — والموجود يستبدل.'); ?></span>
            </div>

            <div class="tqa-actions">
                <button type="submit" class="tqa-btn tqa-btn--primary">
                    <?php echo tq_icon('import', 16); ?> <?php echo t('استورد'); ?>
                </button>
            </div>
        </form>
    </div>

    <script>
    (function () {
        'use strict';

        var URL  = <?php echo json_encode(site_url('admin/update_language_direction')); ?>;
        var CSRF = window.TQ_CSRF || null;

        Array.prototype.forEach.call(document.querySelectorAll('[data-tqa-dir]'), function (radio) {
            radio.addEventListener('change', function () {
                if (!radio.checked) return;

                var body = new URLSearchParams();
                body.set('language', radio.getAttribute('data-tqa-dir'));
                body.set('dir', radio.value);
                if (CSRF && CSRF.name) body.set(CSRF.name, CSRF.hash);

                fetch(URL, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString()
                }).then(function (r) {
                    if (!r.ok) throw new Error(r.status);
                    if (window.TQA) TQA.ok('حدث اتجاه الكتابة');
                }).catch(function () {
                    if (window.TQA) TQA.error('لم يحفظ الاتجاه. حدث الصفحة وأعد المحاولة.');
                });
            });
        });
    })();
    </script>

    <?php include 'tqa_file_js.php'; ?>

<?php endif; ?>
