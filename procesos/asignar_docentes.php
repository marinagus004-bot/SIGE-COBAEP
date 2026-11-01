<?php
session_start();
require_once '../config/database.php';

// Validar seguridad
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_grupo'])) {
    $id_grupo = $_POST['id_grupo'];
    // Recibimos el arreglo de checkboxes (si no hay ninguno, el array estará vacío)
    $maestros = $_POST['maestros_seleccionados'] ?? [];

    try {
        $pdo->beginTransaction();

        if (!empty($maestros)) {
            // INSERT IGNORE evita que la base de datos falle si intentas asignar un maestro que ya estaba en ese grupo
            $stmt = $pdo->prepare("INSERT IGNORE INTO GRUPO_MAESTRO (id_grupo, id_maestro) VALUES (?, ?)");
            
            foreach ($maestros as $id_maestro) {
                $stmt->execute([$id_grupo, $id_maestro]);
            }
        }

        $pdo->commit();
        
        // Redirigimos de vuelta a ver_grupo.php con un mensaje de éxito
        header("Location: ../admin/ver_grupo.php?id=" . $id_grupo . "&msg=docentes_asignados");
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        die("Error al asignar profesores: " . $e->getMessage());
    }
} else {
    // Si intentan entrar directo al archivo sin enviar el form
    header("Location: ../admin/gestion_grupos.php");
    exit();
}