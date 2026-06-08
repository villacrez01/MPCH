<?php
$baseUrl = base_url();
$userName = $_SESSION['user']['name'] ?? 'Usuario';
$userId = $_SESSION['user']['id'] ?? null;
$officeName = $_SESSION['user']['area_name'] ?? 'Sin oficina';
$roleName = $_SESSION['user']['role_name'] ?? 'Usuario';
$userEmail = $_SESSION['user']['email'] ?? '';

$isOtiAdmin = \App\Services\AuthService::isAdmin();

$tituloPagina = 'Mi Perfil - Sistema OTI';
$paginaActual = 'user-profile';

require_once __DIR__ . '/../../Models/User.php';
require_once __DIR__ . '/../../Models/Location.php';
require_once __DIR__ . '/../../Models/Equipment.php';

$userData = \App\Models\User::getUserProfile($userId);
$userLocation = \App\Models\Location::getById($userData['location_id'] ?? null);
$userEquipment = \App\Models\Equipment::getByUserId($userId);
?>
<?php require __DIR__ . '/../partials/head.php'; ?>
<style>
:root {
  --profile-radius: 16px;
  --profile-transition: 200ms cubic-bezier(0.4, 0, 0.2, 1);
}
.main-content { padding-bottom: 40px; }

.profile-header {
  display: flex; align-items: center; gap: 20px; margin-bottom: 32px;
  background: var(--bg-card); border-radius: var(--profile-radius);
  padding: 24px 28px; border: 1px solid var(--border-light);
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.profile-avatar {
  width: 72px; height: 72px; border-radius: 50%;
  background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%);
  display: flex; align-items: center; justify-content: center;
  font-size: 28px; font-weight: 700; color: #fff; flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(15,41,66,0.2);
}
.profile-header-info h1 { font-size: 22px; font-weight: 700; color: var(--text-primary); margin: 0; }
.profile-header-info p { font-size: 14px; color: var(--text-muted); margin: 4px 0 0 0; }

.profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.profile-card {
  background: var(--bg-card); border-radius: var(--profile-radius);
  border: 1px solid var(--border-light); padding: 24px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.profile-card.full { grid-column: span 2; }
.profile-card-title {
  font-size: 16px; font-weight: 600; color: var(--text-primary);
  margin-bottom: 20px; display: flex; align-items: center; gap: 10px;
}
.profile-card-title svg { width: 20px; height: 20px; fill: var(--primary); flex-shrink: 0; }

.form-group { margin-bottom: 18px; }
.form-label { display: block; font-size: 13px; font-weight: 500; color: var(--text-secondary); margin-bottom: 6px; }
.form-input {
  width: 100%; padding: 11px 14px; border: 1px solid var(--border-light);
  border-radius: 10px; font-size: 14px; color: var(--text-primary);
  background: var(--bg-main); box-sizing: border-box;
  transition: all var(--profile-transition); font-family: inherit;
}
.form-input:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-glow); background: var(--bg-card); }
.form-input:disabled { background: #f1f5f9; color: var(--text-muted); cursor: not-allowed; opacity: 0.7; }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

.alert {
  padding: 14px 18px; border-radius: 12px; margin-bottom: 20px;
  font-size: 14px; display: none; font-weight: 500;
}
.alert.success { background: var(--success-soft); color: #15803d; border: 1px solid #bbf7d0; }
.alert.error { background: var(--danger-soft); color: var(--danger); border: 1px solid #fecaca; }

.btn-primary {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 12px 24px; border-radius: 10px; border: none;
  background: var(--primary); color: #fff; font-weight: 600; font-size: 14px;
  cursor: pointer; transition: all var(--profile-transition);
  font-family: inherit;
}
.btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(15,41,66,0.2); }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; box-shadow: none; }

.btn-outline {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 12px 24px; border-radius: 10px;
  border: 1.5px solid var(--border-light);
  background: var(--bg-card); color: var(--text-secondary); font-weight: 600; font-size: 14px;
  cursor: pointer; transition: all var(--profile-transition);
  font-family: inherit;
}
.btn-outline:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-soft); }
.btn-outline:disabled { opacity: 0.6; cursor: not-allowed; }

.password-strength { height: 4px; background: var(--border-light); border-radius: 2px; margin-top: 8px; overflow: hidden; }
.password-strength-bar { height: 100%; width: 0; transition: width 300ms, background 300ms; border-radius: 2px; }
.password-strength-bar.weak { width: 33%; background: var(--danger); }
.password-strength-bar.medium { width: 66%; background: var(--warning); }
.password-strength-bar.strong { width: 100%; background: var(--success); }

