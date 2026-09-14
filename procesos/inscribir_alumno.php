<?php
session_start();
require_once '../config/database.php';

// Validar que sea docente
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_clase = (int)$_POST['id_clase'];
    $id_alumno = (int)$_POST['id_alumno'];
    $id_asignador = $_SESSION['id_usuario']; // El ID del maestro
    
    if (empty($id_clase) || empty($id_alumno)) {
        header("Location: ../docente/detalle_regularizacion.php?id=$id_clase&error=Debes seleccionar un alumno.");
        exit();
    }

    try {
        // Insertar en la tabla CLASE_ALUMNO
        $stmt = $pdo->prepare("
            INSERT INTO CLASE_ALUMNO (id_clase, id_alumno, id_asignador, tipo_asignador, fecha_asignacion) 
            VALUES (?, ?, ?, 'maestro', CURDATE())
        ");
        $stmt->execute([$id_clase, $id_alumno, $id_asignador]);
        
        header("Location: ../docente/detalle_regularizacion.php?id=$id_clase&exito=Alumno inscrito correctamente al espacio.");
        exit();
    } catch (PDOException $e) {
        // Código 23000 es para entradas duplicadas (si el alumno ya estaba inscrito)
        if ($e->getCode() == 23000) {
            header("Location: ../docente/detalle_regularizacion.php?id=$id_clase&error=El alumno ya está inscrito en esta clase.");
        } else {
            error_log("Error al inscribir alumno: " . $e->getMessage());
            header("Location: ../docente/detalle_regularizacion.php?id=$id_clase&error=Hubo un error al inscribir al alumno.");
        }
        exit();
    }
} else {
    header("Location: ../docente/regularizaciones.php");
    exit();
}