<?php
require_once 'config.php';

try {
    $db = getDB();
    echo "<h1>Aggiornamento Database</h1>";

    // Helper function to add columns safely
    function addColumnIfNotExists($db, $table, $column, $definition) {
        $check = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
        if ($check->rowCount() == 0) {
            $db->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
            echo "Aggiunta colonna '$column' a '$table'.<br>";
        } else {
            echo "La colonna '$column' in '$table' esiste già.<br>";
        }
    }

    // 1. Create activity_logs table if it doesn't exist
    $db->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(50),
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )");
    echo "Tabella activity_logs verificata/creata.<br>";

    // 2. Create app_settings table if it doesn't exist
    $db->exec("CREATE TABLE IF NOT EXISTS app_settings (
        setting_key VARCHAR(50) PRIMARY KEY,
        setting_value TEXT
    )");
    echo "Tabella app_settings verificata/creata.<br>";

    // 3. Update users table with name and sex
    addColumnIfNotExists($db, 'users', 'name', "VARCHAR(100) AFTER username");
    addColumnIfNotExists($db, 'users', 'sex', "ENUM('M', 'F') DEFAULT 'M' AFTER name");

    // 4. Update rilevazioni_cliniche with new clinical fields
    addColumnIfNotExists($db, 'rilevazioni_cliniche', 'maschera_venturi', "TEXT");
    addColumnIfNotExists($db, 'rilevazioni_cliniche', 'hfno', "TEXT");
    addColumnIfNotExists($db, 'rilevazioni_cliniche', 'niv', "TEXT");
    addColumnIfNotExists($db, 'rilevazioni_cliniche', 'data_ora', "DATETIME DEFAULT CURRENT_TIMESTAMP");

    // 5. Create clinical_ranges table
    $db->exec("CREATE TABLE IF NOT EXISTS clinical_ranges (
        parameter VARCHAR(50) PRIMARY KEY,
        min_normal REAL,
        max_normal REAL,
        min_critical REAL,
        max_critical REAL,
        step REAL DEFAULT 0.1,
        unit VARCHAR(20)
    )");
    echo "Tabella clinical_ranges verificata/creata.<br>";

    echo "<br><strong>Aggiornamento completato con successo!</strong>";

} catch (Exception $e) {
    echo "<br><strong style='color:red;'>Errore durante l'aggiornamento: " . $e->getMessage() . "</strong>";
}
?>
