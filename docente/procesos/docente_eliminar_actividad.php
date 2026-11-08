<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_actividad = isset($_POST['id_actividad']) ? (int)$_POST['id_actividad'] : 0;
    $id_clase = isset($_POST['id_clase']) ? (int)$_POST['id_clase'] : 0;

    if ($id_actividad === 0 || $id_clase === 0) {
        header("Location: ../mis_grupos.php");
        exit();
    }

    try {
        $stmt_check = $pdo->prepare("SELECT archivo_ruta FROM actividades_clase WHERE id_actividad = ? AND id_clase = ?");
        $stmt_check->execute([$id_actividad, $id_clase]);
        $actividad = $stmt_check->fetch(PDO::FETCH_ASSOC);

        if ($actividad) {
            if (!empty($actividad['archivo_ruta'])) {
                $ruta_archivo = '../../../' . $actividad['archivo_ruta'];
                if (file_exists($ruta_archivo) && is_file($ruta_archivo)) {
                    unlink($ruta_archivo);
                }
            }

            $stmt_delete = $pdo->prepare("DELETE FROM actividades_clase WHERE id_actividad = ?");
            $stmt_delete->execute([$id_actividad]);

            header("Location: ../gestionar_aula.php?id_clase=" . $id_clase . "&exito=Actividad eliminada.");
            exit();
        }
    } catch (PDOException $e) {
        header("Location: ../gestionar_aula.php?id_clase=" . $id_clase . "&error=Error interno.");
        exit();
    }
}
?>