<?php
require_once 'header.php';

$templates_dir = __DIR__ . '/templates';
if (!is_dir($templates_dir)) {
    mkdir($templates_dir, 0755, true);
}
$template_files = glob($templates_dir . '/*.tplt');
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0">Email Templates</h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#templateModal" onclick="prepareTemplateModal()"><i class="bi bi-plus-circle me-2"></i>New Template</button>
</div>
<div id="notification-area"></div>
<div class="card shadow">
    <div class="card-body">
        <p class="card-text text-muted">Manage reusable email templates here. You can also import templates from a remote JSON source in the Admin settings.</p>
        <div class="accordion" id="templatesAccordion">
            <?php if(empty($template_files)): ?>
                <div class="text-center p-4 text-muted">No local templates found. Create one or pull from a source in settings.</div>
            <?php endif; ?>
            <?php foreach ($template_files as $file): 
                $name = basename($file, '.tplt');
                $filename_encoded = htmlspecialchars(basename($file), ENT_QUOTES, 'UTF-8');
            ?>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#tpl-<?php echo md5($name); ?>">
                        <?php echo htmlspecialchars($name); ?>
                    </button>
                </h2>
                <div id="tpl-<?php echo md5($name); ?>" class="accordion-collapse collapse" data-bs-parent="#templatesAccordion">
                    <div class="accordion-body">
                        <div class="mb-3 text-end">
                            <button class="btn btn-sm btn-outline-success" onclick="prepareTemplateModal('<?php echo $filename_encoded; ?>')"><i class="bi bi-pencil-fill me-1"></i>Edit</button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteTemplate('<?php echo $filename_encoded; ?>')"><i class="bi bi-trash-fill me-1"></i>Delete</button>
                        </div>
                        <iframe srcdoc="<?php echo nl2br(htmlspecialchars(file_get_contents($file))); ?>" style="width: 100%; height: 400px; border: 1px solid var(--custom-border-color); border-radius: .375rem;"></iframe>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="templateModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content">
<form id="templateForm">
    <div class="modal-header"><h5 class="modal-title" id="templateModalTitle">Create New Template</h5></div>
    <div class="modal-body">
        <input type="hidden" name="original_filename" id="original_filename">
        <div class="mb-3"><label class="form-label">Template Name</label><input type="text" name="name" id="template_name" class="form-control" required></div>
        <div class="mb-3">
            <label class="form-label">Body (HTML)</label>
            <div class="editor-container" id="template-editor-container">
                <div class="line-numbers">1</div>
                <textarea name="body" class="line-numbered-textarea" rows="15" required></textarea>
            </div>
        </div>
        <div><small class="text-muted">Placeholders:</small>
            <button type="button" class="btn btn-sm btn-outline-secondary placeholder-btn" data-target="template-editor-container" data-value="{TARGET_NAME}">Name</button>
            <button type="button" class="btn btn-sm btn-outline-secondary placeholder-btn" data-target="template-editor-container" data-value="{TARGET_EMAIL}">Email</button>
            <button type="button" class="btn btn-sm btn-outline-secondary placeholder-btn" data-target="template-editor-container" data-value="{TARGET_LINK}">Link</button>
            <button type="button" class="btn btn-sm btn-outline-secondary placeholder-btn" data-target="template-editor-container" data-value="{RANDOM(#)}">Random(#)</button>
        </div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-info me-auto" id="templatePreviewBtn">Preview</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button type="submit" class="btn btn-primary">Save Template</button></div>
</form>
</div></div></div>

<div class="modal fade" id="templatePreviewModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title">Template Preview</h5></div>
<div class="modal-body"><iframe id="preview-template-iframe" style="width: 100%; height: 60vh; border: 0;"></iframe></div>
</div></div></div>


<script>
// --- Line Number Editor Logic ---
function initLineNumberEditor(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    const lineNumbers = container.querySelector('.line-numbers');
    const textarea = container.querySelector('textarea');

    function updateLineNumbers() {
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

// --- Main Page Logic ---
document.addEventListener('DOMContentLoaded', function() {
    initLineNumberEditor('template-editor-container');
    const templateModal = new bootstrap.Modal(document.getElementById('templateModal'));
    const previewModal = new bootstrap.Modal(document.getElementById('templatePreviewModal'));
    
    // Placeholder button logic
    document.getElementById('templateModal').addEventListener('click', function(e) {
        if (e.target.classList.contains('placeholder-btn')) {
            const btn = e.target;
            const container = document.getElementById(btn.dataset.target);
            const textarea = container.querySelector('textarea');
            
            const cursorPos = textarea.selectionStart;
            const textBefore = textarea.value.substring(0, cursorPos);
            const textAfter = textarea.value.substring(cursorPos);
            textarea.value = textBefore + btn.dataset.value + textAfter;
            textarea.focus();
            textarea.setSelectionRange(cursorPos + btn.dataset.value.length, cursorPos + btn.dataset.value.length);
        }
    });

    window.prepareTemplateModal = (filename = null) => {
        const form = document.getElementById('templateForm');
        form.reset();
        const textarea = form.querySelector('textarea');
        textarea.value = '';

        if (filename) {
            document.getElementById('templateModalTitle').textContent = 'Edit Template';
            const nameWithoutExt = filename.replace('.tplt', '');
            document.getElementById('original_filename').value = filename;
            document.getElementById('template_name').value = nameWithoutExt;
            
            fetch(`api.php?action=get_template_content&template_name=${encodeURIComponent(filename)}`)
                .then(res => res.json()).then(data => {
                    if (data.success) {
                        textarea.value = data.body;
                        initLineNumberEditor('template-editor-container');
                    }
                });
        } else {
            document.getElementById('templateModalTitle').textContent = 'Create New Template';
            document.getElementById('original_filename').value = '';
        }
        
        initLineNumberEditor('template-editor-container');
        templateModal.show();
    };
    
    window.deleteTemplate = (filename) => {
        if (confirm(`Are you sure you want to delete the template "${filename.replace('.tplt', '')}"? This cannot be undone.`)) {
            fetch('api.php?action=delete_template', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ template_name: filename })
            }).then(res => res.json()).then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }
    };

    document.getElementById('templateForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');

        function sendSaveRequest(formData) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';

            fetch('api.php?action=save_template', { method: 'POST', body: formData })
                .then(res => res.json()).then(data => {
                    if(data.success) {
                        window.location.reload();
                    } else {
                        if (data.error_type === 'overwrite_confirmation') {
                            if (confirm(data.message)) {
                                formData.append('overwrite', 'true');
                                sendSaveRequest(formData); // Resubmit with overwrite flag
                            } else {
                                // User cancelled, re-enable the button
                                submitBtn.disabled = false;
                                submitBtn.textContent = 'Save Template';
                            }
                        } else {
                            alert('Error: ' + data.error);
                            submitBtn.disabled = false;
                            submitBtn.textContent = 'Save Template';
                        }
                    }
                }).catch(() => {
                    alert('An unexpected network error occurred.');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Save Template';
                });
        }
        
        sendSaveRequest(formData);
    });

    // Preview button logic
    document.getElementById('templatePreviewBtn').addEventListener('click', function() {
        const textarea = document.querySelector('#template-editor-container textarea');
        let body = textarea.value;
        body = body.replace(/\n/g, '<br />'); // Convert newlines to <br>
        document.getElementById('preview-template-iframe').srcdoc = body;
        previewModal.show();
    });
});
</script>
<?php require_once 'footer.php'; ?>
