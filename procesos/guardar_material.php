<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_clase = (int)$_POST['id_clase'];
    $id_bloque = !empty($_POST['id_bloque']) ? (int)$_POST['id_bloque'] : null; // NUEVO: Recibe el bloque
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    
    if (empty($titulo) || empty($id_clase)) {
        header("Location: ../docente/detalle_regularizacion.php?id=$id_clase&error=El título es obligatorio.");
        exit();
    }

    $ruta_archivo = null;

    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $directorio_destino = '../uploads/materiales/';
        if (!is_dir($directorio_destino)) { mkdir($directorio_destino, 0777, true); }

        $archivo_tmp = $_FILES['archivo']['tmp_name'];
        $nombre_original = str_replace(' ', '_', $_FILES['archivo']['name']);
        $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
        
        $prohibidas = ['exe', 'php', 'sh', 'js', 'bat'];

        if (!in_array($extension, $prohibidas)) {
            $nombre_nuevo = uniqid('mat_') . '_' . $nombre_original;
            $ruta_final = $directorio_destino . $nombre_nuevo;

            if (move_uploaded_file($archivo_tmp, $ruta_final)) {
                $ruta_archivo = 'uploads/materiales/' . $nombre_nuevo; 
            }
        } else {
            header("Location: ../docente/detalle_regularizacion.php?id=$id_clase&error=Este tipo de archivo no está permitido.");
            exit();
        }
    }

    try {
        // NUEVO: Agregamos id_bloque en el INSERT
        $stmt = $pdo->prepare("INSERT INTO ACTIVIDADES_REGULARIZACION (id_clase, id_bloque, titulo, descripcion, archivo_ruta) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$id_clase, $id_bloque, $titulo, $descripcion, $ruta_archivo]);
        
        header("Location: ../docente/detalle_regularizacion.php?id=$id_clase&exito=Material publicado correctamente.");
        exit();
    } catch (PDOException $e) {
        error_log("Error al subir material: " . $e->getMessage());
        header("Location: ../docente/detalle_regularizacion.php?id=$id_clase&error=Hubo un error al guardar el material.");
        exit();
    }
} else {
    header("Location: ../docente/regularizaciones.php");
    exit();
}