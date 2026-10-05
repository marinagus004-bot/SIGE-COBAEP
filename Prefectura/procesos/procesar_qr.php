<?php
session_start();
require_once '../../config/database.php';

date_default_timezone_set('America/Mexico_City');
header('Content-Type: application/json');

if (!isset($_SESSION['id_usuario'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión caducada.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matricula = trim($_POST['matricula'] ?? '');
    $id_prefecto = $_SESSION['id_usuario'];

    if (empty($matricula)) {
        echo json_encode(['status' => 'error', 'message' => 'Código vacío.']);
        exit();
    }

    try {
        $stmt = $pdo->prepare("SELECT id_alumno, nombre, apellido_paterno, turno, estado_disciplinario, fecha_fin_suspension FROM ALUMNOS WHERE matricula = ? AND estatus = 'Alta'");
        $stmt->execute([$matricula]);
        $alumno = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$alumno) {
            echo json_encode(['status' => 'error', 'message' => 'Matrícula no encontrada o alumno inactivo.']);
            exit();
        }

        $nombre_completo = $alumno['nombre'] . ' ' . $alumno['apellido_paterno'];
        $fecha_hoy = date('Y-m-d');

        // ==========================================
        // BARRERA: VERIFICAR SUSPENSIÓN PARA EL QR
        // ==========================================
        if ($alumno['estado_disciplinario'] === 'Baja') {
            echo json_encode(['status' => 'error_suspendido', 'message' => "ACCESO DENEGADO: $nombre_completo está dado de BAJA definitiva."]);
            exit();
        }

        if ($alumno['estado_disciplinario'] === 'Suspendido' && $alumno['fecha_fin_suspension'] >= $fecha_hoy) {
            $fecha_retorno = date('d/m/Y', strtotime($alumno['fecha_fin_suspension'] . ' + 1 day'));
            echo json_encode(['status' => 'error_suspendido', 'message' => "ACCESO DENEGADO: $nombre_completo está SUSPENDIDO. Puede regresar el $fecha_retorno."]);
            exit();
        }
        
        // Si el castigo ya expiró, cambiar a Activo automáticamente
        if ($alumno['estado_disciplinario'] === 'Suspendido' && $alumno['fecha_fin_suspension'] < $fecha_hoy) {
            $stmtActivar = $pdo->prepare("UPDATE ALUMNOS SET estado_disciplinario = 'Activo', fecha_fin_suspension = NULL WHERE id_alumno = ?");
            $stmtActivar->execute([$alumno['id_alumno']]);
        }
        // ==========================================

        $id_alumno = $alumno['id_alumno'];

        $stmtCheck = $pdo->prepare("SELECT id_asistencia, fecha_hora_salida FROM ASISTENCIAS WHERE id_alumno = ? AND DATE(fecha_hora_escaneo) = CURDATE() ORDER BY id_asistencia DESC LIMIT 1");
        $stmtCheck->execute([$id_alumno]);
        $registro_hoy = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($registro_hoy) {
            if (is_null($registro_hoy['fecha_hora_salida'])) {
                $stmtOut = $pdo->prepare("UPDATE ASISTENCIAS SET fecha_hora_salida = NOW() WHERE id_asistencia = ?");
                $stmtOut->execute([$registro_hoy['id_asistencia']]);
                echo json_encode(['status' => 'success', 'message' => "Salida registrada: $nombre_completo"]);
            } else {
                echo json_encode(['status' => 'error', 'message' => "$nombre_completo ya registró entrada y salida hoy."]);
            }
            exit();
        } else {
            $hora_actual = date('H:i:s');
            $estatus_asistencia = 'Puntual';

            if ($alumno['turno'] === 'Matutino' && $hora_actual > '07:10:00') {
                $estatus_asistencia = 'Retardo';
            } elseif ($alumno['turno'] === 'Intermedio' && $hora_actual > '10:40:00') {
                $estatus_asistencia = 'Retardo';
            }

            $stmtIn = $pdo->prepare("INSERT INTO ASISTENCIAS (id_alumno, fecha_hora_escaneo, tipo_registro, estatus, id_prefecto) VALUES (?, NOW(), 'Entrada', ?, ?)");
            $stmtIn->execute([$id_alumno, $estatus_asistencia, $id_prefecto]);
            
            echo json_encode(['status' => 'success', 'message' => "$estatus_asistencia: $nombre_completo"]);
            exit();
        }

    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error de base de datos.']);
        exit();
    }
}
?>