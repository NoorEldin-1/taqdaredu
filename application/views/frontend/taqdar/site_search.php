<?php
/**
 * نتائج البحث العام.
 *
 * البطاقات هي بطاقات الصفحات نفسها (`pcard` · `post-card` · `book-card`
 * · `teacher-card`)؛ فلا وسم جديد يصان، وما يراه الزائر في النتيجة هو
 * ما سيراه في الصفحة التي يصل إليها.
 */
$tq_h1   = 'البحث';
$tq_lead = ($tq_q === '')
    ? 'اكتب ما تبحث عنه: مادة أو مقالا أو كتابا أو معلما.'
    : 'نتائج البحث عن «' . $tq_q . '» — ' . tq_num($tq_res['total']) . ' نتيجة.';
include __DIR__ . '/site/site_pagehero.php';
?>
<section class="section">
  <div class="shell">

    <form class="sitesearch sitesearch--page" role="search" method="get"
          action="<?php echo base_url('search'); ?>">
      <label class="sr-only" for="siteQ">ابحث في المنصة</label>
      <svg aria-hidden="true"><use href="#i-search"></use></svg>
      <input id="siteQ" type="search" name="q" placeholder="ابحث عن مادة أو مقال أو معلم…"
             value="<?php echo html_escape($tq_q); ?>" autocomplete="off">
      <button class="btn btn--primary btn--sm" type="submit">بحث</button>
    </form>

<?php if ($tq_q === ''): ?>
<?php elseif ((int) $tq_res['total'] === 0): ?>
    <p class="dir-empty">لم نجد شيئا يطابق «<?php echo html_escape($tq_q); ?>».
       جرب كلمة أقصر أو أعم.</p>
<?php else: ?>

<?php if ($tq_res['paths']): ?>
    <div class="section-head"><h2>المواد والبرامج</h2></div>
    <?php echo tqs_subject_grid($tq_res['paths']); ?>
<?php endif; ?>

<?php if ($tq_res['teachers']): ?>
    <div class="section-head"><h2>المعلمون</h2></div>
    <div class="grid-3">
<?php foreach ($tq_res['teachers'] as $tq_t): ?>
      <article class="pcard">
        <div class="pcard__body">
          <h3><?php echo html_escape($tq_t['name']); ?></h3>
<?php if ((string) $tq_t['bio'] !== ''): ?>
          <p><?php echo html_escape(tqs_excerpt($tq_t['bio'], 130)); ?></p>
<?php endif; ?>
          <a class="pcard__link" href="<?php echo html_escape($tq_t['url']); ?>">صفحة المعلم</a>
        </div>
      </article>
<?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($tq_res['posts']): ?>
    <div class="section-head"><h2>المدونة</h2></div>
    <div class="grid-3">
<?php foreach ($tq_res['posts'] as $tq_p): ?>
      <article class="pcard">
        <div class="pcard__media"><img src="<?php echo tqs_img($tq_p['img'], 'blog-1'); ?>"
             width="620" height="620" loading="lazy" decoding="async" alt=""></div>
        <div class="pcard__body">
          <h3><?php echo html_escape($tq_p['title']); ?></h3>
          <p><?php echo html_escape($tq_p['excerpt']); ?></p>
          <a class="pcard__link" href="<?php echo html_escape($tq_p['url']); ?>">اقرأ المقال</a>
        </div>
      </article>
<?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($tq_res['books']): ?>
    <div class="section-head"><h2>كتب المنهج</h2></div>
    <div class="grid-3">
<?php foreach ($tq_res['books'] as $tq_b): ?>
      <article class="pcard">
        <div class="pcard__body">
          <h3><?php echo html_escape($tq_b['title']); ?></h3>
<?php if ((string) $tq_b['subject'] !== ''): ?>
          <p><?php echo html_escape($tq_b['subject']); ?></p>
<?php endif; ?>
          <?php /* رابط الكتاب نفسه لا رابط القائمة: من بحث فوجد يريده هو،
                   و«إلى الكتب» تعيده ليبحث فيها مرة ثانية عما وجد. */ ?>
          <a class="pcard__link" href="<?php echo html_escape($tq_b['href']); ?>">تفاصيل الكتاب</a>
        </div>
      </article>
<?php endforeach; ?>
    </div>
<?php endif; ?>

<?php endif; ?>
  </div>
</section>
