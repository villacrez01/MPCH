    <aside class="sidebar" id="sidebar" aria-label="Barra lateral">

        <div class="sidebar-banner">
            <div class="logo-container">
                <div class="logo-icon">
                    <i data-lucide="landmark" aria-hidden="true"></i>
                </div>
                <div class="logo-text-group">
                    <span class="logo-text">OTI</span>
                    <span class="logo-subtitle">Municipalidad</span>
                </div>
            </div>
        </div>

        <div class="sidebar-panel">

            <div class="user-info-card">
                <div class="user-info-row">
                    <div class="user-avatar"><?= strtoupper(substr(explode(' ', $userName)[0], 0, 1)) ?></div>
                    <div class="user-details">
                        <div class="user-name"><?= htmlspecialchars($userName) ?></div>
                        <div class="user-role">
                            <i data-lucide="badge-check" width="12" height="12" aria-hidden="true"></i>
                            <?= htmlspecialchars($roleName) ?>
                        </div>
                    </div>
                </div>
                <div class="office-info">
                    <div class="office-icon">
                        <i data-lucide="map-pin" width="16" height="16" aria-hidden="true"></i>
                    </div>
                    <div class="office-text">
                        <div class="office-label">Ubicación</div>
                        <div class="office-name"><?= htmlspecialchars($officeName) ?></div>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav" aria-label="Navegación principal">
            <?php if ($isOtiAdmin ?? false): ?>
            <div class="nav-section">
                <div class="nav-section-title">Administración</div>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="<?= $baseUrl ?>admin/dashboard" class="nav-link <?= $paginaActual === 'admin-dashboard' ? 'active' : '' ?>">
                            <i data-lucide="layout-dashboard" aria-hidden="true"></i>
                            <span class="nav-text">Panel Principal</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= $baseUrl ?>admin/tickets" class="nav-link <?= $paginaActual === 'admin-tickets' ? 'active' : '' ?>">
                            <i data-lucide="clipboard-list" aria-hidden="true"></i>
                            <span class="nav-text">Todos los Tickets</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= $baseUrl ?>admin/equipos" class="nav-link <?= $paginaActual === 'admin-equipos' ? 'active' : '' ?>">
                            <i data-lucide="monitor-smartphone" aria-hidden="true"></i>
                            <span class="nav-text">Inventario de Equipos</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= $baseUrl ?>admin/usuarios" class="nav-link <?= $paginaActual === 'admin-usuarios' ? 'active' : '' ?>">
                            <i data-lucide="users" aria-hidden="true"></i>
                            <span class="nav-text">Control de Usuarios</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= $baseUrl ?>admin/estructura" class="nav-link <?= $paginaActual === 'admin-estructura' ? 'active' : '' ?>">
                            <i data-lucide="building-2" aria-hidden="true"></i>
                            <span class="nav-text">Estructura Orgánica</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= $baseUrl ?>admin/analisis" class="nav-link <?= $paginaActual === 'admin-analisis' ? 'active' : '' ?>">
                            <i data-lucide="bar-chart-3" aria-hidden="true"></i>
                            <span class="nav-text">Análisis de Datos</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= $baseUrl ?>admin/resumen" class="nav-link <?= $paginaActual === 'admin-resumen' ? 'active' : '' ?>">
                            <i data-lucide="file-bar-chart" aria-hidden="true"></i>
                            <span class="nav-text">Mi Resumen</span>
                        </a>
                    </li>
                </ul>
            </div>
            <?php endif; ?>

            <div class="nav-section">
                <div class="nav-section-title">Usuario</div>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="<?= $baseUrl ?>user/dashboard" class="nav-link <?= $paginaActual === 'user-dashboard' ? 'active' : '' ?>">
                            <i data-lucide="home" aria-hidden="true"></i>
                            <span class="nav-text">Principal</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= $baseUrl ?>user/reportar" class="nav-link <?= $paginaActual === 'user-reportar' ? 'active' : '' ?>">
                            <i data-lucide="wrench" aria-hidden="true"></i>
                            <span class="nav-text">Reportar Incidencia</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= $baseUrl ?>user/tickets" class="nav-link <?= $paginaActual === 'user-tickets' ? 'active' : '' ?>">
                            <i data-lucide="ticket" aria-hidden="true"></i>
                            <span class="nav-text">Mis Tickets</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= $baseUrl ?>user/notificaciones" class="nav-link <?= $paginaActual === 'user-notificaciones' ? 'active' : '' ?>">
                            <i data-lucide="bell" aria-hidden="true"></i>
                            <span class="nav-text">Notificaciones</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= $baseUrl ?>user/profile" class="nav-link <?= $paginaActual === 'user-profile' ? 'active' : '' ?>">
                            <i data-lucide="user-circle" aria-hidden="true"></i>
                            <span class="nav-text">Mi Perfil</span>
                        </a>
                    </li>
                </ul>
            </div>
            </nav>
        </div>

        <div class="sidebar-footer">
            <a href="<?= $baseUrl ?>logout" class="logout-btn">
                <i data-lucide="log-out" aria-hidden="true"></i>
                <span>Cerrar Sesión</span>
            </a>
        </div>
    </aside>
