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
    $nombre_materia = isset($_POST['nombre_materia']) ? trim($_POST['nombre_materia']) : '';
    $id_docente = $_SESSION['id_usuario'];

    if ($id_clase === 0 || empty($nombre_materia)) {
        header("Location: ../gestionar_aula.php?id_grupo=$id_grupo&error=Faltan datos para editar la materia.");
        exit();
    }

    try {
        // Obtenemos el id_materia asociado a esta clase para poder cambiarle el nombre
        $stmt_get = $pdo->prepare("SELECT id_materia FROM clases_regulares WHERE id_clase = ? AND id_maestro = ?");
        $stmt_get->execute([$id_clase, $id_docente]);
        $clase = $stmt_get->fetch(PDO::FETCH_ASSOC);

        if (!$clase) {
            header("Location: ../gestionar_aula.php?id_grupo=$id_grupo&error=No tienes permiso para editar esta materia.");
            exit();
        }

        // Actualizamos el nombre en la tabla 'materias'
        $stmt_update = $pdo->prepare("UPDATE materias SET nombre_materia = ? WHERE id_materia = ?");
        $stmt_update->execute([$nombre_materia, $clase['id_materia']]);

        header("Location: ../gestionar_aula.php?id_grupo=$id_grupo&exito=El nombre de la materia se actualizó correctamente.");
        exit();

    } catch (PDOException $e) {
        header("Location: ../gestionar_aula.php?id_grupo=$id_grupo&error=Error en la base de datos: " . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: ../mis_grupos.php");
    exit();
}
?>