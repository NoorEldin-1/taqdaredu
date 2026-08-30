<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * كل الحسابات.
 *
 * كانت الناس في ثلاث شاشات موروثة (`admins` · `instructors` · `users`)
 * تقسمهم بقسمة Academy: مسؤول ومحاضر وطالب. وأدوار تقدر أربعة —
 * و**ولي الأمر** لم تكن له شاشة أصلا: يسجل، ويربط بأبنائه، ويدفع،
 * ولا يظهر في أي قائمة في اللوحة كلها.
 *
 * والبحث يشمل الجوال: الدعم يصل غالبا برقم لا ببريد.
 */
$filters = array(
    ''         => array(t('كل الحسابات'), 'all'),
    'student'  => array(t('الطلاب'),      'student'),
    'teacher'  => array(t('المعلمون'),    'teacher'),
    'parent'   => array(t('أولياء الأمور'), 'parent'),
    'admin'    => array(t('المسؤولون'),   'admin'),
    'disabled' => array(t('حسابات مغلقة'), 'disabled'),
);

/** الدور يشتق بقواعد `tq_role()` نفسها — لا استعلام لكل صف. */
$role_of = function ($u) {
    if ((int) $u['is_instructor'] === 1) return array(t('معلم'), 'info');
    if ((string) $u['tq_gate'] === 'parent') return array(t('ولي أمر'), 'warn');
    if ((int) $u['role_id'] === 1) return array(t('مسؤول'), 'danger');
    return array(t('طالب'), 'muted');
};

$back = http_build_query(array_filter(array('role' => $role, 'q' => $q), 'strlen'));
?>

<?php tqa_head(t('كل الحسابات'), t('الأدوار الأربعة في مكان واحد: طالب · معلم · ولي أمر · مسؤول.'), 'users'); ?>

<div class="tqa-tabs">
    <?php foreach ($filters as $k => [$label, $tally_key]): ?>
        <a href="<?php echo site_url('taqdar_admin/people' . ($k ? '?role=' . $k : '')); ?>"
           <?php echo $role === $k ? 'aria-current="page"' : ''; ?>>
            <?php echo html_escape($label); ?>
            <span class="tqa-num">(<?php echo (int) ($tally[$tally_key] ?? 0); ?>)</span>
        </a>
    <?php endforeach; ?>
</div>

<div class="tqa-toolbar">
    <form action="<?php echo site_url('taqdar_admin/people'); ?>" method="get" role="search">
        <?php if ($role !== ''): ?>
            <input type="hidden" name="role" value="<?php echo html_escape($role); ?>">
        <?php endif; ?>
        <label class="tqa-sr" for="tqa-people-q"><?php echo t('ابحث بالاسم أو البريد أو الجوال'); ?></label>
        <input class="tqa-input" id="tqa-people-q" type="search" name="q"
               value="<?php echo html_escape($q); ?>"
               placeholder="<?php echo te('الاسم أو البريد أو الجوال…'); ?>">
        <button class="tqa-btn tqa-btn--primary tqa-btn--sm" type="submit"><?php echo t('ابحث'); ?></button>
        <?php if ($q !== ''): ?>
            <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
               href="<?php echo site_url('taqdar_admin/people' . ($role ? '?role=' . $role : '')); ?>"><?php echo t('امسح البحث'); ?></a>
        <?php endif; ?>
    </form>

    <?php /* TQ-TEACHER-ADD — الباب من هنا: من يفتح «كل الحسابات» ليضيف
             معلما لا يخطر له أن يبحث عنه في شاشة أخرى. */ ?>
    <a class="tqa-btn tqa-btn--primary tqa-btn--sm" style="margin-inline-start:auto"
       href="<?php echo site_url('taqdar_admin/teacher_new'); ?>">
        <?php echo tq_icon('user-check', 16); ?> <?php echo t('أضف معلما'); ?>
    </a>
</div>

<div class="tqa-card tqa-card--flush">
<?php if (!$rows): ?>

    <?php tqa_empty(
        $q !== '' ? t('لا حساب يطابق البحث') : t('لا حسابات في هذه الفئة'),
        $q !== ''
            ? t('جرب جزءا من الاسم أو البريد، أو ابحث برقم الجوال بلا مفتاح الدولة.')
            : t('الحسابات تنشأ من صفحة التسجيل في الموقع، وحساب المعلم ينشأ من هنا كذلك بزر «أضف معلما». وولي الأمر يظهر هنا بعد أن يختار بوابته عند التسجيل.'),
        '', '', 'users'
    ); ?>

