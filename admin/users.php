<?php
/**
 * Owere & Associates — admin user management.
 * Create, edit, reset passwords for, and delete staff accounts — no SQL needed.
 * Safety guards: you cannot delete your own account, and the last admin
 * account can never be deleted.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_login();
ensure_admin_schema();

$pageTitle = 'Admin Users';
$activeNav = 'users';

$avatarLimit = 10 * 1024 * 1024; // matches the profile page (10 MB)

/* ---------------------------------------------------------------------------
 * Handle POST actions (before any HTML output so redirects work)
 * ------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? '');
    $admin  = current_admin();

    if ($action === 'create') {
        if (!is_owner()) {
            flash('error', 'Only the original administrator can create admin accounts.');
            redirect('users.php');
        }
        $username    = trim((string)($_POST['username'] ?? ''));
        $displayName = mb_substr(strip_tags(trim((string)($_POST['display_name'] ?? ''))), 0, 100);
        $email       = trim((string)($_POST['email'] ?? ''));
        $password    = (string)($_POST['password'] ?? '');

        $errors = [];
        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
            $errors[] = 'Username must be 3–50 characters using letters, numbers, dots, dashes or underscores.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (mb_strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($errors === []) {
            $stmt = db()->prepare('SELECT COUNT(*) FROM admin_users WHERE username = ?');
            $stmt->execute([$username]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errors[] = 'That username is already taken.';
            }
        }
        if ($errors === []) {
            $stmt = db()->prepare('SELECT COUNT(*) FROM admin_users WHERE email = ?');
            $stmt->execute([$email]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errors[] = 'That email is already in use.';
            }
        }

        $avatarPath = '';
        // Only accept the avatar when the rest of the account is valid — this
        // avoids leaving an orphaned file behind when creation fails.
        if ($errors === [] && !empty($_FILES['avatar']['name'])) {
            $up = upload_image($_FILES['avatar'], 'assets/uploads/avatars', ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], $avatarLimit);
            if ($up['ok']) {
                $avatarPath = (string)$up['path'];
            } else {
                $errors[] = $up['error'] ?? 'Avatar upload failed.';
            }
        }

        if ($errors !== []) {
            flash('error', implode(' ', $errors));
            redirect('users.php');
        }

        $stmt = db()->prepare(
            'INSERT INTO admin_users (username, display_name, email, password_hash, avatar) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $username,
            $displayName,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $avatarPath !== '' ? $avatarPath : null,
        ]);
        log_admin_activity('user_create', 'Created admin account "' . $username . '"');
        flash('success', 'Admin account "' . $username . '" created — they can log in immediately.');
        redirect('users.php');
    }

    if ($action === 'update') {
        $id           = (int)($_POST['id'] ?? 0);
        $displayName  = mb_substr(strip_tags(trim((string)($_POST['display_name'] ?? ''))), 0, 100);
        $email        = trim((string)($_POST['email'] ?? ''));
        $removeAvatar = !empty($_POST['remove_avatar']);

        $stmt = db()->prepare('SELECT id, username, email, display_name, avatar, role FROM admin_users WHERE id = ?');
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        if (!$target) {
            flash('error', 'That admin account no longer exists.');
            redirect('users.php');
        }
        if ((string)($target['role'] ?? 'admin') === 'owner' && !is_owner()) {
            flash('error', 'Only the original administrator can manage the owner account.');
            redirect('users.php');
        }

        $errors = [];
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if ($errors === []) {
            $stmt = db()->prepare('SELECT COUNT(*) FROM admin_users WHERE email = ? AND id <> ?');
            $stmt->execute([$email, $id]);
            if ((int)$stmt->fetchColumn() > 0) {
                $errors[] = 'That email is already in use.';
            }
        }

        $avatarPath = (string)($target['avatar'] ?? '');
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
            redirect('users.php');
        }

        $stmt = db()->prepare('UPDATE admin_users SET display_name = ?, email = ?, avatar = ? WHERE id = ?');
        $stmt->execute([$displayName, $email, $avatarPath, $id]);

        // Remove the old avatar file when it was replaced or cleared.
        $old = (string)($target['avatar'] ?? '');
        if ($old !== '' && $old !== $avatarPath && str_starts_with($old, 'assets/uploads/avatars/')) {
            @unlink(BASE_PATH . '/' . $old);
        }

        log_admin_activity('user_update', 'Updated account "' . $target['username'] . '"');
        flash('success', 'Account updated.');
        redirect('users.php');
    }

    if ($action === 'reset_password') {
        $id       = (int)($_POST['id'] ?? 0);
        $password = (string)($_POST['password'] ?? '');
        if ((string)db()->query('SELECT role FROM admin_users WHERE id = ' . (int)$id)->fetchColumn() === 'owner' && !is_owner()) {
            flash('error', 'Only the original administrator can manage the owner account.');
        } elseif (mb_strlen($password) < 8) {
            flash('error', 'Password must be at least 8 characters.');
        } else {
            $stmt = db()->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?');
            $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
            if ($stmt->rowCount() > 0) {
                log_admin_activity('user_password_reset', 'Reset password for admin #' . $id);
                flash('success', 'Password updated.');
            } else {
                flash('error', 'That admin account no longer exists.');
            }
        }
        redirect('users.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($admin && (int)$admin['id'] === $id) {
            flash('error', 'You cannot delete your own account.');
            redirect('users.php');
        }
        if ((string)db()->query('SELECT role FROM admin_users WHERE id = ' . $id)->fetchColumn() === 'owner' && !is_owner()) {
            flash('error', 'Only the original administrator can manage the owner account.');
            redirect('users.php');
        }
        $count = (int)db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
        if ($count <= 1) {
            flash('error', 'At least one admin account is required — create another one before deleting this one.');
            redirect('users.php');
        }

        $stmt = db()->prepare('SELECT username, avatar FROM admin_users WHERE id = ?');
        $stmt->execute([$id]);
        $target = $stmt->fetch();
        if ($target) {
            $avatar = (string)($target['avatar'] ?? '');
            if ($avatar !== '' && str_starts_with($avatar, 'assets/uploads/avatars/')) {
                @unlink(BASE_PATH . '/' . $avatar);
            }
            $stmt = db()->prepare('DELETE FROM admin_users WHERE id = ?');
            $stmt->execute([$id]);
            log_admin_activity('user_delete', 'Deleted admin account "' . $target['username'] . '"');
            flash('success', 'Account "' . $target['username'] . '" deleted.');
        }
        redirect('users.php');
    }
}

/* Current accounts */
$users = db()->query(
    'SELECT id, username, display_name, email, avatar, role, created_at FROM admin_users ORDER BY id ASC'
)->fetchAll();

