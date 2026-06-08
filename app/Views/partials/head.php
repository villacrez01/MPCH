<!DOCTYPE html>
<?php
$baseUrl = $baseUrl ?? base_url();
$isOtiAdmin = $isOtiAdmin ?? false;
$tituloPagina = $tituloPagina ?? 'Sistema OTI';
?>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tituloPagina ?? 'Sistema OTI') ?></title>
    <meta name="base-url" content="<?= htmlspecialchars($baseUrl) ?>">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>public/assets/css/app.css?v=20260606">
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>public/assets/css/pages.css?v=20260606">
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>public/assets/css/oti-white.css?v=20260606">
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>public/assets/css/notifications.css?v=20260606a">
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>public/assets/css/rbac-buttons.css?v=rbac">
    <script src="https://unpkg.com/lucide@0.344.0/dist/umd/lucide.min.js" crossorigin="anonymous"
            onerror="this.remove();var s=document.createElement('script');s.src='<?= htmlspecialchars($baseUrl) ?>public/assets/vendor/lucide.min.js';document.head.appendChild(s);"></script>
</head>
<body>
    <a href="#main-content" class="skip-link">Saltar al contenido principal</a>
    <?php if (isset($userId)): ?>
    <input type="hidden" id="user-id" value="<?= htmlspecialchars($userId) ?>">
    <?php endif; ?>
    <input type="hidden" id="is-admin" value="<?= ($isOtiAdmin ?? false) ? '1' : '0' ?>">
    <script src="<?= htmlspecialchars($baseUrl) ?>public/assets/js/rbac-store.js?v=rbac"></script>