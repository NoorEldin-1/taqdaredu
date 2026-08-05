<?php
/**
 * الموقع العام — الأنماط المشتركة والقطع المتكرّرة.
 *
 * يُدرَج بـ include_once من كل صفحة عامّة فيُطبع مرّة واحدة في الطلب الواحد.
 * لا يُدرَج في صفحات البوّابة — لها غلافها الخاصّ.
 *
 * كل لون هنا توكن، وكل تخطيط منطقي (start/end) لا فيزيائي،
 * وكل رقم يمرّ من tq_num/tq_iso. لا #hex ولا rgb() ولا شبكة خارجية.
 */

if (!function_exists('tqp_plural')) {
    /**
     * العدد في العربية ليس s تُضاف: واحد · اثنان · جمع قلّة · تمييز مفرد منصوب.
     * والرقم معزول دائمًا وإلا انعكس ترتيبه حول المسافة.
     */
    function tqp_plural($n, $one, $two, $few, $many)
    {
        $n = (int) $n;
        if ($n === 1) return $one;
        if ($n === 2) return $two;
        if ($n <= 10) return TQ_LRI . $n . TQ_PDI . ' ' . $few;
        return TQ_LRI . $n . TQ_PDI . ' ' . $many;
    }
}

if (!function_exists('tqp_level')) {
    /** مستوى الدورة بالعربية — القيم في القاعدة إنجليزية وواجهتنا عربية. */
    function tqp_level($level)
    {
        $map = [
            'beginner'     => 'مبتدئ',
            'intermediate' => 'متوسّط',
            'advanced'     => 'متقدّم',
        ];
        $k = strtolower(trim((string) $level));
        return $map[$k] ?? '';
    }
}

if (!function_exists('tqp_duration')) {
    /**
     * مدّة الدورة كوحدة واحدة «01:20:00» لا ثلاثة أطراف.
     * تُعاد فارغة إن لم تُسجَّل مدّة — فالفراغ أصدق من صفر مُلفَّق.
     */
    function tqp_duration($course_id)
    {
        $CI = &get_instance();
        $raw = $CI->crud_model->get_total_duration_of_lesson_by_course_id($course_id);
        if (preg_match('/(\d{1,3}:\d{2}:\d{2})/', (string) $raw, $m) && $m[1] !== '00:00:00') {
            return $m[1];
        }
        return '';
    }
}

if (!function_exists('tqp_stars')) {
    /** نجوم التقييم كحلية لا برتقالية — البرتقالي إشارة ولا يُرسم أيقونةً على أبيض. */
    function tqp_stars($average, $count, $show_text = true)
    {
        $filled = (int) ceil($average);
        $out = '<span class="tqp-stars" aria-hidden="true">';
        for ($i = 1; $i <= 5; $i++) {
            $out .= '<span class="' . ($i <= $filled ? 'is-on' : '') . '">' . tq_icon('star', 14) . '</span>';
        }
        $out .= '</span>';

        if (!$show_text) {
            return $out;
        }

        if ($count > 0) {
            $out .= tq_num(number_format($average, 1), 'tq-num--sm');
            $out .= '<span class="tq-caption">'
                . tqp_plural($count, 'تقييم واحد', 'تقييمان', 'تقييمات', 'تقييمًا')
                . '</span>';
        } else {
            $out .= '<span class="tq-caption">لم يُقيَّم بعد</span>';
        }
        return $out;
    }
}

if (!function_exists('tqp_thumb')) {
    /**
     * صورة الدورة. حين لا تُضبط إعدادات الثيم يعود النموذج بـ base_url() وحده،
     * فنكشف ذلك ونرسم بديلًا بتدرّج CSS بدل صورة مكسورة أو صورة من الإنترنت.
     */
    function tqp_thumb($course_id)
    {
        $CI = &get_instance();
        $url = (string) $CI->crud_model->get_course_thumbnail_url($course_id);
        return (rtrim($url, '/') === rtrim(base_url(), '/')) ? '' : $url;
    }
}

