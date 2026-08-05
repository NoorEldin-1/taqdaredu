<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * مشغّل الدرس — وهذه هي الشاشة التي يقضي فيها الطالب وقته كلّه.
 *
 * كل بيانات هذه الشاشة تأتي من `taqdar_gate` لا من استعلام مباشر، لأن القفل
 * وقرار البوّابة **يُحسمان في الخادم**. والصفحة لا تعرف الإجابات الصحيحة أصلًا:
 * الأسئلة تصل بلا مفاتيح حلّ، والتصحيح يعود من الخادم — فلا يمكن الغشّ بقراءة
 * مصدر الصفحة، ولا بتعديل جافاسكربت.
 *
 * وتسلسل الشاشة يتبع المخطط: فيديو ← «هل فهمت؟» ← خمسة أسئلة ← قرار البوّابة.
 * وعند الرسوب يتصاعد الدعم ولا يُفتح الطريق ولا يُغلق:
 *   المحاولة ١ ⟵ «راجع الدقيقة» وتُفتح عند ثانية المفهوم الأضعف، بلا إعطاء الإجابة
 *   المحاولة ٢ ⟵ شرح بديل للمفهوم نفسه
 *   المحاولة ٣ ⟵ يُمرَّر المفهوم المتعثّر إلى المعلّم، والدرس التالي يبقى مقفلًا
 */
$tq_nav   = 'lessons';
$tq_role  = 'student';
$tq_title = '';   // العنوان يأتي من الدرس نفسه بعد تحميله

$tq_course_id = isset($course_id) ? (int) $course_id : 0;
$tq_lesson_id = isset($lesson_id) ? (int) $lesson_id : 0;

include 'portal_open.php';
?>

