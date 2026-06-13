<?php
// Copia este archivo como "config.php" en el mismo directorio y coloca tus
// credenciales reales de Mercado Pago. NO subas config.php a git.

// Access Token PRIVADO de producción (Mercado Pago > Tu negocio > Credenciales).
define('MP_ACCESS_TOKEN', 'TU_ACCESS_TOKEN_AQUI');

// URLs a las que Mercado Pago redirige al comprador después del pago.
// Usa la URL real de tu sitio en Hostinger.
define('MP_BACK_URL_SUCCESS', 'https://tudominio.com/index.html?pago=exito');
define('MP_BACK_URL_FAILURE', 'https://tudominio.com/index.html?pago=fallo');
define('MP_BACK_URL_PENDING', 'https://tudominio.com/index.html?pago=pendiente');

// URL pública de webhook.php para que Mercado Pago notifique cambios de estado.
define('MP_NOTIFICATION_URL', 'https://tudominio.com/mercadopago/webhook.php');

// Credenciales de la base de datos MySQL (hPanel de Hostinger > Bases de datos).
define('DB_HOST', 'localhost');
define('DB_NAME', 'TU_BASE_DE_DATOS');
define('DB_USER', 'TU_USUARIO_DB');
define('DB_PASS', 'TU_CONTRASENA_DB');
