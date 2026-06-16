<?php
// Copia este archivo como "config.php" en el mismo directorio y coloca tus
// credenciales reales de Wompi. NO subas config.php a git.

// Modo de operación: 'sandbox' para pruebas, 'production' para cobros reales.
// Cambia a 'production' SOLO cuando hayas probado todo con sandbox.
define('WOMPI_ENV', 'sandbox');

// Llave pública — la encuentras en el dashboard de Wompi:
//   Desarrolladores > Llaves de API
//   Sandbox:    empieza con  pub_test_
//   Producción: empieza con  pub_prod_
define('WOMPI_PUBLIC_KEY', 'pub_test_TU_LLAVE_PUBLICA_AQUI');

// Llave privada — mantén esto SECRETO, nunca la expongas en el frontend.
//   Sandbox:    empieza con  prv_test_
//   Producción: empieza con  prv_prod_
define('WOMPI_PRIVATE_KEY', 'prv_test_TU_LLAVE_PRIVADA_AQUI');

// Secreto de integridad — dashboard de Wompi: Desarrolladores > Llaves de API
// Se usa para firmar la URL del checkout y que Wompi verifique que no fue alterada.
define('WOMPI_INTEGRITY_SECRET', 'TU_SECRETO_DE_INTEGRIDAD_AQUI');

// Secreto de eventos — dashboard de Wompi: Desarrolladores > Webhooks
// Se usa para verificar que el webhook proviene realmente de Wompi.
define('WOMPI_EVENTS_SECRET', 'TU_SECRETO_DE_EVENTOS_AQUI');

// URL a la que Wompi redirige al comprador después del pago.
// Debe ser una URL pública y accesible (HTTPS obligatorio en producción).
define('WOMPI_REDIRECT_URL', 'https://www.ycapsgorras.com/wompi/confirmacion.php');

// Email del propietario de la tienda — debe ser un correo que exista en tu hosting
// (ej: info@ycapsgorras.com creado en hPanel > Correos electrónicos).
// Se usa como remitente de todos los correos y para recibir notificaciones de pedidos.
define('TIENDA_EMAIL', 'info@ycapsgorras.com');
define('TIENDA_NOMBRE', 'Ycaps');

// Credenciales de la base de datos MySQL (hPanel de Hostinger > Bases de datos).
define('DB_HOST', 'localhost');
define('DB_NAME', 'TU_BASE_DE_DATOS');
define('DB_USER', 'TU_USUARIO_DB');
define('DB_PASS', 'TU_CONTRASENA_DB');
