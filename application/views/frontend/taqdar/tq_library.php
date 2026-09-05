<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * مكتبتي — الكتب والقارئ.
 *
 * «الكتب والقارئ» في قائمة شاشات الطالب بوثيقة المنتج، وكانت الكتب في
 * الكتالوج العام وفي لوحة الإدارة وحدهما: يشتري الطالب باقة فيها كتب،
 * ولا موضع في بوابته يفتحها منه.
 *
 * **والقارئ داخل الصفحة لا تحميل.** الفرق ليس راحة: الرابط المباشر إلى
 * `uploads/*.pdf` ينسخ ويوزع، والقارئ يعرض صفحة صفحة من `pdf.js` المثبت
 * في المستودع بلا CDN. وهو ليس حماية تامة — لا حماية تامة لملف يعرض —
 * لكنه يمنع النسخ العرضي، ولا يعطي الرابط في وسم. والرابط نفسه يمر
 * بحارس (`book-file/<id>`) لأن الكتاب صار يباع — انظر TQ-BOOK-GATE.
 *
 * ═══ TQ-PDFJS-ARABIC — ولماذا نسخة ثانية من pdf.js لا ترقية للأولى ═══
 *
 * المثبت في `assets/global/pdf-canvas/` هو **2.0.943** (٢٠١٨)، وهو
 * يرسم العربية **بحروف مفككة معكوسة**: كتاب المنهج يفتح فيقرأ الطالب
 * «خطتان يذ ذ م هق قم» مكان «خطة تنفيذ منصة تقدر». والملف سليم — يفتحه
 * قارئ المتصفح نفسه صحيحا — والعطل في المكتبة وحدها.
 *
 * ولم يظهر حتى اليوم لأن **لا كتاب في القاعدة كان له ملف**: القارئ
 * مكتوب منذ كتب ولم يفتح مرة واحدة. وأول ملف حقيقي يرفع هو أول من
 * يشغله — وأول من يكشفه.
 *
 * والنسخة الجديدة (**3.11.174**) تسكن `assets/global/pdfjs/` ولا تحل
 * محل القديمة: `views/lessons/pdf_canvas.php` — شاشة دروس PDF الموروثة
 * من Academy — تنادي `getDocument(url).then()` وهي صيغة ما قبل 2.x،
 * حذفت في 2.5. فترقية الملف في موضعه تصلح مكتبة الطالب **وتكسر شاشة
 * أخرى قائمة**، والمسار الثاني يكلف ملفا ولا يكسر شيئا.
 *
 * والكتب ترشح بمرحلة الطالب: كتاب صف ثالث في مكتبة طالب ثانوي ضجيج.
 * ومن لا مرحلة له يرى الكل — الترشيح الذي يفرغ الشاشة أسوأ من ألا يكون.
 */
include 'tq_student_styles.php';

$tq_nav   = 'library';
$tq_role  = 'student';
$tq_title = t('مكتبتي');
$tq_sub   = t('كتب مرحلتك — تقرأ هنا صفحة صفحة، بلا تحميل ولا تطبيق ثان.');
$tq_icon  = 'book';

$CI  = &get_instance();
$uid = isset($user_id) ? (int) $user_id : (int) $CI->session->userdata('user_id');

/* القاعدة في `Taqdar_student_model::library()`: الكتب ترشح بمرحلة
   الطالب (`category` — الجدول نفسه الذي يبوب به الكتالوج، فلا تصنيف
   ثان يفترق عنه)، ومن لا كتاب لمرحلته يرى الكل. والواجهة (`Api_v1`)
   تنادي الدالة نفسها. */
$CI->load->model('taqdar_student_model', 'tq_stu');
$tq_lib    = $CI->tq_stu->library($uid);
$tq_books  = $tq_lib['books'];
/* TQ-BOOK — مجموعتان لا واحدة: ما يقرؤه الآن، وما يفتحه بشراء.
   وقائمة واحدة تخلطهما تعرض «افتح الكتاب» على كتاب لا يملكه فلا يفتح،
   ولا شيء يقول لماذا. */
$tq_locked = isset($tq_lib['locked']) ? $tq_lib['locked'] : array();
$tq_cat_id = (int) $tq_lib['category_id'];

