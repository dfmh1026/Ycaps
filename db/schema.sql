-- ============================================================
-- ESQUEMA COMPLETO DE BASE DE DATOS — YCAPS
-- Ejecuta este script completo en phpMyAdmin (hPanel > Bases de datos)
-- ============================================================

-- Tabla de pedidos
CREATE TABLE IF NOT EXISTS pedidos (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    nombre               VARCHAR(150) NOT NULL,
    cedula               VARCHAR(20)  NOT NULL DEFAULT '',
    email                VARCHAR(150) NOT NULL,
    telefono             VARCHAR(50)  NOT NULL,
    direccion            VARCHAR(255) NOT NULL,
    ciudad               VARCHAR(100) NOT NULL,
    departamento         VARCHAR(100) DEFAULT NULL,
    total                DECIMAL(12,2) NOT NULL,
    metodo_pago          VARCHAR(30)  NOT NULL DEFAULT 'wompi',
    estado               VARCHAR(30)  NOT NULL DEFAULT 'pendiente',
    wompi_referencia     VARCHAR(100) DEFAULT NULL,
    wompi_transaction_id VARCHAR(100) DEFAULT NULL,
    guia_envio           VARCHAR(150) DEFAULT NULL,
    recibo               LONGBLOB DEFAULT NULL,
    creado_en            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de ítems por pedido
CREATE TABLE IF NOT EXISTS pedido_items (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id        INT NOT NULL,
    nombre_producto  VARCHAR(150) NOT NULL,
    precio           DECIMAL(12,2) NOT NULL,
    cantidad         INT NOT NULL,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
);

-- Historial de cambios de estado por pedido (trazabilidad)
CREATE TABLE IF NOT EXISTS pedido_estado_historial (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id       INT NOT NULL,
    estado_anterior VARCHAR(30) DEFAULT NULL,
    estado_nuevo    VARCHAR(30) NOT NULL,
    origen          VARCHAR(50) NOT NULL,
    detalle         VARCHAR(255) DEFAULT NULL,
    creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
);

-- Historial de números de guía por pedido (trazabilidad de envíos)
CREATE TABLE IF NOT EXISTS pedido_guia_historial (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id   INT NOT NULL,
    guia_envio  VARCHAR(150) NOT NULL,
    creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
);

-- Consecutivo de recibos PDF generados (un recibo por pedido, número fijo)
CREATE TABLE IF NOT EXISTS recibos (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id  INT NOT NULL UNIQUE,
    creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
);

-- Mensajes del formulario de "Contacto" del sitio — respaldo en base de datos
-- por si el envío del correo a ventas@ycapsgorras.com llegara a fallar.
CREATE TABLE IF NOT EXISTS mensajes_contacto (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    nombre    VARCHAR(150) NOT NULL,
    email     VARCHAR(150) NOT NULL,
    telefono  VARCHAR(50)  DEFAULT NULL,
    mensaje   TEXT NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Límite de intentos de login del panel admin (1 fila por IP)
CREATE TABLE IF NOT EXISTS admin_login_intentos (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    ip              VARCHAR(45) NOT NULL UNIQUE,
    intentos        INT NOT NULL DEFAULT 1,
    bloqueado_hasta DATETIME DEFAULT NULL,
    actualizado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de productos e inventario
CREATE TABLE IF NOT EXISTS productos (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    nombre           VARCHAR(150) NOT NULL UNIQUE,
    descripcion      TEXT DEFAULT NULL,
    precio           DECIMAL(12,2) NOT NULL DEFAULT 85000,
    precio_original  DECIMAL(12,2) DEFAULT 95000,
    stock            INT NOT NULL DEFAULT 10,
    categoria        VARCHAR(50)  NOT NULL DEFAULT 'gorras',
    imagen           VARCHAR(255) DEFAULT NULL,
    activo           TINYINT(1)  NOT NULL DEFAULT 1,
    creado_en        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- DATOS SEMILLA — productos actuales del catálogo
-- ============================================================
INSERT IGNORE INTO productos (nombre, precio, precio_original, stock, categoria, imagen) VALUES
('Gorra Negra Azabache',      85000, 95000, 10, 'negra',         'gorra-negra.jpg'),
('Gorra Gris',                85000, 95000, 10, 'gris',          'gorra-gris.jpg'),
('Gorra Blanca Caballo Negro',85000, 95000, 10, 'blanca',        'gorra-blancan.jpg'),
('Gorra Blanca',              85000, 95000, 10, 'blanca',        'gorra-blancat.jpg'),
('Gorra Negra Firma YJ',      85000, 95000, 10, 'negra',         'gorra-negrayj.jpg'),
('Gorra Caqui',               85000, 95000, 10, 'caqui',         'gorra-caqui.jpg'),
('Gorra Negra Caballo Dorado',85000, 95000, 10, 'negra',         'gorra-negrad.jpg'),
('Gorra Blanca Dorado',       85000, 95000, 10, 'blanca',        'gorra-blancad.jpg'),
('Gorra Negra Malla Dorado',  85000, 95000, 10, 'negra',         'gorra-negramallad.jpg'),
('Gorra Blanca Edición YJ',   90000, 95000, 10, 'blanca',        'gorra-blancayj.jpg'),
('Gorra Caqui Edición YJ',    90000, 95000, 10, 'caqui',         'gorra-caquiyj.jpg'),
('Gorra Negra Caballo Blanco',85000, 95000, 10, 'negra',         'gorra-negra-caballo.blanco.jpeg'),
('Gorra Negra YJ Dorada',     90000, 95000, 10, 'negra',         'gorra-negra-yj-dorado.jpg'),
('Gorra Negra YJ Firma Dorada',90000, 95000, 10, 'negra',        'gorra-negra-yj-firma-dorada.jpg'),
('Prueba',                     3000,  3000, 10, 'prueba',        'gorra-negra.jpg'),
('Gorras Personalizadas',         0,     0,  0, 'personalizada', 'personalizada1.jpg');

-- ============================================================
-- MIGRACIONES — ejecuta solo las que apliquen a tu instalación.
-- ============================================================

-- Las fotos de producto se optimizaron y pasaron de .png a .jpg (mismo nombre
-- base) para que el catálogo cargue mucho más rápido. Si tu tabla "productos"
-- ya tenía filas con el nombre antiguo en .png, ejecuta esto una sola vez:
UPDATE productos SET imagen = 'gorra-negra.jpg'        WHERE imagen = 'gorra-negra.png';
UPDATE productos SET imagen = 'gorra-gris.jpg'         WHERE imagen = 'gorra-gris.png';
UPDATE productos SET imagen = 'gorra-blancan.jpg'      WHERE imagen = 'gorra-blancan.png';
UPDATE productos SET imagen = 'gorra-blancat.jpg'      WHERE imagen = 'gorra-blancat.png';
UPDATE productos SET imagen = 'gorra-negrayj.jpg'      WHERE imagen = 'gorra-negrayj.png';
UPDATE productos SET imagen = 'gorra-caqui.jpg'        WHERE imagen = 'gorra-caqui.png';
UPDATE productos SET imagen = 'gorra-negrad.jpg'       WHERE imagen = 'gorra-negrad.png';
UPDATE productos SET imagen = 'gorra-blancad.jpg'      WHERE imagen = 'gorra-blancad.png';
UPDATE productos SET imagen = 'gorra-negramallad.jpg'  WHERE imagen = 'gorra-negramallad.png';
UPDATE productos SET imagen = 'gorra-blancayj.jpg'     WHERE imagen = 'gorra-blancayj.png';
UPDATE productos SET imagen = 'gorra-caquiyj.jpg'      WHERE imagen = 'gorra-caquiyj.png';
UPDATE productos SET imagen = 'personalizada1.jpg'     WHERE imagen = 'personalizada1.png';

-- Guía de envío (agregar si ya tenías la tabla pedidos sin esta columna):
-- ALTER TABLE pedidos ADD COLUMN guia_envio VARCHAR(150) DEFAULT NULL;

-- Departamento (agregar si ya tenías la tabla pedidos sin esta columna):
-- ALTER TABLE pedidos ADD COLUMN departamento VARCHAR(100) DEFAULT NULL;

-- Recibo PDF guardado (agregar si ya tenías la tabla pedidos sin esta columna):
-- guarda el mismo PDF que se envía por correo al confirmarse el pago, para
-- poder consultarlo después desde el panel admin sin tener que regenerarlo.
-- ALTER TABLE pedidos ADD COLUMN recibo LONGBLOB DEFAULT NULL;

-- Cédula (agregar si ya tenías la tabla pedidos sin esta columna):
-- ALTER TABLE pedidos ADD COLUMN cedula VARCHAR(20) NOT NULL DEFAULT '' AFTER nombre;

-- Historial de estados (agregar si ya tenías la base de datos creada):
-- CREATE TABLE IF NOT EXISTS pedido_estado_historial (
--     id              INT AUTO_INCREMENT PRIMARY KEY,
--     pedido_id       INT NOT NULL,
--     estado_anterior VARCHAR(30) DEFAULT NULL,
--     estado_nuevo    VARCHAR(30) NOT NULL,
--     origen          VARCHAR(50) NOT NULL,
--     detalle         VARCHAR(255) DEFAULT NULL,
--     creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
--     FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
-- );

-- Historial de guías de envío (agregar si ya tenías la base de datos creada):
-- CREATE TABLE IF NOT EXISTS pedido_guia_historial (
--     id          INT AUTO_INCREMENT PRIMARY KEY,
--     pedido_id   INT NOT NULL,
--     guia_envio  VARCHAR(150) NOT NULL,
--     creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
--     FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
-- );

-- Consecutivo de recibos PDF (agregar si ya tenías la base de datos creada):
-- CREATE TABLE IF NOT EXISTS recibos (
--     id         INT AUTO_INCREMENT PRIMARY KEY,
--     pedido_id  INT NOT NULL UNIQUE,
--     creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
--     FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
-- );

-- Mensajes de contacto (agregar si ya tenías la base de datos creada):
-- CREATE TABLE IF NOT EXISTS mensajes_contacto (
--     id        INT AUTO_INCREMENT PRIMARY KEY,
--     nombre    VARCHAR(150) NOT NULL,
--     email     VARCHAR(150) NOT NULL,
--     telefono  VARCHAR(50)  DEFAULT NULL,
--     mensaje   TEXT NOT NULL,
--     creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
-- );

-- Límite de intentos de login admin (agregar si ya tenías la base de datos creada):
-- CREATE TABLE IF NOT EXISTS admin_login_intentos (
--     id              INT AUTO_INCREMENT PRIMARY KEY,
--     ip              VARCHAR(45) NOT NULL UNIQUE,
--     intentos        INT NOT NULL DEFAULT 1,
--     bloqueado_hasta DATETIME DEFAULT NULL,
--     actualizado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
-- );

-- Columnas Wompi (solo si venías de MercadoPago):
-- ALTER TABLE pedidos
--     CHANGE COLUMN mp_preference_id wompi_referencia     VARCHAR(100) DEFAULT NULL,
--     CHANGE COLUMN mp_payment_id    wompi_transaction_id VARCHAR(100) DEFAULT NULL;
