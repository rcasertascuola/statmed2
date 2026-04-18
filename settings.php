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
                $leader_pass = $_POST['leader_password'];
                $new_key = $_POST['team_key'];

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
            <h1 class="text-xl font-bold">Amministrazione</h1>
        </div>
        <div class="flex items-center gap-4">
            <span class="text-sm">Admin: <strong><?php echo $_SESSION['name']; ?></strong></span>
            <a href="login.php?action=logout" class="bg-red-500 hover:bg-red-600 px-4 py-2 rounded text-sm">Esci</a>
        </div>
    </nav>

    <main class="container mx-auto p-4 md:p-8">
        <?php if ($message): ?>
            <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-4"><?php echo $message; ?></div>
        <?php endif; ?>

        <div class="flex border-b mb-6 overflow-x-auto">
            <button onclick="openTab('teams-tab')" id="btn-teams-tab" class="tab-btn active px-4 py-2 text-sm font-medium text-gray-500 hover:text-blue-600 whitespace-nowrap">Equipe e Ospedali</button>
            <button onclick="openTab('users-tab')" id="btn-users-tab" class="tab-btn px-4 py-2 text-sm font-medium text-gray-500 hover:text-blue-600 whitespace-nowrap">Gestione Utenti</button>
            <button onclick="openTab('ranges-tab')" id="btn-ranges-tab" class="tab-btn px-4 py-2 text-sm font-medium text-gray-500 hover:text-blue-600 whitespace-nowrap">Range Clinici</button>
            <button onclick="openTab('tags-tab')" id="btn-tags-tab" class="tab-btn px-4 py-2 text-sm font-medium text-gray-500 hover:text-blue-600 whitespace-nowrap">Libreria Tag</button>
        </div>

        <!-- TEAMS TAB -->
        <div id="teams-tab" class="tab-content active space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
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
                </section>
            </div>

            <section class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-lg font-bold mb-4 flex items-center gap-2"><i class="ph ph-shield-check"></i> Sicurezza Equipe</h2>
                <div class="bg-yellow-50 p-4 rounded text-sm text-yellow-800 mb-6">
                    <strong>Attenzione:</strong> Per impostare o cambiare la chiave di un'equipe, devi conoscere la password attuale del Capo Equipe.
                </div>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <input type="hidden" name="action" value="set_team_key">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Equipe</label>
                        <select name="team_id" class="w-full p-2 border rounded" required>
                            <option value="">Seleziona...</option>
                            <?php foreach ($teams as $t): ?>
                                <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?> (Capo: <?php echo htmlspecialchars($t['leader_name']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Chiave Segreta</label>
                        <input type="password" name="team_key" class="w-full p-2 border rounded" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Pass Capo Equipe</label>
                        <input type="password" name="leader_password" class="w-full p-2 border rounded" required>
                    </div>
                    <button type="submit" class="bg-red-600 text-white p-2 rounded shadow font-bold">Salva Chiave</button>
                </form>
            </section>
        </div>

        <!-- USERS TAB -->
        <div id="users-tab" class="tab-content space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
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
                                        <span class="px-2 py-0.5 rounded text-xs font-bold <?php echo $u['role'] === 'admin' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'; ?>">
                                            <?php echo strtoupper($u['role']); ?>
                                        </span>
                                    </td>
                                    <td class="p-3 text-right">
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

                <section class="bg-white p-6 rounded-lg shadow">
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

        <!-- RANGES TAB -->
        <div id="ranges-tab" class="tab-content space-y-6">
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-bold flex items-center gap-2"><i class="ph ph-thermometer"></i> Range Clinici</h2>
                    <button onclick="recalculateRanges()" class="bg-blue-600 text-white px-4 py-2 rounded text-sm font-bold hover:bg-blue-700 shadow-md transition flex items-center gap-2">
                        <i class="ph ph-arrows-clockwise"></i> Ricalcola dai dati storici
                    </button>
                </div>
                <div id="range-categories" class="space-y-8">
                    <!-- Loaded via JS -->
                </div>
            </div>
        </div>

        <!-- TAGS TAB -->
        <div id="tags-tab" class="tab-content space-y-6">
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-xl font-bold mb-6 flex items-center gap-2"><i class="ph ph-tags"></i> Libreria Tag</h2>
                <div class="bg-gray-50 p-4 rounded-lg mb-8 border border-gray-200">
                    <h3 class="text-sm font-bold text-gray-600 mb-3 uppercase">Aggiungi Nuovo Tag</h3>
                    <div class="flex flex-col md:flex-row gap-4">
                        <select id="tag-category-select" class="flex-1 p-2 border rounded text-sm">
                            <option value="tipo_intervento">Tipo Intervento</option>
                            <option value="comorbilita">Comorbidità</option>
                            <option value="maschera_venturi">Maschera Venturi</option>
                            <option value="hfno">HFNO</option>
                            <option value="niv">NIV</option>
                            <option value="tipo_post_estubazione">Tipo Post-Estubazione</option>
                        </select>
                        <input type="text" id="new-tag-name" placeholder="Nome etichetta..." class="flex-1 p-2 border rounded text-sm">
                        <button onclick="addTag()" class="bg-green-600 text-white px-6 py-2 rounded font-bold hover:bg-green-700 shadow transition">Aggiungi</button>
                    </div>
                </div>
                <div id="tags-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Loaded via JS -->
                </div>
            </div>
        </div>
    </main>

    <script>
        function openTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            document.getElementById('btn-' + tabId).classList.add('active');

            if (tabId === 'ranges-tab') loadRanges();
            if (tabId === 'tags-tab') loadTags();
        }

        async function loadRanges() {
            const res = await fetch('api.php?action=ranges');
            const ranges = await res.json();
            const container = document.getElementById('range-categories');
            container.innerHTML = '';
            const categories = { 'pazienti': 'Anagrafica Paziente', 'rilevazioni': 'Rilevazioni Cliniche', 'interventi': 'Parametri Intervento', 'esiti': 'Parametri Esito' };
            for (const [cat, label] of Object.entries(categories)) {
                const catRanges = ranges.filter(r => r.category === cat);
                if (catRanges.length === 0) continue;
                const section = document.createElement('div');
                section.innerHTML = `
                    <h3 class="text-md font-bold text-gray-600 mb-3 border-l-4 border-blue-500 pl-2 uppercase">\${label}</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse mb-6 text-sm">
                            <thead>
                                <tr class="bg-gray-50 text-[10px] uppercase text-gray-500">
                                    <th class="p-3">Parametro</th>
                                    <th class="p-3">Unità</th>
                                    <th class="p-3 text-green-600">Min Norm</th>
                                    <th class="p-3 text-green-600">Max Norm</th>
                                    <th class="p-3 text-red-600">Min Crit</th>
                                    <th class="p-3 text-red-600">Max Crit</th>
                                    <th class="p-3 text-center">Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                \${catRanges.map(r => `
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="p-3 font-bold text-gray-700">\${r.parameter}</td>
                                        <td class="p-2"><input type="text" value="\${r.unit || ''}" id="unit-\${r.parameter}" class="w-full p-1 border rounded text-xs"></td>
                                        <td class="p-2"><input type="number" step="0.01" value="\${r.min_normal}" id="minn-\${r.parameter}" class="w-full p-1 border rounded text-xs bg-green-50"></td>
                                        <td class="p-2"><input type="number" step="0.01" value="\${r.max_normal}" id="maxn-\${r.parameter}" class="w-full p-1 border rounded text-xs bg-green-50"></td>
                                        <td class="p-2"><input type="number" step="0.01" value="\${r.min_critical}" id="minc-\${r.parameter}" class="w-full p-1 border rounded text-xs bg-red-50"></td>
                                        <td class="p-2"><input type="number" step="0.01" value="\${r.max_critical}" id="maxc-\${r.parameter}" class="w-full p-1 border rounded text-xs bg-red-50"></td>
                                        <td class="p-2 text-center">
                                            <button onclick="saveRange('\${r.parameter}')" class="text-blue-600 hover:text-blue-800 p-1" title="Salva">
                                                <i class="ph ph-floppy-disk text-xl"></i>
                                            </button>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
                container.appendChild(section);
            }
        }

        async function saveRange(param) {
            const data = {
                parameter: param,
                unit: document.getElementById(`unit-\${param}`).value,
                min_normal: document.getElementById(`minn-\${param}`).value,
                max_normal: document.getElementById(`maxn-\${param}`).value,
                min_critical: document.getElementById(`minc-\${param}`).value,
                max_critical: document.getElementById(`maxc-\${param}`).value
            };
            const res = await fetch('api.php?action=ranges', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) });
            if (res.ok) alert('Salvato!'); else alert('Errore!');
        }

        async function recalculateRanges() {
            if (confirm('Sei sicuro? I valori manuali verranno ricalcolati.')) {
                await fetch('init_ranges.php?run=1');
                loadRanges();
            }
        }

        async function loadTags() {
            const res = await fetch('api.php?action=tags');
            const tags = await res.json();
            const container = document.getElementById('tags-container');
            container.innerHTML = '';
            const grouped = tags.reduce((acc, tag) => { acc[tag.category] = acc[tag.category] || []; acc[tag.category].push(tag); return acc; }, {});
            for (const [cat, catTags] of Object.entries(grouped)) {
                const card = document.createElement('div');
                card.className = 'bg-gray-50 p-4 rounded-lg border';
                card.innerHTML = `
                    <h3 class="font-bold text-gray-700 mb-3 border-b pb-1 uppercase text-[10px] text-gray-500">\${cat.replace(/_/g, ' ')}</h3>
                    <div class="flex flex-wrap gap-2">
                        \${catTags.map(t => `
                            <span class="bg-white border px-2 py-1 rounded text-xs flex items-center gap-1 shadow-sm">
                                <span>\${t.name}</span>
                                <button onclick="renameTagPrompt(\${t.id}, '\${t.name.replace(/'/g, "\\'")}', '\${cat}')" class="text-blue-500 hover:text-blue-700"><i class="ph ph-pencil-simple"></i></button>
                                <button onclick="deleteTag(\${t.id})" class="text-red-400 hover:text-red-600"><i class="ph ph-trash"></i></button>
                            </span>
                        `).join('')}
                    </div>
                `;
                container.appendChild(card);
            }
        }

        async function addTag() {
            const category = document.getElementById('tag-category-select').value;
            const name = document.getElementById('new-tag-name').value;
            if (!name) return;
            await fetch('api.php?action=tags', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ category, name }) });
            document.getElementById('new-tag-name').value = '';
            loadTags();
        }

        async function deleteTag(id) {
            if (confirm('Eliminare questo tag?')) { await fetch(`api.php?action=tags&id=\${id}`, { method: 'DELETE' }); loadTags(); }
        }

        async function renameTagPrompt(id, oldName, category) {
            const newName = prompt(`Rinomina o unisci il tag "\${oldName}":`, oldName);
            if (!newName || newName === oldName) return;
            const res = await fetch('api.php?action=rename_tag', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ old_name: oldName, new_name: newName, category: category }) });
            if (res.ok) loadTags(); else alert('Errore!');
        }
    </script>
</body>
</html>