include 'portal_open.php';
?>

<div class="tq-library" data-tq-library
     data-tq-pdfjs="<?php echo base_url('assets/global/pdfjs/pdf.min.js'); ?>"
     data-tq-worker="<?php echo base_url('assets/global/pdfjs/pdf.worker.min.js'); ?>">

<?php if (!$tq_books && !$tq_locked): ?>

  <section class="tq-card">
    <?php echo tq_s_empty('book', 'sand', t('لا كتب في مكتبتك بعد'),
          t('كتب مرحلتك تظهر هنا حالما تنشر. وحتى ذلك، تصفح البرامج والدروس.'),
          t('تصفح الكتب'), base_url('books'), false, 'primary'); ?>
  </section>

<?php elseif (!$tq_books): ?>

  <?php /* له كتب تشترى ولا كتاب مفتوح: الحالة الفارغة تقول ذلك بعينه
           بدل «لا كتب بعد» — وهي كاذبة، والكتب تحتها. */ ?>
  <section class="tq-card">
    <?php echo tq_s_empty('book', 'sand', t('لم تفتح كتابا بعد'),
          t('كتب مرحلتك أدناه — تشترى وحدها، أو تفتح كلها باشتراكك في باقة صفك.'),
          t('تصفح الباقات'), base_url('plans'), false, 'primary'); ?>
  </section>

<?php else: ?>

  <?php if ($tq_cat_id === 0): ?>
    <p class="tq-caption" style="margin-block-end:var(--tq-space-l)">
      <?php echo t('لا كتب مخصصة لمرحلتك بعد، فهذه كتب المنصة كلها.'); ?>
    </p>
  <?php endif; ?>

  <div class="tq-lib-grid">
    <?php foreach ($tq_books as $i => $b):
      $has_file = trim((string) $b['file']) !== '';
      $tone     = html_escape($b['tone'] ?: 'math');
      /* المادة صارت وجه الغلاف (TQ-COVER-BLANK)، فسطر «الرياضيات» تحت
         عنوان «الرياضيات — الصف الأول» يكرر ما قرئ مرتين قبله. ويبقى
         المؤلف: هو وحده ما لا يقوله الغلاف. */
      $meta     = array_filter(array($b['author']));
    ?>
      <article class="tq-lib-card">
        <div class="tq-lib-card__cover tq-lib-tone--<?php echo $tone; ?>">
          <?php if (trim((string) $b['cover']) !== ''): ?>
            <img src="<?php echo base_url(html_escape($b['cover'])); ?>" alt=""
                 loading="lazy" width="200" height="280">
          <?php else: ?>
            <?php /* TQ-COVER-BLANK — الغلاف الخالي يحمل اسمه.
                     كان مستطيلا بنسبة ٥:٧ (٢٢٠×٣٠٨) فيه أيقونة قطرها ٣٤ —
                     أي أن تسعة وتسعين بالمئة منه فراغ ملون. وثمانية كتب
                     تعرض ثمانية مستطيلات لا يفرق بينها إلا درجة اللون،
                     فمن يبحث عن كتاب بعينه يقرأ ثمانية عناوين من سطرين
                     تحتها — والغلاف، وهو أكبر ما في البطاقة، لا يعينه.
                     وغلاف الكتاب الورقي يحمل المادة بخط كبير والصف تحته،
                     فيقرأ من مسافة. والعنوان في القاعدة يجمعهما بشرطة
                     («الرياضيات — الصف الأول الابتدائي») فيقسم عندها:
                     المادة للغلاف، والصف شارة في زاويته. وما لا شرطة فيه
                     يعرض كما هو ولا يخترع له قسمان. */
              $tq_bits  = preg_split('/\s+[—–-]\s+/u', (string) $b['title'], 2);
              $tq_face  = trim((string) ($b['subject'] ?: $tq_bits[0]));
              $tq_stage = isset($tq_bits[1]) ? trim($tq_bits[1]) : '';
            ?>
            <p class="tq-lib-cover-t"><?php echo html_escape($tq_face); ?></p>
            <?php if ($tq_stage !== ''): ?>
              <p class="tq-lib-cover-s"><?php echo html_escape($tq_stage); ?></p>
            <?php endif; ?>
            <span aria-hidden="true"><?php echo tq_icon('book', 22); ?></span>
          <?php endif; ?>
        </div>

        <div class="tq-lib-card__body">
          <h2 class="tq-lib-card__t"><?php echo html_escape($b['title']); ?></h2>
          <?php if ($meta): ?>
            <p class="tq-lib-card__m"><?php echo html_escape(implode(' · ', $meta)); ?></p>
          <?php endif; ?>
          <?php if ((int) $b['pages'] > 0): ?>
            <p class="tq-lib-card__m"><?php echo tq_num((int) $b['pages']); ?> <?php echo t('صفحة'); ?></p>
          <?php endif; ?>

          <div class="tq-lib-card__act">
            <?php if ($has_file): ?>
              <?php /* TQ-BOOK-GATE — القارئ يقرأ من الحارس لا من
                       `uploads/` مباشرة: رابط عار لكتاب مدفوع يوزع،
                       والشراء يصير اقتراحا. والمجاني يمر بالحارس
                       نفسه بلا تسجيل، فلا مساران. */ ?>
              <button class="tq-btn tq-btn--primary tq-btn--sm" type="button"
                      data-tq-read="<?php echo base_url('book-file/' . (int) $b['id']); ?>"
                      data-tq-title="<?php echo html_escape($b['title']); ?>"><?php echo t('افتح الكتاب'); ?></button>
            <?php else: ?>
              <?php /* لا ملف: يقال ذلك صراحة. زر يفتح لا شيء أسوأ من غيابه.
                       وهي ملاحظة لا إجراء، فتأخذ سطرها كاملا ولا تصطف مع
                       زر: نص رمادي ملاصق لزر يقرأ زرا معطلا فيضغط. */ ?>
              <span class="tq-lib-card__note"><?php echo t('الملف لم يرفع بعد'); ?></span>
            <?php endif; ?>
            <?php if (trim((string) $b['slug']) !== ''): ?>
              <a class="tq-btn tq-btn--ghost tq-btn--sm"
                 href="<?php echo base_url('book/' . rawurlencode($b['slug'])); ?>"><?php echo t('تفاصيله'); ?></a>
            <?php endif; ?>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

