<?php
// DecepTest Installer & Updater
// This script should be placed in the root directory where you want to install the application.

error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300); // 5 minutes max execution time

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Configuration ---
define('GITHUB_REPO', 'Hebbins/DecepTest');
define('GITHUB_BRANCH', 'main');
define('CONFIG_FILE', 'config.php');
define('README_URL', 'https://raw.githubusercontent.com/' . GITHUB_REPO . '/' . GITHUB_BRANCH . '/README.md');
define('SQL_FILE_URL', 'https://raw.githubusercontent.com/' . GITHUB_REPO . '/' . GITHUB_BRANCH . '/deceptest.sql');
define('INITIAL_VERSION', '1.0.0'); // Base version for new installs.

// --- Pre-flight Checks ---
$is_update_mode = isset($_GET['action']) && $_GET['action'] === 'update';
$config_exists = file_exists(CONFIG_FILE);

// If config exists and it's not an update request, redirect to the app.
if ($config_exists && !$is_update_mode) {
    header('Location: index.php');
    exit();
}
// If it IS an update request but config does NOT exist, force a new installation.
if (!$config_exists && $is_update_mode) {
    header('Location: installer.php');
    exit();
}


// --- API-like Handler for AJAX requests ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $response = ['success' => false, 'message' => 'An unknown error occurred.'];

    try {
        switch ($action) {
            case 'test_db':
                $host = $_POST['db_host'];
                $user = $_POST['db_user'];
                $pass = $_POST['db_pass'];
                $name = $_POST['db_name'];
                
                $test_conn = new mysqli($host, $user, $pass);
                if ($test_conn->connect_error) {
                    throw new Exception('Connection Failed: ' . $test_conn->connect_error);
                }
                
                if (!$test_conn->select_db($name)) {
                    if (!$test_conn->query("CREATE DATABASE `$name`")) {
                        throw new Exception("Database `{$name}` does not exist and could not be created. Please create it manually.");
                    }
                }
                $test_conn->close();
                
                $_SESSION['install_data'] = $_POST;
                $response = ['success' => true, 'message' => 'Database connection successful!'];
                break;

            case 'install_files':
                $zip_url = 'https://github.com/' . GITHUB_REPO . '/archive/' . GITHUB_BRANCH . '.zip';
                $zip_file = 'deceptest_latest.zip';
                
                if (!@copy($zip_url, $zip_file)) {
                    throw new Exception("Failed to download the application files from GitHub. Check server permissions and internet connectivity.");
                }

                $zip = new ZipArchive;
                if ($zip->open($zip_file) === TRUE) {
                    $temp_dir = 'deceptest-install-temp';
                    $zip->extractTo($temp_dir);
                    $zip->close();
                    
                    $source_dir = $temp_dir . '/' . 'DecepTest-' . GITHUB_BRANCH . '/upload/';
                    
                    if (!is_dir($source_dir)) {
                         throw new Exception("Could not find the 'upload' directory in the downloaded files.");
                    }
                    
                    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source_dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
                    foreach ($files as $file) {
                        $destination = realpath('.') . DIRECTORY_SEPARATOR . $files->getSubPathName();
                        if (is_dir($file)) {
                            if (!is_dir($destination)) mkdir($destination, 0755, true);
                        } else {
                            rename($file->getRealPath(), $destination);
                        }
                    }
                    unlink($zip_file);
                    $it = new RecursiveDirectoryIterator($temp_dir, RecursiveDirectoryIterator::SKIP_DOTS);
                    $files_to_delete = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
                    foreach($files_to_delete as $file) {
                        if ($file->isDir()){ rmdir($file->getRealPath()); } else { unlink($file->getRealPath()); }
                    }
                    rmdir($temp_dir);
                } else {
                    throw new Exception('Failed to open the downloaded zip file.');
                }
                
                $_SESSION['install_data'] = array_merge($_SESSION['install_data'] ?? [], $_POST);
                $response = ['success' => true, 'message' => 'Application files installed successfully.'];
                break;
            
            case 'setup_database':
                if (empty($_SESSION['install_data'])) throw new Exception("Session data lost. Please restart installation.");
                $db_data = $_SESSION['install_data'];

                $mysqli = new mysqli($db_data['db_host'], $db_data['db_user'], $db_data['db_pass'], $db_data['db_name']);
                if ($mysqli->connect_error) throw new Exception("Database connection failed during setup.");

                $sql_schema = file_get_contents(SQL_FILE_URL);
                if ($sql_schema === false) throw new Exception("Could not download the database schema file.");

                if (!$mysqli->multi_query($sql_schema)) {
                    throw new Exception("Failed to execute database schema setup: " . $mysqli->error);
                }
                while ($mysqli->next_result()) { if (!$mysqli->more_results()) break; }

                $stmt = $mysqli->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                $stmt->bind_param("ss", $key, $value);
                
                $settings = [
                    'company_name' => $db_data['company_name'],
                    'company_website' => $db_data['company_website'],
                    'company_phone' => $db_data['company_phone'],
                    'app_version' => INITIAL_VERSION
                ];
                foreach ($settings as $key => $value) {
                    $stmt->execute();
                }
                $stmt->close();
                $mysqli->close();

                $response = ['success' => true, 'message' => 'Database configured successfully.'];
                break;
                
            case 'write_config':
                 if (empty($_SESSION['install_data'])) throw new Exception("Session data lost. Please restart installation.");
                 $all_data = array_merge($_SESSION['install_data'], $_POST);

                 $root_url = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'];
                 $root_url .= str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
                
                 $encryption_key = bin2hex(random_bytes(32));

                 $config_content = "<?php
// File: config.php - Generated by DecepTest Installer

if (session_status() === PHP_SESSION_NONE) { session_start(); }
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('APP_NAME', 'DecepTest');
define('DB_HOST', '{$all_data['db_host']}');
define('DB_USER', '{$all_data['db_user']}');
define('DB_PASS', '{$all_data['db_pass']}');
define('DB_NAME', '{$all_data['db_name']}');
define('ENCRYPTION_KEY', '{$encryption_key}');
define('OAUTH_APP_ID', '{$all_data['oauth_app_id']}');
define('OAUTH_APP_SECRET', '{$all_data['oauth_app_secret']}');
define('OAUTH_TENANT_ID', '{$all_data['oauth_tenant_id']}');
define('OAUTH_REDIRECT_URI', '{$all_data['oauth_redirect_uri']}');
define('OAUTH_SCOPES', 'openid profile email User.Read');
define('ROOT_URL', '{$root_url}');

try {
    \$pdo = new PDO(\"mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=utf8mb4\", DB_USER, DB_PASS);
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    \$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException \$e) {
    die(\"ERROR: Could not connect to the database. \" . \$e->getMessage());
}

function encrypt_data(\$data) { if(empty(\$data)) return null; \$iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc')); \$encrypted = openssl_encrypt(\$data, 'aes-256-cbc', ENCRYPTION_KEY, 0, \$iv); return base64_encode(\$iv . \$encrypted); }
function decrypt_data(\$data) { if(empty(\$data)) return null; \$data = base64_decode(\$data); \$ivlen = openssl_cipher_iv_length('aes-256-cbc'); \$iv = substr(\$data, 0, \$ivlen); \$encrypted_data = substr(\$data, \$ivlen); return openssl_decrypt(\$encrypted_data, 'aes-256-cbc', ENCRYPTION_KEY, 0, \$iv); }
";
                 if (file_put_contents(CONFIG_FILE, $config_content) === false) {
                     throw new Exception("Could not write config.php file. Please check file permissions.");
                 }
                 session_destroy();
                 $response = ['success' => true, 'message' => 'Configuration file written. Installation complete!'];
                 break;
            
            case 'check_for_updates':
                require_once CONFIG_FILE;
                $pdo_local = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
                $stmt = $pdo_local->query("SELECT setting_value FROM settings WHERE setting_key = 'app_version'");
                $current_version = $stmt->fetchColumn() ?: '0.0.0';

                $readme_content = @file_get_contents(README_URL);
                if ($readme_content === false) {
                    throw new Exception("Could not fetch version information from GitHub.");
                }

                $latest_version = $current_version; // Default to current
                if (preg_match('/Current Version: ([\d\.]+)/i', $readme_content, $matches)) {
                    $latest_version = $matches[1];
                }

                $update_available = version_compare($latest_version, $current_version, '>');
                
                $response = [
                    'success' => true,
                    'current_version' => $current_version,
                    'latest_version' => $latest_version,
                    'update_available' => $update_available
                ];
                break;
        }

    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
    }
    echo json_encode($response);
    exit();
}

$default_redirect_uri = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . str_replace(basename($_SERVER['SCRIPT_NAME']), 'callback.php', $_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DecepTest Installer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style> body { background-color: #f0f2f5; } </style>
</head>
<body>
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white text-center">
                    <h2 class="mb-0"><i class="bi bi-bug-fill"></i> DecepTest <?php echo $is_update_mode ? 'Updater' : 'Installer'; ?></h2>
                </div>

                <?php if($is_update_mode): ?>
                <div class="card-body">
                    <h5 class="card-title text-center">Application Update</h5>
                    <div id="update-check-area" class="text-center p-3">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Checking for updates...</span>
                        </div>
                        <p class="mt-2">Checking for updates...</p>
                    </div>
                    <div id="update-info-area" class="d-none">
                        <div class="row text-center">
                            <div class="col">
                                <div class="stat-title text-muted">Current Version</div>
                                <div id="current-version-display" class="h4">...</div>
                            </div>
                            <div class="col">
                                <div class="stat-title text-muted">Latest Version</div>
                                <div id="latest-version-display" class="h4">...</div>
                            </div>
                        </div>
                        <div id="update-notification-area" class="mt-3"></div>
                        <button id="startUpdateBtn" class="btn btn-danger w-100 mt-3">Start Update</button>
                    </div>
                </div>
                <?php else: ?>
                <form id="installerForm" novalidate>
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" id="installerTab" role="tablist">
                            <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#step1" type="button">1. Welcome</button></li>
                            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#step2" type="button" disabled>2. Database</button></li>
                            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#step3" type="button" disabled>3. Company</button></li>
                            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#step4" type="button" disabled>4. Install</button></li>
                            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#step5" type="button" disabled>5. Entra ID</button></li>
                            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#step6" type="button" disabled>6. Finish</button></li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div id="notification-area" class="mb-3"></div>
                        <div class="tab-content" id="installerTabContent">
                            <!-- Step 1: Welcome & TOS -->
                            <div class="tab-pane fade show active" id="step1">
                                <h5>Welcome to DecepTest!</h5>
                                <p>This wizard will guide you through the installation process.</p>
                                <label for="tos" class="form-label">Terms of Service:</label>
                                <textarea class="form-control" id="tos" rows="6" readonly>
This is a generic Terms of Service agreement. 
By installing this software, you agree to use it responsibly and at your own risk. The creators of DecepTest are not liable for any misuse, data loss, or security incidents that may arise from its operation. This tool is intended for authorized security testing and awareness training only. Do not use it for illegal or malicious activities.
                                </textarea>
                                <div class="form-check mt-3">
                                    <input class="form-check-input" type="checkbox" id="tos_agree" required>
                                    <label class="form-check-label" for="tos_agree">I agree to the terms and conditions.</label>
                                </div>
                            </div>
                            <!-- Step 2: Database -->
                            <div class="tab-pane fade" id="step2">
                                <h5>Database Configuration</h5>
                                <p>Enter your MySQL database credentials. The installer will attempt to create the database if it doesn't exist.</p>
                                <div class="mb-3"><label class="form-label">Database Host</label><input type="text" name="db_host" class="form-control" value="127.0.0.1" required></div>
                                <div class="mb-3"><label class="form-label">Database Name</label><input type="text" name="db_name" class="form-control" required></div>
                                <div class="mb-3"><label class="form-label">Database User</label><input type="text" name="db_user" class="form-control" required></div>
                                <div class="mb-3"><label class="form-label">Database Password</label><input type="password" name="db_pass" class="form-control"></div>
                            </div>
                            <!-- Step 3: Company Details -->
                            <div class="tab-pane fade" id="step3">
                                <h5>Company Details</h5>
                                <p>This information will be used for display purposes on the phishing landing page.</p>
                                <div class="mb-3"><label class="form-label">Company Name</label><input type="text" name="company_name" class="form-control" required></div>
                                <div class="mb-3"><label class="form-label">Company Website (Optional)</label><input type="url" name="company_website" class="form-control" placeholder="https://example.com"></div>
                                <div class="mb-3"><label class="form-label">Company Phone (Optional)</label><input type="tel" name="company_phone" class="form-control"></div>
                            </div>
                            <!-- Step 4: File & DB Installation -->
                            <div class="tab-pane fade" id="step4">
                                <h5>Installation Progress</h5>
                                <p>The installer will now download the application files and set up the database. This may take a moment.</p>
                                <div class="progress" style="height: 25px;"><div id="install-progress" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%;"></div></div>
                                <div id="install-log" class="mt-3 p-2 border bg-light" style="height: 150px; overflow-y: scroll; font-family: monospace; font-size: 0.8rem;"></div>
                            </div>
                            <!-- Step 5: Entra ID Config -->
                            <div class="tab-pane fade" id="step5">
                                <h5>Microsoft Entra ID Configuration</h5>
                                <p>Enter the details for your Microsoft Entra ID (Azure AD) application for user authentication.</p>
                                <div class="mb-3"><label class="form-label">Application (client) ID</label><input type="text" name="oauth_app_id" class="form-control" required></div>
                                <div class="mb-3"><label class="form-label">Client Secret Value</label><input type="password" name="oauth_app_secret" class="form-control" required></div>
                                <div class="mb-3"><label class="form-label">Directory (tenant) ID</label><input type="text" name="oauth_tenant_id" class="form-control" required></div>
                                <div class="mb-3"><label class="form-label">Redirect URI</label><input type="url" name="oauth_redirect_uri" class="form-control" value="<?php echo htmlspecialchars($default_redirect_uri); ?>" required></div>
                            </div>
                             <!-- Step 6: Finish -->
                            <div class="tab-pane fade" id="step6">
                                <h5>Installation Complete!</h5>
                                <div class="alert alert-success">
                                    <h4 class="alert-heading">Success!</h4>
                                    <p>DecepTest has been successfully installed. For security, please delete the <strong>installer.php</strong> file from your server immediately.</p>
                                </div>
                                <a href="index.php" class="btn btn-primary w-100">Go to Login Page</a>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer d-flex justify-content-between">
                        <button type="button" class="btn btn-secondary" id="prevBtn" disabled>Previous</button>
                        <button type="button" class="btn btn-primary" id="nextBtn">Next</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const isUpdateMode = <?php echo json_encode($is_update_mode); ?>;
    
    const showNotification = (areaId, message, type = 'success') => {
        const el = document.getElementById(areaId);
        if(el) el.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
    };

    if (isUpdateMode) {
        // --- Updater Logic ---
        const updateCheckArea = document.getElementById('update-check-area');
        const updateInfoArea = document.getElementById('update-info-area');
        const updateBtn = document.getElementById('startUpdateBtn');

        async function checkForUpdates() {
            try {
                const res = await fetch('installer.php', {
                    method: 'POST',
                    body: new URLSearchParams({ action: 'check_for_updates' })
                });
                const data = await res.json();
                
                updateCheckArea.classList.add('d-none');
                updateInfoArea.classList.remove('d-none');

                if (!data.success) throw new Error(data.message);

                document.getElementById('current-version-display').textContent = data.current_version;
                document.getElementById('latest-version-display').textContent = data.latest_version;

                if (data.update_available) {
                    showNotification('update-notification-area', `An update to version ${data.latest_version} is available.`, 'info');
                    updateBtn.disabled = false;
                } else {
                    showNotification('update-notification-area', 'You are on the latest version.', 'success');
                    updateBtn.disabled = true;
                    updateBtn.textContent = 'Already Up-to-Date';
                }

            } catch (error) {
                updateCheckArea.innerHTML = `<div class="alert alert-danger">${error.message}</div>`;
            }
        }
        
        updateBtn.addEventListener('click', function() {
            // This is where the actual update file download/DB migration would be triggered.
            // For now, it's a placeholder.
             showNotification('update-notification-area', `Update process would run here.`, 'warning');
        });

        checkForUpdates();

    } else {
        // --- Installer Logic ---
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');
        const form = document.getElementById('installerForm');
        const tabs = Array.from(document.querySelectorAll('#installerTab .nav-link'));
        let currentTab = 0;

        const updateButtons = () => {
            prevBtn.disabled = currentTab === 0;
            nextBtn.style.display = (currentTab >= tabs.length - 1) ? 'none' : 'inline-block';
        };

        const navigateToTab = (index) => {
            if (index < 0 || index >= tabs.length) return;
            tabs[index].disabled = false;
            new bootstrap.Tab(tabs[index]).show();
            currentTab = index;
            updateButtons();
        };

        prevBtn.addEventListener('click', () => navigateToTab(currentTab - 1));
        nextBtn.addEventListener('click', async function() {
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Working...';
            if (await validateAndProcessStep(currentTab)) {
                navigateToTab(currentTab + 1);
            }
            this.disabled = false;
            this.innerHTML = 'Next';
        });

        const sendRequest = async (formData, showGlobalNotification = true) => {
             try {
                const res = await fetch('installer.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (showGlobalNotification) showNotification('notification-area', data.message, data.success ? 'success' : 'danger');
                return data.success;
            } catch (error) {
                if (showGlobalNotification) showNotification('notification-area', `Request failed: ${error.message}`, 'danger');
                return false;
            }
        };

        async function validateAndProcessStep(stepIndex) {
            const pane = document.getElementById(`step${stepIndex + 1}`);
            let isValid = true;
            pane.querySelectorAll('[required]').forEach(input => {
                input.classList.remove('is-invalid');
                if (!(input.type === 'checkbox' ? input.checked : input.value)) {
                    isValid = false;
                    input.classList.add('is-invalid');
                }
            });
            if (!isValid) return false;
            
            let success = false;
            switch(stepIndex) {
                case 0: success = true; break;
                case 1: success = await sendRequest(new FormData(form, form.querySelector('button[type=submit]')), true); break;
                case 2: success = true; break;
                case 3: success = await runInstallationProcess(); break;
                case 4: success = await sendRequest(new FormData(form, form.querySelector('button[type=submit]')), true); break;
            }
            return success;
        }
        
        async function runInstallationProcess() {
            const progress = document.getElementById('install-progress');
            const log = document.getElementById('install-log');
            log.innerHTML = '';
            
            const logMessage = (msg, isError = false) => {
                 log.innerHTML += `<div class="${isError ? 'text-danger' : ''}">${msg}</div>`;
                 log.scrollTop = log.scrollHeight;
            }

            logMessage('Starting file download...');
            if (!await sendRequest(new URLSearchParams({ action: 'install_files' }), false)) { logMessage('File download failed.', true); return false; }
            logMessage('File download complete.');
            progress.style.width = '50%';
            
            logMessage('Setting up database...');
            if (!await sendRequest(new URLSearchParams({ action: 'setup_database' }), false)) { logMessage('Database setup failed.', true); return false; }
            logMessage('Database setup complete.');
            progress.style.width = '100%';

            return true;
        }
        updateButtons();
    }
});
</script>
</body>
</html>
