<?php
require_once 'config.php';

if (!isset($_GET['state']) || empty($_GET['state']) || $_GET['state'] !== session_id()) {
    header('Location: index.php?error=state_mismatch'); exit();
}

if (!isset($_GET['code'])) {
    header('Location: index.php?error=no_code'); exit();
}

$token_url = "https://login.microsoftonline.com/" . OAUTH_TENANT_ID . "/oauth2/v2.0/token";
$token_params = [
    'client_id' => OAUTH_APP_ID, 'scope' => OAUTH_SCOPES, 'code' => $_GET['code'],
    'redirect_uri' => OAUTH_REDIRECT_URI, 'grant_type' => 'authorization_code', 'client_secret' => OAUTH_APP_SECRET
];

$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_params));
$response = curl_exec($ch);
curl_close($ch);
$token_data = json_decode($response, true);

if (!isset($token_data['access_token'])) {
    error_log("Token Error: " . $response);
    header('Location: index.php?error=token_failed'); exit();
}
$_SESSION['access_token'] = $token_data['access_token'];

$graph_url = "https://graph.microsoft.com/v1.0/me";
$ch = curl_init($graph_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $_SESSION['access_token']]);
$user_response = curl_exec($ch);
curl_close($ch);
$user_data = json_decode($user_response, true);

if (!isset($user_data['id'])) {
    error_log("Graph API Error: " . $user_response);
    header('Location: index.php?error=graph_failed'); exit();
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE m365_object_id = ?");
    $stmt->execute([$user_data['id']]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['theme'] = $user['theme_preference'];
        if ($user['is_active'] == 0) {
            $_SESSION['pending_activation'] = true;
        } else {
            unset($_SESSION['pending_activation']);
            $_SESSION['user_role'] = $user['role'];
        }
    } else {
        $stmt_count = $pdo->query("SELECT COUNT(*) as count FROM users");
        $is_first_user = ($stmt_count->fetch()['count'] == 0);
        $role = $is_first_user ? 'admin' : 'user';
        $is_active = $is_first_user ? 1 : 0;

        $insert_stmt = $pdo->prepare("INSERT INTO users (m365_object_id, email, display_name, role, is_active) VALUES (?, ?, ?, ?, ?)");
        $insert_stmt->execute([
            $user_data['id'], $user_data['mail'] ?? $user_data['userPrincipalName'],
            $user_data['displayName'], $role, $is_active
        ]);
        $new_user_id = $pdo->lastInsertId();
        $_SESSION['user_id'] = $new_user_id;
        $_SESSION['theme'] = 'dark';

        if (!$is_active) {
            $_SESSION['pending_activation'] = true;
        } else {
            $_SESSION['user_role'] = $role;
        }
    }
    header('Location: dashboard.php');
    exit();

} catch (PDOException $e) {
    error_log("User Provisioning Error: " . $e->getMessage());
    die("Database error during user provisioning. Please check logs.");
}
?>
