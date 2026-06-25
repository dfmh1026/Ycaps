<?php
require __DIR__ . '/auth.php';

// Solo el administrador puede ver y gestionar usuarios del panel.
if (!($_SESSION['admin_es_admin'] ?? false)) {
    http_response_code(403);
    require __DIR__ . '/load_config.php';
    $titulo = 'Usuarios del panel';
    $pag    = 'usuarios';
    require __DIR__ . '/_head.php';
    echo '<div class="alerta alerta-error">No tienes permiso para administrar usuarios.</div>';
    require __DIR__ . '/_foot.php';
    exit;
}

require __DIR__ . '/load_config.php';

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '-05:00'"]
);

$mensaje = '';
$tipoMsg = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $usuario  = trim($_POST['usuario'] ?? '');
        $clave    = trim($_POST['clave']   ?? '');
        $nombre   = trim($_POST['nombre']  ?? '');
        $esAdmin  = isset($_POST['es_admin']) ? 1 : 0;

        if ($usuario === '' || $clave === '') {
            $mensaje = 'El usuario y la contraseña son obligatorios.';
            $tipoMsg = 'error';
        } elseif (strlen($clave) < 6) {
            $mensaje = 'La contraseña debe tener al menos 6 caracteres.';
            $tipoMsg = 'error';
        } else {
            try {
                $pdo->prepare('INSERT INTO admin_usuarios (usuario, clave_hash, nombre, es_admin) VALUES (:u, :c, :n, :a)')
                    ->execute([
                        ':u' => $usuario,
                        ':c' => password_hash($clave, PASSWORD_BCRYPT),
                        ':n' => $nombre !== '' ? $nombre : null,
                        ':a' => $esAdmin,
                    ]);
                $mensaje = "Usuario \"{$usuario}\" creado correctamente.";
            } catch (Throwable $e) {
                $mensaje = 'Ese nombre de usuario ya existe o no se pudo crear.';
                $tipoMsg = 'error';
            }
        }
    }

    if ($accion === 'toggle') {
        $id     = (int) ($_POST['id'] ?? 0);
        $activo = (int) ($_POST['activo'] ?? 1);
        if ($id > 0) {
            try {
                $pdo->prepare('UPDATE admin_usuarios SET activo = :a WHERE id = :id')
                    ->execute([':a' => $activo ? 0 : 1, ':id' => $id]);
                $mensaje = $activo ? 'Usuario desactivado.' : 'Usuario activado.';
            } catch (Throwable $e) {
                $mensaje = 'No se pudo actualizar el usuario.';
                $tipoMsg = 'error';
            }
        }
    }

    if ($accion === 'toggle_admin') {
        $id      = (int) ($_POST['id'] ?? 0);
        $esAdmin = (int) ($_POST['es_admin'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->prepare('UPDATE admin_usuarios SET es_admin = :a WHERE id = :id')
                    ->execute([':a' => $esAdmin ? 0 : 1, ':id' => $id]);
                $mensaje = $esAdmin ? 'Ya no es administrador.' : 'Ahora es administrador.';
            } catch (Throwable $e) {
                $mensaje = 'No se pudo actualizar el usuario.';
                $tipoMsg = 'error';
            }
        }
    }
}

// Si la tabla admin_usuarios todavía no existe (falta correr la migración),
// se muestra la página vacía en vez de un error fatal.
try {
    $usuarios = $pdo->query('SELECT id, usuario, nombre, es_admin, activo, creado_en FROM admin_usuarios ORDER BY creado_en DESC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $usuarios = [];
    $mensaje  = 'La tabla "admin_usuarios" todavía no existe en tu base de datos. Créala con el SQL que te indicaron para poder usar esta página.';
    $tipoMsg  = 'error';
}

$titulo = 'Usuarios del panel';
$pag    = 'usuarios';
require __DIR__ . '/_head.php';
?>

<?php if ($mensaje): ?>
<div class="alerta alerta-<?= $tipoMsg === 'error' ? 'error' : 'success' ?>" style="margin-bottom:1rem">
    <?= htmlspecialchars($mensaje) ?>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:1.5rem">
    <div class="card-header">
        <h2>Crear nuevo usuario</h2>
    </div>
    <form method="POST" style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end;padding-top:.5rem">
        <input type="hidden" name="accion" value="crear">
        <div class="campo">
            <label>Nombre (opcional)</label>
            <input type="text" name="nombre" placeholder="Ej: María">
        </div>
        <div class="campo">
            <label>Usuario</label>
            <input type="text" name="usuario" required autocomplete="off">
        </div>
        <div class="campo">
            <label>Contraseña</label>
            <input type="password" name="clave" required minlength="6" autocomplete="new-password">
        </div>
        <div class="campo" style="flex-direction:row;align-items:center;gap:.4rem">
            <input type="checkbox" id="es_admin" name="es_admin" style="width:auto">
            <label for="es_admin" style="margin:0">Es administrador</label>
        </div>
        <button type="submit" class="btn-primary btn-sm">Crear usuario</button>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h2>Usuarios con acceso (<?= count($usuarios) ?>)</h2>
    </div>
    <div class="table-wrap">
        <?php if (empty($usuarios)): ?>
        <div class="empty-state">
            <p>Todavía no has creado usuarios adicionales. Por ahora solo funciona el usuario único de <code>ycaps_config.php</code>.</p>
        </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Nombre</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Creado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($usuarios as $u): ?>
                <tr style="<?= $u['activo'] ? '' : 'opacity:.5' ?>">
                    <td><?= htmlspecialchars($u['usuario']) ?></td>
                    <td><?= htmlspecialchars($u['nombre'] ?: '—') ?></td>
                    <td>
                        <?php if ($u['es_admin']): ?>
                        <span class="badge badge-aprobado">Administrador</span>
                        <?php else: ?>
                        <span class="badge" style="background:rgba(255,255,255,.08);color:var(--muted)">Usuario</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($u['activo']): ?>
                        <span class="badge badge-aprobado">Activo</span>
                        <?php else: ?>
                        <span class="badge badge-rechazado">Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap"><?= date('d/m/Y H:i', strtotime($u['creado_en'])) ?></td>
                    <td style="display:flex;gap:.3rem;flex-wrap:wrap">
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="accion" value="toggle_admin">
                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <input type="hidden" name="es_admin" value="<?= (int)$u['es_admin'] ?>">
                            <button type="submit" class="btn-secondary btn-sm">
                                <?= $u['es_admin'] ? 'Quitar admin' : 'Hacer admin' ?>
                            </button>
                        </form>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="accion" value="toggle">
                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <input type="hidden" name="activo" value="<?= (int)$u['activo'] ?>">
                            <button type="submit" class="<?= $u['activo'] ? 'btn-danger' : 'btn-guardar-stock' ?>">
                                <?= $u['activo'] ? 'Desactivar' : 'Activar' ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<p style="margin-top:1rem;color:var(--muted);font-size:.85rem">
    Solo los usuarios marcados como "Administrador" pueden ver esta página y crear, activar/desactivar o cambiar el rol de otros usuarios.
</p>

<?php require __DIR__ . '/_foot.php'; ?>
