<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * TQ-SOCIAL · أزرار الدخول بجوجل وأبل — **شريك واحد لا ثلاث نسخ**.
 *
 * ثلاث شاشات تسأل السؤال نفسه: `/login` و`/sign_up` ونافذة الشراء
 * (`site_auth_modal.php`). وثلاث نسخ من زرين تفترق عند أول تعديل —
 * يضاف مزود ثالث في واحدة وينسى في اثنتين، فيقرأ من فتح صفحة التسجيل
 * بابا لا يجده في صفحة الدخول. وهي علة TQ-SOLD-NAME نفسها.
 *
 * **ولا سكربت فيه.** كل زر `<a>` يذهب إلى `/auth/<مزود>` والخادم يبني
 * الرحلة — فيعمل بلا جافاسكربت كما يعمل الكتالوج، ولا مكتبة من طرف
 * ثالث تحمل في صفحة الدخول لترسم زرا.
 *
 * **وبلا مفاتيح لا يطبع شيء**: `live()` ترد المزودين المضبوطين وحدهم،
 * فتبقى الصفحة كما كانت حرفا بحرف — وهي قاعدة تاب نفسها.
 *
 * @param string $tq_soc_gate   البوابة المختارة (طالب · ولي أمر)
 * @param string $tq_soc_next   الوجهة بعد الدخول (TQ-AUTH-NEXT)
 * @param string $tq_soc_lead   سطر الفاصل — «أو» افتراضا
 */
$tq_soc_ci = &get_instance();
$tq_soc_ci->load->model('taqdar_social_model', 'tq_soc_m');
$tq_soc_live = $tq_soc_ci->tq_soc_m->live();

if ($tq_soc_live):
    $tq_soc_gate = isset($tq_soc_gate) ? (string) $tq_soc_gate : 'student';
    $tq_soc_next = isset($tq_soc_next) ? (string) $tq_soc_next : '';
    $tq_soc_lead = isset($tq_soc_lead) ? (string) $tq_soc_lead : 'أو';

    /* الوجهة والبوابة تعلقان على الرابط لا على حقل: الزر رابط لا
       نموذج، والخادم يصفيهما (`tqs_safe_next()`) ويودعهما صف الحال. */
    $tq_soc_q = function ($gate, $next) {
        $q = array();
        if ($gate !== '' && $gate !== 'student') $q['as'] = $gate;
        if ($next !== '') $q['next'] = $next;
        return $q ? ('?' . http_build_query($q)) : '';
    };
    $tq_soc_suffix = $tq_soc_q($tq_soc_gate, $tq_soc_next);
?>
<div class="soc-auth" data-tq-social data-tq-social-gate="<?php echo html_escape($tq_soc_gate); ?>">
  <p class="soc-auth__or"><span><?php echo html_escape($tq_soc_lead); ?></span></p>

  <div class="soc-auth__row">
    <?php if (isset($tq_soc_live['google'])): ?>
      <a class="soc-btn soc-btn--google"
         href="<?php echo base_url('auth/google') . $tq_soc_suffix; ?>"
         rel="nofollow">
        <?php /* شعار جوجل بألوانه: الوسم يشترط ألا يعاد تلوينه ولا
                 يحور. وهو مضمن لا مرفوع — ملف رابع يحمله كل زائر
                 لصفحة الدخول ثمن يدفع عن أيقونة بحجم فقرة. */ ?>
        <svg class="soc-btn__i" viewBox="0 0 18 18" aria-hidden="true" focusable="false">
          <path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.92c1.7-1.57 2.68-3.88 2.68-6.62z"/>
          <path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.92-2.26c-.8.54-1.84.86-3.04.86-2.34 0-4.32-1.58-5.03-3.7H.96v2.33A9 9 0 0 0 9 18z"/>
          <path fill="#FBBC05" d="M3.97 10.72a5.4 5.4 0 0 1 0-3.44V4.95H.96a9 9 0 0 0 0 8.1l3.01-2.33z"/>
          <path fill="#EA4335" d="M9 3.58c1.32 0 2.5.45 3.44 1.35l2.58-2.58C13.46.9 11.43 0 9 0A9 9 0 0 0 .96 4.95l3.01 2.33C4.68 5.16 6.66 3.58 9 3.58z"/>
        </svg>
        <span>المتابعة بحساب جوجل</span>
      </a>
    <?php endif; ?>

    <?php if (isset($tq_soc_live['apple'])): ?>
      <a class="soc-btn soc-btn--apple"
         href="<?php echo base_url('auth/apple') . $tq_soc_suffix; ?>"
         rel="nofollow">
        <svg class="soc-btn__i" viewBox="0 0 18 18" aria-hidden="true" focusable="false">
          <path fill="currentColor" d="M12.63 9.57c.02-2.07 1.7-3.06 1.77-3.11-.96-1.41-2.46-1.6-3-1.62-1.28-.13-2.5.75-3.14.75-.65 0-1.65-.73-2.71-.71-1.4.02-2.68.81-3.4 2.06-1.45 2.51-.37 6.22 1.04 8.26.69 1 1.51 2.12 2.59 2.08 1.04-.04 1.43-.67 2.69-.67 1.25 0 1.61.67 2.71.65 1.12-.02 1.83-1.02 2.51-2.02.79-1.16 1.11-2.28 1.13-2.34-.02-.01-2.17-.83-2.19-3.33zM10.6 3.24c.57-.69.96-1.65.85-2.61-.82.03-1.82.55-2.41 1.24-.53.6-.99 1.58-.87 2.51.92.07 1.86-.46 2.43-1.14z"/>
        </svg>
        <span>المتابعة بحساب أبل</span>
      </a>
    <?php endif; ?>
  </div>

  <?php /* TQ-SOCIAL-TERMS — الموافقة تقال هنا لأن الزر يفتح حسابا.
           نموذج التسجيل يأخذها بمربع، وهذا الباب بلا نموذج — فلو لم
           تقل هنا لأنشئ حساب و`terms_accepted_at` مختوم بلا أن يقرأ
           صاحبه سطرا واحدا عما وافق عليه. */ ?>
  <p class="soc-auth__terms">
    بالمتابعة أنت توافق على
    <a href="<?php echo base_url('terms'); ?>">الشروط والأحكام</a>
    و<a href="<?php echo base_url('privacy'); ?>">سياسة الخصوصية</a>.
  </p>
</div>
<?php endif; ?>
