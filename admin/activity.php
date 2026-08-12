<?php
/**
 * Owere & Associates — admin activity log.
 * Shows who did what in the panel (logins, content saves, settings changes,
 * uploads, deletions…), with filters and a clear-log action.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_login();

// The activity log is an owner-only audit trail — staff accounts can't see it.
if (!is_owner()) {
    flash('error', 'Only the original administrator can view the activity log.');
    redirect('dashboard.php');
}

ensure_activity_table();

$pageTitle = 'Activity Log';
$activeNav = 'activity';

/* Human labels + badge colour per action key. */
const ACTIVITY_ACTIONS = [
    'login'           => ['Login',                 'badge--closed'],
    'login_failed'    => ['Failed login',          'badge--danger'],
    'logout'          => ['Logout',                'badge--navy'],
    'profile_update'  => ['Profile updated',       'badge--gold'],
    'password_change' => ['Password changed',      'badge--gold'],
    'settings_site'   => ['Contact settings',      'badge--gold'],
    'settings_mail'   => ['Mail settings',         'badge--gold'],
    'content_save'    => ['Content saved',         'badge--navy'],
    'content_reset'   => ['Content reset',         'badge--navy'],
    'logo_add'        => ['Logo added',            'badge--contacted'],
    'logo_toggle'     => ['Logo visibility',       'badge--contacted'],
    'logo_delete'     => ['Logo deleted',          'badge--danger'],
    'media_upload'    => ['Image uploaded',        'badge--contacted'],
    'media_place'     => ['Image placed',          'badge--contacted'],
    'media_delete'    => ['Image deleted',         'badge--danger'],
    'lead_status'     => ['Lead status changed',   'badge--new'],
    'lead_delete'     => ['Lead deleted',          'badge--danger'],
    'user_create'     => ['Admin created',         'badge--gold'],
    'user_update'     => ['Admin updated',         'badge--gold'],
    'user_password_reset' => ['Password reset',    'badge--gold'],
    'user_delete'     => ['Admin deleted',         'badge--danger'],
    'log_clear'       => ['Log cleared',           'badge--danger'],
];

/* ---------------------------------------------------------------------------
 * POST: clear the log
 * ------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'clear') {
        try {
            db()->exec('DELETE FROM admin_activity');
            log_admin_activity('log_clear', 'Cleared the activity log');
            flash('success', 'Activity log cleared.');
        } catch (PDOException $e) {
            flash('error', 'Could not clear the log.');
        }
        redirect('activity.php');
    }
}

/* ---------------------------------------------------------------------------
 * Filters + query
 * ------------------------------------------------------------------------- */
$fAction = (string)($_GET['action'] ?? '');
$q       = trim((string)($_GET['q'] ?? ''));

$where  = ['1 = 1'];
$params = [];
if ($fAction !== '' && isset(ACTIVITY_ACTIONS[$fAction])) {
    $where[]  = 'action = ?';
    $params[] = $fAction;
}
if ($q !== '') {
    $q = addcslashes($q, '%_\\');
    $where[] = '(username LIKE ? OR details LIKE ? OR action LIKE ? OR ip LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$rows = [];
$stats = ['total' => 0, 'today' => 0, 'failed' => 0, 'content' => 0];
try {
    $stmt = db()->prepare(
        "SELECT id, username, action, details, ip, created_at
         FROM admin_activity
         WHERE {$whereSql}
         ORDER BY created_at DESC, id DESC
         LIMIT 300"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $stats['total']   = (int)db()->query('SELECT COUNT(*) FROM admin_activity')->fetchColumn();
    $stats['today']   = (int)db()->query("SELECT COUNT(*) FROM admin_activity WHERE created_at >= CURDATE()")->fetchColumn();
    $stats['failed']  = (int)db()->query("SELECT COUNT(*) FROM admin_activity WHERE action = 'login_failed'")->fetchColumn();
    $stats['content'] = (int)db()->query("SELECT COUNT(*) FROM admin_activity WHERE action = 'content_save'")->fetchColumn();
} catch (PDOException) {
    // Table missing or DB down — show empty state.
}

require_once __DIR__ . '/../includes/admin-header.php';
?>

<div class="cards">
    <div class="card">
        <div class="card__label">Logged actions</div>
        <div class="card__value"><?= (int)$stats['total'] ?></div>
        <div class="card__hint">All time</div>
    </div>
    <div class="card">
        <div class="card__label">Today</div>
        <div class="card__value card__value--gold"><?= (int)$stats['today'] ?></div>
        <div class="card__hint">So far today</div>
    </div>
    <div class="card">
        <div class="card__label">Failed logins</div>
        <div class="card__value"><?= (int)$stats['failed'] ?></div>
        <div class="card__hint">Watch for suspicious activity</div>
    </div>
    <div class="card">
        <div class="card__label">Content saves</div>
        <div class="card__value"><?= (int)$stats['content'] ?></div>
        <div class="card__hint">Website edits published</div>
    </div>
</div>

<div class="panel">
    <div class="panel__head">
        <h2 class="panel__title">Activity Log</h2>
        <form class="filter-bar" id="activityFilterForm" method="get" action="activity.php">
            <select name="action" id="filterAction">
                <option value="">All actions</option>
                <?php foreach (ACTIVITY_ACTIONS as $key => $meta): ?>
                    <option value="<?= esc($key) ?>" <?= $fAction === $key ? 'selected' : '' ?>><?= esc($meta[0]) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="search" name="q" id="filterQ" value="<?= esc($q) ?>" placeholder="Search admin, action, details…">
            <noscript><button class="btn btn--navy btn--sm" type="submit">Filter</button></noscript>
        </form>
        <form method="post" action="activity.php" style="display:inline;">
            <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
            <input type="hidden" name="action" value="clear">
            <button type="submit" class="btn btn--danger btn--sm" data-confirm="Clear the entire activity log? This cannot be undone.">Clear log</button>
        </form>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Admin</th>
                    <th>Action</th>
                    <th>Details</th>
                    <th>IP address</th>
                </tr>
            </thead>
            <tbody id="activityTableBody">
                <?php if (empty($rows)): ?>
                    <tr><td colspan="5" style="text-align:center;color:var(--slate);padding:40px;">
                        No activity recorded yet. Actions in the panel (logins, saves, uploads, deletions…) appear here automatically.
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($rows as $row):
                        [$label, $badge] = ACTIVITY_ACTIONS[(string)$row['action']] ?? [ucfirst((string)$row['action']), 'badge--navy'];
                        ?>
                        <tr>
                            <td>
                                <span class="table__date"><?= esc(format_date($row['created_at'], 'M j, Y g:i A')) ?></span><br>
                                <span class="table__muted"><?= esc(time_ago($row['created_at'])) ?></span>
                            </td>
                            <td><span class="table__name"><?= esc($row['username']) ?></span></td>
                            <td><span class="badge <?= esc($badge) ?>"><?= esc($label) ?></span></td>
                            <td class="table__muted" style="max-width:340px;"><?= esc($row['details']) ?></td>
                            <td class="table__muted"><?= esc($row['ip']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="table__muted" style="margin-top:14px;" id="rowCount"><?= count($rows) ?> entr<?= count($rows) === 1 ? 'y' : 'ies' ?> shown (latest 300<?= $fAction !== '' || $q !== '' ? ' matching your filters' : '' ?>)</p>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
