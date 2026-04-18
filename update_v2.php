<?php
require_once 'config.php';

try {
    $db = getDB();
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

    echo "<h1>Aggiornamento Database V2 - Gestione Utenti e Equipe</h1>";

    function addColumnIfNotExists($db, $table, $column, $definition) {
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $check = $db->query("PRAGMA table_info(`$table`)");
            $exists = false;
            while ($col = $check->fetch()) {
                if ($col['name'] === $column) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $db->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
                echo "Aggiunta colonna '$column' a '$table' (SQLite).<br>";
            }
        } else {
            $check = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
            if ($check->rowCount() == 0) {
                $db->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
                echo "Aggiunta colonna '$column' a '$table'.<br>";
            } else {
                echo "La colonna '$column' in '$table' esiste già.<br>";
            }
        }
    }

    $pk_auto = ($driver === 'sqlite') ? "INTEGER PRIMARY KEY AUTOINCREMENT" : "INT AUTO_INCREMENT PRIMARY KEY";

    // New Tables
    $db->exec("CREATE TABLE IF NOT EXISTS hospitals (
        id $pk_auto,
        name VARCHAR(255) NOT NULL
    )");
    echo "Tabella hospitals verificata/creata.<br>";

    $db->exec("CREATE TABLE IF NOT EXISTS operative_units (
        id $pk_auto,
        hospital_id INT NOT NULL,
        name VARCHAR(255) NOT NULL
    )");
    echo "Tabella operative_units verificata/creata.<br>";

    $db->exec("CREATE TABLE IF NOT EXISTS teams (
        id $pk_auto,
        name VARCHAR(255) NOT NULL,
        leader_id INT,
        encrypted_team_key TEXT
    )");
    echo "Tabella teams verificata/creata.<br>";

    $db->exec("CREATE TABLE IF NOT EXISTS team_operative_units (
        team_id INT NOT NULL,
        operative_unit_id INT NOT NULL,
        PRIMARY KEY (team_id, operative_unit_id)
    )");
    echo "Tabella team_operative_units verificata/creata.<br>";

    $db->exec("CREATE TABLE IF NOT EXISTS user_teams (
        user_id INT NOT NULL,
        team_id INT NOT NULL,
        can_edit_all BOOLEAN DEFAULT 0,
        PRIMARY KEY (user_id, team_id)
    )");
    echo "Tabella user_teams verificata/creata.<br>";

    $db->exec("CREATE TABLE IF NOT EXISTS patient_teams (
        paziente_id INT NOT NULL,
        team_id INT NOT NULL,
        PRIMARY KEY (paziente_id, team_id)
    )");
    echo "Tabella patient_teams verificata/creata.<br>";

    // Update existing tables
    addColumnIfNotExists($db, 'pazienti', 'created_by', "INT");
    addColumnIfNotExists($db, 'interventi', 'created_by', "INT");
    addColumnIfNotExists($db, 'interventi', 'operative_unit_id', "INT");
    addColumnIfNotExists($db, 'rilevazioni_cliniche', 'created_by', "INT");
    addColumnIfNotExists($db, 'esito_weaning', 'created_by', "INT");

    // Migration of existing data
    $stmt = $db->prepare("SELECT id FROM hospitals WHERE name = 'Policlinico Palermo'");
    $stmt->execute();
    $hosp_id = $stmt->fetchColumn();
    if (!$hosp_id) {
        $db->exec("INSERT INTO hospitals (name) VALUES ('Policlinico Palermo')");
        $hosp_id = $db->lastInsertId();
    }

    $stmt = $db->prepare("SELECT id FROM operative_units WHERE name = 'Anestesia' AND hospital_id = ?");
    $stmt->execute([$hosp_id]);
    $ou_id = $stmt->fetchColumn();
    if (!$ou_id) {
        $db->prepare("INSERT INTO operative_units (hospital_id, name) VALUES (?, 'Anestesia')")->execute([$hosp_id]);
        $ou_id = $db->lastInsertId();
    }

    $stmt = $db->prepare("SELECT id FROM teams WHERE name = 'Equipe Anestesia Palermo'");
    $stmt->execute();
    $team_id = $stmt->fetchColumn();
    if (!$team_id) {
        $db->exec("INSERT INTO teams (name) VALUES ('Equipe Anestesia Palermo')");
        $team_id = $db->lastInsertId();

        $stmt = $db->prepare("SELECT COUNT(*) FROM team_operative_units WHERE team_id = ? AND operative_unit_id = ?");
        $stmt->execute([$team_id, $ou_id]);
        if ($stmt->fetchColumn() == 0) {
            $db->prepare("INSERT INTO team_operative_units (team_id, operative_unit_id) VALUES (?, ?)")->execute([$team_id, $ou_id]);
        }
    }

    echo "<br><strong>Migrazione iniziale completata!</strong>";

} catch (Exception $e) {
    echo "<br><strong style='color:red;'>Errore durante l'aggiornamento: " . $e->getMessage() . "</strong>";
}
?>
