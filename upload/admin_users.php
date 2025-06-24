<?php
require_once 'header.php';

if ($_SESSION['user_role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$users = $pdo->query("SELECT id, display_name, email, role, is_active FROM users ORDER BY display_name")->fetchAll();
?>
<h1 class="h3 mb-4">Manage DecepTest Users</h1>
<div class="card shadow">
    <div class="card-header">
        <p class="mb-0">This section lists employees who have logged in. You can activate their accounts and grant admin rights.</p>
    </div>
    <div class="card-body">
        <div id="admin-user-notification"></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['display_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><span class="badge fs-6 bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'secondary'; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                        <td><span class="badge fs-6 bg-<?php echo $user['is_active'] ? 'success' : 'warning'; ?>"><?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                        <td>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-primary" onclick="toggleUserStatus(<?php echo $user['id']; ?>, 'is_active')">Toggle Active</button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="toggleUserStatus(<?php echo $user['id']; ?>, 'role')">Toggle Admin</button>
                            </div>
                            <?php else: ?>
                                <span class="text-muted fst-italic">Cannot edit self</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function showAdminUserNotification(message, type = 'success') {
    const container = document.getElementById('admin-user-notification');
    container.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
}

function toggleUserStatus(userId, field) {
    let message = `Are you sure you want to change this user's ${field}?`;
    if (!confirm(message)) return;

    fetch('api.php?action=toggle_user_status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId, field: field })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            showAdminUserNotification('Error: ' + data.error, 'danger');
        }
    });
}
</script>

<?php require_once 'footer.php'; ?>