<?php endif; ?>

<?php /* ── ما يفتح بشراء — TQ-BOOK ──────────────────────────────────
         قسم ثان تحت مكتبته لا بطاقات مبعثرة بينها: من يقرأ «مكتبتي»
         يقرأ ما يملكه، وما يشترى يقرأ في مكانه بسعره. وخلطهما يجعل
         «افتح الكتاب» يقف أمام كتاب لا يفتح. */ ?>
<?php if ($tq_locked): ?>

  <div class="tq-lib-head">
    <h2 class="tq-lib-head__t"><?php echo t('كتب تفتح بشراء'); ?></h2>
    <p class="tq-caption">
      <?php echo t('تشترى وحدها، أو تفتح كلها — ومعها البرامج والدروس — باشتراكك في باقة صفك.'); ?>
      <a href="<?php echo base_url('plans'); ?>"><?php echo t('شاهد الباقات'); ?></a>
    </p>
  </div>

  <div class="tq-lib-grid">
    <?php foreach ($tq_locked as $b):
      $tone = html_escape($b['tone'] ?: 'math');
      $meta = array_filter(array($b['author']));
    ?>
      <article class="tq-lib-card tq-lib-card--locked">
        <div class="tq-lib-card__cover tq-lib-tone--<?php echo $tone; ?>">
          <?php if (trim((string) $b['cover']) !== ''): ?>
            <img src="<?php echo base_url(html_escape($b['cover'])); ?>" alt=""
                 loading="lazy" width="200" height="280">
          <?php else: ?>
            <?php /* TQ-COVER-BLANK — الغلاف الخالي يحمل اسمه، هنا كما
                     في القسم أعلاه: القاعدة واحدة والوسم واحد. */
              $tq_bits  = preg_split('/\s+[—–-]\s+/u', (string) $b['title'], 2);
              $tq_face  = trim((string) ($b['subject'] ?: $tq_bits[0]));
              $tq_stage = isset($tq_bits[1]) ? trim($tq_bits[1]) : '';
            ?>
            <p class="tq-lib-cover-t"><?php echo html_escape($tq_face); ?></p>
            <?php if ($tq_stage !== ''): ?>
              <p class="tq-lib-cover-s"><?php echo html_escape($tq_stage); ?></p>
            <?php endif; ?>
            <span aria-hidden="true"><?php echo tq_icon('book', 22); ?></span>
          <?php endif; ?>
          <span class="tq-lib-card__lock" aria-hidden="true"><?php echo tq_icon('lock', 16); ?></span>
        </div>

        <div class="tq-lib-card__body">
          <h3 class="tq-lib-card__t"><?php echo html_escape($b['title']); ?></h3>
          <?php if ($meta): ?>
            <p class="tq-lib-card__m"><?php echo html_escape(implode(' · ', $meta)); ?></p>
          <?php endif; ?>
          <?php if ((int) $b['pages'] > 0): ?>
            <p class="tq-lib-card__m"><?php echo tq_num((int) $b['pages']); ?> <?php echo t('صفحة'); ?></p>
          <?php endif; ?>

          <p class="tq-lib-card__price">
            <b class="tq-ltr"><?php echo number_format(((int) $b['price']) / 100, 2); ?></b>
            <span><?php echo t('ر.س'); ?></span>
            <?php if ((int) $b['off'] > 0): ?>
              <s class="tq-ltr"><?php echo number_format(((int) $b['list_price']) / 100, 0); ?></s>
            <?php endif; ?>
          </p>

          <div class="tq-lib-card__act">
            <a class="tq-btn tq-btn--primary tq-btn--sm"
               href="<?php echo base_url('book-checkout/' . (int) $b['id']); ?>"><?php echo t('اشتره'); ?></a>
            <?php if (trim((string) $b['slug']) !== ''): ?>
              <a class="tq-btn tq-btn--ghost tq-btn--sm"
                 href="<?php echo base_url('book/' . rawurlencode($b['slug'])); ?>"><?php echo t('تفاصيله'); ?></a>
            <?php endif; ?>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>

