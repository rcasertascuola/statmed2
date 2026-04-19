<?php
require_once 'auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$db = getDB();
$user_id = $_SESSION['user_id'];
$current_team_id = $_SESSION['active_team_id'] ?? null;

if (!$current_team_id && !isAdmin()) {
    header('Location: index.php');
    exit;
}

// Get operative units for the current team
$operative_units = [];
if ($current_team_id) {
    $stmt = $db->prepare("
        SELECT ou.*
        FROM operative_units ou
        JOIN team_operative_units tou ON ou.id = tou.operative_unit_id
        WHERE tou.team_id = ?
    ");
    $stmt->execute([$current_team_id]);
    $operative_units = $stmt->fetchAll();
} elseif (isAdmin()) {
    $stmt = $db->query("SELECT * FROM operative_units");
    $operative_units = $stmt->fetchAll();
}

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pazienti - StatMed2</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>
    <!-- Tesseract.js for OCR -->
    <script src='https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js'></script>
    <!-- Phosphor Icons -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        .modal { display: none; position: fixed; z-index: 50; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fefefe; margin: 2% auto; padding: 20px; border: 1px solid #888; width: 95%; max-width: 900px; border-radius: 12px; }
        @media (max-width: 640px) {
            .modal-content { margin: 0; width: 100%; min-height: 100%; border-radius: 0; padding: 15px; }
            input, select, textarea { font-size: 16px !important; } /* Prevents iOS zoom */
        }
        .range-ok { background-color: #dcfce7; border-color: #22c55e; }
        .range-warning { background-color: #fef9c3; border-color: #eab308; }
        .range-critical { background-color: #fee2e2; border-color: #ef4444; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <nav class="bg-blue-600 text-white p-4 shadow-lg">
        <div class="container mx-auto flex flex-col md:flex-row justify-between items-center gap-3">
            <div class="flex items-center justify-between w-full md:w-auto">
                <div class="flex items-center gap-4">
                    <a href="index.php" class="hover:text-blue-200 transition">
                        <i class="ph ph-house text-2xl"></i>
                    </a>
                    <img src="assets/logo_small.png" alt="Logo" class="h-8 w-auto">
                </div>
                <div class="flex items-center gap-2 md:hidden">
                    <a href="stats.php" class="bg-purple-500 hover:bg-purple-600 p-2 rounded-full text-white transition" title="Statistiche">
                        <i class="ph ph-chart-line-up text-xl"></i>
                    </a>
                    <?php if (isAdmin() || isLeader()): ?>
                    <a href="settings.php" class="bg-gray-700 hover:bg-gray-800 p-2 rounded-full text-white transition" title="Impostazioni">
                        <i class="ph ph-gear text-xl"></i>
                    </a>
                    <?php endif; ?>
                    <button onclick="exportCSV()" class="bg-green-500 hover:bg-green-600 p-2 rounded-full text-white transition" title="Export CSV">
                        <i class="ph ph-download-simple text-xl"></i>
                    </button>
                    <a href="profile.php" class="bg-blue-700 hover:bg-blue-800 p-2 rounded-full text-white transition" title="Profilo">
                        <i class="ph ph-user text-xl"></i>
                    </a>
                    <a href="login.php?action=logout" class="bg-red-500 hover:bg-red-600 p-2 rounded-full text-white transition" title="Esci">
                        <i class="ph ph-sign-out text-xl"></i>
                    </a>
                </div>
            </div>
            <div class="flex items-center justify-center md:justify-between w-full md:w-auto gap-4">
                <span class="text-sm">
                    Benvenut<?php echo $_SESSION['sex'] === 'F' ? 'a' : 'o'; ?>
                    <strong class="hidden md:inline"><?php echo htmlspecialchars($_SESSION['name']); ?></strong>
                    <strong class="md:hidden"><?php echo htmlspecialchars(explode(' ', trim($_SESSION['name']))[0]); ?></strong>
                </span>
                <div class="hidden md:flex items-center space-x-2">
                    <a href="stats.php" class="bg-purple-500 hover:bg-purple-600 p-2 rounded-full text-white transition" title="Statistiche">
                        <i class="ph ph-chart-line-up text-xl"></i>
                    </a>
                    <?php if (isAdmin() || isLeader()): ?>
                    <a href="settings.php" class="bg-gray-700 hover:bg-gray-800 p-2 rounded-full text-white transition" title="Impostazioni">
                        <i class="ph ph-gear text-xl"></i>
                    </a>
                    <?php endif; ?>
                    <button onclick="exportCSV()" class="bg-green-500 hover:bg-green-600 p-2 rounded-full text-white transition" title="Export CSV">
                        <i class="ph ph-download-simple text-xl"></i>
                    </button>
                    <a href="profile.php" class="bg-blue-700 hover:bg-blue-800 p-2 rounded-full text-white transition" title="Profilo">
                        <i class="ph ph-user text-xl"></i>
                    </a>
                    <a href="login.php?action=logout" class="bg-red-500 hover:bg-red-600 p-2 rounded-full text-white transition" title="Esci">
                        <i class="ph ph-sign-out text-xl"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="container mx-auto p-4 md:p-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-2xl font-semibold text-gray-800">Pazienti</h2>
            <button onclick="openPazienteModal()" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded transition shadow">+ Nuovo Paziente</button>
        </div>

        <div class="bg-white rounded-lg shadow overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm md:text-base">
                <thead>
                    <tr class="bg-gray-100 border-b">
                        <th class="p-2 md:p-4">Paziente</th>
                        <th class="p-2 md:p-4">S</th>
                        <th class="p-2 md:p-4">Età</th>
                        <th class="p-2 md:p-4">BMI</th>
                        <th class="p-2 md:p-4 text-center">Azioni</th>
                    </tr>
                </thead>
                <tbody id="pazienti-table-body">
                    <!-- Data will be loaded here -->
                </tbody>
            </table>
        </div>
    </main>

    <!-- Paziente Modal -->
    <div id="pazienteModal" class="modal">
        <div class="modal-content">
            <h3 id="modalTitle" class="text-xl font-bold mb-4">Aggiungi/Modifica Paziente</h3>
            <form id="pazienteForm" onsubmit="savePaziente(event)" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" id="p_id">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Nome e Cognome</label>
                    <input type="text" id="p_nome" class="w-full p-2 border rounded" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Sesso (M/F)</label>
                    <select id="p_sesso" class="w-full p-2 border rounded" required>
                        <option value="M">M</option>
                        <option value="F">F</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Età</label>
                    <input type="number" inputmode="numeric" id="p_eta" oninput="validateParam('eta', this)" class="w-full p-2 border rounded" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Altezza (m)</label>
                    <input type="number" inputmode="decimal" step="0.01" min="0" max="3" id="p_altezza" oninput="calculateBMI(); validateParam('altezza', this)" class="w-full p-2 border rounded" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Peso (kg)</label>
                    <input type="number" inputmode="decimal" step="0.1" min="0" id="p_peso" oninput="calculateBMI(); validateParam('peso', this)" class="w-full p-2 border rounded" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">BMI (Auto)</label>
                    <input type="number" step="0.01" id="p_bmi" oninput="validateParam('bmi', this)" class="w-full p-2 border bg-gray-100" readonly>
                </div>
                <div class="md:col-span-2 flex justify-end space-x-2 mt-4">
                    <button type="button" onclick="closeModal('pazienteModal')" class="bg-gray-300 px-4 py-2 rounded">Annulla</button>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Salva</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Interventi/Rilevazioni Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-content max-w-4xl">
            <h3 class="text-xl font-bold mb-1">Dettagli Clinici: <span id="detailPazienteNome"></span></h3>
            <div id="detailPazienteInfo" class="text-xs text-gray-500 mb-4"></div>

            <div class="mb-6">
                <h4 class="font-bold border-b mb-2 flex justify-between">
                    Interventi
                    <button onclick="openInterventoModal()" class="text-blue-500 text-sm">+ Aggiungi</button>
                </h4>
                <div id="interventi-list" class="space-y-4"></div>
            </div>

            <div class="flex justify-end">
                <button type="button" onclick="closeModal('detailsModal')" class="bg-gray-300 px-4 py-2 rounded">Chiudi</button>
            </div>
        </div>
    </div>

    <div id="interventoModal" class="modal">
        <div class="modal-content">
            <h3 class="text-xl font-bold mb-4">Dati Intervento</h3>
            <form id="interventoForm" onsubmit="saveIntervento(event)" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" id="i_id">
                <input type="hidden" id="i_paziente_id">

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Unità Operativa</label>
                    <select id="i_uo" class="w-full p-2 border rounded" required>
                        <?php foreach ($operative_units as $ou): ?>
                            <option value="<?php echo $ou['id']; ?>"><?php echo htmlspecialchars($ou['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Comorbidità</label>
                    <div id="i_comorbilita_tags" class="flex flex-wrap gap-1 mb-1"></div>
                    <input type="text" id="i_comorbilita_input" placeholder="Aggiungi comorbidità..." class="w-full p-2 border rounded text-sm mb-1" onkeydown="handleTagInput(event, 'comorbilita', 'i_comorbilita')">
                    <textarea id="i_comorbilita" class="hidden"></textarea>
                    <div id="i_comorbilita_suggestions" class="flex flex-wrap gap-1"></div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">ASA Score</label>
                    <input type="number" inputmode="numeric" min="1" max="5" id="i_asa" oninput="validateParam('asa_score', this)" class="w-full p-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tipo Intervento</label>
                    <input type="text" id="i_tipo" list="tipo_intervento_list" onchange="addTagToField('tipo_intervento', this.value, 'i_tipo')" class="w-full p-2 border rounded">
                    <datalist id="tipo_intervento_list"></datalist>
                </div>
                <div class="flex items-center">
                    <input type="checkbox" id="i_urgenza" class="mr-2">
                    <label class="text-sm font-medium text-gray-700">Urgenza</label>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Euroscore II (%)</label>
                    <input type="number" inputmode="decimal" step="0.01" min="0" id="i_euroscore" oninput="validateParam('euroscore_ii', this)" class="w-full p-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Durata CEC (h)</label>
                    <input type="number" inputmode="decimal" step="0.1" min="0" id="i_cec" oninput="validateParam('durata_cec_ore', this)" class="w-full p-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Timing IOT (h)</label>
                    <input type="number" inputmode="decimal" step="0.1" min="0" id="i_iot" oninput="validateParam('timing_iot_h', this)" class="w-full p-2 border rounded">
                </div>
                <div class="md:col-span-2 flex justify-end space-x-2 mt-4">
                    <button type="button" onclick="closeModal('interventoModal')" class="bg-gray-300 px-4 py-2 rounded">Annulla</button>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Salva</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Rilevazione Modal -->
    <div id="rilevazioneModal" class="modal">
        <div class="modal-content">
            <h3 class="text-xl font-bold mb-4">Rilevazione Clinica</h3>
            <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg flex items-center justify-between">
                <div>
                    <h4 class="font-bold text-blue-800">Acquisizione Rapida OCR</h4>
                    <p class="text-xs text-blue-600">Scansiona uno schermo o un documento per compilare i campi</p>
                </div>
                <div>
                    <label for="ocr-input" class="cursor-pointer bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition flex items-center space-x-2">
                        <i class="ph ph-camera text-xl"></i>
                        <span>Scansiona</span>
                    </label>
                    <input type="file" id="ocr-input" accept="image/*" capture="environment" class="hidden" onchange="handleOCR(event)">
                </div>
            </div>

            <div id="ocr-status" class="hidden mb-4 p-3 bg-yellow-50 border border-yellow-200 rounded text-sm text-yellow-700 animate-pulse">
                <span id="ocr-message">Elaborazione immagine...</span>
            </div>

            <form id="rilevazioneForm" onsubmit="saveRilevazione(event)" class="grid grid-cols-2 md:grid-cols-3 gap-3 md:gap-4">
                <input type="hidden" id="r_id">
                <input type="hidden" id="r_intervento_id">
                <div class="col-span-2 md:col-span-1">
                    <label class="block text-xs font-bold text-gray-500 uppercase">Data e Ora</label>
                    <input type="datetime-local" id="r_data_ora" class="w-full p-3 border rounded-lg shadow-sm" required>
                </div>
                <div class="col-span-2 md:col-span-1">
                    <label class="block text-xs font-bold text-gray-500 uppercase">Fase</label>
                    <select id="r_fase" class="w-full p-3 border rounded-lg shadow-sm">
                        <option value="PRE_SBT">PRE_SBT</option>
                        <option value="SBT">SBT</option>
                        <option value="ESTUBAZIONE">ESTUBAZIONE</option>
                        <option value="T0">T0</option>
                        <option value="T30">T30</option>
                        <option value="POST_2H">POST_2H</option>
                        <option value="POST_6H">POST_6H</option>
                        <option value="POST_12H">POST_12H</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase">FR (bpm)</label>
                    <input type="number" inputmode="decimal" step="0.1" min="0" id="r_fr" oninput="validateParam('fr', this); calculateIndices()" class="w-full p-3 border rounded-lg shadow-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase">TV (L)</label>
                    <input type="number" inputmode="decimal" step="0.001" min="0" id="r_tv" oninput="validateParam('tv', this); calculateIndices()" class="w-full p-3 border rounded-lg shadow-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase">Tobin Index</label>
                    <input type="number" id="r_tobin" oninput="validateParam('tobin_index', this)" class="w-full p-3 border rounded-lg bg-gray-100 shadow-sm" readonly>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase">SpO2 (%)</label>
                    <input type="number" inputmode="decimal" step="0.1" min="0" max="100" id="r_spo2" oninput="validateParam('spo2', this); calculateIndices()" class="w-full p-3 border rounded-lg shadow-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase">FiO2 (0.21-1)</label>
                    <input type="number" inputmode="decimal" step="0.01" min="0.21" max="1" id="r_fio2" oninput="validateParam('fio2', this); calculateIndices()" class="w-full p-3 border rounded-lg shadow-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase">ROX Index</label>
                    <input type="number" id="r_rox" oninput="validateParam('rox_index', this)" class="w-full p-3 border rounded-lg bg-gray-100 shadow-sm" readonly>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase">PEEP</label>
                    <input type="number" inputmode="decimal" step="0.1" min="0" id="r_peep" oninput="validateParam('peep', this)" class="w-full p-3 border rounded-lg shadow-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase">Pres. Supp.</label>
                    <input type="number" inputmode="decimal" step="0.1" min="0" id="r_ps" oninput="validateParam('pressure_support', this)" class="w-full p-3 border rounded-lg shadow-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase">NRS Dolore</label>
                    <input type="number" inputmode="numeric" min="0" max="10" id="r_dolore" oninput="validateParam('nrs_dolore', this)" class="w-full p-3 border rounded-lg shadow-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase">NAS Score</label>
                    <input type="number" inputmode="decimal" step="0.1" min="0" id="r_nas" oninput="validateParam('nas_score', this)" class="w-full p-3 border rounded-lg shadow-sm">
                </div>
                <div class="col-span-2 md:col-span-1">
                    <label class="block text-xs font-bold text-gray-500 uppercase">M. Venturi</label>
                    <input type="text" id="r_venturi" list="maschera_venturi_list" class="w-full p-3 border rounded-lg shadow-sm">
                    <datalist id="maschera_venturi_list"></datalist>
                </div>
                <div class="col-span-2 md:col-span-1">
                    <label class="block text-xs font-bold text-gray-500 uppercase">HFNO</label>
                    <input type="text" id="r_hfno" list="hfno_list" class="w-full p-3 border rounded-lg shadow-sm">
                    <datalist id="hfno_list"></datalist>
                </div>
                <div class="col-span-2 md:col-span-1">
                    <label class="block text-xs font-bold text-gray-500 uppercase">NIV</label>
                    <input type="text" id="r_niv" list="niv_list" class="w-full p-3 border rounded-lg shadow-sm">
                    <datalist id="niv_list"></datalist>
                </div>
                <div class="col-span-2 md:col-span-3 flex justify-end space-x-2 mt-4">
                    <button type="button" onclick="closeModal('rilevazioneModal')" class="bg-gray-200 px-6 py-3 rounded-lg">Annulla</button>
                    <button type="submit" class="bg-blue-600 text-white px-6 py-3 rounded-lg shadow-lg">Salva</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Esito Modal -->
    <div id="esitoModal" class="modal">
        <div class="modal-content">
            <h3 class="text-xl font-bold mb-4">Esito Weaning</h3>
            <form id="esitoForm" onsubmit="saveEsito(event)" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" id="e_id">
                <input type="hidden" id="e_intervento_id">
                <div class="flex items-center">
                    <input type="checkbox" id="e_successo" class="mr-2">
                    <label class="text-sm font-medium text-gray-700">Successo Estubazione</label>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tipo Post-Estubazione</label>
                    <input type="text" id="e_tipo" list="tipo_post_estubazione_list" class="w-full p-2 border rounded">
                    <datalist id="tipo_post_estubazione_list"></datalist>
                </div>
                <div class="flex items-center">
                    <input type="checkbox" id="e_fallimento" class="mr-2">
                    <label class="text-sm font-medium text-gray-700">Fallimento IOT</label>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Ore a failure (h)</label>
                    <input type="number" inputmode="decimal" step="0.1" min="0" id="e_ore" oninput="validateParam('ore_da_estubazione_a_failure', this)" class="w-full p-2 border rounded">
                </div>
                <div class="md:col-span-2 flex justify-end space-x-2 mt-4">
                    <button type="button" onclick="closeModal('esitoModal')" class="bg-gray-300 px-4 py-2 rounded">Annulla</button>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Salva</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const isAdmin = <?php echo isAdmin() ? 'true' : 'false'; ?>;
        const encryptionKey = sessionStorage.getItem('encryption_key');
        if (!encryptionKey && !isAdmin) {
            window.location.href = 'index.php';
        }
        let clinicalRanges = [];
        let tagLibrary = {};

        async function loadRanges() {
            const res = await fetch('api.php?action=ranges');
            clinicalRanges = await res.json();
        }

        async function loadTagLibrary() {
            const res = await fetch('api.php?action=tags');
            const tags = await res.json();
            tagLibrary = tags.reduce(function(acc, t) {
                acc[t.category] = acc[t.category] || [];
                acc[t.category].push(t.name);
                return acc;
            }, {});

            for (const cat in tagLibrary) {
                const dl = document.getElementById(cat + '_list');
                if (dl) {
                    dl.innerHTML = tagLibrary[cat].map(function(n) { return '<option value="' + n + '">'; }).join('');
                }
            }
        }
        loadRanges();
        loadTagLibrary();

        function handleTagInput(e, category, targetId) {
            if (e.key === 'Enter' || e.key === ',') {
                e.preventDefault();
                const val = e.target.value.trim().replace(',', '');
                if (val) addTagToField(category, val, targetId);
                e.target.value = '';
            }
        }

        async function addTagToField(category, name, targetId) {
            const target = document.getElementById(targetId);
            const current = target.value ? target.value.split(',').map(function(s) { return s.trim(); }) : [];
            if (!current.includes(name)) {
                current.push(name);
                target.value = current.join(', ');
                renderTags(category, targetId);

                if (!tagLibrary[category] || !tagLibrary[category].includes(name)) {
                    await fetch('api.php?action=tags', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ category: category, name: name })
                    });
                    loadTagLibrary();
                }
            }
        }

        function removeTagFromField(category, name, targetId) {
            const target = document.getElementById(targetId);
            const current = target.value.split(',').map(function(s) { return s.trim(); }).filter(function(s) { return s !== name; });
            target.value = current.join(', ');
            renderTags(category, targetId);
        }

        function renderTags(category, targetId) {
            const target = document.getElementById(targetId);
            const container = document.getElementById(targetId + '_tags');
            const suggContainer = document.getElementById(targetId + '_suggestions');
            if (!container) return;

            const current = target.value ? target.value.split(',').map(function(s) { return s.trim(); }) : [];
            container.innerHTML = current.map(function(t) {
                return '<span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs font-bold flex items-center gap-1">' +
                    t +
                    '<button type="button" onclick="removeTagFromField(\'' + category + '\', \'' + t + '\', \'' + targetId + '\')" class="text-blue-500 hover:text-blue-700">' +
                        '<i class="ph ph-x-circle"></i>' +
                    '</button>' +
                '</span>';
            }).join('');

            if (suggContainer && tagLibrary[category]) {
                const suggestions = tagLibrary[category].filter(function(t) { return !current.includes(t); });
                suggContainer.innerHTML = suggestions.map(function(t) {
                    return '<button type="button" onclick="addTagToField(\'' + category + '\', \'' + t + '\', \'' + targetId + '\')" class="bg-gray-100 text-gray-600 px-2 py-1 rounded text-[10px] hover:bg-gray-200">' +
                        '+ ' + t +
                    '</button>';
                }).join('');
            }
        }

        function validateParam(param, input) {
            const val = parseFloat(input.value);
            const range = clinicalRanges.find(function(r) { return r.parameter === param; });
            input.classList.remove('range-ok', 'range-warning', 'range-critical');
            if (isNaN(val) || !range) return;
            if (val >= range.min_normal && val <= range.max_normal) {
                input.classList.add('range-ok');
            } else if (val >= range.min_critical && val <= range.max_critical) {
                input.classList.add('range-warning');
            } else {
                input.classList.add('range-critical');
            }
        }

        function encrypt(text) {
            return CryptoJS.AES.encrypt(text, encryptionKey).toString();
        }

        function decrypt(ciphertext) {
            try {
                const bytes = CryptoJS.AES.decrypt(ciphertext, encryptionKey);
                return bytes.toString(CryptoJS.enc.Utf8);
            } catch (e) {
                return "ERRORE DECRIPTAZIONE";
            }
        }

        function calculateBMI() {
            const h = parseFloat(document.getElementById('p_altezza').value);
            const w = parseFloat(document.getElementById('p_peso').value);
            if (h > 0 && w > 0) {
                const bmi = (w / (h * h)).toFixed(2);
                const input = document.getElementById('p_bmi');
                input.value = bmi;
                validateParam('bmi', input);
            }
        }

        function calculateIndices() {
            const fr = parseFloat(document.getElementById('r_fr').value);
            const tv = parseFloat(document.getElementById('r_tv').value);
            const spo2 = parseFloat(document.getElementById('r_spo2').value);
            const fio2 = parseFloat(document.getElementById('r_fio2').value);
            if (fr > 0 && tv > 0) {
                const tobin = (fr / tv).toFixed(1);
                const input = document.getElementById('r_tobin');
                input.value = tobin;
                validateParam('tobin_index', input);
            }
            if (fr > 0 && spo2 > 0 && fio2 > 0) {
                const rox = ((spo2 / fio2) / fr).toFixed(2);
                const input = document.getElementById('r_rox');
                input.value = rox;
                validateParam('rox_index', input);
            }
        }

        function openModal(id) { document.getElementById(id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }

        function openPazienteModal(paziente) {
            document.getElementById('pazienteForm').reset();
            document.querySelectorAll('#pazienteForm input').forEach(function(el) { el.classList.remove('range-ok', 'range-warning', 'range-critical'); });
            document.getElementById('p_id').value = '';
            if (paziente) {
                document.getElementById('p_id').value = paziente.id;
                document.getElementById('p_nome').value = decrypt(paziente.nome_cognome);
                document.getElementById('p_sesso').value = paziente.sesso;
                document.getElementById('p_eta').value = paziente.eta;
                document.getElementById('p_altezza').value = paziente.altezza;
                document.getElementById('p_peso').value = paziente.peso;
                document.getElementById('p_bmi').value = paziente.bmi;
                ['eta', 'altezza', 'peso', 'bmi'].forEach(function(p) {
                    const input = document.getElementById('p_' + p);
                    if (input) validateParam(p, input);
                });
            }
            openModal('pazienteModal');
        }

        async function loadPazienti() {
            const res = await fetch('api.php?action=pazienti');
            const pazienti = await res.json();
            const tbody = document.getElementById('pazienti-table-body');
            tbody.innerHTML = '';
            pazienti.forEach(function(p) {
                const row = document.createElement('tr');
                row.className = 'border-b hover:bg-gray-50';
                const pData = JSON.stringify(p).replace(/'/g, "&apos;");
                row.innerHTML = '<td class="p-2 md:p-4 font-medium">' + decrypt(p.nome_cognome) + '</td>' +
                    '<td class="p-2 md:p-4">' + p.sesso + '</td>' +
                    '<td class="p-2 md:p-4">' + p.eta + '</td>' +
                    '<td class="p-2 md:p-4">' + p.bmi + '</td>' +
                    '<td class="p-2 md:p-4 text-center">' +
                        '<div class="flex items-center justify-center space-x-1 md:space-x-2">' +
                            '<button onclick=\'viewDetails(' + pData + ')\' class="text-blue-500 hover:text-blue-700 p-1" title="Dettagli">' +
                                '<i class="ph ph-eye text-lg md:text-xl"></i>' +
                            '</button>' +
                            (p.can_edit ? '<button onclick=\'openPazienteModal(' + pData + ')\' class="text-yellow-600 hover:text-yellow-800 p-1" title="Modifica"><i class="ph ph-pencil-line text-lg md:text-xl"></i></button>' : '') +
                            (p.can_delete ? '<button onclick="deletePaziente(' + p.id + ')" class="text-red-500 hover:text-red-700 p-1" title="Elimina"><i class="ph ph-trash text-lg md:text-xl"></i></button>' : '') +
                        '</div>' +
                    '</td>';
                tbody.appendChild(row);
            });
        }

        async function savePaziente(e) {
            e.preventDefault();
            if (document.querySelectorAll('#pazienteForm .range-critical').length > 0) {
                if (!confirm("Attenzione: alcuni dati del paziente sono fuori dai range critici. Procedere?")) return;
            }
            const data = {
                id: document.getElementById('p_id').value,
                nome_cognome: encrypt(document.getElementById('p_nome').value),
                sesso: document.getElementById('p_sesso').value,
                eta: document.getElementById('p_eta').value,
                altezza: document.getElementById('p_altezza').value,
                peso: document.getElementById('p_peso').value,
                bmi: document.getElementById('p_bmi').value
            };
            await fetch('api.php?action=pazienti', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            closeModal('pazienteModal');
            loadPazienti();
        }

        async function deletePaziente(id) {
            if (confirm('Sei sicuro di voler eliminare questo paziente?')) {
                await fetch('api.php?action=pazienti&id=' + id, { method: 'DELETE' });
                loadPazienti();
            }
        }

        let currentPazienteId = null;
        async function viewDetails(p) {
            currentPazienteId = p.id;
            document.getElementById('detailPazienteNome').innerText = decrypt(p.nome_cognome);
            document.getElementById('detailPazienteInfo').innerText = "Equipe: " + (p.team_name || 'N/D') + " | Inserito da: " + (p.creator_name || 'N/D');
            await loadInterventi();
            openModal('detailsModal');
        }

        async function loadInterventi() {
            const res = await fetch('api.php?action=interventi&paziente_id=' + currentPazienteId);
            const interventi = await res.json();
            const list = document.getElementById('interventi-list');
            list.innerHTML = '';
            for (let i = 0; i < interventi.length; i++) {
                const intv = interventi[i];
                const div = document.createElement('div');
                div.className = 'p-4 bg-gray-50 rounded border';
                const iData = JSON.stringify(intv).replace(/'/g, "&apos;");
                div.innerHTML = '<div class="flex justify-between items-start mb-2">' +
                    '<div>' +
                        '<strong>' + intv.tipo_intervento + '</strong> - ASA: ' + intv.asa_score + ' - ' + (intv.urgenza == 1 ? 'URGENTE' : 'Elezione') +
                        '<br><span class="text-[10px] text-blue-600 uppercase font-bold">' + (intv.team_name || 'Equipe N/D') + ' | ' + (intv.uo_name || 'U.O. N/D') + '</span>' +
                        '<br><span class="text-[10px] text-gray-500">Eseguito da: ' + (intv.creator_name || 'N/D') + '</span>' +
                    '</div>' +
                    '<div class="space-x-2 flex">' +
                        (intv.can_edit ? '<button onclick=\'openInterventoModal(' + iData + ')\' class="text-yellow-600 p-1" title="Modifica"><i class="ph ph-pencil-line text-lg"></i></button>' : '') +
                        (intv.can_delete ? '<button onclick="deleteIntervento(' + intv.id + ')" class="text-red-500 p-1" title="Elimina"><i class="ph ph-trash text-lg"></i></button>' : '') +
                    '</div>' +
                '</div>' +
                '<div class="text-sm text-gray-600 mb-2">Euroscore: ' + intv.euroscore_ii + ' | CEC: ' + intv.durata_cec_ore + 'h | IOT: ' + intv.timing_iot_h + 'h</div>' +
                (intv.comorbilita ? '<div class="text-xs text-gray-500 mb-2"><strong>Comorbidità:</strong> ' + intv.comorbilita + '</div>' : '') +
                '<div class="ml-4">' +
                    '<h5 class="font-semibold text-xs border-b mb-1 flex justify-between">' +
                        'Rilevazioni Cliniche' +
                        '<button onclick="openRilevazioneModal(' + intv.id + ')" class="text-blue-500">+ Aggiungi</button>' +
                    '</h5>' +
                    '<div id="rilevazioni-for-' + intv.id + '" class="space-y-1 mb-2"></div>' +
                    '<h5 class="font-semibold text-xs border-b mb-1 flex justify-between">' +
                        'Esito Weaning' +
                        '<span id="esito-action-' + intv.id + '"></span>' +
                    '</h5>' +
                    '<div id="esito-for-' + intv.id + '" class="text-xs"></div>' +
                '</div>';
                list.appendChild(div);
                loadRilevazioni(intv.id);
                loadEsito(intv.id);
            }
        }

        function openInterventoModal(intervento) {
            document.getElementById('interventoForm').reset();
            document.querySelectorAll('#interventoForm input').forEach(function(el) { el.classList.remove('range-ok', 'range-warning', 'range-critical'); });
            document.getElementById('i_id').value = '';
            document.getElementById('i_paziente_id').value = currentPazienteId;
            if (intervento) {
                document.getElementById('i_id').value = intervento.id;
                document.getElementById('i_uo').value = intervento.operative_unit_id;
                document.getElementById('i_comorbilita').value = intervento.comorbilita || '';
                document.getElementById('i_asa').value = intervento.asa_score;
                document.getElementById('i_tipo').value = intervento.tipo_intervento;
                document.getElementById('i_urgenza').checked = (intervento.urgenza == 1);
                document.getElementById('i_euroscore').value = intervento.euroscore_ii;
                document.getElementById('i_cec').value = intervento.durata_cec_ore;
                document.getElementById('i_iot').value = intervento.timing_iot_h;
                ['asa_score', 'euroscore_ii', 'durata_cec_ore', 'timing_iot_h'].forEach(function(p) {
                    const idMap = { 'asa_score': 'i_asa', 'euroscore_ii': 'i_euroscore', 'durata_cec_ore': 'i_cec', 'timing_iot_h': 'i_iot' };
                    const input = document.getElementById(idMap[p]);
                    if (input) validateParam(p, input);
                });
            }
            renderTags('comorbilita', 'i_comorbilita');
            openModal('interventoModal');
        }

        async function saveIntervento(e) {
            e.preventDefault();
            if (document.querySelectorAll('#interventoForm .range-critical').length > 0) {
                if (!confirm("Attenzione: alcuni parametri sono fuori dai range critici. Procedere?")) return;
            }
            const data = {
                id: document.getElementById('i_id').value,
                paziente_id: document.getElementById('i_paziente_id').value,
                operative_unit_id: document.getElementById('i_uo').value,
                comorbilita: document.getElementById('i_comorbilita').value,
                asa_score: document.getElementById('i_asa').value,
                tipo_intervento: document.getElementById('i_tipo').value,
                urgenza: document.getElementById('i_urgenza').checked ? 1 : 0,
                euroscore_ii: document.getElementById('i_euroscore').value,
                durata_cec_ore: document.getElementById('i_cec').value,
                timing_iot_h: document.getElementById('i_iot').value
            };
            await fetch('api.php?action=interventi', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            closeModal('interventoModal');
            loadInterventi();
        }

        async function deleteIntervento(id) {
            if (confirm('Eliminare questo intervento?')) {
                await fetch('api.php?action=interventi&id=' + id, { method: 'DELETE' });
                loadInterventi();
            }
        }

        function toggleRilevazione(id) {
            const content = document.getElementById('rilevazione-content-' + id);
            const isHidden = content.classList.contains('hidden');
            document.querySelectorAll('[id^="rilevazione-content-"]').forEach(function(el) { el.classList.add('hidden'); });
            if (isHidden) content.classList.remove('hidden');
        }

        async function loadRilevazioni(intervento_id) {
            const res = await fetch('api.php?action=rilevazioni&intervento_id=' + intervento_id);
            const rilevazioni = await res.json();
            const div = document.getElementById('rilevazioni-for-' + intervento_id);
            div.innerHTML = '';
            rilevazioni.forEach(function(r) {
                const item = document.createElement('div');
                item.className = 'mb-1 border rounded overflow-hidden';
                const tobinWarning = r.tobin_index > 105 ? 'text-red-600 font-bold' : (r.tobin_index > 80 ? 'text-orange-500' : '');
                const roxWarning = r.rox_index < 3.85 ? 'text-red-600 font-bold' : '';
                let extra = '';
                if(r.maschera_venturi) extra += ' Venturi: ' + r.maschera_venturi;
                if(r.hfno) extra += ' HFNO: ' + r.hfno;
                if(r.niv) extra += ' NIV: ' + r.niv;
                const rData = JSON.stringify(r).replace(/'/g, "&apos;");
                item.innerHTML = '<div class="flex justify-between items-center bg-white p-2 cursor-pointer hover:bg-gray-50" onclick="toggleRilevazione(' + r.id + ')">' +
                    '<span class="text-xs truncate pr-2 flex-1">' +
                        '<strong>' + r.fase + ':</strong> FR ' + r.fr + ', TV ' + r.tv + ', Tobin: <span class="' + tobinWarning + '">' + r.tobin_index + '</span>, ROX: <span class="' + roxWarning + '">' + r.rox_index + '</span>, SpO2 ' + r.spo2 + '%, NRS ' + r.nrs_dolore + extra +
                    '</span>' +
                    '<div class="flex items-center space-x-2 ml-2" onclick="event.stopPropagation()">' +
                        (r.can_edit ? '<button onclick=\'openRilevazioneModal(' + intervento_id + ', ' + rData + ')\' class="text-yellow-600 p-1" title="Modifica"><i class="ph ph-pencil-line"></i></button>' : '') +
                        (r.can_delete ? '<button onclick="deleteRilevazione(' + r.id + ', ' + intervento_id + ')" class="text-red-500 p-1" title="Elimina"><i class="ph ph-trash"></i></button>' : '') +
                    '</div>' +
                '</div>' +
                '<div id="rilevazione-content-' + r.id + '" class="hidden p-3 bg-gray-50 border-t grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">' +
                    '<div><span class="text-gray-500 uppercase font-bold text-[10px]">Data/Ora:</span><br>' + r.data_ora + '</div>' +
                    '<div><span class="text-gray-500 uppercase font-bold text-[10px]">FR:</span><br>' + r.fr + '</div>' +
                    '<div><span class="text-gray-500 uppercase font-bold text-[10px]">TV:</span><br>' + r.tv + '</div>' +
                    '<div><span class="text-gray-500 uppercase font-bold text-[10px]">Tobin:</span><br>' + r.tobin_index + '</div>' +
                    '<div><span class="text-gray-500 uppercase font-bold text-[10px]">SpO2:</span><br>' + r.spo2 + '</div>' +
                    '<div><span class="text-gray-500 uppercase font-bold text-[10px]">FiO2:</span><br>' + r.fio2 + '</div>' +
                    '<div><span class="text-gray-500 uppercase font-bold text-[10px]">ROX:</span><br>' + r.rox_index + '</div>' +
                    '<div><span class="text-gray-500 uppercase font-bold text-[10px]">PEEP:</span><br>' + r.peep + '</div>' +
                    '<div><span class="text-gray-500 uppercase font-bold text-[10px]">Pres. Supp.:</span><br>' + r.pressure_support + '</div>' +
                    '<div><span class="text-gray-500 uppercase font-bold text-[10px]">NRS Dolore:</span><br>' + r.nrs_dolore + '</div>' +
                    '<div><span class="text-gray-500 uppercase font-bold text-[10px]">NAS Score:</span><br>' + r.nas_score + '</div>' +
                    '<div><span class="text-gray-500 uppercase font-bold text-[10px]">Venturi:</span><br>' + (r.maschera_venturi || '-') + '</div>' +
                    '<div><span class="text-gray-500 uppercase font-bold text-[10px]">HFNO:</span><br>' + (r.hfno || '-') + '</div>' +
                    '<div><span class="text-gray-500 uppercase font-bold text-[10px]">NIV:</span><br>' + (r.niv || '-') + '</div>' +
                '</div>';
                div.appendChild(item);
            });
        }

        function openRilevazioneModal(intervento_id, r) {
            document.getElementById('rilevazioneForm').reset();
            document.querySelectorAll('#rilevazioneForm input').forEach(function(el) { el.classList.remove('range-ok', 'range-warning', 'range-critical'); });
            document.getElementById('r_intervento_id').value = intervento_id;
            document.getElementById('r_id').value = '';
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            document.getElementById('r_data_ora').value = now.toISOString().slice(0, 16);
            if (r) {
                document.getElementById('r_id').value = r.id;
                document.getElementById('r_data_ora').value = r.data_ora.replace(' ', 'T').slice(0, 16);
                document.getElementById('r_fase').value = r.fase;
                document.getElementById('r_fr').value = r.fr;
                document.getElementById('r_tv').value = r.tv;
                document.getElementById('r_tobin').value = r.tobin_index;
                document.getElementById('r_spo2').value = r.spo2;
                document.getElementById('r_fio2').value = r.fio2;
                document.getElementById('r_rox').value = r.rox_index;
                document.getElementById('r_peep').value = r.peep;
                document.getElementById('r_ps').value = r.pressure_support;
                document.getElementById('r_dolore').value = r.nrs_dolore;
                document.getElementById('r_nas').value = r.nas_score;
                document.getElementById('r_venturi').value = r.maschera_venturi || '';
                document.getElementById('r_hfno').value = r.hfno || '';
                document.getElementById('r_niv').value = r.niv || '';
                ['fr', 'tv', 'tobin_index', 'spo2', 'fio2', 'rox_index', 'peep', 'pressure_support', 'nrs_dolore', 'nas_score'].forEach(function(p) {
                    let fieldId = 'r_' + p;
                    if (p === 'tobin_index') fieldId = 'r_tobin';
                    if (p === 'rox_index') fieldId = 'r_rox';
                    if (p === 'pressure_support') fieldId = 'r_ps';
                    if (p === 'nrs_dolore') fieldId = 'r_dolore';
                    if (p === 'nas_score') fieldId = 'r_nas';
                    const input = document.getElementById(fieldId);
                    if (input) validateParam(p, input);
                });
            }
            renderTags('maschera_venturi', 'r_venturi');
            renderTags('hfno', 'r_hfno');
            renderTags('niv', 'r_niv');
            openModal('rilevazioneModal');
        }

        async function saveRilevazione(e) {
            e.preventDefault();
            const criticals = document.querySelectorAll('.range-critical');
            if (criticals.length > 0) {
                if (!confirm("Attenzione: alcuni parametri sono fuori dai range critici. Vuoi procedere?")) return;
            }
            const int_id = document.getElementById('r_intervento_id').value;
            const data = {
                id: document.getElementById('r_id').value,
                intervento_id: int_id,
                data_ora: document.getElementById('r_data_ora').value.replace('T', ' '),
                fase: document.getElementById('r_fase').value,
                fr: document.getElementById('r_fr').value,
                tv: document.getElementById('r_tv').value,
                tobin_index: document.getElementById('r_tobin').value,
                spo2: document.getElementById('r_spo2').value,
                fio2: document.getElementById('r_fio2').value,
                rox_index: document.getElementById('r_rox').value,
                peep: document.getElementById('r_peep').value,
                pressure_support: document.getElementById('r_ps').value,
                nrs_dolore: document.getElementById('r_dolore').value,
                nas_score: document.getElementById('r_nas').value,
                maschera_venturi: document.getElementById('r_venturi').value,
                hfno: document.getElementById('r_hfno').value,
                niv: document.getElementById('r_niv').value
            };
            await fetch('api.php?action=rilevazioni', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            closeModal('rilevazioneModal');
            loadRilevazioni(int_id);
        }

        async function deleteRilevazione(id, int_id) {
            if (confirm('Eliminare rilevazione?')) {
                await fetch('api.php?action=rilevazioni&id=' + id, { method: 'DELETE' });
                loadRilevazioni(int_id);
            }
        }

        async function loadEsito(intervento_id) {
            const res = await fetch('api.php?action=esito&intervento_id=' + intervento_id);
            const esiti = await res.json();
            const div = document.getElementById('esito-for-' + intervento_id);
            const actionSpan = document.getElementById('esito-action-' + intervento_id);
            div.innerHTML = '';
            if (esiti.length > 0) {
                const es = esiti[0];
                const eData = JSON.stringify(es).replace(/'/g, "&apos;");
                div.innerHTML = 'Successo: ' + (es.successo == 1 ? 'SÌ' : 'NO') + ', Post: ' + (es.tipo_post_estubazione || '-') + ', Fallimento: ' + (es.fallimento_iot == 1 ? 'SÌ' : 'NO') + ' (' + es.ore_da_estubazione_a_failure + 'h)';
                actionSpan.innerHTML = '<div class="flex space-x-2">' +
                    (es.can_edit ? '<button onclick=\'openEsitoModal(' + intervento_id + ', ' + eData + ')\' class="text-yellow-600" title="Modifica"><i class="ph ph-pencil-line"></i></button>' : '') +
                    (es.can_delete ? '<button onclick="deleteEsito(' + es.id + ', ' + intervento_id + ')" class="text-red-500" title="Elimina"><i class="ph ph-trash"></i></button>' : '') +
                '</div>';
            } else {
                div.innerHTML = '<span class="text-gray-400">Nessun esito registrato</span>';
                actionSpan.innerHTML = '<button onclick="openEsitoModal(' + intervento_id + ')" class="text-blue-500">+ Aggiungi</button>';
            }
        }

        function openEsitoModal(intervento_id, esito) {
            document.getElementById('esitoForm').reset();
            document.querySelectorAll('#esitoForm input').forEach(function(el) { el.classList.remove('range-ok', 'range-warning', 'range-critical'); });
            document.getElementById('e_intervento_id').value = intervento_id;
            document.getElementById('e_id').value = '';
            if (esito) {
                document.getElementById('e_id').value = esito.id;
                document.getElementById('e_successo').checked = (esito.successo == 1);
                document.getElementById('e_tipo').value = esito.tipo_post_estubazione || '';
                document.getElementById('e_fallimento').checked = (esito.fallimento_iot == 1);
                document.getElementById('e_ore').value = esito.ore_da_estubazione_a_failure;
                const input = document.getElementById('e_ore');
                if (input) validateParam('ore_da_estubazione_a_failure', input);
            }
            renderTags('tipo_post_estubazione', 'e_tipo');
            openModal('esitoModal');
        }

        async function saveEsito(e) {
            e.preventDefault();
            if (document.querySelectorAll('#esitoForm .range-critical').length > 0) {
                if (!confirm("Attenzione: parametri critici. Procedere?")) return;
            }
            const int_id = document.getElementById('e_intervento_id').value;
            const data = {
                id: document.getElementById('e_id').value,
                intervento_id: int_id,
                successo: document.getElementById('e_successo').checked ? 1 : 0,
                tipo_post_estubazione: document.getElementById('e_tipo').value,
                fallimento_iot: document.getElementById('e_fallimento').checked ? 1 : 0,
                ore_da_estubazione_a_failure: document.getElementById('e_ore').value
            };
            await fetch('api.php?action=esito', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            closeModal('esitoModal');
            loadEsito(int_id);
        }

        async function deleteEsito(id, int_id) {
            if (confirm('Eliminare esito?')) {
                await fetch('api.php?action=esito&id=' + id, { method: 'DELETE' });
                loadEsito(int_id);
            }
        }

        async function exportCSV() {
            const res = await fetch('api.php?action=all_data');
            const data = await res.json();
            if (data.length === 0) { alert("Nessun dato da esportare"); return; }
            const decryptedData = data.map(function(row) {
                const r = Object.assign({}, row);
                r.nome_cognome = decrypt(row.nome_cognome);
                return r;
            });
            const headers = Object.keys(decryptedData[0]);
            const csvRows = [headers.join(',')];
            for (let i = 0; i < decryptedData.length; i++) {
                const row = decryptedData[i];
                const values = headers.map(function(header) {
                    const val = row[header] === null ? '' : row[header];
                    const escaped = ('' + val).replace(/"/g, '""');
                    return '"' + escaped + '"';
                });
                csvRows.push(values.join(','));
            }
            const csvString = csvRows.join('\n');
            const blob = new Blob([csvString], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.setAttribute('href', url);
            link.setAttribute('download', 'statmed2_export.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        async function handleOCR(event) {
            const file = event.target.files[0];
            if (!file) return;
            const status = document.getElementById('ocr-status');
            const message = document.getElementById('ocr-message');
            status.classList.remove('hidden');
            message.innerText = "Avvio OCR...";
            try {
                const worker = await Tesseract.createWorker(['ita', 'eng']);
                const ret = await worker.recognize(file);
                parseOCRText(ret.data.text);
                await worker.terminate();
                status.classList.add('hidden');
            } catch (error) {
                console.error(error);
                message.innerText = "Errore OCR.";
                setTimeout(function() { status.classList.add('hidden'); }, 3000);
            }
        }

        function parseOCRText(text) {
            const mappings = [
                { field: 'r_fr', keywords: ['FR', 'RR', 'FREQ', 'RESP'] },
                { field: 'r_spo2', keywords: ['SPO2', 'SAT', 'O2'] },
                { field: 'r_fio2', keywords: ['FIO2', 'FI'] },
                { field: 'r_peep', keywords: ['PEEP', 'PEP'] },
                { field: 'r_ps', keywords: ['PS', 'SUPPORT'] },
                { field: 'r_dolore', keywords: ['NRS', 'PAIN', 'DOLORE'] },
                { field: 'r_tv', keywords: ['TV', 'VOL', 'TIDAL'] }
            ];
            const cleanText = text.toUpperCase().replace(/\s+/g, ' ');
            mappings.forEach(function(m) {
                m.keywords.forEach(function(key) {
                    const regex = new RegExp(key + '\\s*[:=]?\\s*(\\d+[.,]?\\d*)', 'i');
                    const match = cleanText.match(regex);
                    if (match && match[1]) {
                        const value = match[1].replace(',', '.');
                        const input = document.getElementById(m.field);
                        if (input && (!input.value || input.value == 0)) {
                            input.value = value;
                            validateParam(m.field.replace('r_', ''), input);
                        }
                    }
                });
            });
            calculateIndices();
        }

        loadPazienti();
    </script>
</body>
</html>
