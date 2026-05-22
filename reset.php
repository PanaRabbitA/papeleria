<?php
/**
 * Password Reset Page
 */
require_once __DIR__ . '/includes/auth.php';

$token = $_GET['token'] ?? '';
if (!$token) {
    die("Token inválido o no proporcionado.");
}

$pdo = Database::getInstance()->getConnection();
$stmt = $pdo->query("SELECT valor FROM configuracion WHERE clave='papeleria_logo'");
$logoApp = $stmt->fetchColumn();

// Validate token initially to show either form or error
$stmt = $pdo->prepare("SELECT id FROM usuarios WHERE reset_token=? AND reset_token_expiry > NOW() AND activo=1");
$stmt->execute([$token]);
$isValid = $stmt->fetch() !== false;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña — Papelería Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
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
        .login-logo { text-align: center; margin-bottom: 2rem; }
        .login-logo h1 { font-size: 1.5rem; font-weight: 700; color: #fff; margin: 0; }
        
        .login-field { margin-bottom: 1.25rem; }
        .login-field label {
            display: block; font-size: .8rem; font-weight: 600;
            color: var(--text-muted); margin-bottom: .4rem; text-transform: uppercase;
        }
        .login-field .input-wrap { position: relative; }
        .login-field .input-wrap i {
            position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
            color: var(--text-muted); font-size: .9rem;
        }
        .login-field input {
            width: 100%; padding: .75rem .75rem .75rem 2.6rem;
            background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1);
            border-radius: 10px; color: #fff; font-family: 'Inter', sans-serif;
            font-size: .95rem; outline: none; box-sizing: border-box;
        }
        .login-field input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(108,92,231,.25); }
        .login-field input:focus + i, .login-field input:focus ~ i { color: var(--primary); }
        
        .login-btn {
            width: 100%; padding: .85rem;
            background: linear-gradient(135deg, var(--primary), #8B5CF6);
            color: #fff; border: none; border-radius: 10px;
            font-family: 'Inter', sans-serif; font-size: 1rem; font-weight: 600;
            cursor: pointer; transition: transform .15s, box-shadow .25s;
        }
        .login-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(108,92,231,.4); }
        
        .alert-error {
            background: rgba(255,107,107,.12); border: 1px solid rgba(255,107,107,.3);
            color: #FF6B6B; padding: .7rem 1rem; border-radius: 10px; font-size: .85rem; margin-bottom: 1rem;
        }
        .alert-success {
            background: rgba(16,185,129,.12); border: 1px solid rgba(16,185,129,.3);
            color: #10b981; padding: .7rem 1rem; border-radius: 10px; font-size: .85rem; margin-bottom: 1rem;
        }
    </style>
</head>
<body class="login-body">
<div class="login-wrapper">
    <div class="login-card">
        <div class="login-logo">
            <?php if (!empty($logoApp)): ?>
                <div style="width: 100px; height: 100px; margin: 0 auto 1.5rem; border-radius: 12px; background: rgba(255,255,255,0.05); padding: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 30px rgba(0,0,0,0.2);">
                    <img src="<?= htmlspecialchars($logoApp) ?>" alt="Logo" style="max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 8px;">
                </div>
            <?php else: ?>
                <div style="width: 72px; height: 72px; margin: 0 auto 1rem; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--secondary)); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-store" style="font-size: 1.8rem; color: #fff;"></i>
                </div>
            <?php endif; ?>
            <h1>Crear Nueva Contraseña</h1>
        </div>

        <?php if (!$isValid): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-triangle"></i> El enlace de recuperación es inválido o ha expirado.
            </div>
            <div style="text-align: center; margin-top: 1rem;">
                <a href="index.php" style="color: var(--primary); text-decoration: none; font-size: 0.9rem;">Ir al Inicio de Sesión</a>
            </div>
        <?php else: ?>
            <div id="reset-success" style="display:none;">
                <div class="alert-success">
                    <i class="fas fa-check-circle"></i> Tu contraseña se actualizó correctamente.
                </div>
                <div style="text-align: center; margin-top: 1.5rem;">
                    <a href="index.php" class="login-btn" style="display:inline-block; text-decoration:none; box-sizing:border-box;">Iniciar Sesión</a>
                </div>
            </div>

            <form id="reset-form" onsubmit="event.preventDefault(); submitReset();">
                <div id="reset-error" class="alert-error" style="display:none;"></div>
                
                <div class="login-field">
                    <label for="new_password">Nueva Contraseña</label>
                    <div class="input-wrap">
                        <input type="password" id="new_password" required minlength="6" placeholder="Mínimo 6 caracteres">
                        <i class="fas fa-lock"></i>
                    </div>
                </div>

                <div class="login-field">
                    <label for="confirm_password">Confirmar Contraseña</label>
                    <div class="input-wrap">
                        <input type="password" id="confirm_password" required minlength="6" placeholder="Repite tu contraseña">
                        <i class="fas fa-lock"></i>
                    </div>
                </div>

                <button type="submit" class="login-btn" id="reset-btn">
                    <i class="fas fa-save"></i> &nbsp;Guardar Contraseña
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
    async function submitReset() {
        const btn = document.getElementById('reset-btn');
        const errDiv = document.getElementById('reset-error');
        const form = document.getElementById('reset-form');
        const pass1 = document.getElementById('new_password').value;
        const pass2 = document.getElementById('confirm_password').value;

        if (pass1 !== pass2) {
            errDiv.style.display = 'block';
            errDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Las contraseñas no coinciden.';
            return;
        }
        if (pass1.length < 6) {
            errDiv.style.display = 'block';
            errDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> La contraseña es muy corta.';
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> &nbsp;Guardando...';
        errDiv.style.display = 'none';

        try {
            const fd = new FormData();
            fd.append('token', '<?= htmlspecialchars($token) ?>');
            fd.append('password', pass1);
            
            const res = await fetch('api.php?module=auth&action=reset_password', {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            
            if (data.success) {
                form.style.display = 'none';
                document.getElementById('reset-success').style.display = 'block';
            } else {
                errDiv.style.display = 'block';
                errDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + (data.error || 'Error al restablecer');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> &nbsp;Guardar Contraseña';
            }
        } catch (e) {
            errDiv.style.display = 'block';
            errDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error de conexión';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-save"></i> &nbsp;Guardar Contraseña';
        }
    }
</script>
</body>
</html>
