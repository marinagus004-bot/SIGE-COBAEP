<?php
session_start();
require_once '../config/database.php';

// Validar que el usuario sea docente
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_maestro = $_SESSION['id_usuario'];
    
    // Recibir los datos de texto
    $nombre_materia = trim($_POST['nombre_materia'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $fecha_inicio = $_POST['fecha_inicio'] ?? null;
    $fecha_fin = $_POST['fecha_fin'] ?? null;

    if (empty($nombre_materia) || empty($fecha_inicio) || empty($fecha_fin)) {
        header("Location: ../docente/regularizaciones.php?error=Por favor, completa los campos obligatorios.");
        exit();
    }

    // ==========================================
    // LÓGICA DE SUBIDA DE IMAGEN
    // ==========================================
    $ruta_imagen = null;

    // Verificar si se subió un archivo sin errores
    if (isset($_FILES['imagen_portada']) && $_FILES['imagen_portada']['error'] === UPLOAD_ERR_OK) {
        
        // Crear la carpeta si no existe
        $directorio_destino = '../uploads/portadas/';
        if (!is_dir($directorio_destino)) {
            mkdir($directorio_destino, 0777, true);
        }

        $archivo_tmp = $_FILES['imagen_portada']['tmp_name'];
        $nombre_original = $_FILES['imagen_portada']['name'];
        $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
        
        // Extensiones permitidas
        $permitidas = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($extension, $permitidas)) {
            // Crear un nombre único para que no se sobreescriban
            $nombre_nuevo = uniqid('portada_') . '.' . $extension;
            $ruta_final = $directorio_destino . $nombre_nuevo;

            if (move_uploaded_file($archivo_tmp, $ruta_final)) {
                // Guardamos solo la ruta relativa en la BD
                $ruta_imagen = 'uploads/portadas/' . $nombre_nuevo; 
            }
        }
    }

    // Insertar en la base de datos (con o sin imagen)
    try {
        $stmt = $pdo->prepare("
            INSERT INTO CLASES_REGULARIZACION 
            (nombre_materia, descripcion, id_maestro, fecha_inicio, fecha_fin, estatus, imagen_portada) 
            VALUES (?, ?, ?, ?, ?, 'Activa', ?)
        ");
        
        $stmt->execute([
            $nombre_materia, 
            $descripcion, 
            $id_maestro, 
            $fecha_inicio, 
            $fecha_fin,
            $ruta_imagen
        ]);

        header("Location: ../docente/regularizaciones.php?exito=Espacio creado correctamente.");
        exit();

    } catch (PDOException $e) {
        error_log("Error al crear regularización: " . $e->getMessage());
        header("Location: ../docente/regularizaciones.php?error=Hubo un error al guardar en la base de datos.");
        exit();
    }
} else {
    header("Location: ../docente/regularizaciones.php");
    exit();
}