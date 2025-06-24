<?php
require_once 'header.php';

// Ensure the user is an administrator
if (($_SESSION['user_role'] ?? 'user') !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

// Fetch all settings from the database at once for efficiency
try {
    $settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    // In a real app, you would log this error and handle it gracefully
    die("Error fetching settings: " . $e->getMessage());
}

// Assign settings to variables for easier and cleaner access in the HTML form
$company_name = $settings['company_name'] ?? '';
$company_website = $settings['company_website'] ?? '';
$company_phone = $settings['company_phone'] ?? '';
$default_redirect_url = $settings['default_redirect_url'] ?? '';
$template_source_url = $settings['template_source_url'] ?? '';

?>
<h1 class="h3 mb-4">Application Settings</h1>

<div id="settings-notification"></div>
<div class="card shadow">
    <div class="card-header"><h6 class="m-0 fw-bold">DecepTest Settings</h6></div>
    <div class="card-body">
        <form id="settingsForm">
            <!-- Company Information Section -->
            <div class="mb-3">
                <label for="company_name" class="form-label">Company Name</label>
                <input type="text" class="form-control" id="company_name" name="company_name" value="<?php echo htmlspecialchars($company_name); ?>" required>
                <div class="form-text">Your MSP/Company Name. This is displayed on the landing page.</div>
            </div>
            <div class="mb-3">
                <label for="company_website" class="form-label">Company Website</label>
                <input type="url" class="form-control" id="company_website" name="company_website" value="<?php echo htmlspecialchars($company_website); ?>">
                <div class="form-text">Optional. A link to your company website for the landing page. (e.g., https://mycompany.com)</div>
            </div>
            <div class="mb-3">
                <label for="company_phone" class="form-label">Company Phone Number</label>
                <input type="tel" class="form-control" id="company_phone" name="company_phone" value="<?php echo htmlspecialchars($company_phone); ?>">
                <div class="form-text">Optional. A contact number for the landing page.</div>
            </div>
            <hr class="my-4">
            
            <!-- Campaign & Template Settings Section -->
            <div class="mb-3">
                 <label for="default_redirect_url" class="form-label">Default Redirect URL</label>
                 <div class="input-group">
                    <input type="url" class="form-control" id="default_redirect_url" name="default_redirect_url" value="<?php echo htmlspecialchars($default_redirect_url); ?>" required>
                    <button class="btn btn-outline-secondary" type="button" id="setDefaultRedirectBtn" title="Set to default landing page">Default</button>
                 </div>
                 <div class="form-text">The default URL to redirect users to when creating a new campaign.</div>
            </div>
            <div class="mb-3">
                <label for="template_source_url" class="form-label">Template Source URL</label>
                <input type="url" class="form-control" id="template_source_url" name="template_source_url" value="<?php echo htmlspecialchars($template_source_url); ?>">
                <div class="form-text">Optional. URL to a JSON file containing an array of email templates.</div>
            </div>
            <hr class="my-4">

            <!-- Landing Page & Update Section -->
            <div class="mb-4">
                <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#landingPageModal"><i class="bi bi-pencil-fill me-1"></i> Edit DecepTest Landing Page</button>
                <div class="form-text mt-1">Customize the page users see after clicking a simulated phishing link.</div>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                    <button type="button" id="pullTemplatesBtn" class="btn btn-secondary">Pull Templates From Source</button>
                </div>
                <a href="installer.php?action=update" id="updateBtn" class="btn btn-danger">Update Application</a>
            </div>
        </form>
    </div>
</div>

<!-- Landing Page Editor Modal -->
<div class="modal fade" id="landingPageModal" tabindex="-1" aria-labelledby="landingPageModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <form id="landingPageForm">
                <div class="modal-header">
                    <h5 class="modal-title" id="landingPageModalTitle">Edit Landing Page</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Edit the PHP code for the `landing.php` file. Basic placeholders `&lt;?php echo $company_name; ?&gt;`, `&lt;?php echo $company_website; ?&gt;`, and `&lt;?php echo $company_phone; ?&gt;` are available.</p>
                    <div class="editor-container" id="landing-page-editor-container">
                        <div class="line-numbers">1</div>
                        <textarea name="body" class="line-numbered-textarea" rows="18" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Landing Page</button>
                </div>
            </form>
        </div>
    </div>
</div>


<script>
// Shows a notification message at the top of the page.
function showSettingsNotification(message, type = 'success') {
    const notificationDiv = document.getElementById('settings-notification');
    notificationDiv.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>`;
}

// Initializes the line-numbered text editor.
function initLineNumberEditor(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    const lineNumbers = container.querySelector('.line-numbers');
    const textarea = container.querySelector('textarea');

    function updateLineNumbers() {
        if (!textarea) return;
        const lineCount = textarea.value.split('\n').length;
        const lines = Array.from({ length: lineCount }, (_, i) => i + 1).join('\n');
        lineNumbers.innerText = lines;
        lineNumbers.scrollTop = textarea.scrollTop;
    }
    textarea.addEventListener('scroll', () => { lineNumbers.scrollTop = textarea.scrollTop; });
    textarea.addEventListener('input', updateLineNumbers);
    textarea.addEventListener('keydown', function(e) {
        if (e.key === 'Tab') {
            e.preventDefault();
            var start = this.selectionStart; var end = this.selectionEnd;
            this.value = this.value.substring(0, start) + "\t" + this.value.substring(end);
            this.selectionStart = this.selectionEnd = start + 1;
            updateLineNumbers();
        }
    });
    new ResizeObserver(updateLineNumbers).observe(textarea);
    updateLineNumbers();
}

document.addEventListener('DOMContentLoaded', function() {
    const landingPageModal = new bootstrap.Modal(document.getElementById('landingPageModal'));
    
    // Logic for the main settings form submission
    document.getElementById('settingsForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const saveBtn = this.querySelector('button[type="submit"]');
        const originalText = saveBtn.innerHTML;
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';

        const formData = new FormData(this);
        fetch('api.php?action=save_settings', { method: 'POST', body: formData })
            .then(res => res.json()).then(data => {
                showSettingsNotification(data.message || data.error, data.success ? 'success' : 'danger');
            }).catch(err => {
                 showSettingsNotification('An unexpected error occurred.', 'danger');
            }).finally(() => {
                saveBtn.disabled = false;
                saveBtn.innerHTML = originalText;
            });
    });

    // Logic for the "Pull Templates" button
    document.getElementById('pullTemplatesBtn').addEventListener('click', function() {
        this.disabled = true; this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Pulling...';
        fetch('api.php?action=pull_templates').then(res => res.json()).then(data => {
            showSettingsNotification(data.message || data.error, data.success ? 'success' : 'danger');
        }).finally(() => {
            this.disabled = false; this.innerHTML = 'Pull Templates From Source';
        });
    });

    // Logic to set the default redirect URL
    document.getElementById('setDefaultRedirectBtn').addEventListener('click', function() {
        const path = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
        const landingUrl = `${window.location.protocol}//${window.location.host}${path}/landing.php`;
        document.getElementById('default_redirect_url').value = landingUrl;
    });

    // --- Landing Page Modal Logic ---
    const landingPageEditor = document.getElementById('landingPageModal');
    
    // When the modal is shown, fetch the content of landing.php
    landingPageEditor.addEventListener('show.bs.modal', function () {
        const textarea = this.querySelector('textarea');
        textarea.value = 'Loading content...';
        fetch('api.php?action=get_landing_page_content')
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    textarea.value = data.body;
                } else {
                    textarea.value = `Error loading content: ${data.error}`;
                }
                initLineNumberEditor('landing-page-editor-container');
            });
    });

    // Handle saving the landing page content
    document.getElementById('landingPageForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const saveBtn = this.querySelector('button[type="submit"]');
        const originalText = saveBtn.innerHTML;
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';

        const formData = new FormData(this);
        fetch('api.php?action=save_landing_page_content', { method: 'POST', body: new URLSearchParams(formData) })
            .then(res => res.json()).then(data => {
                showSettingsNotification(data.message || data.error, data.success ? 'success' : 'danger');
                if(data.success) {
                    landingPageModal.hide();
                }
            }).finally(() => {
                saveBtn.disabled = false;
                saveBtn.innerHTML = originalText;
            });
    });
});
</script>
<?php require_once 'footer.php'; ?>
