<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'prefectos') {
    die("Acceso denegado.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $matricula = trim($_POST['matricula']);
    $id_tipo_falta = filter_input(INPUT_POST, 'id_tipo_falta', FILTER_VALIDATE_INT);
    $observaciones = trim($_POST['observaciones']);
    
    $dias_suspension = (int)($_POST['dias_suspension'] ?? 0);
    $es_baja_definitiva = isset($_POST['suspension_definitiva']) ? 1 : 0;
    
    $id_emisor = $_SESSION['id_usuario'];
    $tipo_emisor = 'Prefecto'; 

    try {
        $stmt_al = $pdo->prepare("SELECT id_alumno FROM alumnos WHERE matricula = :matricula LIMIT 1");
        $stmt_al->execute([':matricula' => $matricula]);
        $alumno = $stmt_al->fetch(PDO::FETCH_ASSOC);

        if (!$alumno) { 
            header("Location: ../creacion_reportes.php?error=alumno_no_encontrado");
            exit();
        }
        
        $id_alumno = $alumno['id_alumno'];

        // Insertar reporte (El trigger de BD descontará los puntos automáticamente)
        $query_insert = "INSERT INTO reportes (id_alumno, id_tipo_falta, id_emisor, tipo_emisor, puntos_descontados, observaciones) 
                         VALUES (:id_alumno, :id_tipo_falta, :id_emisor, :tipo_emisor, 0, :observaciones)";
        $stmt_insert = $pdo->prepare($query_insert);
        $stmt_insert->execute([
            ':id_alumno' => $id_alumno, ':id_tipo_falta' => $id_tipo_falta,
            ':id_emisor' => $id_emisor, ':tipo_emisor' => $tipo_emisor, ':observaciones' => $observaciones
        ]);

        // Procesar Sanción Extra
        $stmt_verificar = $pdo->prepare("SELECT tipo_sancion FROM tipos_falta WHERE id_tipo_falta = :id_tipo_falta LIMIT 1");
        $stmt_verificar->execute([':id_tipo_falta' => $id_tipo_falta]);
        $tipo_sancion = $stmt_verificar->fetchColumn();

        if ($tipo_sancion === 'Suspension') {
            if ($es_baja_definitiva === 1) {
                $stmtUpdate = $pdo->prepare("UPDATE alumnos SET estado_disciplinario = 'Baja', fecha_fin_suspension = NULL WHERE id_alumno = :id_alumno");
                $stmtUpdate->execute([':id_alumno' => $id_alumno]);
            } elseif ($dias_suspension > 0) {
                $fecha_retorno = date('Y-m-d', strtotime("+$dias_suspension days"));
                $stmtUpdate = $pdo->prepare("UPDATE alumnos SET estado_disciplinario = 'Suspendido', fecha_fin_suspension = :fecha_fin WHERE id_alumno = :id_alumno");
                $stmtUpdate->execute([':fecha_fin' => $fecha_retorno, ':id_alumno' => $id_alumno]);
            }
        }

        header("Location: ../creacion_reportes.php?msg=exito_reporte");
        exit();

    } catch (PDOException $e) {
        error_log("Error BD Reporte: " . $e->getMessage());
        header("Location: ../creacion_reportes.php?error=error_bd");
        exit();
    }
} else {
    header("Location: ../creacion_reportes.php");
    exit();
}
?>