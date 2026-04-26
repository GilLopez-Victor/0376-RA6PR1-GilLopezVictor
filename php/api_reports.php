<?php
// php/api_reports.php — API JSON de solo lectura para el manager
// Endpoints: ?type=active | ?type=red_list | ?type=projects
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$db   = getDB();
$type = $_GET['type'] ?? '';

switch ($type) {

    // ── Sesiones activas en este momento ──────────────────────────────────
    case 'active':
        $s = $db->query("
            SELECT te.id, u.nombre as employee, p.nombre as project,
                   te.start_time,
                   ROUND(TIMESTAMPDIFF(SECOND, te.start_time, NOW()) / 3600, 2) as hours_running
            FROM time_entries te
            JOIN usuarios u ON u.id = te.employee_id
            JOIN projects  p ON p.id = te.project_id
            WHERE te.end_time IS NULL
            ORDER BY te.start_time ASC
        ");
        echo json_encode(['active_sessions' => $s->fetchAll()]);
        break;

    // ── Red list: incumplimientos de hoy ──────────────────────────────────
    case 'red_list':
        $hoy = date('Y-m-d');
        $red = [];

        // Todos los empleados
        $empleados = $db->query("SELECT id, nombre, hora_entrada, hora_salida FROM usuarios WHERE rol='empleado'")->fetchAll();

        foreach ($empleados as $emp) {
            // Fichaje del día
            $s = $db->prepare("SELECT * FROM fichajes WHERE usuario_id=? AND fecha=?");
            $s->execute([$emp['id'], $hoy]);
            $fich = $s->fetch();

            // Sin entrada
            if (!$fich || !$fich['hora_entrada_real']) {
                // Solo alertar si ya pasó la hora de entrada
                if (date('H:i:s') > $emp['hora_entrada']) {
                    $red[] = ['employee'=>$emp['nombre'],'issue'=>'no_clock_in','hours_today'=>0,'expected'=>null];
                }
                continue;
            }

            // Llegó tarde
            if ($fich['tarde']) {
                $red[] = ['employee'=>$emp['nombre'],'issue'=>'late_arrival',
                          'clock_in'=>$fich['hora_entrada_real'],'expected_in'=>$emp['hora_entrada']];
            }

            // Horas trabajadas hoy (time_entries)
            $s = $db->prepare("
                SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND,
                           start_time, COALESCE(end_time, NOW())
                       )),0)/3600 AS hours_today
                FROM time_entries WHERE employee_id=? AND DATE(start_time)=?
            ");
            $s->execute([$emp['id'], $hoy]);
            $hrs = round($s->fetch()['hours_today'], 2);

            // Calcular horas esperadas según horario asignado
            $expected = round(
                (strtotime($emp['hora_salida']) - strtotime($emp['hora_entrada'])) / 3600, 2
            );

            // Solo alertar si la jornada ya terminó y tiene pocas horas
            if ($fich['hora_salida_real'] && $hrs < $expected - 0.25) {
                $red[] = ['employee'=>$emp['nombre'],'issue'=>'insufficient_hours',
                          'hours_today'=>$hrs,'expected'=>$expected];
            }

            // Salida anticipada
            if ($fich['hora_salida_real'] && $fich['hora_salida_real'] < $emp['hora_salida']) {
                $red[] = ['employee'=>$emp['nombre'],'issue'=>'early_exit',
                          'clock_out'=>$fich['hora_salida_real'],'expected_out'=>$emp['hora_salida']];
            }
        }

        echo json_encode(['red_list' => $red]);
        break;

    // ── Horas por proyecto ────────────────────────────────────────────────
    case 'projects':
        $s = $db->query("
            SELECT p.id, p.nombre as project, p.contracted_hours,
                   ROUND(COALESCE(SUM(
                       TIMESTAMPDIFF(SECOND, te.start_time, COALESCE(te.end_time, NOW()))
                   ),0)/3600, 2) AS used_hours
            FROM projects p
            LEFT JOIN time_entries te ON te.project_id = p.id
            WHERE p.activo = 1
            GROUP BY p.id
            ORDER BY p.nombre
        ");
        $rows = $s->fetchAll();
        $result = array_map(function($r) {
            $r['remaining_hours'] = round(max(0, $r['contracted_hours'] - $r['used_hours']), 2);
            $r['percent_used']    = $r['contracted_hours'] > 0
                ? round(($r['used_hours'] / $r['contracted_hours']) * 100, 1) : 0;
            return $r;
        }, $rows);
        echo json_encode(['projects' => $result]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Tipo no válido. Usa: active, red_list, projects']);
}
