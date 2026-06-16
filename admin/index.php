<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SESSION['admin_logado'] ?? false) {
    header('Location: /admin/dashboard.php');
    exit;
}

require __DIR__ . '/load_config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $clave   = trim($_POST['clave']   ?? '');

    if ($usuario === ADMIN_USUARIO && hash_equals(ADMIN_CLAVE, $clave)) {
        session_regenerate_id(true);
        $_SESSION['admin_logado']        = true;
        $_SESSION['admin_ultimo_acceso'] = time();
        header('Location: /admin/dashboard.php');
        exit;
    }
    $error = 'Usuario o contraseña incorrectos.';
}

$timeout = isset($_GET['timeout']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ycaps — Administración</title>
<link rel="stylesheet" href="/admin/admin.css">
</head>
<body class="login-body">
<div class="login-card">
    <div class="login-logo">
        <img src="/media/logoycaps.png" alt="Ycaps" onerror="this.style.display='none'">
        <h1>YCAPS</h1>
        <p>Panel de Administración</p>
    </div>
    <?php if ($timeout): ?>
        <div class="alerta alerta-warning">Sesión expirada. Ingresa de nuevo.</div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alerta alerta-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="campo">
            <label>Usuario</label>
            <input type="text" name="usuario" required autofocus autocomplete="username">
        </div>
        <div class="campo">
            <label>Contraseña</label>
            <input type="password" name="clave" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn-primary btn-block">Iniciar sesión</button>
    </form>
</div>
</body>
</html>
