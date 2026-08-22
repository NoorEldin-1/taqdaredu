<?php
/**
 * المسابقات.
 *
 * **نافس برنامج وطني رسمي تديره هيئة تقويم التعليم والتدريب.** ومسابقات
 * تقدر **على نمطه** لا هو، والإيضاح مكتوب في أعلى الصفحة لا مطوي في
 * الشروط: إيهام ارتباط رسمي لا وجود له مخاطرة لا داعي لها، والقيمة
 * للطالب تبقى كاملة بلا استعارة الاسم.
 */
/* يحرران من «نصوص الصفحات»؛ و`_raw` لأن `site_pagehero.php` يهرب. */
$tq_h1   = tq_text_raw('competitions', 'hero_title', 'المسابقات');
$tq_lead = tq_text_raw('competitions', 'hero_lede', 'تحديات دورية على نمط أسئلة نافس، يقيس بها الطالب مستواه ويقارن تقدمه.');
include __DIR__ . '/site/site_pagehero.php';

$tq_ci  = &get_instance();
$tq_uid = (int) $tq_ci->session->userdata('user_id');
$tq_now = date('Y-m-d');

$tq_comps = $tq_ci->db->select('c.*, cat.name AS cat_name,
                                (SELECT COUNT(*) FROM competition_entries e WHERE e.competition_id = c.id) AS entries', false)
                      ->from('competitions c')
                      ->join('category cat', 'cat.id = c.category_id', 'left')
                      ->where_in('c.status', array('open', 'closed', 'done'))
                      ->order_by('c.tq_order', 'ASC')->order_by('c.id', 'DESC')
                      ->get()->result_array();

$tq_mine = array();
if ($tq_uid > 0) {
    foreach ($tq_ci->db->select('competition_id')->from('competition_entries')
                       ->where('user_id', $tq_uid)->get()->result_array() as $tq_e) {
        $tq_mine[(int) $tq_e['competition_id']] = true;
    }
}
?>
<section class="section">
  <div class="shell">
    <?php if ($tq_f = $tq_ci->session->flashdata('flash_message')): ?>
      <p class="tq-flash tq-flash--ok" role="status"><?php echo html_escape($tq_f); ?></p>
    <?php endif; ?>
    <?php if ($tq_e2 = $tq_ci->session->flashdata('error_message')): ?>
      <p class="tq-flash tq-flash--err" role="alert"><?php echo html_escape($tq_e2); ?></p>
    <?php endif; ?>

    <div class="icard comp-note">
      <svg aria-hidden="true"><use href="#i-shield"></use></svg>
      <p>
        مسابقات <strong>تقدر</strong> من إعدادنا و<strong>على نمط أسئلة نافس</strong>،
        وهي <strong>غير مرتبطة بهيئة تقويم التعليم والتدريب</strong> ولا تغني عن
        اختبارات نافس الرسمية.
      </p>
    </div>

    <?php if ($tq_comps): ?>
      <?php /* TQ-DIR-FEW: مسابقة واحدة في شبكة رباعية تجلس في ربع
               الصف وتترك ثلاثة أرباعه فراغا. والمرن يتوسط ما وجد. */ ?>
      <div class="grid-4 comp-grid">
        <?php foreach ($tq_comps as $tq_c):
          $tq_open  = ($tq_c['status'] === 'open')
                   && (empty($tq_c['ends_at']) || $tq_c['ends_at'] >= $tq_now);
          $tq_in    = isset($tq_mine[(int) $tq_c['id']]);
          $tq_full  = ((int) $tq_c['seats'] > 0 && (int) $tq_c['entries'] >= (int) $tq_c['seats']);
        ?>
          <article class="icard reveal comp-card">
            <?php if (!empty($tq_c['cat_name'])): ?>
              <span class="post-tag"><?php echo html_escape($tq_c['cat_name']); ?></span>
            <?php endif; ?>
            <?php /* العنوان رابط إلى صفحة المسابقة المفردة (`/competition/<slug>`)
                     — وفيها وصفها وشروطها. وكانت البطاقة تعرض سطرا واحدا
                     وزر تسجيل، فيسجل الطالب في مسابقة لم يقرأ عنها شيئا.
                     وبلا `slug` لا رابط: `competition_by_slug` تقبل الرقم
                     أيضا، لكن الرابط الرقمي لا يقرأ ولا يشارك. */ ?>
            <h3>
              <?php if (!empty($tq_c['slug'])): ?>
                <a href="<?php echo base_url('competition/' . rawurlencode((string) $tq_c['slug'])); ?>">
                  <?php echo html_escape($tq_c['title']); ?>
                </a>
              <?php else: ?>
                <?php echo html_escape($tq_c['title']); ?>
              <?php endif; ?>
            </h3>
            <?php if (!empty($tq_c['tagline'])): ?>
              <p><?php echo html_escape($tq_c['tagline']); ?></p>
            <?php endif; ?>

            <div class="path-facts">
              <?php if (!empty($tq_c['starts_at'])): ?>
                <span><svg aria-hidden="true"><use href="#i-calendar"></use></svg>
                  تبدأ <span class="tq-ltr"><?php echo html_escape(tqs_date_ar($tq_c['starts_at'])); ?></span></span>
              <?php endif; ?>
              <?php if ((int) $tq_c['entries'] > 0): ?>
                <span><svg aria-hidden="true"><use href="#i-users"></use></svg>
                  <span class="tq-ltr"><?php echo (int) $tq_c['entries']; ?></span> مشارك</span>
              <?php endif; ?>
              <?php if (!empty($tq_c['prize'])): ?>
                <span><svg aria-hidden="true"><use href="#i-badge"></use></svg><?php echo html_escape($tq_c['prize']); ?></span>
              <?php endif; ?>
            </div>

            <?php if ($tq_in): ?>
              <p class="comp-state comp-state--in">
                <svg aria-hidden="true"><use href="#i-check"></use></svg>أنت مسجل في هذه المسابقة
              </p>
            <?php elseif (!$tq_open): ?>
              <button class="btn btn--primary btn--block" type="button" disabled>
                <?php echo ($tq_c['status'] === 'done') ? 'انتهت' : 'التسجيل مغلق'; ?>
              </button>
            <?php elseif ($tq_full): ?>
              <button class="btn btn--primary btn--block" type="button" disabled>اكتمل العدد</button>
            <?php elseif ($tq_uid > 0): ?>
              <form method="post" action="<?php echo base_url('competitions/join'); ?>">
                <input type="hidden" name="competition_id" value="<?php echo (int) $tq_c['id']; ?>">
                <button class="btn btn--primary btn--block" type="submit">سجل في المسابقة</button>
              </form>
            <?php else: ?>
              <a class="btn btn--primary btn--block" href="<?php echo base_url('login'); ?>">سجل الدخول للمشاركة</a>
              <p class="tq-caption">المشاركة لطلاب المنصة — فالنتائج تقاس والشهادات تنسب.</p>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p class="dir-empty">لا مسابقات معلنة الآن. تابعنا — نطلق تحديا جديدا كل فترة.</p>
    <?php endif; ?>
  </div>
</section>
