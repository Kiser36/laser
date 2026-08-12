<?php
/**
 * Owere & Associates — settings.
 * WhatsApp number, contact details, SMTP mail (Zoho / Google / any provider), password change.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_login();
ensure_admin_schema();

$pageTitle = 'Settings';
$activeNav = 'settings';

/* Avatar ceiling (10 MB) — used by the profile save handler AND the hint copy. */
$avatarLimit = 10 * 1024 * 1024;

/* ---------------------------------------------------------------------------
 * Handle POST actions (before any HTML output so redirects work)
 * ------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'site') {
        $keys = ['whatsapp_number', 'whatsapp_welcome_msg', 'phone_display', 'notification_email', 'address', 'hours'];
        foreach ($keys as $key) {
            set_setting($key, trim((string)($_POST[$key] ?? '')));
        }
        log_admin_activity('settings_site', 'Saved WhatsApp & contact details');
        flash('success', 'Contact & WhatsApp settings saved.');
        redirect('settings.php');
    }

    if ($action === 'mail') {
        $keys = ['smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'mail_from', 'mail_from_name', 'notification_email'];
        foreach ($keys as $key) {
            set_setting($key, trim((string)($_POST[$key] ?? '')));
        }
        log_admin_activity('settings_mail', 'Saved SMTP email settings');
        flash('success', 'Mail settings saved. Test delivery by submitting the public form.');
        redirect('settings.php');
    }

    if ($action === 'profile') {
        $admin = current_admin();
        if (!$admin) {
            flash('error', 'Session expired — please log in again.');
            redirect('settings.php');
        }

        $username    = trim((string)($_POST['username'] ?? ''));
        $displayName = mb_substr(strip_tags(trim((string)($_POST['display_name'] ?? ''))), 0, 100);
        $email       = trim((string)($_POST['email'] ?? ''));
        $removeAvatar = !empty($_POST['remove_avatar']);

        $errors = [];
        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
            $errors[] = 'Username must be 3–50 characters using letters, numbers, dots, dashes or underscores.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid account email.';
        }

        if ($errors === []) {
            $stmt = db()->prepare('SELECT COUNT(*) FROM admin_users WHERE username = ? AND id <> ?');
            $stmt->execute([$username, $admin['id']]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errors[] = 'That username is already taken.';
            }
        }
        if ($errors === []) {
            $stmt = db()->prepare('SELECT COUNT(*) FROM admin_users WHERE email = ? AND id <> ?');
            $stmt->execute([$email, $admin['id']]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errors[] = 'That email is already in use.';
            }
        }

        // Avatar: uploaded file wins; otherwise keep, or clear when requested.
        // SVG is allowed: upload_image() scans SVGs for script payloads before
        // accepting them.
        $avatarPath = (string)($admin['avatar'] ?? '');
        if ($removeAvatar) {
            $avatarPath = '';
        } elseif (!empty($_FILES['avatar']['name'])) {
            $up = upload_image($_FILES['avatar'], 'assets/uploads/avatars', ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], $avatarLimit);
            if ($up['ok']) {
                $avatarPath = (string)$up['path'];
            } else {
                $errors[] = $up['error'] ?? 'Avatar upload failed.';
            }
        }

        if ($errors !== []) {
            flash('error', implode(' ', $errors));
            redirect('settings.php');
        }

        $stmt = db()->prepare('UPDATE admin_users SET username = ?, display_name = ?, email = ?, avatar = ? WHERE id = ?');
        $stmt->execute([$username, $displayName, $email, $avatarPath, $admin['id']]);
        $_SESSION['admin_username'] = $username;

        // Remove the old avatar file when it was replaced or cleared.
        $old = (string)($admin['avatar'] ?? '');
        if ($old !== '' && $old !== $avatarPath && str_starts_with($old, 'assets/uploads/avatars/')) {
            @unlink(BASE_PATH . '/' . $old);
        }

        // Log exactly what changed so the activity trail is meaningful.
        $changes = [];
        if ($username !== (string)($admin['username'] ?? '')) {
            $changes[] = 'username';
        }
        if ($displayName !== (string)($admin['display_name'] ?? '')) {
            $changes[] = 'display name';
        }
        if ($email !== (string)($admin['email'] ?? '')) {
            $changes[] = 'email';
        }
        if ($old !== $avatarPath) {
            $changes[] = 'profile photo';
        }
        log_admin_activity('profile_update', $changes !== [] ? 'Updated: ' . implode(', ', $changes) : 'Updated profile details');

        flash('success', 'Profile updated.');
        redirect('settings.php');
    }

    if ($action === 'password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new     = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        $admin   = current_admin();

        if (!$admin) {
            flash('error', 'Session expired — please log in again.');
        } elseif ($new !== $confirm) {
            flash('error', 'New passwords do not match.');
        } elseif (mb_strlen($new) < 8) {
            flash('error', 'New password must be at least 8 characters.');
        } else {
            $stmt = db()->prepare('SELECT password_hash FROM admin_users WHERE id = ?');
            $stmt->execute([$admin['id']]);
            $row = $stmt->fetch();

            if (!$row || !password_verify($current, $row['password_hash'])) {
                flash('error', 'Current password is incorrect.');
            } else {
                $stmt = db()->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?');
                $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $admin['id']]);
                log_admin_activity('password_change', 'Changed account password');
                flash('success', 'Password updated successfully.');
            }
        }
        redirect('settings.php');
    }
}

/* Current values */
$wa = [
    'whatsapp_number'    => get_setting('whatsapp_number'),
    'whatsapp_welcome_msg' => get_setting('whatsapp_welcome_msg'),
    'phone_display'      => get_setting('phone_display'),
    'notification_email' => get_setting('notification_email'),
    'address'            => get_setting('address'),
    'hours'              => get_setting('hours'),
];

