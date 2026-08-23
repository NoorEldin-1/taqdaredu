<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * أنماط شاشة الإشعارات — مشتركة بين بوابتي الطالب والمعلم.
 *
 * استخرجت حين احتاج المعلم شاشة إشعارات: صف الإشعار شكل واحد في المنصة،
 * ونسختان منه تتباعدان عند أول تعديل — فتقرأ النقطة غير المقروءة بلونين.
 * والحارس يمنع طباعتها مرتين.
 */
if (defined('TQ_NOTIF_STYLES_DONE')) return;
define('TQ_NOTIF_STYLES_DONE', true);
?>
<style>
/* الإشعارات — صف واحد مقروء: نقطة ← عنوان وسطران ← وقت ← أيقونة النوع. */
.tq-icon-box[class*='tq-pastel--'] { color: var(--tq-pastel-ink); }
.tq-tab[aria-current='page'] { color: var(--tq-navy); border-block-end-color: var(--tq-navy); font-weight: 700; }
.tq-tab { display: inline-flex; align-items: center; gap: var(--tq-space-xs); }
.tq-tab:hover { text-decoration: none; }
.tq-tab__n { padding: 0 var(--tq-space-xs); border-radius: var(--tq-radius-pill);
  background: var(--tq-navyWash); color: var(--tq-navy); font: var(--tq-type-numeralSm);
  unicode-bidi: isolate; direction: ltr; }

.tq-daygroup { margin-block-end: var(--tq-space-xl); }
.tq-daygroup__label { font: var(--tq-type-eyebrow); color: var(--tq-text2); letter-spacing: .02em;
  margin-block-end: var(--tq-space-m); display: block; }

.tq-notif { display: grid; grid-template-columns: 10px minmax(0, 1fr) auto auto; gap: var(--tq-space-m);
  align-items: start; padding: var(--tq-space-l); border-radius: var(--tq-radius-medium); }
.tq-notif:hover { background: var(--tq-navyWash); text-decoration: none; }

/* TQ-NOTIF-READ — الصف كان `div` صامتا: يضغطه صاحبه فلا يقرأ ولا ينتقل،
   وتبقى النقطة الزرقاء مكانها. و«تحديد الكل كمقروء» وحده كان يعمل — وهو
   إما الكل أو لا شيء. فصار غير المقروء زرا يرسل نفسه، والزر عنصر يعرفه
   المتصفح وقارئ الشاشة ولوحة المفاتيح بلا سطر جافاسكربت.
   والشكل من `.tq-notif` وحده: المتصفح يورث الزر خلفية وحدا وخطا ومحاذاة
   وسط، وكلها تنزع هنا حتى يخرج الصف كما كان حرفا بحرف. */
button.tq-notif {
  appearance: none; -webkit-appearance: none;
  background: none; border: 0; margin: 0;
  font: inherit; font-family: inherit; color: inherit;
  text-align: start; inline-size: 100%; cursor: pointer;
}
button.tq-notif:focus-visible,
a.tq-notif:focus-visible { outline: 2px solid var(--tq-focusRing); outline-offset: -2px; }
.tq-notif + .tq-notif { border-block-start: 1px solid var(--tq-line); border-start-start-radius: 0; border-start-end-radius: 0; }
.tq-notif__dot { inline-size: 10px; block-size: 10px; border-radius: var(--tq-radius-pill); margin-block-start: 6px; background: transparent; }
.tq-notif--unread .tq-notif__dot { background: var(--tq-navy); }
.tq-notif__title { color: var(--tq-navy); font: var(--tq-type-bodyStrong); display: block; margin-block-end: var(--tq-space-xs); }
.tq-notif__line { color: var(--tq-text2); font: var(--tq-type-caption); display: block; }
.tq-notif__time { color: var(--tq-text2); font: var(--tq-type-micro); white-space: nowrap; }

.tq-kindrow { display: flex; align-items: center; justify-content: space-between; gap: var(--tq-space-m);
  padding: var(--tq-space-s) 0; }
.tq-prefrow { display: flex; align-items: center; justify-content: space-between; gap: var(--tq-space-m); padding: var(--tq-space-s) 0; }
.tq-chanpair { display: inline-flex; gap: var(--tq-space-xs); }

@media (max-width: 639.98px) {
  .tq-notif { grid-template-columns: 10px minmax(0, 1fr) auto; }
  .tq-notif__time { grid-column: 2 / -1; }
}
</style>
