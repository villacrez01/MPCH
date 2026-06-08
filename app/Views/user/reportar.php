<?php
$baseUrl = base_url();
$userName = $_SESSION['user']['name'] ?? 'Usuario';
$userId = $_SESSION['user']['id'] ?? null;
$userEmail = $_SESSION['user']['email'] ?? '';
$userDni = $_SESSION['user']['dni'] ?? '';
$officeName = $_SESSION['user']['area_name'] ?? 'Sin oficina';
$roleName = $_SESSION['user']['role_name'] ?? 'Usuario';

$isOtiAdmin = \App\Services\AuthService::isAdmin();

$equipoId = $_GET['equipo_id'] ?? null;
$equiposAsignados = \App\Models\User::getAssignedEquipment($userId);

$equipoSeleccionado = null;
if ($equipoId) {
    foreach ($equiposAsignados as $eq) {
        if ((string)$eq['id'] === (string)$equipoId) {
            $equipoSeleccionado = $eq;
            break;
        }
    }
}

try {
    $pdo = \App\Core\Database::connect();
    $stmt = $pdo->query("SELECT id, name FROM oti.service_types ORDER BY id");
    $tipos = $stmt->fetchAll();
} catch (Exception $e) {
    $tipos = [['id' => 1, 'name' => 'Soporte Técnico']];
}

$defaultServiceTypeId = $equipoSeleccionado ? ($tipos[0]['id'] ?? 1) : '';