<?php endif; ?>

  <!-- ── القارئ ────────────────────────────────────────────────────
       يفتح فوق الصفحة لا في تبويب: من فتح كتابا في تبويب ثان خرج من
       بوابته، ولا يعود إلى موضعه إلا بأن يبحث عنه. -->
  <div class="tq-reader" data-tq-reader hidden role="dialog" aria-modal="true"
       aria-label="<?php echo te('قارئ الكتب'); ?>">
    <header class="tq-reader__bar">
      <button class="tq-btn tq-btn--ghost tq-btn--sm" type="button" data-tq-rd-close>
        <?php echo tq_icon('close', 16); ?> <?php echo t('إغلاق'); ?>
      </button>
      <strong class="tq-reader__t" data-tq-rd-title></strong>
      <div class="tq-reader__nav">
        <button class="tq-btn tq-btn--secondary tq-btn--sm" type="button" data-tq-rd-prev><?php echo t('السابقة'); ?></button>
        <span class="tq-reader__pos" data-tq-rd-pos></span>
        <button class="tq-btn tq-btn--secondary tq-btn--sm" type="button" data-tq-rd-next><?php echo t('التالية'); ?></button>
      </div>
    </header>

    <div class="tq-reader__stage">
      <p class="tq-reader__msg" data-tq-rd-msg><?php echo t('يفتح الكتاب…'); ?></p>
      <canvas data-tq-rd-canvas></canvas>
    </div>
  </div>
</div>

<style>
.tq-lib-grid {
  display: grid; gap: var(--tq-space-l);
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
}
.tq-lib-card {
  display: flex; flex-direction: column;
  border: 1px solid var(--tq-line); border-radius: var(--tq-radius-medium);
  background: var(--tq-surface); overflow: hidden;
}
.tq-lib-card__cover {
  aspect-ratio: 5 / 7; display: grid; place-items: center; overflow: hidden;
}
.tq-lib-card__cover img { inline-size: 100%; block-size: 100%; object-fit: cover; }
.tq-lib-tone--math    { background: var(--tq-sky-fill);   color: var(--tq-sky-ink); }
.tq-lib-tone--arabic  { background: var(--tq-peach-fill); color: var(--tq-peach-ink); }
.tq-lib-tone--science { background: var(--tq-mint-fill);  color: var(--tq-mint-ink); }
.tq-lib-tone--islamic { background: var(--tq-sand-fill);  color: var(--tq-sand-ink); }
.tq-lib-tone--english { background: var(--tq-lilac-fill); color: var(--tq-lilac-ink); }

