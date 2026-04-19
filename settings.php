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
    <nav class="bg-gray-800 text-white p-4 shadow-lg flex justify-between items-center">
        <div class="flex items-center gap-4">
            <a href="index.php" class="hover:text-gray-300 transition">
                <i class="ph ph-house text-2xl"></i>
            </a>
            <div class="flex items-center gap-2"><img src="assets/logo_small.png" alt="Logo" class="h-8 w-auto"><span class="text-xl font-bold">Amministrazione</span></div>
        </div>
        <div class="flex items-center gap-4">
            <span class="text-sm"><?php echo isAdmin() ? 'Admin' : 'Leader'; ?>: <strong><?php echo $_SESSION['name']; ?></strong></span>
            <a href="login.php?action=logout" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded text-sm">Esci</a>
        </div>
    </nav>

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
                <?php if (isAdmin()): ?>
                    <button onclick="openTab('new-user-tab')" id="btn-new-user-tab" class="tab-btn px-4 py-2 text-sm font-medium text-gray-500 hover:text-blue-600 whitespace-nowrap">Nuovo Utente</button>
                <?php endif; ?>
            </div>
            <a href="clinical_config.php" class="bg-blue-600 text-white px-4 py-2 rounded text-sm font-bold hover:bg-blue-700 flex items-center gap-2">
                <i class="ph ph-stethoscope"></i> Range e Tag
            </a>
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
                    <ul class="text-sm space-y-1 mt-4 border-t pt-4 max-h-40 overflow-y-auto">
                        <?php foreach ($ous as $ou): ?>
                            <li class="flex justify-between">
                                <span><?php echo htmlspecialchars($ou['name']); ?></span>
                                <span class="text-gray-400"><?php echo htmlspecialchars($ou['hospital_name']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
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
                        ?>
                        <form method="POST" class="space-y-4 p-4 border rounded bg-gray-50 mb-4">
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

    </main>

    <script>
        function openTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            document.getElementById('btn-' + tabId).classList.add('active');
        }
    </script>
</body>
</html>
