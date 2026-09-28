<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'prefectos') {
    header("Location: ../../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_asistencia = $_POST['id_asistencia'] ?? null;

    if ($id_asistencia) {
        // Obtenemos el estado actual
        $stmt = $pdo->prepare("SELECT estatus FROM ASISTENCIAS WHERE id_asistencia = ?");
        $stmt->execute([$id_asistencia]);
        $asistencia = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($asistencia) {
            $nuevo_estatus = '';
            
            // Evaluamos para cambiar el estado correctamente
            if ($asistencia['estatus'] === 'Falta') {
                $nuevo_estatus = 'Falta Justificada';
            } elseif ($asistencia['estatus'] === 'Retardo') {
                $nuevo_estatus = 'Retardo Justificado';
            }

            // Actualizamos solo si era Falta o Retardo
            if ($nuevo_estatus !== '') {
                $update = $pdo->prepare("UPDATE ASISTENCIAS SET estatus = ? WHERE id_asistencia = ?");
                $update->execute([$nuevo_estatus, $id_asistencia]);
            }
        }
    }
}

// Regresamos al historial
header("Location: ../historial_asistencias.php");
exit();
?>