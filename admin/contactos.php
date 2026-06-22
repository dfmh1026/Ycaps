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
if ($busqueda !== '') {
    $where .= ' AND (nombre LIKE :q OR email LIKE :q OR mensaje LIKE :q)';
    $params[':q'] = '%' . $busqueda . '%';
}

$stTotal = $pdo->prepare("SELECT COUNT(*) FROM mensajes_contacto WHERE {$where}");
$stTotal->execute($params);
$total     = (int) $stTotal->fetchColumn();
$totalPags = max(1, (int) ceil($total / $porPag));
$offset    = ($pagina - 1) * $porPag;

$st = $pdo->prepare(
    "SELECT id, nombre, email, telefono, mensaje, creado_en
     FROM mensajes_contacto WHERE {$where}
     ORDER BY creado_en DESC LIMIT {$porPag} OFFSET {$offset}"
);
$st->execute($params);
$mensajes = $st->fetchAll(PDO::FETCH_ASSOC);

$titulo = 'Mensajes de contacto';
$pag    = 'contactos';
require __DIR__ . '/_head.php';
?>

<div class="card">
    <div class="card-header">
        <h2>Mensajes de contacto (<?= $total ?>)</h2>
        <div class="card-header-actions">
            <form method="GET" style="display:flex;gap:.5rem">
                <input type="text" name="q" placeholder="Buscar por nombre, email o mensaje..." value="<?= htmlspecialchars($busqueda) ?>" style="width:260px">
                <button type="submit" class="btn-primary btn-sm">Buscar</button>
                <?php if ($busqueda !== ''): ?>
                <a href="/admin/contactos.php" class="btn-secondary btn-sm">Limpiar</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="table-wrap">
        <?php if (empty($mensajes)): ?>
        <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
            </svg>
            <p>Todavía no hay mensajes de contacto.</p>
        </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Teléfono</th>
                    <th>Mensaje</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($mensajes as $m): ?>
                <tr>
                    <td><?= htmlspecialchars($m['nombre']) ?></td>
                    <td><a href="mailto:<?= htmlspecialchars($m['email']) ?>"><?= htmlspecialchars($m['email']) ?></a></td>
                    <td><?= htmlspecialchars($m['telefono'] ?: '—') ?></td>
                    <td style="max-width:380px;white-space:pre-wrap"><?= htmlspecialchars($m['mensaje']) ?></td>
                    <td style="white-space:nowrap"><?= date('d/m/Y H:i', strtotime($m['creado_en'])) ?></td>
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