if (!function_exists('tqp_flash')) {
    /** رسائل النظام: الخطأ يقول كيف يُصحَّح، والنجاح يقول ما الذي تمّ. */
    function tqp_flash()
    {
        $CI = &get_instance();
        $error = $CI->session->flashdata('error_message');
        $ok    = $CI->session->flashdata('flash_message');
        if ($error) {
            echo '<p class="tqp-alert tqp-alert--error" role="alert">'
                . tq_icon('x', 18) . '<span>' . html_escape($error) . '</span></p>';
        }
        if ($ok) {
            echo '<p class="tqp-alert tqp-alert--ok" role="status">'
                . tq_icon('check', 18) . '<span>' . html_escape($ok) . '</span></p>';
        }
    }
}

if (!function_exists('tqp_empty')) {
    /** الحالة الفارغة الصحيحة: عنوان يقول ما ينقص، وسطر، ومخرج واحد. */
    function tqp_empty($icon, $title, $text, $btn_text = '', $btn_url = '')
    {
        echo '<div class="tq-empty">';
        echo '<span class="tqp-empty__art" aria-hidden="true">' . tq_icon($icon, 40) . '</span>';
        echo '<h2 class="tq-empty__title">' . html_escape($title) . '</h2>';
        echo '<p class="tq-empty__text">' . html_escape($text) . '</p>';
        if ($btn_text !== '' && $btn_url !== '') {
            echo '<a class="tq-btn tq-btn--secondary" href="' . $btn_url . '">' . html_escape($btn_text) . '</a>';
        }
        echo '</div>';
    }
}

if (!function_exists('tqp_course_card')) {
    /**
     * بطاقة الدورة: العنوان · التقييم · عدد الطلاب · المدّة · السعر والخصم.
     * كل قيمة من قاعدة البيانات، وما لا قيمة له يُحذف سطره ولا يُملأ بصفر.
     */
    function tqp_course_card($course)
    {
        $CI = &get_instance();
        $id = (int) $course['id'];

        $link      = site_url('home/course/' . rawurlencode(slugify($course['title'])) . '/' . $id);
        $thumb     = tqp_thumb($id);
        $sum       = (float) $CI->crud_model->get_ratings('course', $id, true)->row()->rating;
        $count     = $CI->crud_model->get_ratings('course', $id)->num_rows();
        $average   = $count > 0 ? $sum / $count : 0;
        $students  = $CI->crud_model->enrol_history($id)->num_rows();
        $duration  = tqp_duration($id);
        $purchased = is_purchased($id);
        ?>
        <a class="tqp-course" href="<?php echo $link; ?>">
            <span class="tqp-course__media">
                <?php if ($thumb !== ''): ?>
                    <img src="<?php echo $thumb; ?>" alt="<?php echo html_escape($course['title']); ?>" loading="lazy">
                <?php else: ?>
                    <span class="tqp-course__fallback" aria-hidden="true"><?php echo tq_icon('book', 36); ?></span>
                <?php endif; ?>
            </span>

            <span class="tqp-course__body">
                <span class="tqp-course__badges">
                    <?php if ($purchased): ?>
                        <?php echo tq_badge('mastered', 'أنت مشترك'); ?>
                    <?php elseif (!empty($course['is_free_course'])): ?>
                        <?php echo tq_badge('progress', 'مجاني'); ?>
                    <?php elseif (!empty($course['discount_flag']) && (float) $course['price'] > 0): ?>
                        <?php
                        $off = (int) round((((float) $course['price'] - (float) $course['discounted_price']) / (float) $course['price']) * 100);
                        echo tq_badge('due', 'خصم ' . TQ_LRI . $off . '%' . TQ_PDI);
                        ?>
                    <?php endif; ?>
                    <?php if (tqp_level($course['level'] ?? '') !== ''): ?>
                        <span class="tq-micro tqp-course__level"><?php echo tqp_level($course['level']); ?></span>
                    <?php endif; ?>
                </span>

                <span class="tqp-course__title tqp-clamp2"><?php echo html_escape($course['title']); ?></span>

                <span class="tqp-course__rate"><?php echo tqp_stars($average, $count); ?></span>

                <span class="tqp-course__meta">
                    <span>
                        <?php echo tq_icon('users', 16); ?>
                        <?php echo tqp_plural($students, 'طالب واحد', 'طالبان', 'طلاب', 'طالبًا'); ?>
                    </span>
                    <?php if ($duration !== ''): ?>
                        <span>
                            <?php echo tq_icon('clock', 16); ?>
                            <span class="tq-sr">المدّة الكلّية</span>
                            <?php echo tq_num($duration, 'tq-num--sm'); ?>
                        </span>
                    <?php endif; ?>
                </span>

                <span class="tqp-course__foot">
                    <span class="tqp-price">
                        <?php if (!empty($course['is_free_course'])): ?>
                            <span class="tq-money">مجاني</span>
                        <?php elseif (!empty($course['discount_flag'])): ?>
                            <?php echo tq_sar($course['discounted_price']); ?>
                            <del class="tqp-was"><?php echo tq_sar($course['price']); ?></del>
                        <?php else: ?>
                            <?php echo tq_sar($course['price']); ?>
                        <?php endif; ?>
                    </span>
                    <span class="tqp-course__go" aria-hidden="true"><?php echo tq_icon('play', 18); ?></span>
                </span>
            </span>
        </a>
        <?php
    }
}

