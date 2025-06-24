<?php
require_once 'config.php';

$type = $_GET['type'] ?? '';
$id = $_GET['id'] ?? '';

if (empty($id) || empty($type)) {
    http_response_code(400); exit();
}

try {
    $stmt = $pdo->prepare("SELECT id, status, campaign_id FROM recipients WHERE unique_id = ?");
    $stmt->execute([$id]);
    $recipient = $stmt->fetch();

    if (!$recipient) {
        http_response_code(404); exit();
    }

    $recipient_id = $recipient['id'];
    $current_status = $recipient['status'];

    if ($type === 'open' && $current_status === 'sent') {
        $update_stmt = $pdo->prepare("UPDATE recipients SET status = 'opened' WHERE id = ?");
        $update_stmt->execute([$recipient_id]);
    } elseif ($type === 'click') {
        $update_stmt = $pdo->prepare("UPDATE recipients SET status = 'clicked' WHERE id = ?");
        $update_stmt->execute([$recipient_id]);
    }
    
    if ($type === 'click') {
        $campaign_stmt = $pdo->prepare("SELECT redirect_url FROM campaigns WHERE id = ?");
        $campaign_stmt->execute([$recipient['campaign_id']]);
        $redirect_url = $campaign_stmt->fetchColumn() ?: 'https://www.google.com';
        header("Location: " . $redirect_url);
        exit();
    } else {
        header('Content-Type: image/gif');
        echo base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICRAEAOw==');
        exit();
    }
} catch (PDOException $e) {
    error_log("Tracking Error: " . $e->getMessage());
    http_response_code(500); exit();
}
?>