$tituloPagina = 'Reportar Incidencia - Sistema OTI';
$paginaActual = 'user-reportar';
?>
<?php require __DIR__ . '/../partials/head.php'; ?>
    <style>
        .report-layout { max-width: 800px; margin: 0 auto; }
        
        .card { background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-light); box-shadow: var(--shadow-subtle); margin-bottom: 20px; }
        .card-header { padding: 20px 24px; border-bottom: 1px solid var(--border-light); display: flex; align-items: center; gap: 14px; }
        .card-header-icon { width: 40px; height: 40px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .card-header-icon svg { width: 20px; height: 20px; }
        .card-header-text h2 { font-size: 17px; font-weight: 600; color: var(--text-primary); }
        .card-header-text p { font-size: 13px; color: var(--text-muted); margin-top: 2px; }
        .card-body { padding: 20px 24px; }
        
        .alert { padding: 14px 18px; border-radius: var(--radius-sm); margin-bottom: 20px; display: flex; align-items: center; gap: 12px; font-size: 14px; }
        .alert-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .alert-success svg { fill: #2e7d32; width: 18px; height: 18px; flex-shrink: 0; }
        .alert-error { background: #ffebee; color: #c62828; border: 1px solid #ffcdd2; }
        .alert-error svg { fill: #c62828; width: 18px; height: 18px; flex-shrink: 0; }
        
        .profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .profile-field { }
        .profile-label { font-size: 11px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .profile-value { font-size: 14px; color: var(--text-primary); font-weight: 500; padding: 8px 12px; background: var(--bg-main); border-radius: var(--radius-sm); border: 1px solid var(--border-light); }
        
        .equipo-card { display: none; background: #f8fafc; border: 1px solid var(--border-light); border-radius: var(--radius-md); padding: 16px; margin-top: 12px; }
        .equipo-card.visible { display: block; }
        .equipo-card-header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
        .equipo-card-header .eq-icon { width: 36px; height: 36px; background: rgba(55,48,163,0.08); border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .equipo-card-header .eq-icon svg { width: 18px; height: 18px; fill: var(--primary); }
        .equipo-card-header .eq-name { font-size: 15px; font-weight: 600; color: var(--text-primary); }
        .equipo-details { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .equipo-detail { }
        .equipo-detail .ed-label { font-size: 11px; color: var(--text-muted); font-weight: 500; }
        .equipo-detail .ed-value { font-size: 13px; color: var(--text-primary); font-weight: 500; }
        
        .form-section { margin-bottom: 20px; }
        .form-section:last-child { margin-bottom: 0; }
        .form-label { display: block; font-size: 13px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; }
        .form-label .required { color: var(--danger); }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 10px 14px; border: 1px solid var(--border-light); border-radius: var(--radius-sm); font-family: 'Outfit', sans-serif; font-size: 14px; color: var(--text-primary); background: var(--bg-card); transition: border-color 0.2s, box-shadow 0.2s; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(55, 48, 163, 0.1); }
        .form-textarea { min-height: 140px; resize: vertical; }
        .form-input.is-invalid, .form-select.is-invalid, .form-textarea.is-invalid { border-color: var(--danger); box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1); }
        .invalid-feedback { display: none; font-size: 12px; color: var(--danger); margin-top: 4px; }
        .invalid-feedback.is-visible { display: block; }
        
        .form-actions { padding: 20px 24px; border-top: 1px solid var(--border-light); display: flex; justify-content: flex-end; gap: 12px; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; border-radius: var(--radius-sm); font-family: 'Outfit', sans-serif; font-size: 14px; font-weight: 500; cursor: pointer; transition: all 0.2s; text-decoration: none; border: none; }
        .btn-secondary { background: var(--bg-main); color: var(--text-secondary); border: 1px solid var(--border-light); }
        .btn-secondary:hover { background: var(--border-light); }
        .btn-primary { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); color: white; }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(55, 48, 163, 0.3); }
        .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        .btn-primary svg { width: 16px; height: 16px; fill: white; }
        
        .spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: white; animation: spin 0.6s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        .empty-equipo { text-align: center; padding: 20px; color: var(--text-muted); }
        .empty-equipo svg { width: 40px; height: 40px; fill: var(--border-light); margin-bottom: 10px; }
        .empty-equipo p { font-size: 14px; }
        
        @media (max-width: 768px) { 
            .main-content { margin-left: 0; margin-top: 60px; padding: 16px; }
            .profile-grid { grid-template-columns: 1fr; }
            .equipo-details { grid-template-columns: 1fr; }
            .report-layout { margin: 0; }
        }
    </style>
<?php require __DIR__ . '/../partials/sidebar.php'; ?>
<?php require __DIR__ . '/../partials/header.php'; ?>
    
    <main id="main-content" class="main-content">
        <div class="page-header">
            <div class="page-title-group">
                <h1>Reportar Incidencia</h1>
                <p><?= $equipoSeleccionado ? 'Reportando para: ' . htmlspecialchars($equipoSeleccionado['name']) : 'Describe el problema que has tenido' ?></p>
            </div>
        </div>
        
        <div class="report-layout">
            <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                <span><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></span>
            </div>
            <?php unset($_SESSION['success']); endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                <span><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
            </div>
            <?php unset($_SESSION['error']); endif; ?>
            
            <form method="POST" action="<?= $baseUrl ?>user/ticket/crear" id="report-form">
                <input type="hidden" name="_token" value="<?= csrf_token() ?>">
                <!-- Card: Tu Información -->
                <div class="card">
                    <div class="card-header" style="background: #f8fafc;">
                        <div class="card-header-icon" style="background: rgba(55,48,163,0.08);">
                            <svg viewBox="0 0 24 24" fill="var(--primary)"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        </div>
                        <div class="card-header-text">
                            <h2>Tu Información</h2>
                            <p>Estos datos se adjuntarán automáticamente al ticket</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="profile-grid">
                            <div class="profile-field">
                                <div class="profile-label">Nombre Completo</div>
                                <div class="profile-value"><?= htmlspecialchars($userName) ?></div>
                            </div>
                            <div class="profile-field">
                                <div class="profile-label">Email</div>
                                <div class="profile-value"><?= htmlspecialchars($userEmail) ?></div>
                            </div>
                            <div class="profile-field">
                                <div class="profile-label">DNI</div>
                                <div class="profile-value"><?= htmlspecialchars($userDni ?: 'No registrado') ?></div>
                            </div>
                            <div class="profile-field">
                                <div class="profile-label">Área / Oficina</div>
                                <div class="profile-value"><?= htmlspecialchars($officeName) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card: Equipo -->
                <div class="card">
                    <div class="card-header" style="background: #f8fafc;">
                        <div class="card-header-icon" style="background: rgba(16,185,129,0.1);">
                            <svg viewBox="0 0 24 24" fill="var(--success)"><path d="M20 18c1.1 0 1.99-.9 1.99-2L22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2H0v2h24v-2h-4z"/></svg>
                        </div>
                        <div class="card-header-text">
                            <h2>Equipo Involucrado</h2>
                            <p>Selecciona el equipo con el que tienes el problema</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-section">
                            <label class="form-label" for="equipo-select">Equipo</label>
                            <select id="equipo-select" class="form-select">
                                <option value="">-- Seleccionar equipo --</option>
                                <?php foreach ($equiposAsignados as $eq): ?>
                                <option value="<?= $eq['id'] ?>" 
                                    data-name="<?= htmlspecialchars($eq['name']) ?>"
                                    data-brand="<?= htmlspecialchars($eq['brand'] ?? '') ?>"
                                    data-model="<?= htmlspecialchars($eq['model'] ?? '') ?>"
                                    data-patrimonial="<?= htmlspecialchars($eq['patrimonial_code'] ?? '') ?>"
                                    data-serial="<?= htmlspecialchars($eq['serial_number'] ?? '') ?>"
                                    data-location="<?= htmlspecialchars($eq['location_name'] ?? '') ?>"
                                    <?= ($equipoSeleccionado && (string)$eq['id'] === (string)$equipoId) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($eq['name']) ?> <?= $eq['patrimonial_code'] ? '(' . htmlspecialchars($eq['patrimonial_code']) . ')' : '' ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="equipo-card <?= $equipoSeleccionado ? 'visible' : '' ?>" id="equipo-card">
                            <div class="equipo-card-header">
                                <div class="eq-icon">
                                    <svg viewBox="0 0 24 24"><path d="M20 18c1.1 0 1.99-.9 1.99-2L22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2H0v2h24v-2h-4z"/></svg>
                                </div>
                                <div class="eq-name" id="eq-name-display"><?= $equipoSeleccionado ? htmlspecialchars($equipoSeleccionado['name']) : '' ?></div>
                            </div>
                            <div class="equipo-details" id="eq-details">
                                <div class="equipo-detail">
                                    <div class="ed-label">Marca</div>
                                    <div class="ed-value" id="eq-brand"><?= $equipoSeleccionado ? htmlspecialchars($equipoSeleccionado['brand'] ?? '—') : '—' ?></div>
                                </div>
                                <div class="equipo-detail">
                                    <div class="ed-label">Modelo</div>
                                    <div class="ed-value" id="eq-model"><?= $equipoSeleccionado ? htmlspecialchars($equipoSeleccionado['model'] ?? '—') : '—' ?></div>
                                </div>
                                <div class="equipo-detail">
                                    <div class="ed-label">Código Patrimonial</div>
                                    <div class="ed-value" id="eq-patrimonial"><?= $equipoSeleccionado ? htmlspecialchars($equipoSeleccionado['patrimonial_code'] ?? '—') : '—' ?></div>
                                </div>
                                <div class="equipo-detail">
                                    <div class="ed-label">Serie</div>
                                    <div class="ed-value" id="eq-serial"><?= $equipoSeleccionado ? htmlspecialchars($equipoSeleccionado['serial_number'] ?? '—') : '—' ?></div>
                                </div>
                                <div class="equipo-detail">
                                    <div class="ed-label">Ubicación</div>
                                    <div class="ed-value" id="eq-location"><?= $equipoSeleccionado ? htmlspecialchars($equipoSeleccionado['location_name'] ?? '—') : '—' ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="empty-equipo" id="empty-equipo" <?= $equipoSeleccionado ? 'style="display:none"' : '' ?>>
                            <svg viewBox="0 0 24 24"><path d="M20 18c1.1 0 1.99-.9 1.99-2L22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2H0v2h24v-2h-4z"/></svg>
                            <p>Selecciona un equipo para ver sus detalles</p>
                        </div>
                    </div>
                </div>

                <!-- Card: Detalles del Problema -->
                <div class="card">
                    <div class="card-header" style="background: #f8fafc;">
                        <div class="card-header-icon" style="background: rgba(245,158,11,0.12);">
                            <svg viewBox="0 0 24 24" fill="var(--warning)"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
                        </div>
                        <div class="card-header-text">
                            <h2>Detalles del Problema</h2>
                            <p>Describe claramente lo que sucede</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-section">
                            <label class="form-label" for="title">Título de la incidencia <span class="required">*</span></label>
                            <input type="text" name="title" id="title" class="form-input" placeholder="Ej: Computadora no enciende, el monitor parpadea..." required aria-required="true" aria-describedby="error-title">
                            <div class="invalid-feedback" id="error-title">El título es obligatorio</div>
                        </div>
                        
                        <div class="form-section">
                            <label class="form-label" for="service_type_id">Tipo de servicio <span class="required">*</span></label>
                            <select name="service_type_id" id="service_type_id" class="form-select" required aria-required="true" aria-describedby="error-service_type_id">
                                <option value="">Seleccionar tipo...</option>
                                <?php foreach ($tipos as $tipo): ?>
                                <option value="<?= $tipo['id'] ?>" <?= (string)$tipo['id'] === (string)$defaultServiceTypeId ? 'selected' : '' ?>><?= htmlspecialchars($tipo['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback" id="error-service_type_id">Seleccione un tipo de servicio</div>
                        </div>
                        
                        <div class="form-section">
                            <label class="form-label" for="description">Descripción del problema <span class="required">*</span></label>
                            <textarea name="description" id="description" class="form-textarea" placeholder="Describe detalladamente el problema. ¿Qué estabas haciendo cuando ocurrió? ¿Hay algún mensaje de error? ¿Desde cuándo ocurre?" required aria-required="true" aria-describedby="error-description"></textarea>
                            <div class="invalid-feedback" id="error-description">La descripción es obligatoria</div>
                        </div>
                        
                        <input type="hidden" name="equipment_id" id="equipment_id" value="<?= $equipoSeleccionado ? $equipoSeleccionado['id'] : '' ?>">
                    </div>
                    
                    <div class="form-actions">
                        <a href="<?= $baseUrl ?>user/dashboard" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary" id="submit-btn">
                            <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                            <span>Enviar Ticket</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </main>
    
    <script>
(function() {
    const form = document.getElementById('report-form');
    const submitBtn = document.getElementById('submit-btn');
    const equipoSelect = document.getElementById('equipo-select');
    const equipoCard = document.getElementById('equipo-card');
    const emptyEquipo = document.getElementById('empty-equipo');
    const equipInput = document.getElementById('equipment_id');
    
    const eqNameDisplay = document.getElementById('eq-name-display');
    const eqBrand = document.getElementById('eq-brand');
    const eqModel = document.getElementById('eq-model');
    const eqPatrimonial = document.getElementById('eq-patrimonial');
    const eqSerial = document.getElementById('eq-serial');
    const eqLocation = document.getElementById('eq-location');
    
    function updateEquipoCard(option) {
        if (!option || !option.value) {
            equipoCard.classList.remove('visible');
            emptyEquipo.style.display = '';
            equipInput.value = '';
            return;
        }
        
        eqNameDisplay.textContent = option.getAttribute('data-name');
        eqBrand.textContent = option.getAttribute('data-brand') || '—';
        eqModel.textContent = option.getAttribute('data-model') || '—';
        eqPatrimonial.textContent = option.getAttribute('data-patrimonial') || '—';
        eqSerial.textContent = option.getAttribute('data-serial') || '—';
        eqLocation.textContent = option.getAttribute('data-location') || '—';
        
        equipoCard.classList.add('visible');
        emptyEquipo.style.display = 'none';
        equipInput.value = option.value;
    }
    
    equipoSelect.addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        updateEquipoCard(selected);
    });
    
    <?php if ($equipoSeleccionado): ?>
    // If equipo_id was in URL, select it immediately
    const initialOption = equipoSelect.querySelector('option[value="<?= $equipoSeleccionado['id'] ?>"]');
    if (initialOption) {
        updateEquipoCard(initialOption);
    }
    <?php endif; ?>
    
    if (form && submitBtn) {
        form.addEventListener('submit', function(e) {
            const title = document.getElementById('title');
            const service = document.getElementById('service_type_id');
            const desc = document.getElementById('description');
            let valid = true;
            
            document.querySelectorAll('.is-invalid').forEach(function(el) { el.classList.remove('is-invalid'); });
            document.querySelectorAll('.invalid-feedback.is-visible').forEach(function(el) { el.classList.remove('is-visible'); });
            
            if (!title.value.trim()) {
                title.classList.add('is-invalid');
                document.getElementById('error-title').classList.add('is-visible');
                valid = false;
            }
            if (!service.value) {
                service.classList.add('is-invalid');
                document.getElementById('error-service_type_id').classList.add('is-visible');
                valid = false;
            }
            if (!desc.value.trim()) {
                desc.classList.add('is-invalid');
                document.getElementById('error-description').classList.add('is-visible');
                valid = false;
            }
            
            if (!valid) {
                e.preventDefault();
                return;
            }
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner"></span><span>Creando ticket...</span>';
        });
    }
})();
    </script>
<?php require __DIR__ . '/../partials/footer.php'; ?>