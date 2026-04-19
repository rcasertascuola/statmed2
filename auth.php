<?php
session_start();
require_once 'config.php';

function login($username, $password) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['name'] = !empty($user['name']) ? $user['name'] : $user['username'];
        $_SESSION['sex'] = $user['sex'] ?? 'M';
        $_SESSION['role'] = $user['role'];

        // Log login
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'LOGIN', 'Utente ha effettuato l''accesso')");
        $stmt->execute([$user['id']]);

        // If team leader, try to decrypt team key
        $stmt = $db->prepare("SELECT id, encrypted_team_key FROM teams WHERE leader_id = ?");
        $stmt->execute([$user['id']]);
        $managed_teams = $stmt->fetchAll();

        $_SESSION['managed_teams'] = [];
        foreach ($managed_teams as $team) {
            if ($team['encrypted_team_key']) {
                $decrypted_key = decryptWithPassword($team['encrypted_team_key'], $password);
                if ($decrypted_key) {
                    $_SESSION['managed_teams'][$team['id']] = $decrypted_key;
                }
            }
        }

        // Initialize team_keys array in session if not exists
        if (!isset($_SESSION['team_keys'])) {
            $_SESSION['team_keys'] = [];
        }

        return true;
    }
    return false;
}

// Ensure session team_keys is always an array
if (isset($_SESSION['user_id']) && !isset($_SESSION['team_keys'])) {
    $_SESSION['team_keys'] = [];
}

function decryptWithKey($encrypted_data, $key_str) {
    $data = base64_decode($encrypted_data);
    if (!$data) return false;

    $iv_length = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($data, 0, $iv_length);
    $ciphertext = substr($data, $iv_length);

    $key = hash('sha256', $key_str, true);
    return openssl_decrypt($ciphertext, 'aes-256-cbc', $key, 0, $iv);
}

function encryptWithKey($data, $key_str) {
    $iv_length = openssl_cipher_iv_length('aes-256-cbc');
    $iv = openssl_random_pseudo_bytes($iv_length);

    $key = hash('sha256', $key_str, true);
    $ciphertext = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);

    return base64_encode($iv . $ciphertext);
}

function decryptWithPassword($encrypted_data, $password) {
    $data = base64_decode($encrypted_data);
    if (!$data) return false;

    $iv_length = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($data, 0, $iv_length);
    $ciphertext = substr($data, $iv_length);

    $key = hash('sha256', $password, true);
    return openssl_decrypt($ciphertext, 'aes-256-cbc', $key, 0, $iv);
}

function encryptWithPassword($data, $password) {
    $iv_length = openssl_cipher_iv_length('aes-256-cbc');
    $iv = openssl_random_pseudo_bytes($iv_length);

    $key = hash('sha256', $password, true);
    $ciphertext = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);

    return base64_encode($iv . $ciphertext);
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isLeader() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'leader';
}

function logout() {
    session_destroy();
}

function getUserTeams($userId) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT t.*, 1 as can_edit_all
        FROM teams t
        WHERE t.leader_id = ?
        UNION
        SELECT t.*, ut.can_edit_all
        FROM teams t
        JOIN user_teams ut ON t.id = ut.team_id
        WHERE ut.user_id = ?
    ");
    $stmt->execute([$userId, $userId]);
    return $stmt->fetchAll();
}
?>
