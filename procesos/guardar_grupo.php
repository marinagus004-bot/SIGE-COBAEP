<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $_SESSION['rol'] === 'administrativo') {
    $semestre = $_POST['semestre'];
    $nombre_grupo = strtoupper(trim($_POST['nombre_grupo']));
    $turno = $_POST['turno'];

    try {
        $stmt = $pdo->prepare("INSERT INTO GRUPOS (semestre, nombre_grupo, turno) VALUES (?, ?, ?)");
        $stmt->execute([$semestre, $nombre_grupo, $turno]);
        header("Location: ../admin/gestion_grupos.php");
    } catch (PDOException $e) {
        die("Error al crear grupo: " . $e->getMessage());
    }
}
?>