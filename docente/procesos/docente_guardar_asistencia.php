<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') { header("Location: ../../login.php"); exit(); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_clase = (int)$_POST['id_clase'];
    $fecha_asistencia = $_POST['fecha_asistencia'];
    $asistencias = isset($_POST['asistencia']) ? $_POST['asistencia'] : [];

    // Redirige a donde el maestro estaba trabajando
    $referer = $_SERVER['HTTP_REFERER'] ?? "../gestionar_aula.php?id_clase=$id_clase";
    $separador = (strpos($referer, '?') !== false) ? '&' : '?';

    if ($id_clase > 0 && !empty($fecha_asistencia)) {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                INSERT INTO asistencias_clases (id_alumno, id_clase, fecha, estatus) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE estatus = VALUES(estatus)
            ");

            foreach ($asistencias as $id_alumno => $estatus) {
                $stmt->execute([(int)$id_alumno, $id_clase, $fecha_asistencia, $estatus]);
            }

            $pdo->commit();
            header("Location: " . $referer . $separador . "exito=Asistencia guardada correctamente.");
        } catch (Exception $e) {
            $pdo->rollBack();
            header("Location: " . $referer . $separador . "error=Error al guardar asistencia.");
        }
    }
}
?>