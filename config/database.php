<?php
/**
 * Database Configuration and Auto-Initialization
 * Papelería Admin System
 * 
 * Uses PDO with prepared statements for SQL injection prevention.
 * Automatically creates database, tables, and sample data on first run.
 * Adapted for PostgreSQL.
 */

function get_env_var($key, $default = '') {
    $val = getenv($key);
    if ($val !== false && $val !== '') return $val;
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
    return $default;
}

$db_url = get_env_var('DATABASE_URL');
if ($db_url) {
    $parsed = parse_url($db_url);
    define('DB_HOST', $parsed['host']);
    $user = $parsed['user'] ?? 'postgres';
    define('DB_USER', $user);
    define('DB_PASS', $parsed['pass'] ?? '');
    define('DB_NAME', ltrim($parsed['path'], '/'));
    define('DB_PORT', $parsed['port'] ?? '5432');
} else {
    define('DB_HOST', get_env_var('PGHOST', 'localhost'));
    define('DB_USER', get_env_var('PGUSER', 'postgres'));
    define('DB_PASS', get_env_var('PGPASSWORD', ''));
    define('DB_NAME', get_env_var('PGDATABASE', 'papeleria_admin'));
    define('DB_PORT', get_env_var('PGPORT', '5432'));
}
define('DB_CHARSET', 'utf8');

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        try {
            $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE  => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES    => false,
            ];

            // Connect to the database
            $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

            // Initialize tables and run migrations
            $this->runMigrations();
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

    private function runMigrations() {
        // Create configuracion table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS configuracion (
                id SERIAL PRIMARY KEY,
                clave VARCHAR(50) UNIQUE NOT NULL,
                valor TEXT NULL
            )
        ");
        $this->pdo->exec("INSERT INTO configuracion (clave, valor) VALUES ('papeleria_logo', '') ON CONFLICT (clave) DO NOTHING");
        
        // Add imagen column if it doesn't exist
        $stmt = $this->pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name='productos' AND column_name='imagen'");
        if ($stmt->rowCount() == 0) {
            try { $this->pdo->exec("ALTER TABLE productos ADD COLUMN imagen TEXT NULL"); } catch(Exception $e) {}
        }
        
        // Add email and token columns to usuarios if they don't exist
        $stmt = $this->pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name='usuarios' AND column_name='email'");
        if ($stmt->rowCount() == 0) {
            try {
                $this->pdo->exec("ALTER TABLE usuarios ADD COLUMN email VARCHAR(100) UNIQUE NULL");
                $this->pdo->exec("ALTER TABLE usuarios ADD COLUMN reset_token VARCHAR(64) NULL");
                $this->pdo->exec("ALTER TABLE usuarios ADD COLUMN reset_token_expiry TIMESTAMP NULL");
            } catch(Exception $e) {}
        }
    }

    private function initializeTables() {
        $stmt = $this->pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public' AND table_name='usuarios'");
        if ($stmt->rowCount() > 0) {
            return; // Already initialized
        }

        // ── Create Tables ──────────────────────────────────────────
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS usuarios (
                id SERIAL PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                email VARCHAR(100) UNIQUE NULL,
                nombre VARCHAR(100) NOT NULL,
                rol VARCHAR(20) DEFAULT 'vendedor',
                activo SMALLINT DEFAULT 1,
                intentos_fallidos INT DEFAULT 0,
                ultimo_intento TIMESTAMP NULL,
                reset_token VARCHAR(64) NULL,
                reset_token_expiry TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS categorias (
                id SERIAL PRIMARY KEY,
                nombre VARCHAR(100) NOT NULL,
                descripcion TEXT,
                activo SMALLINT DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS proveedores (
                id SERIAL PRIMARY KEY,
                nombre VARCHAR(150) NOT NULL,
                contacto VARCHAR(100),
                telefono VARCHAR(20),
                email VARCHAR(100),
                direccion TEXT,
                activo SMALLINT DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS productos (
                id SERIAL PRIMARY KEY,
                codigo VARCHAR(50) UNIQUE NOT NULL,
                nombre VARCHAR(150) NOT NULL,
                descripcion TEXT,
                categoria_id INT,
                proveedor_id INT,
                precio_compra DECIMAL(10,2) NOT NULL,
                precio_venta DECIMAL(10,2) NOT NULL,
                stock INT DEFAULT 0,
                stock_minimo INT DEFAULT 5,
                imagen TEXT NULL,
                activo SMALLINT DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
                FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE SET NULL
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS clientes (
                id SERIAL PRIMARY KEY,
                nombre VARCHAR(150) NOT NULL,
                telefono VARCHAR(20),
                email VARCHAR(100),
                direccion TEXT,
                activo SMALLINT DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS ventas (
                id SERIAL PRIMARY KEY,
                folio VARCHAR(20) UNIQUE NOT NULL,
                cliente_id INT NULL,
                usuario_id INT NOT NULL,
                subtotal DECIMAL(10,2) NOT NULL,
                iva DECIMAL(10,2) NOT NULL DEFAULT 0,
                total DECIMAL(10,2) NOT NULL,
                metodo_pago VARCHAR(20) DEFAULT 'efectivo',
                estado VARCHAR(20) DEFAULT 'completada',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS detalle_ventas (
                id SERIAL PRIMARY KEY,
                venta_id INT NOT NULL,
                producto_id INT NOT NULL,
                cantidad INT NOT NULL,
                precio_unitario DECIMAL(10,2) NOT NULL,
                subtotal DECIMAL(10,2) NOT NULL,
                FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
                FOREIGN KEY (producto_id) REFERENCES productos(id)
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS sesiones_log (
                id SERIAL PRIMARY KEY,
                usuario_id INT NOT NULL,
                ip_address VARCHAR(45),
                user_agent TEXT,
                accion VARCHAR(20) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
            )
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
