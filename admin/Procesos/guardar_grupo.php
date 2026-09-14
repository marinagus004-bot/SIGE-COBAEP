<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recibir datos del formulario
    $semestre = trim($_POST['semestre'] ?? '');
    $nombre_grupo = trim($_POST['nombre_grupo'] ?? '');
    $turno = trim($_POST['turno'] ?? '');
    $id_admin = $_SESSION['id_usuario'];

    if (empty($semestre) || empty($nombre_grupo) || empty($turno)) {
        header("Location: ../admin/agregar_grupo.php?msg=campos_vacios");
        exit();
    }

    try {
        // Verificar si el grupo ya existe para evitar duplicados
        $stmt_check = $pdo->prepare("SELECT id_grupo FROM GRUPOS WHERE semestre = ? AND nombre_grupo = ? AND turno = ?");
        $stmt_check->execute([$semestre, $nombre_grupo, $turno]);
        
        if ($stmt_check->rowCount() > 0) {
            header("Location: ../admin/agregar_grupo.php?msg=grupo_duplicado");
            exit();
        }

        // Insertar el nuevo grupo
        $sql = "INSERT INTO GRUPOS (semestre, nombre_grupo, turno, id_admin_responsable) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$semestre, $nombre_grupo, $turno, $id_admin]);

        header("Location: ../admin/gestion_grupos.php?msg=grupo_creado");
        exit();

    } catch (PDOException $e) {
        die("Error al registrar el grupo: " . $e->getMessage());
    }
} else {
    header("Location: ../admin/gestion_grupos.php");
    exit();
}
?>