<?php
/**
 * Database Configuration and Auto-Initialization
 * Papelería Admin System
 * 
 * Uses PDO with prepared statements for SQL injection prevention.
 * Automatically creates database, tables, and sample data on first run.
 */

function get_env_var($key, $default = '') {
    $val = getenv($key);
    if ($val !== false && $val !== '') return $val;
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
    return $default;
}

$mysql_url = get_env_var('MYSQL_URL');
if ($mysql_url) {
    $parsed = parse_url($mysql_url);
    define('DB_HOST', $parsed['host']);
    define('DB_USER', $parsed['user']);
    define('DB_PASS', $parsed['pass'] ?? '');
    define('DB_NAME', ltrim($parsed['path'], '/'));
    define('DB_PORT', $parsed['port'] ?? '3306');
} else {
    define('DB_HOST', get_env_var('MYSQLHOST', 'localhost'));
    define('DB_USER', get_env_var('MYSQLUSER', 'root'));
    define('DB_PASS', get_env_var('MYSQLPASSWORD', ''));
    define('DB_NAME', get_env_var('MYSQLDATABASE', 'papeleria_admin'));
    define('DB_PORT', get_env_var('MYSQLPORT', '3306'));
}
define('DB_CHARSET', 'utf8mb4');

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            // Connect without database first to create it if needed
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE  => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES    => false,
            ];

            $tempPdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $tempPdo = null;

            // Connect to the database
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

            // Initialize tables if needed
            $this->initializeTables();

        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode([
                'error' => 'Error de conexión a la base de datos.',
                'details' => $e->getMessage(),
                'host' => DB_HOST,
                'port' => DB_PORT,
                'user' => DB_USER,
                'dbname' => DB_NAME
            ]));
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->pdo;
    }

    private function initializeTables() {
        $stmt = $this->pdo->query("SHOW TABLES LIKE 'usuarios'");
        if ($stmt->rowCount() > 0) {
            return; // Already initialized
        }

        // ── Create Tables ──────────────────────────────────────────
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS usuarios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                nombre VARCHAR(100) NOT NULL,
                rol ENUM('admin', 'vendedor') DEFAULT 'vendedor',
                activo TINYINT(1) DEFAULT 1,
                intentos_fallidos INT DEFAULT 0,
                ultimo_intento DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS categorias (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(100) NOT NULL,
                descripcion TEXT,
                activo TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS proveedores (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(150) NOT NULL,
                contacto VARCHAR(100),
                telefono VARCHAR(20),
                email VARCHAR(100),
                direccion TEXT,
                activo TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS productos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                codigo VARCHAR(50) UNIQUE NOT NULL,
                nombre VARCHAR(150) NOT NULL,
                descripcion TEXT,
                categoria_id INT,
                proveedor_id INT,
                precio_compra DECIMAL(10,2) NOT NULL,
                precio_venta DECIMAL(10,2) NOT NULL,
                stock INT DEFAULT 0,
                stock_minimo INT DEFAULT 5,
                activo TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
                FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS clientes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(150) NOT NULL,
                telefono VARCHAR(20),
                email VARCHAR(100),
                direccion TEXT,
                activo TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS ventas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                folio VARCHAR(20) UNIQUE NOT NULL,
                cliente_id INT NULL,
                usuario_id INT NOT NULL,
                subtotal DECIMAL(10,2) NOT NULL,
                iva DECIMAL(10,2) NOT NULL DEFAULT 0,
                total DECIMAL(10,2) NOT NULL,
                metodo_pago ENUM('efectivo','tarjeta','transferencia') DEFAULT 'efectivo',
                estado ENUM('completada','cancelada') DEFAULT 'completada',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS detalle_ventas (
                id INT AUTO_INCREMENT PRIMARY KEY,
                venta_id INT NOT NULL,
                producto_id INT NOT NULL,
                cantidad INT NOT NULL,
                precio_unitario DECIMAL(10,2) NOT NULL,
                subtotal DECIMAL(10,2) NOT NULL,
                FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
                FOREIGN KEY (producto_id) REFERENCES productos(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS sesiones_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                usuario_id INT NOT NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                accion ENUM('login','logout','login_fallido') NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // ── Insert Sample Data ─────────────────────────────────────
        $this->insertSampleData();
    }

    private function insertSampleData() {
        // Users
        $adminPass    = password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12]);
        $vendedorPass = password_hash('vendedor123', PASSWORD_BCRYPT, ['cost' => 12]);

        $stmt = $this->pdo->prepare("INSERT INTO usuarios (username, password, nombre, rol) VALUES (?, ?, ?, ?)");
        $stmt->execute(['admin', $adminPass, 'Administrador General', 'admin']);
        $stmt->execute(['vendedor', $vendedorPass, 'Juan Pérez', 'vendedor']);

        // Categories
        $categorias = [
            ['Cuadernos',            'Cuadernos de todas las medidas y tipos'],
            ['Lápices',              'Lápices de grafito y de colores'],
            ['Bolígrafos',           'Bolígrafos y plumas de escritura'],
            ['Papel',                'Papel bond, de colores y especial'],
            ['Carpetas',             'Carpetas, folders y organizadores'],
            ['Marcadores',           'Marcadores permanentes y para pizarrón'],
            ['Gomas y Correctores',  'Gomas de borrar y correctores líquidos'],
            ['Tijeras y Cortadores', 'Tijeras escolares y cutters'],
            ['Adhesivos',            'Pegamentos, cintas adhesivas'],
            ['Reglas y Geometría',   'Reglas, escuadras y transportadores'],
        ];
        $stmt = $this->pdo->prepare("INSERT INTO categorias (nombre, descripcion) VALUES (?, ?)");
        foreach ($categorias as $c) $stmt->execute($c);

        // Suppliers
        $proveedores = [
            ['Distribuidora Escolar S.A.', 'Carlos Martínez', '555-100-2000', 'ventas@distescolar.com',    'Av. Principal #123, CDMX'],
            ['Papeles y Más',              'Laura Gómez',     '555-200-3000', 'contacto@papelesymas.com',  'Calle Reforma #456, Guadalajara'],
            ['Oficina Total',              'Roberto Sánchez', '555-300-4000', 'info@oficinatotal.com',     'Blvd. Industrial #789, Monterrey'],
        ];
        $stmt = $this->pdo->prepare("INSERT INTO proveedores (nombre, contacto, telefono, email, direccion) VALUES (?, ?, ?, ?, ?)");
        foreach ($proveedores as $p) $stmt->execute($p);

        // Products — one per category
        $productos = [
            ['CUA-001', 'Cuaderno Profesional 100 Hojas',    'Cuaderno profesional de raya, pasta dura, 100 hojas',       1, 1, 25.00, 45.00,  50, 10],
            ['LAP-001', 'Lápiz HB No. 2',                    'Lápiz de grafito HB número 2, madera de cedro',             2, 1,  3.00,  8.00, 200, 50],
            ['BOL-001', 'Bolígrafo Punto Fino Azul',          'Bolígrafo de tinta azul, punto fino 0.7mm',                 3, 2,  5.00, 12.00, 150, 30],
            ['PAP-001', 'Resma Papel Bond Carta',             'Resma de 500 hojas de papel bond tamaño carta, 75g/m²',     4, 2, 65.00,120.00,  30,  5],
            ['CAR-001', 'Carpeta Tipo Folder Tamaño Carta',   'Folder manila tamaño carta',                                5, 3,  1.50,  4.00, 300, 50],
            ['MAR-001', 'Marcador Permanente Negro',          'Marcador permanente punta fina, tinta negra',               6, 1,  8.00, 18.00,  80, 15],
            ['GOM-001', 'Goma de Borrar Blanca',              'Goma de borrar suave, no mancha el papel',                  7, 2,  2.50,  6.00, 100, 20],
            ['TIJ-001', 'Tijeras Escolares 13cm',             'Tijeras escolares con punta roma, mango ergonómico',        8, 3, 12.00, 25.00,  40, 10],
            ['PEG-001', 'Pegamento en Barra 21g',             'Pegamento en barra, no tóxico, secado rápido',              9, 1,  7.00, 15.00,  60, 15],
            ['REG-001', 'Regla Plástica 30cm',                'Regla de plástico transparente, 30 centímetros',           10, 3,  4.00, 10.00,  70, 15],
        ];
        $stmt = $this->pdo->prepare("INSERT INTO productos (codigo, nombre, descripcion, categoria_id, proveedor_id, precio_compra, precio_venta, stock, stock_minimo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($productos as $p) $stmt->execute($p);

        // Customers
        $clientes = [
            ['Público General',                   '000-000-0000', '',                         'N/A'],
            ['Escuela Primaria Benito Juárez',     '555-111-2222', 'compras@escuelabj.edu',    'Col. Centro #100'],
            ['María García López',                 '555-333-4444', 'maria.garcia@email.com',   'Calle Hidalgo #55'],
        ];
        $stmt = $this->pdo->prepare("INSERT INTO clientes (nombre, telefono, email, direccion) VALUES (?, ?, ?, ?)");
        foreach ($clientes as $c) $stmt->execute($c);
    }
}

// Auto-initialize on include
$db = Database::getInstance();
