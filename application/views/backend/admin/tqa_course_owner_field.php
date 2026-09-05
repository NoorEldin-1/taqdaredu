<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * صاحب الكورس — بطاقة مشتركة بين إضافة الكورس وتحريره.
 *
 * TQ-COURSE-OWNER — الكورس الذي ينشأ من اللوحة يولد ملكا للمسؤول
 * (`Crud_model::add_course()` تقرأ الجلسة)، و«من كتبه» مفتاح صلاحية ومال
 * لا بيان يعرض: به يرى المعلم كورسه في بوابته، وبه يصحح لطلابه، وبه
 * تقيد له مبيعاته، وبه يدخل وزن دروسه في وعاء الباقة. والقاعدة كلها في
 * [Taqdar_curriculum_model::set_course_owner()] — وهذه الشاشة تعرض ولا
 * تحكم.
 *
 * والمتغير الوحيد `$course_id` — ويسقط إلى صفر في شاشة الإضافة.
 */
$CI = get_instance();
$CI->load->model('taqdar_curriculum_model', 'tq_curric');

$tq_own_cid  = isset($course_id) ? (int) $course_id : 0;
$tq_own_now  = $tq_own_cid > 0 ? $CI->tq_curric->course_owner($tq_own_cid) : null;
$tq_own_list = $CI->tq_curric->teacher_choices();

/* المنتقي يفتح على المعلم المالك، وعلى «الإدارة» متى كان المالك مسؤولا
   — وهي الحال الافتراضية لكل ما أنشئ من اللوحة. وتركها على حالها لا
   يغير شيئا، انظر `set_course_owner()`. */
$tq_own_sel = ($tq_own_now && $tq_own_now['teacher']) ? (int) $tq_own_now['id'] : 0;
?>

<div class="tqa-card tqa-section">
    <div class="tqa-card__head" style="padding:0 0 var(--tq-space-l);margin-block-end:var(--tq-space-l)">
        <span class="tqa-iconbox tqa-sand" aria-hidden="true"><?php echo tq_icon('users', 20); ?></span>
        <div>
            <h2><?php echo t('المعلم صاحب الكورس'); ?></h2>
            <span class="tqa-media__sub"><?php echo t('يعامل الكورس كأنه من كتبه: يحرره من بوابته، ويصحح لطلابه، وتقيد له مبيعاته.'); ?></span>
        </div>
    </div>

    <input type="hidden" name="tq_owner_sent" value="1">

    <div class="tqa-field">
        <label class="tqa-field__label" for="tq_course_owner"><?php echo t('المعلم'); ?></label>
        <select class="tqa-select" id="tq_course_owner" name="tq_course_owner">
            <option value="0"><?php echo t('— الإدارة (بلا معلم)'); ?></option>
            <?php foreach ($tq_own_list as $tq_own_opt): ?>
                <option value="<?php echo (int) $tq_own_opt['id']; ?>"
                    <?php echo $tq_own_sel === (int) $tq_own_opt['id'] ? 'selected' : ''; ?>>
                    <?php echo html_escape($tq_own_opt['name'] . ' — ' . $tq_own_opt['email']);
                          echo $tq_own_opt['open'] ? '' : ' ' . te('(حساب مغلق)'); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <span class="tqa-field__hint">
            <?php if ($tq_own_cid <= 0): /* شاشة الإضافة: لا مالك بعد */ ?>
                <?php echo t('اتركه على «الإدارة» فيكون الكورس للمنصة، أو اختر معلما فيولد باسمه.'); ?>
            <?php elseif ($tq_own_now && !$tq_own_now['teacher']): ?>
                <?php echo t('الكورس الآن باسم الإدارة'); ?><?php
                    echo $tq_own_now['name'] !== '' ? ' («' . html_escape($tq_own_now['name']) . '»)' : ''; ?>.
                <?php echo t('اتركه كما هو فلا يتغير شيء، أو اختر معلما فينتقل إليه.'); ?>
            <?php else: ?>
                <?php echo t('واختيار «الإدارة» يسحب الكورس من معلمه ويعيده إلى المنصة.'); ?>
            <?php endif; ?>
        </span>
    </div>

    <?php if ($tq_own_now && $tq_own_now['co']): ?>
        <p class="tqa-note" style="margin-block-start:var(--tq-space-l)">
            <span aria-hidden="true"><?php echo tq_icon('help', 18); ?></span>
            <span><?php echo t('ومع صاحب الكورس'); ?> <strong><?php echo (int) count($tq_own_now['co']); ?></strong>
                  <?php echo t('معلما مشاركا يحرر معه، ولا يمسهم النقل.'); ?></span>
        </p>
    <?php endif; ?>

    <p class="tqa-note" style="margin-block-start:var(--tq-space-l)">
        <span aria-hidden="true"><?php echo tq_icon('help', 18); ?></span>
        <span>
            <?php if ($tq_own_cid <= 0): ?>
                <?php echo t('من تختاره هنا يصير صاحب الكورس وصاحب برنامجه في «المواد والبرامج» معا — وبالثاني يقتسم وعاء الباقة، وبالأول تقيد له مبيعاته المفردة.'); ?>
            <?php else: ?>
                <?php echo t('النقل يبدل صاحب الكورس وصاحب برنامجه في «المواد والبرامج» معا — وبالثاني يقتسم وعاء الباقة.'); ?>
                <strong><?php echo t('وما قيد من مال قبله لا يمس'); ?></strong>:
                <?php echo t('أنصبة المبيعات السابقة وقيود المحافظ تبقى لصاحبها، ووصول من دفع لا يتغير.'); ?>
            <?php endif; ?>
        </span>
    </p>
</div>
