<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * المراجعة المتباعدة — الوعد الذي كان يُقطع ولا يُنفَّذ.
 *
 * كل درس يُتقَن يقول لصاحبه: «أسئلة هذا الدرس ستعود إليك غدًا للتثبيت».
 * وكانت تعود فعلًا: الجدولة تكتب في `review_queue` بعد كل تسليم. لكن لا
 * شاشة تفتح ذلك الجدول، فما عاد شيء إلى أحد. هذه هي الشاشة الناقصة.
 *
 * ولا استعلام هنا. كل ما يُعرض يأتي من `taqdar_gate/reviews`، وكل إجابة
 * يحسمها `taqdar_gate/review_answer`. الصفحة لا تعرف الإجابة الصحيحة ولا
 * الفاصل القادم — تعرضهما بعد أن يقولهما الخادم. ولو حُسب أيّهما هنا لأمكن
 * تزوير الجدول الزمني بتعديل جافاسكربت، فيصير «الإتقان» رقمًا يُكتب لا حالة
 * تُقاس.
 *
 * وسؤال واحد في المرّة لا قائمة كاملة: الاستدعاء يقوى حين يُسأل الذهن
 * ويُجاب قبل أن يرى ما بعده. وقائمة مفتوحة تجعل الطالب يمسح الأسئلة بعينه
 * فيتعرّف عليها ولا يستدعيها.
 */
$tq_nav   = 'reviews';
$tq_role  = 'student';
$tq_title = 'المراجعة';
$tq_sub   = 'أسئلة سبق أن أتقنتَها تعود اليوم لتثبت. لا تظهر قبل موعدها، ولا تُعرض دفعةً أكبر ممّا يُنجَز.';
$tq_icon  = 'flame';

include 'portal_open.php';
?>

