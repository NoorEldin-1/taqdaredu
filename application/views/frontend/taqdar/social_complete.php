<?php
/**
 * TQ-SOCIAL-COMPLETE — ما لا يعطيه مزود.
 *
 * جوجل وأبل يعطيان بريدا موثقا واسما، ولا يعطيان جوالا ولا عمرا ولا
 * صفا. وثلاثتها يحتاجها المنتج لا النموذج:
 *
 *   **الجوال** — قناة واتساب: رمز التأكيد وإشعارات المال وتنبيهات
 *   الأبناء لولي الأمر. وحساب بلاه يقرأ صاحبه لافتة تطلبه إلى الأبد.
 *   **العمر والصف** — بهما يفتح `‎/catalog‎` على مرحلة صاحبه
 *   (`with_scope()`)، وبلاهما يقرأ الطالب كتالوج المنصة كله ويبحث عن
 *   صفه فيه.
 *
 * **وهذه الشاشة بعد الدخول لا قبله**، وهو مبدأ TQ-INSTANT نفسه: من وصل
 * إلى هنا حسابه مفتوح ولوحته تعمل واشتراكه يفتح محتواه. وحجبه حتى
 * يملأ يعيد النموذج الذي جاء هذا الباب ليتجاوزه — وزر «لاحقا» موجود
 * لأن شاشة بلا مخرج تجعل من لا يريد أن يكتب جواله الآن يغلق الصفحة
 * كلها ولا يعود.
 */
$tq_u    = isset($tq_user) && is_array($tq_user) ? $tq_user : array();
$tq_gate = (string) (isset($tq_u['tq_gate']) ? $tq_u['tq_gate'] : 'student');
$tq_need = isset($tq_missing) && is_array($tq_missing) ? $tq_missing : array();
$tq_nx   = isset($tq_next) ? (string) $tq_next : '';

$tq_name = trim((string) (isset($tq_u['first_name']) ? $tq_u['first_name'] : ''));

$tq_h1   = $tq_name !== '' ? ('أهلا بك يا ' . $tq_name . '.') : 'أهلا بك في تقدر.';
$tq_lead = 'بقي سطر أو سطران ليعمل حسابك على وجهه.';
include __DIR__ . '/site/site_pagehero.php';
?>
<section class="section">
  <div class="shell shell--auth">
    <div class="auth-wrap">
      <div class="form-card">
        <a class="auth-brand" href="<?php echo base_url(); ?>" aria-label="منصة تقدر">
          <img src="<?php echo tq_site_asset('img/logo.webp'); ?>" alt="منصة تقدر" width="280" height="157">
        </a>

        <?php if ($tq_e = $this->session->flashdata('error_message')): ?>
          <p class="tq-flash tq-flash--err" role="alert"><?php echo html_escape($tq_e); ?></p>
        <?php endif; ?>
        <?php if ($tq_f = $this->session->flashdata('flash_message')): ?>
          <p class="tq-flash tq-flash--ok" role="status"><?php echo html_escape($tq_f); ?></p>
        <?php endif; ?>

        <form action="<?php echo site_url('account/complete/save'); ?>" method="post"
              id="social-complete" data-tq-auth novalidate>
          <?php echo tq_csrf(); ?>
          <?php if ($tq_nx !== ''): ?>
            <input type="hidden" name="tq_next" value="<?php echo html_escape($tq_nx); ?>">
          <?php endif; ?>

          <?php if (in_array('phone', $tq_need, true)): ?>
            <div class="form-cell">
              <?php echo tq_phone_field('phone', array(
                  'required' => true,
                  'value'    => (string) (isset($tq_u['phone']) ? $tq_u['phone'] : ''),
                  'id'       => 'tqPhoneComplete',
                  'hint'     => ($tq_gate === 'parent')
                      ? 'نراسلك عليه في تنبيهات أبنائك المهمة.'
                      : 'عليه تصلك تنبيهات حسابك عبر واتساب.',
              )); ?>
            </div>
          <?php endif; ?>

          <?php if ($tq_gate !== 'parent'): ?>
            <div class="form-grid">
              <?php if (in_array('age', $tq_need, true)): ?>
                <div class="form-cell">
                  <label class="form-field">
                    <svg aria-hidden="true"><use href="#i-user"></use></svg>
                    <span class="sr-only">العمر</span>
                    <input type="number" name="age" placeholder="العمر" min="5" max="99"
                           inputmode="numeric" autocomplete="off"
                           data-msg="اكتب عمرا بين 5 و99."
                           value="<?php echo (int) (isset($tq_u['age']) ? $tq_u['age'] : 0) > 0
                                          ? (int) $tq_u['age'] : ''; ?>">
                  </label>
                </div>
              <?php endif; ?>

              <?php /* الصف اختياري هنا كما هو اختياري في التسجيل — ولكنه
                       يعرض دائما: هو الذي يفتح الكتالوج على مرحلته، ومن
                       اختاره هنا لا يبحث عنه في الإعدادات بعد أسبوع. */ ?>
              <div class="form-cell">
                <label class="form-field">
                  <svg aria-hidden="true"><use href="#i-cap"></use></svg>
                  <span class="sr-only">الصف الدراسي</span>
                  <select name="grade_id">
                    <option value="">الصف الدراسي (اختياري)</option>
                    <?php
                    $tq_ci_g  = &get_instance();
                    $tq_cur_g = (int) (isset($tq_u['grade_id']) ? $tq_u['grade_id'] : 0);
                    foreach ($tq_ci_g->db->select('id, name_ar')->from('grades')->where('active', 1)
                                         ->order_by('`order`', 'ASC')->get()->result_array() as $tq_gr): ?>
                      <option value="<?php echo (int) $tq_gr['id']; ?>"
                        <?php echo $tq_cur_g === (int) $tq_gr['id'] ? ' selected' : ''; ?>><?php
                        echo html_escape($tq_gr['name_ar']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </label>
              </div>
            </div>
          <?php endif; ?>

          <button class="btn btn--primary btn--block" type="submit">احفظ وتابع</button>
        </form>

        <p class="form-alt">
          <a href="<?php echo $tq_nx !== '' ? base_url($tq_nx) : tq_home_for(tq_role()); ?>">لاحقا — خذني إلى لوحتي</a>
        </p>
      </div>

      <?php
      $tq_aside_h2 = 'حسابك جاهز';
      $tq_aside_points = array(
        array('i-cap',         'وفق المنهج السعودي', 'برامج لكل صف ومادة'),
        array('i-chart',       'متابعة تقدمك',       'تقارير دقيقة لك ولولي أمرك'),
        array('i-certificate', 'شهادات إتقان',       'تصدر عند اجتياز المحطات'),
      );
      include __DIR__ . '/site/site_authaside.php';
      ?>
    </div>
  </div>
</section>
