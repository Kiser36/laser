<?php
/**
 * Owere & Associates — partner logo manager.
 * Upload (validated, sanitised SVGs/images), edit ordering/category, delete.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_login();

$pageTitle = 'Partner Logos';
$activeNav = 'logos';

/* ---------------------------------------------------------------------------
 * Handle POST actions (before any HTML output so redirects work)
 * ------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'upload') {
        $orgName = trim((string)($_POST['org_name'] ?? ''));
        $category = (string)($_POST['category'] ?? 'corporate');
        $order    = max(0, (int)($_POST['display_order'] ?? 0));

        if (mb_strlen($orgName) < 2 || mb_strlen($orgName) > 150) {
            flash('error', 'Please provide the organisation name (2–150 characters).');
        } elseif (!in_array($category, ['corporate', 'ngo', 'compliance'], true)) {
            flash('error', 'Invalid category selected.');
        } else {
            $upload = upload_image($_FILES['logo'] ?? [], 'assets/images/partners');

            if (!$upload['ok']) {
                flash('error', $upload['error']);
            } else {
                $stmt = db()->prepare(
                    'INSERT INTO partner_logos (org_name, logo_path, category, display_order, is_active)
                     VALUES (?, ?, ?, ?, 1)'
                );
                $stmt->execute([
                    $orgName,
                    $upload['path'],
                    $category,
                    $order,
                ]);
                log_admin_activity('logo_add', 'Added logo for "' . $orgName . '"');
                flash('success', 'Logo for "' . $orgName . '" uploaded successfully.');
            }
        }
        redirect('logos.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        $stmt = db()->prepare('SELECT logo_path FROM partner_logos WHERE id = ?');
        $stmt->execute([$id]);
        $logo = $stmt->fetch();

        if ($logo) {
            // Only delete the file if it lives inside our uploads dir.
            $file = __DIR__ . '/../' . $logo['logo_path'];
            if (is_file($file) && str_contains($logo['logo_path'], 'assets/images/partners/')) {
                @unlink($file);
            }
            $stmt = db()->prepare('DELETE FROM partner_logos WHERE id = ?');
            $stmt->execute([$id]);
            log_admin_activity('logo_delete', 'Removed logo #' . $id);
            flash('success', 'Logo removed.');
        }
        redirect('logos.php');
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = db()->prepare('UPDATE partner_logos SET is_active = 1 - is_active WHERE id = ?');
        $stmt->execute([$id]);
        log_admin_activity('logo_toggle', 'Toggled visibility of logo #' . $id);
        redirect('logos.php');
    }
}

$partners = db()->query(
    'SELECT id, org_name, logo_path, category, display_order, is_active
     FROM partner_logos
     ORDER BY display_order ASC, id ASC'
)->fetchAll();

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="panel">
    <div class="panel__head">
        <h2 class="panel__title">Upload a New Partner Logo</h2>
    </div>

    <form class="form" action="logos.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
        <input type="hidden" name="action" value="upload">

        <div class="form__row">
            <div class="form__field">
                <label for="logoOrgName">Organisation name *</label>
                <input type="text" id="logoOrgName" name="org_name" required maxlength="150" placeholder="Acme Corporation">
            </div>
            <div class="form__field">
                <label for="logoCategory">Category</label>
                <select id="logoCategory" name="category">
                    <option value="corporate">Corporate</option>
                    <option value="ngo">NGO / Non-profit</option>
                    <option value="compliance">Compliance / Audit</option>
                </select>
            </div>
        </div>

        <div class="form__field" style="max-width:220px;">
            <label for="logoOrder">Display order</label>
            <input type="number" id="logoOrder" name="display_order" value="0" min="0" max="999">
        </div>

        <div class="form__field">
            <label>Logo image *</label>
            <div class="upload-zone" id="uploadZone">
                <div class="upload-zone__icon">
                    <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                </div>
                <p><strong>Click or drag &amp; drop</strong> — JPG, PNG, WebP, GIF or SVG, up to <?= esc(format_upload_limit()) ?>.</p>
            </div>
            <input type="file" id="logoFile" name="logo" accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml" style="display:none;" required>

            <div class="upload-preview" id="uploadPreview">
                <img id="uploadPreviewImg" alt="Preview">
                <span class="table__muted">Preview of the file to be uploaded.</span>
            </div>
        </div>

        <div>
            <button type="submit" class="btn btn--gold">Upload Logo</button>
        </div>
    </form>
</div>

<div class="panel">
    <div class="panel__head">
        <h2 class="panel__title">Current Logos <span class="table__muted">(<?= count($partners) ?>)</span></h2>
    </div>

    <?php if (empty($partners)): ?>
        <p class="table__muted" style="padding:20px 0;">No logos yet — upload your first partner logo above.</p>
    <?php else: ?>
        <div class="logo-grid">
            <?php foreach ($partners as $p): ?>
                <div class="logo-tile">
                    <div class="logo-tile__preview">
                        <img src="../<?= esc($p['logo_path']) ?>" alt="<?= esc($p['org_name']) ?>">
                    </div>
                    <div class="logo-tile__name"><?= esc($p['org_name']) ?></div>
                    <div class="logo-tile__cat">
                        <?= esc($p['category']) ?> &middot; order <?= (int)$p['display_order'] ?>
                        &middot; <?= $p['is_active'] ? 'active' : 'hidden' ?>
                    </div>
                    <div class="logo-tile__actions">
                        <form method="post" action="logos.php" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <button type="submit" class="btn btn--ghost btn--sm"><?= $p['is_active'] ? 'Hide' : 'Show' ?></button>
                        </form>
                        <form method="post" action="logos.php" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                            <button type="submit" class="btn btn--danger btn--sm" data-confirm="Remove this logo permanently?">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
