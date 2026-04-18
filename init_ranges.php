<?php
require_once 'auth.php';

if (!isAdmin() && php_sapi_name() !== 'cli') {
    die("Unauthorized");
}

if (php_sapi_name() !== 'cli' && !isset($_GET['run'])) {
    die("Eseguire da riga di comando o aggiungere ?run=1 all'URL");
}

try {
    $db = getDB();
    echo "Analisi dati esistenti per inizializzazione range...<br>";

    $parameters = [
        'fr' => ['unit' => 'bpm', 'step' => 0.1],
        'tv' => ['unit' => 'L', 'step' => 0.001],
        'tobin_index' => ['unit' => 'f/Vt', 'step' => 0.1],
        'spo2' => ['unit' => '%', 'step' => 0.1],
        'fio2' => ['unit' => 'frac', 'step' => 0.01],
        'rox_index' => ['unit' => 'index', 'step' => 0.01],
        'peep' => ['unit' => 'cmH2O', 'step' => 0.1],
        'pressure_support' => ['unit' => 'cmH2O', 'step' => 0.1],
        'nrs_dolore' => ['unit' => '0-10', 'step' => 1],
        'nas_score' => ['unit' => 'score', 'step' => 0.1]
    ];

    foreach ($parameters as $param => $info) {
        $stmt = $db->query("SELECT MIN($param) as min_val, MAX($param) as max_val FROM rilevazioni_cliniche WHERE $param IS NOT NULL AND $param > 0");
        $res = $stmt->fetch();

        $min = $res['min_val'] ?? 0;
        $max = $res['max_val'] ?? 0;

        if ($min == 0 && $max == 0) {
            // Default values if no data exists
            $min_n = 0; $max_n = 0; $min_c = 0; $max_c = 0;
        } else {
            $min_n = $min;
            $max_n = $max;

            // Expand by 50% for critical range
            // If range is [10, 20], width is 10. 50% expansion adds 2.5 to each side.
            $width = $max - $min;
            if ($width == 0) {
                $min_c = $min * 0.75;
                $max_c = $max * 1.25;
            } else {
                $min_c = $min - ($width * 0.25);
                $max_c = $max + ($width * 0.25);
            }
        }

        // Constraints for specific parameters
        if ($param === 'spo2') {
            if ($max_n > 100) $max_n = 100;
            if ($max_c > 100) $max_c = 100;
            if ($min_c < 0) $min_c = 0;
        }
        if ($param === 'fio2') {
            if ($max_n > 1) $max_n = 1;
            if ($max_c > 1) $max_c = 1;
            if ($min_c < 0.21) $min_c = 0.21;
        }
        if ($param === 'nrs_dolore') {
            if ($max_n > 10) $max_n = 10;
            if ($max_c > 10) $max_c = 10;
            if ($min_c < 0) $min_c = 0;
        }

        $stmt = $db->prepare("INSERT INTO clinical_ranges (parameter, min_normal, max_normal, min_critical, max_critical, step, unit)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE
                            min_normal=VALUES(min_normal), max_normal=VALUES(max_normal),
                            min_critical=VALUES(min_critical), max_critical=VALUES(max_critical),
                            step=VALUES(step), unit=VALUES(unit)");
        $stmt->execute([$param, $min_n, $max_n, $min_c, $max_c, $info['step'], $info['unit']]);

        echo "Parametro $param: Normal [$min_n - $max_n], Critical [$min_c - $max_c]<br>";
    }

    echo "<br>Inizializzazione completata.";

} catch (Exception $e) {
    echo "Errore: " . $e->getMessage();
}
