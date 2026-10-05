<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'prefectos') {
    die("Acceso denegado.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id_reporte = intval($_POST['id_reporte']);
    $password_ingresada = trim($_POST['password_confirm']);
    $id_prefecto = $_SESSION['id_usuario']; 
    
    // 1. Atrapamos la URL de retorno (para saber a qué pantalla regresar)
    $return_url = $_POST['return_url'] ?? 'creacion_reportes.php';

    try {
        $stmt = $pdo->prepare("SELECT contrasena_hash FROM prefectos WHERE id_prefecto = :id_prefecto LIMIT 1");
        $stmt->execute([':id_prefecto' => $id_prefecto]);
        $prefecto = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($prefecto && password_verify($password_ingresada, $prefecto['contrasena_hash'])) {
            
            // Obtener datos del reporte antes de borrarlo
            $stmt_info = $pdo->prepare("SELECT id_alumno, puntos_descontados FROM reportes WHERE id_reporte = :id_reporte LIMIT 1");
            $stmt_info->execute([':id_reporte' => $id_reporte]);
            $reporte = $stmt_info->fetch(PDO::FETCH_ASSOC);

            if ($reporte) {
                $id_alumno = $reporte['id_alumno'];
                $puntos_a_devolver = $reporte['puntos_descontados'] ? (int)$reporte['puntos_descontados'] : 0;

                // Iniciamos transacción para que ambas consultas se ejecuten juntas de forma segura
                $pdo->beginTransaction();

                // 2. Devolver puntos (máximo 100) y QUITAR la suspensión del alumno (volver a la normalidad)
                $stmt_update = $pdo->prepare("
                    UPDATE alumnos 
                    SET puntos_conducta = IF(puntos_conducta + :puntos > 100, 100, puntos_conducta + :puntos),
                        estado_disciplinario = 'Activo',
                        fecha_fin_suspension = NULL
                    WHERE id_alumno = :id_alumno
                ");
                $stmt_update->execute([':puntos' => $puntos_a_devolver, ':id_alumno' => $id_alumno]);

                // 3. Borrar el reporte
                $stmt_delete = $pdo->prepare("DELETE FROM reportes WHERE id_reporte = :id_reporte");
                $stmt_delete->execute([':id_reporte' => $id_reporte]);

                $pdo->commit();
            }
            
            // Redirigir a la URL correcta (añadiendo la alerta de éxito)
            $separador = (strpos($return_url, '?') !== false) ? '&' : '?';
            header("Location: ../" . $return_url . $separador . "status=deleted");
            exit();
            
        } else {
            // Si la contraseña falla, regresamos a la pantalla anterior sin borrar nada
            echo "<script>alert('Error: Contraseña incorrecta.'); window.history.back();</script>";
            exit();
        }
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
        die("Error en la base de datos: " . $e->getMessage());
    }
} else {
    header("Location: ../creacion_reportes.php");
    exit();
}
?>