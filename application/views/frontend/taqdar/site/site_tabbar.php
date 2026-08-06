<?php
/**
 * شريط التنقل السفلي — على الجوال وحده.
 *
 * **ثابت لا يختفي عند التمرير**، وهو سلوك شريط التطبيقات الأصلي —
 * وبتكلفة تشغيل صفر: لا مستمع تمرير جديد بعد شكوى التلعثم.
 *
 * ولا يدرج إلا من `site_footer.php`، وذاك لا يدرج إلا للصفحات
 * المنقولة — فالحارس مجاني ولا تلمس `index.php`.
 */

/* صفحات النماذج تستثنى: شريط يزاحم حقلا مفتوحا ولوحة مفاتيح
   منبثقة يضيق ما يحتاج سعة. */
if (in_array((string) ($page_name ?? ''), array('login', 'sign_up', 'forgot_password'), true)) return;

$tq_tab = tqs_nav_key($page_name ?? '');
$tq_in  = (int) $this->session->userdata('user_id') > 0;   /* مطابق لـsite_header حرفيا */

$tq_tabs = array(
    array('home',     base_url(),           'الرئيسية',  null),
    array('paths',    base_url('plans'),  'البرامج',  'i-grid'),
    array('books',    base_url('books'),    'الكتب',     'i-book'),
    array('teachers', base_url('teachers'), 'المعلمون', 'i-teacher'),
    array('',         $tq_in ? base_url('student') : base_url('login'),
                      $tq_in ? 'حسابي' : 'دخول', 'i-user'),
);
?>
<nav class="tq-tabbar" aria-label="التنقل السريع">
<?php foreach ($tq_tabs as $t): $cur = ($t[0] !== '' && $t[0] === $tq_tab); ?>
  <a class="tq-tabbar__item" href="<?php echo $t[1]; ?>"<?php echo $cur ? ' aria-current="page"' : ''; ?>>
    <span class="tq-tabbar__ico" aria-hidden="true"><?php
      /* لا `i-home` في المكتبة، والمساعد محمل تلقائيا ⇒ صفر تعديل على السبرايت */
      echo $t[3] ? '<svg><use href="#' . $t[3] . '"></use></svg>' : tq_icon('home', 22);
    ?></span>
    <span class="tq-tabbar__lbl"><?php echo html_escape($t[2]); ?></span>
  </a>
<?php endforeach; ?>
</nav>
