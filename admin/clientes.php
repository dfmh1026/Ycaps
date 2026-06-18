<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/load_config.php';

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$busqueda = trim($_GET['q'] ?? '');
$pagina   = max(1, (int)($_GET['pag'] ?? 1));
$porPag   = 25;

$where  = '1=1';
$params = [];
if ($busqueda) {
    $where .= ' AND (email LIKE :q OR nombre LIKE :q)';
    $params[':q'] = "%$busqueda%";
}

$stTotal = $pdo->prepare("SELECT COUNT(DISTINCT email) FROM pedidos WHERE $where");
$stTotal->execute($params);
$total     = (int) $stTotal->fetchColumn();
$totalPags = max(1, (int) ceil($total / $porPag));
$offset    = ($pagina - 1) * $porPag;

$st = $pdo->prepare(
    "SELECT
        email,
        MAX(nombre)   AS nombre,
        MAX(cedula)   AS cedula,
        MAX(telefono) AS telefono,
        MAX(ciudad)   AS ciudad,
        MAX(departamento) AS departamento,
        COUNT(*)      AS total_pedidos,
        SUM(CASE WHEN estado='aprobado' THEN total ELSE 0 END) AS total_gastado,
        MAX(creado_en) AS ultimo_pedido
     FROM pedidos WHERE $where
     GROUP BY email
     ORDER BY ultimo_pedido DESC
     LIMIT $porPag OFFSET $offset"
);
$st->execute($params);
$clientes = $st->fetchAll(PDO::FETCH_ASSOC);

$titulo = 'Clientes';
$pag    = 'clientes';
require __DIR__ . '/_head.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Clientes (<?= $total ?>)</h2>
        <div class="card-header-actions">
            <form method="GET" style="display:flex;gap:.5rem">
                <input type="text" name="q" placeholder="Buscar por nombre o email..." value="<?= htmlspecialchars($busqueda) ?>" style="width:240px">
                <button type="submit" class="btn-primary btn-sm">Buscar</button>
                <?php if ($busqueda): ?>
                <a href="/admin/clientes.php" class="btn-secondary btn-sm">Limpiar</a>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <div class="table-wrap">
        <?php if (empty($clientes)): ?>
        <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            <p>No se encontraron clientes.</p>
        </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Teléfono</th>
                    <th>Ciudad</th>
                    <th>Pedidos</th>
                    <th>Total gastado</th>
                    <th>Último pedido</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($clientes as $c): ?>
                <tr>
                    <td><?= htmlspecialchars($c['nombre']) ?><?php if ($c['cedula'] ?? ''): ?><br><small style="color:var(--muted)">CC: <?= htmlspecialchars($c['cedula']) ?></small><?php endif; ?></td>
                    <td><?= htmlspecialchars($c['email']) ?></td>
                    <td><?= htmlspecialchars($c['telefono'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($c['ciudad'] ?? '—') ?><?php if ($c['departamento'] ?? ''): ?><br><small style="color:var(--muted)"><?= htmlspecialchars($c['departamento']) ?></small><?php endif; ?></td>
                    <td><strong><?= $c['total_pedidos'] ?></strong></td>
                    <td>$<?= number_format($c['total_gastado'], 0, ',', '.') ?></td>
                    <td><?= date('d/m/Y', strtotime($c['ultimo_pedido'])) ?></td>
                    <td>
                        <a href="/admin/pedidos.php?q=<?= urlencode($c['email']) ?>" class="btn-secondary btn-sm">Ver pedidos</a>
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
            <?php $q = http_build_query(array_merge($_GET, ['pag' => $i])); ?>
            <a href="?<?= $q ?>" class="<?= $i === $pagina ? 'activo-pag' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/_foot.php'; ?>