$myId = is_logged_in() ? (int)($_SESSION['admin_id'] ?? 0) : 0;

require_once __DIR__ . '/../includes/admin-header.php';
?>

<?php if (is_owner()): ?>
    <div class="panel">
        <div class="panel__head">
            <h2 class="panel__title">Create a New Admin Account</h2>
        </div>
        <form class="form" action="users.php" method="post" enctype="multipart/form-data" style="max-width:640px;">
            <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
            <input type="hidden" name="action" value="create">

            <div class="form__row">
                <div class="form__field">
                    <label for="new_username">Username <span class="form__optional">(used to log in)</span></label>
                    <input type="text" id="new_username" name="username" required maxlength="50" placeholder="e.g. sarah" pattern="[A-Za-z0-9_.-]{3,50}">
                </div>
                <div class="form__field">
                    <label for="new_display_name">Display name</label>
                    <input type="text" id="new_display_name" name="display_name" maxlength="100" placeholder="e.g. Sarah Namutebi">
                </div>
            </div>

            <div class="form__row">
                <div class="form__field">
                    <label for="new_email">Email</label>
                    <input type="email" id="new_email" name="email" required maxlength="100" placeholder="sarah@owereassociates.com">
                </div>
                <div class="form__field">
                    <label for="new_password">Temporary password <span class="form__optional">(min 8 characters)</span></label>
                    <input type="password" id="new_password" name="password" required minlength="8" autocomplete="new-password">
                </div>
            </div>

            <div class="form__field">
                <label for="new_avatar">Profile photo <span class="form__optional">(optional)</span></label>
                <input type="file" id="new_avatar" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml">
                <p class="form__hint">Up to <?= esc(format_upload_limit($avatarLimit)) ?>. They can change this themselves later under Settings → My Profile.</p>
            </div>

            <div>
                <button type="submit" class="btn btn--gold">Create Admin Account</button>
            </div>
        </form>
    </div>
