<?php
/**
 * صفحة الدورة — المنهج · المعلم · التقييمات · السعر.
 *
 * المتحكم Home::course يمرر $course_id وحده، وبقية البيانات تقرأ من النماذج
 * كما في الثيم القائم — الاستعلامات نفسها بواجهة تقدر.
 *
 * برامج الفعل عقد مع المتحكم ولا تغير:
 * · الدورة المجانية → home/get_enrolled_to_free_course/{id}
 * · الدورة المدفوعة  → home/handle_buy_now/{id}  (يعيد JSON فيه redirectTo)
 * · المشترك فيها     → home/lesson/{slug}/{id}
 */
include_once 'public_parts.php';

$course_details = $this->crud_model->get_course_by_id($course_id)->row_array();

if (empty($course_details)) {
    tqp_empty('search', 'هذه الدورة غير متاحة', 'قد تكون أخفيت أو حذفت. تصفح بقية الدورات المنشورة.', 'كل الدورات', base_url('plans'));
    return;
}

$instructor  = $this->user_model->get_all_user($course_details['creator'])->row_array();
$sections    = $this->crud_model->get_section('course', $course_details['id'])->result_array();
$lesson_rows = $this->crud_model->get_lessons('course', $course_details['id'])->result_array();
$duration    = tqp_duration($course_details['id']);
$students    = $this->crud_model->enrol_history($course_details['id'])->num_rows();

$rating_sum   = (float) $this->crud_model->get_ratings('course', $course_details['id'], true)->row()->rating;
$ratings      = $this->crud_model->get_ratings('course', $course_details['id'])->result_array();
$rating_count = count($ratings);
$rating_avg   = $rating_count > 0 ? $rating_sum / $rating_count : 0;

$thumb     = tqp_thumb($course_details['id']);
$purchased = is_purchased($course_details['id']);
$outcomes  = json_decode($course_details['outcomes'] ?? '[]', true);
$requires  = json_decode($course_details['requirements'] ?? '[]', true);
$faqs      = json_decode($course_details['faqs'] ?? '[]', true);
$outcomes  = is_array($outcomes) ? array_filter($outcomes) : [];
$requires  = is_array($requires) ? array_filter($requires) : [];
$faqs      = is_array($faqs) ? $faqs : [];
?>

<section class="tqp-coursehead">
    <div class="tq-container">
        <nav class="tqp-crumbs" aria-label="برنامج التصفح">
            <a href="<?php echo base_url(); ?>">الرئيسية</a>
            <span class="tqp-crumbs__sep" aria-hidden="true">/</span>
            <a href="<?php echo base_url('plans'); ?>">الدورات</a>
            <span class="tqp-crumbs__sep" aria-hidden="true">/</span>
            <span aria-current="page"><?php echo html_escape($course_details['title']); ?></span>
        </nav>

        <div class="tq-cols">
            <div>
                <div class="tqp-course__badges" style="margin-block-end:var(--tq-space-m)">
                    <?php if ($purchased): ?>
                        <?php echo tq_badge('mastered', 'أنت مشترك في هذه الدورة'); ?>
                    <?php elseif (!empty($course_details['is_free_course'])): ?>
                        <?php echo tq_badge('progress', 'مجاني'); ?>
                    <?php endif; ?>
                    <?php if (tqp_level($course_details['level']) !== ''): ?>
                        <span class="tq-micro tqp-course__level"><?php echo tqp_level($course_details['level']); ?></span>
                    <?php endif; ?>
                </div>

                <h1><?php echo html_escape($course_details['title']); ?></h1>

                <?php if (!empty($course_details['short_description'])): ?>
                    <p class="tqp-hero__lead"><?php echo html_escape($course_details['short_description']); ?></p>
                <?php endif; ?>

                <div class="tqp-coursehead__facts">
                    <span><?php echo tqp_stars($rating_avg, $rating_count); ?></span>
                    <span>
                        <?php echo tq_icon('users', 16); ?>
                        <?php echo tqp_plural($students, 'طالب واحد', 'طالبان', 'طلاب', 'طالبا'); ?>
                    </span>
                    <?php if ($duration !== ''): ?>
                        <span>
                            <?php echo tq_icon('clock', 16); ?>
                            <span class="tq-sr">المدة الكلية</span>
                            <?php echo tq_num($duration, 'tq-num--sm'); ?>
                        </span>
                    <?php endif; ?>
                    <span>
                        <?php echo tq_icon('play', 16); ?>
                        <?php echo tqp_plural(count($lesson_rows), 'درس واحد', 'درسان', 'دروس', 'درسا'); ?>
                    </span>
                </div>

                <?php if (!empty($instructor)): ?>
                    <p class="tq-caption" style="margin-block-start:var(--tq-space-l)">
                        إعداد وتقديم
                        <a href="<?php echo base_url('home/instructor_page/' . (int) $instructor['id']); ?>">
                            <?php echo html_escape($instructor['first_name'] . ' ' . $instructor['last_name']); ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>
            <div></div>
        </div>
    </div>
