<?php
// php/fichajes.php - Lógica de fichajes (llamado vía AJAX)
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

$action = $_POST['action'] ?? '';
$db = getDB();
$usuario_id = $_SESSION['usuario_id'];
$hoy = date('Y-m-d');
$ahora = date('H:i:s');

// Obtener fichaje de hoy
function getFichajeHoy($db, $usuario_id, $hoy) {
    $stmt = $db->prepare("SELECT * FROM fichajes WHERE usuario_id = ? AND fecha = ?");
    $stmt->execute([$usuario_id, $hoy]);
    return $stmt->fetch();
}

switch ($action) {

    case 'entrada':
        $fichaje = getFichajeHoy($db, $usuario_id, $hoy);
        if ($fichaje && $fichaje['hora_entrada_real']) {
            echo json_encode(['success' => false, 'error' => 'Ya fichaste entrada hoy']);
            break;
        }
        // Comprobar si llega tarde
        $stmt = $db->prepare("SELECT hora_entrada FROM usuarios WHERE id = ?");
        $stmt->execute([$usuario_id]);
        $user = $stmt->fetch();
        $tarde = ($ahora > $user['hora_entrada']) ? 1 : 0;

        if ($fichaje) {
            $stmt = $db->prepare("UPDATE fichajes SET hora_entrada_real = ?, tarde = ? WHERE id = ?");
            $stmt->execute([$ahora, $tarde, $fichaje['id']]);
        } else {
            $stmt = $db->prepare("INSERT INTO fichajes (usuario_id, fecha, hora_entrada_real, tarde) VALUES (?, ?, ?, ?)");
            $stmt->execute([$usuario_id, $hoy, $ahora, $tarde]);
        }
        echo json_encode([
            'success' => true,
            'hora'    => $ahora,
            'tarde'   => (bool)$tarde,
            'mensaje' => $tarde ? '⚠️ Has llegado tarde. Hora prevista: ' . $user['hora_entrada'] : '✅ Entrada registrada correctamente'
        ]);
        break;

    case 'salida':
        $fichaje = getFichajeHoy($db, $usuario_id, $hoy);
        if (!$fichaje || !$fichaje['hora_entrada_real']) {
            echo json_encode(['success' => false, 'error' => 'Primero debes fichar la entrada']);
            break;
        }
        if ($fichaje['hora_salida_real']) {
            echo json_encode(['success' => false, 'error' => 'Ya fichaste salida hoy']);
            break;
        }
        $stmt = $db->prepare("UPDATE fichajes SET hora_salida_real = ? WHERE id = ?");
        $stmt->execute([$ahora, $fichaje['id']]);
        echo json_encode([
            'success' => true,
            'hora'    => $ahora,
            'mensaje' => '👋 Salida registrada. ¡Hasta mañana!'
        ]);
        break;

    case 'estado':
        $fichaje = getFichajeHoy($db, $usuario_id, $hoy);
        echo json_encode([
            'success' => true,
            'fichaje' => $fichaje ?: null
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Acción no válida']);
}
