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
        <!-- Tab Navigation -->
        <div class="flex border-b mb-6 overflow-x-auto">
            <button onclick="switchTab('ranges-tab')" id="btn-ranges-tab" class="px-6 py-2 font-bold text-sm transition border-b-2 border-blue-600 text-blue-600">RANGE CLINICI</button>
            <button onclick="switchTab('tags-tab')" id="btn-tags-tab" class="px-6 py-2 font-bold text-sm transition text-gray-500 border-b-2 border-transparent">LIBRERIA TAG</button>
        </div>

        <!-- Range Settings Tab -->
        <div id="ranges-tab" class="tab-content">
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <div id="range-categories" class="space-y-8">
                    <!-- Categories will be rendered here -->
                </div>
            </div>

            <div class="bg-blue-50 border border-blue-200 p-4 rounded-lg">
                <h3 class="font-bold text-blue-800 mb-2">Manutenzione</h3>
                <p class="text-sm text-blue-700 mb-4">Puoi ricalcolare i range basandoti sui dati attualmente presenti nel database. Questa operazione sovrascriverà i valori attuali.</p>
                <button onclick="recalculateRanges()" class="bg-blue-600 text-white px-4 py-2 rounded-lg font-bold hover:bg-blue-700 transition">
                    Ricalcola dai dati esistenti
                </button>
            </div>
        </div>

        <!-- Tag Library Tab -->
        <div id="tags-tab" class="tab-content hidden">
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-lg font-bold text-gray-700">Gestione Tag</h2>
                    <div class="flex gap-2">
                        <select id="tag-category-select" class="p-2 border rounded text-sm">
                            <option value="tipo_intervento">Tipo Intervento</option>
                            <option value="comorbilita">Comorbidità</option>
                            <option value="maschera_venturi">Maschera Venturi</option>
                            <option value="hfno">HFNO</option>
                            <option value="niv">NIV</option>
                            <option value="tipo_post_estubazione">Post-Estubazione</option>
                        </select>
                        <input type="text" id="new-tag-name" placeholder="Nuovo tag..." class="p-2 border rounded text-sm">
                        <button onclick="addTag()" class="bg-green-600 text-white px-4 py-2 rounded font-bold hover:bg-green-700">Aggiungi</button>
                    </div>
                </div>

                <div id="tags-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Tags by category will be rendered here -->
                </div>
            </div>
        </div>
    </main>

    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.getElementById(tabId).classList.remove('hidden');

            document.querySelectorAll('nav + main > div > button').forEach(el => {
                el.classList.remove('text-blue-600', 'border-blue-600');
                el.classList.add('text-gray-500', 'border-transparent');
            });
            document.getElementById('btn-' + tabId).classList.add('text-blue-600', 'border-blue-600');
            document.getElementById('btn-' + tabId).classList.remove('text-gray-500', 'border-transparent');

            if (tabId === 'tags-tab') loadTags();
            if (tabId === 'ranges-tab') loadRanges();
        }

        async function loadRanges() {
            const res = await fetch('api.php?action=ranges');
            const ranges = await res.json();
            const container = document.getElementById('range-categories');
            container.innerHTML = '';

            const categories = {
                'rilevazioni': 'Rilevazioni Cliniche',
                'interventi': 'Parametri Intervento',
                'esiti': 'Parametri Esito'
            };

            for (const [cat, label] of Object.entries(categories)) {
                const catRanges = ranges.filter(r => r.category === cat);
                if (catRanges.length === 0) continue;

                const section = document.createElement('div');
                section.innerHTML = `
                    <h3 class="text-md font-bold text-gray-600 mb-3 border-l-4 border-blue-500 pl-2 uppercase">${label}</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse mb-6">
                            <thead>
                                <tr class="bg-gray-50 text-[10px] uppercase text-gray-500">
                                    <th class="p-3">Parametro</th>
                                    <th class="p-3">Unità</th>
                                    <th class="p-3 text-green-600">Min Normal</th>
                                    <th class="p-3 text-green-600">Max Normal</th>
                                    <th class="p-3 text-red-600">Min Critical</th>
                                    <th class="p-3 text-red-600">Max Critical</th>
                                    <th class="p-3">Step</th>
                                    <th class="p-3 text-center">Salva</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${catRanges.map(r => `
                                    <tr class="border-b hover:bg-gray-50">
                                        <td class="p-3 font-bold text-gray-700 text-sm">${r.parameter}</td>
                                        <td class="p-2"><input type="text" value="${r.unit}" id="unit-${r.parameter}" class="w-full p-1 border rounded text-xs"></td>
                                        <td class="p-2"><input type="number" step="0.01" value="${r.min_normal}" id="minn-${r.parameter}" class="w-full p-1 border rounded text-xs bg-green-50"></td>
                                        <td class="p-2"><input type="number" step="0.01" value="${r.max_normal}" id="maxn-${r.parameter}" class="w-full p-1 border rounded text-xs bg-green-50"></td>
                                        <td class="p-2"><input type="number" step="0.01" value="${r.min_critical}" id="minc-${r.parameter}" class="w-full p-1 border rounded text-xs bg-red-50"></td>
                                        <td class="p-2"><input type="number" step="0.01" value="${r.max_critical}" id="maxc-${r.parameter}" class="w-full p-1 border rounded text-xs bg-red-50"></td>
                                        <td class="p-2"><input type="number" step="0.001" value="${r.step}" id="step-${r.parameter}" class="w-full p-1 border rounded text-xs"></td>
                                        <td class="p-2 text-center">
                                            <button onclick="saveRange('${r.parameter}')" class="text-blue-600 hover:text-blue-800 p-1">
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

        // Tag Management
        async function loadTags() {
            const res = await fetch('api.php?action=tags');
            const tags = await res.json();
            const container = document.getElementById('tags-container');
            container.innerHTML = '';

            const grouped = tags.reduce((acc, tag) => {
                acc[tag.category] = acc[tag.category] || [];
                acc[tag.category].push(tag);
                return acc;
            }, {});

            for (const [cat, catTags] of Object.entries(grouped)) {
                const card = document.createElement('div');
                card.className = 'bg-gray-50 p-4 rounded-lg border';
                card.innerHTML = `
                    <h3 class="font-bold text-gray-700 mb-3 border-b pb-1 uppercase text-xs">${cat.replace(/_/g, ' ')}</h3>
                    <div class="flex flex-wrap gap-2">
                        ${catTags.map(t => `
                            <span class="bg-white border px-2 py-1 rounded text-xs flex items-center gap-1">
                                ${t.name}
                                <button onclick="deleteTag(${t.id})" class="text-red-500 hover:text-red-700">
                                    <i class="ph ph-x-circle"></i>
                                </button>
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

            await fetch('api.php?action=tags', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ category, name })
            });
            document.getElementById('new-tag-name').value = '';
            loadTags();
        }

        async function deleteTag(id) {
            if (confirm('Eliminare questo tag?')) {
                await fetch(`api.php?action=tags&id=${id}`, { method: 'DELETE' });
                loadTags();
            }
        }

        loadRanges();
    </script>
</body>
</html>
