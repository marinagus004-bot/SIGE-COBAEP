<?php
session_start();
require_once '../../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') { echo json_encode(['success' => false]); exit(); }

$id_actividad = (int)$_POST['id_actividad'];
$visible = (int)$_POST['visible'];
$id_clase = (int)$_POST['id_clase'];

if ($id_actividad > 0 && $id_clase > 0) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO visibilidad_actividad (id_actividad, id_alumno, visible)
            SELECT ?, id_alumno, ? FROM clase_regular_alumno WHERE id_clase = ?
            ON DUPLICATE KEY UPDATE visible = VALUES(visible)
        ");
        $stmt->execute([$id_actividad, $visible, $id_clase]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) { echo json_encode(['success' => false]); }
}
?>