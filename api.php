<?php
require_once 'auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = getDB();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

function logActivity($db, $action, $details) {
    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $action, $details]);
}

switch ($action) {
    case 'pazienti':
        handlePazienti($db, $method);
        break;
    case 'interventi':
        handleInterventi($db, $method);
        break;
    case 'rilevazioni':
        handleRilevazioni($db, $method);
        break;
    case 'esito':
        handleEsito($db, $method);
        break;
    case 'all_data':
        handleAllData($db);
        break;
    case 'ranges':
        handleRanges($db, $method);
        break;
    case 'tags':
        handleTags($db, $method);
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}

function handlePazienti($db, $method) {
    if ($method === 'GET') {
        $stmt = $db->query("SELECT * FROM pazienti");
        echo json_encode($stmt->fetchAll());
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['id']) && !empty($data['id'])) {
            $stmt = $db->prepare("UPDATE pazienti SET nome_cognome=?, sesso=?, eta=?, altezza=?, peso=?, bmi=? WHERE id=?");
            $stmt->execute([$data['nome_cognome'], $data['sesso'], $data['eta'], $data['altezza'], $data['peso'], $data['bmi'], $data['id']]);
            logActivity($db, 'UPDATE_PAZIENTE', "ID: " . $data['id']);
        } else {
            $stmt = $db->prepare("INSERT INTO pazienti (nome_cognome, sesso, eta, altezza, peso, bmi) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$data['nome_cognome'], $data['sesso'], $data['eta'], $data['altezza'], $data['peso'], $data['bmi']]);
            logActivity($db, 'INSERT_PAZIENTE', "Nuovo paziente inserito");
        }
        echo json_encode(['success' => true]);
    } elseif ($method === 'DELETE') {
        if (!isAdmin()) { echo json_encode(['error' => 'Forbidden']); exit; }
        $id = $_GET['id'];
        $stmt = $db->prepare("DELETE FROM pazienti WHERE id = ?");
        $stmt->execute([$id]);
        logActivity($db, 'DELETE_PAZIENTE', "ID: " . $id);
        echo json_encode(['success' => true]);
    }
}

function handleInterventi($db, $method) {
    if ($method === 'GET') {
        $paziente_id = $_GET['paziente_id'] ?? null;
        if ($paziente_id) {
            $stmt = $db->prepare("SELECT * FROM interventi WHERE paziente_id = ?");
            $stmt->execute([$paziente_id]);
        } else {
            $stmt = $db->query("SELECT * FROM interventi");
        }
        echo json_encode($stmt->fetchAll());
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['id']) && !empty($data['id'])) {
            $stmt = $db->prepare("UPDATE interventi SET comorbilita=?, asa_score=?, tipo_intervento=?, urgenza=?, euroscore_ii=?, durata_cec_ore=?, timing_iot_h=? WHERE id=?");
            $stmt->execute([$data['comorbilita'], $data['asa_score'], $data['tipo_intervento'], $data['urgenza'], $data['euroscore_ii'], $data['durata_cec_ore'], $data['timing_iot_h'], $data['id']]);
            logActivity($db, 'UPDATE_INTERVENTO', "ID: " . $data['id']);
        } else {
            $stmt = $db->prepare("INSERT INTO interventi (paziente_id, comorbilita, asa_score, tipo_intervento, urgenza, euroscore_ii, durata_cec_ore, timing_iot_h) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$data['paziente_id'], $data['comorbilita'], $data['asa_score'], $data['tipo_intervento'], $data['urgenza'], $data['euroscore_ii'], $data['durata_cec_ore'], $data['timing_iot_h']]);
            logActivity($db, 'INSERT_INTERVENTO', "Paziente ID: " . $data['paziente_id']);
        }
        echo json_encode(['success' => true]);
    } elseif ($method === 'DELETE') {
        if (!isAdmin()) { echo json_encode(['error' => 'Forbidden']); exit; }
        $id = $_GET['id'];
        $stmt = $db->prepare("DELETE FROM interventi WHERE id = ?");
        $stmt->execute([$id]);
        logActivity($db, 'DELETE_INTERVENTO', "ID: " . $id);
        echo json_encode(['success' => true]);
    }
}

function handleRilevazioni($db, $method) {
    if ($method === 'GET') {
        $intervento_id = $_GET['intervento_id'] ?? null;
        if ($intervento_id) {
            $stmt = $db->prepare("SELECT * FROM rilevazioni_cliniche WHERE intervento_id = ?");
            $stmt->execute([$intervento_id]);
        } else {
            $stmt = $db->query("SELECT * FROM rilevazioni_cliniche");
        }
        echo json_encode($stmt->fetchAll());
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['id']) && !empty($data['id'])) {
            $stmt = $db->prepare("UPDATE rilevazioni_cliniche SET fase=?, fr=?, tv=?, tobin_index=?, spo2=?, fio2=?, rox_index=?, peep=?, pressure_support=?, nrs_dolore=?, nas_score=?, maschera_venturi=?, hfno=?, niv=?, data_ora=? WHERE id=?");
            $stmt->execute([$data['fase'], $data['fr'], $data['tv'], $data['tobin_index'], $data['spo2'], $data['fio2'], $data['rox_index'], $data['peep'], $data['pressure_support'], $data['nrs_dolore'], $data['nas_score'], $data['maschera_venturi'], $data['hfno'], $data['niv'], $data['data_ora'], $data['id']]);
            logActivity($db, 'UPDATE_RILEVAZIONE', "ID: " . $data['id']);
        } else {
            $stmt = $db->prepare("INSERT INTO rilevazioni_cliniche (intervento_id, fase, fr, tv, tobin_index, spo2, fio2, rox_index, peep, pressure_support, nrs_dolore, nas_score, maschera_venturi, hfno, niv, data_ora) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$data['intervento_id'], $data['fase'], $data['fr'], $data['tv'], $data['tobin_index'], $data['spo2'], $data['fio2'], $data['rox_index'], $data['peep'], $data['pressure_support'], $data['nrs_dolore'], $data['nas_score'], $data['maschera_venturi'], $data['hfno'], $data['niv'], $data['data_ora']]);
            logActivity($db, 'INSERT_RILEVAZIONE', "Intervento ID: " . $data['intervento_id']);
        }
        echo json_encode(['success' => true]);
    } elseif ($method === 'DELETE') {
        if (!isAdmin()) { echo json_encode(['error' => 'Forbidden']); exit; }
        $id = $_GET['id'];
        $stmt = $db->prepare("DELETE FROM rilevazioni_cliniche WHERE id = ?");
        $stmt->execute([$id]);
        logActivity($db, 'DELETE_RILEVAZIONE', "ID: " . $id);
        echo json_encode(['success' => true]);
    }
}

