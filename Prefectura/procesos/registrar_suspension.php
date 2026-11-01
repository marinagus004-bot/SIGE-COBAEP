<?php
session_start();
require_once '../../config/database.php';

// 1. SEGURIDAD: Verificar que sea un usuario autorizado (Prefecto)
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'prefectos') {
    header("Location: ../../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Usamos trim() para limpiar espacios en blanco accidentales
    $motivo = trim($_POST['motivo'] ?? '');
    $fecha_inicio = trim($_POST['fecha_inicio'] ?? '');
    $fecha_fin = trim($_POST['fecha_fin'] ?? '');

    // 2. VALIDACIÓN DE CAMPOS VACÍOS
    if (empty($motivo) || empty($fecha_inicio) || empty($fecha_fin)) {
        header("Location: ../dashboard_prefectura.php?error=campos_vacios");
        exit();
    }

    // 3. VALIDACIÓN LÓGICA DE FECHAS
    if (strtotime($fecha_fin) < strtotime($fecha_inicio)) {
        header("Location: ../dashboard_prefectura.php?error=fechas_invalidas");
        exit();
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO SUSPENSIONES_CLASES (motivo, fecha_inicio, fecha_fin) VALUES (?, ?, ?)");
        $stmt->execute([$motivo, $fecha_inicio, $fecha_fin]);
        
        header("Location: ../dashboard_prefectura.php?msg=suspension_registrada");
        exit();
    } catch (PDOException $e) {
        // 4. MANEJO DE ERRORES SEGURO (Registramos el error internamente y redirigimos sin romper la web)
        error_log("Error en SUSPENSIONES_CLASES: " . $e->getMessage());
        header("Location: ../dashboard_prefectura.php?error=error_bd");
        exit();
    }
}

// Si alguien intenta entrar directamente por la URL (GET), lo regresamos
header("Location: ../dashboard_prefectura.php");
exit();
?>