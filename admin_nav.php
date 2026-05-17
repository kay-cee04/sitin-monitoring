<?php
/**
 * admin_nav.php — Shared admin navigation bar
 *
 * Include INSIDE <body>, BEFORE page content on every admin page.
 *
 * Variables the host page should set before including:
 *   $admin_active_page  — one of: 'home','students','sitin','history',
 *                         'reports','analytics','software','testimonials',
 *                         'reservation'
 *
 * NOTE: Do NOT set $admin_active_file from PHP_SELF — only set it
 *       explicitly on admin_sitin_history.php like so:
 *         $admin_active_file = 'admin_sitin_history.php';
 */

// Use $page as fallback (set in admin_dashboard.php as $_GET['page'] ?? 'home')
$_anp = $admin_active_page ?? ($page ?? '');

// FIXED: Never fall back to PHP_SELF — only use explicitly set value.
// This prevents "View History" from being permanently active on all pages.
$_anf = $admin_active_file ?? '';

function _anav($label, $href, $match_page, $current_page, $current_file = '', $match_file = '', $extra_class = '') {
    $is_active = ($match_page && $current_page === $match_page)
              || ($match_file && $current_file === $match_file);
    $cls = trim(($is_active ? 'active ' : '') . $extra_class);
    $cls_attr = $cls ? ' class="' . $cls . '"' : '';
    return '<a href="' . $href . '"' . $cls_attr . '>' . $label . '</a>';
}
?>
<nav>
  <div class="nav-brand">CCS Admin</div>
  <div class="nav-links">

    <?= _anav('Home', 'admin_dashboard.php?page=home', 'home', $_anp) ?>

    <a href="#"
       onclick="
         if (typeof openModal === 'function') {
           openModal('searchModal');
         } else {
           window.location.href = 'admin_dashboard.php?page=home&open=search';
         }
         return false;">Search</a>

    <?= _anav('Students', 'admin_dashboard.php?page=students', 'students', $_anp) ?>

    <a href="#"
       onclick="(typeof openBlankSitin==='function' ? openBlankSitin() : window.location.href='admin_dashboard.php?page=sitin'); return false;"
       class="<?= $_anp === 'sitin' ? 'active' : '' ?>">Sit-in</a>

    <?= _anav('View History', 'admin_sitin_history.php', '', '', $_anf, 'admin_sitin_history.php') ?>

    <?= _anav('Reports',      'admin_dashboard.php?page=reports',      'reports',      $_anp) ?>
    <?= _anav('Analytics',    'admin_dashboard.php?page=analytics',    'analytics',    $_anp) ?>
    <?= _anav('Lab Software', 'admin_dashboard.php?page=software',     'software',     $_anp) ?>
    <?= _anav('Testimonials', 'admin_dashboard.php?page=testimonials', 'testimonials', $_anp) ?>
    <?= _anav('Reservation',  'admin_dashboard.php?page=reservation',  'reservation',  $_anp) ?>

    <a href="admin_logout.php" class="btn-logout-nav">Log out</a>

  </div>
</nav>