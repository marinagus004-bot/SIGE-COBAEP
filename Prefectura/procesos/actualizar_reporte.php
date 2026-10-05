<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'prefectos') {
    die("Acceso denegado");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_reporte = $_POST['id_reporte'];
    $id_alumno = $_POST['id_alumno'];
    $id_tipo_falta = $_POST['id_tipo_falta'];
    $observaciones = trim($_POST['observaciones']);
    $return_url = $_POST['return_url'] ?? 'creacion_reportes.php';

    try {
        // 1. Obtener cuántos puntos resta la NUEVA regla elegida
        $stmt_pts = $pdo->prepare("SELECT COALESCE(puntos_descuento, 0) FROM tipos_falta WHERE id_tipo_falta = ?");
        $stmt_pts->execute([$id_tipo_falta]);
        $nuevos_puntos_descuento = (int)$stmt_pts->fetchColumn();

        // 2. Actualizar el reporte con los nuevos datos
        $stmt_update = $pdo->prepare("UPDATE reportes SET id_tipo_falta = ?, puntos_descontados = ?, observaciones = ? WHERE id_reporte = ?");
        $stmt_update->execute([$id_tipo_falta, $nuevos_puntos_descuento, $observaciones, $id_reporte]);

        // 3. Recalcular el total de puntos del alumno basándose en su historial actualizado
        $stmt_total = $pdo->prepare("SELECT COALESCE(SUM(puntos_descontados), 0) FROM reportes WHERE id_alumno = ?");
        $stmt_total->execute([$id_alumno]);
        $total_descontados = (int)$stmt_total->fetchColumn();

        $puntos_finales = 100 - $total_descontados;
        if ($puntos_finales < 0) $puntos_finales = 0;

        // 4. Verificar si el alumno tiene OTRAS faltas de suspensión activas
        $stmt_verificar = $pdo->prepare("
            SELECT COUNT(*) FROM reportes r 
            INNER JOIN tipos_falta tf ON r.id_tipo_falta = tf.id_tipo_falta 
            WHERE r.id_alumno = ? AND tf.tipo_sancion = 'Suspension'
        ");
        $stmt_verificar->execute([$id_alumno]);
        $tiene_suspensiones = (int)$stmt_verificar->fetchColumn();

        // 5. Actualizar los puntos y decidir si se le quita el castigo
        if ($tiene_suspensiones === 0 && $puntos_finales > 0) {
            // Ya no hay motivos de suspensión, regresa a la normalidad
            $stmt_al_update = $pdo->prepare("
                UPDATE alumnos 
                SET puntos_conducta = ?, 
                    estado_disciplinario = 'Activo', 
                    fecha_fin_suspension = NULL 
                WHERE id_alumno = ?
            ");
            $stmt_al_update->execute([$puntos_finales, $id_alumno]);
        } else {
            // Solo actualizamos sus puntos, pero se mantiene suspendido o dado de baja
            $stmt_al_update = $pdo->prepare("UPDATE alumnos SET puntos_conducta = ? WHERE id_alumno = ?");
            $stmt_al_update->execute([$puntos_finales, $id_alumno]);
        }

        // Redirigir de vuelta a la página original (saliendo de la carpeta procesos con ../)
        header("Location: ../" . $return_url);
        exit();

    } catch (PDOException $e) {
        die("Error al actualizar el reporte: " . $e->getMessage());
    }
}
?>