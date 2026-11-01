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
    
    // Este es el ID del profesor que inició sesión
    $id_docente = $_SESSION['id_usuario'];

    if ($id_clase === 0 || empty($password_ingresada)) {
        header("Location: ../gestionar_aula.php?id_grupo=$id_grupo&error=Debes ingresar tu contraseña para eliminar el aula.");
        exit();
    }

    try {
        // 1. Verificar la contraseña del docente apuntando a 'contrasena_hash'
        $stmt_pass = $pdo->prepare("SELECT contrasena_hash FROM maestros WHERE id_maestro = ?");
        $stmt_pass->execute([$id_docente]);
        $user = $stmt_pass->fetch(PDO::FETCH_ASSOC);

        $pass_valida = false;
        
        // Verificamos si existe el usuario y si la contraseña ingresada coincide con el hash de la BD
        if ($user && !empty($user['contrasena_hash'])) {
            if (password_verify($password_ingresada, $user['contrasena_hash'])) {
                $pass_valida = true;
            }
        }

        if (!$pass_valida) {
            // Contraseña incorrecta -> Retorna el error rojo elegante
            header("Location: ../gestionar_aula.php?id_grupo=$id_grupo&error=Contraseña incorrecta. Acción cancelada por seguridad.");
            exit();
        }

        // 2. Verificamos que la clase pertenezca al maestro que intenta borrarla
        $stmt_check = $pdo->prepare("SELECT id_clase FROM clases_regulares WHERE id_clase = ? AND id_maestro = ?");
        $stmt_check->execute([$id_clase, $id_docente]);
        
        if ($stmt_check->rowCount() === 0) {
            header("Location: ../gestionar_aula.php?id_grupo=$id_grupo&error=No tienes permisos para eliminar esta clase.");
            exit();
        }

        // 3. Ocultar la materia cambiando su estatus (DELETE lógico seguro)
        $stmt_update = $pdo->prepare("UPDATE clases_regulares SET estatus = 'Inactiva' WHERE id_clase = ?");
        $stmt_update->execute([$id_clase]);

        // Ocultar también las actividades vinculadas
        $stmt_actividades = $pdo->prepare("UPDATE actividades_clase SET visible = 0 WHERE id_clase = ?");
        $stmt_actividades->execute([$id_clase]);

        // Todo salió bien -> Retorna el recuadro verde de éxito elegante
        header("Location: ../gestionar_aula.php?id_grupo=$id_grupo&exito=El aula virtual fue eliminada de forma segura.");
        exit();

    } catch (PDOException $e) {
        header("Location: ../gestionar_aula.php?id_grupo=$id_grupo&error=Error al intentar eliminar: " . urlencode($e->getMessage()));
        exit();
    }
} else {
    header("Location: ../mis_grupos.php");
    exit();
}
?>