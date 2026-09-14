<?php
session_start();
require_once '../../config/database.php'; 

date_default_timezone_set('America/Mexico_City');

// Validar que el usuario sea prefecto para obtener su ID
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'prefectos') {
    header("Location: ../../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matricula = trim($_POST['matricula'] ?? '');
    // Recibimos el tipo de movimiento desde el nuevo campo del formulario
    $tipo_movimiento = trim($_POST['tipo_movimiento'] ?? 'entrada');
    $estado_post = trim($_POST['estado'] ?? 'asistencia');
    $id_prefecto = $_SESSION['id_usuario'];

    if (empty($matricula)) {
        header("Location: ../dashboard_prefectura.php?msg=campos_vacios");
        exit();
    }

    try {
        // 1. Buscamos solo el ID del alumno
        $stmt = $pdo->prepare("SELECT id_alumno FROM alumnos WHERE matricula = ?");
        $stmt->execute([$matricula]);
        $alumno = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($alumno) {
            
            // 2. Evaluamos si la acción es registrar una SALIDA
            if ($tipo_movimiento === 'salida') {
                // Buscamos el registro de entrada de HOY para este alumno
                $stmtCheck = $pdo->prepare("
                    SELECT id_asistencia 
                    FROM asistencias 
                    WHERE id_alumno = ? AND DATE(fecha_hora_escaneo) = CURDATE() 
                    ORDER BY id_asistencia DESC LIMIT 1
                ");
                $stmtCheck->execute([$alumno['id_alumno']]);
                $registro_hoy = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if ($registro_hoy) {
                    // Actualizamos la hora de salida en su registro de la mañana
                    $stmtUpdate = $pdo->prepare("UPDATE asistencias SET fecha_hora_salida = NOW() WHERE id_asistencia = ?");
                    $stmtUpdate->execute([$registro_hoy['id_asistencia']]);
                    header("Location: ../dashboard_prefectura.php?msg=salida_registrada");
                } else {
                    // Si intenta registrar salida sin tener registro previo de entrada
                    header("Location: ../dashboard_prefectura.php?msg=sin_entrada_previa");
                }
                exit();

            } else {
                // 3. Evaluamos si la acción es registrar una ENTRADA
                $estatus_db = 'Puntual'; 
                if ($estado_post === 'retardo') $estatus_db = 'Retardo';
                elseif ($estado_post === 'inasistencia') $estatus_db = 'Falta'; // Ajustado al ENUM de la BD

                // Insertamos la asistencia de forma limpia (sin grado, ni grupo, ni turno)
                $stmtInsert = $pdo->prepare("
                    INSERT INTO asistencias (id_alumno, fecha_hora_escaneo, tipo_registro, estatus, id_prefecto) 
                    VALUES (?, NOW(), 'Entrada', ?, ?)
                ");
                
                $stmtInsert->execute([
                    $alumno['id_alumno'], 
                    $estatus_db,
                    $id_prefecto
                ]);

                header("Location: ../dashboard_prefectura.php?msg=registro_exitoso");
                exit();
            }

        } else {
            header("Location: ../dashboard_prefectura.php?msg=matricula_no_encontrada");
            exit();
        }
    } catch (PDOException $e) {
        die("Error de BD: " . $e->getMessage());
    }
}
?>