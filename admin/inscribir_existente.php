<?php
session_start();
require_once '../config/database.php';

// Verificamos que sea un administrador el que hace la acción
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../login.php");
    exit();
}

// Verificamos que los datos vengan por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Obtenemos los IDs y nos aseguramos de que sean números enteros
    $id_grupo = isset($_POST['id_grupo']) ? intval($_POST['id_grupo']) : 0;
    $id_alumno = isset($_POST['id_alumno']) ? intval($_POST['id_alumno']) : 0;

    if ($id_grupo > 0 && $id_alumno > 0) {
        try {
            // Preparamos la inserción en la tabla puente GRUPO_ALUMNO
            $sql = "INSERT INTO GRUPO_ALUMNO (id_grupo, id_alumno, fecha_asignacion) VALUES (?, ?, CURDATE())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id_grupo, $id_alumno]);

            // Redirigimos de vuelta al detalle del grupo con un mensaje de éxito
            // NOTA: Asegúrate de que el nombre del archivo abajo (ver_grupo.php) sea el correcto
            header("Location: ../admin/ver_grupo.php?id=" . $id_grupo . "&msg=inscrito");
            exit();

        } catch (PDOException $e) {
            // Si hay un error (ej. si el alumno ya estaba asignado y choca con la llave única)
            header("Location: ../admin/ver_grupo.php?id=" . $id_grupo . "&msg=error");
            exit();
        }
    } else {
        // Si por alguna razón los IDs llegaron vacíos o inválidos
        header("Location: ../admin/gestion_grupos.php");
        exit();
    }
} else {
    // Si alguien intenta entrar a este archivo directamente por la URL
    header("Location: ../admin/gestion_grupos.php");
    exit();
}
?>