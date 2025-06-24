<?php
require_once 'config.php';

$logout_message = $_GET['logout_message'] ?? '';
if (empty($logout_message) && isset($_GET['reason']) && $_GET['reason'] === 'inactive') {
    $logout_message = 'Your account is inactive. Please contact an administrator.';
}

$logout_url = "https://login.microsoftonline.com/common/oauth2/v2.0/logout?" . http_build_query([
    'post_logout_redirect_uri' => ROOT_URL . 'index.php?logout_message=' . urlencode($logout_message)
]);

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

header('Location: ' . $logout_url);
exit();
?>