function handleEsito($db, $method) {
    if ($method === 'GET') {
        $intervento_id = $_GET['intervento_id'] ?? null;
        if ($intervento_id) {
            $stmt = $db->prepare("SELECT * FROM esito_weaning WHERE intervento_id = ?");
            $stmt->execute([$intervento_id]);
        } else {
            $stmt = $db->query("SELECT * FROM esito_weaning");
        }
        echo json_encode($stmt->fetchAll());
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['id']) && !empty($data['id'])) {
            $stmt = $db->prepare("UPDATE esito_weaning SET successo=?, tipo_post_estubazione=?, fallimento_iot=?, ore_da_estubazione_a_failure=? WHERE id=?");
            $stmt->execute([$data['successo'], $data['tipo_post_estubazione'], $data['fallimento_iot'], $data['ore_da_estubazione_a_failure'], $data['id']]);
            logActivity($db, 'UPDATE_ESITO', "ID: " . $data['id']);
        } else {
            $stmt = $db->prepare("INSERT INTO esito_weaning (intervento_id, successo, tipo_post_estubazione, fallimento_iot, ore_da_estubazione_a_failure) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$data['intervento_id'], $data['successo'], $data['tipo_post_estubazione'], $data['fallimento_iot'], $data['ore_da_estubazione_a_failure']]);
            logActivity($db, 'INSERT_ESITO', "Intervento ID: " . $data['intervento_id']);
        }
        echo json_encode(['success' => true]);
    } elseif ($method === 'DELETE') {
        if (!isAdmin()) { echo json_encode(['error' => 'Forbidden']); exit; }
        $id = $_GET['id'];
        $stmt = $db->prepare("DELETE FROM esito_weaning WHERE id = ?");
        $stmt->execute([$id]);
        logActivity($db, 'DELETE_ESITO', "ID: " . $id);
        echo json_encode(['success' => true]);
    }
}

function handleAllData($db) {
    // Join all tables for CSV export
    $sql = "SELECT p.*, i.comorbilita, i.asa_score, i.tipo_intervento, i.urgenza, i.euroscore_ii, i.durata_cec_ore, i.timing_iot_h,
            r.fase, r.fr, r.tv, r.tobin_index, r.spo2, r.fio2, r.rox_index, r.peep, r.pressure_support, r.nrs_dolore, r.nas_score, r.maschera_venturi, r.hfno, r.niv, r.data_ora,
            e.successo, e.tipo_post_estubazione, e.fallimento_iot, e.ore_da_estubazione_a_failure
            FROM pazienti p
            LEFT JOIN interventi i ON p.id = i.paziente_id
            LEFT JOIN rilevazioni_cliniche r ON i.id = r.intervento_id
            LEFT JOIN esito_weaning e ON i.id = e.intervento_id";
    $stmt = $db->query($sql);
    echo json_encode($stmt->fetchAll());
}

function handleRanges($db, $method) {
    if ($method === 'GET') {
        $stmt = $db->query("SELECT * FROM clinical_ranges ORDER BY category, parameter");
        echo json_encode($stmt->fetchAll());
    } elseif ($method === 'POST') {
        if (!isAdmin()) { echo json_encode(['error' => 'Forbidden']); exit; }
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $db->prepare("UPDATE clinical_ranges SET min_normal=?, max_normal=?, min_critical=?, max_critical=?, step=?, unit=? WHERE parameter=?");
        $stmt->execute([
            $data['min_normal'] ?? 0,
            $data['max_normal'] ?? 0,
            $data['min_critical'] ?? 0,
            $data['max_critical'] ?? 0,
            $data['step'] ?? 0.1,
            $data['unit'] ?? '',
            $data['parameter'] ?? ''
        ]);
        logActivity($db, 'UPDATE_RANGE', "Parametro: " . ($data['parameter'] ?? 'unknown'));
        echo json_encode(['success' => true]);
    }
}

function handleTags($db, $method) {
    if ($method === 'GET') {
        $category = $_GET['category'] ?? null;
        if ($category) {
            $stmt = $db->prepare("SELECT name FROM tag_library WHERE category = ? ORDER BY name");
            $stmt->execute([$category]);
        } else {
            $stmt = $db->query("SELECT * FROM tag_library ORDER BY category, name");
        }
        echo json_encode($stmt->fetchAll());
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $db->prepare("INSERT IGNORE INTO tag_library (category, name) VALUES (?, ?)");
        $stmt->execute([$data['category'], $data['name']]);
        echo json_encode(['success' => true]);
    } elseif ($method === 'DELETE') {
        if (!isAdmin()) { echo json_encode(['error' => 'Forbidden']); exit; }
        $id = $_GET['id'];
        $stmt = $db->prepare("DELETE FROM tag_library WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    }
}
?>
