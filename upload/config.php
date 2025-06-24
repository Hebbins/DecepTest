<?php
// File: config.php

// --- Start Session ---
// Best practice to have this at the top of your config file.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Error Reporting ---
// Set to 0 in a production environment
error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- Application Details ---
define('APP_NAME', 'DecepTest');

// --- Demo Mode Setting ---
// Set to true to enable demo mode and bypass M365 authentication
// define('DEMO_MODE', false); // <--- THIS HAS BEEN REMOVED IN RELEASE

// --- Database Configuration ---
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'deceptest_app');

// --- Encryption Key for SMTP Passwords ---
define('ENCRYPTION_KEY', 'CHANGEME_Your_Super_Secret_32_Character_Key_CHANGEME');

// --- Microsoft Entra ID App Configuration ---
define('OAUTH_APP_ID', 'YOUR_APPLICATION_CLIENT_ID');
define('OAUTH_APP_SECRET', 'YOUR_CLIENT_SECRET_VALUE');
define('OAUTH_TENANT_ID', 'YOUR_DIRECTORY_TENANT_ID');
define('OAUTH_REDIRECT_URI', 'http://localhost/deceptest/callback.php');
define('OAUTH_SCOPES', 'openid profile email User.Read');

// --- Application URLs ---
define('ROOT_URL', 'https://my-site.tld/deceptest/');

// --- Establish Database Connection (PDO) ---
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    // Set PDO to throw exceptions on error
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Fetch results as associative arrays
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // In a real app, you might log this error and show a user-friendly message.
    die("ERROR: Could not connect to the database. " . $e->getMessage());
}

// --- Helper Functions for Encrypting/Decrypting SMTP passwords ---
/**
 * Encrypts data using AES-256-CBC.
 * @param string $data The data to encrypt.
 * @return string The base64 encoded encrypted data.
 */
function encrypt_data($data) {
    if(empty($data)) return null;
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', ENCRYPTION_KEY, 0, $iv);
    // Prepend the IV to the encrypted data for use in decryption
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypts data encrypted by encrypt_data.
 * @param string $data The base64 encoded data to decrypt.
 * @return string|null The decrypted data, or null on failure.
 */
function decrypt_data($data) {
    if(empty($data)) return null;
    $data = base66_decode($data);
    $ivlen = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($data, 0, $ivlen);
    $encrypted_data = substr($data, $ivlen);
    return openssl_decrypt($encrypted_data, 'aes-256-cbc', ENCRYPTION_KEY, 0, $iv);
}