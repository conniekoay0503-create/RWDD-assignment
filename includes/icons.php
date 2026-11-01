<?php
/*
 * Simple line icons drawn with SVG (no icon library needed).
 * Usage:  <?= icon('edit') ?>   or   <?= icon('bell', 'icon-ring') ?>  (extra CSS class)
 * Every icon is 24 x 24 and uses "currentColor", so it takes the colour of the text around it.
 */
function icon($name, $class = '')
{
    $paths = [
        'home'       => '<path d="M3 11l9-7 9 7"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>',
        'tag'        => '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        'flag'       => '<path d="M5 21V4"/><path d="M5 4h12l-2.5 4L17 12H5"/>',
        'wallet'     => '<path d="M3 7h15a3 3 0 0 1 3 3v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M3 7l12-4v4"/><path d="M16 14h2"/>',
        'users'      => '<circle cx="9" cy="8" r="3.5"/><path d="M2 20c0-3.5 3-6 7-6s7 2.5 7 6"/><path d="M16 4.6a3.5 3.5 0 0 1 0 6.8"/><path d="M18.5 14.2c2 .7 3.5 2.8 3.5 5.8"/>',
        'megaphone'  => '<path d="M3 10v4h4l9 5V5l-9 5z"/><path d="M19 9a4 4 0 0 1 0 6"/>',
        'message'    => '<path d="M4 5h16v11H9l-5 4z"/><path d="M8 10h8"/>',
        'chart'      => '<path d="M4 4v16h16"/><path d="M8 16v-4"/><path d="M12 16V8"/><path d="M16 16v-6"/>',
        'bell'       => '<path d="M6 16v-5a6 6 0 0 1 12 0v5l2 2H4z"/><path d="M10 20a2 2 0 0 0 4 0"/>',
        'logout'     => '<path d="M14 4h5v16h-5"/><path d="M9 8l-4 4 4 4"/><path d="M5 12h11"/>',
        'user'       => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/>',
        'user-plus'  => '<circle cx="10" cy="8" r="4"/><path d="M2 21c0-4 3.6-7 8-7 1.4 0 2.7.3 3.8.8"/><path d="M18 13v7"/><path d="M14.5 16.5h7"/>',
        'heart'      => '<path d="M12 20s-7.5-4.6-7.5-10.2A4.3 4.3 0 0 1 12 7.2a4.3 4.3 0 0 1 7.5 2.6C19.5 15.4 12 20 12 20z"/>',
        'shield'     => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>',
        'plus'       => '<path d="M12 5v14"/><path d="M5 12h14"/>',
        'edit'       => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M13.5 6.5l4 4"/>',
        'trash'      => '<path d="M4 7h16"/><path d="M9 7V4h6v3"/><path d="M6 7l1 13h10l1-13"/><path d="M10 11v5"/><path d="M14 11v5"/>',
        'check'      => '<path d="M5 12.5l4.5 4.5L19 7"/>',
        'x'          => '<path d="M6 6l12 12"/><path d="M18 6L6 18"/>',
        'search'     => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
        'calculator' => '<rect x="5" y="3" width="14" height="18" rx="2"/><path d="M8 7h8"/><path d="M8.5 12h.01"/><path d="M12 12h.01"/><path d="M15.5 12h.01"/><path d="M8.5 16h.01"/><path d="M12 16h.01"/><path d="M15.5 16h.01"/>',
        'list'       => '<path d="M9 6h11"/><path d="M9 12h11"/><path d="M9 18h11"/><path d="M4.5 6h.01"/><path d="M4.5 12h.01"/><path d="M4.5 18h.01"/>',
        'clock'      => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'history'    => '<path d="M3.5 12a8.5 8.5 0 1 0 2.5-6"/><path d="M3 4v5h5"/><path d="M12 8v4l3 2"/>',
        'gift'       => '<rect x="4" y="10" width="16" height="10" rx="1"/><path d="M3 7h18v3H3z"/><path d="M12 7v13"/><path d="M12 7C10.5 3.5 6.5 4 7.5 7"/><path d="M12 7c1.5-3.5 5.5-3 4.5 0"/>',
        'recycle'    => '<path d="M7 19H4.5a1.5 1.5 0 0 1-1.3-2.2L5 13.5"/><path d="M11 19h8.5a1.5 1.5 0 0 0 1.3-2.2L19 13.5"/><path d="M9 16l-2 3 2 3"/><path d="M8.5 7.5L10.7 3.8a1.5 1.5 0 0 1 2.6 0l2.2 3.7"/><path d="M13 9l2.5-1.5L17 10"/><path d="M5 13.5L3 10l3.5-.5"/>',
        'wrench'     => '<path d="M14.5 6a4 4 0 0 0-5 5L4 16.5 7.5 20l5.5-5.5a4 4 0 0 0 5-5L15.5 12 12 8.5z"/>',
        'device'     => '<rect x="7" y="3" width="10" height="18" rx="2"/><path d="M11 18h2"/>',
        'leaf'       => '<path d="M5 20c0-9 6-15 15-15 0 9-5.5 15-14 15"/><path d="M5 20l8-8"/>',
        'star'       => '<path d="M12 3.5l2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8L3.5 9.7l5.9-.9z"/>',
        'percent'    => '<path d="M19 5L5 19"/><circle cx="7" cy="7" r="2.2"/><circle cx="17" cy="17" r="2.2"/>',
        'alert'      => '<path d="M12 3.5L21.5 20h-19z"/><path d="M12 10v4"/><path d="M12 17h.01"/>',
        'inbox'      => '<path d="M3 13h5l1.5 3h5l1.5-3h5"/><path d="M5.5 5h13L21 13v6H3v-6z"/>',
        'send'       => '<path d="M21 3L3 10.5l7 3 3 7.5z"/><path d="M10 13.5L21 3"/>',
        'arrow-left' => '<path d="M19 12H5"/><path d="M11 6l-6 6 6 6"/>',
        'arrow-right'=> '<path d="M5 12h14"/><path d="M13 6l6 6-6 6"/>',
        'sun'        => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="M4.9 4.9l1.4 1.4"/><path d="M17.7 17.7l1.4 1.4"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="M4.9 19.1l1.4-1.4"/><path d="M17.7 6.3l1.4-1.4"/>',
        'moon'       => '<path d="M20 14.5A8 8 0 1 1 9.5 4a6.5 6.5 0 0 0 10.5 10.5z"/>',
        'printer'    => '<path d="M7 9V3h10v6"/><rect x="4" y="9" width="16" height="8" rx="1.5"/><path d="M7 14h10v7H7z"/>',
        'calendar'   => '<rect x="4" y="5" width="16" height="16" rx="2"/><path d="M4 10h16"/><path d="M8 3v4"/><path d="M16 3v4"/>',
        'box'        => '<path d="M3 7.5l9-4.5 9 4.5v9L12 21l-9-4.5z"/><path d="M3 7.5l9 4.5 9-4.5"/><path d="M12 12v9"/>',
        'eye'        => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off'    => '<path d="M3 3l18 18"/><path d="M10.6 5.1A10 10 0 0 1 12 5c6.4 0 10 7 10 7a17 17 0 0 1-3.2 4"/><path d="M6.6 6.6C3.7 8.4 2 12 2 12s3.6 7 10 7a9.6 9.6 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
        'target'     => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.2"/>',
        'filter'     => '<path d="M4 5h16l-6 7.5V19l-4 2v-8.5z"/>',
        'scale'      => '<path d="M5 20h14"/><path d="M7 20l1.5-12h7L17 20"/><circle cx="12" cy="5" r="2"/>',
        'lock'       => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
        'key'        => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9"/><path d="M16 7l3 3"/>',
        'sliders'    => '<path d="M4 6h10"/><path d="M18 6h2"/><circle cx="16" cy="6" r="2"/><path d="M4 12h3"/><path d="M11 12h9"/><circle cx="9" cy="12" r="2"/><path d="M4 18h12"/><path d="M20 18h0"/><circle cx="18" cy="18" r="2"/>',
        'id'         => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2.2"/><path d="M5.8 16c.6-1.6 1.8-2.4 3.2-2.4s2.6.8 3.2 2.4"/><path d="M15 10h3"/><path d="M15 14h3"/>',
        'type'       => '<path d="M5 7V5h14v2"/><path d="M12 5v14"/><path d="M9 19h6"/>',
        'mail'       => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
        'phone'      => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
        'menu'       => '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>',
        'battery'    => '<rect x="3" y="8" width="15" height="8" rx="1.5"/><path d="M21 11v2"/><path d="M10 9.5L8.5 12h3l-1.5 2.5"/>',
        'laptop'     => '<rect x="5" y="5" width="14" height="10" rx="1"/><path d="M3 19h18"/>',
        'plug'       => '<path d="M9 3v5"/><path d="M15 3v5"/><path d="M7 8h10v3a5 5 0 0 1-10 0z"/><path d="M12 16v5"/>',
    ];
    $svg = $paths[$name] ?? $paths['box'];
    return '<svg class="icon ' . $class . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $svg . '</svg>';
}

// Pick a category icon from a word in the category name (used on the homepage and admin pages)
function category_icon_name($category_name)
{
    $n = strtolower($category_name);
    if (strpos($n, 'battery') !== false) return 'battery';
    if (strpos($n, 'laptop') !== false || strpos($n, 'desktop') !== false) return 'laptop';
    if (strpos($n, 'cable') !== false || strpos($n, 'charger') !== false) return 'plug';
    if (strpos($n, 'printer') !== false) return 'printer';
    return 'device';
}
