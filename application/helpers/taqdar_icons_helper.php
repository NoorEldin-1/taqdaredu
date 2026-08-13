<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * أيقونات تقدر — SVG مضمن، بلا حزمة أيقونات وبلا جلب من الشبكة.
 * كلها مرسومة بـ currentColor فترث لون السياق، ومعلمة aria-hidden لأن
 * المعنى يحمله النص المجاور لا الأيقونة — واللون وحده لا يحمل معنى.
 *
 * الأيقونات الاتجاهية (السهم) تنعكس مع الاتجاه؛ والزمنية والتشغيلية لا تنعكس.
 */
if (!function_exists('tq_icon')) {
    function tq_icon($name, $size = 20)
    {
        static $paths = [
            'home'        => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20a1 1 0 0 0 1 1h4v-6h4v6h4a1 1 0 0 0 1-1V9.5"/>',
            'book'        => '<path d="M4 4.5A1.5 1.5 0 0 1 5.5 3H19v16H5.5A1.5 1.5 0 0 0 4 20.5z"/><path d="M4 17.5A1.5 1.5 0 0 1 5.5 16H19"/>',
            'clipboard'   => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V3h6v1"/><path d="M9 10h6M9 14h4"/>',
            'check-badge' => '<path d="m12 3 2.2 1.6 2.7-.2 1 2.5 2.3 1.4-.6 2.7.6 2.7-2.3 1.4-1 2.5-2.7-.2L12 21l-2.2-1.6-2.7.2-1-2.5-2.3-1.4.6-2.7-.6-2.7 2.3-1.4 1-2.5 2.7.2z"/><path d="m9 12 2 2 4-4"/>',
            'video'       => '<rect x="3" y="6" width="12" height="12" rx="2"/><path d="m15 10 6-3v10l-6-3z"/>',
            'chart'       => '<path d="M4 20h16"/><path d="M7 20v-6M12 20V8M17 20v-9"/>',
            'folder'      => '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
            'heart'       => '<path d="M12 20s-7-4.4-7-9a4 4 0 0 1 7-2.6A4 4 0 0 1 19 11c0 4.6-7 9-7 9z"/>',
            'chat'        => '<path d="M20 15a2 2 0 0 1-2 2H8l-4 3V6a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2z"/>',
            'bell'        => '<path d="M18 15V10a6 6 0 1 0-12 0v5l-1.5 2.5h15z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
            'calendar'    => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
            'award'       => '<circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 7 5-2.5 5 2.5-1.5-7"/>',
            'cog'         => '<circle cx="12" cy="12" r="3.2"/><path d="M12 3v2.2M12 18.8V21M3 12h2.2M18.8 12H21M5.6 5.6l1.6 1.6M16.8 16.8l1.6 1.6M18.4 5.6l-1.6 1.6M7.2 16.8l-1.6 1.6"/>',
            'users'       => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M16 5.5a3 3 0 0 1 0 5.6M17 14.6a5.4 5.4 0 0 1 4 5.4"/>',
            'upload'      => '<path d="M12 16V4"/><path d="m8 8 4-4 4 4"/><path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/>',
            'help'        => '<circle cx="12" cy="12" r="9"/><path d="M9.6 9.4A2.5 2.5 0 0 1 14.4 10c0 1.7-2.4 2-2.4 3.4"/><path d="M12 17.2v.01"/>',
            'wallet'      => '<path d="M3 8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M19 11h2v4h-2a2 2 0 0 1 0-4z"/>',
            'grid'        => '<rect x="4" y="4" width="7" height="7" rx="1.6"/><rect x="13" y="4" width="7" height="7" rx="1.6"/><rect x="4" y="13" width="7" height="7" rx="1.6"/><rect x="13" y="13" width="7" height="7" rx="1.6"/>',
            'menu'        => '<path d="M4 7h16M4 12h16M4 17h16"/>',
            'moon'        => '<path d="M20 14.5A8 8 0 0 1 9.5 4 8 8 0 1 0 20 14.5z"/>',
            'search'      => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
            'play'        => '<path d="M8 5.5v13l11-6.5z"/>',
            'clock'       => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5.2l3.2 2"/>',
            'file'        => '<path d="M13 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V9z"/><path d="M13 3v6h6"/>',
            'download'    => '<path d="M12 4v12"/><path d="m8 12 4 4 4-4"/><path d="M4 20h16"/>',
            'star'        => '<path d="m12 4 2.5 5 5.5.8-4 3.9.9 5.5L12 16.6 7.1 19.2l.9-5.5-4-3.9L9.5 9z"/>',
            'flame'       => '<path d="M12 21a6 6 0 0 0 6-6c0-4-3-5.5-3.5-9-2 1.5-3 3.5-3 5.5C10 9 8.5 8 8.5 8A7.7 7.7 0 0 0 6 15a6 6 0 0 0 6 6z"/>',
            'lock'        => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
            'target'      => '<circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="1"/>',
            'plus'        => '<path d="M12 5v14M5 12h14"/>',
            'check'       => '<path d="m5 12.5 4.5 4.5L19 7"/>',
            'x'           => '<path d="M6 6l12 12M18 6 6 18"/>',
            // اتجاهيتان: ترسمان بمنطق LTR وتنعكسان في RTL عبر .tq-dir-icon
            'chev-prev'   => '<path d="m14.5 6-6 6 6 6"/>',
            'chev-next'   => '<path d="m9.5 6 6 6-6 6"/>',

            /* --- أيقونات اللوحة ---
               أضيفت لأن `spec()` كانت تسمي أيقونات لا وجود لها في هذه
               القائمة، و`tq_icon()` ترجع نصا فارغا للمجهول: فكان كل عنوان
               وحدة في اللوحة يرسم مربعا فارغا مكان أيقونته بلا خطأ ينبه. */
            'meter'       => '<path d="M4 18a8 8 0 1 1 16 0"/><path d="m12 14 4-4"/><circle cx="12" cy="18" r="1.4"/>',
            'graduation'  => '<path d="m2 8 10-4 10 4-10 4z"/><path d="M6 10.5V16c0 1.7 2.7 3 6 3s6-1.3 6-3v-5.5"/>',
            'crosshair'   => '<circle cx="12" cy="12" r="7.5"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4"/>',
            'shield'      => '<path d="M12 3l7 3v5.5c0 4.4-3 8-7 9.5-4-1.5-7-5.1-7-9.5V6z"/><path d="m9 12 2 2 4-4"/>',
            'receipt'     => '<path d="M6 3h12v18l-3-1.7-3 1.7-3-1.7L6 21z"/><path d="M9.5 8h5M9.5 12h5"/>',
            'file-text'   => '<path d="M13 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V9z"/><path d="M13 3v6h6"/><path d="M9 13h6M9 17h4"/>',
            'circle'      => '<circle cx="12" cy="12" r="8.5"/>',
            'bank'        => '<path d="M3 9.5 12 4l9 5.5"/><path d="M5 10v8M9.5 10v8M14.5 10v8M19 10v8"/><path d="M3 21h18"/>',
            'link'        => '<path d="M10 13.5a3.5 3.5 0 0 0 5 0l3-3a3.5 3.5 0 0 0-5-5l-1.2 1.2"/><path d="M14 10.5a3.5 3.5 0 0 0-5 0l-3 3a3.5 3.5 0 0 0 5 5l1.2-1.2"/>',
            'logout'      => '<path d="M14 6V5a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1"/><path d="M10 12h11"/><path d="m18 9 3 3-3 3"/>',
            'edit'        => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4z"/>',
            'trash'       => '<path d="M4 7h16"/><path d="M9 7V5h6v2"/><path d="M6 7v13a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V7"/><path d="M10 11v6M14 11v6"/>',
            'alert'       => '<path d="M12 4 2.5 20h19z"/><path d="M12 10v4"/><path d="M12 17.2v.01"/>',
            'layers'      => '<path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/>',
            'globe'       => '<circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17"/><path d="M12 3.5c2.5 2.6 2.5 14.4 0 17-2.5-2.6-2.5-14.4 0-17z"/>',
            'mail'        => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 6.5 8.5 6 8.5-6"/>',
            'card'        => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/><path d="M7 15h3"/>',
            'trophy'      => '<path d="M8 4h8v5a4 4 0 0 1-8 0z"/><path d="M8 5.5H5.5A2.5 2.5 0 0 0 8 10M16 5.5h2.5A2.5 2.5 0 0 1 16 10"/><path d="M12 13v3.5M9 20h6"/>',
            'import'      => '<path d="M12 4v10"/><path d="m8 10 4 4 4-4"/><path d="M4 17v2a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-2"/>',
            'refresh'     => '<path d="M4 12a8 8 0 0 1 13.7-5.6L20 8"/><path d="M20 4v4h-4"/><path d="M20 12a8 8 0 0 1-13.7 5.6L4 16"/><path d="M4 20v-4h4"/>',
            'eye'         => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>',
            'send'        => '<path d="M21 3 10.5 13.5"/><path d="M21 3l-6.5 18-4-8-8-4z"/>',
            'flag'        => '<path d="M5 21V4"/><path d="M5 5h11l-1.6 3.5L16 12H5z"/>',

            /* --- أيقونات شاشات النظام ---
               أضيفت مع إعادة كتابة وحدات الإعدادات: كانت تلك الشاشات
               تستدعي أيقونات `mdi-*` من حزمة القالب، وهي خارج هذه القائمة
               فتخرج مربعا فارغا أو رمزا من عائلة أخرى بجانب أيقونات تقدر. */
            'image'       => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10" r="1.6"/><path d="m5 17 4.5-4.5L13 16l2.5-2.5L21 19"/>',
            'key'         => '<circle cx="8" cy="12" r="4"/><path d="M12 12h9"/><path d="M17 12v3.5M20 12v2.5"/>',
            'filter'      => '<path d="M3.5 5h17l-6.5 7.5V20l-4-2.2v-5.3z"/>',
            'external'    => '<path d="M14 4h6v6"/><path d="M20 4 11 13"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>',
            'translate'   => '<path d="M3.5 6h9"/><path d="M8 4v2c0 4-1.8 7.2-4.5 9"/><path d="M6 11.5c1.4 2.6 3.4 4.4 6 5.5"/><path d="m13 21 4-10 4 10"/><path d="M14.4 18h5.2"/>',
            'copy'        => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M15 9V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h4"/>',
            'money'       => '<rect x="2.5" y="6" width="19" height="12" rx="2"/><circle cx="12" cy="12" r="2.8"/><path d="M6 10v4M18 10v4"/>',
        ];

        if (!isset($paths[$name])) {
            return '';
        }

        // السهم وحده اتجاهي: ينعكس مع اتجاه الكتابة
        $dir = in_array($name, ['chev-prev', 'chev-next'], true) ? ' tq-dir-icon' : '';

        return '<svg width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24"'
            . ' fill="none" stroke="currentColor" stroke-width="1.7"'
            . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"'
            . ' class="tq-icon' . $dir . '">' . $paths[$name] . '</svg>';
    }
}
