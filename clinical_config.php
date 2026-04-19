<?php
require_once 'auth.php';

if (!isAdmin() && !isLeader()) {
    header('Location: index.php');
    exit;
}

$db = getDB();
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurazione Clinica - StatMed2</title>
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
                    <a href="index.php" class="flex items-center gap-2 hover:text-blue-200 transition" title="Dashboard">
                        <i class="ph ph-gauge text-2xl"></i>
                        <img src="assets/logo_small.png" alt="Logo" class="h-8 w-auto">
                    </a>
                </div>

                <div class="flex items-center gap-2 font-bold uppercase tracking-wider text-sm md:text-base">
                    <i class="ph ph-stethoscope"></i>
                    <span>Config Clinica</span>
                </div>

                <div class="flex items-center">
                    <div class="hidden md:flex items-center gap-2">
                        <a href="pazienti.php" class="p-2 hover:bg-blue-700 rounded-full transition" title="Pazienti">
                            <i class="ph ph-users text-xl"></i>
                        </a>
                        <a href="stats.php" class="p-2 hover:bg-blue-700 rounded-full transition" title="Statistiche">
                            <i class="ph ph-chart-line-up text-xl"></i>
                        </a>
                        <a href="settings.php" class="p-2 hover:bg-blue-700 rounded-full transition" title="Impostazioni">
                            <i class="ph ph-gear text-xl"></i>
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
                <a href="pazienti.php" class="flex items-center gap-3 p-3 hover:bg-blue-800 rounded-lg transition">
                    <i class="ph ph-users text-xl"></i>
                    <span>Pazienti</span>
                </a>
                <a href="stats.php" class="flex items-center gap-3 p-3 hover:bg-blue-800 rounded-lg transition">
                    <i class="ph ph-chart-line-up text-xl"></i>
                    <span>Statistiche</span>
                </a>
                <a href="settings.php" class="flex items-center gap-3 p-3 hover:bg-blue-800 rounded-lg transition">
                    <i class="ph ph-gear text-xl"></i>
                    <span>Impostazioni</span>
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
        <div class="flex border-b mb-6 overflow-x-auto">
            <button onclick="openTab('ranges-tab')" id="btn-ranges-tab" class="tab-btn active px-4 py-2 text-sm font-medium text-gray-500 hover:text-blue-600 whitespace-nowrap">Range Clinici</button>
            <button onclick="openTab('tags-tab')" id="btn-tags-tab" class="tab-btn px-4 py-2 text-sm font-medium text-gray-500 hover:text-blue-600 whitespace-nowrap">Libreria Tag</button>
        </div>

        <!-- RANGES TAB -->
        <div id="ranges-tab" class="tab-content active space-y-6">
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
                    <h3 class="text-md font-bold text-gray-600 mb-3 border-l-4 border-blue-500 pl-2 uppercase">${label}</h3>
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
                                ${catRanges.map(r => `
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="p-3 font-bold text-gray-700">${r.parameter}</td>
                                        <td class="p-2"><input type="text" value="${r.unit || ''}" id="unit-${r.parameter}" class="w-full p-1 border rounded text-xs"></td>
                                        <td class="p-2"><input type="number" step="0.01" value="${r.min_normal}" id="minn-${r.parameter}" class="w-full p-1 border rounded text-xs bg-green-50"></td>
                                        <td class="p-2"><input type="number" step="0.01" value="${r.max_normal}" id="maxn-${r.parameter}" class="w-full p-1 border rounded text-xs bg-green-50"></td>
                                        <td class="p-2"><input type="number" step="0.01" value="${r.min_critical}" id="minc-${r.parameter}" class="w-full p-1 border rounded text-xs bg-red-50"></td>
                                        <td class="p-2"><input type="number" step="0.01" value="${r.max_critical}" id="maxc-${r.parameter}" class="w-full p-1 border rounded text-xs bg-red-50"></td>
                                        <td class="p-2 text-center">
                                            <button onclick="saveRange('${r.parameter}')" class="text-blue-600 hover:text-blue-800 p-1" title="Salva">
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
                unit: document.getElementById(`unit-${param}`).value,
                min_normal: document.getElementById(`minn-${param}`).value,
                max_normal: document.getElementById(`maxn-${param}`).value,
                min_critical: document.getElementById(`minc-${param}`).value,
                max_critical: document.getElementById(`maxc-${param}`).value
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
                    <h3 class="font-bold text-gray-700 mb-3 border-b pb-1 uppercase text-[10px] text-gray-500">${cat.replace(/_/g, ' ')}</h3>
                    <div class="flex flex-wrap gap-2">
                        ${catTags.map(t => `
                            <span class="bg-white border px-2 py-1 rounded text-xs flex items-center gap-1 shadow-sm">
                                <span>${t.name}</span>
                                <button onclick="renameTagPrompt(${t.id}, '${t.name.replace(/'/g, "\\'")}', '${cat}')" class="text-blue-500 hover:text-blue-700"><i class="ph ph-pencil-simple"></i></button>
                                <button onclick="deleteTag(${t.id})" class="text-red-400 hover:text-red-600"><i class="ph ph-trash"></i></button>
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
            if (confirm('Eliminare questo tag?')) { await fetch(`api.php?action=tags&id=${id}`, { method: 'DELETE' }); loadTags(); }
        }

        async function renameTagPrompt(id, oldName, category) {
            const newName = prompt(`Rinomina o unisci il tag "${oldName}":`, oldName);
            if (!newName || newName === oldName) return;
            const res = await fetch('api.php?action=rename_tag', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ old_name: oldName, new_name: newName, category: category }) });
            if (res.ok) loadTags(); else alert('Errore!');
        }

        // Initialize first tab
        loadRanges();
    </script>
</body>
</html>
