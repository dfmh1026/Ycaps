-- ============================================================
-- ESQUEMA COMPLETO DE BASE DE DATOS — YCAPS
-- Ejecuta este script completo en phpMyAdmin (hPanel > Bases de datos)
-- ============================================================

-- Tabla de pedidos
CREATE TABLE IF NOT EXISTS pedidos (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    nombre               VARCHAR(150) NOT NULL,
    email                VARCHAR(150) NOT NULL,
    telefono             VARCHAR(50)  NOT NULL,
    direccion            VARCHAR(255) NOT NULL,
    ciudad               VARCHAR(100) NOT NULL,
    total                DECIMAL(12,2) NOT NULL,
    metodo_pago          VARCHAR(30)  NOT NULL DEFAULT 'wompi',
    estado               VARCHAR(30)  NOT NULL DEFAULT 'pendiente',
    wompi_referencia     VARCHAR(100) DEFAULT NULL,
    wompi_transaction_id VARCHAR(100) DEFAULT NULL,
    guia_envio           VARCHAR(150) DEFAULT NULL,
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
('Gorra Negra Azabache',      85000, 95000, 10, 'negra',         'gorra-negra.png'),
('Gorra Gris',                85000, 95000, 10, 'gris',          'gorra-gris.png'),
('Gorra Blanca Caballo Negro',85000, 95000, 10, 'blanca',        'gorra-blancan.png'),
('Gorra Blanca',              85000, 95000, 10, 'blanca',        'gorra-blancat.png'),
('Gorra Negra Firma YJ',      85000, 95000, 10, 'negra',         'gorra-negrayj.png'),
('Gorra Caqui',               85000, 95000, 10, 'caqui',         'gorra-caqui.png'),
('Gorra Negra Caballo Dorado',85000, 95000, 10, 'negra',         'gorra-negrad.png'),
('Gorra Blanca Dorado',       85000, 95000, 10, 'blanca',        'gorra-blancad.png'),
('Gorra Negra Malla Dorado',  85000, 95000, 10, 'negra',         'gorra-negramallad.png'),
('Gorra Blanca Edición YJ',   85000, 95000, 10, 'blanca',        'gorra-blancayj.png'),
('Gorra Caqui Edición YJ',    85000, 95000, 10, 'caqui',         'gorra-caquiyj.png'),
('Gorras Personalizadas',         0,     0,  0, 'personalizada', 'personalizada1.png');

-- ============================================================
-- MIGRACIONES — ejecuta solo las que apliquen a tu instalación.
-- ============================================================

-- Guía de envío (agregar si ya tenías la tabla pedidos sin esta columna):
-- ALTER TABLE pedidos ADD COLUMN guia_envio VARCHAR(150) DEFAULT NULL;

-- Columnas Wompi (solo si venías de MercadoPago):
-- ALTER TABLE pedidos
--     CHANGE COLUMN mp_preference_id wompi_referencia     VARCHAR(100) DEFAULT NULL,
--     CHANGE COLUMN mp_payment_id    wompi_transaction_id VARCHAR(100) DEFAULT NULL;
