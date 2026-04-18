<?php
require_once 'config.php';

try {
    $db = getDB();
    echo "<h1>Migrazione Dati Esistenti</h1>";

    // 1. Get default team and OU IDs from previous migration
    $stmt = $db->prepare("SELECT id FROM hospitals WHERE name = 'Policlinico Palermo'");
    $stmt->execute();
    $hosp_id = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT id FROM operative_units WHERE name = 'Anestesia' AND hospital_id = ?");
    $stmt->execute([$hosp_id]);
    $ou_id = $stmt->fetchColumn();

    $stmt = $db->prepare("SELECT id FROM teams WHERE name = 'Equipe Anestesia Palermo'");
    $stmt->execute();
    $team_id = $stmt->fetchColumn();

    if (!$team_id || !$ou_id) {
        die("Errore: Equipe o U.O. di default non trovata. Eseguire prima update_v2.php.");
    }

    // 2. Assign all existing patients to the default team
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    $ignore = ($driver === 'sqlite') ? 'OR IGNORE' : 'IGNORE';

    $db->exec("INSERT $ignore INTO patient_teams (paziente_id, team_id) SELECT id, $team_id FROM pazienti");
    echo "Pazienti esistenti associati all'equipe di default.<br>";

    // 3. Assign all existing interventions to the default OU
    $db->exec("UPDATE interventi SET operative_unit_id = $ou_id WHERE operative_unit_id IS NULL");
    echo "Interventi esistenti associati all'U.O. di default.<br>";

    // 4. Handle specific users mentioned by the user
    $stmt = $db->query("SELECT id, username FROM users ORDER BY id LIMIT 3");
    $users = $stmt->fetchAll();

    if (count($users) >= 1) {
        $admin = $users[0];
        $db->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$admin['id']]);
        echo "Utente '{$admin['username']}' impostato come Admin.<br>";
    }

    if (count($users) >= 2) {
        $leader = $users[1];
        $db->prepare("UPDATE teams SET leader_id = ? WHERE id = ?")->execute([$leader['id'], $team_id]);
        $db->prepare("INSERT $ignore INTO user_teams (user_id, team_id, can_edit_all) VALUES (?, ?, 1)")->execute([$leader['id'], $team_id]);
        echo "Utente '{$leader['username']}' impostato come Capo Equipe.<br>";
    }

    if (count($users) >= 3) {
        $member = $users[2];
        $db->prepare("INSERT $ignore INTO user_teams (user_id, team_id, can_edit_all) VALUES (?, ?, 0)")->execute([$member['id'], $team_id]);
        echo "Utente '{$member['username']}' aggiunto all'equipe come membro.<br>";
    }

    echo "<br><strong>Migrazione completata!</strong>";

} catch (Exception $e) {
    echo "<br><strong style='color:red;'>Errore: " . $e->getMessage() . "</strong>";
}
?>
