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
