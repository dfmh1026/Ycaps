<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require __DIR__ . '/auth.php';
require __DIR__ . '/load_config.php';

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$mensaje = '';
$tipoMsg = 'success';

// Actualizar stock via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'stock') {
        $id    = (int)($_POST['id'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
        if ($id > 0 && $stock >= 0) {
            $pdo->prepare("UPDATE productos SET stock = :s WHERE id = :id")
                ->execute([':s' => $stock, ':id' => $id]);
            $mensaje = 'Stock actualizado correctamente.';
        }
    }

    if ($accion === 'toggle') {
        $id     = (int)($_POST['id'] ?? 0);
        $activo = (int)($_POST['activo'] ?? 1);
        if ($id > 0) {
            $pdo->prepare("UPDATE productos SET activo = :a WHERE id = :id")
                ->execute([':a' => $activo ? 0 : 1, ':id' => $id]);
            $mensaje = $activo ? 'Producto desactivado.' : 'Producto activado.';
        }
    }

    if ($accion === 'precios') {
        $id      = (int)($_POST['id'] ?? 0);
        $precio  = (float)($_POST['precio'] ?? 0);
        $prOrig  = (float)($_POST['precio_original'] ?? 0);
        if ($id > 0 && $precio >= 0) {
            $pdo->prepare("UPDATE productos SET precio = :p, precio_original = :po WHERE id = :id")
                ->execute([':p' => $precio, ':po' => $prOrig, ':id' => $id]);
            $mensaje = 'Precios actualizados.';
        }
    }
}

$categoriaFiltro = $_GET['categoria'] ?? '';
$where  = '1=1';
$params = [];
if ($categoriaFiltro) { $where .= ' AND categoria = :cat'; $params[':cat'] = $categoriaFiltro; }

$st = $pdo->prepare("SELECT * FROM productos WHERE $where ORDER BY activo DESC, categoria, nombre");
$st->execute($params);
$productos = $st->fetchAll(PDO::FETCH_ASSOC);

$categorias = $pdo->query("SELECT DISTINCT categoria FROM productos ORDER BY categoria")->fetchAll(PDO::FETCH_COLUMN);

$titulo = 'Inventario';
$pag    = 'inventario';
require __DIR__ . '/_head.php';
?>

<?php if ($mensaje): ?>
<div class="alerta alerta-success" style="margin-bottom:1rem"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>Inventario de productos</h2>
        <div class="card-header-actions">
            <form method="GET" style="display:flex;gap:.5rem;align-items:center">
                <select name="categoria" onchange="this.form.submit()">
                    <option value="">Todas las categorías</option>
                    <?php foreach ($categorias as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= $categoriaFiltro === $cat ? 'selected' : '' ?>><?= ucfirst($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th>Precio</th>
                    <th>Precio original</th>
                    <th>Stock</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($productos as $prod): ?>
                <tr style="<?= $prod['activo'] ? '' : 'opacity:.5' ?>">
                    <td><?= $prod['id'] ?></td>
                    <td>
                        <?php if ($prod['imagen']): ?>
                        <img src="/media/<?= htmlspecialchars($prod['imagen']) ?>" alt="" style="width:36px;height:36px;object-fit:cover;border-radius:6px;margin-right:.5rem;vertical-align:middle">
                        <?php endif; ?>
                        <?= htmlspecialchars($prod['nombre']) ?>
                    </td>
                    <td><?= ucfirst(htmlspecialchars($prod['categoria'])) ?></td>
                    <td>
                        <form method="POST" style="display:inline-flex;gap:.3rem;align-items:center">
                            <input type="hidden" name="accion" value="precios">
                            <input type="hidden" name="id" value="<?= $prod['id'] ?>">
                            <input type="number" name="precio" value="<?= (int)$prod['precio'] ?>" min="0" step="1000" class="stock-input" style="width:95px">
                            <span style="color:var(--muted);font-size:.8rem">/</span>
                            <input type="number" name="precio_original" value="<?= (int)$prod['precio_original'] ?>" min="0" step="1000" class="stock-input" style="width:95px">
                            <button type="submit" class="btn-guardar-stock" title="Guardar precios">&#10003;</button>
                        </form>
                    </td>
                    <td></td>
                    <td>
                        <form method="POST" style="display:inline-flex;gap:.3rem;align-items:center">
                            <input type="hidden" name="accion" value="stock">
                            <input type="hidden" name="id" value="<?= $prod['id'] ?>">
                            <input type="number" name="stock" value="<?= $prod['stock'] ?>" min="0" class="stock-input">
                            <button type="submit" class="btn-guardar-stock">&#10003;</button>
                        </form>
                        <?php if ($prod['stock'] <= 3 && $prod['stock'] >= 0 && $prod['activo']): ?>
                        <span class="badge badge-rechazado" style="margin-left:.3rem">Bajo</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($prod['activo']): ?>
                        <span class="badge badge-aprobado">Activo</span>
                        <?php else: ?>
                        <span class="badge badge-rechazado">Inactivo</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="accion" value="toggle">
                            <input type="hidden" name="id" value="<?= $prod['id'] ?>">
                            <input type="hidden" name="activo" value="<?= $prod['activo'] ?>">
                            <button type="submit" class="<?= $prod['activo'] ? 'btn-danger' : 'btn-guardar-stock' ?>">
                                <?= $prod['activo'] ? 'Desactivar' : 'Activar' ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/_foot.php'; ?>
