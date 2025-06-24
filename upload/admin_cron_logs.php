<?php
require_once 'header.php';

if (($_SESSION['user_role'] ?? 'user') !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$page = max($page, 1);
$limit = 25;
$offset = ($page - 1) * $limit;

// --- Get total count for pagination ---
$sql_count = "SELECT COUNT(*) FROM cron_logs";
$params_count = [];
if (!empty($search)) {
    $sql_count .= " WHERE message LIKE :search";
    $params_count[':search'] = '%' . $search . '%';
}
$total_stmt = $pdo->prepare($sql_count);
$total_stmt->execute($params_count);
$total_logs = $total_stmt->fetchColumn();
$total_pages = ceil($total_logs / $limit);

// --- Get paginated results ---
$sql_select = "SELECT * FROM cron_logs";
if (!empty($search)) {
    $sql_select .= " WHERE message LIKE :search";
}
$sql_select .= " ORDER BY run_time DESC LIMIT :limit OFFSET :offset";

$logs_stmt = $pdo->prepare($sql_select);

// Bind parameters
if (!empty($search)) {
    $logs_stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
}
$logs_stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$logs_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

$logs_stmt->execute();
$logs = $logs_stmt->fetchAll();
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0">Cron Job Logs</h1>
</div>

<div class="card shadow">
    <div class="card-header">
        <form method="GET" class="d-flex">
            <input type="text" name="search" class="form-control me-2" placeholder="Search log messages..." value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn btn-primary">Search</button>
        </form>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-sm">
                <thead><tr><th>Time</th><th>Level</th><th>Message</th></tr></thead>
                <tbody>
                <?php if(empty($logs)): ?>
                    <tr><td colspan="3" class="text-center text-muted">No logs found.</td></tr>
                <?php endif; ?>
                <?php foreach($logs as $log): 
                    $badge_class = 'secondary';
                    if($log['level'] === 'SUCCESS') $badge_class = 'success';
                    if($log['level'] === 'ERROR') $badge_class = 'danger';
                ?>
                    <tr>
                        <td style="white-space: nowrap;"><?php echo date('d M Y, H:i:s', strtotime($log['run_time'])); ?></td>
                        <td><span class="badge bg-<?php echo $badge_class; ?>"><?php echo $log['level']; ?></span></td>
                        <td><?php echo htmlspecialchars($log['message']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer">
        <nav>
            <ul class="pagination justify-content-center mb-0">
                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    </div>
</div>

<?php require_once 'footer.php'; ?>
