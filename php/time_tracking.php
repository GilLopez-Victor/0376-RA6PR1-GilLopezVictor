<?php
// php/time_tracking.php — Endpoint AJAX para control de tiempo por proyecto
// Sigue exactamente el mismo patrón que php/fichajes.php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$action     = $_POST['action'] ?? '';
$db         = getDB();
$emp_id     = (int)$_SESSION['usuario_id'];
$project_id = (int)($_POST['project_id'] ?? 0);
$now        = date('Y-m-d H:i:s');

// Devuelve la entrada activa del empleado (sin end_time)
function getActiveEntry($db, $emp_id) {
    $s = $db->prepare("SELECT te.*, p.nombre as project_name
                        FROM time_entries te
                        JOIN projects p ON p.id = te.project_id
                        WHERE te.employee_id = ? AND te.end_time IS NULL
                        ORDER BY te.id DESC LIMIT 1");
    $s->execute([$emp_id]);
    return $s->fetch();
}

switch ($action) {

    case 'start':
        if (!$project_id) { echo json_encode(['success'=>false,'error'=>'Elige un proyecto']); break; }
        $active = getActiveEntry($db, $emp_id);
        if ($active) { echo json_encode(['success'=>false,'error'=>'Ya tienes una sesión activa. Usa "Cambiar proyecto" o "Parar trabajo".']); break; }
        $s = $db->prepare("INSERT INTO time_entries (employee_id, project_id, start_time) VALUES (?,?,?)");
        $s->execute([$emp_id, $project_id, $now]);
        $pName = $db->prepare("SELECT nombre FROM projects WHERE id=?");
        $pName->execute([$project_id]);
        $p = $pName->fetch();
        echo json_encode(['success'=>true, 'mensaje'=>'▶ Trabajando en: '.$p['nombre'], 'since'=>$now]);
        break;

    case 'switch':
        if (!$project_id) { echo json_encode(['success'=>false,'error'=>'Elige un proyecto']); break; }
        $active = getActiveEntry($db, $emp_id);
        if ($active) {
            // Cerrar entrada actual
            $s = $db->prepare("UPDATE time_entries SET end_time=? WHERE id=?");
            $s->execute([$now, $active['id']]);
        }
        // Abrir nueva
        $s = $db->prepare("INSERT INTO time_entries (employee_id, project_id, start_time) VALUES (?,?,?)");
        $s->execute([$emp_id, $project_id, $now]);
        $pName = $db->prepare("SELECT nombre FROM projects WHERE id=?");
        $pName->execute([$project_id]);
        $p = $pName->fetch();
        echo json_encode(['success'=>true, 'mensaje'=>'🔄 Cambiado a: '.$p['nombre']]);
        break;

    case 'stop':
        $active = getActiveEntry($db, $emp_id);
        if (!$active) { echo json_encode(['success'=>false,'error'=>'No tienes sesión activa']); break; }
        $s = $db->prepare("UPDATE time_entries SET end_time=? WHERE id=?");
        $s->execute([$now, $active['id']]);
        echo json_encode(['success'=>true, 'mensaje'=>'■ Trabajo parado en: '.$active['project_name']]);
        break;

    case 'status':
        $active = getActiveEntry($db, $emp_id);
        // Horas trabajadas hoy
        $s = $db->prepare("SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND,
                                START_TIME,
                                COALESCE(end_time, NOW())
                           )),0) / 3600 as hours_today
                           FROM time_entries
                           WHERE employee_id = ? AND DATE(start_time) = CURDATE()");
        $s->execute([$emp_id]);
        $row = $s->fetch();
        echo json_encode(['success'=>true, 'active'=>$active ?: null, 'hours_today'=>round($row['hours_today'],2)]);
        break;

    default:
        echo json_encode(['success'=>false,'error'=>'Acción no válida']);
}
