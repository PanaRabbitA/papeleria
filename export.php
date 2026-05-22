<?php
/**
 * CSV Export — Papelería Admin System
 *
 * Generates CSV files for download with UTF-8 BOM for Excel compatibility.
 * Usage: export.php?module=<module>&desde=<date>&hasta=<date>
 */
require_once __DIR__ . '/includes/auth.php';
Auth::requireAuth();

$pdo    = Database::getInstance()->getConnection();
$module = $_GET['module'] ?? '';
$filename = $module . '_' . date('Y-m-d_His') . '.csv';

// CSV headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// UTF-8 BOM for Excel
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

switch ($module) {
    case 'productos':
        fputcsv($output, ['Código', 'Nombre', 'Descripción', 'Categoría', 'Proveedor', 'Precio Compra', 'Precio Venta', 'Stock', 'Stock Mínimo']);
        $stmt = $pdo->query("
            SELECT p.codigo, p.nombre, p.descripcion,
                   COALESCE(c.nombre,'Sin categoría') as cat,
                   COALESCE(pr.nombre,'Sin proveedor') as prov,
                   p.precio_compra, p.precio_venta, p.stock, p.stock_minimo
            FROM productos p
            LEFT JOIN categorias c ON p.categoria_id=c.id
            LEFT JOIN proveedores pr ON p.proveedor_id=pr.id
            WHERE p.activo=1
            ORDER BY p.nombre
        ");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) fputcsv($output, $row);
        break;

    case 'ventas':
        $desde = $_GET['desde'] ?? '';
        $hasta = $_GET['hasta'] ?? '';

        fputcsv($output, ['Folio', 'Cliente', 'Vendedor', 'Subtotal', 'IVA', 'Total', 'Método de Pago', 'Estado', 'Fecha']);

        $sql = "SELECT v.folio, COALESCE(c.nombre,'Público General'), u.nombre,
                       v.subtotal, v.iva, v.total, v.metodo_pago, v.estado, v.created_at
                FROM ventas v
                LEFT JOIN clientes c ON v.cliente_id=c.id
                JOIN usuarios u ON v.usuario_id=u.id
                WHERE 1=1";
        $params = [];

        if ($desde) { $sql .= " AND DATE(v.created_at) >= ?"; $params[] = $desde; }
        if ($hasta) { $sql .= " AND DATE(v.created_at) <= ?"; $params[] = $hasta; }
        $sql .= " ORDER BY v.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) fputcsv($output, $row);
        break;

    case 'ventas_detalle':
        $desde = $_GET['desde'] ?? '';
        $hasta = $_GET['hasta'] ?? '';

        fputcsv($output, ['Folio', 'Cliente', 'Vendedor', 'Código Producto', 'Producto', 'Cantidad', 'Precio Unitario', 'Subtotal Línea', 'Total Venta', 'Método Pago', 'Fecha']);

        $sql = "SELECT v.folio, COALESCE(c.nombre,'Público General'), u.nombre,
                       p.codigo, p.nombre, dv.cantidad, dv.precio_unitario, dv.subtotal,
                       v.total, v.metodo_pago, v.created_at
                FROM detalle_ventas dv
                JOIN ventas v ON dv.venta_id=v.id
                JOIN productos p ON dv.producto_id=p.id
                LEFT JOIN clientes c ON v.cliente_id=c.id
                JOIN usuarios u ON v.usuario_id=u.id
                WHERE v.estado='completada'";
        $params = [];

        if ($desde) { $sql .= " AND DATE(v.created_at) >= ?"; $params[] = $desde; }
        if ($hasta) { $sql .= " AND DATE(v.created_at) <= ?"; $params[] = $hasta; }
        $sql .= " ORDER BY v.created_at DESC, dv.id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) fputcsv($output, $row);
        break;

    case 'clientes':
        fputcsv($output, ['Nombre', 'Teléfono', 'Email', 'Dirección']);
        $stmt = $pdo->query("SELECT nombre, telefono, email, direccion FROM clientes WHERE activo=1 ORDER BY nombre");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) fputcsv($output, $row);
        break;

    case 'categorias':
        fputcsv($output, ['ID', 'Nombre', 'Descripción']);
        $stmt = $pdo->query("SELECT id, nombre, descripcion FROM categorias WHERE activo=1 ORDER BY nombre");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) fputcsv($output, $row);
        break;

    case 'proveedores':
        fputcsv($output, ['Nombre', 'Contacto', 'Teléfono', 'Email', 'Dirección']);
        $stmt = $pdo->query("SELECT nombre, contacto, telefono, email, direccion FROM proveedores WHERE activo=1 ORDER BY nombre");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) fputcsv($output, $row);
        break;

    default:
        fputcsv($output, ['Error']);
        fputcsv($output, ['Módulo no válido']);
}

fclose($output);
exit;
