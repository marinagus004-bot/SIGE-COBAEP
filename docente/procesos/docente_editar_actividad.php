<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recepción de datos básicos
    $id_actividad = (int)$_POST['id_actividad'];
    $id_clase = (int)$_POST['id_clase'];
    $titulo = trim($_POST['titulo']);
    $descripcion = trim($_POST['descripcion']);
    
    // Recepción de Fechas y Checkboxes
    $fecha_limite = !empty($_POST['fecha_limite']) ? $_POST['fecha_limite'] : null;
    $fecha_apertura = !empty($_POST['fecha_apertura']) ? $_POST['fecha_apertura'] : null;
    $permite_entregas = isset($_POST['permite_entregas']) ? 1 : 0;
    $acepta_retardo = isset($_POST['acepta_retardo']) ? 1 : 0;

    $ruta_bd = null;

    try {
        // Tu código de validación y subida de archivo se queda igual
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
                $ruta_bd = $nuevo_nombre;
            }
        }

        // Construir la consulta UPDATE (Ejemplo si subió archivo nuevo)
        if ($ruta_bd) {
            $stmt = $pdo->prepare("UPDATE actividades_clase SET titulo = ?, descripcion = ?, fecha_limite = ?, fecha_apertura = ?, permite_entregas = ?, acepta_retardo = ?, archivo_ruta = ? WHERE id_actividad = ? AND id_clase = ?");
            $stmt->execute([$titulo, $descripcion, $fecha_limite, $fecha_apertura, $permite_entregas, $acepta_retardo, $ruta_bd, $id_actividad, $id_clase]);
        } else {
            // Ejemplo si no subió archivo nuevo
            $stmt = $pdo->prepare("UPDATE actividades_clase SET titulo = ?, descripcion = ?, fecha_limite = ?, fecha_apertura = ?, permite_entregas = ?, acepta_retardo = ? WHERE id_actividad = ? AND id_clase = ?");
            $stmt->execute([$titulo, $descripcion, $fecha_limite, $fecha_apertura, $permite_entregas, $acepta_retardo, $id_actividad, $id_clase]);
        }
        
        header("Location: ../gestionar_aula.php?id_clase=$id_clase&exito=Actividad actualizada correctamente.");
        exit();
    } catch (PDOException $e) {
        header("Location: ../gestionar_aula.php?id_clase=$id_clase&error=Error al actualizar la actividad: " . urlencode($e->getMessage()));
        exit();
    }
}
?>