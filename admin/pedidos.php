<?php
require __DIR__ . '/auth.php';
require __DIR__ . '/load_config.php';

$pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER, DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$mensaje  = '';
$tipoMsg  = 'success';

// ── Guardar guía de envío ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guia') {
    $pedidoId = (int) ($_POST['pedido_id'] ?? 0);
    $guia     = trim($_POST['guia_envio'] ?? '');

    if ($pedidoId > 0 && $guia !== '') {
        $stmtActual = $pdo->prepare("SELECT guia_envio FROM pedidos WHERE id = :id LIMIT 1");
        $stmtActual->execute([':id' => $pedidoId]);
        $guiaAnterior = $stmtActual->fetchColumn();

        $pdo->prepare("UPDATE pedidos SET guia_envio = :g WHERE id = :id")
            ->execute([':g' => $guia, ':id' => $pedidoId]);

        if ($guiaAnterior !== $guia) {
            $pdo->prepare("INSERT INTO pedido_guia_historial (pedido_id, guia_envio) VALUES (:id, :g)")
                ->execute([':id' => $pedidoId, ':g' => $guia]);
        }

        // Enviar correo al cliente con el número de guía
        $stmtP = $pdo->prepare("SELECT nombre, email, wompi_referencia FROM pedidos WHERE id = :id LIMIT 1");
        $stmtP->execute([':id' => $pedidoId]);
        $ped = $stmtP->fetch(PDO::FETCH_ASSOC);

        if ($ped && $ped['email'] !== '') {
            require_once __DIR__ . '/../wompi/mailer.php';
            enviarEmailGuiaEnvio($ped['email'], $ped['nombre'], $ped['wompi_referencia'], $guia);
            $mensaje = "Guía <strong>{$guia}</strong> guardada. Correo enviado a {$ped['email']}.";
        } else {
            $mensaje = "Guía guardada (no se pudo enviar correo: email no encontrado).";
        }
    } else {
        $mensaje = 'Escribe un número de guía válido.';
        $tipoMsg = 'error';
    }
}

// ── Consulta de pedidos ───────────────────────────────────────────────────────
$estado   = $_GET['estado']   ?? '';
$busqueda = trim($_GET['q']   ?? '');
$pagina   = max(1, (int)($_GET['pag'] ?? 1));
$porPag   = 20;

$where  = '1=1';
$params = [];
if ($estado !== '')   { $where .= ' AND estado = :estado'; $params[':estado'] = $estado; }
if ($busqueda !== '') {
    $where .= ' AND (wompi_referencia LIKE :q OR nombre LIKE :q OR email LIKE :q)';
    $params[':q'] = '%' . $busqueda . '%';
}

$stTotal = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE {$where}");
$stTotal->execute($params);
$total     = (int) $stTotal->fetchColumn();
$totalPags = max(1, (int) ceil($total / $porPag));
$offset    = ($pagina - 1) * $porPag;

$stPed = $pdo->prepare(
    "SELECT * FROM pedidos WHERE {$where} ORDER BY creado_en DESC LIMIT {$porPag} OFFSET {$offset}"
);
$stPed->execute($params);
$pedidos = $stPed->fetchAll(PDO::FETCH_ASSOC);

$titulo = 'Pedidos';
$pag    = 'pedidos';
require __DIR__ . '/_head.php';
?>

