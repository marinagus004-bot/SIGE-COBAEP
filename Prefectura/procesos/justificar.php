<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'prefectos') {
    header("Location: ../../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_asistencia = filter_input(INPUT_POST, 'id_asistencia', FILTER_VALIDATE_INT);
    $motivo = trim($_POST['motivo'] ?? '');
    $detalles = trim($_POST['detalles'] ?? '');

    // Validar que manden el ID y hayan seleccionado una opción del Select
    if (!$id_asistencia || empty($motivo)) {
        header("Location: ../historial_asistencias.php?error=id_invalido");
        exit();
    }

    // Unir la opción del select con el texto extra (si escribieron algo)
    $motivo_final = $motivo;
    if (!empty($detalles)) {
        $motivo_final .= " - " . $detalles;
    }

    try {
        $stmt = $pdo->prepare("SELECT estatus FROM asistencias WHERE id_asistencia = ?");
        $stmt->execute([$id_asistencia]);
        $asistencia = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($asistencia) {
            $nuevo_estatus = '';
            $estatus_actual = strtolower($asistencia['estatus']);
            
            if ($estatus_actual === 'falta' || empty($estatus_actual)) {
                $nuevo_estatus = 'Falta Justificada';
            } elseif ($estatus_actual === 'retardo') {
                $nuevo_estatus = 'Retardo Justificado';
            }

            if ($nuevo_estatus !== '') {
                // AQUÍ SE ACTUALIZA EL ESTATUS Y SE INSERTA EL MOTIVO EN LA NUEVA COLUMNA
                $update = $pdo->prepare("UPDATE asistencias SET estatus = ?, motivo_justificacion = ? WHERE id_asistencia = ?");
                $update->execute([$nuevo_estatus, $motivo_final, $id_asistencia]);
                
                header("Location: ../historial_asistencias.php?msg=exito_justificante");
                exit();
            } else {
                header("Location: ../historial_asistencias.php?error=no_aplica");
                exit();
            }
        } else {
            header("Location: ../historial_asistencias.php?error=registro_no_encontrado");
            exit();
        }
    } catch (PDOException $e) {
        error_log("Error al justificar asistencia: " . $e->getMessage());
        header("Location: ../historial_asistencias.php?error=error_bd");
        exit();
    }
}

header("Location: ../historial_asistencias.php");
exit();
?>