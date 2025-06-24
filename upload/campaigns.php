<?php
require_once 'header.php';

$action = $_GET['action'] ?? 'list';
$campaign_id = $_GET['id'] ?? null;
$campaign = null;
$selected_groups = [];
$selected_recipient_ids = []; // Initialize for edit mode

if ($action === 'list') {
    $campaigns = $pdo->query("SELECT * FROM campaigns ORDER BY created_at DESC")->fetchAll();
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0">Campaigns</h1>
    <a href="?action=new" class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i>New Campaign</a>
</div>
<div id="campaign-list-notification"></div>
<div class="card shadow"><div class="card-body"><div class="table-responsive">
<table class="table table-hover align-middle">
    <thead><tr><th>Name</th><th>Status</th><th>Dates</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if(empty($campaigns)): ?>
        <tr><td colspan="4" class="text-center text-muted">No campaigns have been created yet.</td></tr>
    <?php endif; ?>
    <?php foreach($campaigns as $c): ?>
        <tr>
            <td><?php echo htmlspecialchars($c['name']); ?></td>
            <td><span class="badge bg-primary"><?php echo ucfirst($c['status']);?></span></td>
            <td><?php echo date('d M Y H:i', strtotime($c['start_date'])) . ' to ' . date('d M Y H:i', strtotime($c['end_date']));?></td>
            <td>
                <a href="reports.php?campaign_id=<?php echo $c['id'];?>" class="btn btn-sm btn-outline-info" title="View Report">Report</a>
                <a href="?action=edit&id=<?php echo $c['id'];?>" class="btn btn-sm btn-outline-primary" title="Edit Campaign">Edit</a>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteCampaign(<?php echo $c['id']; ?>)" title="Delete Campaign">Delete</button>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div></div></div>
<script>
function deleteCampaign(campaignId) {
    if (confirm('Are you sure you want to delete this campaign? This will also delete all associated recipients and tracking data. This action cannot be undone.')) {
        fetch('api.php?action=delete_campaign', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ campaign_id: campaignId })
        })
        .then(res => res.json())
        .then(data => {
            const notifArea = document.getElementById('campaign-list-notification');
            if (data.success) {
                notifArea.innerHTML = `<div class="alert alert-success">${data.message}</div>`;
                setTimeout(() => window.location.reload(), 1500);
            } else {
                notifArea.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
            }
        });
    }
}
</script>

<?php } else { // 'new' or 'edit' form
    // Fetch data for the form
    $mailers = $pdo->query("SELECT id, name FROM mailers ORDER BY name")->fetchAll();
    $target_groups = $pdo->query("SELECT id, name FROM target_groups ORDER BY name")->fetchAll();
    $template_files = glob(__DIR__ . '/templates/*.tplt');
    $default_redirect_url = '';

    // Fetch the default redirect URL from settings
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute(['default_redirect_url']);
        $default_redirect_url = $stmt->fetchColumn();
    } catch (PDOException $e) {
        // Silently fail, so the form can still load.
        error_log("Could not fetch default redirect URL: " . $e->getMessage());
    }

    if ($action === 'edit' && $campaign_id) {
        $stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = ?");
        $stmt->execute([$campaign_id]);
        $campaign = $stmt->fetch();
        
        $group_stmt = $pdo->prepare("SELECT DISTINCT g.id FROM target_groups g JOIN targets t ON g.id = t.group_id JOIN recipients r ON t.email = r.target_email WHERE r.campaign_id = ?");
        $group_stmt->execute([$campaign_id]);
        $selected_groups = $group_stmt->fetchAll(PDO::FETCH_COLUMN);

        $recipient_target_stmt = $pdo->prepare("SELECT t.id FROM targets t JOIN recipients r ON t.email = r.target_email WHERE r.campaign_id = ?");
        $recipient_target_stmt->execute([$campaign_id]);
        $selected_recipient_ids = $recipient_target_stmt->fetchAll(PDO::FETCH_COLUMN);
    }
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0"><?php echo $action === 'edit' ? 'Edit' : 'Create'; ?> Campaign</h1>
    <a href="campaigns.php" id="cancelBtn" class="btn btn-light">Cancel</a>
</div>
<div id="campaign-notification"></div>

