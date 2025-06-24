<?php
require_once 'config.php';

// We will send JSON responses, so set the content type.
header('Content-Type: application/json');

// --- Security Checks ---
// 1. Ensure a user is logged in for any API action.
if (!isset($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit();
}
// 2. If the user is logged in but pending activation, block most actions.
$allowed_pending_actions = ['update_theme']; // Can still change theme
$action = $_GET['action'] ?? '';
if (isset($_SESSION['pending_activation']) && !in_array($action, $allowed_pending_actions)) {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'error' => 'Your account is pending activation.']);
    exit();
}

// --- API Router ---
// Get the data, supporting both standard POST and JSON payloads for flexibility
$data = $_POST;
if (empty($data)) {
    $json_payload = file_get_contents('php://input');
    if (!empty($json_payload)) {
        $data = json_decode($json_payload, true) ?? [];
    }
}
// Merge GET params (like 'action') with the data payload
$data = array_merge($_GET, $data);


// 3. Check for admin-only actions.
$admin_actions = ['save_mailer', 'delete_mailer', 'test_mailer', 'save_settings', 'pull_templates', 'toggle_user_status'];
if (in_array($action, $admin_actions) && ($_SESSION['user_role'] ?? 'user') !== 'admin') {
    http_response_code(403); // Forbidden
    echo json_encode(['success' => false, 'error' => 'Administrator privileges required.']);
    exit();
}


