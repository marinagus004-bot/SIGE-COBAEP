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

        // 2. Insertar el reporte
        // Le mandamos un "0" en puntos_descontados porque tu TRIGGER se encargará de sobreescribirlo 
        // y hacer todo el cálculo y actualización de la tabla alumnos.
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

        // Si el trigger hizo su trabajo sin errores, redirigimos con éxito
        header("Location: ../creacion_reportes.php?status=success");
        exit();

    } catch (PDOException $e) {
        // AQUÍ ESTÁ LA SOLUCIÓN AL MISTERIO:
        // Si el Trigger falla (por falta de tabla, error matemático, etc.), 
        // lo atrapamos y te lo mostramos en pantalla para que sepas qué arreglar en MySQL.
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