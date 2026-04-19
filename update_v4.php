<?php
require_once 'config.php';

$db = getDB();
$driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

echo "<h2>Migrazione Database v4</h2>";

function addColumn($db, $table, $column, $definition) {
    try {
        $db->exec("ALTER TABLE $table ADD COLUMN $column $definition");
        echo "✅ Colonna '$column' aggiunta a '$table'.<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'duplicate column') !== false || strpos($e->getMessage(), 'already exists') !== false) {
            echo "ℹ️ La colonna '$column' esiste già in '$table'.<br>";
        } else {
            echo "❌ Errore nell'aggiunta di '$column' a '$table': " . $e->getMessage() . "<br>";
        }
    }
}

if ($driver === 'sqlite') {
    addColumn($db, 'teams', 'team_key_hash', 'VARCHAR(255)');
    addColumn($db, 'pazienti', 'codice_fiscale', 'TEXT');
} else {
    addColumn($db, 'teams', 'team_key_hash', 'VARCHAR(255) AFTER encrypted_team_key');
    addColumn($db, 'pazienti', 'codice_fiscale', 'TEXT AFTER nome_cognome');
}

echo "<br>Migrazione completata. <a href='index.php'>Torna alla Dashboard</a>";
?>
