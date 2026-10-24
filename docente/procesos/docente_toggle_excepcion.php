<?php
session_start();
require_once '../../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    echo json_encode(['success' => false]);
    exit();
}

$id_actividad = isset($_POST['id_actividad']) ? (int)$_POST['id_actividad'] : 0;
$id_alumno = isset($_POST['id_alumno']) ? (int)$_POST['id_alumno'] : 0;
$visible = isset($_POST['visible']) ? (int)$_POST['visible'] : 1;

if ($id_actividad > 0 && $id_alumno > 0) {
    try {
        // Esto inserta el bloqueo, o lo actualiza si ya existía
        $stmt = $pdo->prepare("
            INSERT INTO visibilidad_actividad (id_actividad, id_alumno, visible) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE visible = VALUES(visible)
        ");
        $stmt->execute([$id_actividad, $id_alumno, $visible]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false]);
}
?>