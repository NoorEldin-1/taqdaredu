<?php
/**
 * لوحة المرشحات.
 *
 * جزء مستقل لأنه **يستبدل مع كل تغيير**: عدادات الخيارات تحسب على
 * النتيجة الحالية، فلوحة ثابتة تقول «الكتب (٨)» فوق نتيجة فيها كتاب
 * واحد مرشد كاذب — والزائر يضغطها فيرى واحدا فيظن العطب في الموقع.
 *
 * وكل خيار **رابط** لا زر: يعمل بلا جافاسكربت، ويفتح في تبويب جديد،
 * ويحفظ في المفضلة. والسكربت يعترض النقر ويجلب الجزء بدل الصفحة —
 * فالتحسين يزيد ولا يشترط.
 *
 * `$tq_f` المرشحات القائمة · `$tq_res` نتيجة البحث بعداداتها.
 */
$tq_facets = $tq_res['facets'];
$tq_prices = array(
    ''     => 'الكل',
    'free' => 'مجاني',
    'paid' => 'مدفوع',
);
?>
<div class="cfilters" data-tq-filters>

  <div class="cfilters__head">
    <h2 class="cfilters__title">
      <svg aria-hidden="true"><use href="#i-filter"></use></svg>تصفية النتائج
    </h2>
<?php if ($tq_res['active']): ?>
    <a class="cfilters__clear" href="<?php echo html_escape(base_url('catalog')); ?>" data-tq-cat-link>
      مسح الكل
    </a>
<?php endif; ?>
  </div>

<?php
/* النوع أولا وبشكل مختلف: هو السؤال الأول («ماذا تريد؟») لا مرشحا
   من جملة مرشحات، فيعرض رقائق تقرأ دفعة لا قائمة تحتاج فتحا. */
if (isset($tq_facets['type'])): ?>
  <div class="cfil cfil--chips">
    <p class="cfil__label">النوع</p>
    <div class="cchips">
      <a class="cchip<?php echo $tq_f['type'] ? '' : ' is-on'; ?>"
         href="<?php echo html_escape(tqs_cat_query($tq_f, array('type' => null))); ?>" data-tq-cat-link>
        الكل
      </a>
<?php foreach ($tq_facets['type']['items'] as $tq_i): ?>
      <a class="cchip<?php echo $tq_i['on'] ? ' is-on' : ''; ?>"
         href="<?php echo html_escape(tqs_cat_toggle($tq_f, 'type', $tq_i['value'])); ?>"
         data-tq-cat-link aria-pressed="<?php echo $tq_i['on'] ? 'true' : 'false'; ?>">
        <?php echo html_escape($tq_i['label']); ?>
        <b class="tq-ltr"><?php echo (int) $tq_i['n']; ?></b>
      </a>
<?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<?php
/* البقية قوائم مطوية — والأولان مفتوحان: القسم والصف أكثر ما يسأل
   عنه ولي الأمر، وما بعدهما يفتحه من يحتاجه. */
$tq_n = 0;
foreach ($tq_facets as $tq_key => $tq_g):
    if ($tq_key === 'type') continue;
    $tq_n++;
    $tq_hit = false;
    foreach ($tq_g['items'] as $tq_i) { if ($tq_i['on']) { $tq_hit = true; break; } }
    /* المفتوح: الأولان، وأي مرشح فيه خيار مضغوط — طي مرشح مفعل يخفي
       سبب قلة النتائج عن صاحبه. */
    $tq_open = ($tq_n <= 2 || $tq_hit);
?>
  <details class="cfil"<?php echo $tq_open ? ' open' : ''; ?>>
    <summary class="cfil__sum">
      <svg class="cfil__ico" aria-hidden="true"><use href="#<?php echo html_escape($tq_g['icon']); ?>"></use></svg>
      <span><?php echo html_escape($tq_g['label']); ?></span>
<?php if ($tq_hit): ?>      <i class="cfil__dot" aria-hidden="true"></i>
<?php endif; ?>
      <svg class="cfil__chev" aria-hidden="true"><use href="#i-chevron"></use></svg>
    </summary>
    <div class="cfil__list">
<?php foreach ($tq_g['items'] as $tq_i): ?>
      <a class="copt<?php echo $tq_i['on'] ? ' is-on' : ''; ?>"
         href="<?php echo html_escape(tqs_cat_toggle($tq_f, $tq_key, $tq_i['value'])); ?>"
         data-tq-cat-link aria-pressed="<?php echo $tq_i['on'] ? 'true' : 'false'; ?>">
        <span class="copt__box" aria-hidden="true"><svg><use href="#i-check"></use></svg></span>
        <span class="copt__t"><?php echo html_escape($tq_i['label']); ?></span>
        <span class="copt__n tq-ltr"><?php echo (int) $tq_i['n']; ?></span>
      </a>
<?php endforeach; ?>
    </div>
  </details>
<?php endforeach; ?>

  <?php /* السعر مرشح واحد لا متعدد: «مجاني ومدفوع» معا هو «الكل». */ ?>
  <div class="cfil cfil--chips">
    <p class="cfil__label">السعر</p>
    <div class="cchips">
<?php foreach ($tq_prices as $tq_v => $tq_l): ?>
      <a class="cchip<?php echo ((string) $tq_f['price'] === (string) $tq_v) ? ' is-on' : ''; ?>"
         href="<?php echo html_escape(tqs_cat_query($tq_f, array('price' => ($tq_v === '') ? null : $tq_v))); ?>"
         data-tq-cat-link><?php echo html_escape($tq_l); ?></a>
<?php endforeach; ?>
    </div>
  </div>

  <?php /* البرنامج لا سعر له: يفتح بالباقة، فمرشح السعر يسقطه من
           الوجهين — وقول ذلك هنا أهون من أن يبحث الزائر عن سببه. */ ?>
  <p class="cfilters__note">
    <svg aria-hidden="true"><use href="#i-shield"></use></svg>
    البرامج تفتح بالاشتراك في باقة، فلا تظهر تحت «مجاني» ولا «مدفوع».
  </p>
</div>
