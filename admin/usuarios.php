<?php
require __DIR__ . '/auth.php';
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
        $usuario = trim($_POST['usuario'] ?? '');
        $clave   = trim($_POST['clave']   ?? '');
        $nombre  = trim($_POST['nombre']  ?? '');

        if ($usuario === '' || $clave === '') {
            $mensaje = 'El usuario y la contraseña son obligatorios.';
            $tipoMsg = 'error';
        } elseif (strlen($clave) < 6) {
            $mensaje = 'La contraseña debe tener al menos 6 caracteres.';
            $tipoMsg = 'error';
        } else {
            try {
                $pdo->prepare('INSERT INTO admin_usuarios (usuario, clave_hash, nombre) VALUES (:u, :c, :n)')
                    ->execute([
                        ':u' => $usuario,
                        ':c' => password_hash($clave, PASSWORD_BCRYPT),
                        ':n' => $nombre !== '' ? $nombre : null,
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
            $pdo->prepare('UPDATE admin_usuarios SET activo = :a WHERE id = :id')
                ->execute([':a' => $activo ? 0 : 1, ':id' => $id]);
            $mensaje = $activo ? 'Usuario desactivado.' : 'Usuario activado.';
        }
    }
}

$usuarios = $pdo->query('SELECT id, usuario, nombre, activo, creado_en FROM admin_usuarios ORDER BY creado_en DESC')->fetchAll(PDO::FETCH_ASSOC);

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
                        <?php if ($u['activo']): ?>
                        <span class="badge badge-aprobado">Activo</span>
                        <?php else: ?>
                        <span class="badge badge-rechazado">Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap"><?= date('d/m/Y H:i', strtotime($u['creado_en'])) ?></td>
                    <td>
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
    Nota: cualquier usuario con acceso al panel puede ver esta página y crear o desactivar otros usuarios — no hay niveles de permiso distintos por ahora.
</p>

<?php require __DIR__ . '/_foot.php'; ?>
