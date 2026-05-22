<?php
/**
 * Login Page — Papelería Admin System
 */
require_once __DIR__ . '/includes/auth.php';

// If already authenticated, redirect to dashboard
if (Auth::isAuthenticated()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$csrfToken = Auth::generateCSRFToken();

$pdo = Database::getInstance()->getConnection();
$stmt = $pdo->query("SELECT valor FROM configuracion WHERE clave='papeleria_logo'");
$logoApp = $stmt->fetchColumn();

// Handle login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido. Recargue la página.';
    } else {
        $auth   = new Auth();
        $result = $auth->login($_POST['username'] ?? '', $_POST['password'] ?? '');
        if ($result['success']) {
            header('Location: dashboard.php');
            exit;
        }
        $error = $result['message'];
        // Regenerate CSRF after failed attempt
        $_SESSION['csrf_token'] = '';
        $csrfToken = Auth::generateCSRFToken();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de Administración de Papelería — Inicio de Sesión">
    <title>Papelería Admin — Iniciar Sesión</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* ── Login-specific overrides ─────────────────────────── */
        body.login-body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-dark);
            overflow: hidden;
            position: relative;
        }
        body.login-body::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background:
                radial-gradient(ellipse at 20% 50%, rgba(108,92,231,.15) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 20%, rgba(0,206,201,.12)  0%, transparent 50%),
                radial-gradient(ellipse at 50% 80%, rgba(253,203,110,.08) 0%, transparent 50%);
            animation: bgShift 20s ease-in-out infinite alternate;
            z-index: 0;
        }
        @keyframes bgShift {
            0%   { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(-5%, -3%) rotate(3deg); }
        }

        .login-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
            padding: 1rem;
        }
        .login-card {
            background: rgba(26,26,46,.75);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 20px;
            padding: 2.5rem 2rem;
            box-shadow: 0 25px 60px rgba(0,0,0,.4);
            animation: cardIn .7s cubic-bezier(.22,1,.36,1);
        }
        @keyframes cardIn {
            from { opacity: 0; transform: translateY(30px) scale(.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-logo .icon-circle {
            width: 72px;
            height: 72px;
            margin: 0 auto 1rem;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 30px rgba(108,92,231,.35);
        }
        .login-logo .icon-circle i {
            font-size: 1.8rem;
            color: #fff;
        }
        .login-logo h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
            margin: 0;
        }
        .login-logo p {
            font-size: .85rem;
            color: var(--text-muted);
            margin-top: .25rem;
        }

        .login-field {
            margin-bottom: 1.25rem;
        }
        .login-field label {
            display: block;
            font-size: .8rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: .4rem;
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .login-field .input-wrap {
            position: relative;
        }
        .login-field .input-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: .9rem;
            transition: color .2s;
        }
        .login-field input {
            width: 100%;
            padding: .75rem .75rem .75rem 2.6rem;
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 10px;
            color: #fff;
            font-family: 'Inter', sans-serif;
            font-size: .95rem;
            transition: border-color .25s, box-shadow .25s;
            outline: none;
            box-sizing: border-box;
        }
        .login-field input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(108,92,231,.25);
        }
        .login-field input:focus + i,
        .login-field input:focus ~ i { color: var(--primary); }

        .login-btn {
            width: 100%;
            padding: .85rem;
            background: linear-gradient(135deg, var(--primary), #8B5CF6);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform .15s, box-shadow .25s;
            margin-top: .5rem;
        }
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(108,92,231,.4);
        }
        .login-btn:active { transform: translateY(0); }

        .login-error {
            background: rgba(255,107,107,.12);
            border: 1px solid rgba(255,107,107,.3);
            color: #FF6B6B;
            padding: .7rem 1rem;
            border-radius: 10px;
            font-size: .85rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: .5rem;
            animation: shakeX .4s;
        }
        @keyframes shakeX {
            0%,100% { transform: translateX(0); }
            20%     { transform: translateX(-6px); }
            40%     { transform: translateX(6px); }
            60%     { transform: translateX(-4px); }
            80%     { transform: translateX(4px); }
        }

        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: .75rem;
            color: var(--text-muted);
        }
        .login-footer strong { color: var(--text-secondary); }

        /* Forgot password modal inside login card */
        #forgot-form-container {
            display: none;
            animation: cardIn .3s ease-out;
        }
        .text-center { text-align: center; }
        .mt-3 { margin-top: 1rem; }
        .text-sm { font-size: 0.85rem; }
        .text-muted { color: var(--text-muted); }
        a.forgot-link {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
            cursor: pointer;
        }
        a.forgot-link:hover { color: #8B5CF6; }
    </style>
</head>
<body class="login-body">

<div class="login-wrapper">
    <div class="login-card">
        <div class="login-logo">
            <?php if (!empty($logoApp)): ?>
                <div style="width: 120px; height: 120px; margin: 0 auto 1.5rem; border-radius: 12px; background: rgba(255,255,255,0.05); padding: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 30px rgba(0,0,0,0.2);">
                    <img src="<?= htmlspecialchars($logoApp) ?>" alt="Logo" style="max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 8px;">
                </div>
            <?php else: ?>
                <div class="icon-circle">
                    <i class="fas fa-store"></i>
                </div>
            <?php endif; ?>
            <h1>Papelería Admin</h1>
            <p>Sistema de Administración</p>
        </div>

        <?php if ($error): ?>
            <div class="login-error" id="login-error">
                <i class="fas fa-exclamation-circle"></i>
                <?= Auth::sanitize($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" autocomplete="off" id="login-form">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

            <div class="login-field">
                <label for="username">Usuario</label>
                <div class="input-wrap">
                    <input type="text" id="username" name="username" placeholder="Ingrese su usuario"
                           required maxlength="50" autocomplete="username">
                    <i class="fas fa-user"></i>
                </div>
            </div>

            <div class="login-field">
                <label for="password">Contraseña</label>
                <div class="input-wrap">
                    <input type="password" id="password" name="password" placeholder="Ingrese su contraseña"
                           required maxlength="255" autocomplete="current-password">
                    <i class="fas fa-lock"></i>
                </div>
            </div>

            <button type="submit" class="login-btn" id="login-btn">
                <i class="fas fa-sign-in-alt"></i> &nbsp;Iniciar Sesión
            </button>
            <div class="text-center mt-3 text-sm">
                <a class="forgot-link" onclick="toggleForgot(true)">¿Olvidaste tu contraseña?</a>
            </div>
        </form>

        <div id="forgot-form-container">
            <h3 style="color:#fff; margin-bottom:0.5rem; text-align:center;">Recuperar Contraseña</h3>
            <p class="text-muted text-center text-sm" style="margin-bottom:1.5rem;">Ingresa tu nombre de usuario. Te enviaremos un enlace de recuperación al correo asociado a tu cuenta.</p>
            
            <form id="forgot-form" onsubmit="event.preventDefault(); submitForgot();">
                <div class="login-field">
                    <label for="forgot-username">Usuario</label>
                    <div class="input-wrap">
                        <input type="text" id="forgot-username" placeholder="Tu nombre de usuario" required>
                        <i class="fas fa-user"></i>
                    </div>
                </div>
                <button type="submit" class="login-btn" id="forgot-btn">
                    <i class="fas fa-paper-plane"></i> &nbsp;Enviar Enlace
                </button>
                <div class="text-center mt-3 text-sm">
                    <a class="forgot-link" onclick="toggleForgot(false)">Volver al Inicio de Sesión</a>
                </div>
            </form>
            <div id="forgot-msg" style="margin-top:1rem;font-size:0.85rem;text-align:center;display:none;"></div>
        </div>

    </div>
</div>

<script>
    function toggleForgot(show) {
        document.getElementById('login-form').style.display = show ? 'none' : 'block';
        document.getElementById('forgot-form-container').style.display = show ? 'block' : 'none';
        document.getElementById('forgot-msg').style.display = 'none';
        if (show) document.getElementById('login-error')?.remove();
    }

    async function submitForgot() {
        const btn = document.getElementById('forgot-btn');
        const msgDiv = document.getElementById('forgot-msg');
        const username = document.getElementById('forgot-username').value;

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> &nbsp;Enviando...';

        try {
            const fd = new FormData();
            fd.append('username', username);
            fd.append('csrf_token', '<?= $csrfToken ?>');
            
            const res = await fetch('api.php?module=auth&action=forgot_password', {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            
            msgDiv.style.display = 'block';
            if (data.success) {
                msgDiv.style.color = '#10b981'; // success
                msgDiv.innerHTML = '<i class="fas fa-check-circle"></i> ' + data.message;
                document.getElementById('forgot-username').value = '';
            } else {
                msgDiv.style.color = '#ef4444'; // danger
                msgDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + (data.error || 'Error al enviar');
            }
        } catch (e) {
            msgDiv.style.display = 'block';
            msgDiv.style.color = '#ef4444';
            msgDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error de conexión (Asegúrate de configurar los correos)';
        }

        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> &nbsp;Enviar Enlace';
    }
</script>

</body>
</html>