if (!defined('TQP_STYLES')) {
    define('TQP_STYLES', 1);
    ?>
<style>
/* ==== الموقع العام: أنماط الصفحات ==== */

.tqp-band       { padding-block: var(--tq-space-h3); }
.tqp-band--tint { background: var(--tq-surface); }

.tqp-head { margin-block-end: var(--tq-space-xxl); max-inline-size: 62ch; }
.tqp-head h2 { margin-block-end: var(--tq-space-s); }
.tqp-head p  { color: var(--tq-text2); margin: 0; }

.tqp-pagehead {
  padding-block: var(--tq-space-h2);
  background:
    radial-gradient(900px 320px at 85% -30%, var(--tq-sky-fill), transparent 62%),
    radial-gradient(700px 280px at 5% -10%, var(--tq-mint-fill), transparent 60%);
}
.tqp-pagehead h1 { margin-block-end: var(--tq-space-s); }
.tqp-pagehead p  { color: var(--tq-text2); max-inline-size: 66ch; margin: 0; }

.tqp-crumbs {
  display: flex; align-items: center; flex-wrap: wrap;
  gap: var(--tq-space-s);
  font: var(--tq-type-caption); color: var(--tq-text2);
  margin-block-end: var(--tq-space-l);
}
.tqp-crumbs a { color: var(--tq-text2); }
.tqp-crumbs a:hover { color: var(--tq-navy); }
.tqp-crumbs [aria-current='page'] { color: var(--tq-navy); font-weight: 700; }

.tqp-clamp2, .tqp-clamp3 { display: -webkit-box; -webkit-box-orient: vertical; overflow: hidden; }
.tqp-clamp2 { -webkit-line-clamp: 2; }
.tqp-clamp3 { -webkit-line-clamp: 3; }

/* ---- الواجهة ---- */
.tqp-hero__eyebrow { display: inline-flex; align-items: center; gap: var(--tq-space-s); margin-block-end: var(--tq-space-m); }
.tqp-hero h1 { margin-block-end: var(--tq-space-l); }
.tqp-hero__lead { color: var(--tq-text2); font-size: 17px; max-inline-size: 52ch; }
.tqp-hero__cta { display: flex; flex-wrap: wrap; gap: var(--tq-space-m); margin-block-start: var(--tq-space-xl); }
.tqp-hero__art {
  display: grid; place-items: center; gap: var(--tq-space-xl);
  padding: var(--tq-space-h2) var(--tq-space-xl);
  background: var(--tq-surface);
  border-radius: var(--tq-radius-soft);
  box-shadow: var(--tq-shadow-card);
}
.tqp-hero__art img { inline-size: min(280px, 70%); block-size: auto; }
.tqp-chips { display: flex; flex-wrap: wrap; gap: var(--tq-space-s); justify-content: center; }
.tqp-chip {
  padding: var(--tq-space-s) var(--tq-space-l);
  border-radius: var(--tq-radius-pill);
  font: var(--tq-type-caption); color: var(--tq-navy);
}

/* ---- لماذا تقدّر ---- */
.tqp-why { display: grid; gap: var(--tq-space-l); }
.tqp-why__card { display: grid; gap: var(--tq-space-s); align-content: start; block-size: 100%; }
.tqp-why__card p { margin: 0; }

/* ---- بطاقة الدورة ---- */
.tqp-course {
  display: flex; flex-direction: column;
  background: var(--tq-surface);
  border-radius: var(--tq-radius-card);
  box-shadow: var(--tq-shadow-soft);
  overflow: hidden; block-size: 100%;
  color: var(--tq-text);
  transition: box-shadow var(--tq-motion-hover) ease, transform var(--tq-motion-hover) ease;
}
.tqp-course:hover { box-shadow: var(--tq-shadow-card); transform: translateY(-2px); text-decoration: none; }
.tqp-course__media { display: block; aspect-ratio: 16 / 9; background: var(--tq-sky-fill); overflow: hidden; }
.tqp-course__media img { inline-size: 100%; block-size: 100%; object-fit: cover; }
.tqp-course__fallback {
  display: grid; place-items: center; block-size: 100%;
  background: linear-gradient(135deg, var(--tq-sky-fill), var(--tq-mint-fill));
  color: var(--tq-navy);
}
.tqp-course__body { display: flex; flex-direction: column; gap: var(--tq-space-s); padding: var(--tq-space-l); flex: 1; }
.tqp-course__badges { display: flex; align-items: center; flex-wrap: wrap; gap: var(--tq-space-s); }
.tqp-course__level { color: var(--tq-text2); }
.tqp-course__title { font: var(--tq-type-h2); color: var(--tq-navy); }
.tqp-course__rate  { display: flex; align-items: center; gap: var(--tq-space-s); flex-wrap: wrap; }
.tqp-course__meta  { display: flex; align-items: center; flex-wrap: wrap; gap: var(--tq-space-l); color: var(--tq-text2); font: var(--tq-type-caption); }
.tqp-course__meta > span { display: inline-flex; align-items: center; gap: var(--tq-space-xs); }
.tqp-course__foot {
  display: flex; align-items: center; justify-content: space-between; gap: var(--tq-space-s);
  margin-block-start: auto; padding-block-start: var(--tq-space-m);
  border-block-start: 1px solid var(--tq-line);
}
.tqp-course__go { color: var(--tq-teal); display: inline-flex; }

.tqp-price { display: flex; align-items: baseline; gap: var(--tq-space-s); flex-wrap: wrap; }
.tq-money { font: var(--tq-type-numeralMd); color: var(--tq-navy); }
.tqp-was { text-decoration: line-through; }
.tqp-was .tq-money { font: var(--tq-type-numeralSm); color: var(--tq-text2); }

.tqp-stars { display: inline-flex; align-items: center; gap: 1px; color: var(--tq-line); }
.tqp-stars .is-on { color: var(--tq-navy); }
.tqp-stars span { display: inline-flex; }

/* ---- الأقسام ---- */
.tqp-cat {
  display: flex; align-items: center; gap: var(--tq-space-m);
  padding: var(--tq-space-l);
  border-radius: var(--tq-radius-card);
  block-size: 100%; color: var(--tq-navy);
}
.tqp-cat:hover { text-decoration: none; box-shadow: var(--tq-shadow-raised); }
.tqp-cat__n { margin-inline-start: auto; }
.tqp-subcats { display: flex; flex-wrap: wrap; gap: var(--tq-space-s); margin-block-start: var(--tq-space-m); }

/* ---- الأرقام ---- */
.tqp-stats-band { background: var(--tq-surface); border-radius: var(--tq-radius-soft); padding: var(--tq-space-h1) var(--tq-space-xl); }

/* ---- آراء الطلاب ---- */
.tqp-quote {
  display: flex; flex-direction: column; gap: var(--tq-space-m);
  background: var(--tq-surface); border-radius: var(--tq-radius-card);
  box-shadow: var(--tq-shadow-soft); padding: var(--tq-space-xl); block-size: 100%;
}
.tqp-quote__text { color: var(--tq-text); margin: 0; }
.tqp-quote__who { display: flex; align-items: center; gap: var(--tq-space-m); margin-block-start: auto; }
.tqp-quote__name { display: block; font: var(--tq-type-bodyStrong); color: var(--tq-navy); }

/* ---- الدعوة ---- */
.tqp-cta {
  display: flex; align-items: center; justify-content: space-between;
  gap: var(--tq-space-xl); flex-wrap: wrap;
  padding: var(--tq-space-h2) var(--tq-space-h1);
  border-radius: var(--tq-radius-soft);
  background: linear-gradient(120deg, var(--tq-mint-fill), var(--tq-sky-fill));
}
.tqp-cta h2 { margin: 0 0 var(--tq-space-s); }
.tqp-cta p  { color: var(--tq-text2); margin: 0; max-inline-size: 60ch; }

/* ---- الحالة الفارغة ---- */
.tqp-empty__art {
  display: grid; place-items: center;
  inline-size: 96px; block-size: 96px;
  border-radius: var(--tq-radius-pill);
  background: var(--tq-sky-fill); color: var(--tq-navy);
}

/* ---- الرسائل ---- */
.tqp-alert {
  display: flex; align-items: flex-start; gap: var(--tq-space-s);
  padding: var(--tq-space-m) var(--tq-space-l);
  border-radius: var(--tq-radius-small);
  font: var(--tq-type-caption);
  margin-block-end: var(--tq-space-l);
}
.tqp-alert svg { flex: none; margin-block-start: 2px; }
.tqp-alert--error { background: var(--tq-dangerSoft); color: var(--tq-danger); }
.tqp-alert--ok    { background: var(--tq-tealSoft);   color: var(--tq-teal); }

/* ---- المصادقة ---- */
.tqp-auth { display: grid; grid-template-columns: 1fr 480px; gap: var(--tq-space-h2); align-items: center; padding-block: var(--tq-space-h3); }
.tqp-auth__card { background: var(--tq-surface); border-radius: var(--tq-radius-soft); box-shadow: var(--tq-shadow-card); padding: var(--tq-space-h1); }
.tqp-auth__card h1 { font: var(--tq-type-h1); margin-block-end: var(--tq-space-s); }
.tqp-auth__sub { color: var(--tq-text2); margin-block-end: var(--tq-space-xl); }
.tqp-auth__alt { margin-block-start: var(--tq-space-xl); padding-block-start: var(--tq-space-l); border-block-start: 1px solid var(--tq-line); text-align: center; color: var(--tq-text2); font: var(--tq-type-caption); }
.tqp-auth__points { display: grid; gap: var(--tq-space-l); }
.tqp-auth__point { display: flex; align-items: flex-start; gap: var(--tq-space-m); }
.tqp-auth__point svg { flex: none; color: var(--tq-teal); margin-block-start: 2px; }
.tqp-auth__row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--tq-space-m); }
.tqp-field-link { display: block; margin-block-start: var(--tq-space-s); font: var(--tq-type-micro); text-align: end; }
.tqp-check { display: flex; align-items: flex-start; gap: var(--tq-space-s); margin-block-end: var(--tq-space-l); }
.tqp-check input { inline-size: 18px; block-size: 18px; margin-block-start: 3px; flex: none; accent-color: var(--tq-navy); }
.tqp-check label { font: var(--tq-type-caption); color: var(--tq-text2); }
@media (max-width: 1023.98px) {
  .tqp-auth { grid-template-columns: minmax(0, 1fr); }
  .tqp-auth__aside { display: none; }
}
@media (max-width: 639.98px) {
  .tqp-auth__row { grid-template-columns: minmax(0, 1fr); }
  .tqp-auth__card { padding: var(--tq-space-xl); }
}

