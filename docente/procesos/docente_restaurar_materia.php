<?php
session_start();
require_once '../../config/database.php';

// Verificación de seguridad
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_clase = isset($_POST['id_clase']) ? (int)$_POST['id_clase'] : 0;
    $id_grupo = isset($_POST['id_grupo']) ? (int)$_POST['id_grupo'] : 0;
    $id_docente = $_SESSION['id_usuario'];

    if ($id_clase === 0) {
        header("Location: ../gestionar_aula.php?id_grupo=$id_grupo&error=No se especificó la materia a restaurar.");
        exit();
    }

    try {
        // 1. Verificamos que la clase pertenezca al maestro
        $stmt_check = $pdo->prepare("SELECT id_clase FROM clases_regulares WHERE id_clase = ? AND id_maestro = ?");
        $stmt_check->execute([$id_clase, $id_docente]);
        
        if ($stmt_check->rowCount() === 0) {
            header("Location: ../gestionar_aula.php?id_grupo=$id_grupo&error=No tienes permisos para restaurar esta clase.");
            exit();
        }

        // 2. Volvemos a activar la clase
        $stmt_update = $pdo->prepare("UPDATE clases_regulares SET estatus = 'Activa' WHERE id_clase = ?");
        $stmt_update->execute([$id_clase]);

        // 3. Volvemos a hacer visibles las actividades de esta clase
        $stmt_actividades = $pdo->prepare("UPDATE actividades_clase SET visible = 1 WHERE id_clase = ?");
        $stmt_actividades->execute([$id_clase]);

        // Redirigir con éxito
        header("Location: ../gestionar_aula.php?id_grupo=$id_grupo&exito=El aula virtual fue restaurada correctamente.");
        exit();

    } catch (PDOException $e) {
        header("Location: ../gestionar_aula.php?id_grupo=$id_grupo&error=Error al intentar restaurar: " . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: ../mis_grupos.php");
    exit();
}
?>