<div class="card shadow">
<form id="campaignForm" novalidate>
    <input type="hidden" name="campaign_id" id="campaign_id" value="<?php echo htmlspecialchars($campaign_id ?? ''); ?>">
    <div class="card-header">
        <!-- Tab Navigation -->
        <ul class="nav nav-tabs card-header-tabs" id="campaignTab" role="tablist">
            <li class="nav-item" role="presentation"><button class="nav-link active" id="step1-tab" data-bs-toggle="tab" data-bs-target="#step1" type="button" role="tab">1. Details</button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" id="step2-tab" data-bs-toggle="tab" data-bs-target="#step2" type="button" role="tab">2. Mailer</button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" id="step3-tab" data-bs-toggle="tab" data-bs-target="#step3" type="button" role="tab">3. Targets</button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" id="step4-tab" data-bs-toggle="tab" data-bs-target="#step4" type="button" role="tab">4. Content</button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" id="step5-tab" data-bs-toggle="tab" data-bs-target="#step5" type="button" role="tab">5. Schedule</button></li>
        </ul>
    </div>

    <div class="card-body">
        <div class="tab-content" id="campaignTabContent">
            <!-- Step 1: Campaign Details -->
            <div class="tab-pane fade show active" id="step1" role="tabpanel">
                <h5 class="mb-3">Campaign Details</h5>
                <div class="mb-3"><label class="form-label">Campaign Name</label><input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($campaign['name'] ?? ''); ?>"></div>
                <div class="mb-3"><label class="form-label">Description (Optional)</label><input type="text" name="description" class="form-control" value="<?php echo htmlspecialchars($campaign['description'] ?? ''); ?>"></div>
            </div>

            <!-- Step 2: Mailer Setup -->
            <div class="tab-pane fade" id="step2" role="tabpanel">
                <h5 class="mb-3">Mailer Configuration</h5>
                 <div class="mb-3">
                    <label class="form-label d-flex justify-content-between">
                        <span>Select Mailer</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshMailersBtn" title="Refresh Mailer List"><i class="bi bi-arrow-clockwise"></i></button>
                    </label>
                    <select name="mailer_id" id="mailerSelect" class="form-select select2" required style="width:100%">
                        <?php foreach($mailers as $m): ?>
                            <option value="<?php echo $m['id']; ?>" <?php echo (($campaign['mailer_id'] ?? '') == $m['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($m['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Configure mailers in the <a href="admin_mailers.php" target="_blank">Admin Panel</a>.</div>
                </div>
                <hr>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="override_sender" id="overrideSenderCheck" <?php echo !empty($campaign['override_from_name']) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="overrideSenderCheck">Override default sender details for this campaign</label>
                </div>
                <div id="override-fields" style="<?php echo !empty($campaign['override_from_name']) ? '' : 'display: none;'; ?>">
                    <div class="row mt-3">
                        <div class="col-md-6 mb-3"><label class="form-label">Override From Name</label><input type="text" name="override_from_name" class="form-control" value="<?php echo htmlspecialchars($campaign['override_from_name'] ?? ''); ?>"></div>
                        <div class="col-md-6 mb-3"><label class="form-label">Override From Email</label><input type="email" name="override_from_email" class="form-control" value="<?php echo htmlspecialchars($campaign['override_from_email'] ?? ''); ?>"></div>
                    </div>
                </div>
            </div>

            <!-- Step 3: Target Setup -->
            <div class="tab-pane fade" id="step3" role="tabpanel">
                <h5 class="mb-3">Target Selection</h5>
                <div class="mb-3">
                     <label class="form-label d-flex justify-content-between">
                        <span>Select Target Groups</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshGroupsBtn" title="Refresh Group List"><i class="bi bi-arrow-clockwise"></i></button>
                    </label>
                    <select id="targetGroupSelect" name="target_groups[]" class="form-select select2" multiple="multiple" required style="width:100%">
                        <?php foreach($target_groups as $g):?>
                            <option value="<?php echo $g['id'];?>" <?php echo in_array($g['id'], $selected_groups) ? 'selected' : ''; ?>><?php echo htmlspecialchars($g['name']);?></option>
                        <?php endforeach;?>
                    </select>
                    <div class="form-text">Configure groups on the <a href="targets.php" target="_blank">Targets page</a>.</div>
                </div>
                <hr>
                <h6>Recipient List <small class="text-muted">(Deselect anyone to exclude from this campaign)</small></h6>
                <div id="recipientListContainer" style="max-height: 400px; overflow-y: auto; border: 1px solid var(--custom-border-color); padding: 10px;" class="rounded">
                    <p class="text-muted text-center">Select groups above to see recipients.</p>
                </div>
            </div>

            <!-- Step 4: Email Content -->
            <div class="tab-pane fade" id="step4" role="tabpanel">
                <h5 class="mb-3">Email Content & Redirect</h5>
                <div class="mb-3"><label class="form-label">Redirect URL (after a link is clicked)</label><input type="url" name="redirect_url" class="form-control" required placeholder="https://www.example.com/security-training" value="<?php echo htmlspecialchars($campaign['redirect_url'] ?? $default_redirect_url ?? ''); ?>"></div>
                <div class="mb-3"><label class="form-label">Subject</label><input type="text" name="email_subject" id="email_subject" class="form-control" required value="<?php echo htmlspecialchars($campaign['email_subject'] ?? ''); ?>"></div>
                 <div class="mb-3">
                    <label class="form-label">Select a Template (Optional)</label>
                    <select id="templateSelect" class="form-select select2" style="width:100%;">
                        <option value="">-- No Template --</option>
                        <?php foreach($template_files as $file): 
                            $name = basename($file);
                        ?>
                            <option value="<?php echo htmlspecialchars($name); ?>"><?php echo htmlspecialchars(basename($name, '.tplt')); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3"><label>Body (HTML)</label><textarea name="email_body" id="email_body" class="form-control" rows="12" required><?php echo htmlspecialchars($campaign['email_body'] ?? ''); ?></textarea></div>
                <div class="d-flex justify-content-between align-items-center">
                    <div><small class="text-muted">Placeholders:</small>
                        <button type="button" class="btn btn-sm btn-outline-secondary placeholder-btn" data-target="email_body" data-value="{TARGET_NAME}">Name</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary placeholder-btn" data-target="email_body" data-value="{TARGET_EMAIL}">Email</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary placeholder-btn" data-target="email_body" data-value="{TARGET_LINK}">Link</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary placeholder-btn" data-target="email_body" data-value="{RANDOM(#)}">Random(#)</button>
                    </div>
                    <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="save_as_template" id="save_as_template"><label class="form-check-label" for="save_as_template">Save as new template</label></div>
                </div>
            </div>

            <!-- Step 5: Schedule -->
            <div class="tab-pane fade" id="step5" role="tabpanel">
                <h5 class="mb-3">Schedule Campaign</h5>
                <p>Emails will be sent randomly to each recipient between these two dates.</p>
                <div class="row">
                    <div class="col-md-6 mb-3"><label>Start Date/Time</label><input type="datetime-local" name="start_date" class="form-control" required value="<?php echo !empty($campaign['start_date']) ? (new DateTime($campaign['start_date']))->format('Y-m-d\TH:i') : ''; ?>"></div>
                    <div class="col-md-6 mb-3"><label>End Date/Time</label><input type="datetime-local" name="end_date" class="form-control" required value="<?php echo !empty($campaign['end_date']) ? (new DateTime($campaign['end_date']))->format('Y-m-d\TH:i') : ''; ?>"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer d-flex justify-content-between">
        <button type="button" class="btn btn-secondary" id="prevBtn" disabled>Previous</button>
        <div>
            <button type="button" class="btn btn-info me-2" id="previewBtn" style="display:none;">Preview</button>
            <button type="button" class="btn btn-primary" id="nextBtn">Next</button>
            <button type="submit" class="btn btn-success" id="saveBtn" style="display:none;">Save & Schedule</button>
        </div>
    </div>
</form>
</div>

<!-- Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title" id="preview-subject"></h5></div>
<div class="modal-body"><iframe id="preview-iframe" style="width: 100%; height: 60vh; border: 0;"></iframe></div>
</div></div></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const isEditMode = <?php echo json_encode($action === 'edit' && !empty($campaign)); ?>;
    const initialRecipientIds = <?php echo json_encode(array_map('intval', $selected_recipient_ids)); ?>;
    let isInitialLoadForEdit = isEditMode;

    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const saveBtn = document.getElementById('saveBtn');
    const previewBtn = document.getElementById('previewBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    const campaignForm = document.getElementById('campaignForm');
    const tabs = document.querySelectorAll('#campaignTab .nav-link');
    const tabPanes = document.querySelectorAll('.tab-pane');
    let currentTab = 0;
    let maxReachedTab = isEditMode ? (tabs.length - 1) : 0;
    let formIsDirty = false;

    // --- Event Listeners ---
    campaignForm.addEventListener('input', () => { formIsDirty = true; });
    $(campaignForm).find('.select2').on('change', () => { formIsDirty = true; });

    cancelBtn.addEventListener('click', function(e) {
        if (formIsDirty) {
            if (!confirm('Are you sure you want to cancel? Any unsaved changes will be lost.')) {
                e.preventDefault();
            }
        }
    });

    document.getElementById('campaignTabContent').addEventListener('click', function(e) {
        if (e.target.classList.contains('placeholder-btn')) {
            const btn = e.target;
            const targetTextarea = document.getElementById(btn.dataset.target);
            if (targetTextarea) {
                const cursorPos = targetTextarea.selectionStart;
                const textBefore = targetTextarea.value.substring(0, cursorPos);
                const textAfter = targetTextarea.value.substring(cursorPos);
                targetTextarea.value = textBefore + btn.dataset.value + textAfter;
                targetTextarea.focus();
                targetTextarea.setSelectionRange(cursorPos + btn.dataset.value.length, cursorPos + btn.dataset.value.length);
                formIsDirty = true;
            }
        }
    });

    $('#templateSelect').on('select2:select', function(e) {
        const templateName = $(this).val();
        const bodyTextarea = document.getElementById('email_body');
        if (!templateName) {
            return;
        }
        fetch(`api.php?action=get_template_content&template_name=${encodeURIComponent(templateName)}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    bodyTextarea.value = data.body;
                    formIsDirty = true;
                } else {
                    alert('Error loading template: ' + data.error);
                }
            });
    });

    const overrideCheckbox = document.getElementById('overrideSenderCheck');
    const overrideFieldsDiv = document.getElementById('override-fields');
    const overrideInputs = overrideFieldsDiv.querySelectorAll('input');

    function toggleOverrideFields() {
        if (overrideCheckbox.checked) {
            overrideFieldsDiv.style.display = 'block';
            overrideInputs.forEach(input => input.required = true);
        } else {
            overrideFieldsDiv.style.display = 'none';
            overrideInputs.forEach(input => input.required = false);
        }
    }
    
    overrideCheckbox.addEventListener('change', toggleOverrideFields);
    toggleOverrideFields();

    // --- Helper Functions ---
    function validateStep(stepIndex) {
        const pane = tabPanes[stepIndex];
        const inputs = pane.querySelectorAll('[required]');
        let isValid = true;
        inputs.forEach(input => {
            input.classList.remove('is-invalid');
            let value = input.type === 'select-multiple' ? $(input).val() : input.value;
            if (!value || value.length === 0) {
                isValid = false;
                input.classList.add('is-invalid');
                if ($(input).hasClass('select2')) {
                    $(input).next('.select2-container').find('.select2-selection').addClass('is-invalid');
                }
            } else {
                 if ($(input).hasClass('select2')) {
                    $(input).next('.select2-container').find('.select2-selection').removeClass('is-invalid');
                }
            }
        });
        return isValid;
    }

    function updateButtons() {
        prevBtn.disabled = currentTab === 0;
        nextBtn.style.display = currentTab === tabs.length - 1 ? 'none' : 'inline-block';
        saveBtn.style.display = currentTab === tabs.length - 1 ? 'inline-block' : 'none';
        previewBtn.style.display = currentTab === 3 ? 'inline-block' : 'none';
    }

    function generateRandomString(length) {
        let result = '';
        const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        const charactersLength = characters.length;
        for (let i = 0; i < length; i++) {
            result += characters.charAt(Math.floor(Math.random() * charactersLength));
        }
        return result;
    }

    function replacePlaceholders(text) {
        text = text.replace(/\n/g, '<br />');
        const randomRegex = /\{RANDOM\((\d+)\)\}/g;
        return text.replace(randomRegex, (match, lengthStr) => {
            const length = parseInt(lengthStr, 10);
            if (isNaN(length) || length <= 0) return '';
            const clampedLength = Math.min(length, 400);
            return generateRandomString(clampedLength);
        });
    }

    // --- Main Logic ---
    prevBtn.addEventListener('click', () => { if (currentTab > 0) { new bootstrap.Tab(tabs[currentTab - 1]).show(); } });
    nextBtn.addEventListener('click', () => { if (validateStep(currentTab) && currentTab < tabs.length - 1) { maxReachedTab = Math.max(maxReachedTab, currentTab + 1); new bootstrap.Tab(tabs[currentTab + 1]).show(); } });
    tabs.forEach((tab, index) => { tab.addEventListener('show.bs.tab', (e) => { if (index > maxReachedTab) { e.preventDefault(); return; } currentTab = index; updateButtons(); }); });
    updateButtons();

    // --- Dynamic Content & Refresh ---
    function refreshSelect2(selector, apiAction, selectedValue, isMultiple = false) {
        const selectElement = $(selector);
        fetch(`api.php?action=${apiAction}`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    selectElement.empty();
                    const listKey = apiAction === 'get_mailers' ? 'mailers' : 'groups';
                    data[listKey].forEach(item => {
                        selectElement.append(new Option(item.name, item.id, false, false));
                    });
                    selectElement.val(selectedValue).trigger('change');
                }
            });
    }

    document.getElementById('refreshMailersBtn').addEventListener('click', () => refreshSelect2('#mailerSelect', 'get_mailers', $('#mailerSelect').val()));
    document.getElementById('refreshGroupsBtn').addEventListener('click', () => refreshSelect2('#targetGroupSelect', 'get_groups', $('#targetGroupSelect').val(), true));

    $('#targetGroupSelect').on('change', function() {
        const groupIds = $(this).val();
        const container = $('#recipientListContainer');
        if (!groupIds || groupIds.length === 0) {
            container.html('<p class="text-muted text-center">Select groups above to see recipients.</p>'); return;
        }
        container.html('<div class="d-flex justify-content-center mt-3"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>');
        
        fetch('api.php?action=get_targets_for_groups', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ group_ids: groupIds }) })
        .then(res => res.json()).then(data => {
            if (data.success) {
                let html = '<ul class="list-group list-group-flush">';
                if (data.targets.length > 0) {
                    data.targets.forEach(target => {
                        const isChecked = isInitialLoadForEdit ? initialRecipientIds.includes(parseInt(target.id)) : true;
                        const checkedAttr = isChecked ? 'checked' : '';
                        html += `<li class="list-group-item"><div class="form-check">
                            <input class="form-check-input" type="checkbox" name="recipients[]" value="${target.id}" id="recip-${target.id}" ${checkedAttr}>
                            <label class="form-check-label" for="recip-${target.id}">${target.name} <small class="text-muted">&lt;${target.email}&gt;</small></label>
                        </div></li>`;
                    });
                } else {
                     html += '<li class="list-group-item text-center text-muted">No targets found in the selected group(s).</li>';
                }
                html += '</ul>';
                container.html(html);
                if (isEditMode) {
                    isInitialLoadForEdit = false;
                }
            } else { container.html(`<p class="text-danger text-center">${data.error}</p>`); }
        });
    });

    if (isEditMode && $('#targetGroupSelect').val().length > 0) {
        $('#targetGroupSelect').trigger('change');
    }
    
    // --- PREVIEW ---
    const previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
    previewBtn.addEventListener('click', () => {
        let subject = document.getElementById('email_subject').value;
        let body = document.getElementById('email_body').value;
        document.getElementById('preview-subject').textContent = 'Subject: ' + replacePlaceholders(subject);
        document.getElementById('preview-iframe').srcdoc = replacePlaceholders(body);
        previewModal.show();
    });

    // --- FORM SUBMISSION ---
    function showNotification(message, type = 'success') {
        document.getElementById('campaign-notification').innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
    }

    campaignForm.addEventListener('submit', function(e) {
        e.preventDefault();
        for (let i = 0; i < tabPanes.length; i++) {
            if (!validateStep(i)) {
                showNotification(`Please fill all required fields in Step ${i + 1}.`, 'danger');
                new bootstrap.Tab(tabs[i]).show();
                return;
            }
        }

        const formData = new FormData(this);
        const selectedGroups = $('#targetGroupSelect').val();
        formData.delete('target_groups[]');
        if(selectedGroups) {
            selectedGroups.forEach(group => formData.append('target_groups[]', group));
        }
        
        const checkedRecipients = [];
        document.querySelectorAll('#recipientListContainer input[type="checkbox"]:checked').forEach(cb => {
            checkedRecipients.push(cb.value);
        });
        formData.append('recipient_ids', JSON.stringify(checkedRecipients));

        fetch('api.php?action=save_campaign', { method: 'POST', body: formData })
            .then(res => res.json()).then(data => {
                if(data.success) {
                    formIsDirty = false;
                    showNotification(data.message, 'success');
                    setTimeout(() => { window.location.href = data.redirectUrl || 'campaigns.php'; }, 1500);
                } else {
                    showNotification(data.error, 'danger');
                }
            });
    });
});
</script>

<?php } ?>
<?php require_once 'footer.php'; ?>
