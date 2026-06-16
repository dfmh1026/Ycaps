<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/load_config.php';

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$estado   = $_GET['estado']   ?? '';
$busqueda = trim($_GET['q']   ?? '');
$pagina   = max(1, (int)($_GET['pag'] ?? 1));
$porPag   = 20;

$where  = '1=1';
$params = [];
if ($estado !== '')  { $where .= ' AND estado = :estado';  $params[':estado'] = $estado; }
if ($busqueda !== '') {
    $where .= ' AND (wompi_referencia LIKE :q OR nombre LIKE :q OR email LIKE :q)';
    $params[':q'] = '%' . $busqueda . '%';
}

$stTotal = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE " . $where);
$stTotal->execute($params);
$total     = (int) $stTotal->fetchColumn();
$totalPags = max(1, (int) ceil($total / $porPag));
$offset    = ($pagina - 1) * $porPag;

$sql     = "SELECT * FROM pedidos WHERE " . $where . " ORDER BY creado_en DESC LIMIT " . (int)$porPag . " OFFSET " . (int)$offset;
$stPed   = $pdo->prepare($sql);
$stPed->execute($params);
$pedidos = $stPed->fetchAll(PDO::FETCH_ASSOC);

$titulo = 'Pedidos';
$pag    = 'pedidos';
require __DIR__ . '/_head.php';
?>
<p style="color:red;font-size:2rem;font-weight:bold">PRUEBA PHP OK — total=<?= $total ?></p>
<div class="card">
    <div class="card-header">
        <h2>Todos los pedidos (<?= $total ?>)</h2>
        <div class="card-header-actions">
            <form method="GET" style="display:flex;gap:.5rem;flex-wrap:wrap">
                <input type="text" name="q" placeholder="Referencia, nombre o email..." value="<?= htmlspecialchars($busqueda) ?>" style="width:220px">
                <select name="estado">
                    <option value="">Todos los estados</option>
                    <?php foreach (['pendiente' => 'Pendiente', 'aprobado' => 'Aprobado', 'rechazado' => 'Rechazado', 'anulado' => 'Anulado', 'error' => 'Error'] as $v => $l): ?>
                    <option value="<?= $v ?>" <?= $estado === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-primary btn-sm">Filtrar</button>
                <?php if ($busqueda !== '' || $estado !== ''): ?>
                <a href="/admin/pedidos.php" class="btn-secondary btn-sm">Limpiar</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <div class="table-wrap">
        <?php if (empty($pedidos)): ?>
        <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
            <p>No se encontraron pedidos.</p>
        </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Referencia</th>
                    <th>Cliente</th>
                    <th>Email</th>
                    <th>Teléfono</th>
                    <th>Ciudad</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th>Fecha</th>
                    <th>Items</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pedidos as $p):
                $stItems = $pdo->prepare("SELECT nombre_producto, cantidad, precio FROM pedido_items WHERE pedido_id = :id");
                $stItems->execute([':id' => $p['id']]);
                $items    = $stItems->fetchAll(PDO::FETCH_ASSOC);
                $estado_p = strtolower($p['estado']);
                $cls      = in_array($estado_p, ['aprobado', 'pendiente', 'rechazado', 'anulado', 'error']) ? $estado_p : 'pendiente';
            ?>
                <tr>
                    <td><?= (int)$p['id'] ?></td>
                    <td><code style="font-size:.78rem"><?= htmlspecialchars($p['wompi_referencia'] ?? '—') ?></code></td>
                    <td><?= htmlspecialchars($p['nombre']) ?></td>
                    <td><?= htmlspecialchars($p['email']) ?></td>
                    <td><?= htmlspecialchars($p['telefono'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($p['ciudad'] ?? '—') ?></td>
                    <td><strong>$<?= number_format((float)$p['total'], 0, ',', '.') ?></strong></td>
                    <td><span class="badge badge-<?= $cls ?>"><?= ucfirst($p['estado']) ?></span></td>
                    <td><?= date('d/m/Y H:i', strtotime($p['creado_en'])) ?></td>
                    <td>
                        <details>
                            <summary style="cursor:pointer;color:var(--info);font-size:.8rem"><?= count($items) ?> item(s)</summary>
                            <ul style="margin:.5rem 0 0 1rem;font-size:.8rem">
                            <?php foreach ($items as $it): ?>
                                <li><?= htmlspecialchars($it['nombre_producto']) ?> &times;<?= (int)$it['cantidad'] ?> &mdash; $<?= number_format((float)$it['precio'], 0, ',', '.') ?></li>
                            <?php endforeach; ?>
                            </ul>
                        </details>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    <?php if ($totalPags > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $totalPags; $i++): ?>
            <?php $qs = http_build_query(array_merge($_GET, ['pag' => $i])); ?>
            <a href="?<?= $qs ?>" class="<?= $i === $pagina ? 'activo-pag' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/_foot.php'; ?>