/* ---- النصّ المُدار من لوحة التحكّم ---- */
.tqp-prose { max-inline-size: 78ch; color: var(--tq-text); }
.tqp-prose h1, .tqp-prose h2 { font: var(--tq-type-h1); color: var(--tq-navy); margin-block: var(--tq-space-h1) var(--tq-space-m); }
.tqp-prose h3, .tqp-prose h4 { font: var(--tq-type-h2); color: var(--tq-navy); margin-block: var(--tq-space-xl) var(--tq-space-s); }
.tqp-prose > :first-child { margin-block-start: 0; }
.tqp-prose p, .tqp-prose li { color: var(--tq-text2); }
.tqp-prose ul, .tqp-prose ol { list-style: revert; padding-inline-start: 1.4em; margin-block-end: var(--tq-space-l); }
.tqp-prose li { margin-block-end: var(--tq-space-xs); }
.tqp-prose img { border-radius: var(--tq-radius-medium); margin-block: var(--tq-space-l); }
.tqp-prose table { inline-size: 100%; border-collapse: collapse; margin-block: var(--tq-space-l); }
.tqp-prose th, .tqp-prose td { padding: var(--tq-space-m); border: 1px solid var(--tq-line); text-align: start; }

/* ---- الأسئلة الشائعة ---- */
.tqp-faq {
  background: var(--tq-surface); border: 1px solid var(--tq-line);
  border-radius: var(--tq-radius-medium);
  padding: var(--tq-space-l) var(--tq-space-xl);
  margin-block-end: var(--tq-space-m);
}
.tqp-faq summary {
  display: flex; align-items: center; justify-content: space-between; gap: var(--tq-space-m);
  cursor: pointer; list-style: none;
  font: var(--tq-type-bodyStrong); color: var(--tq-navy);
}
.tqp-faq summary::-webkit-details-marker { display: none; }
.tqp-faq__mark { flex: none; color: var(--tq-text2); transition: transform var(--tq-motion-hover) ease; }
.tqp-faq[open] .tqp-faq__mark { transform: rotate(45deg); }
.tqp-faq__body { margin-block-start: var(--tq-space-m); color: var(--tq-text2); }

