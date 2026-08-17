<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * مشغل الدرس — وهذه هي الشاشة التي يقضي فيها الطالب وقته كله.
 *
 * كل بيانات هذه الشاشة تأتي من `taqdar_gate` لا من استعلام مباشر، لأن القفل
 * وقرار البوابة **يحسمان في الخادم**. والصفحة لا تعرف الإجابات الصحيحة أصلا:
 * الأسئلة تصل بلا مفاتيح حل، والتصحيح يعود من الخادم — فلا يمكن الغش بقراءة
 * مصدر الصفحة، ولا بتعديل جافاسكربت.
 *
 * وتسلسل الشاشة يتبع المخطط: فيديو ← «هل فهمت؟» ← خمسة أسئلة ← قرار البوابة.
 * وعند الرسوب يتصاعد الدعم ولا يفتح الطريق ولا يغلق:
 *   المحاولة ١ ⟵ «راجع الدقيقة» وتفتح عند ثانية المفهوم الأضعف، بلا إعطاء الإجابة
 *   المحاولة ٢ ⟵ شرح بديل للمفهوم نفسه
 *   المحاولة ٣ ⟵ يمرر المفهوم المتعثر إلى المعلم، والدرس التالي يبقى مقفلا
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

    <!-- حالة التحميل: هيكل عظمي بشكل المحتوى القادم، لا دوار وسط الشاشة -->
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

    <!-- الدرس مقفل: رسالة تقول ما المطلوب لفتحه، لا «ممنوع» صماء -->
    <div class="tq-card" data-tq-lesson-locked hidden>
        <div class="tq-empty">
            <span class="tq-icon-box tq-pastel--peach" style="inline-size:72px;block-size:72px" aria-hidden="true">
                <?php echo tq_icon('lock', 34); ?>
            </span>
            <p class="tq-empty__title">هذا الدرس مقفل</p>
            <p class="tq-empty__text" data-tq-locked-msg>أكمل مراجعة الدرس السابق أولا.</p>
            <a class="tq-btn tq-btn--primary" data-tq-locked-back href="<?php echo base_url('student/lessons'); ?>">
                عد إلى دروسك
            </a>
        </div>
    </div>

    <!-- الخطأ: ما حدث + زر إعادة، والتفصيل في السجل لا على الشاشة -->
    <div class="tq-card" data-tq-lesson-error hidden>
        <div class="tq-empty">
            <p class="tq-empty__title" data-tq-error-msg>تعذر تحميل الدرس</p>
            <button class="tq-btn tq-btn--secondary" type="button" data-tq-retry>إعادة المحاولة</button>
        </div>
    </div>

    <div class="tq-cols" data-tq-lesson-body hidden>
        <div class="tq-stack">

            <article class="tq-card tq-card--panel" style="padding:0;overflow:hidden">
                <div class="tq-player" data-tq-player>
                    <div class="tq-player__frame" data-tq-player-frame></div>
                </div>

                <?php /* ── أدوات المشغل — `F2.1` ──────────────────────────
                        ثلاث: سرعة متغيرة، ونص قابل للبحث، وملاحظات موقوتة.
                        وشرطها في الوثيقة «كل أداة تشتغل قطعيا لا واجهة
                        قاضية» — فما لا مصدر له يخفى ولا يعرض معطلا: النص
                        لا يظهر شريطه إن لم يرفع للدرس نص، والسرعة تخفى
                        على إطار يوتيوب لأن المنصة لا تملك مشغله.

                        والشريط داخل بطاقة المشغل لا في عمود جانبي: الأداة
                        التي تخص الفيديو تسكن بجواره، وإبعادها إلى الحاشية
                        يجعلها لا تستعمل. */ ?>
                <div class="tq-ptools" data-tq-ptools hidden>
                    <div class="tq-ptools__grp" data-tq-speed-grp hidden>
                        <span class="tq-ptools__lbl">السرعة</span>
                        <div class="tq-ptools__rates" role="group" aria-label="سرعة التشغيل">
                            <?php foreach (array('0.75', '1', '1.25', '1.5', '2') as $r): ?>
                                <button class="tq-ptools__rate<?php echo $r === '1' ? ' is-on' : ''; ?>"
                                        type="button" data-tq-rate="<?php echo $r; ?>"
                                        aria-pressed="<?php echo $r === '1' ? 'true' : 'false'; ?>"><?php
                                    echo $r === '1' ? 'عادي' : '×' . $r; ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="tq-ptools__grp tq-ptools__grp--grow" data-tq-tr-grp hidden>
                        <label class="sr-only" for="tqTrSearch">ابحث في نص الدرس</label>
                        <span class="tq-ptools__search">
                            <?php echo tq_icon('search', 16); ?>
                            <input id="tqTrSearch" type="search" data-tq-tr-search
                                   placeholder="ابحث في نص الدرس واقفز إلى موضعه" autocomplete="off">
                        </span>
                        <button class="tq-btn tq-btn--ghost tq-btn--sm" type="button" data-tq-tr-toggle
                                aria-expanded="false">النص</button>
                    </div>

                    <button class="tq-btn tq-btn--ghost tq-btn--sm" type="button" data-tq-note-add>
                        <?php echo tq_icon('pen', 16); ?> ملاحظة هنا
                    </button>
                </div>

                <!-- النص: مقاطع بثوانيها، والضغط يقفز -->
                <div class="tq-transcript" data-tq-transcript hidden>
                    <p class="tq-caption" data-tq-tr-count></p>
                    <ol class="tq-transcript__list" data-tq-tr-list></ol>
                    <p class="tq-caption" data-tq-tr-none hidden>لا نتيجة لهذا البحث في نص الدرس.</p>
                </div>

                <!-- الملاحظة: تفتح عند الثانية التي كان عندها، ولا تسأله عنها -->
                <form class="tq-noteform" data-tq-noteform hidden>
                    <p class="tq-caption" data-tq-note-at></p>
                    <textarea data-tq-note-body rows="3" maxlength="2000"
                              placeholder="ما الذي تريد أن تتذكره من هذه اللحظة؟"></textarea>
                    <div class="tq-row" style="gap:var(--tq-space-s);flex-wrap:wrap">
                        <button class="tq-btn tq-btn--primary tq-btn--sm" type="submit">احفظ</button>
                        <button class="tq-btn tq-btn--ghost tq-btn--sm" type="button" data-tq-note-cancel>ألغ</button>
                    </div>
                </form>

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

            <!-- «هل فهمت؟» — لا تخطي: البوابة هي المنتج -->
            <section class="tq-card" data-tq-gate-intro hidden>
                <div class="tq-row" style="gap:var(--tq-space-l);align-items:flex-start">
                    <span class="tq-icon-box tq-pastel--mint" aria-hidden="true"><?php echo tq_icon('check-badge'); ?></span>
                    <div style="flex:1">
                        <h2 class="tq-card__title" style="margin:0">هل فهمت؟</h2>
                        <p class="tq-caption" style="margin:var(--tq-space-xs) 0 var(--tq-space-l)">
                            خمسة أسئلة قصيرة تفتح الدرس التالي. لا وقت محدد، ولا حد للمحاولات.
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
                        <button class="tq-btn tq-btn--primary" type="submit" data-tq-gate-submit>سلم الإجابات</button>
                    </div>
                </form>
            </section>

            <!-- مراجعة الإجابات: تطلب بعد التسليم وحده -->
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

            <!-- قرار البوابة -->
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

            <?php /* ملاحظاتي — موقوتة، وكل واحدة تعود بصاحبها إلى ثانيتها.
                    وهي في الحاشية لا في المتن: تكتب مرة وتقرأ كثيرا. */ ?>
            <div class="tq-card" data-tq-notes-card hidden>
                <div class="tq-card__head">
                    <h2 class="tq-card__title">ملاحظاتي</h2>
                    <span class="tq-caption" data-tq-notes-count></span>
                </div>
                <ol class="tq-notes" data-tq-notes></ol>
            </div>

            <div class="tq-card">
                <h2 class="tq-card__title">التنقل</h2>
                <div class="tq-stack" style="--tq-space-l:var(--tq-space-s)">
                    <a class="tq-btn tq-btn--secondary tq-btn--block" data-tq-prev hidden>الدرس السابق</a>
                    <a class="tq-btn tq-btn--primary tq-btn--block" data-tq-next hidden>الدرس التالي</a>
                    <span class="tq-caption tq-row" data-tq-next-locked hidden style="gap:var(--tq-space-xs)">
                        <?php echo tq_icon('lock', 16); ?> الدرس التالي يفتح بعد اجتياز المراجعة
                    </span>
                    <a class="tq-btn tq-btn--ghost tq-btn--block" href="<?php echo base_url('student/lessons'); ?>">كل دروسي</a>
                </div>
            </div>
        </aside>
    </div>
