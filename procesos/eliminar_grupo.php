<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo' || !isset($_GET['id'])) {
    header("Location: ../admin/gestion_grupos.php");
    exit();
}

$id_grupo = $_GET['id'];

try {
    // 1. Primero, quitamos a los alumnos de este grupo (ponemos fecha de baja en la tabla puente)
    $stmt1 = $pdo->prepare("UPDATE GRUPO_ALUMNO SET fecha_baja = CURRENT_DATE() WHERE id_grupo = ? AND fecha_baja IS NULL");
    $stmt1->execute([$id_grupo]);

    // 2. Ahora sí, borramos el grupo de la tabla principal
    $stmt2 = $pdo->prepare("DELETE FROM GRUPOS WHERE id_grupo = ?");
    $stmt2->execute([$id_grupo]);

    header("Location: ../admin/gestion_grupos.php?msg=eliminado");
} catch (PDOException $e) {
    die("Error al eliminar. Detalle: " . $e->getMessage());
}
?>