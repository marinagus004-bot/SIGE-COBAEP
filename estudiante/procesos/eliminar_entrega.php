<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'estudiante') {
    header("Location: ../../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_alumno = $_SESSION['id_usuario'];
    $id_actividad = isset($_POST['id_actividad']) ? (int)$_POST['id_actividad'] : 0;
    $id_clase = isset($_POST['id_clase']) ? (int)$_POST['id_clase'] : 0;

    if ($id_actividad === 0 || $id_clase === 0) {
        header("Location: ../actividades_escolares.php");
        exit();
    }

    try {
        // 1. Obtener la ruta del archivo para eliminarlo físicamente
        $stmt_archivo = $pdo->prepare("SELECT archivo_ruta FROM entregas_actividad WHERE id_actividad = ? AND id_alumno = ?");
        $stmt_archivo->execute([$id_actividad, $id_alumno]);
        $entrega = $stmt_archivo->fetch(PDO::FETCH_ASSOC);

        if ($entrega && !empty($entrega['archivo_ruta'])) {
            $ruta_fisica = '../../uploads/entregas_alumnos/' . $entrega['archivo_ruta'];
            if (file_exists($ruta_fisica)) {
                unlink($ruta_fisica); // Elimina el archivo del servidor
            }
        }

        // 2. Eliminar el registro de la base de datos
        $stmt_delete = $pdo->prepare("DELETE FROM entregas_actividad WHERE id_actividad = ? AND id_alumno = ?");
        $stmt_delete->execute([$id_actividad, $id_alumno]);

        header("Location: ../aula_virtual.php?id=" . $id_clase . "&exito=Envío eliminado correctamente.");
        exit();

    } catch (PDOException $e) {
        header("Location: ../aula_virtual.php?id=" . $id_clase . "&error=Error al eliminar el envío.");
        exit();
    }
}
?>