.tq-lib-card__body { padding: var(--tq-space-l); display: flex; flex-direction: column; gap: 4px; flex: 1; }
.tq-lib-card__t { font-size: 1rem; font-weight: 700; margin: 0; }
.tq-lib-card__m { font-size: .82rem; color: var(--tq-text3); margin: 0; }
.tq-lib-card__act {
  display: flex; flex-wrap: wrap; gap: var(--tq-space-s);
  align-items: center; margin-block-start: auto; padding-block-start: var(--tq-space-m);
}
.tq-lib-card__note {
  flex-basis: 100%; font: var(--tq-type-micro);
  color: var(--tq-text3); margin-block-end: var(--tq-space-xs);
}

/* TQ-BOOK — قسم «تفتح بشراء»: الغلاف مخفف والقفل عليه، فيقرأ الفرق
   من مسافة بلا أن يقرأ سطرا. */
.tq-lib-head { margin-block: var(--tq-space-xl) var(--tq-space-l); }
.tq-lib-head__t { font: var(--tq-type-h2); margin: 0 0 4px; }
.tq-lib-card--locked .tq-lib-card__cover { position: relative; opacity: .82; }
.tq-lib-card__lock {
  position: absolute; inset-block-start: 8px; inset-inline-end: 8px;
  display: grid; place-items: center; inline-size: 28px; block-size: 28px;
  border-radius: 999px; background: var(--tq-surface); color: var(--tq-text2);
  box-shadow: 0 1px 3px rgba(0,0,0,.18);
}
.tq-lib-card__price { margin: 4px 0 0; font-weight: 800; color: var(--tq-navy); }
.tq-lib-card__price span { font-size: .8em; font-weight: 700; color: var(--tq-text2); }
.tq-lib-card__price s { font-size: .8em; font-weight: 600; color: var(--tq-text3); }

/* القارئ */
.tq-reader {
  position: fixed; inset: 0; z-index: 90;
  background: var(--tq-ground);
  display: flex; flex-direction: column;
}
.tq-reader__bar {
  display: flex; flex-wrap: wrap; gap: var(--tq-space-m);
  align-items: center; justify-content: space-between;
  padding: var(--tq-space-m) var(--tq-space-l);
  background: var(--tq-surface); border-block-end: 1px solid var(--tq-line);
}
.tq-reader__t { flex: 1; min-inline-size: 120px; text-align: center; }
.tq-reader__nav { display: flex; gap: var(--tq-space-s); align-items: center; }
.tq-reader__pos {
  font-variant-numeric: tabular-nums; unicode-bidi: isolate; direction: ltr;
  min-inline-size: 80px; text-align: center; color: var(--tq-text2);
}
.tq-reader__stage {
  flex: 1; overflow: auto; display: grid; place-items: center;
  padding: var(--tq-space-l);
}
/* TQ-PDF-RTL — واللوحة تكتب من اليسار ولو كانت الصفحة من اليمين.

   `CanvasRenderingContext2D.direction` افتراضها `'inherit'`، فتأخذ اتجاه
   العنصر المحسوب — و**كل صفحة في تقدر `dir="rtl"`**. فيرسم `fillText`
   كل مقطع من اليمين، و pdf.js يسلمه مقاطع **مرتبة بصريا** بمواضع محسوبة
   على اليسار: فيعاد ترتيبها وتشكيلها مرة ثانية، ويقرأ الطالب «ذ طةتن
   فيذ» مكان «خطة تنفيذ».

   وهو غير TQ-PDFJS-ARABIC أعلاه وإن تشابه أثرهما: ذاك مكتبة قديمة ترسم
   العربية مفككة، وهذا اتجاه يرثه السياق من الصفحة. فالنسخة الجديدة
   وحدها لم تصلح شيئا — الصفحة نفسها هي التي كانت تعكس ما ترسمه.

   ولم يظهر إلا على صفحة عربية: القارئ يعمل صحيحا في أي صفحة `ltr`،
   وهو ما يجعل الاختبار خارج البوابة يقول «سليم». */
