<?php
session_start();
require_once '../config/database.php';

// Validar que sea docente
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_clase = (int)$_POST['id_clase'];
    $titulo_bloque = trim($_POST['titulo_bloque'] ?? '');
    
    if (empty($titulo_bloque) || empty($id_clase)) {
        header("Location: ../docente/detalle_regularizacion.php?id=$id_clase&error=El título del bloque es obligatorio.");
        exit();
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO BLOQUES_REGULARIZACION (id_clase, titulo_bloque) VALUES (?, ?)");
        $stmt->execute([$id_clase, $titulo_bloque]);
        
        header("Location: ../docente/detalle_regularizacion.php?id=$id_clase&exito=Bloque creado correctamente.");
        exit();
    } catch (PDOException $e) {
        error_log("Error al crear bloque: " . $e->getMessage());
        header("Location: ../docente/detalle_regularizacion.php?id=$id_clase&error=Hubo un error al guardar el bloque.");
        exit();
    }
} else {
    header("Location: ../docente/regularizaciones.php");
    exit();
}