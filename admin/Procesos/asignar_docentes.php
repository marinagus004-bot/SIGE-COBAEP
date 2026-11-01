<?php
session_start();
// Subimos 2 niveles para llegar a la carpeta config en la raíz
require_once '../../config/database.php';

// Verificación de sesión
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_grupo = isset($_POST['id_grupo']) ? (int)$_POST['id_grupo'] : 0;
    $maestros = isset($_POST['maestros_seleccionados']) ? $_POST['maestros_seleccionados'] : [];

    if ($id_grupo > 0 && count($maestros) > 0) {
        try {
            $pdo->beginTransaction();

            // Usamos INSERT IGNORE por si el docente ya estaba asignado a ese grupo, no genere error duplicado
            $sql = "INSERT IGNORE INTO GRUPO_MAESTRO (id_grupo, id_maestro, fecha_asignacion) VALUES (?, ?, CURDATE())";
            $stmt = $pdo->prepare($sql);

            foreach ($maestros as $id_maestro) {
                $stmt->execute([$id_grupo, (int)$id_maestro]);
            }

            $pdo->commit();
            
            // Redirige de vuelta con mensaje de éxito
            header("Location: ../asignacion_masiva.php?msg=exito");
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            die("Error en la base de datos: " . $e->getMessage());
        }
    } else {
        // Redirige si intentaron enviar el form vacío
        header("Location: ../asignacion_masiva.php?msg=vacio");
        exit();
    }
} else {
    // Si intentan entrar directo a la URL, los regresa
    header("Location: ../asignacion_masiva.php");
    exit();
}
?>