</div>

<style>
/* أدوات المشغل — من التوكنات، وبلا left/right. */
.tq-ptools {
  display: flex; flex-wrap: wrap; gap: var(--tq-space-m); align-items: center;
  padding: var(--tq-space-m) var(--tq-space-xl);
  border-block-end: 1px solid var(--tq-line);
  background: var(--tq-ground);
}
.tq-ptools__grp { display: flex; gap: var(--tq-space-s); align-items: center; }
.tq-ptools__grp--grow { flex: 1; min-inline-size: 200px; }
.tq-ptools__lbl { font-size: .78rem; color: var(--tq-text3); }
.tq-ptools__rates { display: flex; gap: 2px; }
.tq-ptools__rate {
  border: 1px solid var(--tq-line); background: var(--tq-surface);
  color: var(--tq-text2); font: inherit; font-size: .78rem; font-weight: 700;
  padding: 3px 9px; cursor: pointer;
  unicode-bidi: isolate;
}
/* الحواف المستديرة على طرفي المجموعة منطقيا: `start`/`end` تنقلبان مع
   الاتجاه وحدهما، فلا يحتاج RTL قاعدة ثانية. */
.tq-ptools__rate:first-child {
  border-start-start-radius: var(--tq-radius-small);
  border-end-start-radius: var(--tq-radius-small);
}
.tq-ptools__rate:last-child {
  border-start-end-radius: var(--tq-radius-small);
  border-end-end-radius: var(--tq-radius-small);
}
.tq-ptools__rate.is-on {
  background: var(--tq-actionMastery); color: var(--tq-onAction);
  border-color: var(--tq-actionMastery);
}
.tq-ptools__rate:focus-visible { outline: 2px solid var(--tq-focusRing); outline-offset: 1px; }

