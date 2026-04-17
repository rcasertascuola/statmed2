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
            ['admin', 'Amministratore', 'M', 'admin123', 'admin'],
            ['user1', 'Operatore 1', 'M', 'user123', 'user'],
            ['user2', 'Operatore 2', 'F', 'user123', 'user']
        ];
        
        $stmt = $db->prepare("INSERT INTO users (username, name, sex, password_hash, role) VALUES (?, ?, ?, ?, ?)");
        foreach ($users as $u) {
            $stmt->execute([$u[0], $u[1], $u[2], password_hash($u[3], PASSWORD_DEFAULT), $u[4]]);
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

