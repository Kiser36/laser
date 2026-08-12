<?php
/**
 * Owere & Associates — logout.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

// Record the logout before the session is destroyed.
log_admin_activity('logout', 'Signed out');

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();

redirect('index.php');
