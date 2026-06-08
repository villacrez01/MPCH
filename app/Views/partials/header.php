    <header class="header-bar">
        <div class="header-left">
            <?php if ($isOtiAdmin ?? false): ?>
            <a href="<?= $baseUrl ?>admin/dashboard" class="switch-view-btn <?= $paginaActual === 'admin-dashboard' ? 'active' : '' ?>">
                <i data-lucide="shield" aria-hidden="true"></i>
                Administración
            </a>
            <?php endif; ?>
            <a href="<?= $baseUrl ?>user/dashboard" class="switch-view-btn <?= $paginaActual === 'user-dashboard' ? 'active' : '' ?>">
                <i data-lucide="home" aria-hidden="true"></i>
                Usuario
            </a>
            <button type="button" class="sidebar-catcher" id="sidebarCollapseBtn" aria-label="Contraer o expandir panel" title="Panel" aria-expanded="true">
                <i data-lucide="panel-left" id="sidebarCollapseIcon" aria-hidden="true"></i>
            </button>
        </div>
        <div class="header-right">
            <div class="realtime-indicator">
                <span class="realtime-dot"></span>
                <span>Tiempo real</span>
            </div>
            <button type="button" class="notif-btn" onclick="toggleNotifications()" aria-label="Ver notificaciones">
                <i data-lucide="bell" aria-hidden="true"></i>
                <span class="notif-badge" id="notif-badge" aria-label="0 notificaciones">0</span>
            </button>
            <button type="button" class="profile-btn" onclick="toggleProfileMenu()" aria-label="Mi perfil" title="Mi Perfil">
                <i data-lucide="user-round" aria-hidden="true"></i>
            </button>
            <div class="profile-dropdown" id="profile-dropdown">
                <div class="profile-header">
                    <div class="profile-avatar"><?= strtoupper(substr(explode(' ', $userName)[0], 0, 1)) ?></div>
                    <div class="profile-info">
                        <div class="profile-name"><?= htmlspecialchars($userName) ?></div>
                        <div class="profile-role"><?= htmlspecialchars($roleName) ?></div>
                    </div>
                </div>
                <div class="profile-menu">
                    <a href="<?= $baseUrl ?>user/profile" class="profile-menu-item">
                        <i data-lucide="user-circle" aria-hidden="true"></i>
                        Mi Perfil
                    </a>
                    <a href="<?= $baseUrl ?>user/profile" class="profile-menu-item">
                        <i data-lucide="settings" aria-hidden="true"></i>
                        Configuración de Perfil
                    </a>
                    <div class="profile-divider"></div>
                    <a href="<?= $baseUrl ?>logout" class="profile-menu-item logout">
                        <i data-lucide="log-out" aria-hidden="true"></i>
                        Cerrar Sesión
                    </a>
                </div>
            </div>
        </div>
    </header>

    <script>
    (function () {
        'use strict';

        function setCollapseIcon(collapsed) {
            var iconEl = document.getElementById('sidebarCollapseIcon');
            if (!iconEl) return;
            iconEl.setAttribute('data-lucide', collapsed ? 'panel-left-open' : 'panel-left');
            if (window.OTI && window.OTI.refreshIcons) {
                window.OTI.refreshIcons(iconEl.parentElement || document);
            }
        }

        function toggleSidebarCollapsed() {
            var sidebar = document.getElementById('sidebar');
            var btn = document.getElementById('sidebarCollapseBtn');
            if (!sidebar) return;

            var collapsed = sidebar.classList.toggle('collapsed');
            if (btn) btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            setCollapseIcon(collapsed);

            try {
                localStorage.setItem('oti-sidebar-collapsed', collapsed ? '1' : '0');
            } catch (e) {}
        }

        document.addEventListener('DOMContentLoaded', function () {
            var sidebar = document.getElementById('sidebar');
            var btn = document.getElementById('sidebarCollapseBtn');
            if (!sidebar || !btn) return;

            var saved = null;
            try { saved = localStorage.getItem('oti-sidebar-collapsed'); } catch (e) {}
            if (saved === '1' && window.innerWidth > 1024) {
                sidebar.classList.add('collapsed');
                btn.setAttribute('aria-expanded', 'false');
                setCollapseIcon(true);
            }

            btn.addEventListener('click', toggleSidebarCollapsed);
        });
    })();
    </script>
