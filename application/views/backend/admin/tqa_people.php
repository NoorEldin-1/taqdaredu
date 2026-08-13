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
    ''         => array('كل الحسابات', 'all'),
    'student'  => array('الطلاب',      'student'),
    'teacher'  => array('المعلمون',    'teacher'),
    'parent'   => array('أولياء الأمور', 'parent'),
    'admin'    => array('المسؤولون',   'admin'),
    'disabled' => array('حسابات مغلقة', 'disabled'),
);

/** الدور يشتق بقواعد `tq_role()` نفسها — لا استعلام لكل صف. */
$role_of = function ($u) {
    if ((int) $u['is_instructor'] === 1) return array('معلم', 'info');
    if ((string) $u['tq_gate'] === 'parent') return array('ولي أمر', 'warn');
    if ((int) $u['role_id'] === 1) return array('مسؤول', 'danger');
    return array('طالب', 'muted');
};

$back = http_build_query(array_filter(array('role' => $role, 'q' => $q), 'strlen'));
?>

<?php tqa_head('كل الحسابات', 'الأدوار الأربعة في مكان واحد: طالب · معلم · ولي أمر · مسؤول.', 'users'); ?>

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
        <label class="tqa-sr" for="tqa-people-q">ابحث بالاسم أو البريد أو الجوال</label>
        <input class="tqa-input" id="tqa-people-q" type="search" name="q"
               value="<?php echo html_escape($q); ?>"
               placeholder="الاسم أو البريد أو الجوال…">
        <button class="tqa-btn tqa-btn--primary tqa-btn--sm" type="submit">ابحث</button>
        <?php if ($q !== ''): ?>
            <a class="tqa-btn tqa-btn--ghost tqa-btn--sm"
               href="<?php echo site_url('taqdar_admin/people' . ($role ? '?role=' . $role : '')); ?>">امسح البحث</a>
        <?php endif; ?>
    </form>
</div>

<div class="tqa-card tqa-card--flush">
<?php if (!$rows): ?>

    <?php tqa_empty(
        $q !== '' ? 'لا حساب يطابق البحث' : 'لا حسابات في هذه الفئة',
        $q !== ''
            ? 'جرب جزءا من الاسم أو البريد، أو ابحث برقم الجوال بلا مفتاح الدولة.'
            : 'الحسابات تنشأ من صفحة التسجيل في الموقع. وولي الأمر يظهر هنا بعد أن يختار بوابته عند التسجيل.',
        '', '', 'users'
    ); ?>

<?php else: ?>
    <div class="tqa-table__wrap">
    <table class="tqa-table">
        <thead>
            <tr>
                <th>#</th>
                <th>الاسم</th>
                <th>البريد والجوال</th>
                <th>الدور</th>
                <th>الحالة</th>
                <th>التسجيل</th>
                <th><span class="tqa-sr">إجراء</span></th>
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

                <td data-label="الاسم"><?php echo html_escape($name ?: 'بلا اسم'); ?></td>

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
                        <?php echo $on ? 'مفتوح' : 'مغلق'; ?>
                    </span>
                </td>

                <td data-label="التسجيل">
                    <span class="tqa-num"><?php
                        echo !empty($u['date_added']) ? date('Y-m-d', (int) $u['date_added']) : '—';
                    ?></span>
                </td>

                <td data-label="إجراء">
                    <?php /* الفتح والإغلاق POST: يغير من يستطيع الدخول، ورابط
                             يفعل ذلك بمجرد فتحه لا يصلح لفعل يكتب. */ ?>
                    <form action="<?php echo site_url('taqdar_admin/people_toggle'); ?>" method="post"
                          style="margin:0"
                          data-tqa-confirm-title="<?php echo $on ? 'إغلاق الحساب' : 'فتح الحساب'; ?>"
                          data-tqa-confirm="<?php echo $on
                              ? 'لن يستطيع صاحبه الدخول. ولا يحذف شيء: اشتراكاته وتقدمه وفواتيره تبقى كما هي.'
                              : 'سيستطيع صاحبه الدخول من جديد، ويستعيد كل ما كان له.'; ?>"
                          data-tqa-confirm-ok="<?php echo $on ? 'أغلق الحساب' : 'افتح الحساب'; ?>"
                          <?php echo $on ? 'data-tqa-confirm-tone="danger"' : ''; ?>>
                        <?php echo tq_csrf(); ?>
                        <input type="hidden" name="user_id" value="<?php echo (int) $u['id']; ?>">
                        <input type="hidden" name="back" value="<?php echo html_escape($back); ?>">
                        <button class="tqa-btn tqa-btn--ghost tqa-btn--sm" type="submit">
                            <?php echo $on ? 'أغلق' : 'افتح'; ?>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <?php if (count($rows) >= 400): ?>
        <p style="padding:var(--tq-space-l) var(--tq-space-xl);margin:0;font:var(--tq-type-caption);color:var(--tq-text2)">
            تعرض أول <span class="tqa-num">400</span> حساب. استعمل البحث للوصول إلى ما بعدها.
        </p>
    <?php endif; ?>
<?php endif; ?>
</div>

<div class="tqa-note" style="margin-block-start:var(--tq-space-l)">
    <span aria-hidden="true"><?php echo tq_icon('shield', 18); ?></span>
    <span>
        الإغلاق يمنع الدخول ولا يحذف شيئا: الاشتراكات والتقدم والفواتير تبقى كما هي،
        ويستعيدها صاحبها كاملة متى فتح حسابه. وحذف الحساب فعل آخر يبدأ من صاحبه
        في إعدادات بوابته.
    </span>
</div>
