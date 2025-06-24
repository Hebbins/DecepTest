<?php
require_once 'header.php';

$campaign_id = $_GET['campaign_id'] ?? null;
$campaign = null;
$recipients = [];
$stats = ['total' => 0, 'sent' => 0, 'failed' => 0, 'opened' => 0, 'clicked' => 0];

// Fetch all campaigns for the dropdown selector
$all_campaigns = $pdo->query("SELECT id, name FROM campaigns ORDER BY created_at DESC")->fetchAll();

if ($campaign_id) {
    // Fetch details for the selected campaign
    $stmt = $pdo->prepare("SELECT * FROM campaigns WHERE id = ?");
    $stmt->execute([$campaign_id]);
    $campaign = $stmt->fetch();

    if ($campaign) {
        // Fetch all recipients for this campaign
        $recipients_stmt = $pdo->prepare("SELECT * FROM recipients WHERE campaign_id = ? ORDER BY target_email");
        $recipients_stmt->execute([$campaign_id]);
        $recipients = $recipients_stmt->fetchAll();

        // Calculate stats
        $stats['total'] = count($recipients);
        foreach ($recipients as $recipient) {
            if ($recipient['status'] !== 'pending') $stats['sent']++;
            if (in_array($recipient['status'], ['opened', 'clicked'])) $stats['opened']++;
            if ($recipient['status'] === 'clicked') $stats['clicked']++;
            if ($recipient['status'] === 'failed') $stats['failed']++;
        }
    }
}
?>

<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0">Campaign Reports</h1>
    <?php if ($campaign): ?>
        <button class="btn btn-success" id="exportBtn"><i class="bi bi-download me-2"></i>Export to CSV</button>
    <?php endif; ?>
</div>

<div class="card shadow mb-4">
    <div class="card-body">
        <form method="GET">
            <div class="row align-items-end">
                <div class="col-md-10">
                    <label for="campaign_id" class="form-label">Select a Campaign to View its Report</label>
                    <select name="campaign_id" id="campaign_id" class="form-select select2" onchange="this.form.submit()">
                        <option value="">-- Select a Campaign --</option>
                        <?php foreach($all_campaigns as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo ($c['id'] == $campaign_id) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">View</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if ($campaign): ?>
    <h2 class="h4 mb-3"><?php echo htmlspecialchars($campaign['name']); ?> - Report</h2>
    
    <!-- Report Stat Cards -->
    <div class="row">
        <div class="col-lg col-md-6 mb-4"><div class="card h-100"><div class="card-body text-center"><h5>Total Recipients</h5><p class="fs-2 mb-0"><?php echo $stats['total']; ?></p></div></div></div>
        <div class="col-lg col-md-6 mb-4"><div class="card h-100"><div class="card-body text-center"><h5>Sent</h5><p class="fs-2 mb-0"><?php echo $stats['sent']; ?></p></div></div></div>
        <div class="col-lg col-md-6 mb-4"><div class="card h-100"><div class="card-body text-center"><h5>Opened</h5><p class="fs-2 mb-0"><?php echo $stats['opened']; ?></p></div></div></div>
        <div class="col-lg col-md-6 mb-4"><div class="card h-100"><div class="card-body text-center"><h5>Clicked</h5><p class="fs-2 mb-0"><?php echo $stats['clicked']; ?></p></div></div></div>
        <div class="col-lg col-md-6 mb-4"><div class="card h-100"><div class="card-body text-center"><h5>Failed</h5><p class="fs-2 mb-0"><?php echo $stats['failed']; ?></p></div></div></div>
    </div>

    <!-- Detailed Recipient List -->
    <div class="card shadow">
        <div class="card-header"><h6 class="m-0">Recipient Status</h6></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Sent Time</th></tr></thead>
                    <tbody>
                        <?php foreach($recipients as $recipient): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($recipient['target_name']); ?></td>
                                <td><?php echo htmlspecialchars($recipient['target_email']); ?></td>
                                <td>
                                    <?php
                                    $status = $recipient['status']; $reason = $recipient['delivery_failure_reason'];
                                    $badge_class = 'secondary'; $icon = 'bi-question-circle';
                                    if ($status === 'clicked') {$badge_class = 'warning'; $icon = 'bi-cursor-fill';}
                                    elseif ($status === 'opened') {$badge_class = 'info'; $icon = 'bi-envelope-open-fill';}
                                    elseif ($status === 'sent') {$badge_class = 'success'; $icon = 'bi-check-circle-fill';}
                                    elseif ($status === 'failed') {$badge_class = 'danger'; $icon = 'bi-x-octagon-fill';}
                                    ?>
                                    <span class="badge bg-<?php echo $badge_class; ?> fs-6" <?php if($status === 'failed' && $reason): ?>data-bs-toggle="tooltip" title="<?php echo htmlspecialchars($reason);?>"<?php endif; ?>>
                                        <i class="bi <?php echo $icon; ?> me-1"></i> <?php echo ucfirst($status); ?>
                                    </span>
                                </td>
                                <td><?php echo $recipient['sent_time'] ? date('d M Y, H:i', strtotime($recipient['sent_time'])) : 'N/A'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php elseif($campaign_id): ?>
    <div class="alert alert-danger">Campaign with the specified ID was not found.</div>
<?php else: ?>
    <div class="alert alert-info text-center">Please select a campaign from the dropdown above to view its report.</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    const exportBtn = document.getElementById('exportBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function() {
            const campaignId = <?php echo json_encode($campaign_id); ?>;
            const campaignName = <?php echo json_encode($campaign['name'] ?? 'report'); ?>;

            this.disabled = true;
            this.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Exporting...`;

            fetch(`api.php?action=export_campaign_csv&campaign_id=${campaignId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const blob = new Blob([data.csv], { type: 'text/csv;charset=utf-8;' });
                        const link = document.createElement("a");
                        const url = URL.createObjectURL(blob);
                        link.setAttribute("href", url);
                        link.setAttribute("download", `DecepTest-${campaignName.replace(/ /g,"_")}.csv`);
                        link.style.visibility = 'hidden';
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    } else {
                        alert('Error exporting data: ' + data.error);
                    }
                })
                .catch(err => {
                    console.error('Export Error:', err);
                    alert('An unexpected error occurred during export.');
                })
                .finally(() => {
                    this.disabled = false;
                    this.innerHTML = `<i class="bi bi-download me-2"></i>Export to CSV`;
                });
        });
    }
});
</script>

<?php require_once 'footer.php'; ?>
