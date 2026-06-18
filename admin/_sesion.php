<?php
// Inicia la sesión del panel admin con cookies seguras: httponly (no accesible
// desde JS), secure (solo se envían por HTTPS) y samesite=Strict (no se envían
// en peticiones cross-site). Incluir esto en vez de llamar session_start() directo.

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}
