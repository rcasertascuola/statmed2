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
$user_id = $_SESSION['user_id'];
$current_team_id = $_SESSION['active_team_id'] ?? null;

function logActivity($db, $action, $details) {
    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $action, $details]);
}

// Permission Helpers
function canEditPatient($db, $paziente_id) {
    if (isAdmin()) return false; // Admin cannot edit patient data
    global $user_id, $current_team_id;
    if (!$current_team_id) return false;

    // 1. Check if user created the patient
    $stmt = $db->prepare("SELECT created_by FROM pazienti WHERE id = ?");
    $stmt->execute([$paziente_id]);
    if ($stmt->fetchColumn() == $user_id) return true;

    // 2. Check if user is Leader of the active team
    $stmt = $db->prepare("SELECT leader_id FROM teams WHERE id = ?");
    $stmt->execute([$current_team_id]);
    if ($stmt->fetchColumn() == $user_id) {
        // Verify patient belongs to this team
        $stmt = $db->prepare("SELECT COUNT(*) FROM patient_teams WHERE paziente_id = ? AND team_id = ?");
        $stmt->execute([$paziente_id, $current_team_id]);
        return $stmt->fetchColumn() > 0;
    }

    // 3. Check if user has can_edit_all in the active team
    $stmt = $db->prepare("SELECT can_edit_all FROM user_teams WHERE user_id = ? AND team_id = ?");
    $stmt->execute([$user_id, $current_team_id]);
    if ($stmt->fetchColumn() == 1) {
        // Verify patient belongs to this team
        $stmt = $db->prepare("SELECT COUNT(*) FROM patient_teams WHERE paziente_id = ? AND team_id = ?");
        $stmt->execute([$paziente_id, $current_team_id]);
        return $stmt->fetchColumn() > 0;
    }

    return false;
}

function canDelete($db, $table, $id) {
    if (isAdmin()) return false; // Admin cannot delete patient data
    global $user_id, $current_team_id;
    if (!$current_team_id) return false;

    // Usually only Admin or Creator or Leader can delete
    $stmt = $db->prepare("SELECT created_by FROM $table WHERE id = ?");
    $stmt->execute([$id]);
    $creator = $stmt->fetchColumn();
    if ($creator == $user_id) return true;

    // Check if Leader of the active team
    $stmt = $db->prepare("SELECT leader_id FROM teams WHERE id = ?");
    $stmt->execute([$current_team_id]);
    if ($stmt->fetchColumn() == $user_id) return true;

    return false;
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
    case 'rename_tag':
        handleRenameTag($db, $method);
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}

