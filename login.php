<?php
// login.php
session_start();
require_once 'php/auth.php';

if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$email || !$password) {
        $error = 'Completa todos los campos';
    } elseif (!login($email, $password)) {
        $error = 'Email o contraseña incorrectos';
    } else {
        header('Location: dashboard.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceder · Fichajes</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-box">
        <h1>fichajes<span>_app</span></h1>
        <p class="subtitle">Control de horarios laborales</p>

        <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div id="form-error" class="alert alert-error" style="display:none"></div>

        <form method="POST" onsubmit="return validarLogin()">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="tu@empresa.com" required>
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password"
                       placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-primary">Iniciar sesión</button>
        </form>

        <p style="margin-top:1.25rem; text-align:center; font-size:0.875rem; color:var(--muted)">
            ¿Sin cuenta? <a href="register.php">Regístrate</a>
        </p>

        <div style="margin-top:2rem; padding-top:1rem; border-top:1px solid var(--border); font-size:0.78rem; color:var(--muted)">
            <strong>Demo:</strong><br>
            Admin: admin@empresa.com / admin123<br>
            Empleado: juan@empresa.com / empleado123
        </div>
    </div>
</div>
<script src="js/app.js"></script>
</body>
</html>
