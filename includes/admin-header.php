<?php
/**
 * Admin panel layout header. Expects $pageTitle and $activeNav.
 */
require_once __DIR__ . '/functions.php';
require_login();

$admin = current_admin();
$pageTitle = $pageTitle ?? 'Admin';
$activeNav = $activeNav ?? '';
$flash = consume_flash();
$displayName = trim((string)($admin['display_name'] ?? '')) !== ''
    ? $admin['display_name']
    : (string)($admin['username'] ?? 'Admin');
$avatarSrc = (string)($admin['avatar'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= esc($pageTitle) ?> · Owere Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <script>
        (function() {
            var theme = localStorage.getItem('owere_admin_theme') || 'light';
            document.documentElement.setAttribute('data-admin-theme', theme);
        })();
    </script>
</head>
<body>
<div class="admin-shell">

    <!-- Sidebar -->
    <aside class="sidebar">
        <a href="dashboard.php" class="sidebar__brand">
            <span class="sidebar__mark">OA</span>
            <span class="sidebar__name">Owere &amp; Associates</span>
        </a>

        <nav class="sidebar__nav">
            <p class="sidebar__label">Manage</p>
            <a href="dashboard.php" class="sidebar__link <?= $activeNav === 'dashboard' ? 'is-active' : '' ?>">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
                Leads
            </a>
            <a href="logos.php" class="sidebar__link <?= $activeNav === 'logos' ? 'is-active' : '' ?>">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                Partner Logos
            </a>
            <a href="media.php" class="sidebar__link <?= $activeNav === 'media' ? 'is-active' : '' ?>">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                Media Library
            </a>
            <a href="content.php" class="sidebar__link <?= $activeNav === 'content' ? 'is-active' : '' ?>">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                Website Content
            </a>
            <a href="settings.php" class="sidebar__link <?= $activeNav === 'settings' ? 'is-active' : '' ?>">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                Settings
            </a>
            <?php if (is_owner()): ?>
            <a href="activity.php" class="sidebar__link <?= $activeNav === 'activity' ? 'is-active' : '' ?>">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><polyline points="9 12 11 14 15 10"/></svg>
                Activity Log
            </a>
            <?php endif; ?>
            <a href="users.php" class="sidebar__link <?= $activeNav === 'users' ? 'is-active' : '' ?>">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Admin Users
            </a>
        </nav>

        <nav class="sidebar__nav sidebar__nav--bottom">
            <p class="sidebar__label">System</p>
            <a href="../index.php" target="_blank" class="sidebar__link">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                View Site
            </a>
            <a href="logout.php" class="sidebar__link sidebar__link--danger">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                Log Out
            </a>
        </nav>
    </aside>

    <!-- Main column -->
    <div class="admin-main">
        <header class="admin-topbar">
            <div class="admin-topbar__left">
                <button type="button" class="sidebar-toggle" data-sidebar-toggle aria-label="Toggle sidebar">
                    <span></span><span></span><span></span>
                </button>
                <h1 class="admin-topbar__title"><?= esc($pageTitle) ?></h1>
            </div>
            <div class="admin-topbar__actions">
                <button type="button" class="theme-toggle" id="adminThemeToggle" title="Toggle Light / Dark Theme" aria-label="Toggle Theme">
                    <span class="theme-toggle__icon theme-toggle__icon--sun">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
                    </span>
                    <span class="theme-toggle__icon theme-toggle__icon--moon">
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    </span>
                    <span class="theme-toggle__text">Light</span>
                </button>
                <div class="admin-topbar__user">
                    <?php if ($avatarSrc !== ''): ?>
                        <span class="admin-topbar__avatar">
                            <img src="../<?= esc($avatarSrc) ?>" alt="" width="36" height="36">
                        </span>
                    <?php else: ?>
                        <span class="admin-topbar__avatar"><?= esc(strtoupper(mb_substr($displayName, 0, 1))) ?></span>
                    <?php endif; ?>
                    <span class="admin-topbar__name"><?= esc($displayName) ?></span>
                </div>
            </div>
        </header>

        <?php if ($flash): ?>
            <div class="alert alert--<?= esc($flash['type']) ?>" data-alert>
                <span><?= esc($flash['message']) ?></span>
                <button type="button" class="alert__close" data-alert-close aria-label="Dismiss">&times;</button>
            </div>
        <?php endif; ?>

        <div class="admin-content">
