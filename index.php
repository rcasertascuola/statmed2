<?php
require_once 'auth.php';

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    if (login($_POST['username'], $_POST['password'], $_POST['encryption_key'])) {
        header('Location: index.php');
        exit;
    } else {
        $error = "Credenziali o chiave di crittografia errate.";
    }
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logout();
    header('Location: index.php');
    exit;
}

if (!isLoggedIn()): ?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - StatMed2</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded shadow-md w-96">
        <h1 class="text-2xl font-bold mb-6 text-center">StatMed2 Login</h1>
        <?php if (isset($error)): ?>
            <p class="text-red-500 mb-4"><?php echo $error; ?></p>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="action" value="login">
            <div class="mb-4">
                <label class="block text-gray-700">Username</label>
                <input type="text" name="username" class="w-full p-2 border rounded" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700">Password</label>
                <input type="password" name="password" class="w-full p-2 border rounded" required>
            </div>
            <div class="mb-6">
                <label class="block text-gray-700">Chiave di Crittografia</label>
                <input type="password" name="encryption_key" id="encryption_key_input" class="w-full p-2 border rounded" required>
                <p class="text-xs text-gray-500 mt-1">Sostituirà quella esistente se è il primo accesso.</p>
            </div>
            <button type="submit" onclick="saveKey()" class="w-full bg-blue-500 text-white p-2 rounded hover:bg-blue-600 transition">Accedi</button>
        </form>
    </div>
    <script>
        function saveKey() {
            const key = document.getElementById('encryption_key_input').value;
            if(key) {
                sessionStorage.setItem('encryption_key', key);
            }
        }
    </script>
