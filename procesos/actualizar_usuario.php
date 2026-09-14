<?php
// procesos/actualizar_usuario.php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_usuario = $_POST['id_usuario'];
    $matricula = trim($_POST['matricula']);
    $nombre_completo = trim($_POST['nombre_completo']);
    $grado = $_POST['grado'];
    $grupo = $_POST['grupo'];

    try {
        $sql = "UPDATE usuarios 
                SET matricula = :matricula, nombre_completo = :nombre, grado = :grado, grupo = :grupo 
                WHERE id_usuario = :id_usuario AND rol = 'estudiante'";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':matricula' => $matricula,
            ':nombre' => $nombre_completo,
            ':grado' => $grado,
            ':grupo' => $grupo,
            ':id_usuario' => $id_usuario
        ]);

        header("Location: ../admin/gestion_usuarios.php?msg=actualizado");
        exit();

    } catch (PDOException $e) {
        die("Error al actualizar: " . $e->getMessage());
    }
} else {
    header("Location: ../admin/gestion_usuarios.php");
    exit();
}
?>