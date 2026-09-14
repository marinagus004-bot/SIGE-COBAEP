<?php
session_start();
require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $motivo = $_POST['motivo'] ?? '';
    $fecha_inicio = $_POST['fecha_inicio'] ?? '';
    $fecha_fin = $_POST['fecha_fin'] ?? '';

    if (!empty($motivo) && !empty($fecha_inicio) && !empty($fecha_fin)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO SUSPENSIONES_CLASES (motivo, fecha_inicio, fecha_fin) VALUES (?, ?, ?)");
            $stmt->execute([$motivo, $fecha_inicio, $fecha_fin]);
            
            header("Location: ../dashboard_prefectura.php?msg=suspension_registrada");
            exit();
        } catch (PDOException $e) {
            die("Error de BD: " . $e->getMessage());
        }
    }
}
header("Location: ../dashboard_prefectura.php");
exit();
?>