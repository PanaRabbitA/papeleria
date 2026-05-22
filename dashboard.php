<?php
/**
 * Dashboard — Papelería Admin System (SPA-like)
 *
 * All module views are rendered client-side via JavaScript.
 * Data is fetched from api.php via AJAX with CSRF tokens.
 */
require_once __DIR__ . '/includes/auth.php';
Auth::requireAuth();

$csrfToken = Auth::generateCSRFToken();
$userName  = Auth::sanitize($_SESSION['nombre'] ?? '');
$userRole  = Auth::sanitize($_SESSION['rol'] ?? '');
$isAdmin   = Auth::isAdmin();
$initials  = mb_strtoupper(mb_substr($userName, 0, 2));

$pdo = Database::getInstance()->getConnection();
$stmt = $pdo->query("SELECT valor FROM configuracion WHERE clave='papeleria_logo'");
$logoApp = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Panel de Administración — Papelería Admin">
    <title>Papelería Admin — Panel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<div class="app-layout" id="app">

    <!-- ── Sidebar ────────────────────────────────────────────── -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <?php if ($logoApp): ?>
                    <img src="<?= htmlspecialchars($logoApp) ?>" alt="Logo" style="width:100%;height:100%;object-fit:contain;border-radius:var(--radius-sm)">
                <?php else: ?>
                    <i class="fas fa-store"></i>
                <?php endif; ?>
            </div>
            <div class="brand-text">
                <h2>Papelería</h2>
                <span>Sistema Admin</span>
            </div>
        </div>

        <nav class="sidebar-nav" id="sidebar-nav">
            <div class="nav-section">Principal</div>
            <div class="nav-item active" data-module="dashboard" id="nav-dashboard">
                <i class="fas fa-chart-pie"></i> Dashboard
            </div>
            <div class="nav-item" data-module="ventas" id="nav-ventas">
                <i class="fas fa-cash-register"></i> Punto de Venta
            </div>

            <div class="nav-section">Inventario</div>
            <div class="nav-item" data-module="productos" id="nav-productos">
                <i class="fas fa-boxes-stacked"></i> Productos
                <span class="badge" id="badge-stock" style="display:none"></span>
            </div>
            <div class="nav-item" data-module="categorias" id="nav-categorias">
                <i class="fas fa-tags"></i> Categorías
            </div>

            <div class="nav-section">Contactos</div>
            <div class="nav-item" data-module="proveedores" id="nav-proveedores">
                <i class="fas fa-truck"></i> Proveedores
            </div>
            <div class="nav-item" data-module="clientes" id="nav-clientes">
                <i class="fas fa-users"></i> Clientes
            </div>

            <div class="nav-section">Reportes</div>
            <div class="nav-item" data-module="historial" id="nav-historial">
                <i class="fas fa-receipt"></i> Historial de Ventas
            </div>

            <?php if ($isAdmin): ?>
            <div class="nav-section">Administración</div>
            <div class="nav-item" data-module="usuarios" id="nav-usuarios">
                <i class="fas fa-user-shield"></i> Usuarios
            </div>
            <div class="nav-item" data-module="configuracion" id="nav-configuracion">
                <i class="fas fa-cog"></i> Configuración
            </div>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?= $initials ?></div>
                <div class="user-details">
                    <div class="user-name"><?= $userName ?></div>
                    <div class="user-role"><?= $userRole ?></div>
                </div>
                <a href="logout.php" class="logout-btn" title="Cerrar sesión">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </aside>

    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <!-- ── Main ───────────────────────────────────────────────── -->
    <main class="main-content">
        <header class="app-header">
            <div style="display:flex;align-items:center;gap:.75rem">
                <button class="mobile-toggle" id="mobile-toggle"><i class="fas fa-bars"></i></button>
                <span class="page-title" id="page-title">Dashboard</span>
            </div>
            <div class="header-actions">
                <span style="font-size:.75rem;color:var(--text-muted)" id="header-date"></span>
            </div>
        </header>

        <section class="page-content" id="page-content">
            <!-- Content injected by JS -->
            <div class="loading-overlay"><div class="spinner"></div> Cargando…</div>
        </section>
    </main>
</div>

<!-- ── Toast Container ────────────────────────────────────────── -->
<div class="toast-container" id="toast-container"></div>

<!-- ── Modal Container ────────────────────────────────────────── -->
<div class="modal-overlay" id="modal-overlay">
    <div class="modal" id="modal">
        <div class="modal-header">
            <h3 id="modal-title"></h3>
            <button class="modal-close" id="modal-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" id="modal-body"></div>
        <div class="modal-footer" id="modal-footer"></div>
    </div>
</div>

<!-- ── Confirm Dialog ─────────────────────────────────────────── -->
<div class="modal-overlay confirm-dialog" id="confirm-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-triangle-exclamation"></i> Confirmar</h3>
            <button class="modal-close" onclick="closeConfirm()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="confirm-message">
                <i class="fas fa-exclamation-triangle"></i>
                <p id="confirm-message"></p>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="closeConfirm()">Cancelar</button>
            <button class="btn btn-danger" id="confirm-btn">Confirmar</button>
        </div>
    </div>
</div>

<!-- ── Barcode Scanner Overlay ─────────────────────────────────── -->
<div class="modal-overlay" id="scanner-overlay" style="z-index:1100">
    <div class="modal" style="max-width:500px">
        <div class="modal-header">
            <h3><i class="fas fa-barcode"></i> Escáner de Código de Barras</h3>
            <button class="modal-close" onclick="stopScanner()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body" style="padding:0">
            <div id="scanner-reader" style="width:100%"></div>
            
            <div id="scanner-fallback" style="display:none;padding:2rem;text-align:center">
                <p style="color:var(--warning);margin-bottom:1rem;font-size:.9rem">
                    <i class="fas fa-exclamation-triangle"></i> La cámara requiere HTTPS.
                </p>
                <button class="btn btn-primary" onclick="document.getElementById('scanner-file').click()">
                    <i class="fas fa-camera"></i> Tomar Foto del Código
                </button>
                <input type="file" id="scanner-file" accept="image/*" capture="environment" style="display:none" onchange="scanImage(event)">
            </div>

            <div style="padding:1rem;text-align:center">
                <p style="font-size:.85rem;color:var(--text-muted);margin-bottom:.75rem" id="scanner-help-text">
                    <i class="fas fa-camera"></i> Apunte la cámara al código de barras del producto
                </p>
                <div id="scanner-result" style="display:none;padding:.5rem;background:var(--success-bg);border-radius:var(--radius-sm);color:var(--success);font-weight:600"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-ghost" onclick="stopScanner()">Cerrar</button>
        </div>
    </div>
</div>

<script>
/* ═══════════════════════════════════════════════════════════════════
   PAPELERÍA ADMIN — Client Application
   ═══════════════════════════════════════════════════════════════════ */

const CSRF = '<?= $csrfToken ?>';
const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;

// ── Utility Helpers ────────────────────────────────────────────────
function $(sel) { return document.querySelector(sel); }
function $$(sel) { return document.querySelectorAll(sel); }

