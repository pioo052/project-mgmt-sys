<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_login();

$user = current_user();
$userId = (int) $user['id'];

function safe_notification_link(?string $link): ?string
{
    $link = trim((string) $link);
    if ($link === '' || preg_match('#^(https?:)?//#i', $link)) {
        return null;
    }
    return url(ltrim($link, '/'));
}

if (is_post()) {
    $csrfError = verify_csrf();
    if ($csrfError) {
        flash('danger', $csrfError);
        redirect('notifications.php');
    }
    $do = (string) ($_POST['do'] ?? '');
    $noteId = (int) ($_POST['id'] ?? 0);

    if ($do === 'mark_read' && $noteId > 0) {
        db_exec('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?', [$noteId, $userId]);
        flash('success', 'Notification marked as read.');
    } elseif ($do === 'mark_unread' && $noteId > 0) {
        db_exec('UPDATE notifications SET is_read = 0 WHERE id = ? AND user_id = ?', [$noteId, $userId]);
        flash('success', 'Notification marked as unread.');
    } elseif ($do === 'mark_all') {
        $count = db_exec('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', [$userId]);
        flash('success', $count . ' notification' . ($count === 1 ? '' : 's') . ' marked as read.');
    } elseif ($do === 'delete' && $noteId > 0) {
        db_exec('DELETE FROM notifications WHERE id = ? AND user_id = ?', [$noteId, $userId]);
        flash('success', 'Notification removed.');
    }
    redirect('notifications.php' . (!empty($_POST['return']) ? '?' . $_POST['return'] : ''));
}

$status = (string) ($_GET['status'] ?? 'all');
if (!in_array($status, ['all', 'unread', 'read'], true)) {
    $status = 'all';
}
$q = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));

$where = ['user_id = ?'];
$params = [$userId];
if ($status === 'unread') {
    $where[] = 'is_read = 0';
} elseif ($status === 'read') {
    $where[] = 'is_read = 1';
}
if ($q !== '') {
    $where[] = '(title LIKE ? OR message LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
$whereSql = implode(' AND ', $where);

$total = (int) db_value("SELECT COUNT(*) FROM notifications WHERE $whereSql", $params);
$pager = paginate($total, 12, $page);
$rows = db_rows(
    "SELECT * FROM notifications WHERE $whereSql ORDER BY created_at DESC LIMIT {$pager['per_page']} OFFSET {$pager['offset']}",
    $params
);
$unread = (int) db_value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$userId]);

$pageTitle = 'Notifications';
$pageDescription = 'Messages about proposals, milestones, funding decisions and research activity assigned to you.';
$breadcrumbs = [['label' => 'System', 'url' => null], ['label' => 'Notifications']];
$activeNav = 'notifications';
$returnQuery = http_build_query(['status' => $status, 'q' => $q, 'page' => $page]);

$pageActions = $unread > 0
    ? '<form method="post" action="' . e(url('notifications.php')) . '" data-no-loading>' . csrf_field()
    . '<input type="hidden" name="do" value="mark_all"><input type="hidden" name="return" value="' . e($returnQuery) . '">'
    . '<button class="btn btn--secondary" type="submit">' . icon('check', 17) . 'Mark all as read</button></form>'
    : '';

include ROOT_PATH . '/includes/header.php';
?>

<div class="card card--flush">
    <div class="toolbar">
        <div class="tabs" style="border-bottom:0;gap:4px" role="tablist" aria-label="Notification filters">
            <?php foreach (['all' => 'All', 'unread' => 'Unread', 'read' => 'Read'] as $key => $label): ?>
                <a class="tab <?= $status === $key ? 'is-active' : '' ?>" role="tab" aria-selected="<?= $status === $key ? 'true' : 'false' ?>"
                   href="?<?= e(query_string(['status' => $key === 'all' ? null : $key, 'page' => null])) ?>">
                    <?= e($label) ?><?= $key === 'unread' && $unread > 0 ? ' <span class="badge badge--info">' . $unread . '</span>' : '' ?>
                </a>
            <?php endforeach; ?>
        </div>

        <form class="toolbar__search" method="get" action="<?= e(url('notifications.php')) ?>" role="search">
            <label class="sr-only" for="notification-search">Search notifications</label>
            <span class="field-icon"><?= icon('search', 18) ?></span>
            <input class="input" id="notification-search" type="search" name="q" value="<?= e($q) ?>" placeholder="Search notifications…">
            <?php if ($status !== 'all'): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
            <button class="btn btn--secondary" type="submit">Search</button>
            <?php if ($q !== '' || $status !== 'all'): ?>
                <a class="btn btn--ghost" href="<?= e(url('notifications.php')) ?>">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if ($rows): ?>
        <ul class="inbox">
            <?php foreach ($rows as $note):
                $tone = in_array($note['type'], ['success', 'warning', 'danger', 'info'], true) ? $note['type'] : 'info';
                $link = safe_notification_link($note['link']);
                $isUnread = (int) $note['is_read'] === 0;
                ?>
                <li class="inbox__item <?= $isUnread ? 'is-unread' : '' ?>">
                    <span class="inbox__icon inbox__icon--<?= e($tone) ?>"><?= icon($tone === 'danger' ? 'alert' : ($tone === 'success' ? 'check' : 'bell'), 18) ?></span>
                    <div class="inbox__body">
                        <div class="inbox__head">
                            <p class="inbox__title">
                                <?php if ($isUnread): ?><span class="inbox__dot" aria-label="Unread"></span><?php endif; ?>
                                <?= e($note['title']) ?>
                            </p>
                            <span class="inbox__time"><?= e(time_ago($note['created_at'])) ?></span>
                        </div>
                        <p class="inbox__message"><?= e($note['message']) ?></p>
                        <div class="inbox__actions">
                            <?php if ($link): ?>
                                <a class="btn btn--link" href="<?= e($link) ?>">Open related module <?= icon('arrow-right', 14) ?></a>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('notifications.php')) ?>" data-no-loading>
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $note['id'] ?>">
                                <input type="hidden" name="return" value="<?= e($returnQuery) ?>">
                                <?php if ($isUnread): ?>
                                    <button class="btn btn--link" type="submit" name="do" value="mark_read">Mark as read</button>
                                <?php else: ?>
                                    <button class="btn btn--link" type="submit" name="do" value="mark_unread">Mark as unread</button>
                                <?php endif; ?>
                            </form>
                            <form method="post" action="<?= e(url('notifications.php')) ?>" data-no-loading>
                                <?= csrf_field() ?>
                                <input type="hidden" name="do" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $note['id'] ?>">
                                <input type="hidden" name="return" value="<?= e($returnQuery) ?>">
                                <button class="btn btn--link text-danger" type="submit">Delete</button>
                            </form>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="table-footer">
            <p class="text-sm text-secondary">Showing <strong><?= $pager['from'] ?>–<?= $pager['to'] ?></strong> of <strong><?= $pager['total'] ?></strong> notifications</p>
            <?= pagination_links($pager) ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state__icon"><?= icon('bell', 26) ?></div>
            <h2><?= $q !== '' ? 'No matching notifications' : 'Your inbox is clear' ?></h2>
            <p class="text-secondary"><?= $q !== '' ? 'Try another search term or clear the filters.' : 'New messages about your research activity will appear here.' ?></p>
            <?php if ($q !== '' || $status !== 'all'): ?>
                <a class="btn btn--secondary" href="<?= e(url('notifications.php')) ?>">Clear filters</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php include ROOT_PATH . '/includes/footer.php'; ?>
