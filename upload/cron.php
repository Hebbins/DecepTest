<?php
/**
 * DecepTest Cron Job (with Logging & PHPMailer)
 */

set_time_limit(160);
chdir(dirname(__FILE__));
require_once 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/phpmailer/src/Exception.php';
require 'vendor/phpmailer/src/PHPMailer.php';
require 'vendor/phpmailer/src/SMTP.php';

// --- Logging Function ---
function log_cron_action($level, $message) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("INSERT INTO cron_logs (level, message) VALUES (?, ?)");
        $stmt->execute([$level, $message]);
    } catch (PDOException $e) {
        // If logging fails, output to the server's error log as a fallback.
        error_log("CRON LOGGING FAILED: " . $e->getMessage());
    }
}

// --- Placeholder Replacement Function ---
function replace_placeholders($text, $recipient_data) {
    $text = str_replace('{TARGET_NAME}', $recipient_data['target_name'], $text);
    $text = str_replace('{TARGET_EMAIL}', $recipient_data['target_email'], $text);
    $text = str_replace('{TARGET_LINK}', ROOT_URL . "track.php?type=click&id=" . $recipient_data['unique_id'], $text);
    $text = preg_replace_callback('/\{RANDOM\((\d+)\)\}/', function($matches) {
        $length = min((int)$matches[1], 400);
        if ($length <= 0) return '';
        try {
            return substr(bin2hex(random_bytes(ceil($length / 2))), 0, $length);
        } catch (Exception $e) {
            return substr(str_shuffle(str_repeat('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil($length/36))),1,$length);
        }
    }, $text);
    return $text;
}

//log_cron_action('INFO', 'Cron job started.');

try {
    // --- Step 1: Fetch scheduled recipients ---
    $stmt = $pdo->prepare("
        SELECT 
            r.id as recipient_id, r.unique_id, r.target_name, r.target_email,
            c.id as campaign_id, c.name as campaign_name,
            c.email_subject, c.email_body, c.override_from_name, c.override_from_email,
            m.smtp_host, m.smtp_port, m.smtp_auth, m.smtp_security, m.smtp_username, m.smtp_password,
            m.smtp_from_email, m.smtp_from_name
        FROM recipients r
        JOIN campaigns c ON r.campaign_id = c.id
        JOIN mailers m ON c.mailer_id = m.id
        WHERE r.status = 'pending' AND c.status = 'active' AND r.scheduled_send_time <= NOW()
        LIMIT 20
    ");
    $stmt->execute();
    $recipients_to_send = $stmt->fetchAll();

    if (empty($recipients_to_send)) {
        //log_cron_action('DEBUG', 'No pending emails to send at this time.');
    } else {
        //log_cron_action('INFO', "Found " . count($recipients_to_send) . " email(s) to process.");

        foreach ($recipients_to_send as $recipient) {
            $new_status = 'sent';
            $failure_reason = null;
            $mail = new PHPMailer(true);

            try {
                // --- Configure PHPMailer ---
                $mail->isSMTP();
                $mail->Host       = $recipient['smtp_host'];
                $mail->Port       = $recipient['smtp_port'];
                if ($recipient['smtp_auth'] === 'login') {
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $recipient['smtp_username'];
                    $mail->Password   = decrypt_data($recipient['smtp_password']);
                } else {
                    $mail->SMTPAuth = false;
                }
                if ($recipient['smtp_security'] === 'tls' || $recipient['smtp_security'] === 'ssl') {
                    $mail->SMTPSecure = $recipient['smtp_security'];
                } else {
                    $mail->SMTPSecure = false;
                    $mail->SMTPAutoTLS = false;
                }
                
                $from_name = !empty($recipient['override_from_name']) ? $recipient['override_from_name'] : $recipient['smtp_from_name'];
                $from_email = !empty($recipient['override_from_email']) ? $recipient['override_from_email'] : $recipient['smtp_from_email'];

                // --- Prepare & Send ---
                $mail->setFrom($from_email, $from_name);
                $mail->addAddress($recipient['target_email'], $recipient['target_name']);
                $mail->isHTML(true);
                $mail->Subject = replace_placeholders($recipient['email_subject'], $recipient);
                $final_body = replace_placeholders($recipient['email_body'], $recipient);
                $tracking_pixel_url = ROOT_URL . "track.php?type=open&id=" . $recipient['unique_id'];
                $mail->Body    = $final_body . '<img src="' . $tracking_pixel_url . '" width="1" height="1" border="0" alt="" style="display:none;" />';

                $mail->send();
                log_cron_action('SUCCESS', "Email sent to {$recipient['target_email']} for campaign '{$recipient['campaign_name']}' (ID: {$recipient['campaign_id']}).");

            } catch (Exception $e) {
                $new_status = 'failed';
                $failure_reason = $mail->ErrorInfo;
                log_cron_action('ERROR', "Failed to send to {$recipient['target_email']} for campaign ID {$recipient['campaign_id']}: {$failure_reason}");
            }

            // --- Update Recipient Status ---
            $update_stmt = $pdo->prepare("UPDATE recipients SET status = ?, sent_time = NOW(), delivery_failure_reason = ? WHERE id = ?");
            $update_stmt->execute([$new_status, $failure_reason, $recipient['recipient_id']]);
        }
    }

    // --- Mark completed campaigns ---
    $completed_stmt = $pdo->query("UPDATE campaigns SET status = 'completed' WHERE status = 'active' AND end_date <= NOW()");
    $completed_count = $completed_stmt->rowCount();
    if ($completed_count > 0) {
        log_cron_action('INFO', "Marked {$completed_count} campaign(s) as completed.");
    }

} catch (Exception $e) {
    log_cron_action('ERROR', "CRON SCRIPT FAILED: " . $e->getMessage());
}

//log_cron_action('INFO', 'Cron job finished.');
?>
