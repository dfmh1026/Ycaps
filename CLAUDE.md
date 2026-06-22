# Ycaps — guía del proyecto

E-commerce de gorras (Ycaps). Sitio estático (HTML/CSS/JS sin build ni framework) + backend PHP + MySQL, en hosting compartido Hostinger. Pagos con Wompi (Colombia). Dueño no es programador — explicar cambios en términos simples cuando se le reporte algo.

## Estructura

```
index.html         página única (todas las secciones)
styles.css          una sola hoja, sin preprocesador
script.js           un solo archivo, sin módulos/bundler
media/              imágenes y video, referenciados como ./media/archivo.ext
db/schema.sql       esquema completo + datos semilla + migraciones comentadas
wompi/              backend de pagos (PHP)
admin/              panel de administración (PHP)
```

No hay `npm`, `package.json` ni paso de build. Los cambios en HTML/CSS/JS se reflejan directo al recargar.

## Frontend

- **index.html**: una sola página con header, nav, hero, franja de confianza, catálogo (12 tarjetas de producto hardcodeadas), "quiénes somos", carrito lateral (`carrito-panel`), modal de checkout (`modal-checkout`), modal de rastreo de pedido (`modal-rastreo`), lightbox de imágenes, modales legales (política/términos), banner de cookies, footer.
- El catálogo en `index.html` debe coincidir 1:1 (nombre, precio, imagen) con la tabla `productos` de `db/schema.sql`. Es sincronización manual — no hay render dinámico del catálogo en el front público.
- **script.js** agrupa responsabilidades independientes:
  - Carrito en `localStorage` (clave `ycaps-carrito`): agregar/eliminar/actualizar/render.
  - Checkout: `formCheckout` hace POST a `wompi/crear-transaccion.php`, recibe `checkout_url` y redirige a Wompi (flujo de **redirección**, no widget embebido).
  - Lectura de `?pago=...&ref=...` al volver de Wompi para mostrar banner de resultado.
  - Modal de rastreo → POST a `wompi/rastrear-pedido.php`.
  - Utilidades de UI: scroll reveal, botón volver arriba, filtros de catálogo, nav activo on-scroll, autoplay forzado del video del hero, lightbox de imágenes, selectores de cantidad, banner de cookies (clave `ycaps-cookies-aceptadas`), fábrica de modales legales (`inicializarModalLegal()`), fábrica de acordeones (`inicializarAcordeon()`).
- **styles.css**: variables CSS (`--dorado`, etc.), naming por componente (`.insignia-pagos`, `.carrito-panel`, `.marca-pago`...). Hay **dos juegos separados** de media queries (`max-width:768px` y `max-width:480px`) repartidos en distintos bloques por componente, con un comentario en el propio archivo advirtiendo sobre el orden/especificidad — al añadir reglas nuevas, respetar el bloque y el orden existente para no romper la cascada.

## Backend de pagos (`wompi/`)

- `crear-transaccion.php`: **valida precio y stock contra la tabla `productos` en MySQL**, nunca confía en lo que mande el navegador. Crea el pedido y la firma de integridad de Wompi, devuelve la URL de checkout.
- `webhook.php`: recibe la confirmación asíncrona de Wompi, actualiza `pedidos.estado` y registra el cambio en `pedido_estado_historial`.
- `confirmacion.php`: página de resultado tras volver de Wompi.
- `rastrear-pedido.php`: consulta de estado de pedido por el cliente.
- `recibo-pdf.php` / `pdf.php`: generación del PDF de recibo (la tabla `recibos` asegura un número de recibo fijo por pedido).
- `mailer.php`: envío de correos (confirmación de pedido, etc.).
- `db.php` / `load_config.php` / `config.example.php`: conexión y configuración. Las credenciales reales **no están en el repo**; solo se versiona `config.example.php`.

## Panel admin (`admin/`)

Capa separada con su propia sesión (`_sesion.php`, `auth.php`, `logout.php`) y rate-limiting de login por IP (tabla `admin_login_intentos`, con bloqueo temporal). Vistas: `dashboard.php`, `pedidos.php`, `inventario.php`, `clientes.php`, `ventas.php`.

## Base de datos (`db/schema.sql`)

- `pedidos` (cabecera) → `pedido_items` (líneas, FK cascada) → `pedido_estado_historial` y `pedido_guia_historial` (trazabilidad) → `recibos` (1:1 con pedido).
- `productos`: fuente de verdad de precio/stock/catálogo.
- `admin_login_intentos`: control de fuerza bruta del panel admin.
- El archivo incluye bloques `ALTER TABLE` / `CREATE TABLE IF NOT EXISTS` **comentados**, pensados para ejecutarse manualmente en phpMyAdmin solo si una instalación antigua no tiene esas columnas/tablas — no se ejecutan automáticamente.

## Invariantes de seguridad (no romper)

- El precio que cobra Wompi **siempre** se revalida server-side contra `productos` en `crear-transaccion.php`. El precio mostrado en `index.html` es solo presentación.
- El flujo de pago es por **redirección** a Wompi, no widget embebido — no cambiar esto sin que el usuario lo pida explícitamente.
- El modal de checkout solo se cierra con el botón "×" (decisión explícita del usuario, para evitar cierres accidentales mientras se llena el formulario).

## Convenciones de trabajo con el usuario

- El dueño del proyecto no es programador: explicar en términos simples, sin jerga, al reportar cambios o pedir confirmaciones.
- **Logos/marcas de terceros (Wompi, redes de pago, etc.)**: nunca descargar de sitios agregadores no oficiales (Brandfetch, vectorlogo.es, logo-teka.com, worldvectorlogo, seeklogo, brandsoftheworld, etc.). Solo usar activos oficiales (brand center del proveedor) o archivos entregados directamente por el proveedor (p. ej. Wompi envió `media/wompi-pci.jpg` y `media/wompi-logo.jpg`), y solo tras revisión/confirmación del usuario.
- Sin herramienta de navegador/visual testing en este entorno — para cambios CSS que dependen de renderizado real (especialmente en móvil), preferir soluciones simples y ya validadas (`clamp()`, `white-space:nowrap`) sobre soluciones dinámicas con JS (medir `scrollWidth`/`transform:scale`), que ya causaron un bug real de recorte de texto en dispositivo físico y tuvieron que revertirse.
- Si el usuario pide revertir un cambio reciente, revertir limpio y completo a ese estado anterior — no intentar otro parche encima salvo que lo pida.
