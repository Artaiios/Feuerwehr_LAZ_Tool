<?php
/**
 * Öffentliche Startseite – Event-Übersicht
 */

require_once __DIR__ . '/../db.php';

$orgName = get_server_config('organization_name', 'LAZ Übungs-Tracker');
$activeEvents = get_active_events();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔥 <?= e($orgName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/style.css">
    <meta name="theme-color" content="#DC2626">
</head>
<body class="bg-gray-50 min-h-screen">
    <nav class="bg-gradient-to-r from-red-700 to-red-600 shadow-lg">
        <div class="max-w-4xl mx-auto px-4 py-4">
            <div class="flex items-center space-x-2 text-white font-bold text-lg">
                <span class="text-2xl">🔥</span>
                <span><?= e($orgName) ?></span>
            </div>
        </div>
    </nav>

    <main class="max-w-4xl mx-auto px-4 py-8">
        <h1 class="text-2xl font-extrabold text-gray-900 mb-2">Aktuelle Übungszyklen</h1>
        <p class="text-gray-500 mb-8"><?= e($orgName) ?> – Übersicht aller aktiven Jahrgänge</p>

        <?php if (empty($activeEvents)): ?>
            <div class="bg-white rounded-xl shadow-sm border p-8 text-center text-gray-400">
                <div class="text-4xl mb-2">📋</div>
                <p>Derzeit keine aktiven Events.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($activeEvents as $ev):
                    $sessionCount = count(get_sessions($ev['id']));
                    $memberCount = count(get_members($ev['id']));
                    $evOrgName = !empty($ev['organization_name']) ? $ev['organization_name'] : $orgName;
                ?>
                <a href="index.php?event=<?= e($ev['public_token']) ?>"
                   class="block bg-white rounded-xl shadow-sm border p-5 hover:shadow-md hover:border-red-300 transition group">
                    <div class="flex items-start justify-between">
                        <div>
                            <h2 class="font-bold text-gray-900 group-hover:text-red-600 transition"><?= e($ev['name']) ?></h2>
                            <p class="text-sm text-gray-500 mt-0.5"><?= e($evOrgName) ?></p>
                        </div>
                        <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-semibold">Aktiv</span>
                    </div>
                    <div class="flex gap-4 mt-3 text-xs text-gray-400">
                        <span>👥 <?= $memberCount ?> Teilnehmer</span>
                        <span>📅 <?= $sessionCount ?> Termine</span>
                        <span>📆 bis <?= format_date($ev['deadline_2_date']) ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer class="bg-gray-100 border-t mt-12">
        <div class="max-w-4xl mx-auto px-4 py-4 text-center text-gray-400 text-xs">
            LAZ Übungs-Tracker v<?= APP_VERSION ?>
        </div>
    </footer>
</body>
</html>