.tq-ptools__search {
  flex: 1; display: flex; align-items: center; gap: var(--tq-space-s);
  border: 1px solid var(--tq-line); border-radius: var(--tq-radius-small);
  background: var(--tq-surface); padding: var(--tq-space-xs) var(--tq-space-m);
  color: var(--tq-text3);
}
.tq-ptools__search input {
  flex: 1; min-inline-size: 0; border: 0; background: transparent;
  font: inherit; color: var(--tq-text);
}
.tq-ptools__search input:focus { outline: none; }
.tq-ptools__search:focus-within { border-color: var(--tq-teal); }

/* النص */
.tq-transcript {
  max-block-size: 320px; overflow-y: auto;
  padding: var(--tq-space-l) var(--tq-space-xl);
  border-block-end: 1px solid var(--tq-line);
}
.tq-transcript__list { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 2px; }
.tq-transcript__cue {
  display: flex; gap: var(--tq-space-m); align-items: baseline;
  padding: var(--tq-space-xs) var(--tq-space-s);
  border-radius: var(--tq-radius-small);
  cursor: pointer; background: none; border: 0; font: inherit;
  color: inherit; text-align: start; inline-size: 100%;
}
.tq-transcript__cue:hover { background: var(--tq-navyWash); }
.tq-transcript__cue.is-now { background: var(--tq-mint-fill); }
.tq-transcript__cue:focus-visible { outline: 2px solid var(--tq-focusRing); outline-offset: -2px; }
.tq-transcript__t {
  flex: none; font-variant-numeric: tabular-nums; unicode-bidi: isolate; direction: ltr;
  font-size: .8rem; font-weight: 700; color: var(--tq-teal); min-inline-size: 46px;
}
.tq-transcript__x { flex: 1; }
.tq-transcript mark { background: var(--tq-amberSoft); color: inherit; border-radius: 3px; padding: 0 2px; }

/* الملاحظة */
.tq-noteform {
  padding: var(--tq-space-l) var(--tq-space-xl);
  border-block-end: 1px solid var(--tq-line);
  display: flex; flex-direction: column; gap: var(--tq-space-s);
  background: var(--tq-amberSoft);
}
.tq-noteform textarea {
  inline-size: 100%; font: inherit; color: inherit;
  border: 1px solid var(--tq-line); border-radius: var(--tq-radius-small);
  padding: var(--tq-space-m); background: var(--tq-surface); resize: vertical;
}
.tq-noteform textarea:focus { outline: none; border-color: var(--tq-teal); }

.tq-notes { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: var(--tq-space-s); }
.tq-notes li {
  display: flex; gap: var(--tq-space-s); align-items: flex-start;
  padding: var(--tq-space-m); border-radius: var(--tq-radius-small);
  background: var(--tq-ground);
}
.tq-notes__jump {
  flex: none; background: none; border: 0; cursor: pointer; font: inherit;
  font-size: .78rem; font-weight: 700; color: var(--tq-teal);
  unicode-bidi: isolate; direction: ltr; padding: 0;
}
.tq-notes__jump:hover { text-decoration: underline; }
.tq-notes__b { flex: 1; font-size: .88rem; white-space: pre-wrap; }
.tq-notes__del {
  flex: none; background: none; border: 0; cursor: pointer;
  color: var(--tq-text3); padding: 0; line-height: 1;
}
.tq-notes__del:hover { color: var(--tq-danger); }
</style>

<?php include 'portal_close.php'; ?>