/* ---- الباقات ---- */
.tqp-plans { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: var(--tq-space-l); align-items: stretch; }
.tqp-plan {
  display: flex; flex-direction: column; gap: var(--tq-space-m);
  background: var(--tq-surface); border: 1px solid var(--tq-line);
  border-radius: var(--tq-radius-card); padding: var(--tq-space-xl); block-size: 100%;
}
.tqp-plan--pick { border-color: var(--tq-navy); box-shadow: var(--tq-shadow-card); }
.tqp-plan__name { font: var(--tq-type-h2); color: var(--tq-navy); margin: 0; }
.tqp-plan__price { display: flex; align-items: baseline; gap: var(--tq-space-s); flex-wrap: wrap; }
.tqp-plan__price .tq-money { font: var(--tq-type-numeralXl); }
.tqp-plan__cycle { color: var(--tq-text2); font: var(--tq-type-caption); }
.tqp-plan__list { display: grid; gap: var(--tq-space-s); margin-block: var(--tq-space-s); }
.tqp-plan__list li { display: flex; align-items: flex-start; gap: var(--tq-space-s); color: var(--tq-text2); font: var(--tq-type-caption); }
.tqp-plan__list svg { flex: none; color: var(--tq-teal); margin-block-start: 2px; }
.tqp-plan__cta { margin-block-start: auto; }
@media (max-width: 1023.98px) { .tqp-plans { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 639.98px)  { .tqp-plans { grid-template-columns: minmax(0, 1fr); } }

/* ---- صفحة الدورة ---- */
.tqp-coursehead { padding-block: var(--tq-space-h2); background: linear-gradient(160deg, var(--tq-sky-fill), transparent 70%); }
.tqp-coursehead h1 { margin-block-end: var(--tq-space-m); }
.tqp-coursehead__facts { display: flex; align-items: center; flex-wrap: wrap; gap: var(--tq-space-l); color: var(--tq-text2); font: var(--tq-type-caption); }
.tqp-coursehead__facts > span { display: inline-flex; align-items: center; gap: var(--tq-space-xs); }
.tqp-buycard { position: sticky; inset-block-start: 88px; }
.tqp-buycard__media { aspect-ratio: 16 / 9; border-radius: var(--tq-radius-medium); overflow: hidden; background: var(--tq-sky-fill); margin-block-end: var(--tq-space-l); }
.tqp-buycard__media img { inline-size: 100%; block-size: 100%; object-fit: cover; }
.tqp-buycard__actions { display: grid; gap: var(--tq-space-s); margin-block: var(--tq-space-l); }
.tqp-includes { display: grid; gap: var(--tq-space-s); }
.tqp-includes li { display: flex; align-items: center; gap: var(--tq-space-s); color: var(--tq-text2); font: var(--tq-type-caption); }
.tqp-includes svg { flex: none; color: var(--tq-text2); }

.tqp-section { border: 1px solid var(--tq-line); border-radius: var(--tq-radius-medium); margin-block-end: var(--tq-space-m); background: var(--tq-surface); }
.tqp-section > summary {
  display: flex; align-items: center; justify-content: space-between; gap: var(--tq-space-m);
  padding: var(--tq-space-l); cursor: pointer; list-style: none;
  font: var(--tq-type-bodyStrong); color: var(--tq-navy);
}
.tqp-section > summary::-webkit-details-marker { display: none; }
.tqp-section__count { color: var(--tq-text2); font: var(--tq-type-caption); margin-inline-start: auto; }
.tqp-lesson {
  display: flex; align-items: center; gap: var(--tq-space-m);
  padding: var(--tq-space-m) var(--tq-space-l);
  border-block-start: 1px solid var(--tq-line);
  color: var(--tq-text2); font: var(--tq-type-caption);
}
.tqp-lesson__title { color: var(--tq-text); }
.tqp-lesson__time  { margin-inline-start: auto; }

.tqp-teacher { display: flex; gap: var(--tq-space-l); align-items: flex-start; }
.tqp-review { display: flex; gap: var(--tq-space-l); padding-block: var(--tq-space-l); border-block-end: 1px solid var(--tq-line); }
.tqp-review:last-child { border-block-end: 0; }

/* ---- المدوّنة ---- */
.tqp-post { display: flex; flex-direction: column; background: var(--tq-surface); border-radius: var(--tq-radius-card); box-shadow: var(--tq-shadow-soft); overflow: hidden; block-size: 100%; color: var(--tq-text); }
.tqp-post:hover { box-shadow: var(--tq-shadow-card); text-decoration: none; }
.tqp-post__media { aspect-ratio: 16 / 9; background: var(--tq-lilac-fill); overflow: hidden; }
.tqp-post__media img { inline-size: 100%; block-size: 100%; object-fit: cover; }
.tqp-post__fallback { display: grid; place-items: center; block-size: 100%; background: linear-gradient(135deg, var(--tq-lilac-fill), var(--tq-rose-fill)); color: var(--tq-navy); }
.tqp-post__body { display: flex; flex-direction: column; gap: var(--tq-space-s); padding: var(--tq-space-l); flex: 1; }
.tqp-post__title { font: var(--tq-type-h2); color: var(--tq-navy); }
.tqp-post__meta { display: flex; align-items: center; gap: var(--tq-space-s); color: var(--tq-text2); font: var(--tq-type-caption); margin-block-start: auto; padding-block-start: var(--tq-space-m); }

.tqp-tags { display: flex; flex-wrap: wrap; gap: var(--tq-space-s); margin-block: var(--tq-space-l); }

/* ---- الترقيم ---- */
.tqp-pager { display: flex; flex-wrap: wrap; justify-content: center; gap: var(--tq-space-s); margin-block-start: var(--tq-space-h1); }
.tqp-pager a, .tqp-pager strong {
  display: inline-grid; place-items: center;
  min-inline-size: 42px; min-block-size: 42px; padding-inline: var(--tq-space-m);
  border-radius: var(--tq-radius-small);
  background: var(--tq-surface); border: 1px solid var(--tq-line);
  color: var(--tq-navy); font: var(--tq-type-caption); text-decoration: none;
}
.tqp-pager a:hover { background: var(--tq-navyWash); text-decoration: none; }
.tqp-pager strong { background: var(--tq-actionPrimary); border-color: var(--tq-actionPrimary); color: var(--tq-onAction); }

/* ---- المرشّحات ---- */
.tqp-filters { display: grid; gap: var(--tq-space-l); }
.tqp-filters fieldset { border: 0; padding: 0; margin: 0; }
.tqp-filters legend { font: var(--tq-type-bodyStrong); color: var(--tq-navy); margin-block-end: var(--tq-space-s); padding: 0; }
.tqp-choice { display: flex; align-items: center; gap: var(--tq-space-s); padding-block: var(--tq-space-xs); }
.tqp-choice input { inline-size: 18px; block-size: 18px; flex: none; accent-color: var(--tq-navy); }
.tqp-choice label { font: var(--tq-type-caption); color: var(--tq-text2); cursor: pointer; }
.tqp-toolbar { display: flex; align-items: center; justify-content: space-between; gap: var(--tq-space-m); flex-wrap: wrap; margin-block-end: var(--tq-space-xl); }

/* ---- تواصل معنا ---- */
.tqp-contact { display: grid; grid-template-columns: 1fr 420px; gap: var(--tq-space-h2); align-items: start; }
@media (max-width: 1023.98px) { .tqp-contact { grid-template-columns: minmax(0, 1fr); } }
.tqp-contact__item { display: flex; align-items: flex-start; gap: var(--tq-space-m); margin-block-end: var(--tq-space-l); }
.tqp-contact__item svg { flex: none; color: var(--tq-teal); margin-block-start: 2px; }
.tqp-contact__item h3 { font: var(--tq-type-bodyStrong); color: var(--tq-navy); margin: 0 0 2px; }
.tqp-contact__item p { color: var(--tq-text2); font: var(--tq-type-caption); margin: 0; }

/* ---- صفحة 404 ---- */
.tqp-404 { display: grid; place-items: center; gap: var(--tq-space-l); text-align: center; padding-block: var(--tq-space-h3); }
.tqp-404__code { font: var(--tq-type-numeralXl); font-size: 92px; line-height: 1; color: var(--tq-navy); }
.tqp-404 ul { display: grid; gap: var(--tq-space-xs); color: var(--tq-text2); font: var(--tq-type-caption); }
</style>
    <?php
}
