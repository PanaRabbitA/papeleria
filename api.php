<?php
/**
 * REST-like API — Papelería Admin System
 *
 * All data operations are routed through this single entry-point.
 * Security: CSRF validation on mutations, prepared statements, role checks.
 *
 * Usage:  api.php?module=<module>&action=<action>
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

require_once __DIR__ . '/includes/auth.php';

$pdo    = Database::getInstance()->getConnection();
$module = $_GET['module'] ?? '';
$action = $_GET['action'] ?? '';

if ($module !== 'auth') {
    Auth::requireAuth();
}

// CSRF on mutation requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Auth::validateCSRFToken($csrfToken)) {
        http_response_code(403);
        echo json_encode(['error' => 'Token CSRF inválido.']);
        exit;
    }
}

try {
    switch ($module) {
        case 'dashboard':  handleDashboard($pdo, $action);  break;
        case 'productos':  handleProductos($pdo, $action);  break;
        case 'categorias': handleCategorias($pdo, $action); break;
        case 'proveedores':handleProveedores($pdo, $action);break;
        case 'clientes':   handleClientes($pdo, $action);   break;
        case 'ventas':     handleVentas($pdo, $action);     break;
        case 'usuarios':   handleUsuarios($pdo, $action);   break;
        case 'configuracion': handleConfiguracion($pdo, $action); break;
        case 'auth':       handleAuth($pdo, $action);       break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Módulo no válido.']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error en la base de datos.']);
}

/* ═══════════════════════════════════════════════════════════════════
   DASHBOARD
   ═══════════════════════════════════════════════════════════════════ */
