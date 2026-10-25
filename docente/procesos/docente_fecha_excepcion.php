<?php
session_start();
require_once '../../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') { echo json_encode(['success' => false]); exit(); }

$id_actividad = (int)$_POST['id_actividad'];
$id_alumno = (int)$_POST['id_alumno'];
$fecha = empty($_POST['fecha']) ? null : $_POST['fecha'];

if ($id_actividad > 0 && $id_alumno > 0) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO visibilidad_actividad (id_actividad, id_alumno, visible, fecha_limite_excepcion)
            VALUES (?, ?, 1, ?) 
            ON DUPLICATE KEY UPDATE fecha_limite_excepcion = VALUES(fecha_limite_excepcion)
        ");
        $stmt->execute([$id_actividad, $id_alumno, $fecha]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) { echo json_encode(['success' => false]); }
}
?>