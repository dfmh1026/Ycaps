<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/load_config.php';

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '-05:00'"]
);

// Rango de fechas: por defecto, los últimos 30 días
$hoy    = date('Y-m-d');
$desde  = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
$hasta  = $_GET['hasta'] ?? $hoy;
$pagina = max(1, (int) ($_GET['pag'] ?? 1));
$porPag = 25;

// Validar formato de fecha simple para evitar valores raros
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) $desde = date('Y-m-d', strtotime('-30 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) $hasta = $hoy;

$params = [
    ':desde' => $desde . ' 00:00:00',
    ':hasta' => $hasta . ' 23:59:59',
];

$where = "estado = 'aprobado' AND creado_en BETWEEN :desde AND :hasta";

$stResumen = $pdo->prepare(
    "SELECT COUNT(*) AS cantidad, COALESCE(SUM(total),0) AS ingresos
     FROM pedidos WHERE {$where}"
);
$stResumen->execute($params);
$resumen = $stResumen->fetch(PDO::FETCH_ASSOC);

$totalVentas  = (int) $resumen['cantidad'];
$totalIngresos = (float) $resumen['ingresos'];
$promedio     = $totalVentas > 0 ? $totalIngresos / $totalVentas : 0;

$totalPags = max(1, (int) ceil($totalVentas / $porPag));
$offset    = ($pagina - 1) * $porPag;

$stVentas = $pdo->prepare(
    "SELECT id, wompi_referencia, nombre, ciudad, departamento, metodo_pago, total, creado_en
     FROM pedidos WHERE {$where}
     ORDER BY creado_en DESC
     LIMIT {$porPag} OFFSET {$offset}"
);
$stVentas->execute($params);
$ventas = $stVentas->fetchAll(PDO::FETCH_ASSOC);

$titulo = 'Ventas';
$pag    = 'ventas';
require __DIR__ . '/_head.php';
?>

<div class="card" style="margin-bottom:1.5rem">
    <div class="card-header">
        <h2>Filtrar por fecha</h2>
    </div>
    <form method="GET" style="display:flex;gap:.75rem;align-items:end;flex-wrap:wrap;padding:0 0 .5rem">
        <div>
            <label style="display:block;font-size:.8rem;color:var(--muted);margin-bottom:.3rem">Desde</label>
            <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>">
        </div>
        <div>
            <label style="display:block;font-size:.8rem;color:var(--muted);margin-bottom:.3rem">Hasta</label>
            <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>">
        </div>
        <button type="submit" class="btn-primary btn-sm">Consultar</button>
        <a href="/admin/ventas.php" class="btn-secondary btn-sm">Últimos 30 días</a>
    </form>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div class="stat-info">
            <p>Ventas en el rango</p>
            <h2><?= $totalVentas ?></h2>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div class="stat-info">
            <p>Ingresos del rango</p>
            <h2>$<?= number_format($totalIngresos, 0, ',', '.') ?></h2>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        </div>
        <div class="stat-info">
            <p>Promedio por venta</p>
            <h2>$<?= number_format($promedio, 0, ',', '.') ?></h2>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Detalle de ventas (<?= $totalVentas ?>)</h2>
    </div>
    <div class="table-wrap">
        <?php if (empty($ventas)): ?>
        <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            <p>No hay ventas aprobadas en este rango de fechas.</p>
        </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Referencia</th>
                    <th>Cliente</th>
                    <th>Destino</th>
                    <th>Método de pago</th>
                    <th>Total</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($ventas as $v): ?>
                <tr>
                    <td><?= (int) $v['id'] ?></td>
                    <td><code style="font-size:.78rem"><?= htmlspecialchars($v['wompi_referencia'] ?? '—') ?></code></td>
                    <td><?= htmlspecialchars($v['nombre']) ?></td>
                    <td><?= htmlspecialchars($v['ciudad'] ?? '—') ?><?php if ($v['departamento'] ?? ''): ?>, <?= htmlspecialchars($v['departamento']) ?><?php endif; ?></td>
                    <td><?= htmlspecialchars(ucfirst($v['metodo_pago'] ?? 'wompi')) ?></td>
                    <td><strong>$<?= number_format((float) $v['total'], 0, ',', '.') ?></strong></td>
                    <td style="white-space:nowrap"><?= date('d/m/Y H:i', strtotime($v['creado_en'])) ?></td>
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