<?php if ($mensaje): ?>
<div class="alerta alerta-<?= $tipoMsg === 'error' ? 'error' : 'success' ?>" style="margin-bottom:1rem">
    <?= $mensaje ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>Todos los pedidos (<?= $total ?>)</h2>
        <div class="card-header-actions">
            <form method="GET" style="display:flex;gap:.5rem;flex-wrap:wrap">
                <input type="text" name="q" placeholder="Referencia, nombre o email..."
                       value="<?= htmlspecialchars($busqueda) ?>" style="width:220px">
                <select name="estado">
                    <option value="">Todos los estados</option>
                    <?php foreach (['pendiente' => 'Pendiente', 'aprobado' => 'Aprobado',
                                    'rechazado' => 'Rechazado', 'anulado'  => 'Anulado',
                                    'error'     => 'Error'] as $v => $l): ?>
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
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/>
                <rect x="9" y="3" width="6" height="4" rx="1"/>
            </svg>
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
                    <th>Ciudad</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th>Fecha</th>
                    <th>Detalle / Guía</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pedidos as $p):
                $stItems = $pdo->prepare("SELECT nombre_producto, cantidad, precio FROM pedido_items WHERE pedido_id = :id");
                $stItems->execute([':id' => $p['id']]);
                $items    = $stItems->fetchAll(PDO::FETCH_ASSOC);

                $stHist = $pdo->prepare("SELECT estado_anterior, estado_nuevo, origen, detalle, creado_en FROM pedido_estado_historial WHERE pedido_id = :id ORDER BY creado_en ASC");
                $stHist->execute([':id' => $p['id']]);
                $historial = $stHist->fetchAll(PDO::FETCH_ASSOC);

                $stHistGuia = $pdo->prepare("SELECT guia_envio, creado_en FROM pedido_guia_historial WHERE pedido_id = :id ORDER BY creado_en ASC");
                $stHistGuia->execute([':id' => $p['id']]);
                $historialGuia = $stHistGuia->fetchAll(PDO::FETCH_ASSOC);

                $estado_p = strtolower($p['estado']);
                $cls      = in_array($estado_p, ['aprobado','pendiente','rechazado','anulado','error'])
                            ? $estado_p : 'pendiente';
                $guiaActual = $p['guia_envio'] ?? '';
            ?>
                <tr>
                    <td><?= (int)$p['id'] ?></td>
                    <td><code style="font-size:.78rem"><?= htmlspecialchars($p['wompi_referencia'] ?? '—') ?></code></td>
                    <td>
                        <?= htmlspecialchars($p['nombre']) ?>
                        <?php if ($p['cedula'] ?? ''): ?>
                        <br><small style="color:var(--muted)">CC: <?= htmlspecialchars($p['cedula']) ?></small>
                        <?php endif; ?>
                        <?php if ($p['telefono'] ?? ''): ?>
                        <br><small style="color:var(--muted)"><?= htmlspecialchars($p['telefono']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($p['email']) ?></td>
                    <td><?= htmlspecialchars($p['ciudad'] ?? '—') ?><?php if ($p['departamento'] ?? ''): ?><br><small style="color:var(--muted)"><?= htmlspecialchars($p['departamento']) ?></small><?php endif; ?></td>
                    <td><strong>$<?= number_format((float)$p['total'], 0, ',', '.') ?></strong></td>
                    <td><span class="badge badge-<?= $cls ?>"><?= ucfirst($p['estado']) ?></span></td>
                    <td style="white-space:nowrap"><?= date('d/m/Y H:i', strtotime($p['creado_en'])) ?></td>
                    <td>
                        <details>
                            <summary style="cursor:pointer;color:var(--info);font-size:.8rem">
                                <?= count($items) ?> item(s)
                                <?php if ($guiaActual !== ''): ?>
                                · <span style="color:var(--success)">Guía: <?= htmlspecialchars($guiaActual) ?></span>
                                <?php endif; ?>
                            </summary>

                            <ul style="margin:.5rem 0 .75rem 1rem;font-size:.8rem">
                            <?php foreach ($items as $it): ?>
                                <li>
                                    <?= htmlspecialchars($it['nombre_producto']) ?>
                                    &times;<?= (int)$it['cantidad'] ?>
                                    &mdash; $<?= number_format((float)$it['precio'], 0, ',', '.') ?>
                                </li>
                            <?php endforeach; ?>
                            </ul>

                            <?php if ($p['direccion'] ?? ''): ?>
                            <p style="font-size:.78rem;color:var(--muted);margin-bottom:.75rem">
                                📍 <?= htmlspecialchars($p['direccion']) ?>, <?= htmlspecialchars($p['ciudad'] ?? '') ?><?= ($p['departamento'] ?? '') !== '' ? ', ' . htmlspecialchars($p['departamento']) : '' ?>
                            </p>
                            <?php endif; ?>

                            <!-- Historial de estados -->
                            <?php if (!empty($historial)): ?>
                            <p class="rastreo-subtitulo" style="font-size:.78rem;color:var(--muted);margin-bottom:.3rem">Historial de estado:</p>
                            <ul style="margin:0 0 .75rem 1rem;font-size:.75rem;color:var(--muted)">
                                <?php foreach ($historial as $h): ?>
                                <li>
                                    <?= date('d/m/Y H:i', strtotime($h['creado_en'])) ?> —
                                    <?= $h['estado_anterior'] ? htmlspecialchars($h['estado_anterior']) . ' → ' : '' ?><strong><?= htmlspecialchars($h['estado_nuevo']) ?></strong>
                                    <span style="opacity:.7">(<?= htmlspecialchars($h['origen']) ?><?= $h['detalle'] ? ': ' . htmlspecialchars($h['detalle']) : '' ?>)</span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>

                            <!-- Historial de guías -->
                            <?php if (!empty($historialGuia)): ?>
                            <p class="rastreo-subtitulo" style="font-size:.78rem;color:var(--muted);margin-bottom:.3rem">Historial de guías:</p>
                            <ul style="margin:0 0 .75rem 1rem;font-size:.75rem;color:var(--muted)">
                                <?php foreach ($historialGuia as $hg): ?>
                                <li>
                                    <?= date('d/m/Y H:i', strtotime($hg['creado_en'])) ?> —
                                    <strong><?= htmlspecialchars($hg['guia_envio']) ?></strong>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>

                            <!-- Guía de envío -->
                            <form method="POST" class="guia-form">
                                <input type="hidden" name="accion" value="guia">
                                <input type="hidden" name="pedido_id" value="<?= (int)$p['id'] ?>">
                                <div style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap;padding:.5rem 0 .25rem">
                                    <input type="text"
                                           name="guia_envio"
                                           value="<?= htmlspecialchars($guiaActual) ?>"
                                           placeholder="Número de guía (ej: TCC-123456)"
                                           style="width:220px;font-size:.8rem;padding:.3rem .6rem">
                                    <button type="submit" class="btn-primary btn-sm" style="white-space:nowrap">
                                        <?= $guiaActual !== '' ? '↺ Actualizar y reenviar' : '✉ Guardar y notificar' ?>
                                    </button>
                                </div>
                                <?php if ($guiaActual !== ''): ?>
                                <p style="font-size:.75rem;color:var(--muted);margin:.2rem 0 0">
                                    Guía actual: <strong><?= htmlspecialchars($guiaActual) ?></strong>
                                </p>
                                <?php endif; ?>
                            </form>
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
