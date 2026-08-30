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
 * أصلا في `assets/global/pdf-canvas/`. وهو ليس حماية تامة — لا حماية
 * تامة لملف يعرض — لكنه يمنع النسخ العرضي، ولا يعطي الرابط في وسم.
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

/* مرحلة الطالب من صفه. و`category` هي جدول المراحل الذي يربط به الكتاب
   (`books.category_id`)، وهو نفسه الذي يبوب به الكتالوج — فلا تصنيف ثان
   يفترق عنه. */
$tq_cat_id = 0;
try {
    $row = $CI->db->query(
        'SELECT c.`id`
           FROM `users` u
           JOIN `grades` g ON g.`id` = u.`grade_id`
           LEFT JOIN `category` c ON c.`id` = g.`category_id`
          WHERE u.`id` = ? LIMIT 1', array($uid))->row_array();
    $tq_cat_id = $row ? (int) $row['id'] : 0;
} catch (Throwable $e) {
    $tq_cat_id = 0;
}

$tq_books = array();
try {
    $CI->db->select('b.id, b.title, b.slug, b.subject, b.author, b.pages, b.tone, b.cover, b.file, b.description')
           ->from('books b')->where('b.status', 'published');
    if ($tq_cat_id > 0) $CI->db->where('b.category_id', $tq_cat_id);
    $tq_books = $CI->db->order_by('b.tq_order', 'ASC')->order_by('b.id', 'DESC')
                       ->get()->result_array();

    /* لا كتاب لمرحلته: الكل خير من شاشة فارغة تقول «لا شيء هنا» — وهي
       وجهة ميتة في قائمة ثابتة. */
    if (!$tq_books && $tq_cat_id > 0) {
        $tq_books = $CI->db->select('b.id, b.title, b.slug, b.subject, b.author, b.pages, b.tone, b.cover, b.file, b.description')
                           ->from('books b')->where('b.status', 'published')
                           ->order_by('b.tq_order', 'ASC')->limit(24)->get()->result_array();
        $tq_cat_id = 0;
    }
} catch (Throwable $e) {
    $tq_books = array();
}

include 'portal_open.php';
?>

<div class="tq-library" data-tq-library
     data-tq-pdfjs="<?php echo base_url('assets/global/pdf-canvas/pdf.min.js'); ?>"
     data-tq-worker="<?php echo base_url('assets/global/pdf-canvas/pdf.worker.min.js'); ?>">

<?php if (!$tq_books): ?>

  <section class="tq-card">
    <?php echo tq_s_empty('book', 'sand', t('لا كتب في مكتبتك بعد'),
          t('كتب مرحلتك تظهر هنا حالما تنشر. وحتى ذلك، تصفح البرامج والدروس.'),
          t('تصفح الكتالوج'), base_url('catalog'), false, 'primary'); ?>
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
      $meta     = array_filter(array($b['subject'], $b['author']));
    ?>
      <article class="tq-lib-card">
        <div class="tq-lib-card__cover tq-lib-tone--<?php echo $tone; ?>">
          <?php if (trim((string) $b['cover']) !== ''): ?>
            <img src="<?php echo base_url(html_escape($b['cover'])); ?>" alt=""
                 loading="lazy" width="200" height="280">
          <?php else: ?>
            <span aria-hidden="true"><?php echo tq_icon('book', 34); ?></span>
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
              <button class="tq-btn tq-btn--primary tq-btn--sm" type="button"
                      data-tq-read="<?php echo base_url(html_escape($b['file'])); ?>"
                      data-tq-title="<?php echo html_escape($b['title']); ?>"><?php echo t('افتح الكتاب'); ?></button>
            <?php else: ?>
              <?php /* لا ملف: يقال ذلك صراحة. زر يفتح لا شيء أسوأ من غيابه. */ ?>
              <span class="tq-caption"><?php echo t('الملف لم يرفع بعد'); ?></span>
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
.tq-reader__stage canvas {
  max-inline-size: 100%; block-size: auto;
  box-shadow: var(--tq-shadow-card); background: #fff;
  border-radius: var(--tq-radius-small);
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
        msg.textContent = e.message || 'تعذر فتح هذا الكتاب.';
      });
  }

  function render() {
    if (!doc || busy) return Promise.resolve();
    busy = true;
    return doc.getPage(page).then(function (p) {
      /* المقياس يشتق من عرض المسرح لا من رقم ثابت: صفحة بمقياس 1.5
         تخرج عن الشاشة في الجوال وتقرأ نصفها. */
      var stage = rd.querySelector('.tq-reader__stage');
      var avail = Math.max(280, stage.clientWidth - 40);
      var base  = p.getViewport({ scale: 1 });
      var scale = Math.min(2.2, avail / base.width);
      var vp    = p.getViewport({ scale: scale });

      canvas.width  = Math.floor(vp.width);
      canvas.height = Math.floor(vp.height);

      return p.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
    }).then(function () {
      busy = false;
      pos.textContent = page + ' / ' + total;
    }).catch(function () { busy = false; });
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
})();
</script>

<?php include 'portal_close.php'; ?>
