<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matricula = trim($_POST['matricula'] ?? '');
    $id_grupo_nuevo = trim($_POST['id_grupo'] ?? '');

    if (empty($matricula) || empty($id_grupo_nuevo)) {
        header("Location: ../admin/ver_grupo.php?id=$id_grupo_nuevo&msg=campos_vacios");
        exit();
    }

    try {
        // 1. Buscar el id del alumno
        $stmt_alumno = $pdo->prepare("SELECT id_alumno FROM ALUMNOS WHERE matricula = ? AND estatus = 'Alta'");
        $stmt_alumno->execute([$matricula]);
        $alumno = $stmt_alumno->fetch(PDO::FETCH_ASSOC);

        if ($alumno) {
            $id_alumno = $alumno['id_alumno'];

            // 2. Dar de baja al alumno de cualquier grupo activo que tuviera previamente
            // Esto permite que el alumno cambie de grupo limpiamente
            $stmt_baja = $pdo->prepare("UPDATE GRUPO_ALUMNO SET fecha_baja = CURDATE() WHERE id_alumno = ? AND fecha_baja IS NULL");
            $stmt_baja->execute([$id_alumno]);

            // 3. Inscribir al alumno en el nuevo grupo
            $stmt_inscripcion = $pdo->prepare("INSERT INTO GRUPO_ALUMNO (id_grupo, id_alumno, fecha_asignacion) VALUES (?, ?, CURDATE())");
            $stmt_inscripcion->execute([$id_grupo_nuevo, $id_alumno]);

            header("Location: ../admin/ver_grupo.php?id=$id_grupo_nuevo&msg=alumno_inscrito");
            exit();
        } else {
            header("Location: ../admin/ver_grupo.php?id=$id_grupo_nuevo&msg=alumno_no_encontrado");
            exit();
        }
    } catch (PDOException $e) {
        die("Error de Base de Datos: " . $e->getMessage());
    }
}
?>