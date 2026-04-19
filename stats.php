<?php
require_once 'auth.php';
if (!isLoggedIn() || isAdmin()) { header('Location: index.php'); exit; }

$db = getDB();

$current_team_id = $_SESSION['active_team_id'] ?? null;

// Fetch all data for stats
$sql = "SELECT p.id as paziente_id, p.nome_cognome, p.eta, p.altezza, p.peso, p.bmi,
               r.*,
               i.id as intervento_id, i.tipo_intervento, i.asa_score, i.euroscore_ii, i.durata_cec_ore, i.timing_iot_h
        FROM pazienti p
        JOIN interventi i ON p.id = i.paziente_id
        JOIN rilevazioni_cliniche r ON i.id = r.intervento_id";

if (!isAdmin()) {
    $sql .= " JOIN patient_teams pt ON p.id = pt.paziente_id WHERE pt.team_id = ?";
    $stmt = $db->prepare($sql . " ORDER BY r.data_ora DESC");
    $stmt->execute([$current_team_id]);
} else {
    $stmt = $db->query($sql . " ORDER BY r.data_ora DESC");
}
$data = $stmt->fetchAll();

// We need to decrypt names
foreach ($data as &$row) {
    // In a real app, decryption happens client-side.
    // Here we'll pass the encrypted names to JS and decrypt them there.
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiche - StatMed2</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <nav class="bg-purple-700 text-white p-4 shadow-lg">
        <div class="container mx-auto flex flex-col md:flex-row justify-between items-center gap-3">
            <div class="flex items-center justify-between w-full md:w-auto">
                <div class="flex items-center gap-2">
                    <img src="assets/logo_small.png" alt="Logo" class="h-8 w-auto">
                    <h1 class="text-xl font-bold flex items-center gap-2">
                        <i class="ph ph-presentation-chart"></i> Analytics
                    </h1>
                </div>
                <div class="flex items-center gap-2 md:hidden">
                    <a href="index.php" class="bg-white text-purple-700 px-4 py-1 rounded-full font-bold text-sm hover:bg-purple-50 transition">
                        Dashboard
                    </a>
                    <a href="profile.php" class="bg-purple-800 hover:bg-purple-900 p-2 rounded-full text-white transition" title="Profilo">
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
                    <a href="index.php" class="bg-white text-purple-700 px-4 py-1 rounded-full font-bold text-sm hover:bg-purple-50 transition">
                        Dashboard
                    </a>
                    <a href="profile.php" class="bg-purple-800 hover:bg-purple-900 p-2 rounded-full text-white transition" title="Profilo">
                        <i class="ph ph-user text-xl"></i>
                    </a>
                    <a href="login.php?action=logout" class="bg-red-500 hover:bg-red-600 p-2 rounded-full text-white transition" title="Esci">
                        <i class="ph ph-sign-out text-xl"></i>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="container mx-auto p-4 md:p-6">
        <!-- Dashboard Summary Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-blue-500">
                <p class="text-xs text-gray-500 font-bold uppercase">Media</p>
                <h3 id="stat-avg" class="text-2xl font-bold text-gray-800">-</h3>
            </div>
            <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-green-500">
                <p class="text-xs text-gray-500 font-bold uppercase">Minimo</p>
                <h3 id="stat-min" class="text-2xl font-bold text-gray-800">-</h3>
            </div>
            <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-red-500">
                <p class="text-xs text-gray-500 font-bold uppercase">Massimo</p>
                <h3 id="stat-max" class="text-2xl font-bold text-gray-800">-</h3>
            </div>
            <div class="bg-white p-4 rounded-xl shadow-sm border-l-4 border-purple-500">
                <p class="text-xs text-gray-500 font-bold uppercase">Campioni</p>
                <h3 id="stat-count" class="text-2xl font-bold text-gray-800">-</h3>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Sidebar Filters -->
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200">
                    <h2 class="text-sm font-bold mb-4 text-gray-600 flex items-center gap-2">
                        <i class="ph ph-funnel"></i> FILTRI E PIVOT
                    </h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-400 mb-1 uppercase">Paziente</label>
                            <select id="filter-paziente" class="w-full p-2 bg-gray-50 border rounded-lg text-sm" onchange="populateInterventi(); updateStats();">
                                <option value="">Tutti i pazienti</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-400 mb-1 uppercase">Intervento</label>
                            <select id="filter-intervento" class="w-full p-2 bg-gray-50 border rounded-lg text-sm" onchange="updateStats()">
                                <option value="">Tutti gli interventi</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-400 mb-1 uppercase">Fase Clinica</label>
                            <select id="filter-fase" class="w-full p-2 bg-gray-50 border rounded-lg text-sm" onchange="updateStats()">
                                <option value="">Tutte le fasi</option>
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
                        <div class="pt-4 border-t border-gray-100">
                            <label class="block text-xs font-bold text-purple-600 mb-1 uppercase">Parametro da Analizzare</label>
                            <select id="filter-param" class="w-full p-2 bg-purple-50 border border-purple-200 rounded-lg text-sm font-bold text-purple-700" onchange="updateStats()">
                                <optgroup label="Indici Weaning">
                                    <option value="tobin_index">Indice di Tobin (RSBI)</option>
                                    <option value="rox_index">ROX Index</option>
                                </optgroup>
                                <optgroup label="Parametri Vitali">
                                    <option value="fr">Freq. Respiratoria (FR)</option>
                                    <option value="spo2">Saturazione (SpO2)</option>
                                    <option value="tv">Vol. Corrente (TV)</option>
                                    <option value="peep">PEEP</option>
                                    <option value="pressure_support">Pressure Support</option>
                                </optgroup>
                                <optgroup label="Anagrafica">
                                    <option value="eta">Età</option>
                                    <option value="bmi">BMI</option>
                                    <option value="peso">Peso</option>
                                    <option value="altezza">Altezza</option>
                                </optgroup>
                                <optgroup label="Intervento">
                                    <option value="asa_score">ASA Score</option>
                                    <option value="euroscore_ii">Euroscore II</option>
                                    <option value="durata_cec_ore">Durata CEC (h)</option>
                                    <option value="timing_iot_h">Timing IOT (h)</option>
                                </optgroup>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Charts and Data -->
            <div class="lg:col-span-3 space-y-6">
                <div class="bg-white p-4 md:p-6 rounded-xl shadow-sm border border-gray-200">
                    <h3 class="font-bold text-gray-700 mb-4 flex items-center gap-2">
                        <i class="ph ph-chart-line"></i> Andamento Clinico
                    </h3>
                    <div class="h-[300px] md:h-[400px] w-full">
                        <canvas id="statsChart"></canvas>
                    </div>
                </div>

                <div class="bg-white p-4 md:p-6 rounded-xl shadow-sm border border-gray-200">
                    <h3 class="font-bold text-gray-700 mb-4 flex items-center gap-2">
                        <i class="ph ph-table"></i> Dettaglio Rilevazioni
                    </h3>
                    <div class="overflow-y-auto max-h-[400px] rounded-lg border border-gray-100">
                        <table class="w-full text-left border-collapse">
                            <thead class="sticky top-0 bg-gray-50 shadow-sm">
                                <tr class="text-[10px] md:text-xs uppercase text-gray-500">
                                    <th class="p-3">Data/Ora</th>
                                    <th class="p-3">Paziente</th>
                                    <th class="p-3">Fase</th>
                                    <th class="p-3 text-right" id="table-param-header">Valore</th>
                                </tr>
                            </thead>
                            <tbody id="stats-table-body" class="text-xs md:text-sm divide-y divide-gray-100"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        const rawData = <?php echo json_encode($data); ?>;
        const encryptionKey = sessionStorage.getItem('encryption_key');

        function decrypt(ciphertext) {
            try {
                const bytes = CryptoJS.AES.decrypt(ciphertext, encryptionKey);
                return bytes.toString(CryptoJS.enc.Utf8);
            } catch (e) { return "Errore"; }
        }

        // Initialize filters
        const pSelect = document.getElementById('filter-paziente');
        const iSelect = document.getElementById('filter-intervento');
        const uniquePazienti = [...new Set(rawData.map(r => r.paziente_id))];
        uniquePazienti.forEach(id => {
            const row = rawData.find(r => r.paziente_id === id);
            const opt = document.createElement('option');
            opt.value = id;
            opt.text = `ID ${id} - ${decrypt(row.nome_cognome)}`;
            pSelect.appendChild(opt);
        });

        function populateInterventi() {
            const pId = pSelect.value;
            iSelect.innerHTML = '<option value="">Tutti gli interventi</option>';
            const filteredRaw = pId ? rawData.filter(r => r.paziente_id == pId) : rawData;
            const uniqueInt = [...new Set(filteredRaw.map(r => r.intervento_id))];
            uniqueInt.forEach(id => {
                const row = rawData.find(r => r.intervento_id === id);
                const opt = document.createElement('option');
                opt.value = id;
                opt.text = `${row.tipo_intervento} (ID ${id})`;
                iSelect.appendChild(opt);
            });
        }

        let chart = null;

        function updateStats() {
            const pId = document.getElementById('filter-paziente').value;
            const iId = document.getElementById('filter-intervento').value;
            const fase = document.getElementById('filter-fase').value;
            const param = document.getElementById('filter-param').value;

            const filtered = rawData.filter(r => {
                return (!pId || r.paziente_id == pId) &&
                       (!iId || r.intervento_id == iId) &&
                       (!fase || r.fase == fase);
            }).sort((a, b) => new Date(a.data_ora) - new Date(b.data_ora));

            // Summary Stats
            const values = filtered.map(r => parseFloat(r[param])).filter(v => !isNaN(v));
            if (values.length > 0) {
                const sum = values.reduce((a, b) => a + b, 0);
                document.getElementById('stat-avg').innerText = (sum / values.length).toFixed(2);
                document.getElementById('stat-min').innerText = Math.min(...values).toFixed(2);
                document.getElementById('stat-max').innerText = Math.max(...values).toFixed(2);
                document.getElementById('stat-count').innerText = values.length;
            } else {
                ['stat-avg', 'stat-min', 'stat-max', 'stat-count'].forEach(id => document.getElementById(id).innerText = '-');
            }

            // Update Table
            document.getElementById('table-param-header').innerText = document.getElementById('filter-param').selectedOptions[0].text;
            const tbody = document.getElementById('stats-table-body');
            tbody.innerHTML = '';
            filtered.forEach(r => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-gray-50 transition';
                const date = new Date(r.data_ora);
                const dateStr = `${date.getDate().toString().padStart(2, '0')}/${(date.getMonth()+1).toString().padStart(2, '0')} ${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`;
                tr.innerHTML = `
                    <td class="p-3 text-gray-500 font-mono">${dateStr}</td>
                    <td class="p-3 font-medium">${decrypt(r.nome_cognome)}</td>
                    <td class="p-3"><span class="px-2 py-1 bg-gray-100 rounded text-[10px] font-bold">${r.fase}</span></td>
                    <td class="p-3 text-right font-bold text-purple-600">${r[param] ?? '-'}</td>
                `;
                tbody.appendChild(tr);
            });

            // Update Chart
            const ctx = document.getElementById('statsChart').getContext('2d');
            if (chart) chart.destroy();

            const labels = filtered.map(r => {
                const d = new Date(r.data_ora);
                return `${d.getDate()}/${d.getMonth()+1} ${d.getHours()}:${d.getMinutes()}`;
            });

            chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: document.getElementById('filter-param').selectedOptions[0].text,
                        data: filtered.map(r => r[param]),
                        borderColor: '#7e22ce',
                        backgroundColor: 'rgba(126, 34, 206, 0.1)',
                        borderWidth: 3,
                        pointBackgroundColor: '#7e22ce',
                        pointRadius: 4,
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            grid: { color: '#f3f4f6' }
                        },
                        x: {
                            grid: { display: false },
                            ticks: {
                                maxRotation: 45,
                                minRotation: 45,
                                font: { size: 10 }
                            }
                        }
                    }
                }
            });
        }

        updateStats();
    </script>
</body>
</html>