<?php else: ?>
    <div class="panel">
        <div class="panel__head">
            <h2 class="panel__title">Create a New Admin Account</h2>
        </div>
        <p class="form__hint" style="padding:16px 0;">Only the original administrator can create admin accounts.</p>
    </div>
<?php endif; ?>

<div class="panel">
    <div class="panel__head">
        <h2 class="panel__title">Admin Accounts <span class="table__muted">(<?= count($users) ?>)</span></h2>
    </div>

    <?php if (empty($users)): ?>
        <p class="table__muted" style="padding:20px 0;">No accounts found.</p>
    <?php else: ?>
        <div class="user-grid">
            <?php foreach ($users as $u):
                $uId      = (int)$u['id'];
                $uName    = (string)($u['display_name'] !== '' && $u['display_name'] !== null ? $u['display_name'] : $u['username']);
                $uAvatar  = (string)($u['avatar'] ?? '');
                $isMe     = $uId === $myId; ?>
                <div class="user-card">
                    <div class="user-card__head">
                        <div class="user-card__avatar">
                            <?php if ($uAvatar !== ''): ?>
                                <img src="../<?= esc($uAvatar) ?>" alt="">
                            <?php else: ?>
                                <span><?= esc(strtoupper(mb_substr($uName, 0, 1) ?: 'A')) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="user-card__who">
                            <div class="user-card__name"><?= esc($uName) ?> <?= $isMe ? '<span class="badge badge--gold">you</span>' : '' ?> <?= ($u['role'] ?? 'admin') === 'owner' ? '<span class="badge badge--gold">owner</span>' : '' ?></div>
                            <div class="user-card__username">@<?= esc($u['username']) ?></div>
                            <div class="user-card__meta"><?= esc($u['email']) ?></div>
                            <div class="user-card__meta">Joined <?= esc(format_date($u['created_at'])) ?></div>
                        </div>
                    </div>

                    <?php if (($u['role'] ?? 'admin') !== 'owner' || is_owner()): ?>
                    <details class="user-card__manage">
                        <summary class="btn btn--ghost btn--sm">Manage account</summary>
                        <div class="user-card__body">
                            <form class="form" action="users.php" method="post" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="id" value="<?= $uId ?>">
                                <div class="form__row">
                                    <div class="form__field">
                                        <label>Display name</label>
                                        <input type="text" name="display_name" value="<?= esc((string)($u['display_name'] ?? '')) ?>" maxlength="100">
                                    </div>
                                    <div class="form__field">
                                        <label>Email</label>
                                        <input type="email" name="email" value="<?= esc($u['email']) ?>" required maxlength="100">
                                    </div>
                                </div>
                                <div class="form__row" style="align-items:center;">
                                    <div class="form__field">
                                        <label>Profile photo</label>
                                        <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml">
                                    </div>
                                    <label class="form__check">
                                        <input type="checkbox" name="remove_avatar" value="1">
                                        <span>Remove photo</span>
                                    </label>
                                </div>
                                <button type="submit" class="btn btn--navy btn--sm">Save changes</button>
                            </form>

                            <form class="form" action="users.php" method="post">
                                <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
                                <input type="hidden" name="action" value="reset_password">
                                <input type="hidden" name="id" value="<?= $uId ?>">
                                <div class="form__field">
                                    <label>Reset password</label>
                                    <div style="display:flex;gap:8px;">
                                        <input type="password" name="password" required minlength="8" placeholder="New password (min 8 characters)" autocomplete="new-password" style="flex:1;">
                                        <button type="submit" class="btn btn--navy btn--sm">Reset</button>
                                    </div>
                                </div>
                            </form>

                            <?php if (!$isMe): ?>
                                <form method="post" action="users.php" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $uId ?>">
                                    <button type="submit" class="btn btn--danger btn--sm" data-confirm="Delete the account '@<?= esc($u['username']) ?>' permanently? They will no longer be able to log in.">Delete account</button>
                                </form>
                            <?php else: ?>
                                <p class="form__hint">Manage your own profile (username, photo, password) under <a href="settings.php">Settings → My Profile</a>.</p>
                            <?php endif; ?>
                        </div>
                    </details>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
