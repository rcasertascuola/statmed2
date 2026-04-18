<?php
require_once 'auth.php';

if (!isAdmin()) {
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
                $stmt = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$_POST['role'], $_POST['user_id']]);
                $message = "Ruolo utente aggiornato.";
                break;
            case 'assign_user_team':
                $stmt = $db->prepare("INSERT IGNORE INTO user_teams (user_id, team_id, can_edit_all) VALUES (?, ?, ?)");
                $stmt->execute([$_POST['user_id'], $_POST['team_id'], isset($_POST['can_edit_all']) ? 1 : 0]);
                $message = "Utente assegnato all'equipe.";
                break;
            case 'set_team_key':
                $team_id = $_POST['team_id'];
                $leader_pass = $_POST['leader_password']; // Admin must know leader's password or we need another way
                $new_key = $_POST['team_key'];

                // We need the leader's clear password to encrypt the team key for them.
                // For simplicity in this admin interface, we'll ask for it.
                $encrypted_key = encryptWithPassword($new_key, $leader_pass);
                $stmt = $db->prepare("UPDATE teams SET encrypted_team_key = ? WHERE id = ?");
                $stmt->execute([$encrypted_key, $team_id]);
                $message = "Chiave equipe impostata.";
                break;
        }
    } catch (Exception $e) {
        $message = "Errore: " . $e->getMessage();
    }
}

// Fetch data
$hospitals = $db->query("SELECT * FROM hospitals")->fetchAll();
$ous = $db->query("SELECT ou.*, h.name as hospital_name FROM operative_units ou JOIN hospitals h ON ou.hospital_id = h.id")->fetchAll();
$teams = $db->query("SELECT t.*, u.username as leader_name FROM teams t LEFT JOIN users u ON t.leader_id = u.id")->fetchAll();
$users = $db->query("SELECT * FROM users")->fetchAll();

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Impostazioni - StatMed2</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <nav class="bg-gray-800 text-white p-4 shadow-lg flex justify-between items-center">
        <div class="flex items-center gap-4">
            <a href="index.php" class="hover:text-gray-300 transition">
                <i class="ph ph-house text-2xl"></i>
            </a>
            <h1 class="text-xl font-bold">Amministrazione</h1>
        </div>
        <a href="login.php?action=logout" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded text-sm">Esci</a>
    </nav>

    <main class="container mx-auto p-4 md:p-8 space-y-8">
        <?php if ($message): ?>
            <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-4"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Hospital Management -->
            <section class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-lg font-bold mb-4 flex items-center gap-2"><i class="ph ph-hospital"></i> Ospedali e U.O.</h2>
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
                <ul class="text-sm space-y-1 mt-4 border-t pt-4">
                    <?php foreach ($ous as $ou): ?>
                        <li class="flex justify-between">
                            <span><?php echo htmlspecialchars($ou['name']); ?></span>
                            <span class="text-gray-400"><?php echo htmlspecialchars($ou['hospital_name']); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <!-- User Roles -->
            <section class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-lg font-bold mb-4 flex items-center gap-2"><i class="ph ph-user-gear"></i> Ruoli Utenti</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b">
                                <th class="text-left py-2">Username</th>
                                <th class="text-left py-2">Ruolo</th>
                                <th class="text-right py-2">Azione</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr class="border-b">
                                <td class="py-2"><?php echo htmlspecialchars($u['username']); ?></td>
                                <td class="py-2">
                                    <span class="px-2 py-0.5 rounded text-xs <?php echo $u['role'] === 'admin' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-700'; ?>">
                                        <?php echo $u['role']; ?>
                                    </span>
                                </td>
                                <td class="py-2 text-right">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="update_user_role">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <select name="role" onchange="this.form.submit()" class="text-xs p-1 border rounded">
                                            <option value="user" <?php echo $u['role'] === 'user' ? 'selected' : ''; ?>>User</option>
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
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Team Management -->
            <section class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-lg font-bold mb-4 flex items-center gap-2"><i class="ph ph-users-four"></i> Gestione Equipe</h2>
                <form method="POST" class="space-y-4 mb-8 pb-8 border-b">
                    <input type="hidden" name="action" value="add_team">
                    <input type="text" name="name" placeholder="Nome Equipe" class="w-full p-2 border rounded" required>
                    <select name="leader_id" class="w-full p-2 border rounded" required>
                        <option value="">Seleziona Capo Equipe...</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name'] ?? $u['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="text-sm font-bold text-gray-600">Unità Operative associate:</div>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <?php foreach ($ous as $ou): ?>
                            <label class="flex items-center gap-1">
                                <input type="checkbox" name="ou_ids[]" value="<?php echo $ou['id']; ?>">
                                <?php echo htmlspecialchars($ou['name']); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" class="w-full bg-blue-600 text-white p-2 rounded">Crea Equipe</button>
                </form>

                <div class="space-y-4">
                    <h3 class="font-bold text-sm uppercase text-gray-400">Assegna Membro</h3>
                    <form method="POST" class="space-y-2">
                        <input type="hidden" name="action" value="assign_user_team">
                        <select name="user_id" class="w-full p-2 border rounded" required>
                            <option value="">Seleziona Utente...</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name'] ?? $u['username']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select name="team_id" class="w-full p-2 border rounded" required>
                            <option value="">Seleziona Equipe...</option>
                            <?php foreach ($teams as $t): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="can_edit_all"> Abilitato a modificare tutto il team
                        </label>
                        <button type="submit" class="w-full bg-gray-700 text-white p-2 rounded">Assegna</button>
                    </form>
                </div>
            </section>

            <!-- Team Security -->
            <section class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-lg font-bold mb-4 flex items-center gap-2"><i class="ph ph-shield-check"></i> Sicurezza Equipe</h2>
                <div class="bg-yellow-50 p-4 rounded text-sm text-yellow-800 mb-6">
                    <strong>Attenzione:</strong> Per impostare o cambiare la chiave di un'equipe, devi conoscere la password attuale del Capo Equipe, poiché la chiave viene criptata usando quella password.
                </div>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="set_team_key">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Equipe</label>
                        <select name="team_id" class="w-full p-2 border rounded" required>
                            <option value="">Seleziona...</option>
                            <?php foreach ($teams as $t): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?> (Capo: <?php echo htmlspecialchars($t['leader_name']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Nuova Chiave Segreta Equipe</label>
                        <input type="password" name="team_key" class="w-full p-2 border rounded" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Password del Capo Equipe (per conferma)</label>
                        <input type="password" name="leader_password" class="w-full p-2 border rounded" required>
                    </div>
                    <button type="submit" class="w-full bg-red-600 text-white p-2 rounded shadow">Salva Chiave Equipe</button>
                </form>
            </section>
        </div>
    </main>
</body>
</html>
