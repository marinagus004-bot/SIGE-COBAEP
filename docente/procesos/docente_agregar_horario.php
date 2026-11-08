<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_clase = (int)$_POST['id_clase'];
    $dia_semana = (int)$_POST['dia_semana'];
    $hora_inicio = $_POST['hora_inicio'];
    $hora_fin = $_POST['hora_fin'];

    try {
        $stmt = $pdo->prepare("INSERT INTO horarios_clases (id_clase, dia_semana, hora_inicio, hora_fin) VALUES (?, ?, ?, ?)");
        $stmt->execute([$id_clase, $dia_semana, $hora_inicio, $hora_fin]);
        
        header("Location: ../gestionar_aula.php?id_clase=$id_clase&exito=Horario agregado correctamente.");
        exit();
    } catch (PDOException $e) {
        header("Location: ../gestionar_aula.php?id_clase=$id_clase&error=Error al guardar el horario.");
        exit();
    }
}
?>