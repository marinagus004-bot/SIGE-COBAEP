<?php
session_start();
require_once '../config/database.php';

// Validar seguridad: Solo administrativos pueden ejecutar esto
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../login.php");
    exit();
}

// Validar que la petición venga de nuestro formulario por método POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_usuario = intval($_POST['id_usuario']);
    $tipo_usuario = $_POST['tipo_usuario'];
    $nueva_password = trim($_POST['nueva_password']);

    // Validar longitud mínima por seguridad
    if(strlen($nueva_password) < 6) {
        die("Error: La contraseña provisional debe tener al menos 6 caracteres.");
    }

    // Encriptamos la nueva contraseña antes de guardarla
    $hash_seguro = password_hash($nueva_password, PASSWORD_BCRYPT);

    try {
        // Seleccionamos la tabla correcta según el tipo de usuario
        if ($tipo_usuario === 'alumnos') {
            $sql = "UPDATE ALUMNOS SET contrasena_hash = ? WHERE id_alumno = ?";
        } elseif ($tipo_usuario === 'maestros') {
            $sql = "UPDATE MAESTROS SET contrasena_hash = ? WHERE id_maestro = ?";
        } elseif ($tipo_usuario === 'admin') {
            $sql = "UPDATE ADMINISTRATIVOS SET contrasena_hash = ? WHERE id_admin = ?";
        } elseif ($tipo_usuario === 'prefectos') {
            $sql = "UPDATE PREFECTOS SET contrasena_hash = ? WHERE id_prefecto = ?";
        } else {
            die("Tipo de usuario no reconocido por el sistema.");
        }

        // Ejecutamos la actualización
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$hash_seguro, $id_usuario]);

        // Redirigimos de vuelta al directorio con un mensaje de éxito (puedes capturarlo en gestion_usuarios.php si gustas)
        header("Location: ../admin/gestion_usuarios.php?tipo=" . $tipo_usuario . "&msg=pwd_reset");
        exit();

    } catch (PDOException $e) {
        die("Error de Base de Datos al restablecer: " . $e->getMessage());
    }
} else {
    // Si alguien intenta abrir el archivo directamente desde la URL
    header("Location: ../admin/gestion_usuarios.php");
    exit();
}
?>