<?php
/**
 * بوابة المعلم — كتاب: إنشاء أو تعديل (TQ-BOOK).
 *
 * ═══ قالب واحد للحالين ═══
 *
 * والفارق `$tq_row` وحده. وقالبان يفترقان عند أول حقل يضاف: يظهر في
 * الإضافة ولا يظهر في التعديل، فيعدل المعلم كتابا فتمحى منه قيمة لم
 * تعرض له أصلا. وهو مبدأ `tqa_teacher_form.php` نفسه.
 *
 * ═══ والحقول من الوصف لا من هنا ═══
 *
 * `Taqdar_book_model::book_fields($actor)` تصف كل حقل بنوعه وتلميحه
 * وصاحبه، وهذه الشاشة تطبع ما يملكه المعلم منها. فالحقل الجديد يضاف
 * **هناك وحده** فيظهر في الشاشتين ويحفظ ويتحقق بلا قالب يكتب — وهو
 * مبدأ `Taqdar_admin_model::spec()` و`lesson_types()` نفسه.
 *
 * ═══ وما يقرره غيره يعرض ولا يحرر ═══
 *
 * السعر والصف والنسبة والوزن قرارات إدارة. وإخفاؤها كاملة يجعل المعلم
 * يظن أن كتابه بلا سعر لأنه نسي أن يكتبه، ويظن أنه في الباقات وهو ليس
 * فيها. فتعرض **مقروءة** في لوح جانبي بقيمها الحالية.
 */

include 'tq_student_styles.php';

$tq_nav   = 'books';
$tq_role  = 'teacher';
$tq_row   = isset($tq_row) ? $tq_row : null;
$tq_is_ed = ($tq_row !== null);
$tq_title = $tq_is_ed ? t('تعديل كتاب') : t('كتاب جديد');
$tq_sub   = $tq_is_ed
    ? t('عدل ما تملكه من هذا الكتاب — وما تعلنه «منشورا» يمر بمراجعة الإدارة.')
    : t('ارفع ملف PDF وعرف كتابك. وينشر بعد أن تعتمده الإدارة.');
$tq_icon  = 'book';

$tq_uid = (int) $this->session->userdata('user_id');

$tq_ci = &get_instance();
$tq_ci->load->model('taqdar_book_model', 'tq_bk');

$tq_actor  = array('id' => $tq_uid, 'role' => 'teacher');
$tq_fields = $tq_ci->tq_bk->book_fields($tq_actor);
$tq_cfg    = $tq_ci->tq_bk->config();
$tq_direct = $tq_ci->tq_bk->may_publish($tq_actor);

/* حالات يستطيع المعلم أن يعلنها. و«مرفوض» ليست منها: هي قرار مراجع
   لا إعلان مؤلف، وعرضها في منتق يجعله يرد كتابه على نفسه. */
$tq_st_opts = array(
    'draft'     => t('مسودة — لا يراه أحد'),
    'published' => $tq_direct ? t('منشور — يقرؤه طلابك') : t('منشور — يرسل إلى مراجعة الإدارة'),
);

$tq_val = function ($col, $default = '') use ($tq_row) {
    if (!$tq_row || !array_key_exists($col, $tq_row) || $tq_row[$col] === null) return $default;
    return $tq_row[$col];
};

/* أقسام الحقول بترتيبها في الوصف: `section` تفصل مجموعة، وما بعدها
   يتبعها حتى يأتي `section` أخرى — كما تقرأ `tqa_form.php` تماما. */
$tq_groups = array();
$tq_cur    = t('الكتاب');
foreach ($tq_fields as $tq_k => $tq_f) {
    if (!empty($tq_f['section'])) $tq_cur = $tq_f['section'];
    $tq_groups[$tq_cur][$tq_k] = $tq_f;
}

include 'portal_open.php';
?>