// --- Main Logic ---
try {
    switch ($action) {
        
        // --- THEME ---
        case 'update_theme':
            if (isset($data['theme']) && in_array($data['theme'], ['light', 'dark'])) {
                $stmt = $pdo->prepare("UPDATE users SET theme_preference = ? WHERE id = ?");
                $stmt->execute([$data['theme'], $_SESSION['user_id']]);
                $_SESSION['theme'] = $data['theme'];
                echo json_encode(['success' => true]);
            } else {
                throw new Exception("Invalid theme provided.");
            }
            break;
        
        // --- TEMPLATES ---
        case 'save_template':
             if (empty($data['name']) || !isset($data['body'])) throw new Exception("Template name and body are required.");
             
             $templates_dir = __DIR__ . '/templates';
             if (!is_dir($templates_dir)) mkdir($templates_dir, 0755, true);

             $new_filename = preg_replace('/[^\pL\pM\pN\s-]/u', '', $data['name']) . '.tplt';
             $new_filepath = $templates_dir . '/' . $new_filename;
             
             $original_filename = $data['original_filename'] ?? '';
             $is_renaming = !empty($original_filename) && $original_filename !== $new_filename;
             $is_creating_new = empty($original_filename);
             $overwrite = isset($data['overwrite']) && $data['overwrite'] === 'true';

             // We only need to ask for confirmation if we are creating a new template with a name that already exists,
             // or if we are renaming an existing template to a name that belongs to another existing template.
             if (($is_creating_new || $is_renaming) && file_exists($new_filepath) && !$overwrite) {
                 echo json_encode([
                     'success' => false, 
                     'error_type' => 'overwrite_confirmation', 
                     'message' => 'A template with the name "' . htmlspecialchars($data['name']) . '" already exists. Do you want to overwrite it?'
                 ]);
                 exit();
             }
             
             file_put_contents($new_filepath, $data['body']);

             if ($is_renaming) {
                 $old_filepath = $templates_dir . '/' . basename($original_filename);
                 if (file_exists($old_filepath)) {
                     unlink($old_filepath);
                 }
             }
             echo json_encode(['success' => true, 'message' => 'Template saved successfully.']);
             break;
        case 'get_template_content':
            if (empty($data['template_name'])) {
                throw new Exception("Template name is required.");
            }
            $templates_dir = __DIR__ . '/templates';
            $filename = basename($data['template_name']);
            $filepath = $templates_dir . '/' . $filename;

            if (strpos(realpath($filepath), $templates_dir) !== 0) {
                 throw new Exception("Invalid template path.");
            }

            if (file_exists($filepath)) {
                echo json_encode(['success' => true, 'body' => file_get_contents($filepath)]);
            } else {
                throw new Exception("Template file not found.");
            }
            break;
        case 'delete_template':
            if (empty($data['template_name'])) {
                throw new Exception("Template name is required.");
            }
            $templates_dir = __DIR__ . '/templates';
            // Sanitize filename to prevent directory traversal attacks
            $filename = basename($data['template_name']); 
            $filepath = $templates_dir . '/' . $filename;

            // Final security check to ensure the file path is within the intended directory
            if (strpos(realpath($filepath), $templates_dir) !== 0) {
                 throw new Exception("Invalid template path.");
            }

            if (file_exists($filepath)) {
                unlink($filepath);
                echo json_encode(['success' => true, 'message' => 'Template deleted.']);
            } else {
                throw new Exception("Template file not found.");
            }
            break;

        // --- GROUPS ---
        case 'get_groups':
             $stmt = $pdo->query("SELECT id, name FROM target_groups ORDER BY name");
             echo json_encode(['success' => true, 'groups' => $stmt->fetchAll()]);
             break;
        case 'save_group':
            if(empty($data['group_name'])) throw new Exception("Group name cannot be empty.");
            $id = $data['group_id'] ?? null;
            if ($id) {
                $stmt = $pdo->prepare("UPDATE target_groups SET name = ? WHERE id = ?");
                $stmt->execute([$data['group_name'], $id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO target_groups (name, created_by_user_id) VALUES (?, ?)");
                $stmt->execute([$data['group_name'], $_SESSION['user_id']]);
            }
            echo json_encode(['success' => true, 'message' => 'Group saved successfully.']);
            break;
        case 'delete_group':
            $id = $data['group_id'] ?? 0;
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM targets WHERE group_id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM target_groups WHERE id = ?")->execute([$id]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Group and all its targets deleted.']);
            break;

        // --- TARGETS ---
        case 'get_targets':
            $stmt = $pdo->prepare("SELECT id, name, email FROM targets WHERE group_id = ? ORDER BY name");
            $stmt->execute([$data['group_id']]);
            echo json_encode(['success' => true, 'targets' => $stmt->fetchAll()]);
            break;
        case 'get_targets_for_groups':
            $groupIds = $data['group_ids'] ?? [];
            if (empty($groupIds) || !is_array($groupIds)) { echo json_encode(['success' => true, 'targets' => []]); exit(); }
            $placeholders = rtrim(str_repeat('?,', count($groupIds)), ',');
            $stmt = $pdo->prepare("SELECT id, name, email FROM targets WHERE group_id IN ($placeholders) ORDER BY name");
            $stmt->execute($groupIds);
            echo json_encode(['success' => true, 'targets' => $stmt->fetchAll()]);
            break;
        case 'save_target':
            $id = $data['target_id'] ?? null;
            if (empty($data['name'])) throw new Exception("Target name is required.");
            if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) throw new Exception("A valid email is required.");
            if ($id) { // Update
                $stmt = $pdo->prepare("UPDATE targets SET name = ?, email = ? WHERE id = ?");
                $stmt->execute([$data['name'], $data['email'], $id]);
            } else { // Create
                $stmt = $pdo->prepare("INSERT INTO targets (group_id, name, email) VALUES (?, ?, ?)");
                $stmt->execute([$data['group_id'], $data['name'], $data['email']]);
            }
            echo json_encode(['success' => true, 'message' => 'Target saved successfully.']);
            break;
        case 'delete_target':
            $stmt = $pdo->prepare("DELETE FROM targets WHERE id = ?");
            $stmt->execute([$data['target_id']]);
            echo json_encode(['success' => true, 'message' => 'Target deleted successfully.']);
            break;
        case 'bulk_add_targets':
            $group_id = $data['group_id'];
            $bulk_data = trim($data['bulk_data']);
            if (empty($group_id)) throw new Exception("No group selected for bulk add.");
            $lines = explode("\n", str_replace("\r", "", $bulk_data));
            $added = 0; $skipped = 0;
            $stmt = $pdo->prepare("INSERT INTO targets (group_id, name, email) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)");
            foreach ($lines as $line) {
                $parts = str_getcsv(trim($line));
                if (count($parts) === 2 && !empty(trim($parts[0])) && filter_var(trim($parts[1]), FILTER_VALIDATE_EMAIL)) {
                    $stmt->execute([$group_id, trim($parts[0]), trim($parts[1])]);
                    $added++;
                } else {
                    $skipped++;
                }
            }
            echo json_encode(['success' => true, 'message' => "Added/updated $added targets. Skipped $skipped invalid lines."]);
            break;
            
        // --- MAILERS ---
        case 'get_mailers':
             $stmt = $pdo->query("SELECT id, name FROM mailers ORDER BY name");
             echo json_encode(['success' => true, 'mailers' => $stmt->fetchAll()]);
             break;
        case 'test_mailer':
            // This function attempts a direct socket connection to the SMTP server to verify host, port, and security settings.
            // It does NOT verify the username and password, but confirms the server is reachable.
            $host = $data['smtp_host'] ?? '';
            $port = $data['smtp_port'] ?? 0;
            $security = $data['smtp_security'] ?? 'none';

            if (empty($host) || empty($port)) {
                throw new Exception("SMTP Host and Port are required to perform a connection test.");
            }

            // Set the appropriate connection prefix based on the selected security
            $prefix = '';
            if ($security === 'ssl') {
                $prefix = 'ssl://';
            } elseif ($security === 'tls') {
                // For TLS, we connect over a standard TCP socket and then would issue a STARTTLS command.
                // For a simple connection test, 'tls://' is more explicit.
                $prefix = 'tls://';
            }

            // Set a timeout for the connection attempt (e.g., 10 seconds)
            $timeout = 10;
            
            // The @ suppresses the native PHP warning on connection failure, allowing us to handle it gracefully.
            $socket = @fsockopen($prefix . $host, $port, $errno, $errstr, $timeout);

            if ($socket === false) {
                // Connection failed
                throw new Exception("Connection failed: ({$errno}) {$errstr}");
            } else {
                // Connection was successful. We can optionally read the server's welcome message.
                $response = fgets($socket, 512);
                if (strpos($response, '220')) {
                    // 220 is the standard "ready" code from an SMTP server
                    echo json_encode(['success' => true, 'message' => "Successfully connected to {$host}:{$port}. Server responded: " . htmlspecialchars(trim($response))]);
                } else {
                    echo json_encode(['success' => true, 'message' => "Connected to {$host}:{$port}. Server responded: " . htmlspecialchars(trim($response))]);
                }
                // Close the connection
                fclose($socket);
            }
            break;

        case 'save_mailer':
            $id = $data['mailer_id'] ?? null;
            $password = $data['smtp_password'] ?? '';
            $auth_type = $data['smtp_auth'] ?? 'login';
            $username = ($auth_type === 'none') ? '' : ($data['smtp_username'] ?? '');

            // Prepare the fields for saving
            $fields = [
                'name' => $data['name'],
                'smtp_host' => $data['smtp_host'],
                'smtp_port' => $data['smtp_port'],
                'smtp_auth' => $auth_type,
                'smtp_security' => $data['smtp_security'],
                'smtp_username' => $username,
                'smtp_from_email' => $data['smtp_from_email'],
                'smtp_from_name' => $data['smtp_from_name']
            ];

            if ($id) { // Update an existing mailer
                if (!empty($password)) {
                    // If a new password is provided, encrypt and include it in the update
                    $fields['smtp_password'] = encrypt_data($password);
                    $sql = "UPDATE mailers SET name=:name, smtp_host=:smtp_host, smtp_port=:smtp_port, smtp_auth=:smtp_auth, smtp_security=:smtp_security, smtp_username=:smtp_username, smtp_password=:smtp_password, smtp_from_email=:smtp_from_email, smtp_from_name=:smtp_from_name WHERE id=:id";
                    $fields['id'] = $id;
                } else {
                    // If password is blank, update all other fields but leave the existing password untouched
                    $sql = "UPDATE mailers SET name=:name, smtp_host=:smtp_host, smtp_port=:smtp_port, smtp_auth=:smtp_auth, smtp_security=:smtp_security, smtp_username=:smtp_username, smtp_from_email=:smtp_from_email, smtp_from_name=:smtp_from_name WHERE id=:id";
                    $fields['id'] = $id;
                }
                $stmt = $pdo->prepare($sql);
                $stmt->execute($fields);

            } else { // Insert a new mailer
                if ($auth_type === 'login' && empty($password)) {
                     throw new Exception("Password is required for new mailers with Login authentication.");
                }
                $fields['smtp_password'] = encrypt_data($password);
                $fields['created_by_user_id'] = $_SESSION['user_id'];
                $sql = "INSERT INTO mailers (name, smtp_host, smtp_port, smtp_auth, smtp_security, smtp_username, smtp_password, smtp_from_email, smtp_from_name, created_by_user_id) 
                        VALUES (:name, :smtp_host, :smtp_port, :smtp_auth, :smtp_security, :smtp_username, :smtp_password, :smtp_from_email, :smtp_from_name, :created_by_user_id)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($fields);
            }
            echo json_encode(['success' => true, 'message' => 'Mailer saved successfully.']);
            break;
        case 'delete_mailer':
            $stmt = $pdo->prepare("DELETE FROM mailers WHERE id = ?");
            $stmt->execute([$data['mailer_id']]);
            echo json_encode(['success' => true, 'message' => 'Mailer deleted successfully.']);
            break;
            
        // --- CAMPAIGNS ---
        case 'delete_campaign':
            $campaign_id = $data['campaign_id'] ?? 0;
            if (empty($campaign_id)) throw new Exception("Invalid Campaign ID.");
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM recipients WHERE campaign_id = ?")->execute([$campaign_id]);
            $pdo->prepare("DELETE FROM campaigns WHERE id = ?")->execute([$campaign_id]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Campaign and all associated data have been deleted.']);
            break;
            
        case 'save_campaign':
            // Pre-check for template overwrite before starting the main database transaction.
            if(isset($data['save_as_template']) && $data['save_as_template'] == 'on') {
                if(empty($data['name'])) throw new Exception("Campaign Name is required to save it as a template.");
                $templates_dir = __DIR__ . '/templates';
                $filename = preg_replace('/[^\pL\pM\pN\s-]/u', '', $data['name']) . '.tplt';
                $filepath = $templates_dir . '/' . $filename;
                $overwrite_template = isset($data['overwrite_template']) && $data['overwrite_template'] === 'true';
        
                if (file_exists($filepath) && !$overwrite_template) {
                    // Send back a specific error type so the frontend can ask for confirmation.
                    // The campaign is NOT saved at this point.
                    echo json_encode([
                        'success' => false, 
                        'error_type' => 'overwrite_confirmation', 
                        'message' => 'A template with the name "' . htmlspecialchars($data['name']) . '" already exists. Do you want to overwrite it? The campaign has not been saved yet.'
                    ]);
                    exit(); // Stop execution before saving the campaign
                }
            }
            
            // If the check above passes (or wasn't needed), proceed with saving the campaign.
            $id = $data['campaign_id'] ?? null;
            if (empty($data['name'])) throw new Exception("Campaign Name is required.");
            
            $fields = [
                'name' => $data['name'], 
                'description' => $data['description'] ?? null, 
                'email_subject' => $data['email_subject'],
                'email_body' => $data['email_body'], 
                'redirect_url' => $data['redirect_url'], 
                'mailer_id' => $data['mailer_id'],
                'override_from_name' => (isset($data['override_sender'])) ? ($data['override_from_name'] ?? null) : null,
                'override_from_email' => (isset($data['override_sender'])) ? ($data['override_from_email'] ?? null) : null,
                'start_date' => (new DateTime($data['start_date']))->format('Y-m-d H:i:s'),
                'end_date' => (new DateTime($data['end_date']))->format('Y-m-d H:i:s'),
            ];
        
            if ($id) { // This is an UPDATE to an existing campaign
                $fields['id'] = $id;
                $sql = "UPDATE campaigns SET name=:name, description=:description, email_subject=:email_subject, email_body=:email_body, redirect_url=:redirect_url, mailer_id=:mailer_id, override_from_name=:override_from_name, override_from_email=:override_from_email, start_date=:start_date, end_date=:end_date, status='active' WHERE id=:id";
                $stmt = $pdo->prepare($sql); 
                $stmt->execute($fields); 
                $campaign_id = $id;
        
                // Logic to sync recipients
                $selected_target_ids = array_map('intval', json_decode($data['recipient_ids'] ?? '[]', true));
                if (empty($selected_target_ids)) throw new Exception("You must select at least one recipient.");
        
                // Get existing recipients for this campaign to compare
                $existing_recipients_stmt = $pdo->prepare("SELECT t.id, r.status FROM targets t JOIN recipients r ON t.email = r.target_email WHERE r.campaign_id = ?");
                $existing_recipients_stmt->execute([$campaign_id]);
                $existing_recipients_data = $existing_recipients_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                $existing_target_ids = array_keys($existing_recipients_data);
                
                // Determine who to add and who to remove
                $targets_to_add = array_diff($selected_target_ids, $existing_target_ids);
                $targets_to_remove = array_diff($existing_target_ids, $selected_target_ids);
        
                // Remove recipients who were deselected, but ONLY if their email is still 'pending'
                if (!empty($targets_to_remove)) {
                    $pending_to_remove = [];
                    foreach($targets_to_remove as $target_id) {
                        if($existing_recipients_data[$target_id] === 'pending') { 
                            $pending_to_remove[] = $target_id; 
                        }
                    }
                    if(!empty($pending_to_remove)) {
                        $remove_placeholders = rtrim(str_repeat('?,', count($pending_to_remove)), ',');
                        $remove_sql = "DELETE r FROM recipients r JOIN targets t ON r.target_email = t.email WHERE r.campaign_id = ? AND t.id IN ($remove_placeholders)";
                        $remove_stmt = $pdo->prepare($remove_sql); 
                        $remove_stmt->execute(array_merge([$campaign_id], $pending_to_remove));
                    }
                }
        
                // Add new recipients who were selected
                if (!empty($targets_to_add)) {
                    $add_placeholders = rtrim(str_repeat('?,', count($targets_to_add)), ',');
                    $target_stmt = $pdo->prepare("SELECT name, email FROM targets WHERE id IN ($add_placeholders)");
                    $target_stmt->execute($targets_to_add); 
                    $new_targets_data = $target_stmt->fetchAll(PDO::FETCH_ASSOC);
        
                    $recipient_stmt = $pdo->prepare("INSERT INTO recipients (campaign_id, target_name, target_email, unique_id, scheduled_send_time, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                    $start_timestamp = (new DateTime($data['start_date']))->getTimestamp(); 
                    $end_timestamp = (new DateTime($data['end_date']))->getTimestamp();
        
                    foreach ($new_targets_data as $target) {
                        $random_timestamp = mt_rand($start_timestamp, $end_timestamp);
                        $scheduled_time = (new DateTimeImmutable())->setTimestamp($random_timestamp);
                        $recipient_stmt->execute([$campaign_id, $target['name'], $target['email'], bin2hex(random_bytes(16)), $scheduled_time->format('Y-m-d H:i:s')]);
                    }
                }
        
            } else { // This is a NEW campaign
                $fields['created_by_user_id'] = $_SESSION['user_id'];
                $sql = "INSERT INTO campaigns (name, description, email_subject, email_body, redirect_url, mailer_id, override_from_name, override_from_email, start_date, end_date, created_by_user_id, status) VALUES (:name, :description, :email_subject, :email_body, :redirect_url, :mailer_id, :override_from_name, :override_from_email, :start_date, :end_date, :created_by_user_id, 'draft')";
                $stmt = $pdo->prepare($sql); 
                $stmt->execute($fields); 
                $campaign_id = $pdo->lastInsertId();
                
                if (!$campaign_id) { throw new Exception("Failed to create campaign ID."); }
        
                $recipient_ids = json_decode($data['recipient_ids'] ?? '[]', true);
                if(empty($recipient_ids)) throw new Exception("You must select at least one recipient.");
        
                $placeholders = rtrim(str_repeat('?,', count($recipient_ids)), ',');
                $target_stmt = $pdo->prepare("SELECT name, email FROM targets WHERE id IN ($placeholders)");
                $target_stmt->execute($recipient_ids); 
                $targets = $target_stmt->fetchAll(PDO::FETCH_ASSOC);
        
                if (count($targets) > 0) {
                    $recipient_stmt = $pdo->prepare("INSERT INTO recipients (campaign_id, target_name, target_email, unique_id, scheduled_send_time, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                    $start_timestamp = (new DateTime($data['start_date']))->getTimestamp(); 
                    $end_timestamp = (new DateTime($data['end_date']))->getTimestamp();
        
                    foreach ($targets as $target) {
                        $random_timestamp = mt_rand($start_timestamp, $end_timestamp);
                        $scheduled_time = (new DateTimeImmutable())->setTimestamp($random_timestamp);
                        $recipient_stmt->execute([$campaign_id, $target['name'], $target['email'], bin2hex(random_bytes(16)), $scheduled_time->format('Y-m-d H:i:s')]);
                    }
                }
                $pdo->prepare("UPDATE campaigns SET status='active' WHERE id=?")->execute([$campaign_id]);
            }
            
            if(isset($data['save_as_template']) && $data['save_as_template'] == 'on') {
                $templates_dir = __DIR__ . '/templates'; 
                if (!is_dir($templates_dir)) mkdir($templates_dir, 0755, true);
                $filename = preg_replace('/[^\pL\pM\pN\s-]/u', '', $data['name']) . '.tplt';
                file_put_contents($templates_dir . '/' . $filename, $data['email_body']);
            }
            
            echo json_encode(['success' => true, 'message' => 'Campaign saved and scheduled successfully.', 'redirectUrl' => 'campaigns.php']);
            break;

            
            
        // --- ADMIN ACTIONS ---
        case 'save_settings':
             $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
             $allowed_settings = ['company_name', 'company_website', 'company_phone', 'default_redirect_url', 'template_source_url'];
             foreach ($allowed_settings as $key) {
                 // Use isset() to allow submitting empty values to clear a setting
                 if (isset($data[$key])) {
                     $stmt->execute([$key, $data[$key]]);
                 }
             }
             echo json_encode(['success' => true, 'message' => 'Settings saved successfully.']);
             break;
        case 'pull_templates':
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
            $stmt->execute(['template_source_url']);
            $url = $stmt->fetchColumn();
            if (!$url) throw new Exception("Template source URL is not configured in settings.");
            $json_data = @file_get_contents($url);
            if ($json_data === false) throw new Exception("Could not fetch URL. Please check the address and network connectivity.");
            $templates = json_decode($json_data, true);
            if (json_last_error() !== JSON_ERROR_NONE) throw new Exception("Invalid JSON format in the source file.");
            $templates_dir = __DIR__ . '/templates';
            if (!is_dir($templates_dir)) mkdir($templates_dir, 0755, true);
            $count = 0;
            foreach ($templates as $template) {
                if (!empty($template['name']) && isset($template['body'])) {
                    $filename = preg_replace('/[^\pL\pM\pN\s-]/u', '', $template['name']) . '.tplt';
                    file_put_contents($templates_dir . '/' . $filename, $template['body']);
                    $count++;
                }
            }
            echo json_encode(['success' => true, 'message' => "Successfully pulled and saved $count templates."]);
            break;
        case 'get_landing_page_content':
            $filepath = __DIR__ . '/landing.php';
            if (file_exists($filepath)) {
                echo json_encode(['success' => true, 'body' => file_get_contents($filepath)]);
            } else {
                throw new Exception("The landing.php file was not found on the server.");
            }
            break;
        case 'save_landing_page_content':
            if (!isset($data['body'])) {
                throw new Exception("Landing page content cannot be empty.");
            }
            $filepath = __DIR__ . '/landing.php';
            if (file_put_contents($filepath, $data['body']) === false) {
                throw new Exception("Could not write to landing.php. Please check file permissions on the server.");
            }
            echo json_encode(['success' => true, 'message' => 'Landing page updated successfully.']);
            break;
            
        // --- EXPORT ---
        case 'export_campaign_csv':
            $campaign_id = $data['campaign_id'] ?? 0;
            if (empty($campaign_id)) {
                throw new Exception("Campaign ID is required for export.");
            }

            $stmt = $pdo->prepare("SELECT * FROM recipients WHERE campaign_id = ? ORDER BY target_email");
            $stmt->execute([$campaign_id]);
            $recipients = $stmt->fetchAll();
            
            if (empty($recipients)) {
                throw new Exception("No recipients found for this campaign to export.");
            }

            $csv_data = [];
            // Add headers
            $csv_data[] = ['Target Name', 'Target Email', 'Status', 'Scheduled Time', 'Sent Time', 'Failure Reason'];

            // Add data rows
            foreach ($recipients as $row) {
                $csv_data[] = [
                    $row['target_name'],
                    $row['target_email'],
                    ucfirst($row['status']),
                    $row['scheduled_send_time'],
                    $row['sent_time'],
                    $row['delivery_failure_reason']
                ];
            }
            
            // Create a temporary file in memory to write the CSV to
            $output = fopen('php://temp', 'w');
            foreach ($csv_data as $row) {
                fputcsv($output, $row);
            }
            rewind($output);
            $csv_string = stream_get_contents($output);
            fclose($output);

            echo json_encode(['success' => true, 'csv' => $csv_string]);
            break;
        
        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Invalid API action specified.']);
            break;
    }
} catch (Exception $e) {
    if($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400); // Bad Request for most user/logic errors
    error_log("API Action '$action' Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