</body>
</html>
<?php exit; endif; ?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - StatMed2</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>
    <!-- Heroicons for better UI -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        .modal { display: none; position: fixed; z-index: 50; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 800px; border-radius: 8px; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <nav class="bg-blue-600 text-white p-4 shadow-lg flex justify-between items-center">
        <h1 class="text-xl font-bold">StatMed2 Dashboard</h1>
        <div class="flex items-center space-x-4">
            <span>Benvenuto, <strong><?php echo $_SESSION['username']; ?></strong> (<?php echo $_SESSION['role']; ?>)</span>
            <button onclick="exportCSV()" class="bg-green-500 hover:bg-green-600 px-3 py-1 rounded text-sm transition">Scarica CSV</button>
            <a href="?action=logout" class="bg-red-500 hover:bg-red-600 px-3 py-1 rounded text-sm transition">Logout</a>
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
                        <th class="p-2 md:p-4">ID</th>
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
                    <input type="number" id="p_eta" class="w-full p-2 border rounded" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Altezza (m)</label>
                    <input type="number" step="0.01" id="p_altezza" oninput="calculateBMI()" class="w-full p-2 border rounded" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Peso (kg)</label>
                    <input type="number" step="0.1" id="p_peso" oninput="calculateBMI()" class="w-full p-2 border rounded" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">BMI (Auto)</label>
                    <input type="number" step="0.01" id="p_bmi" class="w-full p-2 border bg-gray-100" readonly>
                </div>
                <div class="md:col-span-2 flex justify-end space-x-2 mt-4">
                    <button type="button" onclick="closeModal('pazienteModal')" class="bg-gray-300 px-4 py-2 rounded">Annulla</button>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Salva</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Interventi/Rilevazioni Modal (simplified for now) -->
    <div id="detailsModal" class="modal">
        <div class="modal-content max-w-4xl">
            <h3 class="text-xl font-bold mb-4">Dettagli Clinici: <span id="detailPazienteNome"></span></h3>
            
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

    <!-- Additional Modals for Intervento, Rilevazione, Esito would go here -->
    <div id="interventoModal" class="modal">
        <div class="modal-content">
            <h3 class="text-xl font-bold mb-4">Dati Intervento</h3>
            <form id="interventoForm" onsubmit="saveIntervento(event)" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" id="i_id">
                <input type="hidden" id="i_paziente_id">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Comorbidità</label>
                    <textarea id="i_comorbilita" class="w-full p-2 border rounded"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">ASA Score</label>
                    <input type="number" id="i_asa" class="w-full p-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tipo Intervento</label>
                    <input type="text" id="i_tipo" class="w-full p-2 border rounded">
                </div>
                <div class="flex items-center">
                    <input type="checkbox" id="i_urgenza" class="mr-2">
                    <label class="text-sm font-medium text-gray-700">Urgenza</label>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Euroscore II</label>
                    <input type="number" step="0.01" id="i_euroscore" class="w-full p-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Durata CEC (ore)</label>
                    <input type="number" step="0.1" id="i_cec" class="w-full p-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Timing IOT (h)</label>
                    <input type="number" step="0.1" id="i_iot" class="w-full p-2 border rounded">
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
            <form id="rilevazioneForm" onsubmit="saveRilevazione(event)" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <input type="hidden" id="r_id">
                <input type="hidden" id="r_intervento_id">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Fase</label>
                    <select id="r_fase" class="w-full p-2 border rounded">
                        <option value="PRE_SBT">PRE_SBT</option>
                        <option value="T0">T0</option>
                        <option value="T30">T30</option>
                        <option value="POST_2H">POST_2H</option>
                        <option value="POST_6H">POST_6H</option>
                        <option value="POST_12H">POST_12H</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">FR (Freq. Resp.)</label>
                    <input type="number" step="0.1" id="r_fr" oninput="calculateIndices()" class="w-full p-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">TV (Vol. Corrente L)</label>
                    <input type="number" step="0.001" id="r_tv" oninput="calculateIndices()" class="w-full p-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Tobin Index (RSBI)</label>
                    <input type="number" step="0.1" id="r_tobin" class="w-full p-2 border bg-gray-100" readonly>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">SpO2 (%)</label>
                    <input type="number" step="0.1" id="r_spo2" oninput="calculateIndices()" class="w-full p-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">FiO2 (0.21-1.0)</label>
                    <input type="number" step="0.01" id="r_fio2" oninput="calculateIndices()" class="w-full p-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">ROX Index</label>
                    <input type="number" step="0.01" id="r_rox" class="w-full p-2 border bg-gray-100" readonly>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">PEEP</label>
                    <input type="number" step="0.1" id="r_peep" class="w-full p-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Pressure Support</label>
                    <input type="number" step="0.1" id="r_ps" class="w-full p-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">NRS Dolore (0-10)</label>
                    <input type="number" id="r_dolore" class="w-full p-2 border rounded">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">NAS Score</label>
                    <input type="number" step="0.1" id="r_nas" class="w-full p-2 border rounded">
                </div>
                <div class="md:col-span-3 flex justify-end space-x-2 mt-4">
                    <button type="button" onclick="closeModal('rilevazioneModal')" class="bg-gray-300 px-4 py-2 rounded">Annulla</button>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Salva</button>
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
                    <select id="e_tipo" class="w-full p-2 border rounded">
                        <option value="Ossigeno standard">Ossigeno standard</option>
                        <option value="HFNO">HFNO</option>
                        <option value="NIV">NIV</option>
                    </select>
                </div>
                <div class="flex items-center">
                    <input type="checkbox" id="e_fallimento" class="mr-2">
                    <label class="text-sm font-medium text-gray-700">Fallimento IOT</label>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Ore da estubazione a failure</label>
                    <input type="number" step="0.1" id="e_ore" class="w-full p-2 border rounded">
                </div>
                <div class="md:col-span-2 flex justify-end space-x-2 mt-4">
                    <button type="button" onclick="closeModal('esitoModal')" class="bg-gray-300 px-4 py-2 rounded">Annulla</button>
                    <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded">Salva</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const encryptionKey = sessionStorage.getItem('encryption_key');
        if (!encryptionKey) {
            window.location.href = 'index.php?action=logout';
        }

        const isAdmin = <?php echo isAdmin() ? 'true' : 'false'; ?>;

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

        // Calculation functions
        function calculateBMI() {
            const h = parseFloat(document.getElementById('p_altezza').value);
            const w = parseFloat(document.getElementById('p_peso').value);
            if (h > 0 && w > 0) {
                document.getElementById('p_bmi').value = (w / (h * h)).toFixed(2);
            }
        }

        function calculateIndices() {
            const fr = parseFloat(document.getElementById('r_fr').value);
            const tv = parseFloat(document.getElementById('r_tv').value);
            const spo2 = parseFloat(document.getElementById('r_spo2').value);
            const fio2 = parseFloat(document.getElementById('r_fio2').value);

            if (fr > 0 && tv > 0) {
                document.getElementById('r_tobin').value = (fr / tv).toFixed(1);
            }
            if (fr > 0 && spo2 > 0 && fio2 > 0) {
                document.getElementById('r_rox').value = ((spo2 / fio2) / fr).toFixed(2);
            }
        }

        // Modal helpers
        function openModal(id) { document.getElementById(id).style.display = 'block'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }

        function openPazienteModal(paziente = null) {
            document.getElementById('pazienteForm').reset();
            document.getElementById('p_id').value = '';
            if (paziente) {
                document.getElementById('p_id').value = paziente.id;
                document.getElementById('p_nome').value = decrypt(paziente.nome_cognome);
                document.getElementById('p_sesso').value = paziente.sesso;
                document.getElementById('p_eta').value = paziente.eta;
                document.getElementById('p_altezza').value = paziente.altezza;
                document.getElementById('p_peso').value = paziente.peso;
                document.getElementById('p_bmi').value = paziente.bmi;
            }
            openModal('pazienteModal');
        }

        async function loadPazienti() {
            const res = await fetch('api.php?action=pazienti');
            const pazienti = await res.json();
            const tbody = document.getElementById('pazienti-table-body');
            tbody.innerHTML = '';
            pazienti.forEach(p => {
                const row = document.createElement('tr');
                row.className = 'border-b hover:bg-gray-50';
                row.innerHTML = `
                    <td class="p-2 md:p-4">${p.id}</td>
                    <td class="p-2 md:p-4 font-medium">${decrypt(p.nome_cognome)}</td>
                    <td class="p-2 md:p-4">${p.sesso}</td>
                    <td class="p-2 md:p-4">${p.eta}</td>
                    <td class="p-2 md:p-4">${p.bmi}</td>
                    <td class="p-2 md:p-4">
                        <div class="flex items-center justify-center space-x-1 md:space-x-2">
                            <button onclick='viewDetails(${JSON.stringify(p).replace(/'/g, "&apos;")})' class="text-blue-500 hover:text-blue-700 p-1" title="Dettagli">
                                <i class="ph ph-eye text-lg md:text-xl"></i>
                            </button>
                            <button onclick='openPazienteModal(${JSON.stringify(p).replace(/'/g, "&apos;")})' class="text-yellow-600 hover:text-yellow-800 p-1" title="Modifica">
                                <i class="ph ph-pencil-line text-lg md:text-xl"></i>
                            </button>
                            ${isAdmin ? `
                            <button onclick="deletePaziente(${p.id})" class="text-red-500 hover:text-red-700 p-1" title="Elimina">
                                <i class="ph ph-trash text-lg md:text-xl"></i>
                            </button>` : ''}
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });
        }

        async function savePaziente(e) {
            e.preventDefault();
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
            if (confirm('Sei sicuro di voler eliminare questo paziente e tutti i suoi dati?')) {
                await fetch(`api.php?action=pazienti&id=${id}`, { method: 'DELETE' });
                loadPazienti();
            }
        }

        // Details management
        let currentPazienteId = null;
        async function viewDetails(p) {
            currentPazienteId = p.id;
            document.getElementById('detailPazienteNome').innerText = decrypt(p.nome_cognome);
            await loadInterventi();
            openModal('detailsModal');
        }

        async function loadInterventi() {
            const res = await fetch(`api.php?action=interventi&paziente_id=${currentPazienteId}`);
            const interventi = await res.json();
            const list = document.getElementById('interventi-list');
            list.innerHTML = '';
            for (const i of interventi) {
                const div = document.createElement('div');
                div.className = 'p-4 bg-gray-50 rounded border';
                div.innerHTML = `
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <strong>${i.tipo_intervento}</strong> - ASA: ${i.asa_score} - ${i.urgenza ? 'URGENTE' : 'Elezione'}
                        </div>
                        <div class="space-x-2 flex">
                            <button onclick='openInterventoModal(${JSON.stringify(i).replace(/'/g, "&apos;")})' class="text-yellow-600 p-1" title="Modifica">
                                <i class="ph ph-pencil-line text-lg"></i>
                            </button>
                            ${isAdmin ? `
                            <button onclick="deleteIntervento(${i.id})" class="text-red-500 p-1" title="Elimina">
                                <i class="ph ph-trash text-lg"></i>
                            </button>` : ''}
                        </div>
                    </div>
                    <div class="text-sm text-gray-600 mb-2">
                        Euroscore: ${i.euroscore_ii} | CEC: ${i.durata_cec_ore}h | IOT: ${i.timing_iot_h}h
                    </div>
                    <div class="ml-4">
                        <h5 class="font-semibold text-xs border-b mb-1 flex justify-between">
                            Rilevazioni Cliniche
                            <button onclick="openRilevazioneModal(${i.id})" class="text-blue-500">+ Aggiungi</button>
                        </h5>
                        <div id="rilevazioni-for-${i.id}" class="space-y-1 mb-2"></div>
                        
                        <h5 class="font-semibold text-xs border-b mb-1 flex justify-between">
                            Esito Weaning
                            <span id="esito-action-${i.id}"></span>
                        </h5>
                        <div id="esito-for-${i.id}" class="text-xs"></div>
                    </div>
                `;
                list.appendChild(div);
                loadRilevazioni(i.id);
                loadEsito(i.id);
            }
        }

        async function openInterventoModal(intervento = null) {
            document.getElementById('interventoForm').reset();
            document.getElementById('i_id').value = '';
            document.getElementById('i_paziente_id').value = currentPazienteId;
            if (intervento) {
                document.getElementById('i_id').value = intervento.id;
                document.getElementById('i_comorbilita').value = intervento.comorbilita;
                document.getElementById('i_asa').value = intervento.asa_score;
                document.getElementById('i_tipo').value = intervento.tipo_intervento;
                document.getElementById('i_urgenza').checked = intervento.urgenza == 1;
                document.getElementById('i_euroscore').value = intervento.euroscore_ii;
                document.getElementById('i_cec').value = intervento.durata_cec_ore;
                document.getElementById('i_iot').value = intervento.timing_iot_h;
            }
            openModal('interventoModal');
        }

        async function saveIntervento(e) {
            e.preventDefault();
            const data = {
                id: document.getElementById('i_id').value,
                paziente_id: document.getElementById('i_paziente_id').value,
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
                await fetch(`api.php?action=interventi&id=${id}`, { method: 'DELETE' });
                loadInterventi();
            }
        }

        async function loadRilevazioni(intervento_id) {
            const res = await fetch(`api.php?action=rilevazioni&intervento_id=${intervento_id}`);
            const rilevazioni = await res.json();
            const div = document.getElementById(`rilevazioni-for-${intervento_id}`);
            div.innerHTML = '';
            rilevazioni.forEach(r => {
                const item = document.createElement('div');
                item.className = 'text-xs bg-white p-1 rounded border flex justify-between items-center';
                const tobinWarning = r.tobin_index > 105 ? 'text-red-600 font-bold' : (r.tobin_index > 80 ? 'text-orange-500' : '');
                const roxWarning = r.rox_index < 3.85 ? 'text-red-600 font-bold' : '';
                item.innerHTML = `
                    <span class="truncate pr-2">
                        <strong>${r.fase}:</strong> FR ${r.fr}, TV ${r.tv}, 
                        Tobin: <span class="${tobinWarning}">${r.tobin_index}</span>, 
                        ROX: <span class="${roxWarning}">${r.rox_index}</span>, 
                        SpO2 ${r.spo2}%, NRS ${r.nrs_dolore}
                    </span>
                    <span class="space-x-1 flex-shrink-0">
                        <button onclick='openRilevazioneModal(${intervento_id}, ${JSON.stringify(r).replace(/'/g, "&apos;")})' class="text-yellow-600" title="Modifica">
                            <i class="ph ph-pencil-line"></i>
                        </button>
                        ${isAdmin ? `
                        <button onclick="deleteRilevazione(${r.id}, ${intervento_id})" class="text-red-500" title="Elimina">
                            <i class="ph ph-trash"></i>
                        </button>` : ''}
                    </span>
                `;
                div.appendChild(item);
            });
        }

        function openRilevazioneModal(intervento_id, r = null) {
            document.getElementById('rilevazioneForm').reset();
            document.getElementById('r_intervento_id').value = intervento_id;
            document.getElementById('r_id').value = '';
            if (r) {
                document.getElementById('r_id').value = r.id;
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
            }
            openModal('rilevazioneModal');
        }

        async function saveRilevazione(e) {
            e.preventDefault();
            const int_id = document.getElementById('r_intervento_id').value;
            const data = {
                id: document.getElementById('r_id').value,
                intervento_id: int_id,
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
                nas_score: document.getElementById('r_nas').value
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
                await fetch(`api.php?action=rilevazioni&id=${id}`, { method: 'DELETE' });
                loadRilevazioni(int_id);
            }
        }

        async function loadEsito(intervento_id) {
            const res = await fetch(`api.php?action=esito&intervento_id=${intervento_id}`);
            const esiti = await res.json();
            const div = document.getElementById(`esito-for-${intervento_id}`);
            const actionSpan = document.getElementById(`esito-action-${intervento_id}`);
            div.innerHTML = '';
            
            if (esiti.length > 0) {
                const e = esiti[0];
                div.innerHTML = `
                    Successo: ${e.successo == 1 ? 'SÌ' : 'NO'}, Post: ${e.tipo_post_estubazione}, 
                    Fallimento: ${e.fallimento_iot == 1 ? 'SÌ' : 'NO'} (${e.ore_da_estubazione_a_failure}h)
                `;
                actionSpan.innerHTML = `
                    <div class="flex space-x-2">
                        <button onclick='openEsitoModal(${intervento_id}, ${JSON.stringify(e).replace(/'/g, "&apos;")})' class="text-yellow-600" title="Modifica">
                             <i class="ph ph-pencil-line"></i>
                        </button>
                        ${isAdmin ? `
                        <button onclick="deleteEsito(${e.id}, ${intervento_id})" class="text-red-500" title="Elimina">
                             <i class="ph ph-trash"></i>
                        </button>` : ''}
                    </div>
                `;
            } else {
                div.innerHTML = '<span class="text-gray-400">Nessun esito registrato</span>';
                actionSpan.innerHTML = `<button onclick="openEsitoModal(${intervento_id})" class="text-blue-500">+ Aggiungi</button>`;
            }
        }

        function openEsitoModal(intervento_id, e = null) {
            document.getElementById('esitoForm').reset();
            document.getElementById('e_intervento_id').value = intervento_id;
            document.getElementById('e_id').value = '';
            if (e) {
                document.getElementById('e_id').value = e.id;
                document.getElementById('e_successo').checked = e.successo == 1;
                document.getElementById('e_tipo').value = e.tipo_post_estubazione;
                document.getElementById('e_fallimento').checked = e.fallimento_iot == 1;
                document.getElementById('e_ore').value = e.ore_da_estubazione_a_failure;
            }
            openModal('esitoModal');
        }

        async function saveEsito(e) {
            e.preventDefault();
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
                await fetch(`api.php?action=esito&id=${id}`, { method: 'DELETE' });
                loadEsito(int_id);
            }
        }

        // CSV Export
        async function exportCSV() {
            const res = await fetch('api.php?action=all_data');
            const data = await res.json();
            if (data.length === 0) {
                alert("Nessun dato da esportare");
                return;
            }

            // Decrypt names
            const decryptedData = data.map(row => ({
                ...row,
                nome_cognome: decrypt(row.nome_cognome)
            }));

            const headers = Object.keys(decryptedData[0]);
            const csvRows = [headers.join(',')];

            for (const row of decryptedData) {
                const values = headers.map(header => {
                    const val = row[header] === null ? '' : row[header];
                    const escaped = ('' + val).replace(/"/g, '""');
                    return `"${escaped}"`;
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

        // Initial load
        loadPazienti();
    </script>
</body>
</html>