<div class="tq-cols">
    <div>

        <?php if ($m = tq_flash('tq_error')): ?>
            <div class="tq-alert tq-alert--no tq-section" role="alert"><?php echo html_escape($m); ?></div>
        <?php endif; ?>

        <?php /* **الرفض يقرأ قبل النموذج لا بعده**: من فتح الشاشة ليصحح
                 يحتاج أن يعرف ما يصحح، والسبب في أسفلها يقرأ بعد أن
                 يكون قد أعاد الإرسال. */ ?>
        <?php if ($tq_is_ed && (string) $tq_row['status'] === 'rejected'
                  && trim((string) $tq_row['tq_review_note']) !== ''): ?>
            <div class="tq-alert tq-alert--no tq-section" role="status">
                <strong><?php echo t('رد كتابك للتعديل.'); ?></strong>
                <?php echo html_escape($tq_row['tq_review_note']); ?>
            </div>
        <?php elseif ($tq_is_ed && (string) $tq_row['status'] === 'review'): ?>
            <div class="tq-alert tq-alert--ok tq-section" role="status">
                <?php echo t('كتابك بانتظار مراجعة الإدارة. وتعديلك هنا يبقيه في الطابور.'); ?>
            </div>
        <?php endif; ?>

        <?php /* `enctype` شرط حقل الملف — TQ-IMG-NORM. وبلاها يصل اسم
                 الملف نصا في `$_POST` ويحفظ الصف بلا ملف ولا خطأ يظهر. */ ?>
        <form method="post" action="<?php echo base_url('teacher/books/save'); ?>"
              enctype="multipart/form-data" class="tq-form">
            <?php echo tq_csrf(); ?>
            <input type="hidden" name="book_id" value="<?php echo $tq_is_ed ? (int) $tq_row['id'] : 0; ?>">

            <?php foreach ($tq_groups as $tq_gname => $tq_gfields): ?>
                <div class="tq-card tq-section">
                    <h2 class="tq-card__title"><?php echo html_escape($tq_gname); ?></h2>

                    <?php foreach ($tq_gfields as $tq_k => $tq_f):
                        $tq_id = 'bf-' . $tq_k;
                        $tq_v  = $tq_val($tq_f['col'], isset($tq_f['default']) ? $tq_f['default'] : '');
                    ?>
                        <div class="tq-field">
                            <label class="tq-label" for="<?php echo $tq_id; ?>">
                                <?php echo html_escape($tq_f['label']); ?>
                                <?php if (!empty($tq_f['required'])): ?><span aria-hidden="true">*</span><?php endif; ?>
                            </label>

                            <?php if ($tq_f['kind'] === 'textarea'): ?>
                                <textarea class="tq-input" id="<?php echo $tq_id; ?>" name="<?php echo $tq_k; ?>"
                                          rows="5"><?php echo html_escape((string) $tq_v); ?></textarea>

                            <?php elseif ($tq_f['kind'] === 'enum'): ?>
                                <select class="tq-input" id="<?php echo $tq_id; ?>" name="<?php echo $tq_k; ?>">
                                    <?php foreach ($tq_f['options'] as $tq_ok => $tq_ol): ?>
                                        <option value="<?php echo html_escape($tq_ok); ?>"
                                            <?php echo ((string) $tq_v === (string) $tq_ok) ? 'selected' : ''; ?>>
                                            <?php echo html_escape($tq_ol); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                            <?php elseif ($tq_f['kind'] === 'status'): ?>
                                <select class="tq-input" id="<?php echo $tq_id; ?>" name="<?php echo $tq_k; ?>">
                                    <?php foreach ($tq_st_opts as $tq_ok => $tq_ol): ?>
                                        <option value="<?php echo html_escape($tq_ok); ?>"
                                            <?php echo ((string) $tq_v === (string) $tq_ok) ? 'selected' : ''; ?>>
                                            <?php echo html_escape($tq_ol); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                            <?php elseif ($tq_f['kind'] === 'category'): ?>
                                <?php $tq_opts = $tq_ci->tq_bk->categories(); ?>
                                <select class="tq-input" id="<?php echo $tq_id; ?>" name="<?php echo $tq_k; ?>">
                                    <option value="0"><?php echo t('— بلا تحديد —'); ?></option>
                                    <?php foreach ($tq_opts as $tq_ok => $tq_ol): ?>
                                        <option value="<?php echo (int) $tq_ok; ?>"
                                            <?php echo ((int) $tq_v === (int) $tq_ok) ? 'selected' : ''; ?>>
                                            <?php echo html_escape($tq_ol); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                            <?php elseif ($tq_f['kind'] === 'image' || $tq_f['kind'] === 'doc'): ?>
                                <?php
                                /* اسم غير `$tq_cur`: ذاك يحمل اسم القسم
                                   في الحلقة أعلاه، وإعادة استعماله هنا
                                   تربك من يقرأ. */
                                $tq_have = trim((string) $tq_v);
                                $tq_isD  = ($tq_f['kind'] === 'doc');
                                ?>
                                <?php if ($tq_have !== ''): ?>
                                    <p class="tq-caption">
                                        <?php if ($tq_isD): ?>
                                            <a href="<?php echo base_url(ltrim($tq_have, '/')); ?>" target="_blank" rel="noopener">
                                                <?php echo html_escape(basename($tq_have)); ?></a>
                                        <?php else: ?>
                                            <img src="<?php echo base_url(ltrim($tq_have, '/')); ?>" alt=""
                                                 width="96" height="134" loading="lazy"
                                                 style="border-radius:var(--tq-radius-small)">
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                                <input class="tq-input" type="file" id="<?php echo $tq_id; ?>"
                                       name="<?php echo $tq_k; ?>"
                                       accept="<?php echo $tq_isD ? '.pdf' : '.jpg,.jpeg,.png,.webp'; ?>">
                                <?php if ($tq_have !== ''): ?>
                                    <?php /* المحو صريح: حفظ بلا اختيار ملف **لا يمس** ما
                                             هو محفوظ، فمن أراد إزالته يقولها. */ ?>
                                    <label class="tq-caption" style="display:block;margin-block-start:var(--tq-space-xs)">
                                        <input type="checkbox" name="<?php echo $tq_k; ?>__clear" value="1">
                                        <?php echo $tq_isD ? t('احذف الملف الحالي') : t('احذف الصورة الحالية'); ?>
                                    </label>
                                <?php endif; ?>

                            <?php elseif ($tq_f['kind'] === 'number'): ?>
                                <input class="tq-input" type="number" min="0" id="<?php echo $tq_id; ?>"
                                       name="<?php echo $tq_k; ?>" value="<?php echo html_escape((string) $tq_v); ?>">

                            <?php else: ?>
                                <input class="tq-input" type="text" id="<?php echo $tq_id; ?>"
                                       name="<?php echo $tq_k; ?>" value="<?php echo html_escape((string) $tq_v); ?>"
                                       <?php echo !empty($tq_f['required']) ? 'required' : ''; ?>
                                       <?php echo !empty($tq_f['ltr']) ? 'dir="ltr"' : ''; ?>>
                            <?php endif; ?>

                            <?php if (!empty($tq_f['hint'])): ?>
                                <p class="tq-caption"><?php echo html_escape($tq_f['hint']); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <div class="tq-row" style="gap:var(--tq-space-m)">
                <button class="tq-btn tq-btn--primary" type="submit">
                    <?php echo tq_icon('check', 16); ?>
                    <?php echo $tq_is_ed ? t('احفظ التعديل') : t('أضف الكتاب'); ?>
                </button>
                <a class="tq-btn tq-btn--ghost" href="<?php echo base_url('teacher/books'); ?>"><?php echo t('إلغاء'); ?></a>
            </div>
        </form>

        <?php /* TQ-BOOK-DELETE — والحذف نموذج مستقل خارج نموذج الحفظ:
                 نموذج داخل نموذج لا يصح في HTML، والمتصفح يفكهما فيرسل
                 زر الحذف الحفظ أو العكس. وزر الحذف لا يعرض أصلا حين
                 يوجد مانع: زر يرد كل مرة يقرأ عطلا. */ ?>
        <?php if ($tq_is_ed && $tq_ci->tq_bk->may_delete((int) $tq_row['id'])): ?>
            <form method="post" action="<?php echo base_url('teacher/books/delete'); ?>"
                  class="tq-section"
                  onsubmit="return confirm('<?php echo te('يحذف هذا الكتاب وملفه. لا رجعة.'); ?>');">
                <?php echo tq_csrf(); ?>
                <input type="hidden" name="book_id" value="<?php echo (int) $tq_row['id']; ?>">
                <button class="tq-btn tq-btn--ghost tq-btn--sm" type="submit"><?php echo t('احذف الكتاب'); ?></button>
            </form>
        <?php elseif ($tq_is_ed): ?>
            <p class="tq-caption tq-section">
                <?php echo t('لا يحذف هذا الكتاب: بيع أو قيد له نصيب في دفترك. واجعله «مسودة» لترفعه من العرض، ويبقى لمن اشتراه.'); ?>
            </p>
        <?php endif; ?>

    </div>

    <aside>
        <?php /* ما تقرره الإدارة يعرض ولا يحرر: قرار لا يعلمه صاحبه ليس
                 شفافية، وإخفاؤه يجعل المعلم يظن أن كتابه بلا سعر لأنه
                 نسي أن يكتبه. */ ?>
        <div class="tq-card tq-section">
            <h2 class="tq-card__title"><?php echo t('ما تقرره الإدارة'); ?></h2>
            <?php if ($tq_is_ed): ?>
                <?php $tq_o = $tq_ci->tq_bk->offer($tq_row); ?>
                <table class="tq-table">
                    <tbody>
                        <tr>
                            <th scope="row"><?php echo t('الصف'); ?></th>
                            <td>
                                <?php if ((int) $tq_row['grade_id'] > 0): ?>
                                    <?php echo tq_num(t('تفتحه باقات صفه'), 'tq-num--sm'); ?>
                                <?php else: ?>
                                    <span class="tq-micro"><?php echo t('بلا صف — لا تفتحه باقة'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo t('البيع المفرد'); ?></th>
                            <td>
                                <?php if (!empty($tq_o['marked']) && (int) $tq_o['price'] > 0): ?>
                                    <span class="tq-ltr"><?php echo number_format(((int) $tq_o['price']) / 100, 2); ?></span>
                                    <?php echo t('ر.س'); ?>
                                <?php else: ?>
                                    <span class="tq-micro"><?php echo t('لا يباع'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo t('نصيبك من البيعة'); ?></th>
                            <td>
                                <?php if (!empty($tq_o['marked']) && (int) $tq_o['price'] > 0): ?>
                                    <span class="tq-ltr"><?php echo number_format(((int) $tq_o['share']) / 100, 2); ?></span>
                                    <?php echo t('ر.س'); ?>
                                    (<span class="tq-ltr"><?php echo rtrim(rtrim(number_format((float) $tq_o['percent'], 2), '0'), '.'); ?>%</span>)
                                <?php else: ?>
                                    <span class="tq-micro">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php echo t('وزنه في وعاء الباقة'); ?></th>
                            <td><?php echo tq_num((int) $tq_o['weight'], 'tq-num--sm'); ?>
                                <span class="tq-micro"><?php echo t('بمعادل الدروس'); ?></span></td>
                        </tr>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="tq-caption">
                    <?php echo t('السعر والصف والنسبة تحددها الإدارة بعد أن يعتمد كتابك — وتظهر هنا حينها.'); ?>
                </p>
            <?php endif; ?>
        </div>
    </aside>
</div>

<?php include 'portal_close.php'; ?>
