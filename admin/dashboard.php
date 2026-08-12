<?php
/**
 * Owere & Associates — admin dashboard (inquiry lead viewer).
 * Supports POST actions (status change, delete) and AJAX filtering
 * (?ajax=1&status=..&q=..) that returns only the table rows.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Leads Dashboard';
$activeNav = 'dashboard';

require_login(); // redirects to the admin login page when signed out

/* ---------------------------------------------------------------------------
 * Handle POST actions
 * ------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'status') {
        $id     = (int)($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        if ($id > 0 && in_array($status, ['new', 'contacted', 'closed'], true)) {
            $stmt = db()->prepare('UPDATE inquiries SET status = ? WHERE id = ?');
            $stmt->execute([$status, $id]);
            log_admin_activity('lead_status', 'Lead #' . $id . ' marked as ' . $status);
            flash('success', 'Lead #' . $id . ' marked as ' . $status . '.');
        }
        redirect('dashboard.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = db()->prepare('DELETE FROM inquiries WHERE id = ?');
            $stmt->execute([$id]);
            log_admin_activity('lead_delete', 'Deleted lead #' . $id);
            flash('success', 'Lead #' . $id . ' deleted.');
        }
        redirect('dashboard.php');
    }
}

/* ---------------------------------------------------------------------------
 * Filters
 * ------------------------------------------------------------------------- */
$status = (string)($_GET['status'] ?? '');
$q      = trim((string)($_GET['q'] ?? ''));

$where  = ['1 = 1'];
$params = [];
if ($status !== '' && in_array($status, ['new', 'contacted', 'closed'], true)) {
    $where[] = 'status = ?';
    $params[] = $status;
}
if ($q !== '') {
    // Escape LIKE wildcards so user input is matched literally
    // (backslash is MySQL's default escape character).
    $q = addcslashes($q, '%_\\');
    $where[] = '(client_name LIKE ? OR company_name LIKE ? OR email LIKE ? OR phone LIKE ? OR service_requested LIKE ? OR message LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);

$stmt = db()->prepare(
    "SELECT id, client_name, company_name, email, phone, service_requested, preferred_date, message, status, created_at
     FROM inquiries
     WHERE {$whereSql}
     ORDER BY created_at DESC"
);
$stmt->execute($params);
$leads = $stmt->fetchAll();

/* Stats (always global, unaffected by filters) */
$stats = db()->query(
    "SELECT
        COUNT(*) AS total,
        SUM(status = 'new')      AS new_count,
        SUM(status = 'contacted') AS contacted_count,
        SUM(status = 'closed')   AS closed_count,
        SUM(created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS week_count
     FROM inquiries"
)->fetch();

function render_lead_rows(array $rows): void
{
    foreach ($rows as $lead):
        $id = (int)$lead['id'];
        $current = (string)$lead['status'];
        ?>
        <tr data-lead-row>
            <td><span class="table__name">#<?= $id ?></span></td>
            <td>
                <div class="table__name"><?= esc($lead['client_name']) ?></div>
                <div class="table__muted"><?= esc($lead['company_name'] ?: '—') ?></div>
            </td>
            <td>
                <div><a href="mailto:<?= esc($lead['email']) ?>" class="table__muted" style="text-decoration:underline;"><?= esc($lead['email']) ?></a></div>
                <div class="table__muted"><?= esc($lead['phone']) ?></div>
            </td>
            <td><span class="table__muted"><?= esc($lead['service_requested']) ?></span></td>
            <td>
                <div class="table__muted" style="max-width:220px;" title="<?= esc($lead['message']) ?>"><?= esc(truncate($lead['message'], 90)) ?></div>
                <?php if (!empty($lead['preferred_date'])): ?>
                    <div class="table__muted" style="color:var(--gold-700);">Preferred: <?= esc(format_date($lead['preferred_date'], 'M j, Y')) ?></div>
                <?php endif; ?>
            </td>
            <td>
                <form method="post" action="dashboard.php" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
                    <input type="hidden" name="action" value="status">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <select name="status" data-status-select>
                        <option value="new"       <?= $current === 'new'       ? 'selected' : '' ?>>New</option>
                        <option value="contacted" <?= $current === 'contacted' ? 'selected' : '' ?>>Contacted</option>
                        <option value="closed"    <?= $current === 'closed'    ? 'selected' : '' ?>>Closed</option>
                    </select>
                </form>
            </td>
            <td><span class="table__date"><?= esc(format_date($lead['created_at'])) ?><br><?= esc(time_ago($lead['created_at'])) ?></span></td>
            <td>
                <div class="table__actions">
                    <form method="post" action="dashboard.php" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <button type="submit" class="icon-btn" data-confirm="Delete lead #<?= $id ?> permanently?" aria-label="Delete lead #<?= $id ?>">
                            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                    </form>
                </div>
            </td>
        </tr>
    <?php endforeach;
}

/* ---------------------------------------------------------------------------
 * AJAX: return only table rows (before any HTML is output)
 * ------------------------------------------------------------------------- */
if (($_GET['ajax'] ?? '') === '1') {
    render_lead_rows($leads);
    exit;
}

require_once __DIR__ . '/../includes/admin-header.php';
?>
<div class="cards">
    <div class="card">
        <div class="card__label">Total leads</div>
        <div class="card__value"><?= (int)$stats['total'] ?></div>
        <div class="card__hint"><?= (int)$stats['week_count'] ?> in the last 7 days</div>
    </div>
    <div class="card">
        <div class="card__label">New</div>
        <div class="card__value card__value--gold"><?= (int)$stats['new_count'] ?></div>
        <div class="card__hint">Awaiting first contact</div>
    </div>
    <div class="card">
        <div class="card__label">Contacted</div>
        <div class="card__value"><?= (int)$stats['contacted_count'] ?></div>
        <div class="card__hint">In conversation</div>
    </div>
    <div class="card">
        <div class="card__label">Closed</div>
        <div class="card__value"><?= (int)$stats['closed_count'] ?></div>
        <div class="card__hint">Converted or archived</div>
    </div>
</div>

<div class="panel">
    <div class="panel__head">
        <h2 class="panel__title">Consultation Leads</h2>
        <form class="filter-bar" id="leadFilterForm" method="get" action="dashboard.php">
            <select name="status" id="filterStatus">
                <option value="">All statuses</option>
                <option value="new"       <?= $status === 'new'       ? 'selected' : '' ?>>New</option>
                <option value="contacted" <?= $status === 'contacted' ? 'selected' : '' ?>>Contacted</option>
                <option value="closed"    <?= $status === 'closed'    ? 'selected' : '' ?>>Closed</option>
            </select>
            <input type="search" name="q" id="filterQ" value="<?= esc($q) ?>" placeholder="Search name, company, email, service…">
            <noscript><button class="btn btn--navy btn--sm" type="submit">Filter</button></noscript>
        </form>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Client</th>
                    <th>Contact</th>
                    <th>Service</th>
                    <th>Message</th>
                    <th>Status</th>
                    <th>Received</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="leadsTableBody">
                <?php if (empty($leads)): ?>
                    <tr><td colspan="8" style="text-align:center;color:var(--slate);padding:40px;">No leads match your filters yet. New enquiries will appear here instantly.</td></tr>
                <?php else: ?>
                    <?php render_lead_rows($leads); ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <p class="table__muted" style="margin-top:14px;" id="rowCount"><?= count($leads) ?> lead<?= count($leads) === 1 ? '' : 's' ?></p>
</div>

<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
