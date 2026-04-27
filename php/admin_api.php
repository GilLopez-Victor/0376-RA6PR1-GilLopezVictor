<?php
// php/admin_api.php — API REST per al panel d'administrador
// Endpoints:
//   GET  ?resource=users
//   POST ?resource=users          (crear usuari)
//   POST ?resource=users&_method=DELETE  (eliminar usuari)
//   GET  ?resource=projects
//   POST ?resource=projects        (crear projecte)
//   POST ?resource=projects&_method=DELETE (eliminar projecte)
//
// Tots els endpoints requereixen rol admin.

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

// ── Protecció: només admin ─────────────────────────────────────────────────
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accés denegat']);
    exit;
}

$db       = getDB();
$resource = $_GET['resource'] ?? $_POST['resource'] ?? '';
$method   = $_POST['_method'] ?? $_SERVER['REQUEST_METHOD'];

// ── USUARIS ────────────────────────────────────────────────────────────────
if ($resource === 'users') {

    if ($method === 'GET') {
        // Llistar tots els usuaris (sense password)
        $stmt = $db->query("SELECT id, nombre, email, rol, hora_entrada, hora_salida, created_at
                             FROM usuarios ORDER BY rol DESC, nombre ASC");
        echo json_encode(['success' => true, 'users' => $stmt->fetchAll()]);

    } elseif ($method === 'POST') {
        // Crear nou usuari
        $nombre   = trim($_POST['nombre'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $rol      = $_POST['rol'] ?? 'empleado';
        $h_entra  = $_POST['hora_entrada'] ?? '09:00';
        $h_surt   = $_POST['hora_salida']  ?? '17:00';

        // Validacions backend
        if (!$nombre || !$email || !$password) {
            echo json_encode(['success' => false, 'error' => 'Nom, email i contrasenya són obligatoris']); exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'error' => 'Email no vàlid']); exit;
        }
        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'error' => 'La contrasenya ha de tenir mínim 6 caràcters']); exit;
        }
        if (!in_array($rol, ['admin', 'empleado'])) {
            echo json_encode(['success' => false, 'error' => 'Rol no vàlid']); exit;
        }

        // Comprovar email duplicat
        $check = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
        $check->execute([$email]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Ja existeix un usuari amb aquest email']); exit;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO usuarios (nombre, email, password, rol, hora_entrada, hora_salida)
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$nombre, $email, $hash, $rol, $h_entra . ':00', $h_surt . ':00']);

        echo json_encode(['success' => true, 'id' => $db->lastInsertId(), 'mensaje' => 'Usuari creat correctament']);

    } elseif ($method === 'DELETE') {
        // Eliminar usuari
        $uid = (int)($_POST['id'] ?? 0);

        if (!$uid) {
            echo json_encode(['success' => false, 'error' => 'ID invàlid']); exit;
        }
        // Evitar que l'admin s'elimini a si mateix
        if ($uid === (int)$_SESSION['usuario_id']) {
            echo json_encode(['success' => false, 'error' => 'No pots eliminar el teu propi compte']); exit;
        }

        $stmt = $db->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$uid]);

        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'error' => 'Usuari no trobat']); exit;
        }
        echo json_encode(['success' => true, 'mensaje' => 'Usuari eliminat']);

    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Mètode no permès']);
    }

// ── PROJECTES ──────────────────────────────────────────────────────────────
} elseif ($resource === 'projects') {

    if ($method === 'GET') {
        // Llistar projectes amb hores usades
        $stmt = $db->query("
            SELECT p.id, p.nombre, p.description, p.contracted_hours, p.activo,
                   p.created_at, p.created_by, u.nombre as created_by_name,
                   ROUND(COALESCE(SUM(
                       TIMESTAMPDIFF(SECOND, te.start_time, COALESCE(te.end_time, NOW()))
                   ), 0) / 3600, 2) AS used_hours
            FROM projects p
            LEFT JOIN usuarios u  ON u.id  = p.created_by
            LEFT JOIN time_entries te ON te.project_id = p.id
            GROUP BY p.id
            ORDER BY p.nombre ASC
        ");
        echo json_encode(['success' => true, 'projects' => $stmt->fetchAll()]);

    } elseif ($method === 'POST') {
        // Crear projecte
        $nombre    = trim($_POST['nombre'] ?? '');
        $desc      = trim($_POST['description'] ?? '');
        $hours     = (float)($_POST['contracted_hours'] ?? 0);
        $creator   = (int)$_SESSION['usuario_id'];

        if (!$nombre) {
            echo json_encode(['success' => false, 'error' => 'El nom del projecte és obligatori']); exit;
        }
        if ($hours < 0) {
            echo json_encode(['success' => false, 'error' => 'Les hores contractades no poden ser negatives']); exit;
        }

        $stmt = $db->prepare("INSERT INTO projects (nombre, description, contracted_hours, created_by)
                               VALUES (?, ?, ?, ?)");
        $stmt->execute([$nombre, $desc ?: null, $hours, $creator]);

        echo json_encode(['success' => true, 'id' => $db->lastInsertId(), 'mensaje' => 'Projecte creat correctament']);

    } elseif ($method === 'DELETE') {
        // Eliminar projecte
        $pid = (int)($_POST['id'] ?? 0);

        if (!$pid) {
            echo json_encode(['success' => false, 'error' => 'ID invàlid']); exit;
        }

        // Comprovar si té time_entries actives
        $active = $db->prepare("SELECT COUNT(*) FROM time_entries WHERE project_id = ? AND end_time IS NULL");
        $active->execute([$pid]);
        if ((int)$active->fetchColumn() > 0) {
            echo json_encode(['success' => false, 'error' => 'No es pot eliminar: hi ha empleats treballant en aquest projecte ara mateix']); exit;
        }

        $stmt = $db->prepare("DELETE FROM projects WHERE id = ?");
        $stmt->execute([$pid]);

        if ($stmt->rowCount() === 0) {
            echo json_encode(['success' => false, 'error' => 'Projecte no trobat']); exit;
        }
        echo json_encode(['success' => true, 'mensaje' => 'Projecte eliminat']);

    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Mètode no permès']);
    }

} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Recurs no vàlid. Usa: users, projects']);
}