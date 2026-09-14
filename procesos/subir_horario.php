<?php
session_start();
require_once '../config/database.php';

// Validar que sea docente
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_docente = $_SESSION['id_usuario'];
    
    if (isset($_FILES['archivo_horario']) && $_FILES['archivo_horario']['error'] === UPLOAD_ERR_OK) {
        $directorio_destino = '../uploads/horarios/';
        
        // Crear carpeta si no existe
        if (!is_dir($directorio_destino)) {
            mkdir($directorio_destino, 0777, true);
        }

        $archivo_tmp = $_FILES['archivo_horario']['tmp_name'];
        $nombre_original = str_replace(' ', '_', $_FILES['archivo_horario']['name']);
        $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
        
        // Permitimos PDFs e Imágenes
        $permitidas = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

        if (in_array($extension, $permitidas)) {
            // Creamos un nombre único con el ID del maestro para que siempre se reemplace o identifique fácil
            $nombre_nuevo = 'horario_docente_' . $id_docente . '_' . time() . '.' . $extension;
            $ruta_final = $directorio_destino . $nombre_nuevo;

            if (move_uploaded_file($archivo_tmp, $ruta_final)) {
                $ruta_archivo = 'uploads/horarios/' . $nombre_nuevo; 
                
                try {
                    // Actualizamos el registro del maestro
                    $stmt = $pdo->prepare("UPDATE MAESTROS SET horario_archivo = ? WHERE id_maestro = ?");
                    $stmt->execute([$ruta_archivo, $id_docente]);
                    
                    header("Location: ../docente/mi_horario.php?exito=Horario guardado correctamente.");
                    exit();
                } catch (PDOException $e) {
                    error_log("Error guardando horario: " . $e->getMessage());
                    header("Location: ../docente/mi_horario.php?error=Error al registrar en la base de datos.");
                    exit();
                }
            }
        } else {
            header("Location: ../docente/mi_horario.php?error=Solo se permiten archivos PDF o Imágenes.");
            exit();
        }
    } else {
        header("Location: ../docente/mi_horario.php?error=Por favor, selecciona un archivo válido.");
        exit();
    }
} else {
    header("Location: ../docente/mi_horario.php");
    exit();
}