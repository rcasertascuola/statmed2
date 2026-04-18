<?php
require_once 'auth.php';
if (!isAdmin()) { header('Location: index.php'); exit; }
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
        <h1 class="text-xl font-bold flex items-center gap-2">
            <i class="ph ph-gear"></i> Impostazioni Cliniche
        </h1>
        <a href="index.php" class="bg-white text-gray-800 px-4 py-1 rounded font-bold text-sm">Dashboard</a>
    </nav>

    <main class="container mx-auto p-4 md:p-8">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-lg font-bold mb-6 text-gray-700 border-b pb-2">Configurazione Range Clinici</h2>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-gray-100 text-xs uppercase text-gray-600">
                            <th class="p-3">Parametro</th>
                            <th class="p-3">Unità</th>
                            <th class="p-3 text-green-600">Min Normal</th>
                            <th class="p-3 text-green-600">Max Normal</th>
                            <th class="p-3 text-red-600">Min Critical</th>
                            <th class="p-3 text-red-600">Max Critical</th>
                            <th class="p-3">Step</th>
                            <th class="p-3 text-center">Azioni</th>
                        </tr>
                    </thead>
                    <tbody id="ranges-body">
                        <!-- Data loaded via JS -->
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-8 bg-blue-50 border border-blue-200 p-4 rounded-lg">
            <h3 class="font-bold text-blue-800 mb-2">Manutenzione</h3>
            <p class="text-sm text-blue-700 mb-4">Puoi ricalcolare i range basandoti sui dati attualmente presenti nel database. Questa operazione sovrascriverà i valori attuali.</p>
            <button onclick="recalculateRanges()" class="bg-blue-600 text-white px-4 py-2 rounded-lg font-bold hover:bg-blue-700 transition">
                Ricalcola dai dati esistenti
            </button>
        </div>
    </main>

    <script>
        async function loadRanges() {
            const res = await fetch('api.php?action=ranges');
            const ranges = await res.json();
            const tbody = document.getElementById('ranges-body');
            tbody.innerHTML = '';

            ranges.forEach(r => {
                const tr = document.createElement('tr');
                tr.className = 'border-b hover:bg-gray-50';
                tr.innerHTML = `
                    <td class="p-3 font-bold text-gray-700">${r.parameter}</td>
                    <td class="p-3"><input type="text" value="${r.unit}" id="unit-${r.parameter}" class="w-16 p-1 border rounded text-sm"></td>
                    <td class="p-3"><input type="number" step="0.01" value="${r.min_normal}" id="minn-${r.parameter}" class="w-20 p-1 border rounded text-sm bg-green-50"></td>
                    <td class="p-3"><input type="number" step="0.01" value="${r.max_normal}" id="maxn-${r.parameter}" class="w-20 p-1 border rounded text-sm bg-green-50"></td>
                    <td class="p-3"><input type="number" step="0.01" value="${r.min_critical}" id="minc-${r.parameter}" class="w-20 p-1 border rounded text-sm bg-red-50"></td>
                    <td class="p-3"><input type="number" step="0.01" value="${r.max_critical}" id="maxc-${r.parameter}" class="w-20 p-1 border rounded text-sm bg-red-50"></td>
                    <td class="p-3"><input type="number" step="0.001" value="${r.step}" id="step-${r.parameter}" class="w-16 p-1 border rounded text-sm"></td>
                    <td class="p-3 text-center">
                        <button onclick="saveRange('${r.parameter}')" class="text-blue-600 hover:text-blue-800 transition p-2">
                            <i class="ph ph-floppy-disk text-2xl"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        async function saveRange(param) {
            const data = {
                parameter: param,
                unit: document.getElementById(`unit-${param}`).value,
                min_normal: document.getElementById(`minn-${param}`).value,
                max_normal: document.getElementById(`maxn-${param}`).value,
                min_critical: document.getElementById(`minc-${param}`).value,
                max_critical: document.getElementById(`maxc-${param}`).value,
                step: document.getElementById(`step-${param}`).value
            };

            const res = await fetch('api.php?action=ranges', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            if (res.ok) {
                alert(`Range per ${param} salvato con successo!`);
            } else {
                alert('Errore durante il salvataggio.');
            }
        }

        async function recalculateRanges() {
            if (confirm('Sei sicuro? I valori manuali verranno sovrascritti dai calcoli statistici.')) {
                const res = await fetch('init_ranges.php?run=1');
                if (res.ok) {
                    alert('Ricalcolo completato!');
                    loadRanges();
                }
            }
        }

        loadRanges();
    </script>
</body>
</html>
