<?php
/**
 * TQ-I18N — مبدل اللغة، قالب واحد بجلدين.
 *
 * يركب في ترويسة البوابات الثلاث وفي ترويسة اللوحة. وقالبان يفترقان عند
 * أول تعديل: تضاف لغة ثالثة فتظهر عند الطالب وتغيب عن المسؤول.
 *
 * المتغيرات:
 *   $skin   'portal' (أصناف tq-*) أو 'admin' (أصناف tqa-*) — افتراضه portal
 *
 * **وهو نموذج POST لا رابط**: تبديل اللغة كتابة في تفضيل الحساب، ورابط GET
 * يغيره يعني صورة في بريد تقلب لغة من يفتحه. ولذلك يحمل رمز CSRF.
 *
 * **ويعمل بلا جافاسكربت**: زر لكل لغة، والجاري منها معلم ومعطل. ولا `select`
 * يحتاج `onchange` — قائمة لغتين في `select` تحتاج نقرتين ونصا لا يقرأ حتى
 * تفتح، وزران متجاوران يقرآن قبل أن ينقرا.
 */
$skin = isset($skin) && $skin === 'admin' ? 'admin' : 'portal';
$p    = $skin === 'admin' ? 'tqa' : 'tq';

$tq_ls_current = tq_lang();
$tq_ls_all     = tq_languages();

/* الوجهة: المسار الجاري بمعاملاته كما هو، فيعود من بدل إلى **مكانه** لا
   إلى الرئيسية. و`uri_string()` بلا معاملات الاستعلام — وصفحة الكتالوج
   كلها معاملات (TQ-CATALOG)، فمن بدل لغته فوق نتيجة مرشحة كان يفقد ترشيحه. */
$tq_ls_back = '/' . ltrim(uri_string(), '/');
$tq_ls_qs   = (string) ($_SERVER['QUERY_STRING'] ?? '');
if ($tq_ls_qs !== '') $tq_ls_back .= '?' . $tq_ls_qs;
?>
<form class="<?php echo $p; ?>-langsw" method="post"
      action="<?php echo base_url('language/set'); ?>"
      aria-label="<?php echo te('لغة الواجهة'); ?>">
    <?php echo tq_csrf(); ?>
    <input type="hidden" name="back" value="<?php echo html_escape($tq_ls_back); ?>">

    <?php foreach ($tq_ls_all as $tq_ls_code => $tq_ls_meta):
        $tq_ls_on = ($tq_ls_code === $tq_ls_current); ?>
        <button class="<?php echo $p; ?>-langsw__opt<?php echo $tq_ls_on ? ' is-on' : ''; ?>"
                type="submit" name="lang" value="<?php echo html_escape($tq_ls_code); ?>"
                lang="<?php echo html_escape($tq_ls_meta['iso']); ?>"
                <?php echo $tq_ls_on ? 'aria-current="true" disabled' : ''; ?>
                title="<?php echo html_escape($tq_ls_meta['label']); ?>">
            <span aria-hidden="true"><?php echo html_escape($tq_ls_meta['short']); ?></span>
            <span class="<?php echo $p; ?>-sr"><?php echo html_escape($tq_ls_meta['label']); ?></span>
        </button>
    <?php endforeach; ?>
</form>
