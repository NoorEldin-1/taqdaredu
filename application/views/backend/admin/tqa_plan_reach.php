<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * ما تفتحه هذه الباقة — محسوبا لا مكتوبا.
 *
 * الباقة لا تربط بدرس ولا بدورة: تختار صفوفا، والصف يجمع مسارات المنهج
 * المنشورة، والمسار يحمل دورته ودروسها. فالسؤال «ما محتوى هذه الباقة؟»
 * جوابه استعلام لا حقل — وهذه اللوحة تجريه أمام المسؤول **قبل الحفظ**،
 * فيرى ما سيبيع وهو يعلم الصفوف لا بعد أن يفتح الصفحة العامة.
 *
 * والصفر يقال صفرا: صف بلا مسار منشور يعلن ذلك في مكانه، ومجموع
 * «صفر درسا» تحت باقة بأربعمئة ريال إنذار في موضعه — لا رقم يخفى.
 */
$M     = &get_instance()->taqdar_admin_model;
$map   = $M->grade_reach_map();
$gopts = $M->options('grades');

$now_scope = ($row && isset($row['scope'])) ? (string) $row['scope'] : 'grade';
$reach     = $M->plan_reach($row ? $row : array('scope' => $now_scope, 'scope_ids' => '', 'scope_id' => 0));

/* المختار الآن — لترسم اللوحة صحيحة قبل أن يصل السكربت وإن لم يصل. */
$sel = $row ? $M->plan_grade_ids($row) : array();

/* الخريطة تنقل إلى المتصفح كي يجمع وأنت تعلم الصفوف. والمواد والمعلمون
   قوائم لا أعداد: مادة واحدة تدرس في ستة صفوف، وجمع الأعداد يعدها ستا. */
$js_map = array();
foreach ($gopts as $gid => $gname) {
    $gid = (int) $gid;
    $js_map[$gid] = isset($map[$gid]) ? $map[$gid] : array(
        'paths' => 0, 'lessons' => 0, 'quizzes' => 0, 'free' => 0,
        'subjects' => array(), 'teachers' => array(),
    );
}

$stats = array(
    'grades'   => t('صفا'),
    'subjects' => t('مادة'),
    'paths'    => t('برنامجا'),
    'lessons'  => t('درسا'),
    'quizzes'  => t('اختبارا'),
    'teachers' => t('معلما'),
);
?>

<div class="tqa-card tqa-reach" style="margin-block-end:var(--tq-space-l)"
     data-tqa-reach
     data-tqa-reach-map="<?php echo html_escape(json_encode($js_map)); ?>">

    <div class="tqa-card__head">
        <div>
            <h2 class="tqa-reach__title"><?php echo t('ما تفتحه هذه الباقة'); ?></h2>
            <p class="tqa-reach__lead">
                <?php echo t('محسوب من الصفوف المختارة أعلاه: كل مسار منشور فيها، ومعه دورته ودروسها. ولا يربط الدرس بالباقة بيد — فالدرس الذي ينشر غدا في هذه الصفوف يدخلها وحده.'); ?>
            </p>
        </div>
    </div>

    <div class="tqa-card__body">
        <div class="tqa-reach__nums">
            <?php foreach ($stats as $skey => $slabel): ?>
                <div class="tqa-reach__num">
                    <b data-tqa-reach-n="<?php echo $skey; ?>"><?php echo (int) $reach[$skey]; ?></b>
                    <span><?php echo html_escape($slabel); ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <p class="tqa-reach__free">
            <?php echo t('ومنها'); ?> <b data-tqa-reach-n="free"><?php echo (int) $reach['free']; ?></b>
            <?php echo t('درسا مفتوحا للمعاينة قبل الشراء.'); ?>
        </p>

        <?php /* التفصيل صفا صفا: المجموع يقول «مئتا درس»، وهذا يقول أي
                 صف فيه صفر — وهو ما يصحح قبل النشر. */ ?>
        <div class="tqa-reach__rows" data-tqa-reach-rows
             <?php echo ($sel && $now_scope === 'grade') ? '' : 'hidden'; ?>>
            <?php foreach ($gopts as $gid => $gname):
                $g = isset($map[(int) $gid]) ? $map[(int) $gid] : null;
            ?>
                <div class="tqa-reach__row" data-tqa-reach-row="<?php echo (int) $gid; ?>"
                     <?php echo in_array((int) $gid, $sel, true) ? '' : 'hidden'; ?>>
                    <b><?php echo html_escape($gname); ?></b>
                    <?php if (!$g || (int) $g['paths'] === 0): ?>
                        <span class="tqa-warn"><?php echo t('لا مسار منشور في هذا الصف بعد'); ?></span>
                    <?php else: ?>
                        <span>
                            <?php echo (int) $g['paths']; ?> برنامجا ·
                            <?php echo (int) $g['lessons']; ?> درسا ·
                            <?php echo (int) $g['quizzes']; ?> اختبارا
                        </span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <p class="tqa-reach__note" data-tqa-reach-note hidden></p>

        <?php if ($rid > 0 && $row): ?>
            <?php $vis = $M->plan_visibility($row); ?>
            <div class="tqa-reach__vis tqa-reach__vis--<?php echo html_escape($vis['tone']); ?>">
                <b><?php echo html_escape($vis['label']); ?></b>
                <span><?php echo html_escape($vis['why']); ?></span>
                <?php if ($vis['tone'] === 'ok'): ?>
                    <a class="tqa-btn tqa-btn--ghost tqa-btn--sm" target="_blank" rel="noopener"
                       href="<?php echo base_url('plan/' . (string) $row['code']); ?>">
                        <?php echo t('عاينها كما يراها المشتري'); ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
