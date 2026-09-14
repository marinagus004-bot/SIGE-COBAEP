<?php
session_start();
require_once '../config/database.php';

// Validar que sea alumno
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'alumno') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_clase = (int)$_POST['id_clase'];
    $id_alumno = $_SESSION['id_usuario'];
    
    if (empty($id_clase)) {
        header("Location: ../estudiante/actividades.php?error=Datos inválidos.");
        exit();
    }

    $ruta_archivo = null;

    if (isset($_FILES['archivo_trabajo']) && $_FILES['archivo_trabajo']['error'] === UPLOAD_ERR_OK) {
        $directorio_destino = '../uploads/entregas/';
        
        // Crear carpeta si no existe
        if (!is_dir($directorio_destino)) {
            mkdir($directorio_destino, 0777, true);
        }

        $archivo_tmp = $_FILES['archivo_trabajo']['tmp_name'];
        $nombre_original = str_replace(' ', '_', $_FILES['archivo_trabajo']['name']);
        $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
        
        // Solo aceptamos documentos para las entregas
        $permitidas = ['pdf', 'doc', 'docx', 'zip', 'rar'];

        if (in_array($extension, $permitidas)) {
            $nombre_nuevo = uniqid('entrega_') . '_' . $id_alumno . '_' . $nombre_original;
            $ruta_final = $directorio_destino . $nombre_nuevo;

            if (move_uploaded_file($archivo_tmp, $ruta_final)) {
                $ruta_archivo = 'uploads/entregas/' . $nombre_nuevo; 
            }
        } else {
            header("Location: ../estudiante/espacio_regularizacion.php?id=$id_clase&error=Solo se permiten archivos PDF, Word o ZIP.");
            exit();
        }
    } else {
        header("Location: ../estudiante/espacio_regularizacion.php?id=$id_clase&error=No se adjuntó ningún archivo válido.");
        exit();
    }

    // Insertar la entrega en la base de datos
    try {
        $stmt = $pdo->prepare("INSERT INTO ENTREGAS_REGULARIZACION (id_clase, id_alumno, archivo_ruta) VALUES (?, ?, ?)");
        $stmt->execute([$id_clase, $id_alumno, $ruta_archivo]);
        
        header("Location: ../estudiante/espacio_regularizacion.php?id=$id_clase&exito=Trabajo entregado correctamente.");
        exit();
    } catch (PDOException $e) {
        error_log("Error al subir entrega: " . $e->getMessage());
        header("Location: ../estudiante/espacio_regularizacion.php?id=$id_clase&error=Hubo un error al guardar tu trabajo.");
        exit();
    }
} else {
    header("Location: ../estudiante/actividades.php");
    exit();
}