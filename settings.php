<?php
require_once 'auth.php';

if (!isAdmin() && !isLeader()) {
    header('Location: index.php');
    exit;
}

$db = getDB();

// Handle Actions
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'add_hospital':
                $stmt = $db->prepare("INSERT INTO hospitals (name) VALUES (?)");
                $stmt->execute([$_POST['name']]);
                $message = "Ospedale aggiunto.";
                break;
            case 'add_ou':
                $stmt = $db->prepare("INSERT INTO operative_units (hospital_id, name) VALUES (?, ?)");
                $stmt->execute([$_POST['hospital_id'], $_POST['name']]);
                $message = "Unità Operativa aggiunta.";
                break;
            case 'update_hospital':
                if (!isAdmin()) throw new Exception("Solo l'admin può rinominare ospedali.");
                $stmt = $db->prepare("UPDATE hospitals SET name = ? WHERE id = ?");
                $stmt->execute([$_POST['name'], $_POST['hospital_id']]);
                $message = "Ospedale rinominato.";
                break;
            case 'update_ou':
                if (!isAdmin()) throw new Exception("Solo l'admin può rinominare unità operative.");
                $stmt = $db->prepare("UPDATE operative_units SET name = ? WHERE id = ?");
                $stmt->execute([$_POST['name'], $_POST['ou_id']]);
                $message = "Unità Operativa rinominata.";
                break;
            case 'add_team':
                $stmt = $db->prepare("INSERT INTO teams (name, leader_id) VALUES (?, ?)");
                $stmt->execute([$_POST['name'], $_POST['leader_id']]);
                $team_id = $db->lastInsertId();
                if (!empty($_POST['ou_ids'])) {
                    $stmt = $db->prepare("INSERT INTO team_operative_units (team_id, operative_unit_id) VALUES (?, ?)");
                    foreach ($_POST['ou_ids'] as $ou_id) {
                        $stmt->execute([$team_id, $ou_id]);
                    }
                }
                $message = "Equipe creata.";
                break;
            case 'update_user_role':
                if (!isAdmin()) throw new Exception("Solo l'admin può cambiare i ruoli.");
                $stmt = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$_POST['role'], $_POST['user_id']]);
                $message = "Ruolo utente aggiornato.";
                break;
            case 'assign_user_team':
                if (isLeader()) {
                    // Check if this leader actually leads this team
                    $check = $db->prepare("SELECT id FROM teams WHERE id = ? AND leader_id = ?");
                    $check->execute([$_POST['team_id'], $_SESSION['user_id']]);
                    if (!$check->fetch()) throw new Exception("Non puoi gestire questa equipe.");
                }
                $stmt = $db->prepare("INSERT IGNORE INTO user_teams (user_id, team_id, can_edit_all) VALUES (?, ?, ?)");
                $stmt->execute([$_POST['user_id'], $_POST['team_id'], isset($_POST['can_edit_all']) ? 1 : 0]);
                $message = "Utente assegnato all'equipe.";
                break;
            case 'remove_user_team':
                if (isLeader()) {
                    $check = $db->prepare("SELECT id FROM teams WHERE id = ? AND leader_id = ?");
                    $check->execute([$_POST['team_id'], $_SESSION['user_id']]);
                    if (!$check->fetch()) throw new Exception("Non puoi gestire questa equipe.");
                }
                $stmt = $db->prepare("DELETE FROM user_teams WHERE user_id = ? AND team_id = ?");
                $stmt->execute([$_POST['user_id'], $_POST['team_id']]);
                $message = "Utente rimosso dall'equipe.";
                break;
            case 'add_user':
                if (!isAdmin()) throw new Exception("Solo l'admin può creare utenti.");
                $user_pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, name, sex, password_hash, role) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$_POST['username'], $_POST['name'], $_POST['sex'], $user_pass, $_POST['role']]);
                $message = "Nuovo utente creato.";
                break;
            case 'set_team_key':
                $team_id = $_POST['team_id'];
                $leader_pass = $_POST['leader_password'];
                $new_key = $_POST['team_key'];

                $encrypted_key = encryptWithPassword($new_key, $leader_pass);
                $stmt = $db->prepare("UPDATE teams SET encrypted_team_key = ? WHERE id = ?");
                $stmt->execute([$encrypted_key, $team_id]);
                $message = "Chiave equipe impostata.";
                break;
            case 'update_team':
                if (isLeader()) {
                    $check = $db->prepare("SELECT id FROM teams WHERE id = ? AND leader_id = ?");
                    $check->execute([$_POST['team_id'], $_SESSION['user_id']]);
                    if (!$check->fetch()) throw new Exception("Non puoi gestire questa equipe.");
                }
                $db->beginTransaction();
                $stmt = $db->prepare("UPDATE teams SET name = ? WHERE id = ?");
                $stmt->execute([$_POST['name'], $_POST['team_id']]);

                $stmt = $db->prepare("DELETE FROM team_operative_units WHERE team_id = ?");
                $stmt->execute([$_POST['team_id']]);

                if (!empty($_POST['ou_ids'])) {
                    $stmt = $db->prepare("INSERT INTO team_operative_units (team_id, operative_unit_id) VALUES (?, ?)");
                    foreach ($_POST['ou_ids'] as $ou_id) {
                        $stmt->execute([$_POST['team_id'], $ou_id]);
                    }
                }
                $db->commit();
                $message = "Equipe aggiornata.";
                break;
        }
    } catch (Exception $e) {
        $message = "Errore: " . $e->getMessage();
    }
}

