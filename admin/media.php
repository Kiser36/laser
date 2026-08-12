<?php
/**
 * Owere & Associates — Media Library.
 * Upload, browse, search and delete images — then place any of them directly
 * into a specific spot on the website (hero, about photo, or any pillar's
 * icon / Services-page photo) without touching a single file name.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_login();

$pageTitle = 'Media Library';
$activeNav = 'media';

/* ---------------------------------------------------------------------------
 * Handle POST actions (before any HTML output so redirects work)
 * ------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'upload') {
        $upload = upload_image($_FILES['file'] ?? [], 'assets/images/content');
        if ($upload['ok']) {
            log_admin_activity('media_upload', 'Uploaded ' . basename((string)($_FILES['file']['name'] ?? '')));
        }
        flash(
            $upload['ok'] ? 'success' : 'error',
            $upload['ok'] ? 'Image uploaded to the library.' : $upload['error']
        );
        redirect('media.php');
    }

    if ($action === 'place') {
        $slotKey = (string)($_POST['slot'] ?? '');
        $name    = basename((string)($_POST['name'] ?? ''));
        $path    = 'assets/images/content/' . $name;
        $result  = media_place_image($slotKey, $path);
        if ($result['ok']) {
            $slotLabel = $slotKey;
            foreach (media_slots() as $slot) {
                if ($slot['key'] === $slotKey) {
                    $slotLabel = $slot['label'];
                    break;
                }
            }
            log_admin_activity('media_place', $name . ' placed at: ' . $slotLabel);
        }
        flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('media.php');
    }

    if ($action === 'delete') {
        $name = basename((string)($_POST['name'] ?? ''));
        $file = BASE_PATH . '/assets/images/content/' . $name;
        if ($name !== '' && is_file($file) && str_contains($name, '.')) {
            @unlink($file);
            log_admin_activity('media_delete', 'Removed ' . $name . ' from the library');
            flash('success', 'Image removed from the library.');
        } else {
            flash('error', 'That file could not be found.');
        }
        redirect('media.php');
    }
}

/* ---------------------------------------------------------------------------
 * List images (newest first) + where each is used on the site
 * ------------------------------------------------------------------------- */
$images = [];
$dir = BASE_PATH . '/assets/images/content';
$allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];

if (is_dir($dir)) {
    foreach (glob($dir . '/*.*') ?: [] as $file) {
        if (!is_file($file)) {
            continue;
        }
        $ext = strtolower((string)pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed, true)) {
            continue;
        }
        $dims = [0, 0];
        if ($ext !== 'svg') {
            $info = @getimagesize($file);
            if (is_array($info)) {
                $dims = [(int)$info[0], (int)$info[1]];
            }
        }
        $images[] = [
            'name'  => basename($file),
            'path'  => 'assets/images/content/' . basename($file),
            'size'  => (int)filesize($file),
            'mtime' => (int)filemtime($file),
            'w'     => $dims[0],
            'h'     => $dims[1],
        ];
    }
    usort($images, static function (array $a, array $b): int {
        return $b['mtime'] <=> $a['mtime'];
    });
}

$slots  = media_slots();
$usage  = media_usage_map();
$uploadLimitText = format_upload_limit();

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="panel">
    <div class="panel__head">
        <h2 class="panel__title">Upload an Image</h2>
        <span class="badge badge--contacted"><?= count($images) ?> image<?= count($images) === 1 ? '' : 's' ?></span>
    </div>

    <form class="form" action="media.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
        <input type="hidden" name="action" value="upload">

        <div class="form__field">
            <div class="upload-zone" id="uploadZone">
                <div class="upload-zone__icon">
                    <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                </div>
                <p><strong>Click or drag &amp; drop</strong> — JPG, PNG, WebP, GIF or SVG, up to <?= esc($uploadLimitText) ?>.</p>
            </div>
            <input type="file" id="logoFile" name="file" accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml" style="display:none;">

            <div class="upload-preview" id="uploadPreview">
                <img id="uploadPreviewImg" alt="Preview">
                <span class="table__muted">Preview of the file to be uploaded.</span>
            </div>
        </div>

        <div>
            <button type="submit" class="btn btn--gold">Upload to Library</button>
        </div>
    </form>
    <p class="form__hint" style="margin-top:12px;">
        After uploading, click <strong>Place on site…</strong> on an image to choose exactly where it appears — the change goes live immediately.
    </p>
