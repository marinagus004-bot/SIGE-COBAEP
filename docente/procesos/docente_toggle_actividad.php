<?php
session_start();
require_once '../../config/database.php';
header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    echo json_encode(['success' => false]);
    exit();
}

$id_actividad = isset($_POST['id_actividad']) ? (int)$_POST['id_actividad'] : 0;
$visible = isset($_POST['visible']) ? (int)$_POST['visible'] : 1;

if ($id_actividad > 0) {
    try {
        $stmt = $pdo->prepare("UPDATE actividades_clase SET visible = ? WHERE id_actividad = ?");
        $stmt->execute([$visible, $id_actividad]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false]);
}
?>