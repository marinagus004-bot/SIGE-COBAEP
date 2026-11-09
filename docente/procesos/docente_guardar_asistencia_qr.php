<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit();
}

$qr_crudo = isset($_POST['id_alumno']) ? $_POST['id_alumno'] : '';
$id_clase = isset($_POST['id_clase']) ? (int)$_POST['id_clase'] : 0;
require_once __DIR__ . '/permisos.php';
if (!docente_dueno_clase($pdo, $id_clase)) docente_denegar(true);
$fecha = isset($_POST['fecha']) ? trim($_POST['fecha']) : date('Y-m-d');

if (empty($qr_crudo) || $id_clase === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Datos incompletos']);
    exit();
}

try {
    // 1. Limpiamos la lectura del QR
    $qr_leido = preg_replace('/[^a-zA-Z0-9\/]/', '', $qr_crudo);

    if (empty($qr_leido)) {
         echo json_encode(['status' => 'error', 'message' => 'Código QR vacío o ilegible']);
         exit();
    }

    // 2. Buscamos el ID del alumno por su matrícula
    if (is_numeric($qr_leido)) {
        $stmt = $pdo->prepare("SELECT id_alumno FROM alumnos WHERE matricula = ? OR id_alumno = ?");
        $stmt->execute([$qr_leido, (int)$qr_leido]);
    } else {
        $stmt = $pdo->prepare("SELECT id_alumno FROM alumnos WHERE matricula = ?");
        $stmt->execute([$qr_leido]);
    }
    
    $id_alumno = $stmt->fetchColumn();

    if (!$id_alumno) {
         echo json_encode(['status' => 'error', 'message' => 'No registrado: ' . htmlspecialchars($qr_crudo)]);
         exit();
    }

    // 3. Verificamos en "asistencias_clases" con la columna "fecha"
    $stmtCheck = $pdo->prepare("SELECT id_asistencia FROM asistencias_clases WHERE id_clase = ? AND id_alumno = ? AND fecha = ?");
    $stmtCheck->execute([$id_clase, $id_alumno, $fecha]);
    
    if ($stmtCheck->fetchColumn()) {
        echo json_encode(['status' => 'warning', 'message' => 'Ya fue capturado', 'id_alumno' => $id_alumno]);
        exit();
    }

    // 4. Insertamos en "asistencias_clases" usando la columna "estatus"
    $stmtInsert = $pdo->prepare("INSERT INTO asistencias_clases (id_clase, id_alumno, fecha, estatus) VALUES (?, ?, ?, 'Presente')");
    $resultado = $stmtInsert->execute([$id_clase, $id_alumno, $fecha]);

    if ($resultado) {
        echo json_encode(['status' => 'success', 'message' => 'Asistencia tomada', 'id_alumno' => $id_alumno]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al guardar']);
    }

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error SQL: ' . $e->getMessage()]);
}
?>