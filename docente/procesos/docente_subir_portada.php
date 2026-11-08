<?php
session_start();
require_once '../../config/database.php';

// Verificación de seguridad
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_clase = isset($_POST['id_clase']) ? (int)$_POST['id_clase'] : 0;
    require_once __DIR__ . '/permisos.php';
    if (!docente_dueno_clase($pdo, $id_clase)) docente_denegar(false);
    $id_materia = isset($_POST['id_materia']) ? (int)$_POST['id_materia'] : 0;
    $id_grupo = isset($_POST['id_grupo']) ? (int)$_POST['id_grupo'] : 0;

    if ($id_clase === 0 || $id_materia === 0) {
        header("Location: ../gestionar_aula.php?id_grupo=$id_grupo&error=Faltan datos para procesar la imagen.");
        exit();
    }

    // Verificar si se subió un archivo sin errores
    if (isset($_FILES['imagen_portada']) && $_FILES['imagen_portada']['error'] === UPLOAD_ERR_OK) {
        
        $archivo = $_FILES['imagen_portada'];
        
        // Validar que sea una imagen
        $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'webp'];
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, $extensiones_permitidas)) {
            header("Location: ../gestionar_aula.php?id_grupo=$id_grupo&error=Formato no válido. Solo se permiten imágenes JPG, PNG o WEBP.");
            exit();
        }

        // Limitar tamaño a 5MB
        if ($archivo['size'] > 5242880) {
            header("Location: ../gestionar_aula.php?id_grupo=$id_grupo&error=La imagen es muy pesada. El límite es de 5MB.");
            exit();
        }

        // Crear la carpeta si no existe
        $carpeta_destino = '../../uploads/portadas_materias/';
        if (!is_dir($carpeta_destino)) {
            mkdir($carpeta_destino, 0777, true);
        }

        // Generar un nombre único para evitar que se sobreescriban imágenes de otros maestros
        $nuevo_nombre = "portada_mat_" . $id_materia . "_" . time() . "." . $extension;
        $ruta_fisica = $carpeta_destino . $nuevo_nombre;
        
        // Esta es la ruta que se guarda en BD para que funcione en todas las vistas (docente y estudiante)
        $ruta_bd = "uploads/portadas_materias/" . $nuevo_nombre;

        try {
            if (move_uploaded_file($archivo['tmp_name'], $ruta_fisica)) {
                
                // Actualizamos la ruta en la tabla 'materias'
                $stmt = $pdo->prepare("UPDATE materias SET imagen_portada = ? WHERE id_materia = ?");
                $stmt->execute([$ruta_bd, $id_materia]);

                header("Location: ../gestionar_aula.php?id_grupo=$id_grupo&exito=Portada actualizada correctamente.");
                exit();
            } else {
                header("Location: ../gestionar_aula.php?id_grupo=$id_grupo&error=Error al subir el archivo al servidor.");
                exit();
            }
        } catch (PDOException $e) {
            header("Location: ../gestionar_aula.php?id_grupo=$id_grupo&error=Error en la base de datos: " . urlencode($e->getMessage()));
            exit();
        }
    } else {
        header("Location: ../gestionar_aula.php?id_grupo=$id_grupo&error=No se seleccionó ninguna imagen válida.");
        exit();
    }
} else {
    header("Location: ../mis_grupos.php");
    exit();
}
?>