.tq-reader__stage canvas {
  max-inline-size: 100%; block-size: auto;
  box-shadow: var(--tq-shadow-card); background: #fff;
  border-radius: var(--tq-radius-small);
  direction: ltr;
}
.tq-reader__msg { color: var(--tq-text2); }
</style>

<script>
/**
 * القارئ — `pdf.js` المثبت في المستودع، بلا CDN.
 *
 * التحميل عند أول فتح لا مع الصفحة: الملف نحو ثلاثمئة كيلوبايت، وأكثر
 * من يفتح المكتبة يتصفح ولا يقرأ.
 */
(function () {
  var root = document.querySelector('[data-tq-library]');
  if (!root) return;

  var PDFJS_SRC = root.getAttribute('data-tq-pdfjs');
  var WORKER    = root.getAttribute('data-tq-worker');

  var rd     = root.querySelector('[data-tq-reader]');
  var canvas = root.querySelector('[data-tq-rd-canvas]');
  var msg    = root.querySelector('[data-tq-rd-msg]');
  var title  = root.querySelector('[data-tq-rd-title]');
  var pos    = root.querySelector('[data-tq-rd-pos]');

  var doc = null, page = 1, total = 0, busy = false, libLoaded = false;

  function loadLib() {
    if (libLoaded) return Promise.resolve();
    return new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.src = PDFJS_SRC;
      s.onload = function () {
        if (window.pdfjsLib) window.pdfjsLib.GlobalWorkerOptions.workerSrc = WORKER;
        libLoaded = true;
        resolve();
      };
      s.onerror = function () { reject(new Error('تعذر تحميل القارئ.')); };
      document.head.appendChild(s);
    });
  }

  function open(url, name) {
    rd.hidden = false;
    document.body.style.overflow = 'hidden';
    title.textContent = name || '';
    msg.hidden = false;
    msg.textContent = 'يفتح الكتاب…';
    canvas.style.display = 'none';

    loadLib()
      .then(function () { return window.pdfjsLib.getDocument(url).promise; })
      .then(function (d) {
        doc = d; total = d.numPages; page = 1;
        msg.hidden = true;
        canvas.style.display = '';
        return render();
      })
      .catch(function (e) {
        msg.hidden = false;
        msg.textContent = readerError(e);
      });
  }

  /* TQ-RAW-ERROR — pdf.js يرمي بالإنجليزية: «Missing PDF»، «Invalid PDF
     structure»، «Unexpected server response». وكتابتها حرفا تضع جملة
     إنجليزية وسط قارئ عربي، ولا تقول لصاحبها ما يفعل — والأربعة تعالج
     بغير ما يعالج به بعضها: هذا يبلغ معلمه، وهذا يعيد المحاولة. فالنوع
     (`e.name`) هو المفتاح لا النص، وما لا يعرف يرد إلى جملة واحدة. */
  /* والنصوص تخرج من PHP مترجمة، لا من `TQ.t()`.
     `tq_i18n_js()` لا يطبع في المتصفح إلا قاموس `js.php`، وهو يبنى من
     `assets/taqdar/js/*.js` وحدها — فمفتاح يكتب `TQ.t()` داخل قالب PHP
     يسجل في `portal.php` ولا يصل المتصفح أبدا، فيقرأ الإنجليزي عربيا
     ولا يخطئ شيء. و`te()` تحل هنا: الترجمة تقع في الخادم قبل الطبع. */
  var READER_ERR = {
    missing:  <?php echo json_encode(t('لم يعد ملف هذا الكتاب موجودا. أبلغ معلمك.'), JSON_UNESCAPED_UNICODE); ?>,
    invalid:  <?php echo json_encode(t('ملف هذا الكتاب تالف ولا يفتح. أبلغ معلمك.'), JSON_UNESCAPED_UNICODE); ?>,
    locked:   <?php echo json_encode(t('هذا الكتاب محمي بكلمة مرور ولا يفتح هنا.'), JSON_UNESCAPED_UNICODE); ?>,
    fallback: <?php echo json_encode(t('تعذر فتح هذا الكتاب. أعد المحاولة، وإن تكرر فأبلغ الدعم.'), JSON_UNESCAPED_UNICODE); ?>
  };

  function readerError(e) {
    var n = (e && e.name) || '';
    if (n === 'MissingPDFException')          return READER_ERR.missing;
    if (n === 'InvalidPDFException')          return READER_ERR.invalid;
    if (n === 'PasswordException')            return READER_ERR.locked;
    if (n === 'UnexpectedResponseException')  return TQ.netMessage('offline');
    if (!navigator.onLine)                    return TQ.netMessage('offline');
    return READER_ERR.fallback;
  }

  /* TQ-PDF-VIEWPORT — **توقيعان لـ`getViewport()` لا واحد.**

     كان المثبت pdf.js **2.0.943**، وفيه `getViewport(scale)` تأخذ
     **رقما**؛ وصيغة الكائن (`getViewport({scale})`) لم تدخل إلا في
     2.2. وتمرير كائن إليها يمرره كما هو إلى
     `PageViewport({scale: <كائن>})`، فتخرج الأبعاد `NaN`، فيصير
     `canvas.width = 0` — **ويرسم القارئ صفحة بلا حجم**.

     ولا شيء يخطئ في ذلك: `render()` تنجح على لوحة صفرية، فيقرأ الطالب
     «1 / 24» فوق مساحة بيضاء فارغة، ويظن أن الكتاب نفسه فارغ.

     ولم يظهر العطل حتى اليوم لأن **لا كتاب في القاعدة كان له ملف**:
     الثمانية كلها تعرض «الملف لم يرفع بعد»، فالقارئ مكتوب منذ كتب ولم
     يفتح مرة واحدة. وأول ملف حقيقي يرفع هو أول من يشغله.

     والحل يقبل التوقيعين معا لا يثبت على واحد: ترقية pdf.js يوما تكسر
     ما يثبت على القديم، والعكس. */
  function viewportOf(p, scale) {
    var vp = null;
    try { vp = p.getViewport(scale); } catch (e) { vp = null; }
    if (vp && isFinite(vp.width) && vp.width > 0) return vp;

    try { vp = p.getViewport({ scale: scale }); } catch (e) { vp = null; }
    if (vp && isFinite(vp.width) && vp.width > 0) return vp;
    return null;
  }

  function render() {
    if (!doc || busy) return Promise.resolve();
    busy = true;
    return doc.getPage(page).then(function (p) {
      /* المقياس يشتق من المسرح لا من رقم ثابت: صفحة بمقياس 1.5 تخرج
         عن الشاشة في الجوال وتقرأ نصفها. */
      var stage = rd.querySelector('.tq-reader__stage');
      /* والقياس يرتد إلى النافذة حين يقرأ صفرا: المسرح كان مخفيا قبل
         لحظة، وقياس عنصر لم يخطط بعد يرد صفرا فتخرج الصفحة بمقياس
         صغير جدا. */
      var availW = Math.max(280, (stage.clientWidth  || window.innerWidth  || 900) - 40);
      var availH = Math.max(240, (stage.clientHeight || window.innerHeight || 700) - 40);

      var base = viewportOf(p, 1);
      if (!base) throw new Error('viewport');

      /* TQ-PDF-FIT — والصفحة تدخل الشاشة طولا كما تدخلها عرضا.

         كان المقياس `avail / base.width` وحده، وورقة A4 نسبتها
         ١:١٫٤١ — فصفحة تملأ العرض تخرج عن الطول بأربعين بالمئة. وهذا
         قارئ **صفحة صفحة**: من ضغط «التالية» يقرأ ثلثي الصفحة ثم يمرر
         ليقرأ ثلثها، ثم يمرر إلى أعلاها ليضغط «التالية» — والزر في
         الشريط لا في المسرح، فالتمرير يعاد عند كل صفحة من ثلاث وعشرين.
         فالمقياس أصغر النسبتين، كأي قارئ. */
      var scale = Math.min(2.2, availW / base.width, availH / base.height);
      var vp    = viewportOf(p, scale) || base;

      /* TQ-PDF-DPR — والرسم بكثافة الشاشة لا بكثافة CSS.
         لوحة عرضها ٦٥٤ بكسل CSS تعرض على شاشة كثافتها اثنان بنصف
         دقتها، فيقرأ نص الكتاب مشوشا. فترسم اللوحة مضاعفة وتعرض
         بمقاسها الأول. والسقف اثنان: ثلاثة تربع مساحة الذاكرة لصفحة
         واحدة ولا تضيف ما يرى. */
      var dpr = Math.max(1, Math.min(2, window.devicePixelRatio || 1));
      var vpp = viewportOf(p, scale * dpr) || vp;

      canvas.width  = Math.max(1, Math.floor(vpp.width));
      canvas.height = Math.max(1, Math.floor(vpp.height));
      /* والعرض وحده يكتب: الطول `auto` في الورقة فيتبع النسبة، وكتابته
         هنا تفسدها متى قص `max-inline-size` العرض على شاشة ضيقة. */
      canvas.style.width = Math.floor(vp.width) + 'px';
      vp = vpp;

      var ctx = canvas.getContext('2d');
      /* TQ-PDF-RTL — والاتجاه يثبت هنا كذلك لا في الورقة وحدها.
         الورقة تصلحه (`.tq-reader__stage canvas{direction:ltr}`)، لكن
         ورقة تتعثر تعيد العطل صامتا — ونص مقلوب لا يخطئ ولا يقال، فمن
         يراه يظن الكتاب نفسه تالفا. وضبط `ctx.direction` بعد ضبط
         `canvas.width` لا قبله: كل كتابة في الأبعاد **تصفر حال السياق**
         كلها فتعيده إلى `'inherit'`. */
      ctx.direction = 'ltr';

      return p.render({ canvasContext: ctx, viewport: vp }).promise;
    }).then(function () {
      busy = false;
      pos.textContent = page + ' / ' + total;
      /* والصفحة الجديدة تبدأ من أعلاها: المسرح يحفظ تمريره، فمن قرأ
         أسفل صفحة ثم ضغط «التالية» يهبط في وسط التالية ويظن أولها
         ناقصا. */
      var st = rd.querySelector('.tq-reader__stage');
      if (st) st.scrollTop = 0;
    }).catch(function (e) {
      busy = false;
      /* والفشل يقال لا يبتلع: `catch` صامتة تترك لوحة بيضاء بلا سبب،
         وهي عين ما جعل هذا العطل يمر. */
      msg.hidden = false;
      msg.textContent = readerError(e);
      canvas.style.display = 'none';
    });
  }

  function close() {
    rd.hidden = true;
    document.body.style.overflow = '';
    if (doc) { try { doc.destroy(); } catch (e) {} }
    doc = null;
  }

  root.addEventListener('click', function (e) {
    var openBtn = e.target.closest('[data-tq-read]');
    if (openBtn) {
      open(openBtn.getAttribute('data-tq-read'), openBtn.getAttribute('data-tq-title'));
      return;
    }
    if (e.target.closest('[data-tq-rd-close]')) { close(); return; }
    if (e.target.closest('[data-tq-rd-prev]') && page > 1)     { page--; render(); return; }
    if (e.target.closest('[data-tq-rd-next]') && page < total) { page++; render(); return; }
  });

  /* لوح المفاتيح: القارئ يفتح ملء الشاشة، ومن فتحه بلوحة لا يجد فأرة
     يضغط بها «التالية». */
  document.addEventListener('keydown', function (e) {
    if (rd.hidden) return;
    if (e.key === 'Escape') close();
    else if (e.key === 'ArrowLeft'  && page < total) { page++; render(); }
    else if (e.key === 'ArrowRight' && page > 1)     { page--; render(); }
  });

  /* والمقياس يعاد حسابه متى تغير المسرح: من قلب جهازه أو غير حجم
     نافذته يبقى على مقياس شاشة ذهبت — صفحة ربع الشاشة أو صفحة تخرج
     عنها. والتأخير لأن التغيير يقع عشرات المرات في السحبة الواحدة،
     ورسم صفحة عند كل واحدة يجمد المتصفح. */
  var rzT = null;
  window.addEventListener('resize', function () {
    if (rd.hidden || !doc) return;
    clearTimeout(rzT);
    rzT = setTimeout(function () { render(); }, 200);
  });
})();
</script>

<?php include 'portal_close.php'; ?>
