<?php
require_once 'header.php';

if (($_SESSION['user_role'] ?? 'user') !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$mailers = $pdo->query("SELECT * FROM mailers ORDER BY name")->fetchAll();
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0">Manage Mailers</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#mailerModal" onclick="prepareMailerModal()"><i class="bi bi-plus-circle me-2"></i>New Mailer</button>
</div>
<div id="mailer-notification"></div>
<div class="card shadow">
    <div class="card-body">
        <p class="card-text text-muted">These are the SMTP configurations the application will use to send phishing emails. You can add multiple mailers to rotate sending servers.</p>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Name</th><th>Host</th><th>Auth Type</th><th>From Address</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php if(empty($mailers)): ?>
                    <tr><td colspan="5" class="text-center text-muted">No mailers configured yet.</td></tr>
                <?php endif; ?>
                <?php foreach($mailers as $mailer): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($mailer['name']); ?></td>
                        <td><?php echo htmlspecialchars($mailer['smtp_host']); ?>:<?php echo htmlspecialchars($mailer['smtp_port']); ?></td>
                        <td><span class="badge bg-secondary"><?php echo ucfirst($mailer['smtp_auth']); ?></span></td>
                        <td><?php echo htmlspecialchars($mailer['smtp_from_email']); ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary" onclick='prepareMailerModal(<?php echo json_encode($mailer, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>Edit</button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteMailer(<?php echo $mailer['id']; ?>)">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Mailer Modal -->
<div class="modal fade" id="mailerModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
<form id="mailerForm" novalidate>
    <div class="modal-header"><h5 class="modal-title" id="mailerModalTitle">Add New Mailer</h5></div>
    <div class="modal-body">
        <input type="hidden" name="mailer_id" id="mailer_id">
        <div class="mb-3"><label class="form-label">Configuration Name</label><input type="text" class="form-control" name="name" id="mailer_name" required></div>
        <div class="row">
            <div class="col-md-8"><div class="mb-3"><label class="form-label">SMTP Host</label><input type="text" class="form-control" name="smtp_host" id="mailer_host" required></div></div>
            <div class="col-md-4"><div class="mb-3"><label class="form-label">SMTP Port</label><input type="number" class="form-control" name="smtp_port" id="mailer_port" value="25" required></div></div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3"><label class="form-label">Authentication</label>
                    <select class="form-select" name="smtp_auth" id="mailer_auth">
                        <option value="none">None</option>
                        <option value="login">Login</option>
                    </select>
                </div>
            </div>
             <div class="col-md-6">
                <div class="mb-3"><label class="form-label">Security</label>
                    <select class="form-select" name="smtp_security" id="mailer_security">
                        <option value="none">None</option>
                        <option value="tls">TLS</option>
                        <option value="ssl">SSL</option>
                    </select>
                </div>
            </div>
        </div>
        <div id="auth-fields" style="display: none;">
            <div class="row">
                <div class="col-md-6"><div class="mb-3"><label class="form-label">SMTP Username</label><input type="text" class="form-control" name="smtp_username" id="mailer_username"></div></div>
                <div class="col-md-6"><div class="mb-3"><label class="form-label">SMTP Password</label><input type="password" class="form-control" name="smtp_password" id="mailer_password" autocomplete="new-password"><small class="form-text text-muted">Leave blank to keep existing password.</small></div></div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6"><div class="mb-3"><label class="form-label">From Name</label><input type="text" class="form-control" name="smtp_from_name" id="mailer_from_name" required></div></div>
            <div class="col-md-6"><div class="mb-3"><label class="form-label">From Email</label><input type="email" class="form-control" name="smtp_from_email" id="mailer_from_email" required></div></div>
        </div>
        <div id="test-connection-result" class="mt-3"></div>
    </div>
    <div class="modal-footer justify-content-between">
        <button type="button" class="btn btn-info" id="testMailerBtn">Test Connection</button>
        <div>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn btn-primary">Save Mailer</button>
        </div>
    </div>
</form>
</div></div></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mailerModal = new bootstrap.Modal(document.getElementById('mailerModal'));
    const mailerForm = document.getElementById('mailerForm');
    const testConnectionResultDiv = document.getElementById('test-connection-result');
    const authFieldsDiv = document.getElementById('auth-fields');
    const authSelect = document.getElementById('mailer_auth');
    const securitySelect = document.getElementById('mailer_security');
    const portInput = document.getElementById('mailer_port');
    const usernameInput = document.getElementById('mailer_username');
    const passwordInput = document.getElementById('mailer_password');

    const defaultPorts = { none: '25', tls: '587', ssl: '465' };

    function toggleAuthFields() {
        const isLoginAuth = authSelect.value === 'login';
        const isNewMailer = document.getElementById('mailer_id').value === '';
        authFieldsDiv.style.display = isLoginAuth ? 'block' : 'none';
        usernameInput.required = isLoginAuth;
        passwordInput.required = isLoginAuth && isNewMailer;
    }

    authSelect.addEventListener('change', toggleAuthFields);

    securitySelect.addEventListener('change', function() {
        const currentPort = portInput.value;
        const currentSecurity = this.value;

        // Check if the current port is one of the standard default ports.
        // If so, it means the user likely hasn't set a custom port, so we can auto-update it.
        if (Object.values(defaultPorts).includes(currentPort)) {
            portInput.value = defaultPorts[currentSecurity];
        }
    });

    function showNotification(message, type = 'success') {
        document.getElementById('mailer-notification').innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    }

    window.prepareMailerModal = (mailer = null) => {
        mailerForm.reset();
        testConnectionResultDiv.innerHTML = '';
        
        if (mailer) {
            document.getElementById('mailerModalTitle').textContent = 'Edit Mailer';
            document.getElementById('mailer_id').value = mailer.id;
            document.getElementById('mailer_name').value = mailer.name;
            document.getElementById('mailer_host').value = mailer.smtp_host;
            portInput.value = mailer.smtp_port;
            authSelect.value = mailer.smtp_auth || 'none';
            securitySelect.value = mailer.smtp_security || 'none';
            usernameInput.value = mailer.smtp_username;
            document.getElementById('mailer_from_name').value = mailer.smtp_from_name;
            document.getElementById('mailer_from_email').value = mailer.smtp_from_email;
            passwordInput.placeholder = "Leave blank to keep existing";
        } else {
            document.getElementById('mailerModalTitle').textContent = 'Add New Mailer';
            document.getElementById('mailer_id').value = '';
            portInput.value = '25'; // Default for new mailer
            authSelect.value = 'none'; // Default for new mailer
            securitySelect.value = 'none'; // Default for new mailer
            passwordInput.placeholder = "";
        }
        toggleAuthFields(); 
        mailerModal.show();
    };

    function submitApiForm(action, formData) {
        fetch(`api.php?action=${action}`, { method: 'POST', body: formData })
            .then(res => res.json()).then(result => {
                if (result.success) {
                    showNotification(result.message, 'success');
                    mailerModal.hide();
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showNotification(result.error, 'danger');
                }
            }).catch(err => showNotification('An unexpected error occurred.', 'danger'));
    }

    mailerForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        submitApiForm('save_mailer', formData);
    });

    document.getElementById('testMailerBtn').addEventListener('click', function() {
        const formData = new FormData(mailerForm);
        testConnectionResultDiv.innerHTML = `<div class="text-center"><div class="spinner-border spinner-border-sm"></div> Testing...</div>`;
        fetch(`api.php?action=test_mailer`, { method: 'POST', body: formData })
            .then(res => res.json()).then(result => {
                const alertClass = result.success ? 'alert-success' : 'alert-danger';
                testConnectionResultDiv.innerHTML = `<div class="alert ${alertClass} mb-0">${result.message || result.error}</div>`;
            });
    });

    window.deleteMailer = (id) => {
        if (!confirm('Are you sure you want to delete this mailer? Campaigns using it will fail to send emails.')) return;
        const formData = new FormData();
        formData.append('mailer_id', id);
        submitApiForm('delete_mailer', formData);
    };
});
</script>
<?php require_once 'footer.php'; ?>
