<?php
require_once 'auth.php';
if (!isLoggedIn()) { header('Location: index.php'); exit; }

$db = getDB();

// Fetch all data for stats
$sql = "SELECT p.id as paziente_id, p.nome_cognome, r.*, i.tipo_intervento, i.asa_score
        FROM pazienti p
        JOIN interventi i ON p.id = i.paziente_id
        JOIN rilevazioni_cliniche r ON i.id = r.intervento_id
        ORDER BY r.data_ora DESC";
$stmt = $db->query($sql);
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
</head>
<body class="bg-gray-50 min-h-screen">
    <nav class="bg-purple-600 text-white p-4 shadow-lg flex justify-between items-center">
        <h1 class="text-xl font-bold">Analisi Dati Clinici</h1>
        <a href="index.php" class="bg-white text-purple-600 px-3 py-1 rounded text-sm font-bold">Torna alla Dashboard</a>
    </nav>

    <main class="container mx-auto p-4">
        <div class="bg-white p-6 rounded-lg shadow mb-6">
            <h2 class="text-lg font-bold mb-4">Filtri e Pivot</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium">Paziente ID</label>
                    <select id="filter-paziente" class="w-full p-2 border rounded" onchange="updateStats()">
                        <option value="">Tutti i pazienti</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium">Fase</label>
                    <select id="filter-fase" class="w-full p-2 border rounded" onchange="updateStats()">
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
                <div>
                    <label class="block text-sm font-medium">Parametro Grafico</label>
                    <select id="filter-param" class="w-full p-2 border rounded" onchange="updateStats()">
                        <option value="tobin_index">Indice di Tobin</option>
                        <option value="rox_index">ROX Index</option>
                        <option value="fr">Freq. Respiratoria</option>
                        <option value="spo2">SpO2</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white p-6 rounded-lg shadow">
                <h3 class="font-bold mb-4">Andamento Temporale</h3>
                <canvas id="statsChart"></canvas>
            </div>
            <div class="bg-white p-6 rounded-lg shadow overflow-x-auto">
                <h3 class="font-bold mb-4">Dati Pivot</h3>
                <table class="w-full text-xs text-left">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="p-2">Data</th>
                            <th class="p-2">Paziente</th>
                            <th class="p-2">Fase</th>
                            <th class="p-2">Tobin</th>
                            <th class="p-2">ROX</th>
                        </tr>
                    </thead>
                    <tbody id="stats-table-body"></tbody>
                </table>
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
        const uniquePazienti = [...new Set(rawData.map(r => r.paziente_id))];
        uniquePazienti.forEach(id => {
            const row = rawData.find(r => r.paziente_id === id);
            const opt = document.createElement('option');
            opt.value = id;
            opt.text = `ID ${id} - ${decrypt(row.nome_cognome)}`;
            pSelect.appendChild(opt);
        });

        let chart = null;

        function updateStats() {
            const pId = document.getElementById('filter-paziente').value;
            const fase = document.getElementById('filter-fase').value;
            const param = document.getElementById('filter-param').value;

            const filtered = rawData.filter(r => {
                return (!pId || r.paziente_id == pId) && (!fase || r.fase == fase);
            }).sort((a, b) => new Date(a.data_ora) - new Date(b.data_ora));

            // Update Table
            const tbody = document.getElementById('stats-table-body');
            tbody.innerHTML = '';
            filtered.forEach(r => {
                const tr = document.createElement('tr');
                tr.className = 'border-b';
                tr.innerHTML = `
                    <td class="p-2">${new Date(r.data_ora).toLocaleString()}</td>
                    <td class="p-2">${decrypt(r.nome_cognome)}</td>
                    <td class="p-2">${r.fase}</td>
                    <td class="p-2">${r.tobin_index}</td>
                    <td class="p-2">${r.rox_index}</td>
                `;
                tbody.appendChild(tr);
            });

            // Update Chart
            const ctx = document.getElementById('statsChart').getContext('2d');
            if (chart) chart.destroy();

            chart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: filtered.map(r => new Date(r.data_ora).toLocaleString()),
                    datasets: [{
                        label: param.replace('_', ' ').toUpperCase(),
                        data: filtered.map(r => r[param]),
                        borderColor: 'rgb(147, 51, 234)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    scales: { y: { beginAtZero: false } }
                }
            });
        }

        updateStats();
    </script>
</body>
</html>
