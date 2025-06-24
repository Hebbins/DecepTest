<?php
require_once 'config.php';

if (isset($_SESSION['user_id']) && !isset($_SESSION['pending_activation'])) {
    header('Location: dashboard.php');
    exit();
}

$login_url = "https://login.microsoftonline.com/" . OAUTH_TENANT_ID . "/oauth2/v2.0/authorize?" . http_build_query([
    'client_id' => OAUTH_APP_ID,
    'response_type' => 'code',
    'redirect_uri' => OAUTH_REDIRECT_URI,
    'response_mode' => 'query',
    'scope' => OAUTH_SCOPES,
    'state' => session_id()
]);

$logout_message = $_GET['logout_message'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background-color: #1a1a2e; }
        .login-container { min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-box h1 { color: #e0e0e0; font-size: 4rem; font-weight: bold; margin-bottom: 30px; text-shadow: 0 0 15px rgba(225, 70, 96, 0.5); }
        .btn-microsoft { background-color: #2f80ed; color: white; border: none; padding: 12px 25px; border-radius: 8px; font-weight: bold; transition: background-color 0.3s ease; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="text-center login-box">
            <h1><?php echo APP_NAME; ?></h1>
            <?php if (!empty($logout_message)): ?>
                <div class="alert alert-warning"><?php echo htmlspecialchars($logout_message); ?></div>
            <?php endif; ?>
            <a href="<?php echo htmlspecialchars($login_url); ?>" class="btn btn-lg btn-microsoft">
                <i class="bi bi-microsoft"></i> Sign in with Microsoft
            </a>
        </div>
    </div>
</body>
</html>
