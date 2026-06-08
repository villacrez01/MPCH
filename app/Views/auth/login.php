<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Acceso al Sistema — OTI Municipal</title>
    <meta name="description" content="Portal de acceso exclusivo para personal de la OTI Municipal.">
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" href="<?= BASE_URL ?>public/assets/img/bg-municipal.jpg" as="image" imagesrcset="" imagesizes="100vw">
    <link rel="preload" href="https://fonts.gstatic.com/s/outfit/v19/QGYyz_MVcCNPc_UW3Z7H.woff2" as="font" type="font/woff2" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>public/assets/css/login.css">
</head>
<body>

<!-- ══════════════════════════════════════════════ -->
<!--   Background — bg-municipal.jpg reads first   -->
<!-- ══════════════════════════════════════════════ -->
<main id="main-content">

    <!-- ── Login Card ── -->
    <div class="login-wrapper">

        <div class="login-card" role="main">

            <!-- ── Brand Header ── -->
            <header class="login-card-header">
                <div class="login-emblem">
                    <img class="login-emblem-img"
                         src="<?= BASE_URL ?>public/assets/img/OTI.jpeg"
                         alt="Escudo OTI"
                         width="72" height="72">
                </div>
                <div class="login-badge">
                    <span class="login-badge-main">Municipalidad</span>
                    <span class="login-badge-sub">Oficina de Tecnologías de la Información</span>
                </div>
            </header>

            <!-- ── Title Block ── -->
            <div class="login-title-block">
                <h1 class="login-title">Acceso al Sistema</h1>
                <p class="login-subtitle">Ingrese sus credenciales asignadas por el administrador</p>
                <div class="login-accent-line" aria-hidden="true"></div>
            </div>

            <!-- ── Alerts ── -->
            <?php if (!empty($_SESSION['error'])): ?>
                <div class="login-alert login-alert--error" role="alert">
                    <svg class="login-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <span class="login-alert-text"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($_SESSION['success'])): ?>
                <div class="login-alert login-alert--success" role="status">
                    <svg class="login-alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    <span class="login-alert-text"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></span>
                </div>
            <?php endif; ?>

            <!-- ── Login Form ── -->
            <form id="loginForm" method="POST" action="<?= BASE_URL ?>login" autocomplete="off" novalidate>
                <input type="hidden" name="_token" value="<?= csrf_token() ?>">

                <!-- Usuario / Correo -->
                <div class="login-field">
                    <label for="username" class="login-label">Usuario / Correo Electrónico</label>
                    <div class="login-input-wrap">
                        <svg class="login-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                        <input
                            type="text"
                            id="username"
                            name="identifier"
                            class="login-input"
                            placeholder="usuario@municipalidad.gob"
                            autocomplete="username"
                            required
                            aria-required="true"
                            autocapitalize="none"
                            spellcheck="false"
                        >
                    </div>
                </div>

                <!-- Contraseña -->
                <div class="login-field">
                    <label for="password" class="login-label">Contraseña</label>
                    <div class="login-input-wrap">
                        <svg class="login-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="login-input"
                            placeholder="Contraseña asignada"
                            autocomplete="current-password"
                            required
                            aria-required="true"
                        >
                        <button type="button"
                                class="login-pw-toggle"
                                id="togglePass"
                                aria-label="Mostrar contraseña"
                                tabindex="-1">
                            <svg class="login-pw-toggle-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Submit -->
                <button type="submit" class="login-submit" id="loginBtn">
                    <svg class="login-submit-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <span>Ingresar al Sistema</span>
                </button>
            </form>

            <!-- ── Support link ── -->
            <div class="login-support">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
                <a href="mailto:soporte@oti.gob">¿Problemas con el acceso? Contacte soporte</a>
            </div>

            <!-- ── Trust badges ── -->
            <div class="login-badges" aria-label="Características del sistema">
                <span class="login-badge-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                    Acceso cifrado
                </span>
                <span class="login-badge-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    Solo personal autorizado
                </span>
            </div>

        </div><!-- /.login-card -->
    </div><!-- /.login-wrapper -->

    <!-- ── Footer ── -->
    <footer class="login-footer">
        &copy; 2026 OTI — Municipalidad · Sistema de Gestión de Tickets
    </footer>

</main>

<!-- ── Inline Scripts ── -->
<script>
(function () {
    'use strict';

    var toggleBtn = document.getElementById('togglePass');
    var passInput  = document.getElementById('password');

    if (toggleBtn && passInput) {
        toggleBtn.addEventListener('click', function () {
            var isPassword = passInput.type === 'password';
            passInput.type = isPassword ? 'text' : 'password';
            toggleBtn.setAttribute('aria-label', isPassword ? 'Ocultar contraseña' : 'Mostrar contraseña');
        });
    }

    var form    = document.getElementById('loginForm');
    var loginBtn = document.getElementById('loginBtn');

    if (form && loginBtn) {
        form.addEventListener('submit', function () {
            loginBtn.classList.add('is-loading');
            loginBtn.querySelector('span').textContent = 'Validando acceso\u2026';
            loginBtn.disabled = true;
            if (toggleBtn) toggleBtn.disabled = true;
        });
    }
})();
</script>
</body>
</html>
