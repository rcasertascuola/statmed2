<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'my_dottorci');
define('DB_USER', 'dottorci');
define('DB_PASS', '');

function getDB() {
    try {
        $pdo = new PDO("sqlite:database_ocr.sqlite");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}
?>
