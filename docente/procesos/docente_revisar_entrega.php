<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_entrega = (int)$_POST['id_entrega'];
    $id_actividad = (int)$_POST['id_actividad'];
    $id_clase = (int)$_POST['id_clase'];
    $estatus_entrega = $_POST['estatus_entrega'] === 'Revisada' ? 'Revisada' : 'Pendiente';
    $retroalimentacion = trim($_POST['retroalimentacion']);

    try {
        $stmt = $pdo->prepare("UPDATE entregas_actividad SET estatus_entrega = ?, retroalimentacion = ? WHERE id_entrega = ?");
        $stmt->execute([$estatus_entrega, $retroalimentacion, $id_entrega]);
        
        header("Location: ../ver_entregas.php?id_actividad=$id_actividad&id_clase=$id_clase&exito=Evaluación guardada exitosamente.");
    } catch (PDOException $e) {
        header("Location: ../ver_entregas.php?id_actividad=$id_actividad&id_clase=$id_clase&error=Error al guardar la evaluación.");
    }
}
?>