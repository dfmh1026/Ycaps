-- Esquema de base de datos para los pedidos de Ycaps.
-- Ejecuta este script en la base de datos MySQL creada en Hostinger (hPanel > Bases de datos).

CREATE TABLE IF NOT EXISTS pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    telefono VARCHAR(50) NOT NULL,
    direccion VARCHAR(255) NOT NULL,
    ciudad VARCHAR(100) NOT NULL,
    total DECIMAL(12,2) NOT NULL,
    metodo_pago VARCHAR(30) NOT NULL DEFAULT 'mercadopago',
    estado VARCHAR(30) NOT NULL DEFAULT 'pendiente',
    mp_preference_id VARCHAR(100) DEFAULT NULL,
    mp_payment_id VARCHAR(100) DEFAULT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS pedido_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    nombre_producto VARCHAR(150) NOT NULL,
    precio DECIMAL(12,2) NOT NULL,
    cantidad INT NOT NULL,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
);