function handlePazienti($db, $method) {
    global $user_id, $current_team_id;
    if ($method === 'GET') {
        if (isAdmin()) {
            echo json_encode([]);
            exit;
        } else {
            // Patients in current team
            $stmt = $db->prepare("
                SELECT p.* FROM pazienti p
                JOIN patient_teams pt ON p.id = pt.paziente_id
                WHERE pt.team_id = ?
            ");
            $stmt->execute([$current_team_id]);
        }
        $pazienti = $stmt->fetchAll();
        foreach ($pazienti as &$p) {
            $p['can_edit'] = canEditPatient($db, $p['id']);
            $p['can_delete'] = canDelete($db, 'pazienti', $p['id']);
        }
        echo json_encode($pazienti);
    } elseif ($method === 'POST') {
        if (isAdmin()) { echo json_encode(['error' => 'Forbidden']); exit; }
        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['id']) && !empty($data['id'])) {
            if (!canEditPatient($db, $data['id'])) { echo json_encode(['error' => 'Forbidden']); exit; }
            $stmt = $db->prepare("UPDATE pazienti SET nome_cognome=?, sesso=?, eta=?, altezza=?, peso=?, bmi=? WHERE id=?");
            $stmt->execute([$data['nome_cognome'], $data['sesso'], $data['eta'], $data['altezza'], $data['peso'], $data['bmi'], $data['id']]);
            logActivity($db, 'UPDATE_PAZIENTE', "ID: " . $data['id']);
        } else {
            $stmt = $db->prepare("INSERT INTO pazienti (nome_cognome, sesso, eta, altezza, peso, bmi, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$data['nome_cognome'], $data['sesso'], $data['eta'], $data['altezza'], $data['peso'], $data['bmi'], $user_id]);
            $new_id = $db->lastInsertId();
            // Link to current team
            if ($current_team_id) {
                $stmt = $db->prepare("INSERT INTO patient_teams (paziente_id, team_id) VALUES (?, ?)");
                $stmt->execute([$new_id, $current_team_id]);
            }
            logActivity($db, 'INSERT_PAZIENTE', "Nuovo paziente ID: $new_id");
        }
        echo json_encode(['success' => true]);
    } elseif ($method === 'DELETE') {
        $id = $_GET['id'];
        if (!canDelete($db, 'pazienti', $id)) { echo json_encode(['error' => 'Forbidden']); exit; }
        $stmt = $db->prepare("DELETE FROM pazienti WHERE id = ?");
        $stmt->execute([$id]);
        logActivity($db, 'DELETE_PAZIENTE', "ID: " . $id);
        echo json_encode(['success' => true]);
    }
}

function handleInterventi($db, $method) {
    global $user_id, $current_team_id;
    if ($method === 'GET') {
        if (isAdmin()) {
            echo json_encode([]);
            exit;
        }
        $paziente_id = $_GET['paziente_id'] ?? null;
        if ($paziente_id) {
            $stmt = $db->prepare("
                SELECT i.*, ou.name as uo_name
                FROM interventi i
                LEFT JOIN operative_units ou ON i.operative_unit_id = ou.id
                WHERE i.paziente_id = ?
            ");
            $stmt->execute([$paziente_id]);
        } else {
            $stmt = $db->prepare("SELECT i.* FROM interventi i JOIN patient_teams pt ON i.paziente_id = pt.paziente_id WHERE pt.team_id = ?");
            $stmt->execute([$current_team_id]);
        }
        $interventi = $stmt->fetchAll();
        foreach ($interventi as &$i) {
            $i['can_edit'] = canEditPatient($db, $i['paziente_id']);
            $i['can_delete'] = canDelete($db, 'interventi', $i['id']);
        }
        echo json_encode($interventi);
    } elseif ($method === 'POST') {
        if (isAdmin()) { echo json_encode(['error' => 'Forbidden']); exit; }
        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['id']) && !empty($data['id'])) {
            // Check permission (creator or leader)
            if (!canDelete($db, 'interventi', $data['id']) && !canEditPatient($db, $data['paziente_id'])) {
                 echo json_encode(['error' => 'Forbidden']); exit;
            }
            $stmt = $db->prepare("UPDATE interventi SET comorbilita=?, asa_score=?, tipo_intervento=?, urgenza=?, euroscore_ii=?, durata_cec_ore=?, timing_iot_h=?, operative_unit_id=? WHERE id=?");
            $stmt->execute([$data['comorbilita'], $data['asa_score'], $data['tipo_intervento'], $data['urgenza'], $data['euroscore_ii'], $data['durata_cec_ore'], $data['timing_iot_h'], $data['operative_unit_id'], $data['id']]);
            logActivity($db, 'UPDATE_INTERVENTO', "ID: " . $data['id']);
        } else {
            if (!canEditPatient($db, $data['paziente_id'])) { echo json_encode(['error' => 'Forbidden']); exit; }
            $stmt = $db->prepare("INSERT INTO interventi (paziente_id, comorbilita, asa_score, tipo_intervento, urgenza, euroscore_ii, durata_cec_ore, timing_iot_h, created_by, operative_unit_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$data['paziente_id'], $data['comorbilita'], $data['asa_score'], $data['tipo_intervento'], $data['urgenza'], $data['euroscore_ii'], $data['durata_cec_ore'], $data['timing_iot_h'], $user_id, $data['operative_unit_id']]);
            logActivity($db, 'INSERT_INTERVENTO', "Paziente ID: " . $data['paziente_id']);
        }
        echo json_encode(['success' => true]);
    } elseif ($method === 'DELETE') {
        $id = $_GET['id'];
        if (!canDelete($db, 'interventi', $id)) { echo json_encode(['error' => 'Forbidden']); exit; }
        $stmt = $db->prepare("DELETE FROM interventi WHERE id = ?");
        $stmt->execute([$id]);
        logActivity($db, 'DELETE_INTERVENTO', "ID: " . $id);
        echo json_encode(['success' => true]);
    }
}

