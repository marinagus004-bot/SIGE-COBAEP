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
    $id_prefecto =$_SESSION['id_usuario'];

    if (empty($matricula)) {
        echo json_encode(['status' => 'error', 'message' => 'Código vacío.']);
        exit();
    }

    try {
        $stmt =$pdo->prepare("SELECT id_alumno, nombre, apellido_paterno, turno, estado_disciplinario, fecha_fin_suspension FROM ALUMNOS WHERE matricula = ? AND estatus = 'Alta'");
        $stmt->execute([$matricula]);
        $alumno =$stmt->fetch(PDO::FETCH_ASSOC);

        if (!$alumno) {
            echo json_encode(['status' => 'error', 'message' => 'Matrícula no encontrada o alumno inactivo.']);
            exit();
        }

        $nombre_completo = mb_strtoupper($alumno['nombre'] . ' ' . $alumno['apellido_paterno'], 'UTF-8');$fecha_hoy = date('Y-m-d');

        // ==========================================
        // BARRERA: VERIFICAR SUSPENSIÓN PARA EL QR
        // ==========================================
        if ($alumno['estado_disciplinario'] === 'Baja') {
            echo json_encode(['status' => 'error_suspendido', 'message' => "ACCESO DENEGADO: $nombre_completo está dado de BAJA."]);
            exit();
        }

        if ($alumno['estado_disciplinario'] === 'Suspendido') {
            if ($alumno['fecha_fin_suspension'] >=$fecha_hoy) {
                $fecha_retorno = date('d/m/Y', strtotime($alumno['fecha_fin_suspension'] . ' + 1 day'));
                echo json_encode(['status' => 'error_suspendido', 'message' => "SUSPENDIDO: $nombre_completo. Regresa el $fecha_retorno."]);
                exit();
            } else {
                // Ya cumplió su castigo, reactivar automáticamente
                $stmtActivar =$pdo->prepare("UPDATE ALUMNOS SET estado_disciplinario = 'Activo', fecha_fin_suspension = NULL WHERE id_alumno = ?");
                $stmtActivar->execute([$alumno['id_alumno']]);
            }
        }
        // ==========================================

        $id_alumno =$alumno['id_alumno'];

        // EXTRAEMOS LA FECHA DE ESCANEO
        $stmtCheck =$pdo->prepare("SELECT id_asistencia, fecha_hora_escaneo, fecha_hora_salida FROM ASISTENCIAS WHERE id_alumno = ? AND DATE(fecha_hora_escaneo) = CURDATE() ORDER BY id_asistencia DESC LIMIT 1");
        $stmtCheck->execute([$id_alumno]);
        $registro_hoy =$stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($registro_hoy) {
            // Evaluar Salida
            if (is_null($registro_hoy['fecha_hora_salida'])) {
                // PROTECCIÓN CONTRA DOBLE ESCANEO (Cooldown de 5 min)
                $minutos_transcurridos = (time() - strtotime($registro_hoy['fecha_hora_escaneo'])) / 60;
                
                if ($minutos_transcurridos < 5) {
                    echo json_encode(['status' => 'warning', 'message' => "Ya está dentro: $nombre_completo. (Espera 5 min para la salida)"]);
                    exit();
                }

                // REGISTRAR SALIDA
                $stmtOut =$pdo->prepare("UPDATE ASISTENCIAS SET fecha_hora_salida = NOW() WHERE id_asistencia = ?");
                $stmtOut->execute([$registro_hoy['id_asistencia']]);
                echo json_encode(['status' => 'success_salida', 'message' => "Salida registrada: $nombre_completo"]);
            } else {
                echo json_encode(['status' => 'error', 'message' => "$nombre_completo ya registró su salida hoy."]);
            }
            exit();
        } else {
            // NO TIENE REGISTROS HOY: MARCAR ENTRADA
            $hora_actual = date('H:i:s');$estatus_asistencia = 'Puntual';

            // Lógica de Retardos incluyendo Vespertino
            if ($alumno['turno'] === 'Matutino' && $hora_actual > '07:10:00') {$estatus_asistencia = 'Retardo';
            } elseif ($alumno['turno'] === 'Intermedio' && $hora_actual > '10:40:00') {$estatus_asistencia = 'Retardo';
            } elseif ($alumno['turno'] === 'Vespertino' && $hora_actual > '14:10:00') {$estatus_asistencia = 'Retardo'; // Ajusta esta hora según tu plantel
            }

            $stmtIn =$pdo->prepare("INSERT INTO ASISTENCIAS (id_alumno, fecha_hora_escaneo, tipo_registro, estatus, id_prefecto) VALUES (?, NOW(), 'Entrada', ?, ?)");
            $stmtIn->execute([$id_alumno, $estatus_asistencia,$id_prefecto]);
            
            // Enviamos un status diferente si es retardo para que pite diferente
            $status_respuesta = ($estatus_asistencia === 'Retardo') ? 'warning' : 'success';
            echo json_encode(['status' => $status_respuesta, 'message' => "$estatus_asistencia:$nombre_completo"]);
            exit();
        }

    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Error de base de datos.']);
        exit();
    }
}
?>