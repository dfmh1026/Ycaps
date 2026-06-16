<?php
// Verificación de sesión admin. Incluir al inicio de cada página protegida.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$logado  = $_SESSION['admin_logado']       ?? false;
$ultimo  = $_SESSION['admin_ultimo_acceso'] ?? 0;
$timeout = 7200; // 2 horas

if (!$logado || (time() - $ultimo) > $timeout) {
    session_destroy();
    $motivo = (!$logado) ? '' : '?timeout=1';
    header('Location: /admin/index.php' . $motivo);
    exit;
}

$_SESSION['admin_ultimo_acceso'] = time();
