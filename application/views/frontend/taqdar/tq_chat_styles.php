<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * أنماط شاشة المراسلة — مشتركة بين صندوق الطالب وصندوق المعلم.
 *
 * كانت هذه الأنماط مكتوبة داخل `tq_messages.php` وحدها. ولما احتاج المعلم
 * صندوقا كان الخيار بين نسخها أو استخراجها، والنسخ يعني فقاعتين تتباعدان:
 * يعدل لون الفقاعة في شاشة فيبقى القديم في الأخرى، فيصير للمنصة شكلان
 * للمحادثة الواحدة. فاستخرجت هنا، ولا تكتب مرتين.
 *
 * والحارس يمنع طباعتها مرتين لو ضم الملف من شاشة تضم أخرى.
 *
 * وفقاعة «أنا» تعرف بالدور لا بالجهة: صاحب الشاشة إلى البداية دائما،
 * ومحدثه إلى النهاية — فالمعلم يرى نفسه حيث يرى الطالب نفسه.
 */
if (!defined('TQ_CHAT_STYLES_DONE')) {
    define('TQ_CHAT_STYLES_DONE', true);
    ?>
<style>
/* المراسلة — عمودان: القائمة · الحوار، ومعلومات المحادثة في الشريط الجانبي. */
.tq-icon-box[class*='tq-pastel--'] { color: var(--tq-pastel-ink); }
/* تبويبات القائمة روابط حقيقية تعمل بلا جافاسكربت، فحالتها aria-current. */
.tq-tab[aria-current='page'] { color: var(--tq-navy); border-block-end-color: var(--tq-navy); font-weight: 700; }
.tq-tab { display: inline-flex; align-items: center; gap: var(--tq-space-xs); }
.tq-tab:hover { text-decoration: none; }

.tq-chatwrap { display: grid; grid-template-columns: 320px minmax(0, 1fr); gap: var(--tq-space-xl); align-items: start; }

.tq-convlist { background: var(--tq-surface); border-radius: var(--tq-radius-card); box-shadow: var(--tq-shadow-soft); padding: var(--tq-space-l); }
.tq-convsearch { position: relative; margin-block: var(--tq-space-m) var(--tq-space-l); }
.tq-conv { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: var(--tq-space-m);
  padding: var(--tq-space-m); border-radius: var(--tq-radius-medium); align-items: center; }
.tq-conv:hover { background: var(--tq-navyWash); text-decoration: none; }
.tq-conv[aria-current='page'] { background: var(--tq-sky-fill); }
.tq-conv__ava { position: relative; }
.tq-conv__on { position: absolute; inset-block-end: 0; inset-inline-end: 0; inline-size: 10px; block-size: 10px;
  border-radius: var(--tq-radius-pill); background: var(--tq-teal); border: 2px solid var(--tq-surface); }
.tq-conv__name { color: var(--tq-navy); font: var(--tq-type-bodyStrong); display: block;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tq-conv__last { color: var(--tq-text2); font: var(--tq-type-caption); display: block;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tq-conv__meta { display: grid; justify-items: end; gap: var(--tq-space-xs); }
.tq-conv__count { min-inline-size: 22px; padding: 0 var(--tq-space-xs); background: var(--tq-actionPrimary); color: var(--tq-onAction);
  border-radius: var(--tq-radius-pill); font: var(--tq-type-numeralSm); text-align: center; unicode-bidi: isolate; direction: ltr; }

.tq-thread { background: var(--tq-surface); border-radius: var(--tq-radius-card); box-shadow: var(--tq-shadow-soft);
  display: flex; flex-direction: column; min-block-size: 640px; }
.tq-thread__head { display: flex; align-items: center; gap: var(--tq-space-m);
  padding: var(--tq-space-l) var(--tq-space-xl); border-block-end: 1px solid var(--tq-line); }
.tq-thread__pin { display: flex; align-items: center; gap: var(--tq-space-m);
  margin: var(--tq-space-l) var(--tq-space-xl) 0; padding: var(--tq-space-m) var(--tq-space-l);
  background: var(--tq-sand-fill); border-radius: var(--tq-radius-medium); color: var(--tq-navy); font: var(--tq-type-caption); }
.tq-thread__body { flex: 1; padding: var(--tq-space-xl); display: flex; flex-direction: column; gap: var(--tq-space-l); overflow-y: auto; }
.tq-thread__foot { border-block-start: 1px solid var(--tq-line); padding: var(--tq-space-l) var(--tq-space-xl); }

.tq-daysep { display: grid; place-items: center; }
.tq-daysep span { background: var(--tq-navyWash); color: var(--tq-navy); border-radius: var(--tq-radius-pill);
  padding: var(--tq-space-xs) var(--tq-space-m); font: var(--tq-type-micro); }

.tq-bubble { max-inline-size: 62%; padding: var(--tq-space-m) var(--tq-space-l); border-radius: var(--tq-radius-medium); }
/* رسائل صاحب الشاشة: تعبئة باستيل ومحاذاة start، ونصها كحلي لا حبر العائلة. */
.tq-bubble--me { align-self: flex-start; background: var(--tq-sky-fill); color: var(--tq-navy); }
/* رسائل محدثه: بيضاء بحد. */
.tq-bubble--them { align-self: flex-end; background: var(--tq-surface); border: 1px solid var(--tq-line); color: var(--tq-text); }
.tq-bubble__meta { display: flex; align-items: center; gap: var(--tq-space-xs); margin-block-start: var(--tq-space-xs); }
.tq-bubble__time { font: var(--tq-type-micro); color: var(--tq-text2); }
.tq-bubble__seen { color: var(--tq-teal); display: inline-flex; }
.tq-bubble__seen--sent { color: var(--tq-text3); }

.tq-attach { display: flex; align-items: center; gap: var(--tq-space-m); margin-block-start: var(--tq-space-s);
  padding: var(--tq-space-m); background: var(--tq-surface); border: 1px solid var(--tq-line); border-radius: var(--tq-radius-medium); }

.tq-composer { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: var(--tq-space-m); align-items: center; }
.tq-composer__send { inline-size: var(--tq-touch-min); block-size: var(--tq-touch-min); border-radius: var(--tq-radius-pill);
  background: var(--tq-actionPrimary); color: var(--tq-onAction); display: grid; place-items: center; flex: none; }
.tq-composer__send:hover { background: var(--tq-navySoft); }
html[dir='rtl'] .tq-composer__send svg { transform: scaleX(-1); }

.tq-inforow { display: flex; align-items: center; justify-content: space-between; gap: var(--tq-space-m);
  padding: var(--tq-space-m) 0; color: var(--tq-text2); font: var(--tq-type-caption); }
.tq-inforow + .tq-inforow { border-block-start: 1px solid var(--tq-line); }
.tq-inforow a, .tq-inforow button { color: var(--tq-text2); }

.tq-danger-link { color: var(--tq-danger); display: inline-flex; align-items: center; gap: var(--tq-space-s); }

@media (max-width: 1023.98px) { .tq-chatwrap { grid-template-columns: minmax(0, 1fr); } .tq-thread { min-block-size: 480px; } }
</style>
    <?php
}

/* ---------------------------------------------------------------------
   دوال عرض المحادثة — واحدة للشاشتين، فلا يختلف شكل الساعة بينهما.
   --------------------------------------------------------------------- */

if (!function_exists('tq_chat_photo')) {
    /** صورة المحدث، أو الصورة البديلة إن حذف حسابه. */
    function tq_chat_photo($person)
    {
        return tqs_person_img(isset($person['image']) ? $person['image'] : '');
    }
}

if (!function_exists('tq_chat_name')) {
    /** اسم المحدث — والحساب المحذوف يقال عنه ذلك، ولا يترك سطرا فارغا. */
    function tq_chat_name($person)
    {
        if (empty($person)) return t('مستخدم محذوف');
        return trim($person['first_name'] . ' ' . $person['last_name']) ?: t('مستخدم');
    }
}

if (!function_exists('tq_chat_clock')) {
    /** «٣:٤٥ م» — الرقم معزول والفترة عربية. */
    function tq_chat_clock($ts)
    {
        return tq_num(date('g:i', (int) $ts), 'tq-num--sm') . ' ' . ((int) date('G', (int) $ts) < 12 ? t('ص') : t('م'));
    }
}

if (!function_exists('tq_chat_daylabel')) {
    /** فاصل اليوم بين الرسائل: «اليوم» و«أمس» ثم اسم اليوم وتاريخه. */
    function tq_chat_daylabel($ts)
    {
        $d = strtotime('today') - strtotime(date('Y-m-d', (int) $ts));
        if ($d === 0)     return t('اليوم');
        if ($d === 86400) return t('أمس');
        $days   = [t('الأحد'), t('الاثنين'), t('الثلاثاء'), t('الأربعاء'), t('الخميس'), t('الجمعة'), t('السبت')];
        $months = [t('يناير'), t('فبراير'), t('مارس'), t('أبريل'), t('مايو'), t('يونيو'), t('يوليو'), t('أغسطس'), t('سبتمبر'), t('أكتوبر'), t('نوفمبر'), t('ديسمبر')];
        return $days[(int) date('w', $ts)] . t('،') . TQ_LRI . date('j', $ts) . TQ_PDI . ' ' . $months[(int) date('n', $ts) - 1];
    }
}
