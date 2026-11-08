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

    // ==========================================
    // NUEVOS CAMPOS DE HORARIO (DÍAS Y HORAS)
    // ==========================================
    $dias_array = $_POST['dias'] ?? []; // Array con los números de los días (1=Lun, 5=Vie)
    $hora_inicio = !empty($_POST['hora_inicio']) ? $_POST['hora_inicio'] : null;
    $hora_fin = !empty($_POST['hora_fin']) ? $_POST['hora_fin'] : null;

    // Convertir el array de días a un string separado por comas (ej. "1,3,5")
    $dias_str = !empty($dias_array) ? implode(',', $dias_array) : null;
    // ==========================================

    if ($id_grupo === 0 || empty($nombre_materia)) {
        header("Location: ../mis_grupos.php?error=Datos incompletos para activar el aula.");
        exit();
    }

    try {
        $pdo->beginTransaction();

        $stmt_materia = $pdo->prepare("INSERT INTO materias (nombre_materia) VALUES (?)");
        $stmt_materia->execute([$nombre_materia]);
        $id_materia = $pdo->lastInsertId();

        // AQUÍ AGREGAMOS LOS CAMPOS DE TIEMPO AL INSERTAR LA CLASE
        $stmt_clase = $pdo->prepare("INSERT INTO clases_regulares (id_materia, id_maestro, estatus, id_grupo, dias_clase, hora_inicio, hora_fin) VALUES (?, ?, 'Activa', ?, ?, ?, ?)");
        $stmt_clase->execute([$id_materia, $id_maestro, $id_grupo, $dias_str, $hora_inicio, $hora_fin]);
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
        // Redirigir con éxito
        header("Location: ../gestionar_aula.php?id_clase=" . $id_clase . "&exito=Aula virtual y horario guardados exitosamente.");
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_real = $e->getMessage();
        header("Location: ../gestionar_aula.php?id_grupo=" . $id_grupo . "&error=" . urlencode("Error BD: " . $error_real));
        exit();
    }
}
?>