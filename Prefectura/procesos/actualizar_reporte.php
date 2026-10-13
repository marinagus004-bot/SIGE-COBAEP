<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'prefectos') {
    die("Acceso denegado");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_reporte = filter_input(INPUT_POST, 'id_reporte', FILTER_VALIDATE_INT);
    $id_alumno = filter_input(INPUT_POST, 'id_alumno', FILTER_VALIDATE_INT);
    $id_tipo_falta = filter_input(INPUT_POST, 'id_tipo_falta', FILTER_VALIDATE_INT);
    $observaciones = trim($_POST['observaciones']);
    $return_url = $_POST['return_url'] ?? 'creacion_reportes.php';

    try {
        $stmt_pts = $pdo->prepare("SELECT COALESCE(puntos_descuento, 0) FROM tipos_falta WHERE id_tipo_falta = ?");
        $stmt_pts->execute([$id_tipo_falta]);
        $nuevos_puntos_descuento = (int)$stmt_pts->fetchColumn();

        $stmt_update = $pdo->prepare("UPDATE reportes SET id_tipo_falta = ?, puntos_descontados = ?, observaciones = ? WHERE id_reporte = ?");
        $stmt_update->execute([$id_tipo_falta, $nuevos_puntos_descuento, $observaciones, $id_reporte]);

        $stmt_total = $pdo->prepare("SELECT COALESCE(SUM(puntos_descontados), 0) FROM reportes WHERE id_alumno = ?");
        $stmt_total->execute([$id_alumno]);
        $total_descontados = (int)$stmt_total->fetchColumn();

        $puntos_finales = 100 - $total_descontados;
        if ($puntos_finales < 0) $puntos_finales = 0;

        $stmt_verificar = $pdo->prepare("
            SELECT COUNT(*) FROM reportes r 
            INNER JOIN tipos_falta tf ON r.id_tipo_falta = tf.id_tipo_falta 
            WHERE r.id_alumno = ? AND tf.tipo_sancion = 'Suspension'
        ");
        $stmt_verificar->execute([$id_alumno]);
        $tiene_suspensiones = (int)$stmt_verificar->fetchColumn();

        if ($tiene_suspensiones === 0 && $puntos_finales > 0) {
            $stmt_al_update = $pdo->prepare("UPDATE alumnos SET puntos_conducta = ?, estado_disciplinario = 'Activo', fecha_fin_suspension = NULL WHERE id_alumno = ?");
            $stmt_al_update->execute([$puntos_finales, $id_alumno]);
        } else {
            $stmt_al_update = $pdo->prepare("UPDATE alumnos SET puntos_conducta = ? WHERE id_alumno = ?");
            $stmt_al_update->execute([$puntos_finales, $id_alumno]);
        }

        // Se agregó el ?msg= para el modal
        $separador = (strpos($return_url, '?') !== false) ? '&' : '?';
        header("Location: ../" . $return_url . $separador . "msg=exito_actualizar");
        exit();

    } catch (PDOException $e) {
        error_log("Error al actualizar reporte: " . $e->getMessage());
        header("Location: ../creacion_reportes.php?error=error_bd");
        exit();
    }
}
?>