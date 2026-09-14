<?php
// procesos/eliminar_usuario.php
session_start();
require_once '../config/database.php';

// Validar seguridad
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../login.php");
    exit();
}

if (isset($_GET['id']) && is_numeric($_GET['id']) && isset($_GET['tipo'])) {
    
    $id_eliminar = $_GET['id'];
    $tipo = $_GET['tipo'];

    // Protección adicional: Evitar que el administrador activo borre su propia cuenta por accidente
    if ($tipo === 'admin' && $id_eliminar == $_SESSION['id_usuario']) {
        die("<h2 style='color:red; text-align:center;'>Error de seguridad: No puedes eliminar tu propia cuenta mientras estás conectado.</h2><a href='../admin/gestion_usuarios.php?tipo=admin'>Volver</a>");
    }

    try {
        // Ejecutar el DELETE en la tabla correspondiente
        if ($tipo === 'alumnos') {
            $stmt = $pdo->prepare("DELETE FROM ALUMNOS WHERE id_alumno = ?");
        } elseif ($tipo === 'maestros') {
            $stmt = $pdo->prepare("DELETE FROM MAESTROS WHERE id_maestro = ?");
        } elseif ($tipo === 'admin') {
            $stmt = $pdo->prepare("DELETE FROM ADMINISTRATIVOS WHERE id_admin = ?");
        }
        
        $stmt->execute([$id_eliminar]);

        // Redirigir con mensaje
        header("Location: ../admin/gestion_usuarios.php?tipo=$tipo&msg=eliminado");
        exit();

    } catch (PDOException $e) {
        // En una BD profesional como la tuya, si un alumno ya tiene reportes o faltas ligadas a su ID, 
        // MySQL bloqueará el borrado para proteger la integridad de los datos (Foreign Key Constraint).
        die("<div style='padding:20px; font-family:sans-serif;'>
                <h3 style='color:red;'>No se puede eliminar a este usuario.</h3>
                <p>Este registro está conectado a otras tablas (historiales de asistencia, clases o reportes). 
                Primero debes dar de baja sus registros asociados o implementar un sistema de 'Baja Lógica'.</p>
                <a href='../admin/gestion_usuarios.php?tipo=$tipo'>Volver al directorio</a>
             </div>");
    }
} else {
    header("Location: ../admin/gestion_usuarios.php");
    exit();
}
?>