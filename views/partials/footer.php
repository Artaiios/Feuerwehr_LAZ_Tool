    </main>

    <?php
    $footerOrgName = get_server_config('organization_name', 'LAZ Übungs-Tracker');
    $footerEmail = get_server_config('admin_email', '');
    ?>
    <footer class="bg-gray-100 border-t mt-12">
        <div class="max-w-7xl mx-auto px-4 py-4 text-center text-gray-400 text-xs">
            LAZ Übungs-Tracker v<?= APP_VERSION ?> · <?= e($footerOrgName) ?>
            <?php if ($footerEmail): ?>
                · <a href="mailto:<?= e($footerEmail) ?>" class="hover:text-gray-600 underline"><?= e($footerEmail) ?></a>
            <?php endif; ?>
        </div>
    </footer>

    <!-- Toast-Benachrichtigungen -->
    <div id="toast-container" class="fixed bottom-4 right-4 z-50 space-y-2"></div>

    <script>
    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        const colors = {
            success: 'bg-green-600',
            error: 'bg-red-600',
            warning: 'bg-yellow-500',
            info: 'bg-blue-600'
        };
        toast.className = `${colors[type] || colors.info} text-white px-5 py-3 rounded-xl shadow-xl text-sm font-medium transform transition-all duration-300 translate-x-full opacity-0 max-w-sm`;
        toast.textContent = message;
        container.appendChild(toast);
        requestAnimationFrame(() => {
            toast.classList.remove('translate-x-full', 'opacity-0');
        });
        setTimeout(() => {
            toast.classList.add('translate-x-full', 'opacity-0');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    async function apiCall(action, data = {}, adminToken = null) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('event_token', '<?= e($event['public_token']) ?>');
        formData.append('csrf_token', '<?= csrf_token() ?>');
        if (adminToken) formData.append('admin_token', adminToken);
        for (const [key, val] of Object.entries(data)) {
            formData.append(key, val);
        }
        try {
            const resp = await fetch('api.php', { method: 'POST', body: formData });
            const json = await resp.json();
            if (json.success) {
                showToast(json.message, 'success');
            } else {
                showToast(json.message || 'Fehler aufgetreten', 'error');
            }
            return json;
        } catch (e) {
            showToast('Netzwerkfehler: ' + e.message, 'error');
            return { success: false, message: e.message };
        }
    }
    </script>
</body>
</html>
