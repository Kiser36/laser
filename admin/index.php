<?php
/**
 * Owere & Associates — admin login.
 * On the very first visit, if no admin exists yet, a default account is
 * created:  username: admin  /  password: admin123
 * Change it immediately from Settings.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

// Already logged in? Go straight to the dashboard.
if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter both your username and password.';
    } else {
        try {
            // Auto-create the default admin on a fresh database.
            ensure_default_admin();

            $stmt = db()->prepare('SELECT id, username, password_hash FROM admin_users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['admin_id'] = (int)$user['id'];
                $_SESSION['admin_username'] = $user['username'];
                log_admin_activity('login', 'Signed in');
                redirect('dashboard.php');
            }

            log_admin_activity('login_failed', 'Failed login attempt for username "' . $username . '"');
            $error = 'Invalid username or password.';
        } catch (PDOException $e) {
            error_log('[owere] Login error: ' . $e->getMessage());
            $error = 'Could not connect to the database. Have you imported schema.sql and created the owere_db database?';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Staff Login · Owere Admin</title>
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
<body class="login-body">
    <div class="login-top-bar">
        <button type="button" class="theme-toggle" id="adminThemeToggle" title="Toggle Light / Dark Theme" aria-label="Toggle Theme">
            <span class="theme-toggle__icon theme-toggle__icon--sun">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
            </span>
            <span class="theme-toggle__icon theme-toggle__icon--moon">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </span>
            <span class="theme-toggle__text">Light</span>
        </button>
    </div>
    <div class="login-card">
        <div class="login-card__brand">
            <span class="login-card__mark">OA</span>
            <div>
                <div class="login-card__name">Owere &amp; Associates</div>
                <div class="login-card__sub">Admin Panel</div>
            </div>
        </div>

        <h1>Staff Login</h1>
        <p class="login-card__lede">Access the consultation leads dashboard and site management.</p>

        <?php if ($error !== ''): ?>
            <div class="alert alert--error" data-alert>
                <span><?= esc($error) ?></span>
                <button type="button" class="alert__close" data-alert-close aria-label="Dismiss">&times;</button>
            </div>
        <?php endif; ?>

        <form class="form" action="index.php" method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">

            <div class="form__field">
                <label for="login-username">Username</label>
                <input type="text" id="login-username" name="username" required autofocus value="<?= esc($username) ?>" placeholder="admin">
            </div>

            <div class="form__field">
                <label for="login-password">Password</label>
                <input type="password" id="login-password" name="password" required placeholder="••••••••">
            </div>

            <button type="submit" class="btn btn--gold btn--block">Sign In</button>
        </form>

        <div class="login-notice">
            <strong>First time?</strong> If this is a fresh install, log in with
            <code>admin</code> / <code>admin123</code> and change the password
            immediately from <em>Settings</em>.
        </div>
    </div>
    <script src="../assets/js/admin.js" defer></script>
</body>
</html>