</section>

<section class="tqp-band">
    <div class="tq-container">
        <?php tqp_flash(); ?>

        <div class="tq-cols">
            <div>
                <div class="tq-tabs" role="tablist" data-tq-tabs aria-label="تفاصيل الدورة">
                    <button class="tq-tab" role="tab" id="tqp-tab-plan" aria-controls="tqp-panel-plan" aria-selected="true" type="button">المنهج</button>
                    <button class="tq-tab" role="tab" id="tqp-tab-about" aria-controls="tqp-panel-about" aria-selected="false" tabindex="-1" type="button">الوصف</button>
                    <button class="tq-tab" role="tab" id="tqp-tab-teacher" aria-controls="tqp-panel-teacher" aria-selected="false" tabindex="-1" type="button">المعلم</button>
                    <button class="tq-tab" role="tab" id="tqp-tab-reviews" aria-controls="tqp-panel-reviews" aria-selected="false" tabindex="-1" type="button">التقييمات</button>
                </div>

                <!-- المنهج -->
                <div id="tqp-panel-plan" role="tabpanel" aria-labelledby="tqp-tab-plan">
                    <?php if (count($sections) > 0): ?>
                        <?php foreach ($sections as $index => $section): ?>
                            <?php
                            $section_lessons = $this->crud_model->get_lessons('section', $section['id'])->result_array();
                            ?>
                            <details class="tqp-section" <?php echo $index === 0 ? 'open' : ''; ?>>
                                <summary>
                                    <span><?php echo html_escape($section['title']); ?></span>
                                    <span class="tqp-section__count">
                                        <?php echo tqp_plural(count($section_lessons), 'درس واحد', 'درسان', 'دروس', 'درسا'); ?>
                                    </span>
                                </summary>
                                <?php foreach ($section_lessons as $lesson): ?>
                                    <div class="tqp-lesson">
                                        <span aria-hidden="true"><?php echo tq_icon($lesson['lesson_type'] === 'quiz' ? 'clipboard' : 'play', 16); ?></span>
                                        <span class="tqp-lesson__title"><?php echo html_escape($lesson['title']); ?></span>
                                        <?php if (!empty($lesson['is_free'])): ?>
                                            <?php echo tq_badge('progress', 'درس تجريبي'); ?>
                                        <?php endif; ?>
                                        <?php if (!empty($lesson['duration']) && $lesson['duration'] !== '00:00:00'): ?>
                                            <span class="tqp-lesson__time">
                                                <span class="tq-sr">مدة الدرس</span>
                                                <?php echo tq_num($lesson['duration'], 'tq-num--sm'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (count($section_lessons) === 0): ?>
                                    <div class="tqp-lesson"><span class="tq-caption">لم تضف دروس إلى هذا القسم بعد.</span></div>
                                <?php endif; ?>
                            </details>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php tqp_empty('clipboard', 'المنهج قيد الإعداد', 'ينشر المنهج مقسما إلى وحدات ودروس فور اكتماله.', '', ''); ?>
                    <?php endif; ?>
                </div>

                <!-- الوصف -->
                <div id="tqp-panel-about" role="tabpanel" aria-labelledby="tqp-tab-about" hidden>
                    <?php if (count($outcomes) > 0): ?>
                        <h2 class="tq-h2">ماذا ستتعلم</h2>
                        <ul class="tqp-plan__list" style="margin-block-end:var(--tq-space-xl)">
                            <?php foreach ($outcomes as $item): ?>
                                <li><?php echo tq_icon('check', 18); ?><span><?php echo html_escape($item); ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (count($requires) > 0): ?>
                        <h2 class="tq-h2">المتطلبات</h2>
                        <ul class="tqp-plan__list" style="margin-block-end:var(--tq-space-xl)">
                            <?php foreach ($requires as $item): ?>
                                <li><?php echo tq_icon('target', 18); ?><span><?php echo html_escape($item); ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (!empty($course_details['description'])): ?>
                        <div class="tqp-prose"><?php echo htmlspecialchars_decode_($course_details['description']); ?></div>
                    <?php endif; ?>

                    <?php if (count($faqs) > 0): ?>
                        <h2 class="tq-h2" style="margin-block-start:var(--tq-space-h1)">أسئلة عن الدورة</h2>
                        <?php foreach ($faqs as $faq): ?>
                            <?php if (empty($faq['title'])) continue; ?>
                            <details class="tqp-faq">
                                <summary>
                                    <span><?php echo html_escape($faq['title']); ?></span>
                                    <span class="tqp-faq__mark" aria-hidden="true"><?php echo tq_icon('plus', 18); ?></span>
                                </summary>
                                <p class="tqp-faq__body"><?php echo nl2br(html_escape($faq['description'] ?? '')); ?></p>
                            </details>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (count($outcomes) === 0 && count($requires) === 0 && empty($course_details['description'])): ?>
                        <?php tqp_empty('file', 'لا وصف منشور لهذه الدورة', 'يظهر هنا وصف الدورة ومخرجاتها ومتطلباتها فور كتابتها.', '', ''); ?>
                    <?php endif; ?>
                </div>

                <!-- المعلم -->
                <div id="tqp-panel-teacher" role="tabpanel" aria-labelledby="tqp-tab-teacher" hidden>
                    <?php if (!empty($instructor)): ?>
                        <div class="tq-card tq-card--panel tqp-teacher">
                            <img class="tq-avatar tq-avatar--lg"
                                 src="<?php echo $this->user_model->get_user_image_url($instructor['id']); ?>"
                                 alt="صورة <?php echo html_escape($instructor['first_name'] . ' ' . $instructor['last_name']); ?>"
                                 loading="lazy">
                            <div>
                                <h2 class="tq-h2" style="margin-block-end:var(--tq-space-xs)">
                                    <?php echo html_escape($instructor['first_name'] . ' ' . $instructor['last_name']); ?>
                                </h2>
                                <?php if (!empty($instructor['title'])): ?>
                                    <p class="tq-caption"><?php echo html_escape($instructor['title']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($instructor['biography'])): ?>
                                    <div class="tqp-prose"><?php echo htmlspecialchars_decode_($instructor['biography']); ?></div>
                                <?php else: ?>
                                    <p class="tq-caption">لم تكتب نبذة عن المعلم بعد.</p>
                                <?php endif; ?>
                                <a class="tq-btn tq-btn--secondary tq-btn--sm"
                                   href="<?php echo base_url('home/instructor_page/' . (int) $instructor['id']); ?>">صفحة المعلم</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php tqp_empty('users', 'لم يربط معلم بهذه الدورة', 'يظهر هنا اسم المعلم ونبذته فور إسناد الدورة إليه.', '', ''); ?>
                    <?php endif; ?>
                </div>

                <!-- التقييمات -->
                <div id="tqp-panel-reviews" role="tabpanel" aria-labelledby="tqp-tab-reviews" hidden>
                    <?php if ($rating_count > 0): ?>
                        <div class="tq-card tq-card--panel" style="margin-block-end:var(--tq-space-xl)">
                            <div class="tq-row" style="gap:var(--tq-space-l)">
                                <?php echo tq_num(number_format($rating_avg, 1), 'tq-num--xl'); ?>
                                <div>
                                    <?php echo tqp_stars($rating_avg, $rating_count); ?>
                                </div>
                            </div>
                            <div style="margin-block-start:var(--tq-space-l);display:grid;gap:var(--tq-space-s)">
                                <?php for ($star = 5; $star >= 1; $star--): ?>
                                    <?php $pct = $this->crud_model->get_percentage_of_specific_rating($star, 'course', $course_details['id']); ?>
                                    <div class="tq-row" style="gap:var(--tq-space-m)">
                                        <span class="tq-caption" style="min-inline-size:64px">
                                            <?php echo tq_iso($star . ' نجوم'); ?>
                                        </span>
                                        <?php echo tq_progress($pct, 'نسبة من قيموا بـ ' . $star . ' من 5'); ?>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>

                        <?php foreach ($ratings as $rating): ?>
                            <?php $author = $this->user_model->get_all_user($rating['user_id'])->row_array(); ?>
                            <article class="tqp-review">
                                <img class="tq-avatar"
                                     src="<?php echo $this->user_model->get_user_image_url($rating['user_id']); ?>"
                                     alt="<?php echo !empty($author) ? 'صورة ' . html_escape($author['first_name'] . ' ' . $author['last_name']) : 'صورة الطالب'; ?>"
                                     loading="lazy">
                                <div>
                                    <p class="tqp-quote__name" style="margin:0">
                                        <?php echo !empty($author) ? html_escape($author['first_name'] . ' ' . $author['last_name']) : 'طالب في تقدر'; ?>
                                    </p>
                                    <div style="margin-block:var(--tq-space-xs)">
                                        <?php echo tqp_stars((float) $rating['rating'], 1, false); ?>
                                    </div>
                                    <?php if (!empty($rating['review'])): ?>
                                        <p style="margin:0"><?php echo html_escape($rating['review']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <?php tqp_empty('star', 'لا تقييمات بعد', 'التقييم متاح للمشتركين في الدورة، ويظهر هنا فور كتابته.', '', ''); ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- السعر والفعل -->
            <aside class="tq-aside">
                <div class="tq-card tq-card--panel tqp-buycard">
                    <div class="tqp-buycard__media">
                        <?php if ($thumb !== ''): ?>
                            <img src="<?php echo $thumb; ?>" alt="غلاف دورة <?php echo html_escape($course_details['title']); ?>">
                        <?php else: ?>
                            <span class="tqp-course__fallback" aria-hidden="true"><?php echo tq_icon('book', 36); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="tqp-price">
                        <?php if (!empty($course_details['is_free_course'])): ?>
                            <span class="tq-money">مجاني</span>
                        <?php elseif (!empty($course_details['discount_flag'])): ?>
                            <?php echo tq_sar($course_details['discounted_price']); ?>
                            <del class="tqp-was"><?php echo tq_sar($course_details['price']); ?></del>
                        <?php else: ?>
                            <?php echo tq_sar($course_details['price']); ?>
                        <?php endif; ?>
                    </div>
                    <p class="tq-caption" style="margin:var(--tq-space-xs) 0 0">
                        السعر شامل ما سيدفع فعلا، ولا رسوم تضاف في خطوة الدفع.
                    </p>

                    <div class="tqp-buycard__actions">
                        <?php if ($purchased): ?>
                            <a class="tq-btn tq-btn--mastery tq-btn--block"
                               href="<?php echo site_url('home/lesson/' . rawurlencode(slugify($course_details['title'])) . '/' . (int) $course_details['id']); ?>">
                                <?php echo tq_icon('play', 18); ?> تابع الدراسة
                            </a>
                        <?php elseif (!empty($course_details['is_free_course'])): ?>
                            <a class="tq-btn tq-btn--primary tq-btn--block"
                               href="<?php echo site_url('home/get_enrolled_to_free_course/' . (int) $course_details['id']); ?>">
                                ابدأ الدورة مجانا
                            </a>
                        <?php else: ?>
                            <button class="tq-btn tq-btn--primary tq-btn--block" type="button"
                                    data-tqp-buy="<?php echo site_url('home/handle_buy_now/' . (int) $course_details['id']); ?>">
                                اشتر الدورة
                            </button>
                            <a class="tq-btn tq-btn--secondary tq-btn--block" href="<?php echo base_url('plans'); ?>">
                                أو اشترك في باقة
                            </a>
                        <?php endif; ?>

                        <?php if ($this->session->userdata('user_id')): ?>
                            <a class="tq-btn tq-btn--ghost tq-btn--block"
                               href="<?php echo site_url('home/toggleWishlistItems/' . (int) $course_details['id']); ?>">
                                <?php echo tq_icon('heart', 18); ?> أضف إلى المفضلة
                            </a>
                        <?php endif; ?>
                    </div>

                    <ul class="tqp-includes">
                        <li>
                            <?php echo tq_icon('play', 16); ?>
                            <?php echo tqp_plural(count($lesson_rows), 'درس واحد', 'درسان', 'دروس', 'درسا'); ?>
                        </li>
                        <?php if ($duration !== ''): ?>
                            <li>
                                <?php echo tq_icon('clock', 16); ?>
                                <span class="tq-sr">المدة الكلية</span>
                                <?php echo tq_num($duration, 'tq-num--sm'); ?>
                            </li>
                        <?php endif; ?>
                        <li>
                            <?php echo tq_icon('lock', 16); ?>
                            <?php if ((int) $course_details['expiry_period'] > 0): ?>
                                <?php echo tq_iso('وصول لمدة ' . (int) $course_details['expiry_period'] . ' شهرا'); ?>
                            <?php else: ?>
                                وصول دائم بلا انتهاء
                            <?php endif; ?>
                        </li>
                        <li><?php echo tq_icon('check-badge', 16); ?> شهادة إتمام عند اجتياز الاختبارات</li>
                    </ul>
                </div>
            </aside>
        </div>
    </div>
</section>

<script>
/* «اشتر الدورة» يخاطب المتحكم القائم الذي يعيد JSON فيه redirectTo،
   فنتبع ما يقوله الخادم ولا نخمن الوجهة في الواجهة. */
(function () {
  var btn = document.querySelector('[data-tqp-buy]');
  if (!btn) return;
  btn.addEventListener('click', function () {
    btn.setAttribute('data-loading', 'true');
    fetch(btn.getAttribute('data-tqp-buy'), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.redirectTo) { window.location.href = d.redirectTo; return; }
        btn.removeAttribute('data-loading');
      })
      .catch(function () { btn.removeAttribute('data-loading'); });
  });
})();
</script>
