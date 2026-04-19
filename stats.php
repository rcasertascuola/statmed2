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
    <nav class="bg-blue-600 text-white p-3 shadow-lg relative z-50">
        <div class="container mx-auto">
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <a href="index.php" class="hover:opacity-80 transition" title="Dashboard">
                        <img src="assets/logo_small.png" alt="Logo" class="h-8 w-auto">
                    </a>
                </div>

                <div class="flex items-center gap-2 font-bold uppercase tracking-wider text-sm md:text-base">
                    <i class="ph ph-chart-line-up"></i>
                    <span>Statistiche</span>
                </div>

                <div class="flex items-center">
                    <div class="hidden md:flex items-center gap-2">
                        <a href="index.php" class="p-2 hover:bg-blue-700 rounded-full transition" title="Dashboard">
                            <i class="ph ph-gauge text-xl"></i>
                        </a>
                        <a href="pazienti.php" class="p-2 hover:bg-blue-700 rounded-full transition" title="Pazienti">
                            <i class="ph ph-users text-xl"></i>
                        </a>
                        <?php if (isAdmin() || isLeader()): ?>
                        <a href="settings.php" class="p-2 hover:bg-blue-700 rounded-full transition" title="Impostazioni">
                            <i class="ph ph-gear text-xl"></i>
                        </a>
                        <?php endif; ?>
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
                <?php if (isAdmin() || isLeader()): ?>
                <a href="settings.php" class="flex items-center gap-3 p-3 hover:bg-blue-800 rounded-lg transition">
                    <i class="ph ph-gear text-xl"></i>
                    <span>Impostazioni</span>
                </a>
                <?php endif; ?>
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

    <main class="container mx-auto p-4 md:p-6 space-y-6">
        <!-- Top Parameter Selection -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-purple-100">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex-1">
                    <label class="block text-xs font-bold text-purple-600 mb-1 uppercase tracking-wider">Parametro da Analizzare</label>
                    <select id="filter-param" class="w-full p-3 bg-purple-50 border border-purple-200 rounded-xl text-lg font-bold text-purple-700 focus:ring-2 focus:ring-purple-500 outline-none transition" onchange="updateStats()">
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
                            <option value="fio2">FiO2</option>
                            <option value="nrs_dolore">NRS Dolore</option>
                            <option value="nas_score">NAS Score</option>
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
                <div id="histogram-controls" class="hidden animate-pulse bg-blue-50 p-4 rounded-xl border border-blue-100">
                    <label class="block text-[10px] font-bold text-blue-600 mb-1 uppercase tracking-wider text-center">Intervalli Istogramma (Bins)</label>
                    <div class="flex items-center gap-3">
                        <button onclick="changeBins(-1)" class="w-8 h-8 flex items-center justify-center bg-white border border-blue-200 rounded-lg text-blue-600 hover:bg-blue-600 hover:text-white transition shadow-sm"><i class="ph ph-minus"></i></button>
                        <input type="number" id="hist-bins" value="10" min="1" max="50" class="w-12 text-center bg-transparent font-bold text-blue-700 border-none outline-none" onchange="updateStats()">
                        <button onclick="changeBins(1)" class="w-8 h-8 flex items-center justify-center bg-white border border-blue-200 rounded-lg text-blue-600 hover:bg-blue-600 hover:text-white transition shadow-sm"><i class="ph ph-plus"></i></button>
                        <div class="relative group">
                            <i class="ph ph-info text-blue-400 text-xl cursor-help"></i>
                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 w-64 p-3 bg-gray-900 text-white text-[10px] rounded-lg opacity-0 group-hover:opacity-100 transition pointer-events-none shadow-xl z-[60] leading-relaxed">
                                <p class="font-bold mb-1 border-b border-gray-700 pb-1">Calcolo degli Intervalli</p>
                                <p>Il numero di classi (bins) è calcolato automaticamente seguendo la <strong>Regola di Sturges</strong> o la <strong>Regola di Rice</strong>, ottimizzate per distribuzioni statistiche in ambito clinico-scientifico.</p>
                                <div class="mt-1 text-gray-400">N. classi = ceil(log2(n) + 1)</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards Grid -->
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4 mb-6">
            <div class="bg-white p-3 rounded-xl shadow-sm border-b-4 border-blue-500">
                <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">Media</p>
                <h3 id="stat-avg" class="text-xl font-black text-gray-800">-</h3>
            </div>
            <div class="bg-white p-3 rounded-xl shadow-sm border-b-4 border-green-500">
                <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">Minimo</p>
                <h3 id="stat-min" class="text-xl font-black text-gray-800">-</h3>
            </div>
            <div class="bg-white p-3 rounded-xl shadow-sm border-b-4 border-red-500">
                <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">Massimo</p>
                <h3 id="stat-max" class="text-xl font-black text-gray-800">-</h3>
            </div>
            <div class="bg-white p-3 rounded-xl shadow-sm border-b-4 border-indigo-500">
                <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">Std Dev</p>
                <h3 id="stat-std" class="text-xl font-black text-gray-800">-</h3>
            </div>
            <div class="bg-white p-3 rounded-xl shadow-sm border-b-4 border-yellow-500">
                <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">Q1 (25%)</p>
                <h3 id="stat-q1" class="text-xl font-black text-gray-800">-</h3>
            </div>
            <div class="bg-white p-3 rounded-xl shadow-sm border-b-4 border-orange-500">
                <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">Mediana</p>
                <h3 id="stat-q2" class="text-xl font-black text-gray-800">-</h3>
            </div>
            <div class="bg-white p-3 rounded-xl shadow-sm border-b-4 border-pink-500">
                <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">Q3 (75%)</p>
                <h3 id="stat-q3" class="text-xl font-black text-gray-800">-</h3>
            </div>
            <div class="bg-white p-3 rounded-xl shadow-sm border-b-4 border-purple-500">
                <p class="text-[10px] text-gray-500 font-bold uppercase tracking-wider">Campioni</p>
                <h3 id="stat-count" class="text-xl font-black text-gray-800">-</h3>
            </div>
        </div>

        <!-- Filters and Pivot -->
        <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-200">
            <h2 class="text-xs font-bold mb-4 text-gray-400 flex items-center gap-2 uppercase tracking-widest">
                <i class="ph ph-funnel"></i> FILTRI E PIVOT
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 mb-1 uppercase">Paziente</label>
                    <select id="filter-paziente" class="w-full p-2 bg-gray-50 border rounded-lg text-sm focus:ring-2 focus:ring-blue-400 outline-none transition" onchange="populateInterventi(); updateStats();">
                        <option value="">Tutti i pazienti</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-gray-400 mb-1 uppercase">Intervento</label>
                    <select id="filter-intervento" class="w-full p-2 bg-gray-50 border rounded-lg text-sm focus:ring-2 focus:ring-blue-400 outline-none transition" onchange="updateStats()">
                        <option value="">Tutti gli interventi</option>
                    </select>
                </div>
                <div id="container-filter-fase">
                    <label class="block text-[10px] font-bold text-gray-400 mb-1 uppercase">Fasi Cliniche</label>
                    <div id="filter-fase-list" class="max-h-24 overflow-y-auto border rounded-lg p-2 bg-gray-50 space-y-1">
                        <!-- Loaded via JS -->
                    </div>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-blue-600 mb-1 uppercase">Pivot (Raggruppa per)</label>
                    <select id="filter-pivot" class="w-full p-2 bg-blue-50 border border-blue-200 rounded-lg text-sm font-bold text-blue-700 focus:ring-2 focus:ring-blue-400 outline-none transition" onchange="updateStats()">
                        <option value="">Nessuno</option>
                        <option value="asa_score">ASA Score</option>
                        <option value="sesso">Sesso</option>
                        <option value="urgenza">Urgenza</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Chart Area -->
        <div class="bg-white p-4 md:p-6 rounded-xl shadow-sm border border-gray-200">
            <h3 id="chart-title" class="font-bold text-gray-700 mb-4 flex items-center gap-2 uppercase tracking-wide text-sm">
                <i class="ph ph-chart-line"></i> Andamento Clinico
            </h3>
            <div class="h-[400px] w-full">
                <canvas id="statsChart"></canvas>
            </div>
        </div>

        <!-- Table Area -->
        <div class="bg-white p-4 md:p-6 rounded-xl shadow-sm border border-gray-200">
            <h3 class="font-bold text-gray-700 mb-4 flex items-center gap-2 uppercase tracking-wide text-sm">
                <i class="ph ph-table"></i> Dettaglio Rilevazioni
            </h3>
            <div class="overflow-y-auto max-h-[500px] rounded-lg border border-gray-100">
                <table class="w-full text-left border-collapse">
                    <thead class="sticky top-0 bg-gray-50 shadow-sm z-10">
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
    </main>

    <script>
        const rawData = <?php echo json_encode($data); ?>;
        const encryptionKey = sessionStorage.getItem('encryption_key');

        const STATIC_PARAMS = ['eta', 'bmi', 'peso', 'altezza', 'asa_score', 'euroscore_ii', 'durata_cec_ore', 'timing_iot_h'];

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
            opt.text = decrypt(row.nome_cognome);
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
                opt.text = row.tipo_intervento;
                iSelect.appendChild(opt);
            });
        }

        let chart = null;
        let phaseOrder = ["PRE_SBT","SBT","ESTUBAZIONE","T0","T30","POST_2H","POST_6H","POST_12H"];

        async function init() {
            const res = await fetch('api.php?action=app_settings&key=phase_order');
            const data = await res.json();
            if (data.value) {
                try { phaseOrder = JSON.parse(data.value); } catch(e) {}
            }
            renderFaseFilters();
            updateStats();
        }

        function renderFaseFilters() {
            const container = document.getElementById('filter-fase-list');
            container.innerHTML = '';
            phaseOrder.forEach(fase => {
                const label = document.createElement('label');
                label.className = 'flex items-center gap-2 text-xs cursor-pointer hover:bg-gray-100 p-1 rounded transition';
                label.innerHTML = `
                    <input type="checkbox" value="${fase}" checked class="fase-checkbox" onchange="updateStats()">
                    <span>${fase}</span>
                `;
                container.appendChild(label);
            });
        }

        function changeBins(delta) {
            const input = document.getElementById('hist-bins');
            input.value = Math.max(1, Math.min(50, parseInt(input.value) + delta));
            updateStats();
        }

        function calculateStats(values) {
            if (values.length === 0) return null;
            const n = values.length;
            const sorted = [...values].sort((a, b) => a - b);
            const sum = values.reduce((a, b) => a + b, 0);
            const avg = sum / n;

            const sqDiff = values.map(v => Math.pow(v - avg, 2));
            const std = Math.sqrt(sqDiff.reduce((a, b) => a + b, 0) / n);

            const getQuartile = (q) => {
                const pos = (n - 1) * q;
                const base = Math.floor(pos);
                const rest = pos - base;
                if (sorted[base + 1] !== undefined) {
                    return sorted[base] + rest * (sorted[base + 1] - sorted[base]);
                } else {
                    return sorted[base];
                }
            };

            return {
                avg: avg.toFixed(2),
                min: Math.min(...values).toFixed(2),
                max: Math.max(...values).toFixed(2),
                std: std.toFixed(2),
                q1: getQuartile(0.25).toFixed(2),
                q2: getQuartile(0.50).toFixed(2),
                q3: getQuartile(0.75).toFixed(2),
                count: n
            };
        }

        function updateStats() {
            const pId = document.getElementById('filter-paziente').value;
            const iId = document.getElementById('filter-intervento').value;
            const param = document.getElementById('filter-param').value;
            const isStatic = STATIC_PARAMS.includes(param);
            const pivot = document.getElementById('filter-pivot').value;

            // Phase filters
            const selectedFasi = Array.from(document.querySelectorAll('.fase-checkbox:checked')).map(cb => cb.value);
            document.getElementById('container-filter-fase').classList.toggle('hidden', isStatic);
            document.getElementById('histogram-controls').classList.toggle('hidden', !isStatic);

            // Filtering logic
            let dataToProcess = [];
            if (isStatic) {
                // For static, we usually want one record per patient or per intervention
                // If we filter by patient, take latest intervention
                // If no patient filter, take all interventions (or all patients if it's patient level)
                const patientLevel = ['eta', 'bmi', 'peso', 'altezza'].includes(param);
                if (patientLevel) {
                    const uniqueP = [...new Set(rawData.map(r => r.paziente_id))];
                    dataToProcess = uniqueP.map(pid => rawData.find(r => r.paziente_id === pid));
                } else {
                    const uniqueI = [...new Set(rawData.map(r => r.intervento_id))];
                    dataToProcess = uniqueI.map(iid => rawData.find(r => r.intervento_id === iid));
                }

                if (pId) dataToProcess = dataToProcess.filter(r => r.paziente_id == pId);
                if (iId) dataToProcess = dataToProcess.filter(r => r.intervento_id == iId);
            } else {
                dataToProcess = rawData.filter(r => {
                    return (!pId || r.paziente_id == pId) &&
                           (!iId || r.intervento_id == iId) &&
                           (selectedFasi.includes(r.fase));
                });
            }

            const values = dataToProcess.map(r => parseFloat(r[param])).filter(v => !isNaN(v));
            const stats = calculateStats(values);

            if (stats) {
                Object.keys(stats).forEach(k => {
                    document.getElementById('stat-' + k).innerText = stats[k];
                });
            } else {
                ['avg', 'min', 'max', 'std', 'q1', 'q2', 'q3', 'count'].forEach(k => {
                    document.getElementById('stat-' + k).innerText = '-';
                });
            }

            // Update Table
            document.getElementById('table-param-header').innerText = document.getElementById('filter-param').selectedOptions[0].text;
            const tbody = document.getElementById('stats-table-body');
            tbody.innerHTML = '';

            const tableData = [...dataToProcess].sort((a, b) => new Date(b.data_ora) - new Date(a.data_ora));
            tableData.forEach(r => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-gray-50 transition';
                const date = new Date(r.data_ora);
                const dateStr = `${date.getDate().toString().padStart(2, '0')}/${(date.getMonth()+1).toString().padStart(2, '0')} ${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`;
                tr.innerHTML = `
                    <td class="p-3 text-gray-500 font-mono">${dateStr}</td>
                    <td class="p-3 font-medium">${decrypt(r.nome_cognome)}</td>
                    <td class="p-3"><span class="px-2 py-1 bg-gray-100 rounded text-[10px] font-bold">${isStatic ? '-' : r.fase}</span></td>
                    <td class="p-3 text-right font-bold text-purple-600">${r[param] ?? '-'}</td>
                `;
                tbody.appendChild(tr);
            });

            // Chart Title
            document.getElementById('chart-title').innerHTML = isStatic ?
                `<i class="ph ph-chart-bar"></i> Distribuzione (Istogramma)` :
                `<i class="ph ph-chart-line"></i> Andamento Clinico`;

            // Update Chart
            const ctx = document.getElementById('statsChart').getContext('2d');
            if (chart) chart.destroy();

            if (isStatic) {
                renderHistogram(ctx, values, param);
            } else {
                renderTrend(ctx, dataToProcess, param, pivot);
            }
        }

        function renderHistogram(ctx, values, param) {
            if (values.length === 0) return;

            // Automatic Bins calculation (Sturges' Rule)
            let binsCount = parseInt(document.getElementById('hist-bins').value);
            if (!document.getElementById('hist-bins')._manual) {
                binsCount = Math.ceil(Math.log2(values.length) + 1);
                document.getElementById('hist-bins').value = binsCount;
            }

            const min = Math.min(...values);
            const max = Math.max(...values);
            const range = max - min;
            const binSize = range / binsCount;

            const bins = Array(binsCount).fill(0);
            const labels = [];

            for (let i = 0; i < binsCount; i++) {
                const start = min + (i * binSize);
                const end = min + ((i + 1) * binSize);
                labels.push(`${start.toFixed(1)}-${end.toFixed(1)}`);
            }

            values.forEach(v => {
                let idx = Math.floor((v - min) / binSize);
                if (idx === binsCount) idx--; // include max in last bin
                bins[idx]++;
            });

            chart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Frequenza',
                        data: bins,
                        backgroundColor: 'rgba(126, 34, 206, 0.6)',
                        borderColor: '#7e22ce',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true, grid: { color: '#f3f4f6' } },
                        x: { grid: { display: false }, ticks: { font: { size: 10 } } }
                    },
                    plugins: { legend: { display: false } }
                }
            });
        }

        document.getElementById('hist-bins').addEventListener('input', () => {
            document.getElementById('hist-bins')._manual = true;
        });

        function renderTrend(ctx, data, param, pivot) {
            // Group data by Intervention (or Pivot)
            let datasets = [];
            const colors = ['#7e22ce', '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#6366f1', '#ec4899', '#14b8a6'];

            // X-axis will be the phases in order
            const labels = phaseOrder;

            if (pivot) {
                // Group by pivot value (ASA score, sesso, urgenza)
                const grouped = {};
                data.forEach(r => {
                    let val = r[pivot];
                    if (pivot === 'urgenza') val = val == 1 ? 'Urgente' : 'Programmato';
                    if (!grouped[val]) grouped[val] = [];
                    grouped[val].push(r);
                });

                Object.keys(grouped).forEach((key, idx) => {
                    const groupData = grouped[key];
                    // Aggregate by phase
                    const phaseValues = labels.map(fase => {
                        const phaseData = groupData.filter(r => r.fase === fase);
                        if (phaseData.length === 0) return null;
                        const v = phaseData.map(r => parseFloat(r[param])).filter(v => !isNaN(v));
                        return v.length > 0 ? (v.reduce((a, b) => a + b, 0) / v.length) : null;
                    });

                    datasets.push({
                        label: `${pivot.toUpperCase()}: ${key}`,
                        data: phaseValues,
                        borderColor: colors[idx % colors.length],
                        backgroundColor: colors[idx % colors.length] + '20',
                        tension: 0.3,
                        fill: false,
                        pointRadius: 5,
                        spanGaps: true
                    });
                });
            } else {
                // If specific intervention is selected, just one line
                const iId = document.getElementById('filter-intervento').value;
                if (iId) {
                    const phaseValues = labels.map(fase => {
                        const r = data.find(r => r.fase === fase);
                        return r ? parseFloat(r[param]) : null;
                    });
                    datasets.push({
                        label: 'Intervento ' + iId,
                        data: phaseValues,
                        borderColor: '#7e22ce',
                        backgroundColor: 'rgba(126, 34, 206, 0.1)',
                        tension: 0.3,
                        fill: true,
                        pointRadius: 5,
                        spanGaps: true
                    });
                } else {
                    // Multiple interventions: one line per intervention
                    const uniqueI = [...new Set(data.map(r => r.intervento_id))];
                    uniqueI.forEach((iid, idx) => {
                        const intData = data.filter(r => r.intervento_id === iid);
                        const phaseValues = labels.map(fase => {
                            const r = intData.find(r => r.fase === fase);
                            return r ? parseFloat(r[param]) : null;
                        });
                        datasets.push({
                            label: 'Int. ' + iid,
                            data: phaseValues,
                            borderColor: colors[idx % colors.length],
                            backgroundColor: 'transparent',
                            borderWidth: 1.5,
                            tension: 0.3,
                            pointRadius: 3,
                            spanGaps: true
                        });
                    });

                    // Add average line
                    const avgValues = labels.map(fase => {
                        const phaseData = data.filter(r => r.fase === fase);
                        if (phaseData.length === 0) return null;
                        const v = phaseData.map(r => parseFloat(r[param])).filter(v => !isNaN(v));
                        return v.length > 0 ? (v.reduce((a, b) => a + b, 0) / v.length) : null;
                    });
                    datasets.push({
                        label: 'MEDIA',
                        data: avgValues,
                        borderColor: '#000',
                        borderWidth: 4,
                        backgroundColor: 'transparent',
                        tension: 0.3,
                        pointRadius: 6,
                        pointStyle: 'rectRot',
                        spanGaps: true,
                        zIndex: 10
                    });
                }
            }

            chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'bottom',
                            labels: { boxWidth: 12, font: { size: 10 } }
                        }
                    },
                    scales: {
                        y: { beginAtZero: false, grid: { color: '#f3f4f6' } },
                        x: { grid: { color: '#f3f4f6' }, ticks: { font: { size: 10, weight: 'bold' } } }
                    }
                }
            });
        }

        init();
    </script>
</body>
</html>