// Fetch data
if (isAdmin()) {
    $hospitals = $db->query("SELECT * FROM hospitals")->fetchAll();
    $ous = $db->query("SELECT ou.*, h.name as hospital_name FROM operative_units ou JOIN hospitals h ON ou.hospital_id = h.id")->fetchAll();
    $teams = $db->query("SELECT t.*, u.username as leader_name FROM teams t LEFT JOIN users u ON t.leader_id = u.id")->fetchAll();
    $users = $db->query("SELECT * FROM users ORDER BY name")->fetchAll();
} else {
    // Leader sees only their teams and related data
    $stmt = $db->prepare("SELECT t.*, u.username as leader_name FROM teams t LEFT JOIN users u ON t.leader_id = u.id WHERE t.leader_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $teams = $stmt->fetchAll();

    $team_ids = array_column($teams, 'id');
    if (empty($team_ids)) {
        $ous = [];
        $hospitals = [];
    } else {
        $placeholders = implode(',', array_fill(0, count($team_ids), '?'));
        $stmt = $db->prepare("
            SELECT ou.*, h.name as hospital_name
            FROM operative_units ou
            JOIN hospitals h ON ou.hospital_id = h.id
            JOIN team_operative_units tou ON ou.id = tou.operative_unit_id
            WHERE tou.team_id IN ($placeholders)
        ");
        $stmt->execute($team_ids);
        $ous = $stmt->fetchAll();

        $hosp_ids = array_unique(array_column($ous, 'hospital_id'));
        if (empty($hosp_ids)) {
            $hospitals = [];
        } else {
            $placeholders_h = implode(',', array_fill(0, count($hosp_ids), '?'));
            $stmt = $db->prepare("SELECT * FROM hospitals WHERE id IN ($placeholders_h)");
            $stmt->execute(array_values($hosp_ids));
            $hospitals = $stmt->fetchAll();
        }
    }
    // Leader needs all users to assign them to their team
    $users = $db->query("SELECT * FROM users ORDER BY name")->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Impostazioni - StatMed2</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .tab-btn.active { border-color: #3b82f6; color: #3b82f6; border-bottom-width: 2px; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <nav class="bg-blue-600 text-white p-3 shadow-lg relative z-50">
        <div class="container mx-auto">
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <a href="index.php" class="hover:opacity-80 transition" title="Dashboard">
                        <img src="assets/logo_small.png" alt="Logo" class="h-8 w-auto">
                    </a>
                </div>

                <div class="flex items-center gap-2 font-bold uppercase tracking-wider text-sm md:text-base">
                    <i class="ph ph-gear"></i>
                    <span>Impostazioni</span>
                </div>

                <div class="flex items-center">
                    <div class="hidden md:flex items-center gap-2">
                        <a href="index.php" class="p-2 hover:bg-blue-700 rounded-full transition" title="Dashboard">
                            <i class="ph ph-gauge text-xl"></i>
                        </a>
                        <a href="pazienti.php" class="p-2 hover:bg-blue-700 rounded-full transition" title="Pazienti">
                            <i class="ph ph-users text-xl"></i>
                        </a>
                        <a href="stats.php" class="p-2 hover:bg-blue-700 rounded-full transition" title="Statistiche">
                            <i class="ph ph-chart-line-up text-xl"></i>
                        </a>
                        <a href="profile.php" class="p-2 hover:bg-blue-700 rounded-full transition" title="Profilo">
                            <i class="ph ph-user text-xl"></i>
                        </a>
                        <a href="login.php?action=logout" class="p-2 hover:bg-red-600 rounded-full transition" title="Esci">
                            <i class="ph ph-sign-out text-xl"></i>
                        </a>
                    </div>
                    <button onclick="toggleMobileMenu()" class="md:hidden p-2 hover:bg-blue-700 rounded-full transition">
                        <i class="ph ph-list text-2xl"></i>
                    </button>
                </div>
            </div>
        </div>
        <div id="mobileMenu" class="hidden absolute top-full left-0 w-full bg-blue-700 shadow-xl md:hidden">
            <div class="flex flex-col p-2">
                <a href="index.php" class="flex items-center gap-3 p-3 hover:bg-blue-800 rounded-lg transition">
                    <i class="ph ph-gauge text-xl"></i>
                    <span>Dashboard</span>
                </a>
                <a href="pazienti.php" class="flex items-center gap-3 p-3 hover:bg-blue-800 rounded-lg transition">
                    <i class="ph ph-users text-xl"></i>
                    <span>Pazienti</span>
                </a>
                <a href="stats.php" class="flex items-center gap-3 p-3 hover:bg-blue-800 rounded-lg transition">
                    <i class="ph ph-chart-line-up text-xl"></i>
                    <span>Statistiche</span>
                </a>
                <a href="profile.php" class="flex items-center gap-3 p-3 hover:bg-blue-800 rounded-lg transition">
                    <i class="ph ph-user text-xl"></i>
                    <span>Profilo</span>
                </a>
                <a href="login.php?action=logout" class="flex items-center gap-3 p-3 hover:bg-red-600 rounded-lg transition">
                    <i class="ph ph-sign-out text-xl"></i>
                    <span>Esci</span>
                </a>
            </div>
        </div>
    </nav>
    <script>
    function toggleMobileMenu() {
        const menu = document.getElementById('mobileMenu');
        menu.classList.toggle('hidden');
    }
    </script>

    <main class="container mx-auto p-4 md:p-8">
        <?php if ($message): ?>
            <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-4"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="flex justify-between items-center mb-6">
            <div class="flex border-b overflow-x-auto">
                <button onclick="openTab('teams-tab')" id="btn-teams-tab" class="tab-btn active px-4 py-2 text-sm font-medium text-gray-500 hover:text-blue-600 whitespace-nowrap">
                    <?php echo isAdmin() ? 'Equipe e Ospedali' : 'La Mia Equipe'; ?>
                </button>
                <button onclick="openTab('users-tab')" id="btn-users-tab" class="tab-btn px-4 py-2 text-sm font-medium text-gray-500 hover:text-blue-600 whitespace-nowrap">Membri Equipe</button>
                <button onclick="openTab('clinical-tab')" id="btn-clinical-tab" class="tab-btn px-4 py-2 text-sm font-medium text-gray-500 hover:text-blue-600 whitespace-nowrap">Configurazione Clinica</button>
                <?php if (isAdmin()): ?>
                    <button onclick="openTab('new-user-tab')" id="btn-new-user-tab" class="tab-btn px-4 py-2 text-sm font-medium text-gray-500 hover:text-blue-600 whitespace-nowrap">Nuovo Utente</button>
                <?php endif; ?>
            </div>
        </div>

        <!-- TEAMS TAB -->
        <div id="teams-tab" class="tab-content active space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <section class="bg-white p-6 rounded-lg shadow">
                    <h2 class="text-lg font-bold mb-4 flex items-center gap-2"><i class="ph ph-hospital"></i> Ospedali e U.O.</h2>
                    <?php if (isAdmin()): ?>
                    <form method="POST" class="mb-6">
                        <input type="hidden" name="action" value="add_hospital">
                        <div class="flex gap-2">
                            <input type="text" name="name" placeholder="Nome Ospedale" class="flex-1 p-2 border rounded" required>
                            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">+</button>
                        </div>
                    </form>
                    <form method="POST" class="mb-4">
                        <input type="hidden" name="action" value="add_ou">
                        <div class="space-y-2">
                            <select name="hospital_id" class="w-full p-2 border rounded" required>
                                <option value="">Seleziona Ospedale...</option>
                                <?php foreach ($hospitals as $h): ?>
                                    <option value="<?php echo $h['id']; ?>"><?php echo htmlspecialchars($h['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="flex gap-2">
                                <input type="text" name="name" placeholder="Nome Unità Operativa" class="flex-1 p-2 border rounded" required>
                                <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded">+</button>
                            </div>
                        </div>
                    </form>
                    <?php endif; ?>
                    <div class="mt-4 border-t pt-4">
                        <h3 class="text-xs font-bold text-gray-400 uppercase mb-2">Modifica Ospedali</h3>
                        <div class="space-y-2 max-h-40 overflow-y-auto">
                            <?php foreach ($hospitals as $h): ?>
                                <form method="POST" class="flex gap-2 items-center">
                                    <input type="hidden" name="action" value="update_hospital">
                                    <input type="hidden" name="hospital_id" value="<?php echo $h['id']; ?>">
                                    <input type="text" name="name" value="<?php echo htmlspecialchars($h['name']); ?>" class="flex-1 p-1 text-xs border rounded" required>
                                    <button type="submit" class="text-blue-500 hover:text-blue-700" title="Rinomina">
                                        <i class="ph ph-check-circle text-lg"></i>
                                    </button>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mt-4 border-t pt-4">
                        <h3 class="text-xs font-bold text-gray-400 uppercase mb-2">Modifica Unità Operative</h3>
                        <div class="space-y-2 max-h-60 overflow-y-auto">
                            <?php foreach ($ous as $ou): ?>
                                <form method="POST" class="flex flex-col gap-1 p-2 border rounded bg-gray-50">
                                    <input type="hidden" name="action" value="update_ou">
                                    <input type="hidden" name="ou_id" value="<?php echo $ou['id']; ?>">
                                    <div class="flex justify-between items-center">
                                        <span class="text-[10px] text-gray-400 font-bold uppercase"><?php echo htmlspecialchars($ou['hospital_name']); ?></span>
                                        <button type="submit" class="text-blue-500 hover:text-blue-700" title="Rinomina">
                                            <i class="ph ph-check-circle text-lg"></i>
                                        </button>
                                    </div>
                                    <input type="text" name="name" value="<?php echo htmlspecialchars($ou['name']); ?>" class="w-full p-1 text-xs border rounded" required>
                                </form>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>

                <section class="bg-white p-6 rounded-lg shadow">
                    <h2 class="text-lg font-bold mb-4 flex items-center gap-2"><i class="ph ph-users-four"></i> Gestione Equipe</h2>
                    <?php if (isAdmin()): ?>
                    <form method="POST" class="space-y-4 mb-4">
                        <input type="hidden" name="action" value="add_team">
                        <input type="text" name="name" placeholder="Nome Equipe" class="w-full p-2 border rounded" required>
                        <select name="leader_id" class="w-full p-2 border rounded" required>
                            <option value="">Seleziona Capo Equipe...</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name'] ?? $u['username']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="text-sm font-bold text-gray-600">Unità Operative associate:</div>
                        <div class="grid grid-cols-2 gap-2 text-xs max-h-32 overflow-y-auto border p-2 rounded">
                            <?php foreach ($ous as $ou): ?>
                                <label class="flex items-center gap-1">
                                    <input type="checkbox" name="ou_ids[]" value="<?php echo $ou['id']; ?>">
                                    <?php echo htmlspecialchars($ou['name']); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" class="w-full bg-blue-600 text-white p-2 rounded">Crea Equipe</button>
                    </form>
                    <?php else: ?>
                        <?php foreach ($teams as $t):
                            $stmt = $db->prepare("SELECT operative_unit_id FROM team_operative_units WHERE team_id = ?");
                            $stmt->execute([$t['id']]);
                            $active_ous = $stmt->fetchAll(PDO::FETCH_COLUMN);

                            // Fetch members of this team
                            $stmt = $db->prepare("
                                SELECT u.name, u.username, ut.can_edit_all, u.id as user_id
                                FROM users u
                                JOIN user_teams ut ON u.id = ut.user_id
                                WHERE ut.team_id = ?
                                UNION
                                SELECT name, username, 1 as can_edit_all, id as user_id
                                FROM users
                                WHERE id = ?
                            ");
                            $stmt->execute([$t['id'], $t['leader_id']]);
                            $members = $stmt->fetchAll();
                        ?>
                        <div class="p-4 border rounded bg-gray-50 mb-4">
                            <form method="POST" class="space-y-4 mb-4">
                                <input type="hidden" name="action" value="update_team">
                                <input type="hidden" name="team_id" value="<?php echo $t['id']; ?>">
                                <div>
                                    <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nome Equipe</label>
                                    <input type="text" name="name" value="<?php echo htmlspecialchars($t['name']); ?>" class="w-full p-2 border rounded" required>
                                </div>
                                <div class="text-sm font-bold text-gray-600">Unità Operative associate:</div>
                                <div class="grid grid-cols-2 gap-2 text-xs max-h-32 overflow-y-auto border p-2 rounded bg-white">
                                    <?php
                                    // Fetch all OUs to let leader choose from
                                    $all_ous = $db->query("SELECT ou.*, h.name as hospital_name FROM operative_units ou JOIN hospitals h ON ou.hospital_id = h.id")->fetchAll();
                                    foreach ($all_ous as $ou): ?>
                                        <label class="flex items-center gap-1">
                                            <input type="checkbox" name="ou_ids[]" value="<?php echo $ou['id']; ?>" <?php echo in_array($ou['id'], $active_ous) ? 'checked' : ''; ?>>
                                            <?php echo htmlspecialchars($ou['name']); ?> (<?php echo htmlspecialchars($ou['hospital_name']); ?>)
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <button type="submit" class="w-full bg-blue-600 text-white p-2 rounded text-sm">Salva Modifiche</button>
                            </form>

                            <div class="mt-4 border-t pt-4">
                                <h4 class="text-xs font-bold text-gray-400 uppercase mb-2">Membri dell'Equipe</h4>
                                <ul class="text-xs space-y-1">
                                    <?php foreach ($members as $m): ?>
                                        <li class="flex justify-between items-center bg-white p-1 rounded px-2 border border-gray-100">
                                            <span><?php echo htmlspecialchars($m['name'] ?? $m['username']); ?></span>
                                            <div class="flex items-center gap-2">
                                                <span class="text-[10px] text-gray-400 font-bold uppercase">
                                                    <?php echo $m['user_id'] == $t['leader_id'] ? 'Capo' : ($m['can_edit_all'] ? 'Editor' : 'User'); ?>
                                                </span>
                                                <?php if ($m['user_id'] != $t['leader_id']): ?>
                                                    <form method="POST" onsubmit="return confirm('Rimuovere questo membro?')" class="inline">
                                                        <input type="hidden" name="action" value="remove_user_team">
                                                        <input type="hidden" name="team_id" value="<?php echo $t['id']; ?>">
                                                        <input type="hidden" name="user_id" value="<?php echo $m['user_id']; ?>">
                                                        <button type="submit" class="text-red-500 hover:text-red-700" title="Rimuovi">
                                                            <i class="ph ph-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>
            </div>

            <?php if (isLeader()): ?>
            <section class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-lg font-bold mb-4 flex items-center gap-2"><i class="ph ph-shield-check"></i> Sicurezza e Chiave Equipe</h2>
                <div class="bg-yellow-50 p-4 rounded text-sm text-yellow-800 mb-6">
                    <strong>Controllo Chiave:</strong> Come Capo Equipe, sei l'unico a poter impostare la chiave di cifratura. La tua password attuale verrà usata per proteggere la chiave del team.
                </div>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <input type="hidden" name="action" value="set_team_key">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Equipe da Cifrare</label>
                        <select name="team_id" class="w-full p-2 border rounded" required>
                            <option value="">Seleziona...</option>
                            <?php foreach ($teams as $t): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nuova Chiave Segreta</label>
                        <input type="password" name="team_key" class="w-full p-2 border rounded" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Tua Password (Login)</label>
                        <input type="password" name="leader_password" class="w-full p-2 border rounded" required>
                    </div>
                    <button type="submit" class="bg-red-600 text-white p-2 rounded shadow font-bold">Imposta Chiave</button>
                </form>
            </section>
            <?php endif; ?>
        </div>

        <!-- NEW USER TAB -->
        <div id="new-user-tab" class="tab-content space-y-6">
            <section class="bg-white p-6 rounded-lg shadow max-w-lg mx-auto">
                <h2 class="text-lg font-bold mb-4 flex items-center gap-2"><i class="ph ph-user-plus"></i> Crea Nuovo Utente</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add_user">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Username</label>
                        <input type="text" name="username" class="w-full p-2 border rounded" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nome e Cognome</label>
                        <input type="text" name="name" class="w-full p-2 border rounded" required>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Sesso</label>
                            <select name="sex" class="w-full p-2 border rounded">
                                <option value="M">M</option>
                                <option value="F">F</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Ruolo</label>
                            <select name="role" class="w-full p-2 border rounded">
                                <option value="user">User (Medico)</option>
                                <option value="admin">Admin (Super User)</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Password Iniziale</label>
                        <input type="password" name="password" class="w-full p-2 border rounded" required>
                    </div>
                    <button type="submit" class="w-full bg-blue-600 text-white p-2 rounded font-bold hover:bg-blue-700 transition">Crea Utente</button>
                </form>
            </section>
        </div>

        <!-- USERS TAB -->
        <div id="users-tab" class="tab-content space-y-8">
            <div class="grid grid-cols-1 <?php echo isAdmin() ? 'md:grid-cols-3' : 'md:grid-cols-1'; ?> gap-8">
                <?php if (isAdmin()): ?>
                <section class="md:col-span-2 bg-white p-6 rounded-lg shadow">
                    <h2 class="text-lg font-bold mb-4 flex items-center gap-2"><i class="ph ph-user-gear"></i> Lista Utenti e Ruoli</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50 text-gray-500 border-b">
                                    <th class="text-left p-3">Username / Nome</th>
                                    <th class="text-left p-3">Ruolo</th>
                                    <th class="text-right p-3">Cambia Ruolo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                <tr class="border-b hover:bg-gray-50">
                                    <td class="p-3">
                                        <strong><?php echo htmlspecialchars($u['username']); ?></strong><br>
                                        <span class="text-xs text-gray-500"><?php echo htmlspecialchars($u['name']); ?></span>
                                    </td>
                                    <td class="p-3">
                                        <span class="px-2 py-0.5 rounded text-xs font-bold <?php echo $u['role'] === 'admin' ? 'bg-purple-100 text-purple-700' : ($u['role'] === 'leader' ? 'bg-yellow-100 text-yellow-700' : 'bg-blue-100 text-blue-700'); ?>">
                                            <?php echo strtoupper($u['role']); ?>
                                        </span>
                                    </td>
                                    <td class="p-3 text-right">
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="update_user_role">
                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                            <select name="role" onchange="this.form.submit()" class="text-xs p-1 border rounded">
                                                <option value="user" <?php echo $u['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                                <option value="leader" <?php echo $u['role'] === 'leader' ? 'selected' : ''; ?>>Leader</option>
                                                <option value="admin" <?php echo $u['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                            </select>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
                <?php endif; ?>

                <section class="bg-white p-6 rounded-lg shadow <?php echo isLeader() ? 'max-w-xl mx-auto w-full' : ''; ?>">
                    <h2 class="text-lg font-bold mb-4 flex items-center gap-2"><i class="ph ph-user-plus"></i> Assegna Equipe</h2>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="assign_user_team">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Utente</label>
                            <select name="user_id" class="w-full p-2 border rounded" required>
                                <option value="">Seleziona...</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name'] ?? $u['username']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Equipe</label>
                            <select name="team_id" class="w-full p-2 border rounded" required>
                                <option value="">Seleziona...</option>
                                <?php foreach ($teams as $t): ?>
                                    <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <label class="flex items-center gap-2 text-sm bg-gray-50 p-2 rounded">
                            <input type="checkbox" name="can_edit_all">
                            <span>Abilitato a modificare tutto il team</span>
                        </label>
                        <button type="submit" class="w-full bg-gray-700 text-white p-2 rounded font-bold hover:bg-gray-800 transition">Assegna</button>
                    </form>
                </section>
            </div>
        </div>

        <!-- CLINICAL CONFIG TAB -->
        <div id="clinical-tab" class="tab-content space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Phase Order Management -->
                <section class="bg-white p-6 rounded-lg shadow">
                    <h2 class="text-lg font-bold mb-4 flex items-center gap-2"><i class="ph ph-list-numbers"></i> Ordine Fasi Cliniche</h2>
                    <p class="text-xs text-gray-500 mb-4">Trascina le fasi per cambiare l'ordine di visualizzazione nei grafici. Le fasi disattivate non verranno mostrate.</p>

                    <div id="phase-list" class="space-y-2 mb-6">
                        <!-- Loaded via JS -->
                    </div>

                    <button onclick="savePhaseOrder()" class="w-full bg-blue-600 text-white p-2 rounded font-bold hover:bg-blue-700 transition">Salva Ordine Fasi</button>

                    <div class="mt-6 pt-6 border-t">
                        <h3 class="text-sm font-bold text-gray-600 mb-2 uppercase">Aggiungi Nuova Fase</h3>
                        <div class="flex gap-2">
                            <input type="text" id="new-phase-name" placeholder="Es: POST_24H" class="flex-1 p-2 border rounded text-sm uppercase">
                            <button onclick="addPhase()" class="bg-green-600 text-white px-4 py-2 rounded font-bold hover:bg-green-700 shadow transition">+</button>
                        </div>
                    </div>
                </section>

                <!-- Links to clinical_config.php parts -->
                <section class="bg-white p-6 rounded-lg shadow">
                    <h2 class="text-lg font-bold mb-4 flex items-center gap-2"><i class="ph ph-stethoscope"></i> Altre Configurazioni</h2>
                    <div class="grid grid-cols-1 gap-4">
                        <a href="clinical_config.php" class="flex items-center justify-between p-4 bg-gray-50 rounded-xl border border-gray-200 hover:border-blue-300 hover:bg-blue-50 transition group">
                            <div class="flex items-center gap-3">
                                <div class="bg-blue-100 p-2 rounded-lg text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition">
                                    <i class="ph ph-thermometer text-2xl"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-800">Range Clinici</h3>
                                    <p class="text-xs text-gray-500">Configura i limiti normali e critici per i parametri.</p>
                                </div>
                            </div>
                            <i class="ph ph-caret-right text-gray-400"></i>
                        </a>

                        <a href="clinical_config.php#tags" class="flex items-center justify-between p-4 bg-gray-50 rounded-xl border border-gray-200 hover:border-blue-300 hover:bg-blue-50 transition group" onclick="localStorage.setItem('active_clinical_tab', 'tags-tab')">
                            <div class="flex items-center gap-3">
                                <div class="bg-purple-100 p-2 rounded-lg text-purple-600 group-hover:bg-purple-600 group-hover:text-white transition">
                                    <i class="ph ph-tags text-2xl"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-gray-800">Libreria Tag</h3>
                                    <p class="text-xs text-gray-500">Gestisci i tag per interventi, comorbidità, ecc.</p>
                                </div>
                            </div>
                            <i class="ph ph-caret-right text-gray-400"></i>
                        </a>
                    </div>
                </section>
            </div>
        </div>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        function openTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            document.getElementById('btn-' + tabId).classList.add('active');

            if (tabId === 'clinical-tab') loadPhaseOrder();
        }

        let phaseSortable;
        async function loadPhaseOrder() {
            const res = await fetch('api.php?action=app_settings&key=phase_order');
            const data = await res.json();
            let phases = [];
            try {
                phases = JSON.parse(data.value || '["PRE_SBT","SBT","ESTUBAZIONE","T0","T30","POST_2H","POST_6H","POST_12H"]');
            } catch(e) {
                phases = ["PRE_SBT","SBT","ESTUBAZIONE","T0","T30","POST_2H","POST_6H","POST_12H"];
            }

            // We also want to support hidden phases or a complete list
            // For now let's just use what's in the setting

            const container = document.getElementById('phase-list');
            container.innerHTML = '';

            phases.forEach(phase => {
                const item = document.createElement('div');
                item.className = 'flex items-center gap-3 p-3 bg-gray-50 border rounded-lg cursor-move hover:bg-gray-100 transition';
                item.dataset.phase = phase;
                item.innerHTML = `
                    <i class="ph ph-dots-six-vertical text-gray-400"></i>
                    <span class="flex-1 font-bold text-sm">${phase}</span>
                    <button onclick="removePhase(this)" class="text-red-500 hover:text-red-700 p-1">
                        <i class="ph ph-trash"></i>
                    </button>
                `;
                container.appendChild(item);
            });

            if (phaseSortable) phaseSortable.destroy();
            phaseSortable = new Sortable(container, {
                animation: 150,
                ghostClass: 'bg-blue-50'
            });
        }

        async function savePhaseOrder() {
            const phases = Array.from(document.querySelectorAll('#phase-list > div')).map(el => el.dataset.phase);
            const res = await fetch('api.php?action=app_settings', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ key: 'phase_order', value: JSON.stringify(phases) })
            });
            if (res.ok) alert('Ordine fasi salvato!');
            else alert('Errore nel salvataggio.');
        }

        function addPhase() {
            const nameInput = document.getElementById('new-phase-name');
            const name = nameInput.value.trim().toUpperCase();
            if (!name) return;

            // Check if already exists
            const existing = Array.from(document.querySelectorAll('#phase-list > div')).map(el => el.dataset.phase);
            if (existing.includes(name)) {
                alert('Questa fase esiste già.');
                return;
            }

            const container = document.getElementById('phase-list');
            const item = document.createElement('div');
            item.className = 'flex items-center gap-3 p-3 bg-gray-50 border rounded-lg cursor-move hover:bg-gray-100 transition';
            item.dataset.phase = name;
            item.innerHTML = `
                <i class="ph ph-dots-six-vertical text-gray-400"></i>
                <span class="flex-1 font-bold text-sm">${name}</span>
                <button onclick="removePhase(this)" class="text-red-500 hover:text-red-700 p-1">
                    <i class="ph ph-trash"></i>
                </button>
            `;
            container.appendChild(item);
            nameInput.value = '';
        }

        function removePhase(btn) {
            if (confirm('Rimuovere questa fase?')) {
                btn.closest('div').remove();
            }
        }
    </script>
</body>
</html>
