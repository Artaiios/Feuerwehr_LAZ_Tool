<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seite nicht gefunden – LAZ Tracker</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="text-center max-w-md">
        <div class="text-6xl mb-4">🚒</div>
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Seite nicht gefunden</h1>
        <p class="text-gray-500 mb-6"><?= e($errorMessage ?? 'Diese Seite existiert nicht oder der Link ist ungültig.') ?></p>
        <?php
        $errorEmail = '';
        try { $errorEmail = get_server_config('admin_email', ''); } catch (Exception $ex) {}
        ?>
        <?php if ($errorEmail): ?>
            <p class="text-gray-400 text-sm">Bitte überprüfe den Link oder kontaktiere den Administrator:<br>
            <a href="mailto:<?= e($errorEmail) ?>" class="text-red-600 hover:underline"><?= e($errorEmail) ?></a></p>
        <?php else: ?>
            <p class="text-gray-400 text-sm">Bitte überprüfe den Link oder kontaktiere den Administrator.</p>
        <?php endif; ?>
    </div>
</body>
</html>