<div class="tq-lesson" data-tq-lesson="<?php echo $tq_lesson_id; ?>"
     data-tq-course="<?php echo $tq_course_id; ?>"
     data-tq-gate="<?php echo base_url('taqdar_gate'); ?>">

    <!-- حالة التحميل: هيكل عظمي بشكل المحتوى القادم، لا دوّار وسط الشاشة -->
    <div class="tq-cols" data-tq-lesson-skeleton>
        <div class="tq-stack">
            <div class="tq-skeleton tq-skeleton--card" style="block-size:420px"></div>
            <div class="tq-skeleton tq-skeleton--title"></div>
            <div class="tq-skeleton" style="inline-size:80%"></div>
        </div>
        <aside class="tq-aside">
            <div class="tq-skeleton tq-skeleton--card" style="block-size:260px"></div>
        </aside>
    </div>

    <!-- الدرس مقفل: رسالة تقول ما المطلوب لفتحه، لا «ممنوع» صمّاء -->
    <div class="tq-card" data-tq-lesson-locked hidden>
        <div class="tq-empty">
            <span class="tq-icon-box tq-pastel--peach" style="inline-size:72px;block-size:72px" aria-hidden="true">
                <?php echo tq_icon('lock', 34); ?>
            </span>
            <p class="tq-empty__title">هذا الدرس مقفل</p>
            <p class="tq-empty__text" data-tq-locked-msg>أكمل مراجعة الدرس السابق أولًا.</p>
            <a class="tq-btn tq-btn--primary" data-tq-locked-back href="<?php echo base_url('student/lessons'); ?>">
                عد إلى دروسك
            </a>
        </div>
    </div>

    <!-- الخطأ: ما حدث + زرّ إعادة، والتفصيل في السجلّ لا على الشاشة -->
    <div class="tq-card" data-tq-lesson-error hidden>
        <div class="tq-empty">
            <p class="tq-empty__title" data-tq-error-msg>تعذّر تحميل الدرس</p>
            <button class="tq-btn tq-btn--secondary" type="button" data-tq-retry>إعادة المحاولة</button>
        </div>
    </div>

    <div class="tq-cols" data-tq-lesson-body hidden>
        <div class="tq-stack">

            <article class="tq-card tq-card--panel" style="padding:0;overflow:hidden">
                <div class="tq-player" data-tq-player>
                    <div class="tq-player__frame" data-tq-player-frame></div>
                </div>

                <div style="padding:var(--tq-space-xl)">
                    <span class="tq-eyebrow" data-tq-lesson-course></span>
                    <h1 class="tq-h1" style="margin:var(--tq-space-xs) 0 var(--tq-space-m)" data-tq-lesson-title></h1>

                    <div class="tq-row tq-row--between" style="flex-wrap:wrap;gap:var(--tq-space-m)">
                        <div class="tq-row" style="gap:var(--tq-space-l)">
                            <span class="tq-caption tq-row" style="gap:var(--tq-space-xs)">
                                <?php echo tq_icon('clock', 16); ?>
                                <span data-tq-lesson-duration></span>
                            </span>
                            <span data-tq-lesson-badge></span>
                        </div>
                        <div style="min-inline-size:220px" data-tq-lesson-progress></div>
                    </div>

                    <p class="tq-body" style="margin-block-start:var(--tq-space-l)" data-tq-lesson-summary></p>
                </div>
            </article>

            <!-- «هل فهمت؟» — لا تخطّي: البوّابة هي المنتج -->
            <section class="tq-card" data-tq-gate-intro hidden>
                <div class="tq-row" style="gap:var(--tq-space-l);align-items:flex-start">
                    <span class="tq-icon-box tq-pastel--mint" aria-hidden="true"><?php echo tq_icon('check-badge'); ?></span>
                    <div style="flex:1">
                        <h2 class="tq-card__title" style="margin:0">هل فهمت؟</h2>
                        <p class="tq-caption" style="margin:var(--tq-space-xs) 0 var(--tq-space-l)">
                            خمسة أسئلة قصيرة تفتح الدرس التالي. لا وقت محدّد، ولا حدّ للمحاولات.
                        </p>
                        <button class="tq-btn tq-btn--primary" type="button" data-tq-gate-start>
                            ابدأ المراجعة
                        </button>
                    </div>
                </div>
            </section>

            <!-- المراجعة الخماسية -->
            <section class="tq-card" data-tq-gate-quiz hidden>
                <div class="tq-card__head">
                    <h2 class="tq-card__title">مراجعة الدرس</h2>
                    <span class="tq-caption" data-tq-gate-counter></span>
                </div>
                <form data-tq-gate-form>
                    <div data-tq-gate-questions></div>
                    <div class="tq-row" style="margin-block-start:var(--tq-space-xl)">
                        <button class="tq-btn tq-btn--primary" type="submit" data-tq-gate-submit>سلّم الإجابات</button>
                    </div>
                </form>
            </section>

            <!-- مراجعة الإجابات: تُطلب بعد التسليم وحده -->
            <section class="tq-card tq-review" data-tq-gate-review hidden>
                <div class="tq-row" style="justify-content:space-between;align-items:center;flex-wrap:wrap;gap:var(--tq-space-m)">
                    <h2 class="tq-card__title" style="margin:0">مراجعة إجاباتك</h2>
                    <p class="tq-review__score" data-tq-review-score></p>
                </div>
                <ol class="tq-review__list" data-tq-review-list></ol>
                <div class="tq-row" style="flex-wrap:wrap;gap:var(--tq-space-s)">
                    <button class="tq-btn tq-btn--primary" type="button" data-tq-review-again>أعد الاختبار</button>
                    <button class="tq-btn tq-btn--secondary" type="button" data-tq-review-close>إغلاق المراجعة</button>
                </div>
            </section>

            <!-- قرار البوّابة -->
            <section class="tq-card" data-tq-gate-result hidden>
                <div class="tq-row" style="gap:var(--tq-space-l);align-items:flex-start">
                    <span class="tq-icon-box" data-tq-result-icon aria-hidden="true"></span>
                    <div style="flex:1">
                        <h2 class="tq-card__title" style="margin:0" data-tq-result-title></h2>
                        <p class="tq-body" style="margin:var(--tq-space-s) 0 var(--tq-space-l)" data-tq-result-text></p>
                        <div class="tq-row" style="flex-wrap:wrap" data-tq-result-actions></div>
                    </div>
                </div>
            </section>
        </div>

        <aside class="tq-aside">
            <div class="tq-card">
                <h2 class="tq-card__title">أهداف هذا الدرس</h2>
                <p class="tq-caption">ما ستعرفه بعد هذا الدرس — وكل سؤال في المراجعة مربوط بأحدها.</p>
                <ol class="tq-s-list" data-tq-objectives></ol>
            </div>

            <div class="tq-card" data-tq-attachments-card hidden>
                <h2 class="tq-card__title">مرفقات الدرس</h2>
                <div data-tq-attachments></div>
            </div>

            <div class="tq-card">
                <h2 class="tq-card__title">التنقّل</h2>
                <div class="tq-stack" style="--tq-space-l:var(--tq-space-s)">
                    <a class="tq-btn tq-btn--secondary tq-btn--block" data-tq-prev hidden>الدرس السابق</a>
                    <a class="tq-btn tq-btn--primary tq-btn--block" data-tq-next hidden>الدرس التالي</a>
                    <span class="tq-caption tq-row" data-tq-next-locked hidden style="gap:var(--tq-space-xs)">
                        <?php echo tq_icon('lock', 16); ?> الدرس التالي يُفتح بعد اجتياز المراجعة
                    </span>
                    <a class="tq-btn tq-btn--ghost tq-btn--block" href="<?php echo base_url('student/lessons'); ?>">كل دروسي</a>
                </div>
            </div>
        </aside>
    </div>
</div>

<?php include 'portal_close.php'; ?>
