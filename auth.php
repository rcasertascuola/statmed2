<?php
session_start();
require_once 'config.php';

function login($username, $password, $encryption_key) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Verify encryption key hash
        $stmt = $db->prepare("SELECT setting_value FROM app_settings WHERE setting_key = 'encryption_key_hash'");
        $stmt->execute();
        $stored_hash = $stmt->fetchColumn();

        $provided_key_hash = hash('sha256', $encryption_key);

        if (!$stored_hash) {
            // First time setup: store the hash
            $stmt = $db->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES ('encryption_key_hash', ?)");
            $stmt->execute([$provided_key_hash]);
            $stored_hash = $provided_key_hash;
        }

        if ($provided_key_hash === $stored_hash) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            // We do NOT store the encryption key in the session server-side
            // as it should stay client-side for "true" client-side encryption.
            // But we can return success.
            return true;
        }
    }
    return false;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function logout() {
    session_destroy();
}
?>
