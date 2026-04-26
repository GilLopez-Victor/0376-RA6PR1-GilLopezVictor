<?php
// dashboard.php
session_start();
require_once 'php/auth.php';
require_once 'php/config.php';
requireLogin();

$user = getCurrentUser();
$db   = getDB();
$hoy  = date('Y-m-d');

// Manejar cambio de horario (admin sobre empleado)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_horario']) && isAdmin()) {
    $uid = (int)($_POST['uid'] ?? 0);
    $he  = $_POST['hora_entrada_new'] ?? '';
    $hs  = $_POST['hora_salida_new']  ?? '';
    if ($uid && $he && $hs) {
        $s = $db->prepare("UPDATE usuarios SET hora_entrada=?, hora_salida=? WHERE id=?");
        $s->execute([$he.':00', $hs.':00', $uid]);
    }
    header('Location: dashboard.php');
    exit;
}

// Fichaje del usuario hoy
$stmt = $db->prepare("SELECT * FROM fichajes WHERE usuario_id = ? AND fecha = ?");
$stmt->execute([$user['id'], $hoy]);
$fichajeHoy = $stmt->fetch();

// Historial propio (últimos 30 días)
$stmt = $db->prepare("SELECT * FROM fichajes WHERE usuario_id = ? ORDER BY fecha DESC, id DESC LIMIT 30");
$stmt->execute([$user['id']]);
$historial = $stmt->fetchAll();

