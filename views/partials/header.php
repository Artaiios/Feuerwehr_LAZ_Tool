<?php
/**
 * Gemeinsamer HTML-Header
 * Variablen: $event, $pageTitle (optional), $isAdmin (optional)
 */
$pageTitle = $pageTitle ?? $event['name'];
$isAdmin = $isAdmin ?? false;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> – LAZ Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="assets/style.css">
    <meta name="theme-color" content="#DC2626">
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Navigation -->
    <nav class="bg-gradient-to-r from-red-700 to-red-600 shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 py-3">
            <div class="flex items-center justify-between">
                <a href="index.php?event=<?= e($event['public_token']) ?><?= $isAdmin ? '&admin=' . e($event['admin_token']) : '' ?>"
                   class="flex items-center space-x-2 text-white font-bold text-lg hover:opacity-90 transition">
                    <span class="text-2xl">🔥</span>
                    <span class="hidden sm:inline"><?= e($event['name']) ?></span>
                    <span class="sm:hidden">LAZ</span>
                </a>
                <div class="flex items-center space-x-3">
                    <?php if ($isAdmin): ?>
                        <span class="bg-red-900 bg-opacity-50 text-white text-xs font-semibold px-3 py-1 rounded-full">
                            🔑 Admin
                        </span>
                    <?php endif; ?>
                    <span class="text-red-200 text-sm hidden md:inline">
                        <?= date('d.m.Y') ?>
                    </span>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 py-6">
