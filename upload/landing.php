<?php
// This file should be placed in the root directory of your application.
require_once 'config.php';

// Fetch required settings from the database
try {
    $settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('company_name', 'company_website', 'company_phone')");
    $settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    // If DB fails, use generic defaults and log the error.
    error_log("Landing Page DB Error: " . $e->getMessage());
    $settings = [];
}

// Sanitize output for security
$company_name = htmlspecialchars($settings['company_name'] ?? 'your organization');
$company_website = htmlspecialchars($settings['company_website'] ?? '');
$company_phone = htmlspecialchars($settings['company_phone'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Alert</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        html, body { height: 100%; }
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa; /* Light gray background */
            color: #212529;
            text-align: center;
            padding: 1rem;
        }
        .container { max-width: 650px; }
        .icon { font-size: 4rem; color: #ffc107; } /* Warning yellow */
    </style>
</head>
<body>
    <div class="container">
        <div class="icon mb-3">
            <i class="bi bi-exclamation-triangle-fill"></i>
        </div>
        <h1 class="h2 mb-3">A Security Event Was Recorded</h1>
        <p class="lead">
            You have clicked a link in a simulated phishing email sent by <strong><?php echo $company_name; ?></strong>.
        </p>
        <p>
            This was part of a security awareness test, and this action has been logged for training purposes. In a real-world scenario, clicking such a link could have exposed your device and our network to threats like malware or data theft.
        </p>
        <hr class="my-4">
        <p class="mb-0">If you have any questions, please contact your IT department.</p>
        <?php if (!empty($company_website) || !empty($company_phone)): ?>
        <div class="mt-4">
            <?php if (!empty($company_website)): ?>
                <a href="<?php echo $company_website; ?>" class="btn btn-primary me-2">Visit <?php echo $company_name; ?></a>
            <?php endif; ?>
            <?php if (!empty($company_phone)): ?>
                <span class="align-middle"><strong>Contact:</strong> <?php echo $company_phone; ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