// Admin: todos los usuarios y sus fichajes de hoy
$todosUsuarios = [];
$todosHoy = [];
if (isAdmin()) {
    $todosUsuarios = $db->query("SELECT id, nombre, email, rol, hora_entrada, hora_salida FROM usuarios ORDER BY rol DESC, nombre ASC")->fetchAll();
    $todosHoy = $db->query("
        SELECT f.*, u.nombre, u.hora_entrada as he_prevista
        FROM fichajes f
        JOIN usuarios u ON u.id = f.usuario_id
        WHERE f.fecha = '$hoy'
        ORDER BY f.id DESC
    ")->fetchAll();
}

function fmt($t) { return $t ? substr($t, 0, 5) : '—'; }

// ── Time tracking: proyectos disponibles + sesión activa del usuario ──
$projects = $db->query("SELECT id, nombre FROM projects WHERE activo=1 ORDER BY nombre")->fetchAll();
$activeEntry = null;
$s = $db->prepare("SELECT te.*, p.nombre as project_name FROM time_entries te
                   JOIN projects p ON p.id = te.project_id
                   WHERE te.employee_id = ? AND te.end_time IS NULL ORDER BY te.id DESC LIMIT 1");
$s->execute([$user['id']]);
$activeEntry = $s->fetch();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel · Fichajes</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
    <div class="container">
        <span class="navbar-brand">fichajes<span>_app</span></span>
        <div class="navbar-user">
            <span>Hola, <strong><?= htmlspecialchars($user['nombre']) ?></strong></span>
            <span class="badge <?= $user['rol'] === 'admin' ? 'badge-blue' : 'badge-green' ?>">
                <?= $user['rol'] ?>
            </span>
            <form method="POST" action="logout.php" style="margin:0">
                <button type="submit" class="btn-logout">Salir</button>
            </form>
        </div>
    </div>
</nav>

<div class="container">

    <!-- Header -->
    <div class="page-header">
        <h1>Panel de fichajes</h1>
        <p>
            Horario asignado:
            <strong><?= fmt($user['hora_entrada']) ?> – <?= fmt($user['hora_salida']) ?></strong>
        </p>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab-btn active" data-tab="tab-fichar">Fichar hoy</button>
        <button class="tab-btn" data-tab="tab-historial">Mi historial</button>
        <button class="tab-btn" data-tab="tab-proyectos">Proyectos</button>
        <?php if (isAdmin()): ?>
        <button class="tab-btn" data-tab="tab-admin">Admin</button>
        <button class="tab-btn" data-tab="tab-manager">Manager</button>
        <?php endif; ?>
    </div>

    <!-- TAB: Fichar hoy -->
    <div id="tab-fichar" class="tab-content active">
        <div class="fichar-card">
            <h2>Control de presencia</h2>
            <div class="clock" id="reloj">--:--:--</div>
            <div class="clock-date" id="reloj-fecha"></div>

            <div class="fichar-btns">
                <button id="btn-entrada"
                        class="btn btn-green"
                        onclick="fichar('entrada')"
                        <?= ($fichajeHoy && $fichajeHoy['hora_entrada_real']) ? 'disabled' : '' ?>>
                    ▶ Fichar entrada
                </button>
                <button id="btn-salida"
                        class="btn btn-red"
                        onclick="fichar('salida')"
                        <?= (!$fichajeHoy || !$fichajeHoy['hora_entrada_real'] || $fichajeHoy['hora_salida_real']) ? 'disabled' : '' ?>>
                    ■ Fichar salida
                </button>
            </div>

            <!-- Estado de hoy -->
            <div class="status-row">
                <div class="status-item">
                    <div class="label">Entrada hoy</div>
                    <div class="value">
                        <?php if ($fichajeHoy && $fichajeHoy['hora_entrada_real']): ?>
                            <?= fmt($fichajeHoy['hora_entrada_real']) ?>
                            <?php if ($fichajeHoy['tarde']): ?>
                                <span class="badge badge-red" style="margin-left:0.5rem">tarde</span>
                            <?php else: ?>
                                <span class="badge badge-green" style="margin-left:0.5rem">puntual</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color:var(--muted)">Sin fichar</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="status-item">
                    <div class="label">Salida hoy</div>
                    <div class="value">
                        <?php if ($fichajeHoy && $fichajeHoy['hora_salida_real']): ?>
                            <?= fmt($fichajeHoy['hora_salida_real']) ?>
                        <?php elseif ($fichajeHoy && $fichajeHoy['hora_entrada_real']): ?>
                            <span style="color:var(--yellow)">Pendiente</span>
                        <?php else: ?>
                            <span style="color:var(--muted)">—</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="status-item">
                    <div class="label">Horario previsto</div>
                    <div class="value"><?= fmt($user['hora_entrada']) ?> – <?= fmt($user['hora_salida']) ?></div>
                </div>
            </div>

            <!-- Mensaje dinámico AJAX -->
            <div id="mensaje-fichaje" class="alert"></div>

            <?php if ($fichajeHoy && $fichajeHoy['hora_entrada_real'] && !$fichajeHoy['hora_salida_real']): ?>
            <div class="alert alert-warning" style="margin-top:1rem">
                ⏳ Recuerda fichar la salida al terminar tu jornada.
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB: Historial propio -->
    <div id="tab-historial" class="tab-content">
        <p class="section-title">Mis últimos fichajes (30 días)</p>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Entrada real</th>
                        <th>Salida real</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($historial)): ?>
                    <tr><td colspan="4" style="text-align:center; color:var(--muted)">Sin fichajes registrados</td></tr>
                <?php else: ?>
                    <?php foreach ($historial as $f): ?>
                    <tr class="<?= $f['tarde'] ? 'tarde' : '' ?>">
                        <td><?= htmlspecialchars($f['fecha']) ?></td>
                        <td><?= fmt($f['hora_entrada_real']) ?></td>
                        <td>
                            <?php if ($f['hora_salida_real']): ?>
                                <?= fmt($f['hora_salida_real']) ?>
                            <?php elseif ($f['hora_entrada_real']): ?>
                                <span style="color:var(--yellow)">Pendiente</span>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$f['hora_entrada_real']): ?>
                                <span class="badge badge-yellow">Sin entrada</span>
                            <?php elseif ($f['tarde']): ?>
                                <span class="badge badge-red">Tarde</span>
                            <?php else: ?>
                                <span class="badge badge-green">Puntual</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB: Admin -->
    <!-- TAB: Proyectos (time tracking) -->
    <div id="tab-proyectos" class="tab-content">
        <div class="fichar-card">
            <h2>Control de tiempo por proyecto</h2>

            <?php if ($activeEntry): ?>
            <div class="alert alert-info" style="margin-bottom:1rem">
                ▶ Trabajando en <strong><?= htmlspecialchars($activeEntry['project_name']) ?></strong>
                desde <?= date('H:i', strtotime($activeEntry['start_time'])) ?>
            </div>
            <?php endif; ?>

            <div class="form-group" style="max-width:320px">
                <label for="sel-proyecto">Proyecto</label>
                <select id="sel-proyecto">
                    <option value="">— Selecciona proyecto —</option>
                    <?php foreach ($projects as $p): ?>
                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="fichar-btns" style="max-width:520px">
                <button class="btn btn-green" onclick="ttAction('start')">▶ Iniciar trabajo</button>
                <button class="btn btn-primary" onclick="ttAction('switch')" style="background:var(--accent)">🔄 Cambiar proyecto</button>
                <button class="btn btn-red" onclick="ttAction('stop')" style="grid-column:span 2">■ Parar trabajo</button>
            </div>

            <div id="msg-tt" class="alert" style="display:none;margin-top:1rem"></div>
        </div>

        <!-- Mis horas por proyecto (últimos 30 días) -->
        <?php
        $myHours = $db->prepare("
            SELECT p.nombre, ROUND(SUM(TIMESTAMPDIFF(SECOND, te.start_time, COALESCE(te.end_time,NOW())))/3600,2) as horas
            FROM time_entries te JOIN projects p ON p.id=te.project_id
            WHERE te.employee_id=? AND te.start_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY p.id ORDER BY horas DESC
        ");
        $myHours->execute([$user['id']]);
        $myHoursData = $myHours->fetchAll();
        ?>
        <?php if ($myHoursData): ?>
        <p class="section-title">Mis horas por proyecto (últimos 30 días)</p>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Proyecto</th><th>Horas</th></tr></thead>
                <tbody>
                <?php foreach ($myHoursData as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['nombre']) ?></td>
                    <td style="font-family:var(--font-mono)"><?= $r['horas'] ?>h</td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <?php if (isAdmin()): ?>
    <!-- TAB: Manager -->
    <div id="tab-manager" class="tab-content">

        <!-- Empleados activos ahora -->
        <p class="section-title">Empleados trabajando ahora</p>
        <div class="table-wrap" style="margin-bottom:2rem" id="active-sessions-wrap">
            <table><thead><tr><th>Empleado</th><th>Proyecto</th><th>Desde</th><th>Horas</th></tr></thead>
            <tbody id="active-sessions-body">
                <tr><td colspan="4" style="text-align:center;color:var(--muted)">Cargando…</td></tr>
            </tbody></table>
        </div>

        <!-- Red list -->
        <p class="section-title">⚠️ Lista roja — incumplimientos de hoy</p>
        <div class="table-wrap" style="margin-bottom:2rem">
            <table><thead><tr><th>Empleado</th><th>Problema</th><th>Detalle</th></tr></thead>
            <tbody id="red-list-body">
                <tr><td colspan="3" style="text-align:center;color:var(--muted)">Cargando…</td></tr>
            </tbody></table>
        </div>

        <!-- Horas por proyecto -->
        <p class="section-title">Resumen de proyectos</p>
        <div id="project-report-wrap" style="margin-bottom:2rem"></div>

        <button class="btn btn-primary" style="width:auto;padding:0.5rem 1.5rem" onclick="loadManagerData()">↻ Actualizar</button>
    </div>
    <?php endif; ?>



    <?php if (isAdmin()): ?>
    <div id="tab-admin" class="tab-content">
        <!-- Fichajes de hoy -->
        <p class="section-title" style="margin-bottom:0.75rem">Fichajes de hoy — <?= $hoy ?></p>
        <div class="table-wrap" style="margin-bottom:2rem">
            <table>
                <thead>
                    <tr><th>Empleado</th><th>Entrada prevista</th><th>Entrada real</th><th>Salida real</th><th>Estado</th></tr>
                </thead>
                <tbody>
                <?php if (empty($todosHoy)): ?>
                    <tr><td colspan="5" style="text-align:center;color:var(--muted)">Nadie ha fichado hoy</td></tr>
                <?php else: ?>
                    <?php foreach ($todosHoy as $f): ?>
                    <tr class="<?= $f['tarde'] ? 'tarde' : '' ?>">
                        <td><?= htmlspecialchars($f['nombre']) ?></td>
                        <td><?= fmt($f['he_prevista']) ?></td>
                        <td><?= fmt($f['hora_entrada_real']) ?></td>
                        <td>
                            <?php if ($f['hora_salida_real']): ?>
                                <?= fmt($f['hora_salida_real']) ?>
                            <?php elseif ($f['hora_entrada_real']): ?>
                                <span style="color:var(--yellow)">Pendiente</span>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td>
                            <?php if ($f['tarde']): ?>
                                <span class="badge badge-red">Tarde</span>
                            <?php else: ?>
                                <span class="badge badge-green">Puntual</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Gestión de usuarios -->
        <p class="section-title" style="margin-bottom:0.75rem">Todos los usuarios</p>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Entrada</th><th>Salida</th><th>Acción</th></tr>
                </thead>
                <tbody>
                <?php foreach ($todosUsuarios as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['nombre']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td>
                        <span class="badge <?= $u['rol'] === 'admin' ? 'badge-blue' : 'badge-green' ?>">
                            <?= $u['rol'] ?>
                        </span>
                    </td>
                    <td><?= fmt($u['hora_entrada']) ?></td>
                    <td><?= fmt($u['hora_salida']) ?></td>
                    <td>
                        <form method="POST" style="display:flex;gap:0.4rem;align-items:center;flex-wrap:wrap">
                            <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                            <input type="hidden" name="update_horario" value="1">
                            <input type="time" name="hora_entrada_new"
                                   value="<?= substr($u['hora_entrada'],0,5) ?>"
                                   style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:0.3rem 0.5rem;border-radius:4px;font-size:0.8rem;width:110px">
                            <input type="time" name="hora_salida_new"
                                   value="<?= substr($u['hora_salida'],0,5) ?>"
                                   style="background:var(--bg3);border:1px solid var(--border);color:var(--text);padding:0.3rem 0.5rem;border-radius:4px;font-size:0.8rem;width:110px">
                            <button type="submit" class="btn btn-primary"
                                    style="width:auto;padding:0.3rem 0.75rem;font-size:0.8rem">
                                Guardar
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /container -->

<script src="js/app.js"></script>
</body>
</html>
