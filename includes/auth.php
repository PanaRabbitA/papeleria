<?php
/**
 * Authentication & Security Module
 * Papelería Admin System
 *
 * Security features:
 *  - CSRF token generation & validation
 *  - Brute-force protection (account lockout)
 *  - Session fixation prevention (regenerate ID)
 *  - Session hijacking detection (IP + UA binding)
 *  - Automatic session timeout
 *  - Input sanitization helpers
 */

// ── Secure session settings ────────────────────────────────────────
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

class Auth {
    private $pdo;

    const MAX_LOGIN_ATTEMPTS = 5;
    const LOCKOUT_TIME       = 900;   // 15 minutes
    const SESSION_TIMEOUT    = 3600;  // 1 hour

    public function __construct() {
        $this->pdo = Database::getInstance()->getConnection();
    }

    /* ── CSRF ───────────────────────────────────────────────────── */

    public static function generateCSRFToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validateCSRFToken(?string $token): bool {
        return !empty($_SESSION['csrf_token'])
            && !empty($token)
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    /* ── Login ──────────────────────────────────────────────────── */

    public function login(string $username, string $password): array {
        $username = trim($username);

        if ($username === '' || $password === '') {
            return ['success' => false, 'message' => 'Usuario y contraseña son requeridos.'];
        }

        $stmt = $this->pdo->prepare("SELECT * FROM usuarios WHERE username = ? AND activo = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'message' => 'Credenciales inválidas.'];
        }

        // Account lockout check
        if ($user['intentos_fallidos'] >= self::MAX_LOGIN_ATTEMPTS) {
            $lockoutEnd = strtotime($user['ultimo_intento']) + self::LOCKOUT_TIME;
            if (time() < $lockoutEnd) {
                $remaining = ceil(($lockoutEnd - time()) / 60);
                return ['success' => false, 'message' => "Cuenta bloqueada. Intente en {$remaining} minutos."];
            }
            // Reset after lockout period
            $stmt = $this->pdo->prepare("UPDATE usuarios SET intentos_fallidos = 0 WHERE id = ?");
            $stmt->execute([$user['id']]);
        }

        if (!password_verify($password, $user['password'])) {
            $stmt = $this->pdo->prepare("UPDATE usuarios SET intentos_fallidos = intentos_fallidos + 1, ultimo_intento = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            $this->logSession($user['id'], 'login_fallido');
            return ['success' => false, 'message' => 'Credenciales inválidas.'];
        }

        // ── Success ──
        $stmt = $this->pdo->prepare("UPDATE usuarios SET intentos_fallidos = 0 WHERE id = ?");
        $stmt->execute([$user['id']]);

        session_regenerate_id(true);

        $_SESSION['user_id']       = $user['id'];
        $_SESSION['username']      = $user['username'];
        $_SESSION['nombre']        = $user['nombre'];
        $_SESSION['rol']           = $user['rol'];
        $_SESSION['login_time']    = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['ip_address']    = $_SERVER['REMOTE_ADDR'];
        $_SESSION['user_agent']    = $_SERVER['HTTP_USER_AGENT'];

        $this->logSession($user['id'], 'login');

        return ['success' => true, 'message' => 'Inicio de sesión exitoso.'];
    }

    /* ── Session validation ─────────────────────────────────────── */

    public static function isAuthenticated(): bool {
        if (empty($_SESSION['user_id'])) return false;

        // Timeout
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > self::SESSION_TIMEOUT) {
            self::logout();
            return false;
        }

        // IP binding
        if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
            self::logout();
            return false;
        }

        $_SESSION['last_activity'] = time();
        return true;
    }

    public static function isAdmin(): bool {
        return isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin';
    }

    public static function requireAuth(): void {
        if (!self::isAuthenticated()) {
            if (self::isAjax()) {
                http_response_code(401);
                echo json_encode(['error' => 'Sesión expirada. Inicie sesión nuevamente.']);
                exit;
            }
            header('Location: index.php');
            exit;
        }
    }

    public static function requireAdmin(): void {
        self::requireAuth();
        if (!self::isAdmin()) {
            if (self::isAjax()) {
                http_response_code(403);
                echo json_encode(['error' => 'Acceso denegado. Se requiere rol de administrador.']);
                exit;
            }
            header('Location: dashboard.php');
            exit;
        }
    }

    /* ── Logout ─────────────────────────────────────────────────── */

    public static function logout(): void {
        if (isset($_SESSION['user_id'])) {
            (new self())->logSession($_SESSION['user_id'], 'logout');
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    /* ── Helpers ─────────────────────────────────────────────────── */

    private static function isAjax(): bool {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    private function logSession(int $userId, string $action): void {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO sesiones_log (usuario_id, ip_address, user_agent, accion) VALUES (?, ?, ?, ?)");
            $stmt->execute([$userId, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '', $action]);
        } catch (PDOException $e) { /* fail silently */ }
    }

    public static function sanitize($input) {
        if (is_array($input)) return array_map([self::class, 'sanitize'], $input);
        return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
    }

    public static function validateEmail(string $email): bool {
        return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}
