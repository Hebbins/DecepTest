<?php
require_once 'header.php';

// Fetch initial list of groups to build the sidebar
$groups = $pdo->query("SELECT g.id, g.name, (SELECT COUNT(*) FROM targets t WHERE t.group_id = g.id) as target_count FROM target_groups g ORDER BY g.name")->fetchAll();
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0">Manage Targets & Groups</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#groupModal" onclick="prepareGroupModal()"><i class="bi bi-plus-circle me-2"></i>New Group</button>
</div>

<div id="notification-area"></div>

<div class="row">
    <!-- Groups List (Sidebar) -->
    <div class="col-md-4">
        <div class="card shadow">
            <div class="card-header">
                <h6>Target Groups</h6>
            </div>
            <div class="list-group list-group-flush" id="group-list">
                <?php foreach ($groups as $group): ?>
                    <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center group-item" data-group-id="<?php echo $group['id']; ?>">
                        <div>
                            <span class="fw-bold group-name-<?php echo $group['id']; ?>"><?php echo htmlspecialchars($group['name']); ?></span><br>
                            <small class="text-muted target-count-<?php echo $group['id']; ?>"><?php echo $group['target_count']; ?> targets</small>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-outline-success me-1" onclick="prepareGroupModal(<?php echo $group['id']; ?>, '<?php echo htmlspecialchars($group['name'], ENT_QUOTES); ?>'); event.stopPropagation();" title="Edit Group"><i class="bi bi-pencil-fill"></i></button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteGroup(<?php echo $group['id']; ?>); event.stopPropagation();" title="Delete Group"><i class="bi bi-trash-fill"></i></button>
                        </div>
                    </a>
                <?php endforeach; ?>
                 <?php if(empty($groups)): ?>
                    <div class="list-group-item text-center text-muted">No groups created yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Targets View (Main Content) -->
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 id="targets-header" class="mb-0">Select a group to view targets</h6>
                <div id="targets-actions" style="display: none;">
                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#bulkAddModal" title="Bulk Add Targets"><i class="bi bi-upload me-1"></i> Bulk Add</button>
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#targetModal" onclick="prepareTargetModal()" title="Add Single Target"><i class="bi bi-plus-circle me-1"></i> Add Target</button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead><tr><th>Name</th><th>Email</th><th class="text-end">Actions</th></tr></thead>
                        <tbody id="target-list-container">
                            <tr><td colspan="3" class="text-center text-muted">No group selected.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<div class="modal fade" id="groupModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form id="groupForm"><div class="modal-header"><h5 class="modal-title" id="groupModalTitle">New Group</h5></div><div class="modal-body"><input type="hidden" name="group_id" id="group_id_input"><label class="form-label">Group Name</label><input type="text" class="form-control" name="group_name" id="group_name_input" required></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Save</button></div></form></div></div></div>
