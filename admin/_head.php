<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ycaps Admin — <?= htmlspecialchars($titulo ?? 'Panel') ?></title>
<link rel="stylesheet" href="/admin/admin.css">
</head>
<body class="admin-body">
<aside class="sidebar">
    <div class="sidebar-brand">
        <span class="brand-y">Y</span><span class="brand-caps">CAPS</span>
        <small>Admin</small>
    </div>
    <nav class="sidebar-nav">
        <a href="/admin/dashboard.php"  class="nav-item <?= ($pag ?? '') === 'dashboard'  ? 'activo' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            Dashboard
        </a>
        <a href="/admin/pedidos.php"    class="nav-item <?= ($pag ?? '') === 'pedidos'    ? 'activo' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="13" y2="16"/></svg>
            Pedidos
        </a>
        <a href="/admin/clientes.php"   class="nav-item <?= ($pag ?? '') === 'clientes'   ? 'activo' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            Clientes
        </a>
        <a href="/admin/ventas.php"     class="nav-item <?= ($pag ?? '') === 'ventas'     ? 'activo' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
            Ventas
        </a>
        <a href="/admin/inventario.php" class="nav-item <?= ($pag ?? '') === 'inventario' ? 'activo' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2Z"/><path d="M16 7V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2"/></svg>
            Inventario
        </a>
        <a href="/admin/contactos.php"  class="nav-item <?= ($pag ?? '') === 'contactos'  ? 'activo' : '' ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            Mensajes
        </a>
    </nav>
    <div class="sidebar-footer">
        <a href="/" target="_blank" class="nav-item">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
            Ver tienda
        </a>
        <a href="/admin/logout.php" class="nav-item nav-logout">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Salir
        </a>
    </div>
</aside>
<main class="admin-main">
<div class="admin-topbar">
    <h1 class="page-title"><?= htmlspecialchars($titulo ?? '') ?></h1>
    <span class="topbar-date"><?= date('d \d\e F \d\e Y', time()) ?></span>
</div>
<div class="admin-content">