</div>

<div class="panel">
    <div class="panel__head">
        <h2 class="panel__title">Library</h2>
        <input type="search" id="mediaSearch" class="media-search" placeholder="Search images…">
    </div>

    <?php if (empty($images)): ?>
        <p class="table__muted" style="padding:20px 0;">No images yet — upload your first one above.</p>
    <?php else: ?>
        <div class="logo-grid" id="mediaGrid">
            <?php foreach ($images as $img):
                $usedHere = $usage[$img['path']] ?? []; ?>
                <div class="media-tile" data-media-tile data-name="<?= esc(strtolower($img['name'])) ?>">
                    <div class="media-tile__preview">
                        <img src="../<?= esc($img['path']) ?>" alt="<?= esc($img['name']) ?>" loading="lazy">
                    </div>
                    <div class="media-tile__meta">
                        <div class="media-tile__name" title="<?= esc($img['name']) ?>"><?= esc($img['name']) ?></div>
                        <div class="media-tile__dims">
                            <?= $img['w'] > 0 ? (int)$img['w'] . '×' . (int)$img['h'] : '' ?>
                            <?= esc(number_format($img['size'] / 1024, 0)) ?> KB
                        </div>
                    </div>
                    <div class="media-tile__usage">
                        <?php if (!empty($usedHere)): ?>
                            <span class="media-tile__badge">In use</span>
                            <span class="media-tile__where" title="<?= esc(implode(' · ', $usedHere)) ?>">
                                <?= esc(implode(' · ', array_slice($usedHere, 0, 2))) ?><?= count($usedHere) > 2 ? '…' : '' ?>
                            </span>
                        <?php else: ?>
                            <span class="media-tile__where media-tile__where--none">Not used on the site yet</span>
                        <?php endif; ?>
                    </div>
                    <div class="media-tile__actions">
                        <button type="button" class="btn btn--gold btn--sm" data-place-open="<?= esc($img['name']) ?>">Place on site…</button>
                        <button type="button" class="btn btn--ghost btn--sm" data-copy-path="<?= esc($img['path']) ?>">Copy path</button>
                        <form method="post" action="media.php" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="name" value="<?= esc($img['name']) ?>">
                            <button type="submit" class="btn btn--danger btn--sm"
                                data-confirm="<?= esc(
                                    empty($usedHere)
                                        ? 'Remove this image from the library?'
                                        : 'This image is currently used on the site (' . implode(' · ', $usedHere) . '). If you remove it, those spots will show a broken image until you place another. Remove anyway?'
                                ) ?>">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Place-on-site dialog -->
<div class="media-modal" id="placeModal" hidden>
    <div class="media-modal__backdrop" data-place-close></div>
    <div class="media-modal__panel">
        <div class="media-modal__head">
            <h3>Where should this image go?</h3>
            <button type="button" class="media-modal__close" data-place-close aria-label="Close">&times;</button>
        </div>

        <form method="post" action="media.php" id="placeForm">
            <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
            <input type="hidden" name="action" value="place">
            <input type="hidden" name="slot" id="placeSlot" value="">
            <input type="hidden" name="name" id="placeName" value="">

            <div class="place-hero">
                <img id="placePreview" alt="Selected image" src="">
                <p class="table__muted">Choose a spot below. The image will replace whatever is there now, and the change goes live on the website immediately.</p>
            </div>

            <div class="place-slots" id="placeSlots">
                <?php foreach ($slots as $slot): ?>
                    <button type="button" class="place-slot" data-place-slot="<?= esc($slot['key']) ?>">
                        <span class="place-slot__thumb">
                            <?php $cur = trim((string)$slot['path']); ?>
                            <?php if ($cur !== ''): ?>
                                <img src="../<?= esc($cur) ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <span class="place-slot__empty">empty</span>
                            <?php endif; ?>
                        </span>
                        <span class="place-slot__body">
                            <span class="place-slot__label"><?= esc($slot['label']) ?></span>
                            <span class="place-slot__group"><?= esc($slot['group']) ?></span>
                        </span>
                        <span class="place-slot__arrow">→</span>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="form__row" style="margin-top:16px;">
                <button type="submit" class="btn btn--gold" disabled id="placeSubmit">Place this image</button>
                <button type="button" class="btn btn--ghost" data-place-close>Cancel</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
