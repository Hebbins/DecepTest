<?php
require_once 'header.php';

// --- Dashboard Statistics ---
// Total number of campaigns
$campaign_count = $pdo->query("SELECT COUNT(*) FROM campaigns")->fetchColumn();
// Total number of unique targets
$target_user_count = $pdo->query("SELECT COUNT(*) FROM targets")->fetchColumn();
// Total number of emails sent (any recipient whose status is not 'pending')
$sent_count = $pdo->query("SELECT COUNT(*) FROM recipients WHERE status != 'pending'")->fetchColumn();
// Total number of links clicked across all campaigns
$clicked_count = $pdo->query("SELECT COUNT(*) FROM recipients WHERE status = 'clicked'")->fetchColumn();


// --- Recent Campaign Data ---
// Fetches the last 5 campaigns and calculates their individual stats.
// The click rate is now more accurately calculated based on emails sent, not total scheduled recipients.
$recent_campaigns = $pdo->query("
    SELECT 
        c.id, 
        c.name, 
        c.status, 
        c.created_at,
        (SELECT COUNT(*) FROM recipients r WHERE r.campaign_id = c.id AND r.status != 'pending') as total_sent,
        (SELECT COUNT(*) FROM recipients r WHERE r.campaign_id = c.id AND r.status = 'clicked') as total_clicked
    FROM campaigns c
    ORDER BY c.created_at DESC 
    LIMIT 5
")->fetchAll();
?>
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0">Dashboard</h1>
    <a href="campaigns.php?action=new" class="btn btn-primary shadow-sm"><i class="bi bi-plus-circle-fill me-2"></i>New Campaign</a>
</div>

<!-- Stat Cards Row -->
<div class="row">
    <div class="col-lg-3 col-md-6 mb-4"><div class="card h-100 shadow border-start-primary"><div class="card-body"><div class="row align-items-center"><div class="col"><div class="text-xs fw-bold text-primary text-uppercase mb-1">Campaigns</div><div class="h5 mb-0 fw-bold"><?php echo $campaign_count; ?></div></div><div class="col-auto"><i class="bi bi-send-fill fs-2 text-secondary"></i></div></div></div></div></div>
    <div class="col-lg-3 col-md-6 mb-4"><div class="card h-100 shadow border-start-info"><div class="card-body"><div class="row align-items-center"><div class="col"><div class="text-xs fw-bold text-info text-uppercase mb-1">Target Users</div><div class="h5 mb-0 fw-bold"><?php echo $target_user_count; ?></div></div><div class="col-auto"><i class="bi bi-people-fill fs-2 text-secondary"></i></div></div></div></div></div>
    <div class="col-lg-3 col-md-6 mb-4"><div class="card h-100 shadow border-start-success"><div class="card-body"><div class="row align-items-center"><div class="col"><div class="text-xs fw-bold text-success text-uppercase mb-1">Emails Sent</div><div class="h5 mb-0 fw-bold"><?php echo $sent_count; ?></div></div><div class="col-auto"><i class="bi bi-envelope-check-fill fs-2 text-secondary"></i></div></div></div></div></div>
    <div class="col-lg-3 col-md-6 mb-4"><div class="card h-100 shadow border-start-warning"><div class="card-body"><div class="row align-items-center"><div class="col"><div class="text-xs fw-bold text-warning text-uppercase mb-1">Links Clicked</div><div class="h5 mb-0 fw-bold"><?php echo $clicked_count; ?></div></div><div class="col-auto"><i class="bi bi-cursor-fill fs-2 text-secondary"></i></div></div></div></div></div>
</div>

<!-- Recent Campaigns Table -->
<div class="card shadow">
    <div class="card-header"><h6 class="m-0 font-weight-bold text-primary">Recent Campaigns</h6></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead><tr><th>Campaign Name</th><th>Status</th><th>Click Rate</th><th>Date</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if (empty($recent_campaigns)): ?>
                        <tr><td colspan="5" class="text-center">No campaigns yet. <a href="campaigns.php?action=new">Create one!</a></td></tr>
                    <?php else: ?>
                        <?php foreach ($recent_campaigns as $campaign): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($campaign['name']); ?></td>
                                <td><span class="badge bg-primary"><?php echo ucfirst($campaign['status']); ?></span></td>
                                <td><?php echo $campaign['total_sent'] > 0 ? round(($campaign['total_clicked'] / $campaign['total_sent']) * 100, 1) . '%' : 'N/A'; ?></td>
                                <td><?php echo date('d M Y', strtotime($campaign['created_at'])); ?></td>
                                <td><a href="reports.php?campaign_id=<?php echo $campaign['id']; ?>" class="btn btn-sm btn-outline-primary">View Report</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once 'footer.php'; ?>
