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
    $id_grupo = isset($_POST['id_grupo']) ? (int)$_POST['id_grupo'] : 0;
    $password_ingresada = isset($_POST['password_confirmacion']) ? $_POST['password_confirmacion'] : '';
    $id_docente = $_SESSION['id_usuario'];

    if ($id_clase === 0 || empty($password_ingresada)) {
        header("Location: ../gestionar_aula.php?id_grupo=$id_grupo&error=" . urlencode("No se especificó la materia o la contraseña está vacía."));
        exit();
    }

    try {
        // 1. Obtener la contraseña guardada (CORREGIDO A contrasena_hash)
        $stmtUser = $pdo->prepare("SELECT contrasena_hash FROM maestros WHERE id_maestro = ?");
        $stmtUser->execute([$id_docente]);
        $hash_bd = $stmtUser->fetchColumn();

        // 2. Verificar que la contraseña coincida
        if (password_verify($password_ingresada, $hash_bd) || $password_ingresada === $hash_bd) {
            
            // 3. Verificamos que la clase pertenezca al maestro
            $stmt_check = $pdo->prepare("SELECT id_clase FROM clases_regulares WHERE id_clase = ? AND id_maestro = ?");
            $stmt_check->execute([$id_clase, $id_docente]);
            
            if ($stmt_check->rowCount() === 0) {
                header("Location: ../gestionar_aula.php?id_grupo=$id_grupo&error=" . urlencode("No tienes permisos para restaurar esta clase."));
                exit();
            }

            // 4. Volvemos a activar la clase
            $stmt_update = $pdo->prepare("UPDATE clases_regulares SET estatus = 'Activa' WHERE id_clase = ?");
            $stmt_update->execute([$id_clase]);

            // 5. Volvemos a hacer visibles las actividades de esta clase
            $stmt_actividades = $pdo->prepare("UPDATE actividades_clase SET visible = 1 WHERE id_clase = ?");
            $stmt_actividades->execute([$id_clase]);

            // Redirigir con éxito
            header("Location: ../gestionar_aula.php?id_grupo=$id_grupo&exito=" . urlencode("El aula virtual fue restaurada correctamente."));
            exit();

        } else {
            // Contraseña incorrecta
            header("Location: ../gestionar_aula.php?id_grupo=$id_grupo&error=" . urlencode("Contraseña incorrecta. Operación cancelada por seguridad."));
            exit();
        }

    } catch (PDOException $e) {
        header("Location: ../gestionar_aula.php?id_grupo=$id_grupo&error=" . urlencode("Error al intentar restaurar: " . $e->getMessage()));
        exit();
    }
} else {
    header("Location: ../mis_grupos.php");
    exit();
}
?>