function handleRilevazioni($db, $method) {
    global $user_id;
    if ($method === 'GET') {
        if (isAdmin()) {
            echo json_encode([]);
            exit;
        }
        $intervento_id = $_GET['intervento_id'] ?? null;
        if ($intervento_id) {
            $stmt = $db->prepare("SELECT r.*, i.paziente_id FROM rilevazioni_cliniche r JOIN interventi i ON r.intervento_id = i.id WHERE r.intervento_id = ?");
            $stmt->execute([$intervento_id]);
        } else {
            echo json_encode([]);
            exit;
        }
        $rilevazioni = $stmt->fetchAll();
        foreach ($rilevazioni as &$r) {
            $r['can_edit'] = canEditPatient($db, $r['paziente_id'] ?? 0);
            $r['can_delete'] = canDelete($db, 'rilevazioni_cliniche', $r['id']);
        }
        echo json_encode($rilevazioni);
    } elseif ($method === 'POST') {
        if (isAdmin()) { echo json_encode(['error' => 'Forbidden']); exit; }
        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['id']) && !empty($data['id'])) {
            if (!canDelete($db, 'rilevazioni_cliniche', $data['id'])) { echo json_encode(['error' => 'Forbidden']); exit; }
            $stmt = $db->prepare("UPDATE rilevazioni_cliniche SET fase=?, fr=?, tv=?, tobin_index=?, spo2=?, fio2=?, rox_index=?, peep=?, pressure_support=?, nrs_dolore=?, nas_score=?, maschera_venturi=?, hfno=?, niv=?, data_ora=? WHERE id=?");
            $stmt->execute([$data['fase'], $data['fr'], $data['tv'], $data['tobin_index'], $data['spo2'], $data['fio2'], $data['rox_index'], $data['peep'], $data['pressure_support'], $data['nrs_dolore'], $data['nas_score'], $data['maschera_venturi'], $data['hfno'], $data['niv'], $data['data_ora'], $data['id']]);
            logActivity($db, 'UPDATE_RILEVAZIONE', "ID: " . $data['id']);
        } else {
            // We should ideally check if user has access to the intervento's patient
            $stmt = $db->prepare("INSERT INTO rilevazioni_cliniche (intervento_id, fase, fr, tv, tobin_index, spo2, fio2, rox_index, peep, pressure_support, nrs_dolore, nas_score, maschera_venturi, hfno, niv, data_ora, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$data['intervento_id'], $data['fase'], $data['fr'], $data['tv'], $data['tobin_index'], $data['spo2'], $data['fio2'], $data['rox_index'], $data['peep'], $data['pressure_support'], $data['nrs_dolore'], $data['nas_score'], $data['maschera_venturi'], $data['hfno'], $data['niv'], $data['data_ora'], $user_id]);
            logActivity($db, 'INSERT_RILEVAZIONE', "Intervento ID: " . $data['intervento_id']);
        }
        echo json_encode(['success' => true]);
    } elseif ($method === 'DELETE') {
        $id = $_GET['id'];
        if (!canDelete($db, 'rilevazioni_cliniche', $id)) { echo json_encode(['error' => 'Forbidden']); exit; }
        $stmt = $db->prepare("DELETE FROM rilevazioni_cliniche WHERE id = ?");
        $stmt->execute([$id]);
        logActivity($db, 'DELETE_RILEVAZIONE', "ID: " . $id);
        echo json_encode(['success' => true]);
    }
}

