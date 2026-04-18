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

    // 5. Create clinical_ranges table with category
    $db->exec("CREATE TABLE IF NOT EXISTS clinical_ranges (
        parameter VARCHAR(50) PRIMARY KEY,
        category VARCHAR(50) DEFAULT 'rilevazioni',
        min_normal REAL,
        max_normal REAL,
        min_critical REAL,
        max_critical REAL,
        step REAL DEFAULT 0.1,
        unit VARCHAR(20)
    )");
    addColumnIfNotExists($db, 'clinical_ranges', 'category', "VARCHAR(50) DEFAULT 'rilevazioni'");
    echo "Tabella clinical_ranges verificata/aggiornata.<br>";

    // 6. Create tag_library table
    $db->exec("CREATE TABLE IF NOT EXISTS tag_library (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category VARCHAR(50),
        name VARCHAR(100) NOT NULL,
        UNIQUE(category, name)
    )");
    echo "Tabella tag_library verificata/creata.<br>";

    // 7. Migration of existing text to tags
    echo "Migrazione testi in tag...<br>";
    $text_fields = [
        'rilevazioni_cliniche' => ['maschera_venturi', 'hfno', 'niv'],
        'interventi' => ['comorbilita', 'tipo_intervento'],
        'esito_weaning' => ['tipo_post_estubazione']
    ];

    foreach ($text_fields as $table => $fields) {
        foreach ($fields as $field) {
            $stmt = $db->query("SELECT DISTINCT $field FROM $table WHERE $field IS NOT NULL AND $field != ''");
            while ($row = $stmt->fetch()) {
                $val = trim($row[$field]);
                if (empty($val)) continue;

                // For comma separated values, split them
                $parts = array_map('trim', explode(',', $val));
                foreach ($parts as $part) {
                    if (empty($part)) continue;
                    $ins = $db->prepare("INSERT IGNORE INTO tag_library (category, name) VALUES (?, ?)");
                    $ins->execute([$field, $part]);
                }
            }
        }
    }
    echo "Migrazione completata.<br>";

    echo "<br><strong>Aggiornamento completato con successo!</strong>";

} catch (Exception $e) {
    echo "<br><strong style='color:red;'>Errore durante l'aggiornamento: " . $e->getMessage() . "</strong>";
}
?>