.info-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 12px; }
.info-row:last-child { margin-bottom: 0; }
.info-item {
  display: flex; align-items: flex-start; gap: 12px;
  padding: 14px; background: var(--bg-main); border-radius: 12px;
}
.info-icon {
  width: 40px; height: 40px; border-radius: 10px;
  background: var(--primary-soft); display: flex;
  align-items: center; justify-content: center; flex-shrink: 0;
}
.info-icon svg { width: 20px; height: 20px; fill: var(--primary); }
.info-content { flex: 1; min-width: 0; }
.info-label { font-size: 12px; color: var(--text-muted); margin-bottom: 2px; }
.info-value { font-size: 14px; font-weight: 500; color: var(--text-primary); }

.equipment-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px; }
.equipment-card {
  background: var(--bg-main); border-radius: 12px; padding: 16px;
  border: 1px solid var(--border-light);
  transition: all var(--profile-transition);
}
.equipment-card:hover { border-color: var(--primary); box-shadow: 0 4px 12px rgba(15,41,66,0.08); }
.equipment-header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
.equipment-icon {
  width: 40px; height: 40px; border-radius: 10px;
  background: var(--primary-soft); display: flex;
  align-items: center; justify-content: center; flex-shrink: 0;
}
.equipment-icon svg { width: 20px; height: 20px; fill: var(--primary); }
.equipment-name { font-size: 14px; font-weight: 600; color: var(--text-primary); }
.equipment-code { font-size: 12px; color: var(--text-muted); }
.equipment-details { display: flex; flex-direction: column; gap: 8px; }
.equipment-detail { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--text-secondary); }
.equipment-detail svg { width: 14px; height: 14px; fill: var(--text-muted); flex-shrink: 0; }
.equipment-status {
  display: inline-flex; padding: 4px 10px; border-radius: 20px;
  font-size: 11px; font-weight: 500; align-self: flex-start;
}
.equipment-status.active { background: var(--success-soft); color: #15803d; }
.equipment-status.inactive { background: var(--danger-soft); color: var(--danger); }
.equipment-status.maintenance { background: var(--warning-soft); color: #b45309; }
.equipment-status.retired { background: #f1f5f9; color: #64748b; }

.no-equipment { text-align: center; padding: 40px 20px; }
.no-equipment svg { width: 48px; height: 48px; fill: var(--text-muted); opacity: 0.4; margin-bottom: 12px; }
.no-equipment div { color: var(--text-muted); font-size: 14px; }

@media (max-width: 1024px) {
  .main-content { margin-left: 0; padding: 16px; }
  .profile-grid { grid-template-columns: 1fr; }
  .profile-card.full { grid-column: span 1; }
  .form-row { grid-template-columns: 1fr; }
  .info-row { grid-template-columns: 1fr; }
}
@media (max-width: 640px) {
  .profile-header { flex-direction: column; text-align: center; padding: 20px; }
}
</style>
<?php require __DIR__ . '/../partials/sidebar.php'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>

<main id="main-content" class="main-content">
  <div class="profile-header">
    <div class="profile-avatar"><?= strtoupper(substr(explode(' ', $userName)[0], 0, 1)) ?></div>
    <div class="profile-header-info">
      <h1><?= htmlspecialchars($userName) ?></h1>
      <p><?= htmlspecialchars($roleName) ?> &middot; <?= htmlspecialchars($officeName) ?></p>
    </div>
  </div>

  <div id="alert" class="alert"></div>

  <div class="profile-grid">
    <div class="profile-card">
      <div class="profile-card-title">
        <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
        Informaci&oacute;n Personal
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label" for="nombre">Nombres</label>
          <input type="text" class="form-input" id="nombre" value="<?= htmlspecialchars($userData['nombre'] ?? '') ?>" disabled>
        </div>
        <div class="form-group">
          <label class="form-label" for="apellidos">Apellidos</label>
          <input type="text" class="form-input" id="apellidos" value="<?= htmlspecialchars($userData['apellidos'] ?? '') ?>" disabled>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="email">Correo Electr&oacute;nico</label>
        <input type="email" class="form-input" id="email" value="<?= htmlspecialchars($userData['email'] ?? '') ?>" aria-required="true" autocomplete="email">
      </div>

      <div class="form-group">
        <label class="form-label" for="telefono">Tel&eacute;fono</label>
        <input type="tel" class="form-input" id="telefono" value="<?= htmlspecialchars($userData['phone'] ?? '') ?>" aria-required="true" autocomplete="tel">
      </div>

      <div class="form-group">
        <label class="form-label" for="dni">DNI</label>
        <input type="text" class="form-input" id="dni" value="<?= htmlspecialchars($userData['dni'] ?? '') ?>" disabled>
      </div>

      <button class="btn-primary" id="btn-guardar-perfil" onclick="guardarPerfil()">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
        Guardar Cambios
      </button>
    </div>

    <div class="profile-card">
      <div class="profile-card-title">
        <svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
        Cambiar Contrase&ntilde;a
      </div>

      <div class="form-group">
        <label class="form-label" for="password-actual">Contrase&ntilde;a Actual</label>
        <input type="password" class="form-input" id="password-actual" placeholder="Ingresa tu contrase&ntilde;a actual" aria-required="true" autocomplete="current-password">
      </div>

      <div class="form-group">
        <label class="form-label" for="password-nueva">Nueva Contrase&ntilde;a</label>
        <input type="password" class="form-input" id="password-nueva" placeholder="Ingresa tu nueva contrase&ntilde;a" onkeyup="verificarFortaleza()" aria-required="true" autocomplete="new-password">
        <div class="password-strength">
          <div class="password-strength-bar" id="password-strength-bar"></div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="password-confirmar">Confirmar Nueva Contrase&ntilde;a</label>
        <input type="password" class="form-input" id="password-confirmar" placeholder="Confirma tu nueva contrase&ntilde;a" aria-required="true" autocomplete="new-password">
      </div>

      <button class="btn-outline" id="btn-cambiar-password" onclick="cambiarContrasena()">
        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
        Cambiar Contrase&ntilde;a
      </button>
    </div>

    <div class="profile-card full">
      <div class="profile-card-title">
        <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
        Informaci&oacute;n de Ubicaci&oacute;n
      </div>

      <div class="info-row">
        <div class="info-item">
          <div class="info-icon">
            <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/></svg>
          </div>
          <div class="info-content">
            <div class="info-label">Sede</div>
            <div class="info-value"><?= htmlspecialchars($userLocation['sede_name'] ?? 'No asignada') ?></div>
          </div>
        </div>
        <div class="info-item">
          <div class="info-icon">
            <svg viewBox="0 0 24 24"><path d="M12 2l-5.5 9h11z"/><path d="M17.5 17.5c0 1.38-1.12 2.5-2.5 2.5s-2.5-1.12-2.5-2.5 1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5z"/></svg>
          </div>
          <div class="info-content">
            <div class="info-label">&Aacute;rea</div>
            <div class="info-value"><?= htmlspecialchars($userLocation['area_name'] ?? 'No asignada') ?></div>
          </div>
        </div>
        <div class="info-item">
          <div class="info-icon">
            <svg viewBox="0 0 24 24"><path d="M20 18c1.1 0 1.99-.9 1.99-2L22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2H0v2h24v-2h-4z"/></svg>
          </div>
          <div class="info-content">
            <div class="info-label">Oficina</div>
            <div class="info-value"><?= htmlspecialchars($userLocation['name'] ?? 'No asignada') ?></div>
          </div>
        </div>
      </div>

      <div class="info-row">
        <div class="info-item">
          <div class="info-icon">
            <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
          </div>
          <div class="info-content">
            <div class="info-label">C&oacute;digo</div>
            <div class="info-value"><?= htmlspecialchars($userData['user_number'] ?? '-') ?></div>
          </div>
        </div>
        <div class="info-item">
          <div class="info-icon">
            <svg viewBox="0 0 24 24"><path d="M12 7V3H2v18h20V7H12z"/></svg>
          </div>
          <div class="info-content">
            <div class="info-label">Sede</div>
            <div class="info-value"><?= htmlspecialchars($userLocation['sede_name'] ?? $userLocation['name'] ?? 'No asignada') ?></div>
          </div>
        </div>
        <div class="info-item">
          <div class="info-icon">
            <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
          </div>
          <div class="info-content">
            <div class="info-label">Estado</div>
            <div class="info-value"><?= ($userData['activo'] ?? false) ? 'Activo' : 'Inactivo' ?></div>
          </div>
        </div>
      </div>
    </div>

    <div class="profile-card full">
      <div class="profile-card-title">
        <svg viewBox="0 0 24 24"><path d="M20 18c1.1 0 1.99-.9 1.99-2L22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2H0v2h24v-2h-4zM4 6h16v10H4V6z"/></svg>
        Mis Dispositivos Asignados
      </div>

      <?php if (empty($userEquipment)): ?>
      <div class="no-equipment">
        <svg viewBox="0 0 24 24"><path d="M20 18c1.1 0 1.99-.9 1.99-2L22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2H0v2h24v-2h-4zM4 6h16v10H4V6z"/></svg>
        <div>No tienes dispositivos asignados</div>
      </div>
      <?php else: ?>
      <div class="equipment-grid">
        <?php foreach ($userEquipment as $equip): ?>
        <div class="equipment-card">
          <div class="equipment-header">
            <div class="equipment-icon">
              <svg viewBox="0 0 24 24"><path d="M20 18c1.1 0 1.99-.9 1.99-2L22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2H0v2h24v-2h-4z"/></svg>
            </div>
            <div>
              <div class="equipment-name"><?= htmlspecialchars($equip['name']) ?></div>
              <div class="equipment-code"><?= htmlspecialchars($equip['patrimonial_code'] ?? $equip['serial_number'] ?? 'Sin c&oacute;digo') ?></div>
            </div>
          </div>
          <div class="equipment-details">
            <div class="equipment-detail">
              <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/></svg>
              <span><?= htmlspecialchars($equip['location_name'] ?? 'Sin ubicaci&oacute;n') ?></span>
            </div>
            <div class="equipment-detail">
              <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z"/></svg>
              <span><?= htmlspecialchars($equip['serial_number'] ?? 'Sin serie') ?></span>
            </div>
            <span class="equipment-status <?= strtolower($equip['status'] ?? 'active') ?>"><?= htmlspecialchars(match ($equip['status'] ?? '') { 'active' => 'Activo', 'inactive' => 'Inactivo', 'maintenance' => 'Mantenimiento', 'retired' => 'Retirado', default => 'Activo' }) ?></span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</main>

<script>
var BASE_URL = window.location.origin + '/OTI/';

function showAlert(message, type) {
  var alert = document.getElementById('alert');
  alert.textContent = message;
  alert.className = 'alert ' + type;
  alert.style.display = 'block';
  setTimeout(function() { alert.style.display = 'none'; }, 5000);
}

function verificarFortaleza() {
  var password = document.getElementById('password-nueva').value;
  var bar = document.getElementById('password-strength-bar');
  bar.className = 'password-strength-bar';
  if (password.length === 0) return;
  if (password.length < 6) { bar.classList.add('weak'); }
  else if (password.length < 10 || !/[A-Z]/.test(password) || !/[0-9]/.test(password)) { bar.classList.add('medium'); }
  else { bar.classList.add('strong'); }
}

async function guardarPerfil() {
  var btn = document.getElementById('btn-guardar-perfil');
  var email = document.getElementById('email').value.trim();
  var telefono = document.getElementById('telefono').value.trim();

  if (!email) { showAlert('El correo electr&oacute;nico es obligatorio', 'error'); return; }

  btn.disabled = true;
  btn.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M12 4V2A10 10 0 0 0 2 12h2a8 8 0 0 1 8-8z"/><path d="M12 2v2a8 8 0 0 1 8 8h2A10 10 0 0 0 12 2z" opacity="0.3"/></svg> Guardando...';

  try {
    var res = await fetch(BASE_URL + 'app/api/profile.php?action=update-profile', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ email: email, telefono: telefono })
    });
    var result = await res.json();
    if (result.success) { showAlert('Perfil actualizado correctamente', 'success'); }
    else { showAlert(result.message || 'Error al actualizar perfil', 'error'); }
  } catch (e) { showAlert('Error de conexi&oacute;n', 'error'); }
  finally {
    btn.disabled = false;
    btn.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg> Guardar Cambios';
  }
}

