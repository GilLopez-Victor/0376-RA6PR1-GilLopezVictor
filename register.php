<?php
// register.php
session_start();
require_once 'php/auth.php';
require_once 'php/config.php';

if (isLoggedIn()) { header('Location: dashboard.php'); exit; }

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre    = trim($_POST['nombre'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $h_entrada = $_POST['hora_entrada'] ?? '09:00';
    $h_salida  = $_POST['hora_salida']  ?? '17:00';

    // Validaciones backend
    if (!$nombre || !$email || !$password || !$password2) {
        $error = 'Todos los campos son obligatorios';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email no válido';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } elseif ($password !== $password2) {
        $error = 'Las contraseñas no coinciden';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Ese email ya está registrado';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO usuarios (nombre, email, password, rol, hora_entrada, hora_salida) VALUES (?, ?, ?, 'empleado', ?, ?)");
            $stmt->execute([$nombre, $email, $hash, $h_entrada . ':00', $h_salida . ':00']);
            $success = '¡Cuenta creada! Ya puedes iniciar sesión.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro · Fichajes</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-box">
        <h1>nuevo<span>_usuario</span></h1>
        <p class="subtitle">Crea tu cuenta de empleado</p>

        <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?> <a href="login.php">Acceder</a></div>
        <?php endif; ?>

        <div id="form-error" class="alert alert-error" style="display:none"></div>

        <?php if (!$success): ?>
        <form method="POST" onsubmit="return validarRegistro()">
            <div class="form-group">
                <label for="nombre">Nombre completo</label>
                <input type="text" id="nombre" name="nombre"
                       value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>"
                       placeholder="Ana Martínez" required>
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="tu@empresa.com" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label for="hora_entrada">Hora de entrada</label>
                    <input type="time" id="hora_entrada" name="hora_entrada"
                           value="<?= htmlspecialchars($_POST['hora_entrada'] ?? '09:00') ?>" required>
                </div>
                <div class="form-group">
                    <label for="hora_salida">Hora de salida</label>
                    <input type="time" id="hora_salida" name="hora_salida"
                           value="<?= htmlspecialchars($_POST['hora_salida'] ?? '17:00') ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" placeholder="Mín. 6 caracteres" required>
            </div>
            <div class="form-group">
                <label for="password2">Repite contraseña</label>
                <input type="password" id="password2" name="password2" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn btn-primary">Crear cuenta</button>
        </form>
        <?php endif; ?>

        <p style="margin-top:1.25rem; text-align:center; font-size:0.875rem; color:var(--muted)">
            ¿Ya tienes cuenta? <a href="login.php">Iniciar sesión</a>
        </p>
    </div>
</div>
<script src="js/app.js"></script>
</body>
</html>
