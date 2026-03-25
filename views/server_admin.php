<?php
/**
 * Server-Admin Ansicht
 */

$tab = $_GET['tab'] ?? 'overview';
$orgName = get_server_config('organization_name', 'LAZ Übungs-Tracker');
$showOverview = get_server_config('show_public_overview', '0') === '1';
$allEvents = get_all_events();
$baseUrl = get_base_url();
$pageTitle = 'Server-Admin';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔑 Server-Admin – <?= e($orgName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="bg-gray-50 min-h-screen">
    <nav class="bg-gradient-to-r from-gray-800 to-gray-700 shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-3">
            <div class="flex items-center justify-between">
                <a href="admin.php?token=<?= e($serverAdminToken) ?>" class="flex items-center space-x-2 text-white font-bold text-lg">
                    <span class="text-2xl">🔑</span>
                    <span class="hidden sm:inline"><?= e($orgName) ?></span>
                    <span class="sm:hidden">Server-Admin</span>
                </a>
                <span class="bg-gray-900 bg-opacity-50 text-white text-xs font-semibold px-3 py-1 rounded-full">
                    Server-Admin
                </span>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6">

    <!-- Tabs -->
    <div class="mb-6 flex flex-wrap gap-2 border-b pb-3">
        <?php
        $tabs = [
            'overview' => '📊 Events',
            'create' => '➕ Neues Event',
            'settings' => '⚙️ Einstellungen',
            'audit' => '📝 Audit-Log',
        ];
        foreach ($tabs as $key => $label):
            $active = $tab === $key;
            $url = 'admin.php?token=' . e($serverAdminToken) . '&tab=' . $key;
        ?>
        <a href="<?= $url ?>"
           class="px-3 py-2 rounded-lg text-sm font-medium transition <?= $active ? 'bg-gray-800 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>">
            <?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>

<?php
// ══════════════════════════════════════════════════════════════
// Tab: Übersicht
// ══════════════════════════════════════════════════════════════
if ($tab === 'overview'):
    $eventStats = get_event_stats_overview();
?>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border p-4 text-center">
        <div class="text-2xl font-bold text-gray-900"><?= count($allEvents) ?></div>
        <div class="text-gray-500 text-sm">Events gesamt</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-4 text-center">
        <div class="text-2xl font-bold text-green-600"><?= count(array_filter($allEvents, fn($e) => $e['status'] === 'active')) ?></div>
        <div class="text-gray-500 text-sm">Aktive Events</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-4 text-center">
        <?php $totalMembers = array_sum(array_column($eventStats, 'member_count')); ?>
        <div class="text-2xl font-bold text-gray-900"><?= $totalMembers ?></div>
        <div class="text-gray-500 text-sm">Teilnehmer gesamt</div>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-4 text-center">
        <?php $totalPenalties = array_sum(array_column($eventStats, 'total_penalties')); ?>
        <div class="text-2xl font-bold text-red-600"><?= format_currency($totalPenalties) ?></div>
        <div class="text-gray-500 text-sm">Strafkasse gesamt</div>
    </div>
</div>

<?php if (empty($eventStats)): ?>
    <div class="bg-white rounded-xl shadow-sm border p-8 text-center text-gray-400">
        <div class="text-4xl mb-2">📋</div>
        <p>Noch keine Events erstellt.</p>
        <a href="admin.php?token=<?= e($serverAdminToken) ?>&tab=create" class="inline-block mt-4 bg-gray-800 text-white px-6 py-2 rounded-lg font-semibold hover:bg-gray-900 transition">
            ➕ Erstes Event erstellen
        </a>
    </div>
<?php else: ?>
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="px-5 py-4 border-b" style="background-color: #e5e7eb;">
            <h3 class="font-bold text-gray-700">Alle Events</h3>
        </div>
        <div class="divide-y">
            <?php foreach ($eventStats as $ev):
                $evPublicUrl = $baseUrl . '/index.php?event=' . $ev['public_token'];
                $evAdminUrl = $baseUrl . '/index.php?event=' . $ev['public_token'] . '&admin=' . $ev['admin_token'];
            ?>
            <div class="px-5 py-4">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-bold text-gray-900"><?= e($ev['name']) ?></span>
                            <?php if ($ev['status'] === 'active'): ?>
                                <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-semibold">Aktiv</span>
                            <?php else: ?>
                                <span class="text-xs bg-gray-200 text-gray-500 px-2 py-0.5 rounded-full">Archiviert</span>
                            <?php endif; ?>
                        </div>
                        <div class="flex gap-4 mt-1 text-xs text-gray-500">
                            <span>👥 <?= $ev['member_count'] ?> Teilnehmer</span>
                            <span>📅 <?= $ev['session_count'] ?> Termine</span>
                            <span>✅ <?= $ev['total_present'] ?> Teilnahmen</span>
                            <?php if ($ev['total_penalties'] > 0): ?>
                                <span class="text-red-500">💰 <?= format_currency($ev['total_penalties']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="mt-2 space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-400 w-16 shrink-0">🌐 Public:</span>
                                <input type="text" readonly value="<?= e($evPublicUrl) ?>"
                                       class="flex-1 min-w-0 text-xs p-1 bg-gray-50 border rounded font-mono text-gray-600" onclick="this.select()">
                                <a href="<?= e($evPublicUrl) ?>" target="_blank" class="text-xs text-blue-500 hover:text-blue-700 shrink-0">↗</a>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-gray-400 w-16 shrink-0">🔑 Admin:</span>
                                <input type="text" readonly value="<?= e($evAdminUrl) ?>"
                                       class="flex-1 min-w-0 text-xs p-1 bg-gray-50 border rounded font-mono text-gray-600" onclick="this.select()">
                                <a href="<?= e($evAdminUrl) ?>" target="_blank" class="text-xs text-red-500 hover:text-red-700 shrink-0">↗</a>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-2 shrink-0">
                        <button onclick="confirmDeleteEvent(<?= $ev['id'] ?>, '<?= e(addslashes($ev['name'])) ?>')"
                                class="text-xs bg-red-50 text-red-400 px-3 py-1.5 rounded-lg font-medium hover:bg-red-100 transition">🗑️ Löschen</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php
// ══════════════════════════════════════════════════════════════
// Tab: Event erstellen
// ══════════════════════════════════════════════════════════════
elseif ($tab === 'create'):
?>
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <h3 class="font-bold text-gray-800 text-lg mb-6">Neues Event erstellen</h3>
        <div>
            <div class="space-y-5">
                <div>
                    <label class="text-sm font-semibold text-gray-700">Event-Name *</label>
                    <input type="text" id="newEventName" placeholder="z.B. LAZ Bronze 2026"
                           class="w-full border rounded-lg p-2.5 text-sm mt-1 focus:ring-2 focus:ring-gray-500 focus:border-gray-500">
                </div>

                <div>
                    <label class="text-sm font-semibold text-gray-700">Organisationsname (optional)</label>
                    <input type="text" id="newOrgName" placeholder="Leer = globaler Standard (<?= e($orgName) ?>)"
                           class="w-full border rounded-lg p-2.5 text-sm mt-1">
                    <p class="text-xs text-gray-400 mt-1">Überschreibt den globalen Organisationsnamen nur für dieses Event.</p>
                </div>

                <hr>
                <h4 class="font-semibold text-gray-700">Hauptfrist (Abnahme / Finale)</h4>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs text-gray-500">Datum *</label>
                        <input type="date" id="newD2Date" required class="w-full border rounded-lg p-2 text-sm mt-1">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Mindest-Teilnahmen *</label>
                        <input type="number" id="newD2Count" value="20" min="1" class="w-full border rounded-lg p-2 text-sm mt-1">
                    </div>
                </div>

                <div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="newD1Enabled" class="rounded" onchange="document.getElementById('d1Fields').classList.toggle('hidden', !this.checked)">
                        <span class="text-sm font-semibold text-gray-700">Zwischenziel (Frist 1) aktivieren</span>
                    </label>
                </div>
                <div id="d1Fields" class="hidden">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs text-gray-500">Datum Zwischenziel</label>
                            <input type="date" id="newD1Date" class="w-full border rounded-lg p-2 text-sm mt-1">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500">Mindest-Teilnahmen</label>
                            <input type="number" id="newD1Count" value="11" min="1" class="w-full border rounded-lg p-2 text-sm mt-1">
                        </div>
                    </div>
                </div>

                <hr>
                <h4 class="font-semibold text-gray-700">Strafenkatalog</h4>
                <div class="space-y-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="penaltySource" value="default" checked class="text-gray-800">
                        <span class="text-sm text-gray-700">Standard-Strafenkatalog verwenden</span>
                    </label>
                    <?php if (!empty($allEvents)): ?>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="penaltySource" value="copy" class="text-gray-800"
                               onchange="document.getElementById('copySource').classList.toggle('hidden', !this.checked)">
                        <span class="text-sm text-gray-700">Von bestehendem Event kopieren</span>
                    </label>
                    <div id="copySource" class="hidden ml-6">
                        <select id="copyFromEvent" class="border rounded-lg p-2 text-sm w-full">
                            <?php foreach ($allEvents as $ev): ?>
                            <option value="<?= $ev['id'] ?>"><?= e($ev['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="penaltySource" value="none" class="text-gray-800">
                        <span class="text-sm text-gray-700">Leerer Katalog (später manuell anlegen)</span>
                    </label>
                    <?php endif; ?>
                </div>

                <button type="button" onclick="try{createNewEvent();}catch(err){alert('Fehler: '+err.message);}" class="w-full bg-gray-800 text-white py-3 rounded-xl font-bold hover:bg-gray-900 transition">
                    ➕ Event erstellen
                </button>
            </div>
        </div>
        <div id="createResult" class="mt-4 hidden"></div>
    </div>
</div>

<?php
// ══════════════════════════════════════════════════════════════
// Tab: Einstellungen
// ══════════════════════════════════════════════════════════════
elseif ($tab === 'settings'):
    $adminEmail = get_server_config('admin_email', '');
?>
<div class="max-w-2xl mx-auto space-y-6">
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <h3 class="font-bold text-gray-800 mb-4">Globale Einstellungen</h3>
        <div class="space-y-4">
            <div>
                <label class="text-sm font-semibold text-gray-700">Organisationsname</label>
                <input type="text" id="cfgOrgName" value="<?= e($orgName) ?>"
                       class="w-full border rounded-lg p-2.5 text-sm mt-1">
                <p class="text-xs text-gray-400 mt-1">Wird auf allen Seiten angezeigt, sofern ein Event keinen eigenen Namen hat.</p>
            </div>
            <div>
                <label class="text-sm font-semibold text-gray-700">Administrator E-Mail</label>
                <input type="email" id="cfgAdminEmail" value="<?= e($adminEmail) ?>" placeholder="admin@beispiel.de"
                       class="w-full border rounded-lg p-2.5 text-sm mt-1">
                <p class="text-xs text-gray-400 mt-1">Wird im Footer und auf Fehlerseiten als Kontaktadresse angezeigt.</p>
            </div>
            <div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" id="cfgShowOverview" <?= $showOverview ? 'checked' : '' ?> class="rounded">
                    <span class="text-sm text-gray-700">Öffentliche Startseite mit Event-Übersicht anzeigen</span>
                </label>
                <p class="text-xs text-gray-400 mt-1">Wenn aktiviert, zeigt <code>index.php</code> ohne Event-Token eine Liste aller aktiven Events.</p>
            </div>
            <button type="button" onclick="try{saveServerSettings();}catch(err){alert(err.message);}" class="w-full bg-gray-800 text-white py-2.5 rounded-xl font-bold hover:bg-gray-900 transition">
                💾 Speichern
            </button>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-6">
        <h3 class="font-bold text-gray-800 mb-2">🔑 Server-Admin URL</h3>
        <input type="text" readonly value="<?= e($baseUrl . '/admin.php?token=' . $serverAdminToken) ?>"
               class="w-full text-xs p-2 bg-gray-50 border rounded font-mono" onclick="this.select()">
    </div>
</div>

<?php
// ══════════════════════════════════════════════════════════════
// Tab: Audit-Log
// ══════════════════════════════════════════════════════════════
elseif ($tab === 'audit'):
    $logs = get_global_audit_log(300);
?>
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="px-5 py-4 border-b flex items-center justify-between">
        <h3 class="font-bold text-gray-800">Globales Audit-Log</h3>
        <span class="text-xs text-gray-400"><?= count($logs) ?> Einträge</span>
    </div>
    <div class="divide-y max-h-screen overflow-y-auto text-sm">
        <?php if (empty($logs)): ?>
            <div class="px-5 py-8 text-center text-gray-400">Noch keine Log-Einträge.</div>
        <?php endif; ?>
        <?php foreach ($logs as $log): ?>
        <div class="px-5 py-2.5">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <span class="inline-block bg-gray-100 text-gray-600 text-xs px-2 py-0.5 rounded font-mono"><?= e($log['action_type']) ?></span>
                    <span class="text-xs text-blue-600 ml-1"><?= e($log['event_name'] ?? '–') ?></span>
                    <?php if ($log['member_name']): ?>
                        <span class="text-xs text-gray-400 ml-1">· <?= e($log['member_name']) ?></span>
                    <?php endif; ?>
                    <div class="text-gray-700 mt-0.5"><?= e($log['action_description']) ?></div>
                </div>
                <div class="text-xs text-gray-400 whitespace-nowrap"><?= format_datetime($log['created_at']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php endif; ?>

    </main>

    <footer class="bg-gray-100 border-t mt-12">
        <div class="max-w-7xl mx-auto px-4 py-4 text-center text-gray-400 text-xs">
            LAZ Übungs-Tracker v<?= APP_VERSION ?> · Server-Administration
        </div>
    </footer>

    <div id="toast-container" class="fixed bottom-4 right-4 z-50 space-y-2"></div>

    <script>
    // Global Error Handler – zeigt JS-Fehler als Toast
    window.onerror = function(msg, url, line, col, error) {
        var container = document.getElementById('toast-container');
        if (container) {
            var toast = document.createElement('div');
            toast.className = 'bg-red-600 text-white px-5 py-3 rounded-xl shadow-xl text-sm font-medium max-w-sm';
            toast.textContent = 'JS-Fehler: ' + msg + ' (Zeile ' + line + ')';
            container.appendChild(toast);
            setTimeout(function() { toast.remove(); }, 8000);
        }
        console.error('JS Error:', msg, url, line, col, error);
        return false;
    };

    function showToast(message, type) {
        type = type || 'success';
        var container = document.getElementById('toast-container');
        var toast = document.createElement('div');
        var colors = { success: 'bg-green-600', error: 'bg-red-600', warning: 'bg-yellow-500', info: 'bg-blue-600' };
        toast.className = (colors[type] || colors.info) + ' text-white px-5 py-3 rounded-xl shadow-xl text-sm font-medium transform transition-all duration-300 translate-x-full opacity-0 max-w-sm';
        toast.textContent = message;
        container.appendChild(toast);
        requestAnimationFrame(function() { toast.classList.remove('translate-x-full', 'opacity-0'); });
        setTimeout(function() { toast.classList.add('translate-x-full', 'opacity-0'); setTimeout(function() { toast.remove(); }, 300); }, 4000);
    }

    async function serverApi(action, data) {
        data = data || {};
        var formData = new FormData();
        formData.append('action', action);
        formData.append('server_token', '<?= e($serverAdminToken) ?>');
        formData.append('csrf_token', '<?= csrf_token() ?>');
        var keys = Object.keys(data);
        for (var i = 0; i < keys.length; i++) {
            formData.append(keys[i], data[keys[i]]);
        }
        try {
            var resp = await fetch('api.php', { method: 'POST', body: formData });
            var text = await resp.text();
            var json;
            try { json = JSON.parse(text); } catch(pe) {
                showToast('Server-Antwort ungültig (HTTP ' + resp.status + '): ' + text.substring(0, 100), 'error');
                console.error('Response:', text);
                return { success: false };
            }
            if (json.success) { showToast(json.message, 'success'); }
            else { showToast(json.message || 'Unbekannter Fehler', 'error'); }
            return json;
        } catch (err) {
            showToast('Netzwerkfehler: ' + err.message, 'error');
            console.error('serverApi error:', err);
            return { success: false };
        }
    }

    async function createNewEvent() {
        try {
            var nameEl = document.getElementById('newEventName');
            var d2DateEl = document.getElementById('newD2Date');

            if (!nameEl) { alert('Fehler: Feld newEventName nicht gefunden'); return; }
            if (!d2DateEl) { alert('Fehler: Feld newD2Date nicht gefunden'); return; }

            var name = nameEl.value.trim();
            var d2Date = d2DateEl.value;
            if (!name) { showToast('Bitte einen Event-Namen eingeben.', 'warning'); return; }
            if (!d2Date) { showToast('Bitte ein Hauptfrist-Datum angeben.', 'warning'); return; }

            var penaltyEl = document.querySelector('input[name="penaltySource"]:checked');
            var penaltySource = penaltyEl ? penaltyEl.value : 'default';

            var orgNameEl = document.getElementById('newOrgName');
            var d2CountEl = document.getElementById('newD2Count');
            var d1EnabledEl = document.getElementById('newD1Enabled');
            var d1DateEl = document.getElementById('newD1Date');
            var d1CountEl = document.getElementById('newD1Count');
            var copyFromEl = document.getElementById('copyFromEvent');

            var data = {
                name: name,
                organization_name: orgNameEl ? orgNameEl.value : '',
                deadline_2_date: d2Date,
                deadline_2_count: d2CountEl ? d2CountEl.value : '20',
                deadline_1_enabled: (d1EnabledEl && d1EnabledEl.checked) ? '1' : '0',
                deadline_1_date: d1DateEl ? d1DateEl.value : '',
                deadline_1_count: d1CountEl ? d1CountEl.value : '11',
                penalty_source: penaltySource,
                copy_from_event: (penaltySource === 'copy' && copyFromEl) ? copyFromEl.value : ''
            };

            var r = await serverApi('create_event', data);
            if (r && r.success) {
                var div = document.getElementById('createResult');
                if (div) {
                    div.classList.remove('hidden');
                    div.innerHTML = '<div class="bg-green-50 border border-green-200 rounded-lg p-4">' +
                        '<p class="text-green-800 font-semibold mb-2">✅ Event erstellt!</p>' +
                        '<div class="space-y-2 text-xs">' +
                        '<div><label class="font-semibold text-green-700">🌐 Öffentlich:</label><br>' +
                        '<input type="text" readonly value="' + r.public_url + '" class="w-full p-1 border rounded font-mono" onclick="this.select()"></div>' +
                        '<div><label class="font-semibold text-green-700">🔑 Event-Admin:</label><br>' +
                        '<input type="text" readonly value="' + r.admin_url + '" class="w-full p-1 border rounded font-mono" onclick="this.select()"></div>' +
                        '</div></div>';
                }
            }
        } catch(err) {
            alert('createNewEvent Fehler: ' + err.message);
            console.error(err);
        }
    }

    async function confirmDeleteEvent(id, name) {
        if (!confirm('Event "' + name + '" wirklich löschen? Alle Daten werden unwiderruflich gelöscht!')) return;
        if (!confirm('LETZTE WARNUNG: Fortfahren?')) return;
        var r = await serverApi('delete_event', { event_id: id });
        if (r.success) { setTimeout(function() { location.reload(); }, 800); }
    }

    async function saveServerSettings() {
        try {
            var orgEl = document.getElementById('cfgOrgName');
            var emailEl = document.getElementById('cfgAdminEmail');
            var overviewEl = document.getElementById('cfgShowOverview');

            await serverApi('save_server_settings', {
                organization_name: orgEl ? orgEl.value : '',
                admin_email: emailEl ? emailEl.value : '',
                show_public_overview: (overviewEl && overviewEl.checked) ? '1' : '0'
            });
        } catch(err) {
            alert('saveServerSettings Fehler: ' + err.message);
        }
    }

    // Radio-Toggle für Strafenkatalog-Quelle
    var radios = document.querySelectorAll('input[name="penaltySource"]');
    for (var i = 0; i < radios.length; i++) {
        radios[i].addEventListener('change', function() {
            var copyDiv = document.getElementById('copySource');
            if (copyDiv) { copyDiv.classList.toggle('hidden', this.value !== 'copy'); }
        });
    }
    </script>
</body>
</html>