<div class="tq-reviews" data-tq-reviews
     data-tq-gate="<?php echo base_url('taqdar_gate'); ?>"
     data-tq-lessons="<?php echo base_url('student/lessons'); ?>">

    <!-- التحميل: هيكل بشكل المحتوى القادم، لا دوّار وسط الشاشة -->
    <div class="tq-cols" data-tq-rv-skeleton>
        <div class="tq-stack">
            <div class="tq-skeleton tq-skeleton--card" style="block-size:280px"></div>
            <div class="tq-skeleton tq-skeleton--title"></div>
            <div class="tq-skeleton" style="inline-size:70%"></div>
        </div>
        <aside class="tq-aside">
            <div class="tq-skeleton tq-skeleton--card" style="block-size:180px"></div>
        </aside>
    </div>

    <!-- الخطأ: ما حدث + زرّ إعادة. والتفصيل في السجلّ لا على الشاشة -->
    <div class="tq-card" data-tq-rv-error hidden>
        <div class="tq-empty">
            <span class="tq-icon-box tq-pastel--peach" style="inline-size:72px;block-size:72px" aria-hidden="true">
                <?php echo tq_icon('help', 34); ?>
            </span>
            <p class="tq-empty__title" data-tq-rv-error-msg>تعذّر تحميل مراجعاتك</p>
            <button class="tq-btn tq-btn--secondary" type="button" data-tq-rv-retry>إعادة المحاولة</button>
        </div>
    </div>

    <div class="tq-cols" data-tq-rv-body hidden>
        <div class="tq-stack">

            <!-- لا مستحقّ اليوم: هذا نجاح لا فراغ، فيُقال بلغة النجاح -->
            <section class="tq-card" data-tq-rv-empty hidden>
                <div class="tq-empty">
                    <span class="tq-icon-box tq-pastel--mint" style="inline-size:72px;block-size:72px" aria-hidden="true">
                        <?php echo tq_icon('check-badge', 34); ?>
                    </span>
                    <p class="tq-empty__title">لا مراجعة مستحقّة اليوم</p>
                    <p class="tq-empty__text" data-tq-rv-empty-text>
                        أسئلتك السابقة ما زالت في موعدها البعيد. تابع دروسك، وستعود إليك هنا حين يحين وقتها.
                    </p>
                    <a class="tq-btn tq-btn--primary" href="<?php echo base_url('student/lessons'); ?>">تابع دروسك</a>
                </div>
            </section>

            <!-- السؤال -->
            <section class="tq-card" data-tq-rv-question hidden>
                <div class="tq-card__head">
                    <h2 class="tq-card__title">سؤال المراجعة</h2>
                    <span class="tq-caption" data-tq-rv-counter></span>
                </div>

                <div data-tq-rv-progress style="margin-block-end:var(--tq-space-l)"></div>

                <span class="tq-eyebrow" data-tq-rv-source></span>

                <form data-tq-rv-form style="margin-block-start:var(--tq-space-m)">
                    <fieldset style="border:0;padding:0;margin:0">
                        <legend class="tq-h2" style="margin-block-end:var(--tq-space-l)" data-tq-rv-title></legend>
                        <div data-tq-rv-options></div>
                    </fieldset>

                    <p class="tq-field__msg" data-tq-rv-hint hidden style="color:var(--tq-danger)">
                        اختر إجابةً قبل التحقّق.
                    </p>

                    <div class="tq-row" style="flex-wrap:wrap;margin-block-start:var(--tq-space-xl)">
                        <button class="tq-btn tq-btn--primary" type="submit" data-tq-rv-submit>تحقّق</button>
                        <button class="tq-btn tq-btn--ghost" type="button" data-tq-rv-skip>
                            تخطَّ الآن — يبقى مستحقًّا
                        </button>
                    </div>
                </form>
            </section>

            <!-- الحكم: صواب أو خطأ ومعه موعد العودة. ولا يُعطى الحلّ بحال -->
            <section class="tq-card" data-tq-rv-verdict hidden>
                <div class="tq-row" style="gap:var(--tq-space-l);align-items:flex-start">
                    <span class="tq-icon-box" data-tq-rv-verdict-icon aria-hidden="true"></span>
                    <div style="flex:1">
                        <h2 class="tq-card__title" style="margin:0" data-tq-rv-verdict-title></h2>
                        <p class="tq-body" style="margin:var(--tq-space-s) 0 var(--tq-space-l)" data-tq-rv-verdict-text></p>
                        <div class="tq-row" style="flex-wrap:wrap" data-tq-rv-verdict-actions></div>
                    </div>
                </div>
            </section>

            <!-- خلاصة الجلسة -->
            <section class="tq-card" data-tq-rv-done hidden>
                <div class="tq-card__head">
                    <h2 class="tq-card__title">انتهت جلسة اليوم</h2>
                </div>
                <div class="tq-stats" style="grid-template-columns:repeat(3,minmax(0,1fr));gap:var(--tq-space-l)">
                    <div class="tq-stat">
                        <span class="tq-stat__value" data-tq-rv-done-answered>0</span>
                        <span class="tq-stat__label">سؤالًا أجبتَه</span>
                    </div>
                    <div class="tq-stat">
                        <span class="tq-stat__value" data-tq-rv-done-correct>0</span>
                        <span class="tq-stat__label">إجابة صحيحة</span>
                    </div>
                    <div class="tq-stat">
                        <span class="tq-stat__value" data-tq-rv-done-remaining>0</span>
                        <span class="tq-stat__label">ما زال مستحقًّا</span>
                    </div>
                </div>
                <p class="tq-caption" style="margin-block-start:var(--tq-space-l)" data-tq-rv-done-text></p>
                <div class="tq-row" style="flex-wrap:wrap;margin-block-start:var(--tq-space-l)" data-tq-rv-done-actions></div>
            </section>
        </div>

        <aside class="tq-aside">
            <div class="tq-card">
                <h2 class="tq-card__title">مستحقّ اليوم</h2>
                <div class="tq-stats" style="grid-template-columns:repeat(2,minmax(0,1fr));gap:var(--tq-space-l)">
                    <div class="tq-stat">
                        <span class="tq-stat__value" data-tq-rv-total>—</span>
                        <span class="tq-stat__label">سؤالًا مستحقًّا</span>
                    </div>
                    <div class="tq-stat">
                        <span class="tq-stat__value" data-tq-rv-batch>—</span>
                        <span class="tq-stat__label">دفعة اليوم</span>
                    </div>
                </div>
            </div>

            <div class="tq-card">
                <h2 class="tq-card__title">كيف تعمل المراجعة</h2>
                <p class="tq-caption" style="margin-block-end:var(--tq-space-l)">
                    الفاصل بين المرّة والتي تليها يطول كلّما أجبتَ صوابًا، ويعود إلى الغد كلّما تعثّرت.
                </p>
                <ul class="tq-stack" style="--tq-space-l:var(--tq-space-m);list-style:none;padding:0;margin:0">
                    <li class="tq-row" style="gap:var(--tq-space-s);align-items:flex-start">
                        <span class="tq-icon-box tq-pastel--mint" aria-hidden="true"><?php echo tq_icon('check', 16); ?></span>
                        <span class="tq-caption">إجابة صحيحة تُباعد الموعد، حتى ستّين يومًا على الأكثر.</span>
                    </li>
                    <li class="tq-row" style="gap:var(--tq-space-s);align-items:flex-start">
                        <span class="tq-icon-box tq-pastel--peach" aria-hidden="true"><?php echo tq_icon('clock', 16); ?></span>
                        <span class="tq-caption">إجابة خاطئة تُعيد السؤال غدًا — ولا تُعطيك الحلّ.</span>
                    </li>
                    <li class="tq-row" style="gap:var(--tq-space-s);align-items:flex-start">
                        <span class="tq-icon-box tq-pastel--sky" aria-hidden="true"><?php echo tq_icon('target', 16); ?></span>
                        <span class="tq-caption">كل سؤال مربوط بهدف في درسه، فتعرف أين تراجع لا أن تعيد الدرس كلّه.</span>
                    </li>
                </ul>
            </div>
        </aside>
    </div>
</div>

<?php include 'portal_close.php'; ?>