function handleEsito($db, $method) {
    global $user_id;
    if ($method === 'GET') {
        if (isAdmin()) {
            echo json_encode([]);
            exit;
        }
        $intervento_id = $_GET['intervento_id'] ?? null;
        if ($intervento_id) {
            $stmt = $db->prepare("SELECT e.*, i.paziente_id FROM esito_weaning e JOIN interventi i ON e.intervento_id = i.id WHERE e.intervento_id = ?");
            $stmt->execute([$intervento_id]);
        } else {
            echo json_encode([]);
            exit;
        }
        $esiti = $stmt->fetchAll();
        foreach ($esiti as &$e) {
            $e['can_edit'] = canEditPatient($db, $e['paziente_id'] ?? 0);
            $e['can_delete'] = canDelete($db, 'esito_weaning', $e['id']);
        }
        echo json_encode($esiti);
    } elseif ($method === 'POST') {
        if (isAdmin()) { echo json_encode(['error' => 'Forbidden']); exit; }
        $data = json_decode(file_get_contents('php://input'), true);
        if (isset($data['id']) && !empty($data['id'])) {
            if (!canDelete($db, 'esito_weaning', $data['id'])) { echo json_encode(['error' => 'Forbidden']); exit; }
            $stmt = $db->prepare("UPDATE esito_weaning SET successo=?, tipo_post_estubazione=?, fallimento_iot=?, ore_da_estubazione_a_failure=? WHERE id=?");
            $stmt->execute([$data['successo'], $data['tipo_post_estubazione'], $data['fallimento_iot'], $data['ore_da_estubazione_a_failure'], $data['id']]);
            logActivity($db, 'UPDATE_ESITO', "ID: " . $data['id']);
        } else {
            $stmt = $db->prepare("INSERT INTO esito_weaning (intervento_id, successo, tipo_post_estubazione, fallimento_iot, ore_da_estubazione_a_failure, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$data['intervento_id'], $data['successo'], $data['tipo_post_estubazione'], $data['fallimento_iot'], $data['ore_da_estubazione_a_failure'], $user_id]);
            logActivity($db, 'INSERT_ESITO', "Intervento ID: " . $data['intervento_id']);
        }
        echo json_encode(['success' => true]);
    } elseif ($method === 'DELETE') {
        $id = $_GET['id'];
        if (!canDelete($db, 'esito_weaning', $id)) { echo json_encode(['error' => 'Forbidden']); exit; }
        $stmt = $db->prepare("DELETE FROM esito_weaning WHERE id = ?");
        $stmt->execute([$id]);
        logActivity($db, 'DELETE_ESITO', "ID: " . $id);
        echo json_encode(['success' => true]);
    }
}

function handleAllData($db) {
    global $current_team_id;
    if (isAdmin()) {
        echo json_encode([]);
        exit;
    }
    // Join all tables for CSV export
    $sql = "SELECT p.*, i.comorbilita, i.asa_score, i.tipo_intervento, i.urgenza, i.euroscore_ii, i.durata_cec_ore, i.timing_iot_h,
            r.fase, r.fr, r.tv, r.tobin_index, r.spo2, r.fio2, r.rox_index, r.peep, r.pressure_support, r.nrs_dolore, r.nas_score, r.maschera_venturi, r.hfno, r.niv, r.data_ora,
            e.successo, e.tipo_post_estubazione, e.fallimento_iot, e.ore_da_estubazione_a_failure
            FROM pazienti p
            LEFT JOIN interventi i ON p.id = i.paziente_id
            LEFT JOIN rilevazioni_cliniche r ON i.id = r.intervento_id
            LEFT JOIN esito_weaning e ON i.id = e.intervento_id";

    $sql .= " JOIN patient_teams pt ON p.id = pt.paziente_id WHERE pt.team_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$current_team_id]);

    echo json_encode($stmt->fetchAll());
}

function handleRanges($db, $method) {
    if ($method === 'GET') {
        $stmt = $db->query("SELECT * FROM clinical_ranges ORDER BY category, parameter");
        echo json_encode($stmt->fetchAll());
    } elseif ($method === 'POST') {
        if (!isAdmin() && !isLeader()) { echo json_encode(['error' => 'Forbidden']); exit; }
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

function handleRenameTag($db, $method) {
    if ($method !== 'POST') return;
    if (!isAdmin() && !isLeader()) { echo json_encode(['error' => 'Forbidden']); exit; }

    $data = json_decode(file_get_contents('php://input'), true);
    $oldName = $data['old_name'];
    $newName = $data['new_name'];
    $category = $data['category'];

    try {
        $db->beginTransaction();

        // 1. Update tag library
        $stmt = $db->prepare("SELECT id FROM tag_library WHERE category = ? AND name = ?");
        $stmt->execute([$category, $newName]);
        $exists = $stmt->fetch();

        if ($exists) {
            $stmt = $db->prepare("DELETE FROM tag_library WHERE category = ? AND name = ?");
            $stmt->execute([$category, $oldName]);
        } else {
            $stmt = $db->prepare("UPDATE tag_library SET name = ? WHERE category = ? AND name = ?");
            $stmt->execute([$newName, $category, $oldName]);
        }

        // 2. Update existing data in clinical tables
        $mappings = [
            'tipo_intervento' => ['table' => 'interventi', 'column' => 'tipo_intervento'],
            'comorbilita' => ['table' => 'interventi', 'column' => 'comorbilita'],
            'maschera_venturi' => ['table' => 'rilevazioni_cliniche', 'column' => 'maschera_venturi'],
            'hfno' => ['table' => 'rilevazioni_cliniche', 'column' => 'hfno'],
            'niv' => ['table' => 'rilevazioni_cliniche', 'column' => 'niv'],
            'tipo_post_estubazione' => ['table' => 'esito_weaning', 'column' => 'tipo_post_estubazione']
        ];

        if (isset($mappings[$category])) {
            $m = $mappings[$category];
            $table = $m['table'];
            $col = $m['column'];

            $stmt = $db->prepare("SELECT id, $col FROM $table WHERE $col LIKE ?");
            $stmt->execute(["%$oldName%"]);
            $rows = $stmt->fetchAll();

            foreach ($rows as $row) {
                $tags = array_map('trim', explode(',', $row[$col]));
                $updated = false;
                foreach ($tags as &$t) {
                    if ($t === $oldName) {
                        $t = $newName;
                        $updated = true;
                    }
                }
                if ($updated) {
                    $newVal = implode(', ', array_unique($tags));
                    $upd = $db->prepare("UPDATE $table SET $col = ? WHERE id = ?");
                    $upd->execute([$newVal, $row['id']]);
                }
            }
        }

        $db->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['error' => $e->getMessage()]);
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
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $ignore = ($driver === 'sqlite') ? 'OR IGNORE' : 'IGNORE';
        $stmt = $db->prepare("INSERT $ignore INTO tag_library (category, name) VALUES (?, ?)");
        $stmt->execute([$data['category'], $data['name']]);
        echo json_encode(['success' => true]);
    } elseif ($method === 'DELETE') {
        if (!isAdmin() && !isLeader()) { echo json_encode(['error' => 'Forbidden']); exit; }
        $id = $_GET['id'];
        $stmt = $db->prepare("DELETE FROM tag_library WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
    }
}
?>
