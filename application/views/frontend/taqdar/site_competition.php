<?php
/**
 * صفحة المسابقة الواحدة.
 *
 * صفحة القائمة تعرض بطاقات، والبطاقة لا تسع شروط المشاركة ولا وصف
 * الأسئلة ولا الجائزة مفصلة — فكان الزائر يقرر المشاركة من ثلاثة
 * أسطر. وهذه الصفحة موضع التفصيل، والتسجيل فيها يمر بـ
 * `competition_join` **نفسها** التي تمر بها القائمة: مساران يكتبان
 * في `competition_entries` بشرطين مختلفين يعنيان مقعدا يحجز مرتين.
 *
 * والإيضاح عن «نافس» يعاد هنا لا يحال إليه: من يصل من محرك بحث لم يمر
 * بصفحة القائمة أصلا، وإيهام ارتباط رسمي لا وجود له مخاطرة لا داعي لها.
 */
$tq_c    = $tq_comp;
$tq_ci   = &get_instance();
$tq_uid  = (int) $tq_ci->session->userdata('user_id');
$tq_now  = date('Y-m-d');
$tq_open = ((string) $tq_c['status'] === 'open')
        && (empty($tq_c['ends_at']) || $tq_c['ends_at'] >= $tq_now);
$tq_full = ((int) $tq_c['seats'] > 0 && (int) $tq_c['entries'] >= (int) $tq_c['seats']);
?>
<section class="page-hero page-hero--path">
  <?php include __DIR__ . '/site/site_arch.php'; ?>
  <div class="shell">
    <span class="lantern lantern--r" aria-hidden="true"><?php include __DIR__ . '/site/site_lantern.php'; ?></span>
    <nav class="crumbs" aria-label="مسار التصفح">
      <a href="<?php echo base_url(); ?>">الرئيسية</a>
      <span aria-hidden="true">›</span>
      <a href="<?php echo base_url('catalog'); ?>">المواد والبرامج</a>
      <span aria-hidden="true">›</span>
      <a href="<?php echo base_url('competitions'); ?>">المسابقات</a>
    </nav>
    <h1><?php echo html_escape($tq_c['title']); ?></h1>
<?php if (!empty($tq_c['tagline'])): ?>
    <p class="page-hero__lead"><?php echo html_escape($tq_c['tagline']); ?></p>
<?php endif; ?>
  </div>
</section>

<section class="section">
  <div class="shell tq-cols-2">

    <div class="path-main">
<?php if ($tq_f2 = $tq_ci->session->flashdata('flash_message')): ?>
      <p class="tq-flash tq-flash--ok" role="status"><?php echo html_escape($tq_f2); ?></p>
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

      <div class="icard">
        <h2>عن هذه المسابقة</h2>
        <?php /* `tqs_rich_text` لا `html_escape`: الوصف يحرر بمحرر غني
                 فيخزن `<p>…</p>` — والتهريب يطبع الوسوم حرفا في الصفحة،
                 والطباعة الخام تفتح كل صفحة عامة لحقن سكربت من شاشة
                 تحرير مسابقة. */ ?>
        <?php echo tqs_rich_text($tq_c['description'],
            (string) $tq_c['tagline'] !== '' ? $tq_c['tagline']
                                            : 'تحد قصير يقيس المستوى ويقارن التقدم.'); ?>

        <div class="path-facts">
<?php if (!empty($tq_c['cat_name'])): ?>
          <span><svg aria-hidden="true"><use href="#i-curriculum"></use></svg><?php echo html_escape($tq_c['cat_name']); ?></span>
<?php endif; ?>
<?php if (!empty($tq_c['subject_ar'])): ?>
          <span><svg aria-hidden="true"><use href="#i-book"></use></svg><?php echo html_escape($tq_c['subject_ar']); ?></span>
<?php endif; ?>
<?php if (!empty($tq_c['starts_at'])): ?>
          <span><svg aria-hidden="true"><use href="#i-calendar"></use></svg>تبدأ <span class="tq-ltr"><?php
              echo html_escape(tqs_date_ar($tq_c['starts_at'])); ?></span></span>
<?php endif; ?>
<?php if (!empty($tq_c['ends_at'])): ?>
          <span><svg aria-hidden="true"><use href="#i-clock"></use></svg>تنتهي <span class="tq-ltr"><?php
              echo html_escape(tqs_date_ar($tq_c['ends_at'])); ?></span></span>
<?php endif; ?>
<?php if ((int) $tq_c['entries'] > 0): ?>
          <span><svg aria-hidden="true"><use href="#i-users"></use></svg><span class="tq-ltr"><?php
              echo (int) $tq_c['entries']; ?></span> مشارك</span>
<?php endif; ?>
        </div>
      </div>

<?php if (!empty($tq_c['prize'])): ?>
      <div class="icard">
        <h2>الجائزة</h2>
        <p><svg class="tq-inline-ico" aria-hidden="true"><use href="#i-badge"></use></svg>
           <?php echo html_escape($tq_c['prize']); ?></p>
      </div>
<?php endif; ?>
    </div>

    <aside class="path-buy">
      <div class="icard icard--sticky">
        <p class="path-inbundle">
          <svg aria-hidden="true"><use href="#i-badge"></use></svg>
          <?php echo $tq_open ? 'التسجيل مفتوح الآن'
              : (((string) $tq_c['status'] === 'done') ? 'انتهت هذه المسابقة' : 'التسجيل مغلق'); ?>
        </p>

<?php if ((int) $tq_c['seats'] > 0): ?>
        <p class="tq-caption">
          المقاعد <span class="tq-ltr"><?php echo (int) $tq_c['entries']; ?></span>
          من <span class="tq-ltr"><?php echo (int) $tq_c['seats']; ?></span>
        </p>
<?php endif; ?>

<?php if (!empty($tq_mine)): ?>
        <p class="comp-state comp-state--in">
          <svg aria-hidden="true"><use href="#i-check"></use></svg>أنت مسجل في هذه المسابقة
        </p>
<?php elseif (!$tq_open): ?>
        <button class="btn btn--primary btn--block" type="button" disabled>
          <?php echo ((string) $tq_c['status'] === 'done') ? 'انتهت' : 'التسجيل مغلق'; ?>
        </button>
<?php elseif ($tq_full): ?>
        <button class="btn btn--primary btn--block" type="button" disabled>اكتمل العدد</button>
<?php elseif ($tq_uid > 0): ?>
        <?php /* `tq_csrf()` صراحة: الحقن العام من `includes_top.php` يجري
                 بجافاسكربت، ونموذج يعتمد عليه ليحفظ يسقط صامتا متى تعثر. */ ?>
        <form method="post" action="<?php echo base_url('competitions/join'); ?>">
          <?php echo tq_csrf(); ?>
          <input type="hidden" name="competition_id" value="<?php echo (int) $tq_c['id']; ?>">
          <button class="btn btn--primary btn--block" type="submit">سجل في المسابقة</button>
        </form>
<?php else: ?>
        <a class="btn btn--primary btn--block" href="<?php echo base_url('login'); ?>">سجل الدخول للمشاركة</a>
        <p class="tq-caption">المشاركة لطلاب المنصة — فالنتائج تقاس والشهادات تنسب.</p>
<?php endif; ?>

        <a class="path-back" href="<?php echo base_url('competitions'); ?>">كل المسابقات</a>
      </div>
    </aside>

  </div>
</section>
