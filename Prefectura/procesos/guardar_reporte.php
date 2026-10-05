<?php
session_start();
require_once '../../config/database.php';

// Validar que un prefecto inició sesión
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'prefectos') {
    die("Acceso denegado.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $matricula = trim($_POST['matricula']);
    $id_tipo_falta = intval($_POST['id_tipo_falta']);
    $observaciones = trim($_POST['observaciones']);
    
    // Capturar datos de suspensión (si los hay)
    $dias_suspension = (int)($_POST['dias_suspension'] ?? 0);
    $es_baja_definitiva = isset($_POST['suspension_definitiva']) ? 1 : 0;
    
    $id_emisor = $_SESSION['id_usuario'];
    $tipo_emisor = 'Prefecto'; 

    try {
        // 1. Buscar ID del alumno
        $stmt_al = $pdo->prepare("SELECT id_alumno FROM alumnos WHERE matricula = :matricula LIMIT 1");
        $stmt_al->execute([':matricula' => $matricula]);
        $alumno = $stmt_al->fetch(PDO::FETCH_ASSOC);

        if (!$alumno) { 
            echo "<script>alert('Error: Alumno no encontrado.'); window.location.href = '../creacion_reportes.php';</script>";
            exit();
        }
        
        $id_alumno = $alumno['id_alumno'];

        // 2. Insertar el reporte (El trigger ajustará los puntos)
        $query_insert = "INSERT INTO reportes (id_alumno, id_tipo_falta, id_emisor, tipo_emisor, puntos_descontados, observaciones) 
                         VALUES (:id_alumno, :id_tipo_falta, :id_emisor, :tipo_emisor, 0, :observaciones)";
        
        $stmt_insert = $pdo->prepare($query_insert);
        $stmt_insert->execute([
            ':id_alumno' => $id_alumno,
            ':id_tipo_falta' => $id_tipo_falta,
            ':id_emisor' => $id_emisor,
            ':tipo_emisor' => $tipo_emisor,
            ':observaciones' => $observaciones
        ]);

        // 3. Procesar Sanción Extra (Suspensión o Baja)
        $stmt_verificar = $pdo->prepare("SELECT tipo_sancion FROM tipos_falta WHERE id_tipo_falta = :id_tipo_falta LIMIT 1");
        $stmt_verificar->execute([':id_tipo_falta' => $id_tipo_falta]);
        $tipo_sancion = $stmt_verificar->fetchColumn();

        if ($tipo_sancion === 'Suspension') {
            if ($es_baja_definitiva === 1) {
                // Aplicar Baja Definitiva
                $stmtUpdate = $pdo->prepare("UPDATE alumnos SET estado_disciplinario = 'Baja', fecha_fin_suspension = NULL WHERE id_alumno = :id_alumno");
                $stmtUpdate->execute([':id_alumno' => $id_alumno]);
            } elseif ($dias_suspension > 0) {
                // Aplicar Suspensión Temporal (Sumamos los días a la fecha actual)
                $fecha_retorno = date('Y-m-d', strtotime("+$dias_suspension days"));
                $stmtUpdate = $pdo->prepare("UPDATE alumnos SET estado_disciplinario = 'Suspendido', fecha_fin_suspension = :fecha_fin WHERE id_alumno = :id_alumno");
                $stmtUpdate->execute([
                    ':fecha_fin' => $fecha_retorno, 
                    ':id_alumno' => $id_alumno
                ]);
            }
        }

        // 4. Redirigir con éxito
        header("Location: ../creacion_reportes.php?status=success");
        exit();

    } catch (PDOException $e) {
        // Atrapar errores de la BD o del Trigger
        $error_msg = addslashes($e->getMessage());
        
        echo "<script>
                alert('La base de datos bloqueó el reporte (Posible fallo en los Triggers):\\n\\n" . $error_msg . "');
                window.location.href = '../creacion_reportes.php';
              </script>";
        exit();
    }
} else {
    header("Location: ../creacion_reportes.php");
    exit();
}
?>