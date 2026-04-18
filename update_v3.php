<?php
require_once 'config.php';

try {
    $db = getDB();
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

    echo "<h1>Aggiornamento Database V3 - Ruolo Leader</h1>";

    if ($driver === 'mysql') {
        // Update users table role column
        $db->exec("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'leader', 'user') DEFAULT 'user'");
        echo "Colonna 'role' aggiornata a ENUM('admin', 'leader', 'user').<br>";
    }

    // Update users who are already leaders in the teams table
    $stmt = $db->query("SELECT DISTINCT leader_id FROM teams WHERE leader_id IS NOT NULL");
    $leaders = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($leaders)) {
        $placeholders = implode(',', array_fill(0, count($leaders), '?'));
        $stmt = $db->prepare("UPDATE users SET role = 'leader' WHERE id IN ($placeholders) AND role = 'user'");
        $stmt->execute($leaders);
        echo "Aggiornati " . $stmt->rowCount() . " utenti al ruolo 'leader'.<br>";

        // Ensure leaders are in user_teams for their teams
        $stmt = $db->query("SELECT id, leader_id FROM teams WHERE leader_id IS NOT NULL");
        $teams = $stmt->fetchAll();
        $ins = $db->prepare("INSERT IGNORE INTO user_teams (user_id, team_id, can_edit_all) VALUES (?, ?, 1)");
        foreach ($teams as $team) {
            $ins->execute([$team['leader_id'], $team['id']]);
        }
        echo "Assicurata la presenza dei leader nella tabella user_teams.<br>";
    }

    echo "<br><strong>Aggiornamento V3 completato!</strong>";

} catch (Exception $e) {
    echo "<br><strong style='color:red;'>Errore durante l'aggiornamento: " . $e->getMessage() . "</strong>";
}
?>