<?php else: ?>
    <div class="tqa-table__wrap">
    <table class="tqa-table">
        <thead>
            <tr>
                <th>#</th>
                <th><?php echo t('الاسم'); ?></th>
                <th><?php echo t('البريد والجوال'); ?></th>
                <th><?php echo t('الدور'); ?></th>
                <th><?php echo t('الحالة'); ?></th>
                <th><?php echo t('التسجيل'); ?></th>
                <th><span class="tqa-sr"><?php echo t('إجراء'); ?></span></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $u):
            [$role_label, $role_tone] = $role_of($u);
            $on = (int) $u['status'] === 1;
            $name = trim($u['first_name'] . ' ' . $u['last_name']);
        ?>
            <tr>
                <td data-label="#"><span class="tqa-num"><?php echo (int) $u['id']; ?></span></td>

                <td data-label="الاسم"><?php echo html_escape($name ?: t('بلا اسم')); ?></td>

                <td data-label="البريد والجوال">
                    <span class="tqa-num" style="font-size:13px"><?php echo html_escape($u['email']); ?></span>
                    <?php if (!empty($u['phone'])): ?>
                        <br><span class="tqa-num" style="font-size:12px;color:var(--tq-text2)">
                            <?php echo html_escape($u['phone']); ?></span>
                    <?php endif; ?>
                </td>

                <td data-label="الدور">
                    <span class="tqa-badge tqa-badge--<?php echo $role_tone; ?>"><?php echo $role_label; ?></span>
                </td>

                <td data-label="الحالة">
                    <span class="tqa-badge tqa-badge--<?php echo $on ? 'ok' : 'danger'; ?>">
                        <?php echo $on ? t('مفتوح') : t('مغلق'); ?>
                    </span>
                </td>

                <td data-label="التسجيل">
                    <span class="tqa-num"><?php
                        echo !empty($u['date_added']) ? date('Y-m-d', (int) $u['date_added']) : '—';
                    ?></span>
                </td>

                <td data-label="إجراء">
                  <div style="display:flex;gap:var(--tq-space-xs);flex-wrap:wrap;align-items:center">
                    <?php /* TQ-TEACHER-ADD — التفاصيل والتعديل للمعلم وحده:
                             نموذجهما نموذج معلم (صفة ومواد ونبذة وعرض علني)،
                             وفتحه على طالب يعرض حقولا لا معنى لها في حسابه.
                             والحذف بابه صفحة المعلم — هناك يقرأ ما يمنعه
                             بالرقم قبل أن يضغط. */ ?>
                    <?php if ((int) $u['is_instructor'] === 1): ?>
                        <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                           href="<?php echo site_url('taqdar_admin/teacher/' . (int) $u['id']); ?>"><?php echo t('تفاصيل'); ?></a>
                        <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
                           href="<?php echo site_url('taqdar_admin/teacher_edit/' . (int) $u['id']); ?>"><?php echo t('عدل'); ?></a>
                    <?php endif; ?>

                    <?php /* الفتح والإغلاق POST: يغير من يستطيع الدخول، ورابط
                             يفعل ذلك بمجرد فتحه لا يصلح لفعل يكتب. */ ?>
                    <form action="<?php echo site_url('taqdar_admin/people_toggle'); ?>" method="post"
                          style="margin:0"
                          data-tqa-confirm-title="<?php echo $on ? t('إغلاق الحساب') : t('فتح الحساب'); ?>"
                          data-tqa-confirm="<?php echo $on
                              ? t('لن يستطيع صاحبه الدخول. ولا يحذف شيء: اشتراكاته وتقدمه وفواتيره تبقى كما هي.')
                              : t('سيستطيع صاحبه الدخول من جديد، ويستعيد كل ما كان له.'); ?>"
                          data-tqa-confirm-ok="<?php echo $on ? t('أغلق الحساب') : t('افتح الحساب'); ?>"
                          <?php echo $on ? 'data-tqa-confirm-tone="danger"' : ''; ?>>
                        <?php echo tq_csrf(); ?>
                        <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                        <input type="hidden" name="back" value="<?php echo html_escape($back); ?>">
                        <button class="tqa-btn tqa-btn--ghost tqa-btn--sm" type="submit">
                            <?php echo $on ? t('أغلق') : t('افتح'); ?>
                        </button>
                    </form>
                  </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <?php if (count($rows) >= 400): ?>
        <p style="padding:var(--tq-space-l) var(--tq-space-xl);margin:0;font:var(--tq-type-caption);color:var(--tq-text2)">
            <?php echo t('تعرض أول'); ?> <span class="tqa-num">400</span> <?php echo t('حساب. استعمل البحث للوصول إلى ما بعدها.'); ?>
        </p>
    <?php endif; ?>
<?php endif; ?>
</div>

<div class="tqa-note" style="margin-block-start:var(--tq-space-l)">
    <span aria-hidden="true"><?php echo tq_icon('shield', 18); ?></span>
    <span>
        <?php echo t('الإغلاق يمنع الدخول ولا يحذف شيئا: الاشتراكات والتقدم والفواتير تبقى كما هي، ويستعيدها صاحبها كاملة متى فتح حسابه. وحذف الحساب فعل آخر يبدأ من صاحبه في إعدادات بوابته.'); ?>
    </span>
</div>
