<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/load_config.php';

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '-05:00'"]
);

$totalPedidos     = (int) $pdo->query("SELECT COUNT(*) FROM pedidos")->fetchColumn();
$pedidosAprobados = (int) $pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado='aprobado'")->fetchColumn();
$ingresos         = (float) $pdo->query("SELECT COALESCE(SUM(total),0) FROM pedidos WHERE estado='aprobado'")->fetchColumn();
$totalProductos   = (int) $pdo->query("SELECT COUNT(*) FROM productos WHERE activo=1")->fetchColumn();
$stockBajo        = (int) $pdo->query("SELECT COUNT(*) FROM productos WHERE stock<=3 AND activo=1")->fetchColumn();

$recientes = $pdo->query(
    "SELECT id, wompi_referencia, nombre, total, estado, creado_en
     FROM pedidos ORDER BY creado_en DESC LIMIT 10"
)->fetchAll(PDO::FETCH_ASSOC);

$titulo = 'Dashboard';
$pag    = 'dashboard';
require __DIR__ . '/_head.php';
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon blue">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
        </div>
        <div class="stat-info">
            <p>Total pedidos</p>
            <h2><?= $totalPedidos ?></h2>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <div class="stat-info">
            <p>Pedidos aprobados</p>
            <h2><?= $pedidosAprobados ?></h2>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon yellow">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
        </div>
        <div class="stat-info">
            <p>Ingresos totales</p>
            <h2>$<?= number_format($ingresos, 0, ',', '.') ?></h2>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon <?= $stockBajo > 0 ? 'red' : 'green' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2Z"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
        </div>
        <div class="stat-info">
            <p>Productos activos / stock bajo</p>
            <h2><?= $totalProductos ?> <small style="font-size:.9rem;color:<?= $stockBajo > 0 ? 'var(--danger)' : 'var(--muted)' ?>">/ <?= $stockBajo ?></small></h2>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2>Pedidos recientes</h2>
        <a href="/admin/pedidos.php" class="btn-secondary btn-sm">Ver todos</a>
    </div>
    <div class="table-wrap">
        <?php if (empty($recientes)): ?>
        <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
            <p>Aún no hay pedidos registrados.</p>
        </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Referencia</th>
                    <th>Cliente</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recientes as $p):
                $e   = strtolower($p['estado']);
                $cls = in_array($e, ['aprobado','pendiente','rechazado','anulado','error']) ? $e : 'pendiente';
                $label = ucfirst($p['estado']);
            ?>
                <tr>
                    <td><?= $p['id'] ?></td>
                    <td><code style="font-size:.8rem"><?= htmlspecialchars($p['wompi_referencia'] ?? '—') ?></code></td>
                    <td><?= htmlspecialchars($p['nombre']) ?></td>
                    <td>$<?= number_format($p['total'], 0, ',', '.') ?></td>
                    <td><span class="badge badge-<?= $cls ?>"><?= $label ?></span></td>
                    <td><?= date('d/m/Y H:i', strtotime($p['creado_en'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/_foot.php'; ?>