function handleDashboard(PDO $pdo, string $action) {
    if ($action !== 'stats') {
        http_response_code(400);
        echo json_encode(['error' => 'Acción no válida.']);
        return;
    }

    $stats = [];

    // Total products
    $stats['total_productos'] = (int)$pdo->query("SELECT COUNT(*) FROM productos WHERE activo=1")->fetchColumn();

    // Low stock
    $stats['stock_bajo'] = (int)$pdo->query("SELECT COUNT(*) FROM productos WHERE activo=1 AND stock <= stock_minimo")->fetchColumn();

    // Today's sales
    $stmt = $pdo->query("SELECT COUNT(*) as total_ventas, COALESCE(SUM(total),0) as ingresos FROM ventas WHERE CAST(created_at AS DATE)=CURRENT_DATE AND estado='completada'");
    $row = $stmt->fetch();
    $stats['ventas_hoy']   = (int)$row['total_ventas'];
    $stats['ingresos_hoy'] = (float)$row['ingresos'];

    // Total customers
    $stats['total_clientes'] = (int)$pdo->query("SELECT COUNT(*) FROM clientes WHERE activo=1")->fetchColumn();

    // Total categories
    $stats['total_categorias'] = (int)$pdo->query("SELECT COUNT(*) FROM categorias WHERE activo=1")->fetchColumn();

    // Recent sales (last 5)
    $stmt = $pdo->query("
        SELECT v.id, v.folio, v.total, v.metodo_pago, v.created_at,
               COALESCE(c.nombre,'Público General') as cliente,
               u.nombre as vendedor
        FROM ventas v
        LEFT JOIN clientes c ON v.cliente_id=c.id
        JOIN usuarios u ON v.usuario_id=u.id
        WHERE v.estado='completada'
        ORDER BY v.created_at DESC LIMIT 5
    ");
    $stats['ventas_recientes'] = $stmt->fetchAll();

    // Low-stock products
    $stmt = $pdo->query("
        SELECT p.id, p.codigo, p.nombre, p.stock, p.stock_minimo,
               COALESCE(cat.nombre,'Sin categoría') as categoria
        FROM productos p
        LEFT JOIN categorias cat ON p.categoria_id=cat.id
        WHERE p.activo=1 AND p.stock <= p.stock_minimo
        ORDER BY p.stock ASC LIMIT 10
    ");
    $stats['productos_stock_bajo'] = $stmt->fetchAll();

    // Sales last 7 days (for chart)
    $stmt = $pdo->query("
        SELECT CAST(created_at AS DATE) as fecha, COUNT(*) as num_ventas, SUM(total) as ingresos
        FROM ventas
        WHERE estado='completada' AND created_at >= CURRENT_DATE - INTERVAL '7 days'
        GROUP BY CAST(created_at AS DATE)
        ORDER BY fecha ASC
    ");
    $stats['ventas_semana'] = $stmt->fetchAll();

    // Products by category (for pie chart)
    $stmt = $pdo->query("
        SELECT c.nombre, COUNT(p.id) as cantidad
        FROM categorias c
        LEFT JOIN productos p ON p.categoria_id = c.id AND p.activo = 1
        WHERE c.activo = 1
        GROUP BY c.id, c.nombre
        HAVING COUNT(p.id) > 0
    ");
    $stats['productos_por_categoria'] = $stmt->fetchAll();

    echo json_encode($stats);
}

/* ═══════════════════════════════════════════════════════════════════
   PRODUCTOS
   ═══════════════════════════════════════════════════════════════════ */
function handleProductos(PDO $pdo, string $action) {
    switch ($action) {
        case 'list':
            $search = Auth::sanitize($_GET['search'] ?? '');
            $catId  = (int)($_GET['categoria_id'] ?? 0);

            $sql = "SELECT p.*, COALESCE(c.nombre,'Sin categoría') as categoria_nombre,
                           COALESCE(pr.nombre,'Sin proveedor') as proveedor_nombre
                    FROM productos p
                    LEFT JOIN categorias c ON p.categoria_id=c.id
                    LEFT JOIN proveedores pr ON p.proveedor_id=pr.id
                    WHERE p.activo=1";
            $params = [];

            if ($search !== '') {
                $sql .= " AND (p.nombre LIKE ? OR p.codigo LIKE ?)";
                $params[] = "%{$search}%";
                $params[] = "%{$search}%";
            }
            if ($catId > 0) {
                $sql .= " AND p.categoria_id = ?";
                $params[] = $catId;
            }
            $sql .= " ORDER BY p.nombre ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll());
            break;

        case 'get':
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM productos WHERE id=? AND activo=1");
            $stmt->execute([$id]);
            $product = $stmt->fetch();
            echo json_encode($product ?: ['error' => 'Producto no encontrado.']);
            break;

        case 'create':
            $d = $_POST;
            $imagen = !empty($d['imagen']) ? $d['imagen'] : null;
            $stmt = $pdo->prepare("INSERT INTO productos (codigo,nombre,descripcion,categoria_id,proveedor_id,precio_compra,precio_venta,stock,stock_minimo,imagen)
                                   VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                Auth::sanitize($d['codigo']),
                Auth::sanitize($d['nombre']),
                Auth::sanitize($d['descripcion'] ?? ''),
                (int)$d['categoria_id'] ?: null,
                (int)$d['proveedor_id'] ?: null,
                (float)$d['precio_compra'],
                (float)$d['precio_venta'],
                (int)$d['stock'],
                (int)$d['stock_minimo'],
                $imagen
            ]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'message' => 'Producto creado exitosamente.']);
            break;

        case 'update':
            $d = $_POST;
            if (isset($d['imagen_updated']) && $d['imagen_updated'] === 'true') {
                $imagen = !empty($d['imagen']) ? $d['imagen'] : null;
                $stmt = $pdo->prepare("UPDATE productos SET codigo=?,nombre=?,descripcion=?,categoria_id=?,proveedor_id=?,precio_compra=?,precio_venta=?,stock=?,stock_minimo=?,imagen=? WHERE id=?");
                $stmt->execute([
                    Auth::sanitize($d['codigo']),
                    Auth::sanitize($d['nombre']),
                    Auth::sanitize($d['descripcion'] ?? ''),
                    (int)$d['categoria_id'] ?: null,
                    (int)$d['proveedor_id'] ?: null,
                    (float)$d['precio_compra'],
                    (float)$d['precio_venta'],
                    (int)$d['stock'],
                    (int)$d['stock_minimo'],
                    $imagen,
                    (int)$d['id'],
                ]);
            } else {
                $stmt = $pdo->prepare("UPDATE productos SET codigo=?,nombre=?,descripcion=?,categoria_id=?,proveedor_id=?,precio_compra=?,precio_venta=?,stock=?,stock_minimo=? WHERE id=?");
                $stmt->execute([
                    Auth::sanitize($d['codigo']),
                    Auth::sanitize($d['nombre']),
                    Auth::sanitize($d['descripcion'] ?? ''),
                    (int)$d['categoria_id'] ?: null,
                    (int)$d['proveedor_id'] ?: null,
                    (float)$d['precio_compra'],
                    (float)$d['precio_venta'],
                    (int)$d['stock'],
                    (int)$d['stock_minimo'],
                    (int)$d['id'],
                ]);
            }
            echo json_encode(['success' => true, 'message' => 'Producto actualizado.']);
            break;

        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE productos SET activo=0 WHERE id=?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Producto eliminado.']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida.']);
    }
}

