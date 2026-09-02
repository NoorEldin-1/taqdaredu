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

$back = http_build_query(array_filter(array('role' => $role, 'q' => $q, 'p' => (string) $view['page']), 'strlen'));

/* الرقاقة تقول بماذا رشح، وتنزعه وحده. والدور مرشح كالبحث: من فتح
   «المعلمون» ثم بحث لا يرى في شريط الأدوات ما يقول إن نصف النتيجة
   محجوب بالتبويب — والتبويب أعلى الصفحة لا يراه من مرر. */
$active = array();
if ($q !== '')    $active['q'] = t('بحث:') . ' ' . $q;
if ($role !== '') $active['role'] = $filters[$role][0];
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
    <a class="tqa-btn tqa-btn--primary tqa-btn--sm tqa-toolbar__end"
       href="<?php echo site_url('taqdar_admin/teacher_new'); ?>">
        <?php echo tq_icon('user-check', 16); ?> <?php echo t('أضف معلما'); ?>
    </a>
</div>

<?php tqa_active_filters('taqdar_admin/people', $active); ?>

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
        <caption class="tqa-sr"><?php echo t('الحسابات: الاسم والتواصل والدور والحالة وتاريخ التسجيل'); ?></caption>
        <thead>
            <tr>
                <th class="tqa-col--tight">#</th>
                <th><?php echo t('الحساب'); ?></th>
                <th><?php echo t('الجوال'); ?></th>
                <th class="tqa-col--tight"><?php echo t('الدور'); ?></th>
                <th class="tqa-col--tight"><?php echo t('الحالة'); ?></th>
                <th class="tqa-col--tight"><?php echo t('التسجيل'); ?></th>
                <?php /* TQ-ROW-CLUTTER — العمود صار زرا واحدا، فعرضه عرضه.
                         وكان يحمل ثلاثة أزرار متساوية الوزن تلتف سطرين على
                         شاشة لوح، فيقف «تفاصيل» فوق «عدل» في كل صف من ثلاثين. */ ?>
                <th class="tqa-col--acts"><span class="tqa-sr"><?php echo t('إجراء'); ?></span></th>
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

                <?php /* الاسم والبريد والوجه شيء واحد يقرأ مرة: كانا عمودين
                         متجاورين، فالعين تقفز بين خليتين لتجمع هوية شخص
                         واحد — وفي جدول من خمسين صفا هذه قفزة في كل سطر.
                         والوجه هو ما تمسكه العين قبل الحرف. */ ?>
                <td data-label="الحساب">
                    <span class="tqa-media">
                        <?php echo tqa_avatar(isset($u['image']) ? $u['image'] : '', $name ?: $u['email']); ?>
                        <span class="tqa-media__body">
                            <span class="tqa-media__title"><?php echo html_escape($name ?: t('بلا اسم')); ?></span>
                            <span class="tqa-media__sub tqa-mono"><?php echo html_escape($u['email']); ?></span>
                        </span>
                    </span>
                </td>

                <td data-label="الجوال">
                    <?php if (trim((string) $u['phone']) !== ''): ?>
                        <span class="tqa-mono"><?php echo html_escape($u['phone']); ?></span>
                    <?php else: ?>
                        <span class="tqa-dim">—</span>
                    <?php endif; ?>
                </td>

                <td data-label="الدور">
                    <span class="tqa-badge tqa-badge--<?php echo $role_tone; ?>"><?php echo $role_label; ?></span>
                </td>

                <?php /* النقطة مع اللون: من لا يفرق الأخضر عن الأحمر — وهو
                         واحد من كل اثني عشر رجلا — كان يقرأ شارتين
                         متطابقتين في عمود يفرق بين حساب يدخل وحساب لا. */ ?>
                <td data-label="الحالة">
                    <span class="tqa-badge tqa-badge--dot tqa-badge--<?php echo $on ? 'ok' : 'danger'; ?>">
                        <?php echo $on ? t('مفتوح') : t('مغلق'); ?>
                    </span>
                </td>

                <td data-label="التسجيل">
                    <span class="tqa-num"><?php
                        echo !empty($u['date_added']) ? date('Y-m-d', (int) $u['date_added']) : '—';
                    ?></span>
                </td>

                <?php
                /* TQ-ROW-CLUTTER — ما يفعل بالحساب في قائمة واحدة.

                   كانت الخلية تحمل رابطين ونموذجا بزره، ثلاثتها بوزن واحد
                   ولون واحد: «تفاصيل» و«عدل» و«أغلق» — والثالث يقطع الدخول
                   عن صاحبه، وهو ملاصق للأول بحجمه. وعمود يحمل ثلاثة يأخذ
                   مئتي بكسل من جدول سبعة أعمدة ثم يلتف سطرين.

                   والترتيب هنا يقول ما لا يقوله الصف: المقصود أولا، ثم
                   التحرير، ثم ما يقطع الدخول تحت فاصل.

                   والتفاصيل والتعديل للمعلم وحده: نموذجهما نموذج معلم (صفة
                   ومواد ونبذة وعرض علني)، وفتحه على طالب يعرض حقولا لا
                   معنى لها في حسابه. والحذف بابه صفحة المعلم — هناك يقرأ ما
                   يمنعه بالرقم قبل أن يضغط (TQ-TEACHER-DELETE). */
                $tq_acts = array();

                if ((int) $u['is_instructor'] === 1) {
                    $tq_acts[] = array(
                        'label' => t('صفحة المعلم'),
                        'sub'   => t('ماذا يدرس؟ وكم طالبا عنده؟'),
                        'icon'  => 'meter',
                        'tone'  => 'go',
                        'href'  => site_url('taqdar_admin/teacher/' . (int) $u['id']),
                    );
                    $tq_acts[] = array(
                        'label' => t('تعديل البيانات'),
                        'icon'  => 'edit',
                        'href'  => site_url('taqdar_admin/teacher_edit/' . (int) $u['id']),
                    );
                    $tq_acts[] = array('sep' => true);
                }

                /* الفتح والإغلاق POST: يغير من يستطيع الدخول، ورابط يفعل
                   ذلك بمجرد فتحه لا يصلح لفعل يكتب. */
                $tq_acts[] = array(
                    'label'   => $on ? t('أغلق الحساب') : t('افتح الحساب'),
                    'sub'     => $on ? t('يمنع الدخول ولا يحذف شيئا') : t('يستعيد كل ما كان له'),
                    'icon'    => $on ? 'lock' : 'key',
                    'tone'    => $on ? 'danger' : '',
                    'action'  => 'taqdar_admin/people_toggle',
                    'hidden'  => array('user_id' => (int) $u['id'], 'back' => $back),
                    'confirm' => array(
                        'title' => $on ? t('إغلاق الحساب') : t('فتح الحساب'),
                        'body'  => $on
                            ? t('لن يستطيع صاحبه الدخول. ولا يحذف شيء: اشتراكاته وتقدمه وفواتيره تبقى كما هي.')
                            : t('سيستطيع صاحبه الدخول من جديد، ويستعيد كل ما كان له.'),
                        'ok'    => $on ? t('أغلق الحساب') : t('افتح الحساب'),
                        'tone'  => $on ? 'danger' : '',
                    ),
                );
                ?>
                <td class="tqa-col--acts" data-label="<?php echo te('إجراء'); ?>">
                    <?php echo tqa_rowmenu($tq_acts, array(
                        'title' => $name ?: $u['email'],
                        'sub'   => $role_label . ' · #' . (int) $u['id'],
                    )); ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <?php /* TQ-PEOPLE-CAP — كان هنا اعتذار: «تعرض أول ٤٠٠ حساب، استعمل
             البحث للوصول إلى ما بعدها». وهو يطلب من المسؤول أن يعرف اسم
             من يبحث عنه قبل أن يفتح الشاشة التي يفتحها ليعرف. */ ?>
    <div class="tqa-tablefoot">
        <?php if ($view['pages'] > 1): ?>
            <?php tqa_pager('taqdar_admin/people', $view['page'], $view['pages'], $view['total']); ?>
        <?php else: ?>
            <span class="tqa-pager__info">
                <?php echo t('المعروض'); ?>
                <span class="tqa-num"><?php echo count($rows); ?></span> <?php echo t('حسابا'); ?>
            </span>
        <?php endif; ?>
    </div>
<?php endif; ?>
</div>

<div class="tqa-note" style="margin-block-start:var(--tq-space-l)">
    <span aria-hidden="true"><?php echo tq_icon('shield', 18); ?></span>
    <span>
        <?php echo t('الإغلاق يمنع الدخول ولا يحذف شيئا: الاشتراكات والتقدم والفواتير تبقى كما هي، ويستعيدها صاحبها كاملة متى فتح حسابه. وحذف الحساب فعل آخر يبدأ من صاحبه في إعدادات بوابته.'); ?>
    </span>
</div>
