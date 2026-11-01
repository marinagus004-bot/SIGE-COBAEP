<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    echo json_encode(['success' => false]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_alumno = isset($_POST['id_alumno']) ? (int)$_POST['id_alumno'] : 0;
    $id_clase = isset($_POST['id_clase']) ? (int)$_POST['id_clase'] : 0;
    $acceso = isset($_POST['acceso']) ? (int)$_POST['acceso'] : 1;

    if ($id_alumno > 0 && $id_clase > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE clase_regular_alumno SET acceso = ? WHERE id_alumno = ? AND id_clase = ?");
            $stmt->execute([$acceso, $id_alumno, $id_clase]);
            echo json_encode(['success' => true]);
            exit();
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            exit();
        }
    }
}
echo json_encode(['success' => false]);
?>