<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_actividad = (int)$_POST['id_actividad'];
    $id_clase = (int)$_POST['id_clase'];
    $titulo = trim($_POST['titulo']);
    $descripcion = trim($_POST['descripcion']);
    $fecha_limite = !empty($_POST['fecha_limite']) ? $_POST['fecha_limite'] : null;
    $permite_entregas = isset($_POST['permite_entregas']) ? 1 : 0;

    try {
        // 1. Actualizamos la información de texto primero
        $stmt = $pdo->prepare("UPDATE actividades_clase SET titulo = ?, descripcion = ?, fecha_limite = ?, permite_entregas = ? WHERE id_actividad = ?");
        $stmt->execute([$titulo, $descripcion, $fecha_limite, $permite_entregas, $id_actividad]);
        
        // 2. Si el profesor subió un NUEVO archivo adjunto para reemplazar el anterior
        if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            $archivo = $_FILES['archivo'];
            
            // Ruta centralizada para materiales de clase
            $carpeta_destino = '../../uploads/materiales/';
            if (!is_dir($carpeta_destino)) {
                mkdir($carpeta_destino, 0777, true);
            }

            // Limpieza de nombre y extensión
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            $nombre_original = pathinfo($archivo['name'], PATHINFO_FILENAME);
            $nombre_limpio = preg_replace("/[^a-zA-Z0-9_-]/", "_", $nombre_original);
            
            // Prefijo "mat_" (material) + id_actividad para que sea único
            $nuevo_nombre = "mat_act" . $id_actividad . "_" . time() . "_" . $nombre_limpio . "." . $extension;
            $ruta_final = $carpeta_destino . $nuevo_nombre;

            if (move_uploaded_file($archivo['tmp_name'], $ruta_final)) {
                // Actualizamos SOLO la columna del archivo en la BD
                // Asumo que tu columna se llama 'archivo' (si se llama distinto, cámbialo aquí)
                $stmt_file = $pdo->prepare("UPDATE actividades_clase SET archivo_ruta = ? WHERE id_actividad = ?");
                $stmt_file->execute([$nuevo_nombre, $id_actividad]);
            }
        }
        
        header("Location: ../gestionar_aula.php?id_clase=$id_clase&exito=Actividad actualizada correctamente.");
    } catch (PDOException $e) {
        header("Location: ../gestionar_aula.php?id_clase=$id_clase&error=Error al actualizar la actividad.");
    }
}
?>