$mail = [
    'smtp_host'        => get_setting('smtp_host'),
    'smtp_port'        => get_setting('smtp_port'),
    'smtp_user'        => get_setting('smtp_user'),
    'smtp_pass'        => get_setting('smtp_pass'),
    'mail_from'        => get_setting('mail_from'),
    'mail_from_name'   => get_setting('mail_from_name'),
];

$admin = current_admin();
$profile = [
    'username'     => (string)($admin['username'] ?? ''),
    'display_name' => (string)($admin['display_name'] ?? ''),
    'email'        => (string)($admin['email'] ?? ''),
    'avatar'       => (string)($admin['avatar'] ?? ''),
];

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="panel">
    <div class="panel__head">
        <h2 class="panel__title">My Profile</h2>
    </div>
    <form class="form" method="post" action="settings.php" enctype="multipart/form-data" style="max-width:640px;">
        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
        <input type="hidden" name="action" value="profile">

        <div class="form__row" style="align-items:center;">
            <div class="profile-avatar" id="profileAvatar">
                <?php if ($profile['avatar'] !== ''): ?>
                    <img src="../<?= esc($profile['avatar']) ?>" alt="Current profile photo">
                <?php else: ?>
                    <span><?= esc(strtoupper(mb_substr($profile['display_name'] !== '' ? $profile['display_name'] : $profile['username'], 0, 1) ?: 'A')) ?></span>
                <?php endif; ?>
            </div>
            <div class="form__field">
                <label for="profile_avatar">Profile photo</label>
                <input type="file" id="profile_avatar" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml">
                <p class="form__hint">Square image, up to <?= esc(format_upload_limit($avatarLimit)) ?> (JPG, PNG, WEBP, GIF or SVG). The photo previews in the circle the moment you pick it — leave empty to keep the current photo.</p>
                <label class="form__check">
                    <input type="checkbox" name="remove_avatar" value="1">
                    <span>Remove current photo</span>
                </label>
            </div>
        </div>

        <div class="form__row">
            <div class="form__field">
                <label for="profile_username">Username <span class="form__optional">(used to log in)</span></label>
                <input type="text" id="profile_username" name="username" value="<?= esc($profile['username']) ?>" maxlength="50" required>
            </div>
            <div class="form__field">
                <label for="profile_display_name">Display name <span class="form__optional">(shown in the panel)</span></label>
                <input type="text" id="profile_display_name" name="display_name" value="<?= esc($profile['display_name']) ?>" maxlength="100">
            </div>
        </div>

        <div class="form__field" style="max-width:50%;">
            <label for="profile_email">Account email</label>
            <input type="email" id="profile_email" name="email" value="<?= esc($profile['email']) ?>" maxlength="100" required>
        </div>

        <div>
            <button type="submit" class="btn btn--navy">Save Profile</button>
        </div>
    </form>
</div>

