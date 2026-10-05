<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../login.php");
    exit();
}

// 1. Eliminar horario si viene por GET
if (isset($_GET['eliminar'])) {
    $id_eliminar = intval($_GET['eliminar']);
    $stmt = $pdo->prepare("DELETE FROM HORARIOS_MAESTRO WHERE id_horario = ?");
    $stmt->execute([$id_eliminar]);
    header("Location: ../admin/gestion_horarios.php?msg=eliminado");
    exit();
}

// 2. Crear o Actualizar horario por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_horario = intval($_POST['id_horario'] ?? 0);
    $id_maestro = intval($_POST['id_maestro'] ?? 0);
    $id_grupo = intval($_POST['id_grupo'] ?? 0);
    $ciclo = $_POST['ciclo'] === 'Feb-Jul' ? 'Feb-Jul' : 'Ago-Ene';
    $anio = intval($_POST['anio'] ?? date('Y'));

    $nombre_archivo = null;
    $ruta_pdf = null;

    // Si subieron archivo PDF
    if (isset($_FILES['archivo_pdf']) && $_FILES['archivo_pdf']['error'] === UPLOAD_ERR_OK) {
        $dir = '../uploads/horarios/';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $nombre_original = basename($_FILES['archivo_pdf']['name']);
        $nuevo_nombre = 'horario_docente_' . $id_maestro . '_' . time() . '.pdf';
        if (move_uploaded_file($_FILES['archivo_pdf']['tmp_name'], $dir . $nuevo_nombre)) {
            $nombre_archivo = $nombre_original;
            $ruta_pdf = 'uploads/horarios/' . $nuevo_nombre;

            // Sincronizar también el campo horario_archivo en la tabla MAESTROS
            $updM = $pdo->prepare("UPDATE MAESTROS SET horario_archivo = ? WHERE id_maestro = ?");
            $updM->execute([$ruta_pdf, $id_maestro]);
        }
    }

    if ($id_horario > 0) {
        // Actualizar existente
        if ($ruta_pdf) {
            $sql = "UPDATE HORARIOS_MAESTRO SET id_maestro=?, id_grupo=?, nombre_archivo=?, ruta_pdf=?, ciclo=?, anio=? WHERE id_horario=?";
            $pdo->prepare($sql)->execute([$id_maestro, $id_grupo, $nombre_archivo, $ruta_pdf, $ciclo, $anio, $id_horario]);
        } else {
            $sql = "UPDATE HORARIOS_MAESTRO SET id_maestro=?, id_grupo=?, ciclo=?, anio=? WHERE id_horario=?";
            $pdo->prepare($sql)->execute([$id_maestro, $id_grupo, $ciclo, $anio, $id_horario]);
        }
        header("Location: ../admin/gestion_horarios.php?msg=actualizado");
        exit();
    } else {
        // Insertar nuevo
        $sql = "INSERT INTO HORARIOS_MAESTRO (id_maestro, id_grupo, nombre_archivo, ruta_pdf, ciclo, anio) VALUES (?, ?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute([$id_maestro, $id_grupo, $nombre_archivo, $ruta_pdf, $ciclo, $anio]);
        header("Location: ../admin/gestion_horarios.php?msg=creado");
        exit();
    }
}