<div class="modal fade" id="targetModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form id="targetForm"><div class="modal-header"><h5 class="modal-title" id="targetModalTitle">New Target</h5></div><div class="modal-body"><input type="hidden" name="target_id"><input type="hidden" name="group_id" id="target_group_id_input"><div class="mb-3"><label class="form-label">Name</label><input type="text" class="form-control" name="name" required></div><div class="mb-3"><label class="form-label">Email Address</label><input type="email" class="form-control" name="email" required></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Save</button></div></form></div></div></div>
<div class="modal fade" id="bulkAddModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><form id="bulkAddForm"><div class="modal-header"><h5 class="modal-title">Bulk Add Targets</h5></div><div class="modal-body"><div class="mb-3"><label class="form-label">Paste Data (CSV format: Name,Email)</label><textarea name="bulk_data" class="form-control" rows="10" placeholder="John Doe,john.doe@client.com&#10;Jane Smith,jane.smith@client.com"></textarea></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Add Targets</button></div></form></div></div></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let activeGroupId = null;
    const groupModal = new bootstrap.Modal(document.getElementById('groupModal'));
    const targetModal = new bootstrap.Modal(document.getElementById('targetModal'));
    const bulkAddModal = new bootstrap.Modal(document.getElementById('bulkAddModal'));
    
    function showNotification(message, type = 'success') {
        document.getElementById('notification-area').innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    }

    function fetchTargets(groupId) {
        activeGroupId = groupId;
        sessionStorage.setItem('lastActiveGroupId', groupId); // Store the active group ID
        
        document.querySelectorAll('.group-item').forEach(el => el.classList.remove('active'));
        const activeGroupElement = document.querySelector(`.group-item[data-group-id='${groupId}']`);
        if (activeGroupElement) {
            activeGroupElement.classList.add('active');
        }
        
        const groupNameEl = document.querySelector(`.group-name-${groupId}`);
        if(!groupNameEl) return;
        const groupName = groupNameEl.textContent;
        document.getElementById('targets-header').textContent = `Targets in: ${groupName}`;
        document.getElementById('targets-actions').style.display = 'flex';
        document.getElementById('target_group_id_input').value = groupId;

        const container = document.getElementById('target-list-container');
        container.innerHTML = '<tr><td colspan="3" class="text-center"><div class="spinner-border spinner-border-sm"></div></td></tr>';

        fetch(`api.php?action=get_targets&group_id=${groupId}`)
            .then(res => res.json()).then(data => {
                container.innerHTML = '';
                if (data.success && data.targets.length > 0) {
                    data.targets.forEach(t => {
                        container.innerHTML += `
                            <tr>
                                <td>${t.name}</td><td>${t.email}</td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary" onclick="prepareTargetModal(${t.id}, '${t.name.replace(/'/g, "\\'")}', '${t.email}')" title="Edit Target"><i class="bi bi-pencil-fill"></i></button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteTarget(${t.id})" title="Delete Target"><i class="bi bi-trash-fill"></i></button>
                                </td>
                            </tr>`;
                    });
                } else {
                    container.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No targets in this group.</td></tr>';
                }
            });
    }

    window.prepareGroupModal = (id = null, name = '') => {
        document.getElementById('groupForm').reset();
        document.getElementById('group_id_input').value = id;
        document.getElementById('group_name_input').value = name;
        document.getElementById('groupModalTitle').textContent = id ? 'Edit Group' : 'New Group';
        groupModal.show();
    };

    window.prepareTargetModal = (id = null, name = '', email = '') => {
        document.getElementById('targetForm').reset();
        const form = document.getElementById('targetForm');
        form.querySelector('[name="target_id"]').value = id;
        form.querySelector('[name="name"]').value = name;
        form.querySelector('[name="email"]').value = email;
        document.getElementById('targetModalTitle').textContent = id ? 'Edit Target' : 'New Target';
        targetModal.show();
    };
    
    window.deleteGroup = (id) => { 
        if (confirm('Are you sure? Deleting a group will also delete all targets within it.')) {
            sessionStorage.removeItem('lastActiveGroupId'); // Clear stored group on delete
            submitApiForm('delete_group', { group_id: id }); 
        }
    };
    window.deleteTarget = (id) => { if (confirm('Are you sure?')) submitApiForm('delete_target', { target_id: id }); };

    function submitApiForm(action, data) {
        fetch(`api.php?action=${action}`, { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data) })
            .then(res => res.json()).then(result => {
                showNotification(result.message || result.error, result.success ? 'success' : 'danger');
                if (result.success) {
                    groupModal.hide(); targetModal.hide(); bulkAddModal.hide();
                    setTimeout(() => window.location.reload(), 1000);
                }
            });
    }

    document.querySelectorAll('.group-item').forEach(item => item.addEventListener('click', (e) => { e.preventDefault(); fetchTargets(item.dataset.groupId); }));
    document.getElementById('groupForm').addEventListener('submit', (e) => { e.preventDefault(); submitApiForm('save_group', Object.fromEntries(new FormData(e.target))); });
    document.getElementById('targetForm').addEventListener('submit', (e) => { e.preventDefault(); submitApiForm('save_target', Object.fromEntries(new FormData(e.target))); });
    document.getElementById('bulkAddForm').addEventListener('submit', (e) => { e.preventDefault(); const data = Object.fromEntries(new FormData(e.target)); data.group_id = activeGroupId; submitApiForm('bulk_add_targets', data); });

    // On page load, check for a stored active group and select it
    const lastActiveGroupId = sessionStorage.getItem('lastActiveGroupId');
    if (lastActiveGroupId) {
        const groupElement = document.querySelector(`.group-item[data-group-id='${lastActiveGroupId}']`);
        if (groupElement) {
            groupElement.click();
        } else {
            // The group might have been deleted, so clear the storage
            sessionStorage.removeItem('lastActiveGroupId');
        }
    }
});
</script>
<?php require_once 'footer.php'; ?>
