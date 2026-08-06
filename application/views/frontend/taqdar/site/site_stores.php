<?php
/**
 * شارتا المتجرين.
 *
 * التطبيق غير منشور بعد، فالشارتان **معطلتان بنص صريح** لا روابط تقود
 * إلى لا شيء: شارة تنقر فلا تفتح شيئا وعد مكسور، والتصريح بـ«قريبا»
 * أصدق منه. وحين ينشر التطبيق تضبط `app_store_url` و`google_play_url`
 * من اللوحة فتصيران رابطين حيين بلا تعديل سطر.
 *
 * و`direction:ltr` على الـsvg ضروري: `<text>` يرث اتجاه الصفحة، فيصير `x`
 * نقطة نهاية النص في RTL فينزلق خارج الإطار.
 */
$tq_stores = array(
    'app_store_url' => array(
        'label' => 'App Store',
        'aria'  => 'حمل التطبيق من App Store',
        'svg'   => '<svg viewBox="0 0 120 40" aria-hidden="true">'
            . '<rect width="120" height="40" rx="7" fill="#000"/>'
            . '<rect x=".5" y=".5" width="119" height="39" rx="6.5" fill="none" stroke="#A6A6A6"/>'
            . '<path fill="#fff" d="M24.8 20.3c0-2 1.6-3 1.7-3.1-.9-1.4-2.4-1.6-2.9-1.6-1.2-.1-2.4.7-3'
            . ' .7-.6 0-1.6-.7-2.6-.7-1.3 0-2.6.8-3.2 2-1.4 2.4-.4 6 1 8 .7 1 1.5 2 2.5 2'
            . ' 1 0 1.4-.6 2.6-.6s1.5.6 2.6.6c1.1 0 1.8-1 2.4-1.9.8-1.1 1.1-2.2 1.1-2.3'
            . ' 0 0-2.2-.8-2.2-3.1Zm-2-5.7c.5-.7.9-1.6.8-2.6-.8 0-1.8.5-2.4 1.2-.5.6-1 1.6-.9'
            . ' 2.5.9.1 1.9-.4 2.5-1.1Z"/>'
            . '<text x="36" y="15" fill="#fff" font-family="Helvetica,Arial" font-size="7.5">Download on the</text>'
            . '<text x="36" y="29" fill="#fff" font-family="Helvetica,Arial" font-size="15" font-weight="600">App Store</text>'
            . '</svg>',
    ),
    'google_play_url' => array(
        'label' => 'Google Play',
        'aria'  => 'حمل التطبيق من Google Play',
        'svg'   => '<svg viewBox="0 0 120 40" aria-hidden="true">'
            . '<rect width="120" height="40" rx="7" fill="#000"/>'
            . '<rect x=".5" y=".5" width="119" height="39" rx="6.5" fill="none" stroke="#A6A6A6"/>'
            . '<path fill="#00D3FF" d="m13.5 11.4 9.3 9.3-9.3 9.3a1.6 1.6 0 0 1-.6-1.3V12.7c0-.5.2-1 .6-1.3Z"/>'
            . '<path fill="#FFCE00" d="m26.4 17.2 3.5 2c.8.5.8 1.6 0 2.1l-3.5 2-3.6-3.6Z"/>'
            . '<path fill="#FF3A44" d="m13.5 11.4 12.9 5.8-3.6 3.5Z"/>'
            . '<path fill="#00E676" d="m22.8 20.7 3.6 3.6-12.9 5.7Z"/>'
            . '<text x="38" y="15" fill="#fff" font-family="Helvetica,Arial" font-size="7.5">GET IT ON</text>'
            . '<text x="38" y="29" fill="#fff" font-family="Helvetica,Arial" font-size="14" font-weight="600">Google Play</text>'
            . '</svg>',
    ),
);

$tq_any_live = false;
foreach ($tq_stores as $tq_key => $tq_s) {
    $tq_url = trim((string) get_settings($tq_key));
    if ($tq_url !== '') {
        $tq_any_live = true;
        echo '<a href="' . html_escape($tq_url) . '" target="_blank" rel="noopener noreferrer"'
           . ' aria-label="' . $tq_s['aria'] . '">' . $tq_s['svg'] . '</a>';
    } else {
        echo '<span class="store-soon" role="img" aria-label="' . $tq_s['label'] . ' — قريبا">'
           . $tq_s['svg'] . '</span>';
    }
}

if (!$tq_any_live) {
    echo '<span class="store-note">التطبيق قريبا</span>';
}