<div class="panel">
    <div class="panel__head">
        <h2 class="panel__title">Admin Panel Theme</h2>
    </div>
    <div class="theme-setting-card">
        <p class="form__hint" style="margin-bottom: 16px;">Select your preferred workspace theme. Switch seamlessly between Executive Dark Mode and Crisp Light Mode at any time.</p>
        <div class="theme-options">
            <label class="theme-option">
                <input type="radio" name="admin_theme_radio" value="light" id="themeRadioLight">
                <div class="theme-option__box theme-option__box--light">
                    <div class="theme-option__sidebar"></div>
                    <div class="theme-option__content">
                        <div class="theme-option__bar"></div>
                        <div class="theme-option__line"></div>
                        <div class="theme-option__line theme-option__line--short"></div>
                    </div>
                </div>
                <span class="theme-option__label">☀️ Light Mode</span>
            </label>
            <label class="theme-option">
                <input type="radio" name="admin_theme_radio" value="dark" id="themeRadioDark">
                <div class="theme-option__box theme-option__box--dark">
                    <div class="theme-option__sidebar"></div>
                    <div class="theme-option__content">
                        <div class="theme-option__bar"></div>
                        <div class="theme-option__line"></div>
                        <div class="theme-option__line theme-option__line--short"></div>
                    </div>
                </div>
                <span class="theme-option__label">🌙 Dark Mode</span>
            </label>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel__head">
        <h2 class="panel__title">WhatsApp &amp; Contact Details</h2>
    </div>
    <form class="form" method="post" action="settings.php">
        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
        <input type="hidden" name="action" value="site">

        <div class="form__row">
            <div class="form__field">
                <label for="whatsapp_number">WhatsApp number</label>
                <input type="text" id="whatsapp_number" name="whatsapp_number" value="<?= esc($wa['whatsapp_number']) ?>" placeholder="+256 701 700 461">
                <p class="form__hint">International format with country code — used by the floating chat button.</p>
            </div>
            <div class="form__field">
                <label for="phone_display">Display phone</label>
                <input type="text" id="phone_display" name="phone_display" value="<?= esc($wa['phone_display']) ?>" placeholder="+256 701 700 461">
            </div>
        </div>

        <div class="form__field">
            <label for="whatsapp_welcome_msg">WhatsApp welcome message</label>
            <input type="text" id="whatsapp_welcome_msg" name="whatsapp_welcome_msg" value="<?= esc($wa['whatsapp_welcome_msg']) ?>" maxlength="300">
        </div>

        <div class="form__field" style="max-width:50%;">
            <label for="notification_email">Public email &amp; lead notification inbox</label>
            <input type="email" id="notification_email" name="notification_email" value="<?= esc($wa['notification_email']) ?>">
            <p class="form__hint">Displayed on the public site and receives general enquiries.</p>
        </div>

        <div class="form__row">
            <div class="form__field">
                <label for="address">Office address</label>
                <input type="text" id="address" name="address" value="<?= esc($wa['address']) ?>" maxlength="255">
            </div>
            <div class="form__field">
                <label for="hours">Office hours</label>
                <input type="text" id="hours" name="hours" value="<?= esc($wa['hours']) ?>" maxlength="120">
            </div>
        </div>

        <div>
            <button type="submit" class="btn btn--gold">Save Contact Settings</button>
        </div>
    </form>
</div>

<div class="panel">
    <div class="panel__head">
        <h2 class="panel__title">SMTP Email Settings</h2>
        <span class="badge badge--contacted"><?= $mail['smtp_user'] !== '' ? 'Configured' : 'Not configured' ?></span>
    </div>
    <form class="form" method="post" action="settings.php">
        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
        <input type="hidden" name="action" value="mail">

        <div class="form__row">
            <div class="form__field">
                <label for="smtp_host">SMTP host</label>
                <input type="text" id="smtp_host" name="smtp_host" value="<?= esc($mail['smtp_host']) ?>" placeholder="smtp.zoho.com">
                <p class="form__hint">Zoho: smtp.zoho.com (port 465). Google: smtp.gmail.com (port 587).</p>
            </div>
            <div class="form__field">
                <label for="smtp_port">Port</label>
                <input type="number" id="smtp_port" name="smtp_port" value="<?= esc($mail['smtp_port']) ?>" placeholder="465" min="1" max="65535">
            </div>
        </div>

        <div class="form__row">
            <div class="form__field">
                <label for="smtp_user">SMTP username</label>
                <input type="text" id="smtp_user" name="smtp_user" value="<?= esc($mail['smtp_user']) ?>" placeholder="info@owereassociates.com" autocomplete="off">
            </div>
            <div class="form__field">
                <label for="smtp_pass">App password</label>
                <input type="password" id="smtp_pass" name="smtp_pass" value="<?= esc($mail['smtp_pass']) ?>" placeholder="16-character app password" autocomplete="new-password">
                <p class="form__hint">Use an app-specific password. Zoho: Zoho Accounts → Security → App Passwords. Google: account Security → App passwords (needs 2-Step Verification).</p>
            </div>
        </div>

        <div class="form__row">
            <div class="form__field">
                <label for="mail_from">From address</label>
                <input type="email" id="mail_from" name="mail_from" value="<?= esc($mail['mail_from']) ?>">
            </div>
            <div class="form__field">
                <label for="mail_from_name">From name</label>
                <input type="text" id="mail_from_name" name="mail_from_name" value="<?= esc($mail['mail_from_name']) ?>">
            </div>
        </div>

        <div class="form__field">
            <label for="notification_email">Booking notification inbox</label>
            <input type="email" id="notification_email" name="notification_email" value="<?= esc(get_setting('notification_email')) ?>">
            <p class="form__hint">All web consultation alerts are routed here.</p>
        </div>

        <div>
            <button type="submit" class="btn btn--gold">Save Mail Settings</button>
        </div>
    </form>
</div>

<div class="panel">
    <div class="panel__head">
        <h2 class="panel__title">Change Password</h2>
    </div>
    <form class="form" method="post" action="settings.php" style="max-width:480px;">
        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
        <input type="hidden" name="action" value="password">

        <div class="form__field">
            <label for="current_password">Current password</label>
            <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
        </div>
        <div class="form__row">
            <div class="form__field">
                <label for="new_password">New password</label>
                <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">
            </div>
            <div class="form__field">
                <label for="confirm_password">Confirm new password</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">
            </div>
        </div>

        <div>
            <button type="submit" class="btn btn--navy">Update Password</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