async function cambiarContrasena() {
  var btn = document.getElementById('btn-cambiar-password');
  var actual = document.getElementById('password-actual').value;
  var nueva = document.getElementById('password-nueva').value;
  var confirmar = document.getElementById('password-confirmar').value;

  if (!actual || !nueva || !confirmar) { showAlert('Todos los campos son obligatorios', 'error'); return; }
  if (nueva !== confirmar) { showAlert('Las contrase&ntilde;as no coinciden', 'error'); return; }
  if (nueva.length < 6) { showAlert('La contrase&ntilde;a debe tener al menos 6 caracteres', 'error'); return; }

  btn.disabled = true;
  btn.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M12 4V2A10 10 0 0 0 2 12h2a8 8 0 0 1 8-8z"/><path d="M12 2v2a8 8 0 0 1 8 8h2A10 10 0 0 0 12 2z" opacity="0.3"/></svg> Cambiando...';

  try {
    var res = await fetch(BASE_URL + 'app/api/profile.php?action=change-password', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ actual: actual, nueva: nueva })
    });
    var result = await res.json();
    if (result.success) {
      showAlert('Contrase&ntilde;a cambiada correctamente', 'success');
      document.getElementById('password-actual').value = '';
      document.getElementById('password-nueva').value = '';
      document.getElementById('password-confirmar').value = '';
      document.getElementById('password-strength-bar').className = 'password-strength-bar';
    } else { showAlert(result.message || 'Error al cambiar contrase&ntilde;a', 'error'); }
  } catch (e) { showAlert('Error de conexi&oacute;n', 'error'); }
  finally {
    btn.disabled = false;
    btn.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg> Cambiar Contrase&ntilde;a';
  }
}
</script>
<?php require __DIR__ . '/../partials/footer.php'; ?>
