    <script>
    function toggleProfileMenu() {
        const dropdown = document.getElementById('profile-dropdown');
        dropdown.classList.toggle('active');
        const notifDropdown = document.getElementById('notif-dropdown');
        if (notifDropdown) notifDropdown.classList.remove('active');
    }
    document.addEventListener('click', function(e) {
        const profileBtn = document.querySelector('.profile-btn');
        const profileDropdown = document.getElementById('profile-dropdown');
        if (profileBtn && profileDropdown && !profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
            profileDropdown.classList.remove('active');
        }
    });

    // CSRF token auto-refresh (every 30 min, before 1h expiration)
    (function() {
        function refreshCSRF() {
            var baseUrl = document.querySelector('script[src*="action-dd"]')?.src?.split('public')[0] || '';
            fetch(baseUrl + 'app/api/csrf_refresh.php')
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.token) {
                        var meta = document.querySelector('meta[name="csrf-token"]');
                        if (meta) meta.setAttribute('content', d.token);
                        window.__CSRF_TOKEN = d.token;
                    }
                })
                .catch(function() {});
        }
        refreshCSRF();
        setInterval(refreshCSRF, 30 * 60 * 1000);
    })();
    </script>
    <script src="<?= htmlspecialchars($baseUrl) ?>public/assets/js/oti-icons.js?v=20260606"></script>
    <script src="<?= htmlspecialchars($baseUrl) ?>public/assets/js/action-dd.js?v=2.0"></script>
    <script src="<?= htmlspecialchars($baseUrl) ?>public/assets/js/realtime.js"></script>
    <script src="<?= htmlspecialchars($baseUrl) ?>public/assets/js/rbac-button.js?v=rbac"></script>
</body>
</html>
