<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_grupo = intval($_POST['id_grupo']);
    $alumnos = $_POST['alumnos_seleccionados'] ?? [];

    if ($id_grupo > 0 && !empty($alumnos)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO GRUPO_ALUMNO (id_grupo, id_alumno, fecha_asignacion) VALUES (?, ?, CURDATE())");
            
            foreach ($alumnos as $id_alumno) {
                try {
                    // Inscribir individualmente
                    $stmt->execute([$id_grupo, intval($id_alumno)]);
                } catch (PDOException $e) {
                    // Si un alumno ya estaba inscrito u otro error menor, lo ignoramos y continuamos con los demás
                    continue;
                }
            }
            
            $pdo->commit();
            header("Location: ../admin/ver_grupo.php?id=" . $id_grupo . "&msg=inscritos_multiples");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            header("Location: ../admin/ver_grupo.php?id=" . $id_grupo . "&error=Error al inscribir alumnos");
            exit();
        }
    } else {
        // Redirigir si no seleccionaron nada pero dieron clic a guardar
        header("Location: ../admin/ver_grupo.php?id=" . $id_grupo);
        exit();
    }
}
header("Location: ../admin/gestion_grupos.php");
exit();
?>