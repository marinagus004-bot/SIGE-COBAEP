<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'prefectos') {
    die("Acceso denegado.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_reporte = filter_input(INPUT_POST, 'id_reporte', FILTER_VALIDATE_INT);
    $password_ingresada = trim($_POST['password_confirm']);
    $id_prefecto = $_SESSION['id_usuario']; 
    $return_url = $_POST['return_url'] ?? 'creacion_reportes.php';

    try {
        $stmt = $pdo->prepare("SELECT contrasena_hash FROM prefectos WHERE id_prefecto = :id_prefecto LIMIT 1");
        $stmt->execute([':id_prefecto' => $id_prefecto]);
        $prefecto = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($prefecto && password_verify($password_ingresada, $prefecto['contrasena_hash'])) {
            
            $stmt_info = $pdo->prepare("SELECT id_alumno, puntos_descontados FROM reportes WHERE id_reporte = :id_reporte LIMIT 1");
            $stmt_info->execute([':id_reporte' => $id_reporte]);
            $reporte = $stmt_info->fetch(PDO::FETCH_ASSOC);

            if ($reporte) {
                $id_alumno = $reporte['id_alumno'];
                $puntos_a_devolver = (int)$reporte['puntos_descontados'];

                $pdo->beginTransaction();

                // 1. Borrar el reporte PRIMERO
                $stmt_delete = $pdo->prepare("DELETE FROM reportes WHERE id_reporte = :id_reporte");
                $stmt_delete->execute([':id_reporte' => $id_reporte]);

                // 2. Verificar si al alumno le quedan MÁS suspensiones activas
                $stmt_verificar = $pdo->prepare("
                    SELECT COUNT(*) FROM reportes r 
                    INNER JOIN tipos_falta tf ON r.id_tipo_falta = tf.id_tipo_falta 
                    WHERE r.id_alumno = :id_alumno AND tf.tipo_sancion = 'Suspension'
                ");
                $stmt_verificar->execute([':id_alumno' => $id_alumno]);
                $tiene_mas_suspensiones = (int)$stmt_verificar->fetchColumn();

                // 3. Devolver puntos y decidir si se quita la suspensión
                if ($tiene_mas_suspensiones === 0) {
                    // Ya no le quedan faltas graves, se le quita la suspensión
                    $stmt_update = $pdo->prepare("
                        UPDATE alumnos 
                        SET puntos_conducta = IF(puntos_conducta + :puntos > 100, 100, puntos_conducta + :puntos),
                            estado_disciplinario = 'Activo',
                            fecha_fin_suspension = NULL
                        WHERE id_alumno = :id_alumno
                    ");
                } else {
                    // Sigue suspendido por otras faltas, solo le devolvemos los puntos de esta
                    $stmt_update = $pdo->prepare("
                        UPDATE alumnos 
                        SET puntos_conducta = IF(puntos_conducta + :puntos > 100, 100, puntos_conducta + :puntos)
                        WHERE id_alumno = :id_alumno
                    ");
                }
                
                $stmt_update->execute([':puntos' => $puntos_a_devolver, ':id_alumno' => $id_alumno]);
                $pdo->commit();
            }
            
            $separador = (strpos($return_url, '?') !== false) ? '&' : '?';
            header("Location: ../" . $return_url . $separador . "msg=exito_eliminar");
            exit();
            
        } else {
            // Contraseña incorrecta
            $separador = (strpos($return_url, '?') !== false) ? '&' : '?';
            header("Location: ../" . $return_url . $separador . "error=password_incorrecta");
            exit();
        }
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
        error_log("Error BD Eliminar: " . $e->getMessage());
        header("Location: ../creacion_reportes.php?error=error_bd");
        exit();
    }
}
?>