function formatMoney(n) {
    return '$' + parseFloat(n).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function escapeHtml(text) {
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}
function formatDate(d) {
    if (!d) return '-';
    return new Date(d).toLocaleDateString('es-MX', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' });
}

// ── Toast ──────────────────────────────────────────────────────────
function toast(msg, type = 'success') {
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    const icon = type === 'success' ? 'check-circle' : type === 'error' ? 'circle-xmark' : 'triangle-exclamation';
    t.innerHTML = `<i class="fas fa-${icon}"></i><span>${escapeHtml(msg)}</span>
                   <button class="toast-close" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>`;
    $('#toast-container').appendChild(t);
    setTimeout(() => t.remove(), 4000);
}

// ── Modal ──────────────────────────────────────────────────────────
function openModal(title, bodyHtml, footerHtml = '') {
    $('#modal-title').innerHTML = title;
    $('#modal-body').innerHTML = bodyHtml;
    $('#modal-footer').innerHTML = footerHtml;
    $('#modal-overlay').classList.add('active');
}
function closeModal() {
    $('#modal-overlay').classList.remove('active');
}
$('#modal-close').addEventListener('click', closeModal);
$('#modal-overlay').addEventListener('click', e => { if (e.target === e.currentTarget) closeModal(); });

// ── Confirm Dialog ─────────────────────────────────────────────────
let confirmCallback = null;
function openConfirm(msg, callback) {
    $('#confirm-message').textContent = msg;
    confirmCallback = callback;
    $('#confirm-overlay').classList.add('active');
}
function closeConfirm() {
    $('#confirm-overlay').classList.remove('active');
    confirmCallback = null;
}
$('#confirm-btn').addEventListener('click', () => {
    if (confirmCallback) confirmCallback();
    closeConfirm();
});
$('#confirm-overlay').addEventListener('click', e => { if (e.target === e.currentTarget) closeConfirm(); });

// ── API Helper ─────────────────────────────────────────────────────
async function api(module, action, params = {}, method = 'GET') {
    let url = `api.php?module=${module}&action=${action}`;
    const opts = { headers: { 'X-Requested-With': 'XMLHttpRequest' } };

    if (method === 'GET') {
        const qs = new URLSearchParams(params).toString();
        if (qs) url += '&' + qs;
    } else if (method === 'POST') {
        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        Object.entries(params).forEach(([k, v]) => fd.append(k, v));
        opts.method = 'POST';
        opts.body = fd;
    } else if (method === 'JSON') {
        opts.method = 'POST';
        opts.headers['Content-Type'] = 'application/json';
        opts.headers['X-CSRF-TOKEN'] = CSRF;
        opts.body = JSON.stringify({ ...params, csrf_token: CSRF });
    }

    const res = await fetch(url, opts);
    if (res.status === 401) { window.location.href = 'index.php'; return null; }
    return res.json();
}

// ── Navigation ─────────────────────────────────────────────────────
const pageTitles = {
    dashboard: 'Dashboard',
    productos: 'Productos',
    categorias: 'Categorías',
    proveedores: 'Proveedores',
    clientes: 'Clientes',
    ventas: 'Punto de Venta',
    historial: 'Historial de Ventas',
    usuarios: 'Gestión de Usuarios',
    configuracion: 'Configuración General',
};

let currentModule = 'dashboard';

function navigate(module) {
    currentModule = module;
    $$('.nav-item').forEach(n => n.classList.remove('active'));
    const navItem = document.querySelector(`.nav-item[data-module="${module}"]`);
    if (navItem) navItem.classList.add('active');
    $('#page-title').textContent = pageTitles[module] || module;

    // Close sidebar on mobile
    $('#sidebar').classList.remove('open');

    // Load module
    const loaders = {
        dashboard: loadDashboard,
        productos: loadProductos,
        categorias: loadCategorias,
        proveedores: loadProveedores,
        clientes: loadClientes,
        ventas: loadVentas,
        historial: loadHistorial,
        usuarios: loadUsuarios,
        configuracion: loadConfiguracion,
    };
    const loader = loaders[module];
    if (loader) loader();
}

// Nav click handlers
$$('.nav-item').forEach(item => {
    item.addEventListener('click', () => navigate(item.dataset.module));
});

// Mobile sidebar
$('#mobile-toggle').addEventListener('click', () => $('#sidebar').classList.toggle('open'));
$('#sidebar-overlay').addEventListener('click', () => $('#sidebar').classList.remove('open'));

// Header date
$('#header-date').textContent = new Date().toLocaleDateString('es-MX', { weekday:'long', day:'numeric', month:'long', year:'numeric' });

/* ═══════════════════════════════════════════════════════════════════
   MODULE: DASHBOARD
   ═══════════════════════════════════════════════════════════════════ */
async function loadDashboard() {
    const c = $('#page-content');
    c.innerHTML = '<div class="loading-overlay"><div class="spinner"></div> Cargando dashboard…</div>';

    const data = await api('dashboard', 'stats');
    if (!data) return;

    // Update low-stock badge
    const badge = $('#badge-stock');
    if (data.stock_bajo > 0) {
        badge.style.display = '';
        badge.textContent = data.stock_bajo;
    } else {
        badge.style.display = 'none';
    }

    c.innerHTML = `
    <div class="fade-in">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-boxes-stacked"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Productos</div>
                    <div class="stat-value">${data.total_productos}</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red"><i class="fas fa-triangle-exclamation"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Stock Bajo</div>
                    <div class="stat-value">${data.stock_bajo}</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-cart-shopping"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Ventas Hoy</div>
                    <div class="stat-value">${data.ventas_hoy}</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon teal"><i class="fas fa-coins"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Ingresos Hoy</div>
                    <div class="stat-value">${formatMoney(data.ingresos_hoy)}</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange"><i class="fas fa-users"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Clientes</div>
                    <div class="stat-value">${data.total_clientes}</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-tags"></i></div>
                <div class="stat-info">
                    <div class="stat-label">Categorías</div>
                    <div class="stat-value">${data.total_categorias}</div>
                </div>
            </div>
        </div>

        <div class="grid-2" style="margin-bottom: 1.5rem">
            <div class="data-card">
                <div class="data-card-header">
                    <h3><i class="fas fa-chart-bar"></i> Ventas (Últimos 7 días)</h3>
                </div>
                <div class="table-wrapper" style="padding: 1rem;">
                    <canvas id="chart-ventas" height="250"></canvas>
                </div>
            </div>
            <div class="data-card">
                <div class="data-card-header">
                    <h3><i class="fas fa-chart-pie"></i> Productos por Categoría</h3>
                </div>
                <div class="table-wrapper" style="padding: 1rem;">
                    <canvas id="chart-categorias" height="250"></canvas>
                </div>
            </div>
        </div>

        <div class="grid-2">
            <div class="data-card">
                <div class="data-card-header">
                    <h3><i class="fas fa-receipt"></i> Ventas Recientes</h3>
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead><tr><th>Folio</th><th>Cliente</th><th>Total</th><th>Fecha</th></tr></thead>
                        <tbody>
                            ${data.ventas_recientes.length === 0
                                ? '<tr><td colspan="4" style="text-align:center;color:var(--text-muted)">Sin ventas recientes</td></tr>'
                                : data.ventas_recientes.map(v => `
                                    <tr>
                                        <td class="cell-code">${escapeHtml(v.folio)}</td>
                                        <td>${escapeHtml(v.cliente)}</td>
                                        <td class="cell-price">${formatMoney(v.total)}</td>
                                        <td>${formatDate(v.created_at)}</td>
                                    </tr>
                                `).join('')
                            }
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="data-card">
                <div class="data-card-header">
                    <h3><i class="fas fa-triangle-exclamation"></i> Productos con Stock Bajo</h3>
                </div>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead><tr><th>Código</th><th>Producto</th><th>Stock</th><th>Mínimo</th></tr></thead>
                        <tbody>
                            ${data.productos_stock_bajo.length === 0
                                ? '<tr><td colspan="4" style="text-align:center;color:var(--text-muted)">Todos los productos tienen stock suficiente</td></tr>'
                                : data.productos_stock_bajo.map(p => `
                                    <tr>
                                        <td class="cell-code">${escapeHtml(p.codigo)}</td>
                                        <td>${escapeHtml(p.nombre)}</td>
                                        <td class="cell-stock low">${p.stock}</td>
                                        <td>${p.stock_minimo}</td>
                                    </tr>
                                `).join('')
                            }
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>`;

    // Render charts
    if (data.ventas_semana && data.ventas_semana.length > 0) {
        new Chart(document.getElementById('chart-ventas').getContext('2d'), {
            type: 'bar',
            data: {
                labels: data.ventas_semana.map(v => v.fecha),
                datasets: [{
                    label: 'Ingresos ($)',
                    data: data.ventas_semana.map(v => v.ingresos),
                    backgroundColor: 'rgba(99, 102, 241, 0.7)',
                    borderColor: 'rgba(99, 102, 241, 1)',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    if (data.productos_por_categoria && data.productos_por_categoria.length > 0) {
        new Chart(document.getElementById('chart-categorias').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: data.productos_por_categoria.map(c => c.nombre),
                datasets: [{
                    data: data.productos_por_categoria.map(c => c.cantidad),
                    backgroundColor: ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#3b82f6', '#14b8a6', '#f97316', '#ec4899', '#64748b'],
                    borderWidth: 0
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }
}

/* ═══════════════════════════════════════════════════════════════════
   MODULE: PRODUCTOS
   ═══════════════════════════════════════════════════════════════════ */
async function loadProductos() {
    const c = $('#page-content');
    c.innerHTML = '<div class="loading-overlay"><div class="spinner"></div> Cargando productos…</div>';

    const [products, categories, suppliers] = await Promise.all([
        api('productos', 'list'),
        api('categorias', 'list'),
        api('proveedores', 'list'),
    ]);

    c.innerHTML = `
    <div class="fade-in">
        <div class="data-card">
            <div class="data-card-header">
                <h3><i class="fas fa-boxes-stacked"></i> Inventario de Productos</h3>
                <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input class="form-control" id="prod-search" placeholder="Buscar producto…" style="width:220px">
                    </div>
                    <select class="form-control" id="prod-cat-filter" style="width:180px">
                        <option value="">Todas las categorías</option>
                        ${categories.map(c => `<option value="${c.id}">${escapeHtml(c.nombre)}</option>`).join('')}
                    </select>
                    <a href="export.php?module=productos" class="btn btn-ghost btn-sm" title="Descargar CSV"><i class="fas fa-file-csv"></i> CSV</a>
                    <button class="btn btn-primary" onclick="openProductForm(null)"><i class="fas fa-plus"></i> Nuevo</button>
                </div>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:60px">Foto</th><th>Código</th><th>Producto</th><th>Categoría</th><th>P. Compra</th>
                            <th>P. Venta</th><th>Stock</th><th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="productos-tbody"></tbody>
                </table>
            </div>
        </div>
    </div>`;

    window._categories = categories;
    window._suppliers = suppliers;
    window._allProducts = products;
    renderProductTable(products);

    // Search & filter
    let searchTimeout;
    $('#prod-search').addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => filterProducts(), 300);
    });
    $('#prod-cat-filter').addEventListener('change', () => filterProducts());
}

async function filterProducts() {
    const search = $('#prod-search')?.value || '';
    const catId  = $('#prod-cat-filter')?.value || '';
    const products = await api('productos', 'list', { search, categoria_id: catId });
    renderProductTable(products);
}

function renderProductTable(products) {
    const tbody = $('#productos-tbody');
    if (!tbody) return;
    if (!products || products.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state"><i class="fas fa-box-open"></i><p>No se encontraron productos</p></div></td></tr>';
        return;
    }
    tbody.innerHTML = products.map(p => `
        <tr>
            <td>
                ${p.imagen ? `<img src="${p.imagen}" style="width:40px;height:40px;object-fit:cover;border-radius:4px">` : `<div style="width:40px;height:40px;background:var(--bg-tertiary);border-radius:4px;display:flex;align-items:center;justify-content:center;color:var(--text-muted)"><i class="fas fa-image"></i></div>`}
            </td>
            <td class="cell-code">${escapeHtml(p.codigo)}</td>
            <td><strong>${escapeHtml(p.nombre)}</strong><br><small style="color:var(--text-muted)">${escapeHtml(p.descripcion || '')}</small></td>
            <td><span class="badge-status info">${escapeHtml(p.categoria_nombre)}</span></td>
            <td>${formatMoney(p.precio_compra)}</td>
            <td class="cell-price">${formatMoney(p.precio_venta)}</td>
            <td><span class="cell-stock ${parseInt(p.stock) <= parseInt(p.stock_minimo) ? 'low' : 'ok'}">${p.stock}</span></td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-ghost btn-icon btn-sm" title="Editar" onclick="openProductForm(${p.id})"><i class="fas fa-pen"></i></button>
                    <button class="btn btn-ghost btn-icon btn-sm" title="Eliminar" onclick="deleteProduct(${p.id},'${escapeHtml(p.nombre)}')"><i class="fas fa-trash" style="color:var(--danger)"></i></button>
                </div>
            </td>
        </tr>
    `).join('');
}

function openProductForm(id) {
    const cats = window._categories || [];
    const supps = window._suppliers || [];
    const isEdit = id !== null;
    const title = isEdit ? '<i class="fas fa-pen"></i> Editar Producto' : '<i class="fas fa-plus"></i> Nuevo Producto';

    const formHtml = `
        <form id="product-form">
            <input type="hidden" name="id" id="pf-id" value="">
            <div class="form-row" style="margin-bottom:1rem">
                <div class="form-group" style="flex:1">
                    <label>Foto del Producto</label>
                    <input type="file" accept="image/*" class="form-control" onchange="previewProdImage(event)">
                    <input type="hidden" name="imagen" id="pf-imagen-b64">
                    <input type="hidden" name="imagen_updated" id="pf-imagen-updated" value="false">
                    <p style="font-size:0.8rem;color:var(--text-muted);margin-top:0.5rem">La imagen se redimensionará automáticamente.</p>
                </div>
                <div style="width:100px;height:100px;border:1px dashed var(--border);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;overflow:hidden;background:var(--bg-secondary)">
                    <img id="pf-imagen-preview" src="" style="max-width:100%;max-height:100%;display:none">
                    <i id="pf-imagen-icon" class="fas fa-image" style="color:var(--text-muted);font-size:2rem"></i>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="pf-codigo">Código *</label>
                    <div style="display:flex;gap:.5rem">
                        <input class="form-control" id="pf-codigo" name="codigo" required maxlength="50" style="flex:1">
                        <button type="button" class="btn btn-ghost" onclick="startScanner('product')" title="Escanear"><i class="fas fa-barcode"></i></button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="pf-nombre">Nombre *</label>
                    <input class="form-control" id="pf-nombre" name="nombre" required maxlength="150">
                </div>
            </div>
            <div class="form-group">
                <label for="pf-descripcion">Descripción</label>
                <textarea class="form-control" id="pf-descripcion" name="descripcion" rows="2"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="pf-categoria">Categoría</label>
                    <select class="form-control" id="pf-categoria" name="categoria_id">
                        <option value="">Sin categoría</option>
                        ${cats.map(c => `<option value="${c.id}">${escapeHtml(c.nombre)}</option>`).join('')}
                    </select>
                </div>
                <div class="form-group">
                    <label for="pf-proveedor">Proveedor</label>
                    <select class="form-control" id="pf-proveedor" name="proveedor_id">
                        <option value="">Sin proveedor</option>
                        ${supps.map(s => `<option value="${s.id}">${escapeHtml(s.nombre)}</option>`).join('')}
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="pf-precio-compra">Precio Compra *</label>
                    <input class="form-control" id="pf-precio-compra" name="precio_compra" type="number" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label for="pf-precio-venta">Precio Venta *</label>
                    <input class="form-control" id="pf-precio-venta" name="precio_venta" type="number" step="0.01" min="0" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="pf-stock">Stock</label>
                    <input class="form-control" id="pf-stock" name="stock" type="number" min="0" value="0">
                </div>
                <div class="form-group">
                    <label for="pf-stock-min">Stock Mínimo</label>
                    <input class="form-control" id="pf-stock-min" name="stock_minimo" type="number" min="0" value="5">
                </div>
            </div>
        </form>
    `;

    const footerHtml = `
        <button class="btn btn-ghost" onclick="closeModal()">Cancelar</button>
        <button class="btn btn-primary" onclick="saveProduct()"><i class="fas fa-save"></i> Guardar</button>
    `;

    openModal(title, formHtml, footerHtml);

    if (isEdit) {
        api('productos', 'get', { id }).then(p => {
            if (!p || p.error) return;
            $('#pf-id').value = p.id;
            $('#pf-codigo').value = p.codigo;
            $('#pf-nombre').value = p.nombre;
            $('#pf-descripcion').value = p.descripcion || '';
            $('#pf-categoria').value = p.categoria_id || '';
            $('#pf-proveedor').value = p.proveedor_id || '';
            $('#pf-precio-compra').value = p.precio_compra;
            $('#pf-precio-venta').value = p.precio_venta;
            $('#pf-stock').value = p.stock;
            $('#pf-stock-min').value = p.stock_minimo;
            
            if (p.imagen) {
                $('#pf-imagen-preview').src = p.imagen;
                $('#pf-imagen-preview').style.display = 'block';
                $('#pf-imagen-icon').style.display = 'none';
                $('#pf-imagen-b64').value = p.imagen;
            }
        });
    }
}

function previewProdImage(event) {
    const file = event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        const img = new Image();
        img.onload = function() {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const maxW = 500, maxH = 500;
            let width = img.width, height = img.height;
            if (width > height) { if (width > maxW) { height *= maxW / width; width = maxW; } }
            else { if (height > maxH) { width *= maxH / height; height = maxH; } }
            canvas.width = width; canvas.height = height;
            ctx.drawImage(img, 0, 0, width, height);
            const dataUrl = canvas.toDataURL('image/jpeg', 0.8);
            $('#pf-imagen-b64').value = dataUrl;
            $('#pf-imagen-updated').value = 'true';
            $('#pf-imagen-preview').src = dataUrl;
            $('#pf-imagen-preview').style.display = 'block';
            $('#pf-imagen-icon').style.display = 'none';
        }
        img.src = e.target.result;
    }
    reader.readAsDataURL(file);
}

async function saveProduct() {
    const form = $('#product-form');
    if (!form.checkValidity()) { form.reportValidity(); return; }

    const fd = Object.fromEntries(new FormData(form));
    const action = fd.id ? 'update' : 'create';

    const res = await api('productos', action, fd, 'POST');
    if (res.success) {
        toast(res.message);
        closeModal();
        filterProducts();
    } else {
        toast(res.error || 'Error al guardar.', 'error');
    }
}

function deleteProduct(id, name) {
    openConfirm(`¿Desea eliminar el producto "${name}"?`, async () => {
        const res = await api('productos', 'delete', { id }, 'POST');
        if (res.success) { toast(res.message); filterProducts(); }
        else toast(res.error || 'Error.', 'error');
    });
}

/* ═══════════════════════════════════════════════════════════════════
   MODULE: CATEGORÍAS
   ═══════════════════════════════════════════════════════════════════ */
async function loadCategorias() {
    const c = $('#page-content');
    c.innerHTML = '<div class="loading-overlay"><div class="spinner"></div> Cargando…</div>';
    const data = await api('categorias', 'list');

    c.innerHTML = `
    <div class="fade-in">
        <div class="data-card">
            <div class="data-card-header">
                <h3><i class="fas fa-tags"></i> Categorías</h3>
                <button class="btn btn-primary" onclick="openCatForm(null)"><i class="fas fa-plus"></i> Nueva</button>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead><tr><th>ID</th><th>Nombre</th><th>Descripción</th><th>Productos</th><th>Acciones</th></tr></thead>
                    <tbody>
                        ${data.map(c => `
                            <tr>
                                <td>${c.id}</td>
                                <td><strong>${escapeHtml(c.nombre)}</strong></td>
                                <td style="color:var(--text-muted)">${escapeHtml(c.descripcion || '-')}</td>
                                <td><span class="badge-status info">${c.total_productos}</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-ghost btn-icon btn-sm" onclick="openCatForm(${c.id},'${escapeHtml(c.nombre)}','${escapeHtml(c.descripcion||'')}')"><i class="fas fa-pen"></i></button>
                                        <button class="btn btn-ghost btn-icon btn-sm" onclick="deleteCat(${c.id},'${escapeHtml(c.nombre)}')"><i class="fas fa-trash" style="color:var(--danger)"></i></button>
                                    </div>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    </div>`;
}

function openCatForm(id, nombre = '', desc = '') {
    const isEdit = id !== null;
    const formHtml = `
        <form id="cat-form">
            <input type="hidden" name="id" value="${id || ''}">
            <div class="form-group">
                <label>Nombre *</label>
                <input class="form-control" name="nombre" value="${escapeHtml(nombre)}" required maxlength="100">
            </div>
            <div class="form-group">
                <label>Descripción</label>
                <textarea class="form-control" name="descripcion" rows="3">${escapeHtml(desc)}</textarea>
            </div>
        </form>
    `;
    openModal(isEdit ? '<i class="fas fa-pen"></i> Editar Categoría' : '<i class="fas fa-plus"></i> Nueva Categoría',
        formHtml,
        `<button class="btn btn-ghost" onclick="closeModal()">Cancelar</button>
         <button class="btn btn-primary" onclick="saveCat()"><i class="fas fa-save"></i> Guardar</button>`);
}

async function saveCat() {
    const form = $('#cat-form');
    if (!form.checkValidity()) { form.reportValidity(); return; }
    const fd = Object.fromEntries(new FormData(form));
    const res = await api('categorias', fd.id ? 'update' : 'create', fd, 'POST');
    if (res.success) { toast(res.message); closeModal(); loadCategorias(); }
    else toast(res.error || 'Error.', 'error');
}

function deleteCat(id, name) {
    openConfirm(`¿Desea eliminar la categoría "${name}"?`, async () => {
        const res = await api('categorias', 'delete', { id }, 'POST');
        if (res.success) { toast(res.message); loadCategorias(); }
        else toast(res.error || 'Error.', 'error');
    });
}

/* ═══════════════════════════════════════════════════════════════════
   MODULE: PROVEEDORES
   ═══════════════════════════════════════════════════════════════════ */
async function loadProveedores() {
    const c = $('#page-content');
    c.innerHTML = '<div class="loading-overlay"><div class="spinner"></div> Cargando…</div>';
    const data = await api('proveedores', 'list');

    c.innerHTML = `
    <div class="fade-in">
        <div class="data-card">
            <div class="data-card-header">
                <h3><i class="fas fa-truck"></i> Proveedores</h3>
                <button class="btn btn-primary" onclick="openProvForm()"><i class="fas fa-plus"></i> Nuevo</button>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead><tr><th>Nombre</th><th>Contacto</th><th>Teléfono</th><th>Email</th><th>Productos</th><th>Acciones</th></tr></thead>
                    <tbody>
                        ${data.map(p => `
                            <tr>
                                <td><strong>${escapeHtml(p.nombre)}</strong></td>
                                <td>${escapeHtml(p.contacto || '-')}</td>
                                <td>${escapeHtml(p.telefono || '-')}</td>
                                <td style="color:var(--info)">${escapeHtml(p.email || '-')}</td>
                                <td><span class="badge-status info">${p.total_productos}</span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-ghost btn-icon btn-sm" onclick='openProvForm(${JSON.stringify(p)})'><i class="fas fa-pen"></i></button>
                                        <button class="btn btn-ghost btn-icon btn-sm" onclick="deleteProv(${p.id},'${escapeHtml(p.nombre)}')"><i class="fas fa-trash" style="color:var(--danger)"></i></button>
                                    </div>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    </div>`;
}

function openProvForm(p = null) {
    const isEdit = p !== null;
    const formHtml = `
        <form id="prov-form">
            <input type="hidden" name="id" value="${p?.id || ''}">
            <div class="form-group">
                <label>Nombre *</label>
                <input class="form-control" name="nombre" value="${escapeHtml(p?.nombre || '')}" required maxlength="150">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Contacto</label>
                    <input class="form-control" name="contacto" value="${escapeHtml(p?.contacto || '')}">
                </div>
                <div class="form-group">
                    <label>Teléfono</label>
                    <input class="form-control" name="telefono" value="${escapeHtml(p?.telefono || '')}">
                </div>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input class="form-control" name="email" type="email" value="${escapeHtml(p?.email || '')}">
            </div>
            <div class="form-group">
                <label>Dirección</label>
                <textarea class="form-control" name="direccion" rows="2">${escapeHtml(p?.direccion || '')}</textarea>
            </div>
        </form>
    `;
    openModal(isEdit ? '<i class="fas fa-pen"></i> Editar Proveedor' : '<i class="fas fa-plus"></i> Nuevo Proveedor',
        formHtml,
        `<button class="btn btn-ghost" onclick="closeModal()">Cancelar</button>
         <button class="btn btn-primary" onclick="saveProv()"><i class="fas fa-save"></i> Guardar</button>`);
}

async function saveProv() {
    const form = $('#prov-form');
    if (!form.checkValidity()) { form.reportValidity(); return; }
    const fd = Object.fromEntries(new FormData(form));
    const res = await api('proveedores', fd.id ? 'update' : 'create', fd, 'POST');
    if (res.success) { toast(res.message); closeModal(); loadProveedores(); }
    else toast(res.error || 'Error.', 'error');
}

function deleteProv(id, name) {
    openConfirm(`¿Desea eliminar el proveedor "${name}"?`, async () => {
        const res = await api('proveedores', 'delete', { id }, 'POST');
        if (res.success) { toast(res.message); loadProveedores(); }
        else toast(res.error || 'Error.', 'error');
    });
}

/* ═══════════════════════════════════════════════════════════════════
   MODULE: CLIENTES
   ═══════════════════════════════════════════════════════════════════ */
async function loadClientes() {
    const c = $('#page-content');
    c.innerHTML = '<div class="loading-overlay"><div class="spinner"></div> Cargando…</div>';
    const data = await api('clientes', 'list');

    c.innerHTML = `
    <div class="fade-in">
        <div class="data-card">
            <div class="data-card-header">
                <h3><i class="fas fa-users"></i> Clientes</h3>
                <button class="btn btn-primary" onclick="openClientForm()"><i class="fas fa-plus"></i> Nuevo</button>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead><tr><th>Nombre</th><th>Teléfono</th><th>Email</th><th>Dirección</th><th>Acciones</th></tr></thead>
                    <tbody>
                        ${data.map(c => `
                            <tr>
                                <td><strong>${escapeHtml(c.nombre)}</strong></td>
                                <td>${escapeHtml(c.telefono || '-')}</td>
                                <td style="color:var(--info)">${escapeHtml(c.email || '-')}</td>
                                <td style="color:var(--text-muted)">${escapeHtml(c.direccion || '-')}</td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-ghost btn-icon btn-sm" onclick='openClientForm(${JSON.stringify(c)})'><i class="fas fa-pen"></i></button>
                                        <button class="btn btn-ghost btn-icon btn-sm" onclick="deleteClient(${c.id},'${escapeHtml(c.nombre)}')"><i class="fas fa-trash" style="color:var(--danger)"></i></button>
                                    </div>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    </div>`;
}

function openClientForm(c = null) {
    const isEdit = c !== null;
    const formHtml = `
        <form id="client-form">
            <input type="hidden" name="id" value="${c?.id || ''}">
            <div class="form-group">
                <label>Nombre *</label>
                <input class="form-control" name="nombre" value="${escapeHtml(c?.nombre || '')}" required maxlength="150">
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Teléfono</label>
                    <input class="form-control" name="telefono" value="${escapeHtml(c?.telefono || '')}">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input class="form-control" name="email" type="email" value="${escapeHtml(c?.email || '')}">
                </div>
            </div>
            <div class="form-group">
                <label>Dirección</label>
                <textarea class="form-control" name="direccion" rows="2">${escapeHtml(c?.direccion || '')}</textarea>
            </div>
        </form>
    `;
    openModal(isEdit ? '<i class="fas fa-pen"></i> Editar Cliente' : '<i class="fas fa-plus"></i> Nuevo Cliente',
        formHtml,
        `<button class="btn btn-ghost" onclick="closeModal()">Cancelar</button>
         <button class="btn btn-primary" onclick="saveClient()"><i class="fas fa-save"></i> Guardar</button>`);
}

async function saveClient() {
    const form = $('#client-form');
    if (!form.checkValidity()) { form.reportValidity(); return; }
    const fd = Object.fromEntries(new FormData(form));
    const res = await api('clientes', fd.id ? 'update' : 'create', fd, 'POST');
    if (res.success) { toast(res.message); closeModal(); loadClientes(); }
    else toast(res.error || 'Error.', 'error');
}

function deleteClient(id, name) {
    openConfirm(`¿Desea eliminar el cliente "${name}"?`, async () => {
        const res = await api('clientes', 'delete', { id }, 'POST');
        if (res.success) { toast(res.message); loadClientes(); }
        else toast(res.error || 'Error.', 'error');
    });
}

/* ═══════════════════════════════════════════════════════════════════
   MODULE: PUNTO DE VENTA (POS)
   ═══════════════════════════════════════════════════════════════════ */
let cart = [];

async function loadVentas() {
    const c = $('#page-content');
    c.innerHTML = '<div class="loading-overlay"><div class="spinner"></div> Cargando…</div>';

    const [products, clients] = await Promise.all([
        api('productos', 'list'),
        api('clientes', 'list'),
    ]);

    window._posProducts = products;
    window._posClients = clients;
    cart = [];

    c.innerHTML = `
    <div class="fade-in">
        <div class="pos-layout">
            <!-- Products List -->
            <div class="data-card">
                <div class="data-card-header">
                    <h3><i class="fas fa-boxes-stacked"></i> Seleccionar Productos</h3>
                    <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
                        <button class="btn btn-ghost btn-sm" onclick="startScanner()" id="scanner-btn">
                            <i class="fas fa-barcode"></i> Escanear
                        </button>
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input class="form-control" id="pos-search" placeholder="Buscar producto…" style="width:220px">
                        </div>
                    </div>
                </div>
                <div class="table-wrapper" style="max-height:55vh;overflow-y:auto">
                    <table class="data-table">
                        <thead><tr><th>Código</th><th>Producto</th><th>Precio</th><th>Stock</th><th></th></tr></thead>
                        <tbody id="pos-products-tbody"></tbody>
                    </table>
                </div>
            </div>

            <!-- Cart -->
            <div class="data-card" style="position:sticky;top:80px;align-self:start">
                <div class="data-card-header">
                    <h3><i class="fas fa-cart-shopping"></i> Carrito</h3>
                    <button class="btn btn-ghost btn-sm" onclick="cart=[];renderCart()"><i class="fas fa-trash"></i> Vaciar</button>
                </div>
                <div class="data-card-body">
                    <div id="cart-items"></div>
                    <div class="cart-totals" id="cart-totals"></div>

                    <div style="margin-top:1rem">
                        <div class="form-group">
                            <label>Cliente</label>
                            <select class="form-control" id="pos-cliente">
                                <option value="">Público General</option>
                                ${clients.map(c => `<option value="${c.id}">${escapeHtml(c.nombre)}</option>`).join('')}
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Método de Pago</label>
                            <select class="form-control" id="pos-metodo">
                                <option value="efectivo">Efectivo</option>
                                <option value="tarjeta">Tarjeta</option>
                                <option value="transferencia">Transferencia</option>
                            </select>
                        </div>
                        <button class="btn btn-success" style="width:100%;padding:.85rem" onclick="processSale()" id="pos-checkout-btn">
                            <i class="fas fa-check-circle"></i> Procesar Venta
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>`;

    renderPosProducts(products);
    renderCart();

    // Search
    $('#pos-search').addEventListener('input', () => {
        const q = $('#pos-search').value.toLowerCase();
        const filtered = products.filter(p =>
            p.nombre.toLowerCase().includes(q) || p.codigo.toLowerCase().includes(q)
        );
        renderPosProducts(filtered);
    });
}

function renderPosProducts(products) {
    const tbody = $('#pos-products-tbody');
    if (!tbody) return;
    tbody.innerHTML = products.filter(p => parseInt(p.stock) > 0).map(p => `
        <tr>
            <td class="cell-code">${escapeHtml(p.codigo)}</td>
            <td>${escapeHtml(p.nombre)}</td>
            <td class="cell-price">${formatMoney(p.precio_venta)}</td>
            <td><span class="cell-stock ${parseInt(p.stock) <= parseInt(p.stock_minimo) ? 'low' : 'ok'}">${p.stock}</span></td>
            <td><button class="btn btn-primary btn-sm" onclick="addToCart(${p.id})"><i class="fas fa-plus"></i></button></td>
        </tr>
    `).join('');
}

function addToCart(prodId) {
    const prod = (window._posProducts || []).find(p => p.id == prodId);
    if (!prod) return;

    const existing = cart.find(c => c.producto_id == prodId);
    if (existing) {
        if (existing.cantidad >= parseInt(prod.stock)) {
            toast('Stock insuficiente.', 'warning');
            return;
        }
        existing.cantidad++;
    } else {
        cart.push({
            producto_id: prod.id,
            nombre: prod.nombre,
            precio: parseFloat(prod.precio_venta),
            cantidad: 1,
            stock: parseInt(prod.stock),
        });
    }
    renderCart();
}

function removeFromCart(idx) {
    cart.splice(idx, 1);
    renderCart();
}

function updateCartQty(idx, qty) {
    qty = parseInt(qty);
    if (qty <= 0) { removeFromCart(idx); return; }
    if (qty > cart[idx].stock) { toast('Stock insuficiente.', 'warning'); return; }
    cart[idx].cantidad = qty;
    renderCart();
}

function renderCart() {
    const items = $('#cart-items');
    const totals = $('#cart-totals');
    if (!items || !totals) return;

    if (cart.length === 0) {
        items.innerHTML = '<div class="empty-state" style="padding:1.5rem"><i class="fas fa-cart-shopping"></i><p>Carrito vacío</p></div>';
        totals.innerHTML = '';
        return;
    }

    items.innerHTML = cart.map((item, i) => `
        <div class="cart-item">
            <div class="cart-item-info">
                <div class="cart-item-name">${escapeHtml(item.nombre)}</div>
                <div class="cart-item-price">${formatMoney(item.precio)} c/u</div>
            </div>
            <div class="cart-item-qty">
                <button class="btn btn-ghost btn-icon btn-sm" onclick="updateCartQty(${i},${item.cantidad - 1})"><i class="fas fa-minus"></i></button>
                <input type="number" value="${item.cantidad}" min="1" max="${item.stock}" onchange="updateCartQty(${i},this.value)">
                <button class="btn btn-ghost btn-icon btn-sm" onclick="updateCartQty(${i},${item.cantidad + 1})"><i class="fas fa-plus"></i></button>
            </div>
            <div class="cart-item-subtotal">${formatMoney(item.precio * item.cantidad)}</div>
            <button class="btn btn-ghost btn-icon btn-sm" onclick="removeFromCart(${i})"><i class="fas fa-times" style="color:var(--danger)"></i></button>
        </div>
    `).join('');

    const subtotal = cart.reduce((s, item) => s + item.precio * item.cantidad, 0);
    const iva = subtotal * 0.16;
    const total = subtotal + iva;

    totals.innerHTML = `
        <div class="total-row"><span>Subtotal</span><span>${formatMoney(subtotal)}</span></div>
        <div class="total-row"><span>IVA (16%)</span><span>${formatMoney(iva)}</span></div>
        <div class="total-row grand"><span>Total</span><span>${formatMoney(total)}</span></div>
    `;
}

async function processSale() {
    if (cart.length === 0) { toast('Agregue productos al carrito.', 'warning'); return; }

    const btn = $('#pos-checkout-btn');
    btn.disabled = true;
    btn.innerHTML = '<div class="spinner" style="width:18px;height:18px;border-width:2px"></div> Procesando…';

    const payload = {
        items: cart.map(c => ({ producto_id: c.producto_id, cantidad: c.cantidad })),
        cliente_id: $('#pos-cliente').value || '',
        metodo_pago: $('#pos-metodo').value,
    };

    const res = await api('ventas', 'create', payload, 'JSON');
    btn.disabled = false;
    btn.innerHTML = '<i class="fas fa-check-circle"></i> Procesar Venta';

    if (res.success) {
        toast(`${res.message} — Total: ${formatMoney(res.total)}`);
        cart = [];
        loadVentas(); // Reload to refresh stock
    } else {
        toast(res.error || 'Error al procesar la venta.', 'error');
    }
}

/* ═══════════════════════════════════════════════════════════════════
   MODULE: HISTORIAL DE VENTAS
   ═══════════════════════════════════════════════════════════════════ */
async function loadHistorial() {
    const c = $('#page-content');
    c.innerHTML = '<div class="loading-overlay"><div class="spinner"></div> Cargando…</div>';
    const data = await api('ventas', 'list');

    c.innerHTML = `
    <div class="fade-in">
        <div class="data-card">
            <div class="data-card-header">
                <h3><i class="fas fa-receipt"></i> Historial de Ventas</h3>
                <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
                    <input type="date" class="form-control" id="hist-desde" style="width:160px" placeholder="Desde">
                    <input type="date" class="form-control" id="hist-hasta" style="width:160px" placeholder="Hasta">
                    <button class="btn btn-primary btn-sm" onclick="filterHistorial()"><i class="fas fa-filter"></i> Filtrar</button>
                    <button class="btn btn-ghost btn-sm" onclick="downloadHistorialCSV()" title="Descargar CSV"><i class="fas fa-file-csv"></i> CSV</button>
                    <button class="btn btn-ghost btn-sm" onclick="downloadHistorialDetalleCSV()" title="Descargar detalle completo"><i class="fas fa-file-csv"></i> CSV Detallado</button>
                </div>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead><tr><th>Folio</th><th>Cliente</th><th>Vendedor</th><th>Método</th><th>Total</th><th>Estado</th><th>Fecha</th><th>Acciones</th></tr></thead>
                    <tbody id="historial-tbody"></tbody>
                </table>
            </div>
        </div>
    </div>`;

    renderHistorial(data);
}

function renderHistorial(data) {
    const tbody = $('#historial-tbody');
    if (!tbody) return;
    if (!data || data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8"><div class="empty-state"><i class="fas fa-receipt"></i><p>No hay ventas registradas</p></div></td></tr>';
        return;
    }
    tbody.innerHTML = data.map(v => `
        <tr>
            <td class="cell-code">${escapeHtml(v.folio)}</td>
            <td>${escapeHtml(v.cliente_nombre)}</td>
            <td>${escapeHtml(v.vendedor)}</td>
            <td><span class="badge-status info">${v.metodo_pago}</span></td>
            <td class="cell-price">${formatMoney(v.total)}</td>
            <td><span class="badge-status ${v.estado === 'completada' ? 'success' : 'danger'}">${v.estado}</span></td>
            <td>${formatDate(v.created_at)}</td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-ghost btn-icon btn-sm" title="Ver detalle" onclick="viewSaleDetail(${v.id},'${escapeHtml(v.folio)}')"><i class="fas fa-eye"></i></button>
                    ${v.estado === 'completada' && IS_ADMIN ? `<button class="btn btn-ghost btn-icon btn-sm" title="Cancelar" onclick="cancelSale(${v.id},'${escapeHtml(v.folio)}')"><i class="fas fa-ban" style="color:var(--danger)"></i></button>` : ''}
                </div>
            </td>
        </tr>
    `).join('');
}

async function filterHistorial() {
    const desde = $('#hist-desde').value;
    const hasta = $('#hist-hasta').value;
    const data = await api('ventas', 'list', { desde, hasta });
    renderHistorial(data);
}

async function viewSaleDetail(id, folio) {
    const detail = await api('ventas', 'detail', { id });
    if (!detail) return;
    const html = `
        <table class="data-table" style="font-size:.85rem">
            <thead><tr><th>Código</th><th>Producto</th><th>Cantidad</th><th>P. Unit.</th><th>Subtotal</th></tr></thead>
            <tbody>
                ${detail.map(d => `
                    <tr>
                        <td class="cell-code">${escapeHtml(d.producto_codigo)}</td>
                        <td>${escapeHtml(d.producto_nombre)}</td>
                        <td>${d.cantidad}</td>
                        <td>${formatMoney(d.precio_unitario)}</td>
                        <td class="cell-price">${formatMoney(d.subtotal)}</td>
                    </tr>
                `).join('')}
            </tbody>
        </table>
    `;
    openModal(`<i class="fas fa-receipt"></i> Detalle — ${escapeHtml(folio)}`, html,
        `<button class="btn btn-ghost" onclick="closeModal()">Cerrar</button>`);
}

function cancelSale(id, folio) {
    openConfirm(`¿Desea cancelar la venta ${folio}? El stock será restaurado.`, async () => {
        const res = await api('ventas', 'cancel', { id }, 'POST');
        if (res.success) { toast(res.message); loadHistorial(); }
        else toast(res.error || 'Error.', 'error');
    });
}

/* ═══════════════════════════════════════════════════════════════════
   MODULE: USUARIOS (Admin)
   ═══════════════════════════════════════════════════════════════════ */
async function loadUsuarios() {
    if (!IS_ADMIN) { navigate('dashboard'); return; }
    const c = $('#page-content');
    c.innerHTML = '<div class="loading-overlay"><div class="spinner"></div> Cargando…</div>';
    const data = await api('usuarios', 'list');

    c.innerHTML = `
    <div class="fade-in">
        <div class="data-card">
            <div class="data-card-header">
                <h3><i class="fas fa-user-shield"></i> Gestión de Usuarios</h3>
                <button class="btn btn-primary" onclick="openUserForm()"><i class="fas fa-plus"></i> Nuevo</button>
            </div>
            <div class="table-wrapper">
                <table class="data-table">
                    <thead><tr><th>Usuario</th><th>Nombre</th><th>Rol</th><th>Estado</th><th>Creado</th><th>Acciones</th></tr></thead>
                    <tbody>
                        ${data.map(u => `
                            <tr>
                                <td class="cell-code">${escapeHtml(u.username)}</td>
                                <td><strong>${escapeHtml(u.nombre)}</strong></td>
                                <td><span class="badge-status ${u.rol === 'admin' ? 'warning' : 'info'}">${u.rol}</span></td>
                                <td><span class="badge-status ${u.activo == 1 ? 'success' : 'danger'}">${u.activo == 1 ? 'Activo' : 'Inactivo'}</span></td>
                                <td>${formatDate(u.created_at)}</td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-ghost btn-icon btn-sm" onclick='openUserForm(${JSON.stringify(u)})'><i class="fas fa-pen"></i></button>
                                        <button class="btn btn-ghost btn-icon btn-sm" onclick="deleteUser(${u.id},'${escapeHtml(u.username)}')"><i class="fas fa-trash" style="color:var(--danger)"></i></button>
                                    </div>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        </div>
    </div>`;
}

function openUserForm(u = null) {
    const isEdit = u !== null;
    const formHtml = `
        <form id="user-form">
            <input type="hidden" name="id" value="${u?.id || ''}">
            <div class="form-row">
                <div class="form-group">
                    <label>Usuario *</label>
                    <input class="form-control" name="username" value="${escapeHtml(u?.username || '')}" ${isEdit ? 'readonly' : 'required'} maxlength="50">
                </div>
                <div class="form-group">
                    <label>Nombre Completo *</label>
                    <input class="form-control" name="nombre" value="${escapeHtml(u?.nombre || '')}" required maxlength="100">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Contraseña ${isEdit ? '(dejar vacío para no cambiar)' : '*'}</label>
                    <input class="form-control" name="password" type="password" ${isEdit ? '' : 'required'} minlength="6">
                </div>
                <div class="form-group">
                    <label>Rol</label>
                    <select class="form-control" name="rol">
                        <option value="vendedor" ${u?.rol === 'vendedor' ? 'selected' : ''}>Vendedor</option>
                        <option value="admin" ${u?.rol === 'admin' ? 'selected' : ''}>Administrador</option>
                    </select>
                </div>
            </div>
            ${isEdit ? `
            <div class="form-group">
                <label>Estado</label>
                <select class="form-control" name="activo">
                    <option value="1" ${u?.activo == 1 ? 'selected' : ''}>Activo</option>
                    <option value="0" ${u?.activo == 0 ? 'selected' : ''}>Inactivo</option>
                </select>
            </div>` : '<input type="hidden" name="activo" value="1">'}
        </form>
    `;
    openModal(isEdit ? '<i class="fas fa-pen"></i> Editar Usuario' : '<i class="fas fa-plus"></i> Nuevo Usuario',
        formHtml,
        `<button class="btn btn-ghost" onclick="closeModal()">Cancelar</button>
         <button class="btn btn-primary" onclick="saveUser()"><i class="fas fa-save"></i> Guardar</button>`);
}

async function saveUser() {
    const form = $('#user-form');
    if (!form.checkValidity()) { form.reportValidity(); return; }
    const fd = Object.fromEntries(new FormData(form));
    const res = await api('usuarios', fd.id ? 'update' : 'create', fd, 'POST');
    if (res.success) { toast(res.message); closeModal(); loadUsuarios(); }
    else toast(res.error || 'Error.', 'error');
}

function deleteUser(id, username) {
    openConfirm(`¿Desea desactivar al usuario "${username}"?`, async () => {
        const res = await api('usuarios', 'delete', { id }, 'POST');
        if (res.success) { toast(res.message); loadUsuarios(); }
        else toast(res.error || 'Error.', 'error');
    });
}

/* ═══════════════════════════════════════════════════════════════════
   CSV DOWNLOAD HELPERS
   ═══════════════════════════════════════════════════════════════════ */
function downloadHistorialCSV() {
    const desde = $('#hist-desde')?.value || '';
    const hasta = $('#hist-hasta')?.value || '';
    let url = 'export.php?module=ventas';
    if (desde) url += '&desde=' + desde;
    if (hasta) url += '&hasta=' + hasta;
    window.open(url, '_blank');
}

function downloadHistorialDetalleCSV() {
    const desde = $('#hist-desde')?.value || '';
    const hasta = $('#hist-hasta')?.value || '';
    let url = 'export.php?module=ventas_detalle';
    if (desde) url += '&desde=' + desde;
    if (hasta) url += '&hasta=' + hasta;
    window.open(url, '_blank');
}

/* ═══════════════════════════════════════════════════════════════════
   BARCODE SCANNER (Camera)
   ═══════════════════════════════════════════════════════════════════ */
let html5QrCode = null;
let scannerActive = false;
let scannerContext = 'pos'; // 'pos' or 'product'

function startScanner(context = 'pos') {
    scannerContext = context;
    if (typeof Html5Qrcode === 'undefined') {
        toast('Librería de escáner no disponible. Recargue la página.', 'error');
        return;
    }

    $('#scanner-overlay').classList.add('active');
    $('#scanner-result').style.display = 'none';
    $('#scanner-fallback').style.display = 'none';
    $('#scanner-reader').style.display = 'block';
    $('#scanner-help-text').style.display = 'block';

    html5QrCode = new Html5Qrcode('scanner-reader');
    scannerActive = true;

    // Si el navegador bloquea la cámara por falta de HTTPS (ej. en móviles por red local)
    if (!window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1') {
        showScannerFallback();
        return;
    }

    const config = {
        fps: 10,
        qrbox: { width: 300, height: 150 },
        aspectRatio: 1.5,
        formatsToSupport: [
            Html5QrcodeSupportedFormats.CODE_128,
            Html5QrcodeSupportedFormats.CODE_39,
            Html5QrcodeSupportedFormats.EAN_13,
            Html5QrcodeSupportedFormats.EAN_8,
            Html5QrcodeSupportedFormats.UPC_A,
            Html5QrcodeSupportedFormats.UPC_E,
            Html5QrcodeSupportedFormats.QR_CODE,
        ]
    };

    html5QrCode.start(
        { facingMode: 'environment' },
        config,
        (decodedText) => onScanSuccess(decodedText),
        () => {} // ignore scan failures
    ).catch(err => {
        console.error('Error al iniciar cámara:', err);
        showScannerFallback();
    });
}

function showScannerFallback() {
    $('#scanner-reader').style.display = 'none';
    $('#scanner-help-text').style.display = 'none';
    $('#scanner-fallback').style.display = 'block';
}

function scanImage(e) {
    if (e.target.files.length === 0) return;
    const file = e.target.files[0];
    
    if (!html5QrCode) html5QrCode = new Html5Qrcode('scanner-reader');
    
    const btn = e.target.previousElementSibling;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Analizando...';
    btn.disabled = true;

    html5QrCode.scanFile(file, true)
        .then(decodedText => {
            btn.innerHTML = originalText;
            btn.disabled = false;
            scannerActive = true;
            onScanSuccess(decodedText);
            e.target.value = ''; // Reset input
        })
        .catch(err => {
            btn.innerHTML = originalText;
            btn.disabled = false;
            e.target.value = '';
            toast('La foto no es clara o no se detectó el código. Intente de nuevo con mejor iluminación.', 'warning');
        });
}

function onScanSuccess(code) {
    if (!scannerActive) return;

    // Prevent multiple rapid scans
    scannerActive = false;

    if (scannerContext === 'product') {
        const codeInput = $('#pf-codigo');
        if (codeInput) {
            codeInput.value = code;
            const products = window._allProducts || [];
            const existing = products.find(p => p.codigo.toLowerCase() === code.toLowerCase());
            
            if (existing) {
                toast('Producto encontrado. Puede editar los datos y actualizar el stock.', 'info');
                $('#pf-id').value = existing.id;
                $('#pf-nombre').value = existing.nombre;
                $('#pf-descripcion').value = existing.descripcion || '';
                $('#pf-categoria').value = existing.categoria_id || '';
                $('#pf-proveedor').value = existing.proveedor_id || '';
                $('#pf-precio-compra').value = existing.precio_compra;
                $('#pf-precio-venta').value = existing.precio_venta;
                $('#pf-stock').value = existing.stock;
                $('#pf-stock-min').value = existing.stock_minimo;
                $('#modal-title').innerHTML = '<i class="fas fa-pen"></i> Editar Producto';
            } else {
                toast(`Código escaneado: ${code}`);
            }
        }
        stopScanner();
        return;
    }

    // POS context
    const resultDiv = $('#scanner-result');
    const products = window._posProducts || [];
    const product = products.find(p => p.codigo.toLowerCase() === code.toLowerCase());

    if (product) {
        // Play success beep
        try {
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioCtx.createOscillator();
            const gainNode = audioCtx.createGain();
            oscillator.type = 'sine';
            oscillator.frequency.setValueAtTime(800, audioCtx.currentTime);
            gainNode.gain.setValueAtTime(0.1, audioCtx.currentTime);
            oscillator.connect(gainNode);
            gainNode.connect(audioCtx.destination);
            oscillator.start();
            oscillator.stop(audioCtx.currentTime + 0.1);
        } catch(e) {}

        addToCart(product.id);
        toast(`Añadido: ${product.nombre}`);
        stopScanner();
    } else {
        // Play error beep
        try {
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioCtx.createOscillator();
            const gainNode = audioCtx.createGain();
            oscillator.type = 'sawtooth';
            oscillator.frequency.setValueAtTime(300, audioCtx.currentTime);
            gainNode.gain.setValueAtTime(0.1, audioCtx.currentTime);
            oscillator.connect(gainNode);
            gainNode.connect(audioCtx.destination);
            oscillator.start();
            oscillator.stop(audioCtx.currentTime + 0.3);
        } catch(e) {}

        resultDiv.style.display = 'block';
        resultDiv.style.background = 'var(--warning-bg)';
        resultDiv.style.color = 'var(--warning)';
        resultDiv.innerHTML = `<i class="fas fa-triangle-exclamation"></i> Código "${escapeHtml(code)}" no encontrado`;
        toast(`Código "${code}" no coincide.`, 'warning');

        // Allow scanning again after 1.5 seconds if error
        setTimeout(() => {
            scannerActive = true;
            resultDiv.style.display = 'none';
        }, 1500);
    }
}

function stopScanner() {
    scannerActive = false;
    $('#scanner-overlay').classList.remove('active');
    
    if (html5QrCode) {
        try {
            // Some versions of html5QrCode throw synchronously if stop() is called while not scanning
            const stopPromise = html5QrCode.stop();
            if (stopPromise) {
                stopPromise.then(() => {
                    html5QrCode.clear();
                    html5QrCode = null;
                }).catch(() => {
                    html5QrCode.clear();
                    html5QrCode = null;
                });
            }
        } catch (e) {
            try { html5QrCode.clear(); } catch (e2) {}
            html5QrCode = null;
        }
    }
}

// Close scanner on overlay click
$('#scanner-overlay').addEventListener('click', e => {
    if (e.target === e.currentTarget) stopScanner();
});

/* ═══════════════════════════════════════════════════════════════════
   INIT
   ═══════════════════════════════════════════════════════════════════ */

/* ═══════════════════════════════════════════════════════════════════
   MODULE: CONFIGURACION
   ═══════════════════════════════════════════════════════════════════ */
async function loadConfiguracion() {
    const c = $('#page-content');
    c.innerHTML = '<div class="loading-overlay"><div class="spinner"></div> Cargando…</div>';
    
    const data = await api('configuracion', 'get');

    c.innerHTML = `
    <div class="fade-in">
        <div class="data-card" style="max-width: 600px; margin: 0 auto;">
            <div class="data-card-header">
                <h3><i class="fas fa-cog"></i> Configuración General</h3>
            </div>
            <div style="padding: 2rem;">
                <form id="config-form" onsubmit="event.preventDefault(); saveConfig();">
                    <div class="form-group">
                        <label>Logo de la Papelería</label>
                        <div style="display:flex;gap:1rem;align-items:center;margin-top:.5rem">
                            <div style="width:100px;height:100px;border:1px dashed var(--border);border-radius:var(--radius-md);display:flex;align-items:center;justify-content:center;overflow:hidden;background:var(--bg-secondary)">
                                <img id="cfg-logo-preview" src="${data.logo || ''}" style="max-width:100%;max-height:100%;display:${data.logo ? 'block' : 'none'}">
                                <i id="cfg-logo-icon" class="fas fa-store" style="color:var(--text-muted);font-size:2rem;display:${data.logo ? 'none' : 'block'}"></i>
                            </div>
                            <div style="flex:1">
                                <input type="file" accept="image/*" class="form-control" onchange="previewLogo(event)">
                                <input type="hidden" name="logo" id="cfg-logo-b64" value="${data.logo || ''}">
                                <p style="font-size:0.8rem;color:var(--text-muted);margin-top:0.5rem">Sube una imagen cuadrada (PNG o JPG). Se redimensionará automáticamente.</p>
                            </div>
                        </div>
                    </div>
                    <hr style="border:none;border-top:1px solid var(--border);margin:2rem 0">
                    <div style="text-align:right">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>`;
}

function previewLogo(event) {
    const file = event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        const img = new Image();
        img.onload = function() {
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            const maxW = 300, maxH = 300;
            let width = img.width, height = img.height;
            if (width > height) { if (width > maxW) { height *= maxW / width; width = maxW; } }
            else { if (height > maxH) { width *= maxH / height; height = maxH; } }
            canvas.width = width; canvas.height = height;
            ctx.drawImage(img, 0, 0, width, height);
            const dataUrl = canvas.toDataURL('image/png');
            $('#cfg-logo-b64').value = dataUrl;
            $('#cfg-logo-preview').src = dataUrl;
            $('#cfg-logo-preview').style.display = 'block';
            $('#cfg-logo-icon').style.display = 'none';
        }
        img.src = e.target.result;
    }
    reader.readAsDataURL(file);
}

async function saveConfig() {
    const logo = $('#cfg-logo-b64').value;
    const res = await api('configuracion', 'update_logo', { logo }, 'POST');
    if (res.success) {
        toast(res.message);
        setTimeout(() => location.reload(), 1000); // Reload to apply logo everywhere
    } else {
        toast(res.error || 'Error.', 'error');
    }
}

// Initial Navigation
navigate('dashboard');
</script>
</body>
</html>
