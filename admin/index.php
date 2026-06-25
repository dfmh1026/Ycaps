<?php
require __DIR__ . '/_sesion.php';

if ($_SESSION['admin_logado'] ?? false) {
    header('Location: /admin/dashboard.php');
    exit;
}

require __DIR__ . '/load_config.php';

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '-05:00'"]
);

// ── Límite de intentos de login por IP: 5 intentos fallidos bloquean 15 min ──
const LOGIN_MAX_INTENTOS  = 5;
const LOGIN_BLOQUEO_SEGS  = 900;

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

$stmtIntentos = $pdo->prepare('SELECT intentos, bloqueado_hasta FROM admin_login_intentos WHERE ip = :ip LIMIT 1');
$stmtIntentos->execute([':ip' => $ip]);
$registro = $stmtIntentos->fetch(PDO::FETCH_ASSOC);

$bloqueadoHastaTs = ($registro && $registro['bloqueado_hasta']) ? strtotime($registro['bloqueado_hasta']) : 0;
$bloqueado        = $bloqueadoHastaTs > time();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $bloqueado) {
    $minutosRestantes = (int) ceil(($bloqueadoHastaTs - time()) / 60);
    $error = "Demasiados intentos fallidos. Intenta de nuevo en {$minutosRestantes} minuto(s).";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $clave   = trim($_POST['clave']   ?? '');

    // 1) Buscar primero en la tabla de usuarios del panel (admin_usuarios).
    // Si la tabla todavía no existe (instalación que no ha corrido esa
    // migración), no debe bloquear el login — se sigue con el respaldo.
    $usuarioDb = false;
    try {
        $stmtUsuario = $pdo->prepare('SELECT clave_hash, nombre, es_admin FROM admin_usuarios WHERE usuario = :u AND activo = 1 LIMIT 1');
        $stmtUsuario->execute([':u' => $usuario]);
        $usuarioDb = $stmtUsuario->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $usuarioDb = false;
    }

    $claveValida = false;
    $esAdmin     = false;
    if ($usuarioDb) {
        $claveValida = password_verify($clave, $usuarioDb['clave_hash']);
        $esAdmin     = (bool) $usuarioDb['es_admin'];
    } elseif ($usuario === ADMIN_USUARIO) {
        // 2) Respaldo: el usuario único definido en ycaps_config.php.
        // Soporta ADMIN_CLAVE como hash bcrypt (recomendado) o como texto plano.
        // Este usuario siempre es administrador (es el dueño original de la tienda).
        $claveValida = (strncmp(ADMIN_CLAVE, '$2y$', 4) === 0)
            ? password_verify($clave, ADMIN_CLAVE)
            : hash_equals(ADMIN_CLAVE, $clave);
        $esAdmin = true;
    }

    if ($claveValida) {
        $pdo->prepare('DELETE FROM admin_login_intentos WHERE ip = :ip')->execute([':ip' => $ip]);

        session_regenerate_id(true);
        $_SESSION['admin_logado']        = true;
        $_SESSION['admin_usuario']       = $usuario;
        $_SESSION['admin_es_admin']      = $esAdmin;
        $_SESSION['admin_ultimo_acceso'] = time();
        header('Location: /admin/dashboard.php');
        exit;
    }

    // El intentos previo solo cuenta si el bloqueo anterior ya venció
    $intentosPrevios = ($registro && $bloqueadoHastaTs <= time()) ? (int) $registro['intentos'] : 0;
    $intentos        = $intentosPrevios + 1;
    $bloqueadoHasta  = $intentos >= LOGIN_MAX_INTENTOS
        ? date('Y-m-d H:i:s', time() + LOGIN_BLOQUEO_SEGS)
        : null;

    $pdo->prepare(
        'INSERT INTO admin_login_intentos (ip, intentos, bloqueado_hasta)
         VALUES (:ip, :intentos, :bloqueado)
         ON DUPLICATE KEY UPDATE intentos = :intentos, bloqueado_hasta = :bloqueado'
    )->execute([':ip' => $ip, ':intentos' => $intentos, ':bloqueado' => $bloqueadoHasta]);

    $error = $intentos >= LOGIN_MAX_INTENTOS
        ? 'Demasiados intentos fallidos. Intenta de nuevo en 15 minutos.'
        : 'Usuario o contraseña incorrectos.';
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
