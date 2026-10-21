<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_maestro = $_SESSION['id_usuario'];
    $id_grupo = isset($_POST['id_grupo']) ? (int)$_POST['id_grupo'] : 0;
    $nombre_materia = isset($_POST['nombre_materia']) ? trim($_POST['nombre_materia']) : '';

    if ($id_grupo === 0 || empty($nombre_materia)) {
        header("Location: ../mis_grupos.php?error=Datos incompletos para activar el aula.");
        exit();
    }

    try {
        $pdo->beginTransaction();

        $stmt_materia = $pdo->prepare("INSERT INTO materias (nombre_materia) VALUES (?)");
        $stmt_materia->execute([$nombre_materia]);
        $id_materia = $pdo->lastInsertId();

        $stmt_clase = $pdo->prepare("INSERT INTO clases_regulares (id_materia, id_maestro, estatus, id_grupo) VALUES (?, ?, 'Activa', ?)");
        $stmt_clase->execute([$id_materia, $id_maestro, $id_grupo]);
        $id_clase = $pdo->lastInsertId();

        $stmt_alumnos = $pdo->prepare("SELECT id_alumno FROM grupo_alumno WHERE id_grupo = ? AND fecha_baja IS NULL");
        $stmt_alumnos->execute([$id_grupo]);
        $alumnos_grupo = $stmt_alumnos->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($alumnos_grupo)) {
            $stmt_inscripcion = $pdo->prepare("INSERT INTO clase_regular_alumno (id_clase, id_alumno, acceso) VALUES (?, ?, 1)");
            foreach ($alumnos_grupo as $alumno) {
                $stmt_inscripcion->execute([$id_clase, $alumno['id_alumno']]);
            }
        }

        $pdo->commit();
        header("Location: ../gestionar_aula.php?id_clase=" . $id_clase . "&exito=Aula virtual activada exitosamente.");
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        // ESTO TE MOSTRARÁ EL ERROR REAL DE LA BASE DE DATOS
        $error_real = $e->getMessage();
        header("Location: ../gestionar_aula.php?id_grupo=" . $id_grupo . "&error=" . urlencode("Error BD: " . $error_real));
        exit();
    }
}
?>