/* ═══════════════════════════════════════════════════════════════════
   CATEGORÍAS
   ═══════════════════════════════════════════════════════════════════ */
function handleCategorias(PDO $pdo, string $action) {
    switch ($action) {
        case 'list':
            $stmt = $pdo->query("
                SELECT c.*, (SELECT COUNT(*) FROM productos p WHERE p.categoria_id=c.id AND p.activo=1) as total_productos
                FROM categorias c WHERE c.activo=1 ORDER BY c.nombre ASC
            ");
            echo json_encode($stmt->fetchAll());
            break;

        case 'create':
            $stmt = $pdo->prepare("INSERT INTO categorias (nombre,descripcion) VALUES (?,?)");
            $stmt->execute([Auth::sanitize($_POST['nombre']), Auth::sanitize($_POST['descripcion'] ?? '')]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'message' => 'Categoría creada.']);
            break;

        case 'update':
            $stmt = $pdo->prepare("UPDATE categorias SET nombre=?,descripcion=? WHERE id=?");
            $stmt->execute([Auth::sanitize($_POST['nombre']), Auth::sanitize($_POST['descripcion'] ?? ''), (int)$_POST['id']]);
            echo json_encode(['success' => true, 'message' => 'Categoría actualizada.']);
            break;

        case 'delete':
            $stmt = $pdo->prepare("UPDATE categorias SET activo=0 WHERE id=?");
            $stmt->execute([(int)$_POST['id']]);
            echo json_encode(['success' => true, 'message' => 'Categoría eliminada.']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida.']);
    }
}

/* ═══════════════════════════════════════════════════════════════════
   PROVEEDORES
   ═══════════════════════════════════════════════════════════════════ */
function handleProveedores(PDO $pdo, string $action) {
    switch ($action) {
        case 'list':
            $stmt = $pdo->query("
                SELECT pr.*, (SELECT COUNT(*) FROM productos p WHERE p.proveedor_id=pr.id AND p.activo=1) as total_productos
                FROM proveedores pr WHERE pr.activo=1 ORDER BY pr.nombre ASC
            ");
            echo json_encode($stmt->fetchAll());
            break;

        case 'create':
            $stmt = $pdo->prepare("INSERT INTO proveedores (nombre,contacto,telefono,email,direccion) VALUES (?,?,?,?,?)");
            $stmt->execute([
                Auth::sanitize($_POST['nombre']),
                Auth::sanitize($_POST['contacto'] ?? ''),
                Auth::sanitize($_POST['telefono'] ?? ''),
                Auth::sanitize($_POST['email'] ?? ''),
                Auth::sanitize($_POST['direccion'] ?? ''),
            ]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'message' => 'Proveedor creado.']);
            break;

        case 'update':
            $stmt = $pdo->prepare("UPDATE proveedores SET nombre=?,contacto=?,telefono=?,email=?,direccion=? WHERE id=?");
            $stmt->execute([
                Auth::sanitize($_POST['nombre']),
                Auth::sanitize($_POST['contacto'] ?? ''),
                Auth::sanitize($_POST['telefono'] ?? ''),
                Auth::sanitize($_POST['email'] ?? ''),
                Auth::sanitize($_POST['direccion'] ?? ''),
                (int)$_POST['id'],
            ]);
            echo json_encode(['success' => true, 'message' => 'Proveedor actualizado.']);
            break;

        case 'delete':
            $stmt = $pdo->prepare("UPDATE proveedores SET activo=0 WHERE id=?");
            $stmt->execute([(int)$_POST['id']]);
            echo json_encode(['success' => true, 'message' => 'Proveedor eliminado.']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida.']);
    }
}

/* ═══════════════════════════════════════════════════════════════════
   CLIENTES
   ═══════════════════════════════════════════════════════════════════ */
function handleClientes(PDO $pdo, string $action) {
    switch ($action) {
        case 'list':
            $stmt = $pdo->query("SELECT * FROM clientes WHERE activo=1 ORDER BY nombre ASC");
            echo json_encode($stmt->fetchAll());
            break;

        case 'create':
            $stmt = $pdo->prepare("INSERT INTO clientes (nombre,telefono,email,direccion) VALUES (?,?,?,?)");
            $stmt->execute([
                Auth::sanitize($_POST['nombre']),
                Auth::sanitize($_POST['telefono'] ?? ''),
                Auth::sanitize($_POST['email'] ?? ''),
                Auth::sanitize($_POST['direccion'] ?? ''),
            ]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'message' => 'Cliente creado.']);
            break;

        case 'update':
            $stmt = $pdo->prepare("UPDATE clientes SET nombre=?,telefono=?,email=?,direccion=? WHERE id=?");
            $stmt->execute([
                Auth::sanitize($_POST['nombre']),
                Auth::sanitize($_POST['telefono'] ?? ''),
                Auth::sanitize($_POST['email'] ?? ''),
                Auth::sanitize($_POST['direccion'] ?? ''),
                (int)$_POST['id'],
            ]);
            echo json_encode(['success' => true, 'message' => 'Cliente actualizado.']);
            break;

        case 'delete':
            $stmt = $pdo->prepare("UPDATE clientes SET activo=0 WHERE id=?");
            $stmt->execute([(int)$_POST['id']]);
            echo json_encode(['success' => true, 'message' => 'Cliente eliminado.']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida.']);
    }
}

/* ═══════════════════════════════════════════════════════════════════
   VENTAS
   ═══════════════════════════════════════════════════════════════════ */
function handleVentas(PDO $pdo, string $action) {
    switch ($action) {
        case 'list':
            $desde = $_GET['desde'] ?? '';
            $hasta = $_GET['hasta'] ?? '';

            $sql = "SELECT v.*, COALESCE(c.nombre,'Público General') as cliente_nombre, u.nombre as vendedor
                    FROM ventas v
                    LEFT JOIN clientes c ON v.cliente_id=c.id
                    JOIN usuarios u ON v.usuario_id=u.id
                    WHERE 1=1";
            $params = [];

            if ($desde) {
                $sql .= " AND DATE(v.created_at) >= ?";
                $params[] = $desde;
            }
            if ($hasta) {
                $sql .= " AND DATE(v.created_at) <= ?";
                $params[] = $hasta;
            }

            $sql .= " ORDER BY v.created_at DESC LIMIT 100";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll());
            break;

        case 'detail':
            $id = (int)($_GET['id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT dv.*, p.nombre as producto_nombre, p.codigo as producto_codigo
                FROM detalle_ventas dv
                JOIN productos p ON dv.producto_id=p.id
                WHERE dv.venta_id=?
            ");
            $stmt->execute([$id]);
            echo json_encode($stmt->fetchAll());
            break;

        case 'create':
            $input = json_decode(file_get_contents('php://input'), true);

            // Validate CSRF from JSON body
            if (!Auth::validateCSRFToken($input['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['error' => 'Token CSRF inválido.']);
                return;
            }

            $items = $input['items'] ?? [];
            if (empty($items)) {
                echo json_encode(['error' => 'No hay productos en la venta.']);
                return;
            }

            $pdo->beginTransaction();
            try {
                // Generate folio
                $stmt = $pdo->query("SELECT COALESCE(MAX(id),0)+1 as next_id FROM ventas");
                $nextId = $stmt->fetch()['next_id'];
                $folio  = 'VTA-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);

                $subtotal = 0;
                // Validate stock and calculate subtotal
                foreach ($items as &$item) {
                    $stmt = $pdo->prepare("SELECT id,precio_venta,stock FROM productos WHERE id=? AND activo=1");
                    $stmt->execute([(int)$item['producto_id']]);
                    $prod = $stmt->fetch();
                    if (!$prod) {
                        throw new Exception("Producto ID {$item['producto_id']} no encontrado.");
                    }
                    if ($prod['stock'] < (int)$item['cantidad']) {
                        throw new Exception("Stock insuficiente para producto ID {$item['producto_id']}.");
                    }
                    $item['precio_unitario'] = $prod['precio_venta'];
                    $item['subtotal_line']   = $prod['precio_venta'] * (int)$item['cantidad'];
                    $subtotal += $item['subtotal_line'];
                }
                unset($item);

                $iva   = round($subtotal * 0.16, 2);
                $total = round($subtotal + $iva, 2);

                // Insert sale
                $stmt = $pdo->prepare("INSERT INTO ventas (folio,cliente_id,usuario_id,subtotal,iva,total,metodo_pago) VALUES (?,?,?,?,?,?,?)");
                $stmt->execute([
                    $folio,
                    (int)($input['cliente_id'] ?? 0) ?: null,
                    $_SESSION['user_id'],
                    $subtotal,
                    $iva,
                    $total,
                    Auth::sanitize($input['metodo_pago'] ?? 'efectivo'),
                ]);
                $ventaId = $pdo->lastInsertId();

                // Insert detail + update stock
                $stmtDetail = $pdo->prepare("INSERT INTO detalle_ventas (venta_id,producto_id,cantidad,precio_unitario,subtotal) VALUES (?,?,?,?,?)");
                $stmtStock  = $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");

                foreach ($items as $item) {
                    $stmtDetail->execute([
                        $ventaId,
                        (int)$item['producto_id'],
                        (int)$item['cantidad'],
                        $item['precio_unitario'],
                        $item['subtotal_line'],
                    ]);
                    $stmtStock->execute([(int)$item['cantidad'], (int)$item['producto_id']]);
                }

                $pdo->commit();
                echo json_encode(['success' => true, 'folio' => $folio, 'total' => $total, 'message' => "Venta {$folio} registrada."]);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;

        case 'cancel':
            Auth::requireAdmin();
            $id = (int)($_POST['id'] ?? 0);

            $pdo->beginTransaction();
            try {
                // Restore stock
                $stmt = $pdo->prepare("SELECT producto_id, cantidad FROM detalle_ventas WHERE venta_id=?");
                $stmt->execute([$id]);
                $details = $stmt->fetchAll();

                $stmtStock = $pdo->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
                foreach ($details as $d) {
                    $stmtStock->execute([$d['cantidad'], $d['producto_id']]);
                }

                // Mark cancelled
                $stmt = $pdo->prepare("UPDATE ventas SET estado='cancelada' WHERE id=?");
                $stmt->execute([$id]);

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Venta cancelada y stock restaurado.']);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['error' => $e->getMessage()]);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida.']);
    }
}

/* ═══════════════════════════════════════════════════════════════════
   USUARIOS (Admin only)
   ═══════════════════════════════════════════════════════════════════ */
function handleUsuarios(PDO $pdo, string $action) {
    Auth::requireAdmin();

    switch ($action) {
        case 'list':
            $stmt = $pdo->query("SELECT id,username,email,nombre,rol,activo,created_at FROM usuarios ORDER BY nombre ASC");
            echo json_encode($stmt->fetchAll());
            break;

        case 'create':
            $pass = password_hash($_POST['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("INSERT INTO usuarios (username,password,email,nombre,rol) VALUES (?,?,?,?,?)");
            $stmt->execute([
                Auth::sanitize($_POST['username']),
                $pass,
                Auth::sanitize($_POST['email'] ?? ''),
                Auth::sanitize($_POST['nombre']),
                in_array($_POST['rol'], ['admin','vendedor']) ? $_POST['rol'] : 'vendedor',
            ]);
            echo json_encode(['success' => true, 'message' => 'Usuario creado.']);
            break;

        case 'update':
            $fields = "nombre=?, email=?, rol=?, activo=?";
            $params = [
                Auth::sanitize($_POST['nombre']),
                Auth::sanitize($_POST['email'] ?? ''),
                in_array($_POST['rol'], ['admin','vendedor']) ? $_POST['rol'] : 'vendedor',
                (int)$_POST['activo'],
            ];

            // Only update password if provided
            if (!empty($_POST['password'])) {
                $fields .= ", password=?";
                $params[] = password_hash($_POST['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            }

            $params[] = (int)$_POST['id'];
            $stmt = $pdo->prepare("UPDATE usuarios SET {$fields} WHERE id=?");
            $stmt->execute($params);
            echo json_encode(['success' => true, 'message' => 'Usuario actualizado.']);
            break;

        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            if ($id === (int)$_SESSION['user_id']) {
                echo json_encode(['error' => 'No puede eliminar su propia cuenta.']);
                return;
            }
            $stmt = $pdo->prepare("UPDATE usuarios SET activo=0 WHERE id=?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Usuario desactivado.']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida.']);
    }
}

/* ═══════════════════════════════════════════════════════════════════
   CONFIGURACION
   ═══════════════════════════════════════════════════════════════════ */
function handleConfiguracion(PDO $pdo, string $action) {
    switch ($action) {
        case 'get':
            $stmt = $pdo->query("SELECT valor FROM configuracion WHERE clave='papeleria_logo'");
            $logo = $stmt->fetchColumn();
            echo json_encode(['logo' => $logo ?: '']);
            break;

        case 'update_logo':
            Auth::requireAdmin();
            $logo = $_POST['logo'] ?? '';
            // Insert or Update the logo
            $stmt = $pdo->prepare("INSERT INTO configuracion (clave, valor) VALUES ('papeleria_logo', ?) ON CONFLICT (clave) DO UPDATE SET valor = EXCLUDED.valor");
            $stmt->execute([$logo]);
            echo json_encode(['success' => true, 'message' => 'Logo actualizado exitosamente.']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida.']);
    }
}

/* ═══════════════════════════════════════════════════════════════════
   AUTH (Public endpoints for password recovery)
   ═══════════════════════════════════════════════════════════════════ */
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function handleAuth(PDO $pdo, string $action) {
    switch ($action) {
        case 'forgot_password':
            $username = trim($_POST['username'] ?? '');
            if (!$username) {
                echo json_encode(['error' => 'Debe proporcionar un nombre de usuario.']);
                return;
            }

            $stmt = $pdo->prepare("SELECT id, nombre, email FROM usuarios WHERE username=? AND activo=1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if (!$user || empty($user['email'])) {
                // Return success to prevent enumeration attacks, but we can return an error if it's an internal tool.
                echo json_encode(['success' => true, 'message' => 'Si el usuario existe y tiene correo, se ha enviado un enlace.']);
                return;
            }

            $email = $user['email'];
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = $pdo->prepare("UPDATE usuarios SET reset_token=?, reset_token_expiry=? WHERE id=?");
            $stmt->execute([$token, $expiry, $user['id']]);

            // Load Composer's autoloader for PHPMailer
            if (file_exists(__DIR__ . '/vendor/autoload.php')) {
                require_once __DIR__ . '/vendor/autoload.php';
            } else {
                echo json_encode(['error' => 'No se encontró PHPMailer. Ejecute composer install.']);
                return;
            }

            $mail = new PHPMailer(true);
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host       = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = getenv('SMTP_USER') ?: 'tu_correo@gmail.com';
                $mail->Password   = getenv('SMTP_PASS') ?: 'tu_contraseña';
                
                $port = getenv('SMTP_PORT') ?: 587;
                $mail->SMTPSecure = ($port == 465) ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = $port;
                $mail->Timeout    = 5; // Timeout rápido de 5 segundos para evitar que la página se quede congelada
                
                // Ignorar verificación de certificados SSL (útil en contenedores Docker donde faltan los CA certs)
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    ]
                ];
                
                $mail->CharSet    = 'UTF-8';

                // Recipients
                $mail->setFrom($mail->Username, 'Papelería Admin');
                $mail->addAddress($email, $user['nombre']);

                // Content
                $resetUrl = "https://" . $_SERVER['HTTP_HOST'] . "/reset.php?token=" . $token;
                
                $mail->isHTML(true);
                $mail->Subject = 'Restablecer Contraseña - Papelería Admin';
                $mail->Body    = "
                    <h2>Hola, {$user['nombre']}</h2>
                    <p>Has solicitado restablecer tu contraseña en el sistema de Papelería Admin.</p>
                    <p>Haz clic en el siguiente enlace para crear una nueva contraseña. Este enlace expira en 1 hora.</p>
                    <p><a href='{$resetUrl}' style='padding: 10px 20px; background-color: #6c5ce7; color: white; text-decoration: none; border-radius: 5px; display: inline-block;'>Restablecer Contraseña</a></p>
                    <p>Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
                    <p>{$resetUrl}</p>
                    <p><small>Si no solicitaste este cambio, puedes ignorar este correo.</small></p>
                ";

                $mail->send();
                echo json_encode(['success' => true, 'message' => 'Se ha enviado un enlace de recuperación a tu correo.']);
            } catch (Exception $e) {
                echo json_encode(['error' => "El correo no pudo ser enviado. Verifique la configuración SMTP. Mailer Error: {$mail->ErrorInfo}"]);
            }
            break;

        case 'reset_password':
            $token = $_POST['token'] ?? '';
            $password = $_POST['password'] ?? '';

            if (!$token || !$password || strlen($password) < 6) {
                echo json_encode(['error' => 'Datos inválidos o la contraseña es muy corta.']);
                return;
            }

            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE reset_token=? AND reset_token_expiry > NOW() AND activo=1");
            $stmt->execute([$token]);
            $user = $stmt->fetch();

            if (!$user) {
                echo json_encode(['error' => 'El enlace es inválido o ha expirado.']);
                return;
            }

            $passHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("UPDATE usuarios SET password=?, reset_token=NULL, reset_token_expiry=NULL WHERE id=?");
            $stmt->execute([$passHash, $user['id']]);

            echo json_encode(['success' => true, 'message' => 'Tu contraseña ha sido actualizada con éxito.']);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida en auth.']);
    }
}
