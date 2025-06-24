<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit(); }

try {
    $user_check_stmt = $pdo->prepare("SELECT is_active, theme_preference, display_name, role FROM users WHERE id = ?");
    $user_check_stmt->execute([$_SESSION['user_id']]);
    $current_user = $user_check_stmt->fetch();

    if (!$current_user) { header('Location: logout.php'); exit(); }

    if ($current_user['is_active'] == 0 && !isset($_SESSION['pending_activation'])) {
         $_SESSION['pending_activation'] = true;
    } elseif ($current_user['is_active'] == 1) {
        unset($_SESSION['pending_activation']);
        $_SESSION['user_role'] = $current_user['role'];
    }
    
    $_SESSION['theme'] = $current_user['theme_preference'];
    $theme = $_SESSION['theme'];
    $display_name = $current_user['display_name'];

} catch (PDOException $e) { die("Error fetching user data: " . $e->getMessage()); }

$active_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?php echo htmlspecialchars($theme); ?>">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style>
        :root, [data-bs-theme="light"] { --bs-body-bg: #f8f9fa; --bs-body-color: #212529; --custom-card-bg: #ffffff; --custom-nav-bg: #e9ecef; --custom-border-color: #dee2e6; --editor-bg: #fff; --editor-line-color: #ced4da; --editor-line-text-color: #6c757d;}
        [data-bs-theme="dark"] { --bs-body-bg: #121212; --bs-body-color: #e0e0e0; --custom-card-bg: #1e1e1e; --custom-nav-bg: #1f2937; --custom-border-color: #444; --editor-bg: #2b2b2b; --editor-line-color: #444; --editor-line-text-color: #888; }
        body { background-color: var(--bs-body-bg); color: var(--bs-body-color); }
        .navbar { background-color: var(--custom-nav-bg); border-bottom: 1px solid var(--custom-border-color); }
        .card { background-color: var(--custom-card-bg); border-color: var(--custom-border-color); }
        .nav-link.active { font-weight: bold; }
        [data-bs-theme="dark"] .select2-container--bootstrap-5 .select2-selection { background-color: var(--bs-body-bg) !important; color: var(--bs-body-color) !important; border-color: var(--custom-border-color) !important; }
        [data-bs-theme="dark"] .select2-container--bootstrap-5 .select2-dropdown { background-color: var(--custom-card-bg); color: var(--bs-body-color); border-color: var(--custom-border-color) !important; }
        [data-bs-theme="dark"] .select2-container--bootstrap-5 .select2-results__option--highlighted { background-color: #0d6efd; }
        [data-bs-theme="dark"] .select2-container--bootstrap-5 .select2-results__option[aria-selected=true] { background-color: #333; }

        /* Line Number Editor Styles */
        .editor-container {
            display: flex;
            flex-direction: row;
            border: 1px solid var(--custom-border-color);
            border-radius: .375rem;
            overflow: hidden;
            max-height: 400px; /* Set max height for the container */
        }
        .line-numbers {
            flex-shrink: 0;
            width: 45px;
            padding: .5rem .75rem;
            text-align: right;
            color: var(--editor-line-text-color);
            background-color: var(--editor-bg);
            border-right: 1px solid var(--editor-line-color);
            font-family: monospace;
            font-size: 1rem;
            line-height: 1.5;
            user-select: none;
            overflow-y: hidden;
        }
        .line-numbered-textarea {
            flex-grow: 1;
            padding: .5rem .75rem;
            border: none;
            font-family: monospace;
            font-size: 1rem;
            line-height: 1.5;
            background-color: var(--editor-bg);
            color: var(--bs-body-color);
            resize: none;
            overflow-y: auto; /* Allow the textarea itself to scroll */
        }
        .line-numbered-textarea:focus { outline: none; box-shadow: none; }
    </style>
</head>
<body>
<?php if(isset($_SESSION['pending_activation'])): ?>
<div class="d-flex flex-column justify-content-center align-items-center vh-100 text-center p-3">
    <h1 class="display-1"><i class="bi bi-lock-fill text-warning"></i></h1>
    <h2 class="h1">Access Pending</h2>
    <p class="lead">Your account requires activation by an administrator.</p>
    <p>Once your account is approved, please refresh this page.</p>
    <div>
        <button class="btn btn-primary" onclick="window.location.reload()"><i class="bi bi-arrow-clockwise"></i> Refresh Page</button>
        <a href="logout.php" class="btn btn-secondary">Logout</a>
    </div>
</div>
<?php else: ?>
<nav class="navbar navbar-expand-lg shadow-sm mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="dashboard.php"><i class="bi bi-bug-fill text-primary"></i> <?php echo APP_NAME; ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"><span class="navbar-toggler-icon"></span></button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link <?php echo ($active_page == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link <?php echo ($active_page == 'campaigns.php') ? 'active' : ''; ?>" href="campaigns.php">Campaigns</a></li>
        <li class="nav-item"><a class="nav-link <?php echo ($active_page == 'targets.php') ? 'active' : ''; ?>" href="targets.php">Targets</a></li>
        <li class="nav-item"><a class="nav-link <?php echo ($active_page == 'templates.php') ? 'active' : ''; ?>" href="templates.php">Templates</a></li>
        <li class="nav-item"><a class="nav-link <?php echo ($active_page == 'reports.php') ? 'active' : ''; ?>" href="reports.php">Reports</a></li>
        <?php if (($_SESSION['user_role'] ?? 'user') === 'admin'): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">Admin</a>
            <ul class="dropdown-menu">
              <li><a class="dropdown-item" href="admin_users.php">Manage Users</a></li>
              <li><a class="dropdown-item" href="admin_mailers.php">Manage Mailers</a></li>
              <li><a class="dropdown-item" href="admin_settings.php">Application Settings</a></li>
              <li><a class="dropdown-item" href="admin_cron_logs.php">Cron Logs</a></li>
            </ul>
          </li>
        <?php endif; ?>
      </ul>
      <div class="d-flex align-items-center">
        <span class="navbar-text me-3 d-none d-lg-inline">Welcome, <?php echo htmlspecialchars(explode(' ', $display_name)[0]); ?></span>
        <button id="theme-toggle" class="btn btn-outline-secondary me-3" title="Toggle Theme"><i class="bi"></i></button>
        <a href="logout.php" class="btn btn-outline-danger">Logout</a>
      </div>
    </div>
  </div>
</nav>
<main class="container">
<?php endif; ?>
