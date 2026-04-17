<?php
require_once 'config.php';

try {
    $db = getDB();

    // Create tables from schema.sql
    $sql = file_get_contents('schema.sql');
    $db->exec($sql);

    // Check if users exist
    $stmt = $db->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        // Create 1 admin and 2 base users
        $users = [
            ['admin', 'admin123', 'admin'],
            ['user1', 'user123', 'user'],
            ['user2', 'user123', 'user']
        ];

        $stmt = $db->prepare("INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)");
        foreach ($users as $u) {
            $stmt->execute([$u[0], password_hash($u[1], PASSWORD_DEFAULT), $u[2]]);
        }
        echo "Database initialized and 3 users created (admin/admin123, user1/user123, user2/user123).<br>";
        echo "IMPORTANT: The first login will set the global encryption key hash.";
    } else {
        echo "Database already initialized.";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
