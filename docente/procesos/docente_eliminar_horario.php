<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../../login.php");
    exit();
}

if (isset($_GET['id_horario']) && isset($_GET['id_clase'])) {
    $id_horario = (int)$_GET['id_horario'];
    $id_clase = (int)$_GET['id_clase'];
    require_once __DIR__ . '/permisos.php';
    if (!docente_dueno_clase($pdo, $id_clase)) docente_denegar(false);

    try {
        $stmt = $pdo->prepare("DELETE FROM horarios_clases WHERE id_horario = ? AND id_clase = ?");
        $stmt->execute([$id_horario, $id_clase]);
        
        header("Location: ../gestionar_aula.php?id_clase=$id_clase&exito=Horario eliminado.");
        exit();
    } catch (PDOException $e) {
        header("Location: ../gestionar_aula.php?id_clase=$id_clase&error=Error al eliminar.");
        exit();
    }
}
?>