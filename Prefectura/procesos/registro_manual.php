<?php
session_start();
require_once '../../config/database.php'; 

date_default_timezone_set('America/Mexico_City');

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'prefectos') {
    header("Location: ../../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matricula = trim($_POST['matricula'] ?? '');
    $tipo_movimiento = trim($_POST['tipo_movimiento'] ?? 'entrada');
    $estado_post = trim($_POST['estado'] ?? 'asistencia');
    $id_prefecto = $_SESSION['id_usuario'];

    if (empty($matricula)) {
        header("Location: ../dashboard_prefectura.php?msg=error_manual&detalle=" . urlencode("El campo de matrícula no puede estar vacío."));
        exit();
    }

    try {
        $stmt = $pdo->prepare("SELECT id_alumno, estado_disciplinario, fecha_fin_suspension FROM alumnos WHERE matricula = ?");
        $stmt->execute([$matricula]);
        $alumno = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($alumno) {
            $fecha_hoy = date('Y-m-d');
            
            // ==========================================
            // BARRERA DE SUSPENSIÓN MANUAL
            // ==========================================
            if ($alumno['estado_disciplinario'] === 'Baja') {
                header("Location: ../dashboard_prefectura.php?msg=alumno_baja");
                exit();
            }

            if ($alumno['estado_disciplinario'] === 'Suspendido' && $alumno['fecha_fin_suspension'] >= $fecha_hoy) {
                $fecha_ret = date('d/m/Y', strtotime($alumno['fecha_fin_suspension'] . ' + 1 day'));
                header("Location: ../dashboard_prefectura.php?msg=alumno_suspendido&retorno=" . urlencode($fecha_ret));
                exit();
            }

            if ($alumno['estado_disciplinario'] === 'Suspendido' && $alumno['fecha_fin_suspension'] < $fecha_hoy) {
                $stmtActivar = $pdo->prepare("UPDATE alumnos SET estado_disciplinario = 'Activo', fecha_fin_suspension = NULL WHERE id_alumno = ?");
                $stmtActivar->execute([$alumno['id_alumno']]);
            }
            // ==========================================

            // VALIDACIÓN: Buscar si el alumno ya tiene un registro HOY
            $stmtCheck = $pdo->prepare("
                SELECT id_asistencia, fecha_hora_salida 
                FROM asistencias 
                WHERE id_alumno = ? AND DATE(fecha_hora_escaneo) = CURDATE() 
                ORDER BY id_asistencia DESC LIMIT 1
            ");
            $stmtCheck->execute([$alumno['id_alumno']]);
            $registro_hoy = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($tipo_movimiento === 'entrada') {
                // Si el alumno ya ingresó hoy, mostrar aviso de que ya está dentro
                if ($registro_hoy) {
                    header("Location: ../dashboard_prefectura.php?msg=error_manual&detalle=" . urlencode("El alumno ya registró su entrada y se encuentra dentro de la institución."));
                    exit();
                } else {
                    $estatus_db = 'Puntual'; 
                    if ($estado_post === 'retardo') $estatus_db = 'Retardo';
                    elseif ($estado_post === 'inasistencia') $estatus_db = 'Falta'; 

                    $stmtInsert = $pdo->prepare("INSERT INTO asistencias (id_alumno, fecha_hora_escaneo, tipo_registro, estatus, id_prefecto) VALUES (?, NOW(), 'Entrada', ?, ?)");
                    $stmtInsert->execute([$alumno['id_alumno'], $estatus_db, $id_prefecto]);

                    header("Location: ../dashboard_prefectura.php?msg=exito_entrada");
                    exit();
                }
            } elseif ($tipo_movimiento === 'salida') {
                if (!$registro_hoy) {
                    // ERROR: NO HAY ENTRADA PREVIA HOY
                    header("Location: ../dashboard_prefectura.php?msg=error_manual&detalle=" . urlencode("No se puede registrar salida. El alumno no tiene una entrada previa el día de hoy."));
                    exit();
                } elseif ($registro_hoy['fecha_hora_salida'] !== null) {
                    // ERROR: LA SALIDA YA SE REGISTRÓ ANTERIORMENTE
                    header("Location: ../dashboard_prefectura.php?msg=error_manual&detalle=" . urlencode("El alumno ya registró su salida previamente y ya se retiró."));
                    exit();
                } else {
                    // ÉXITO AL REGISTRAR SALIDA
                    $stmtUpdate = $pdo->prepare("UPDATE asistencias SET fecha_hora_salida = NOW() WHERE id_asistencia = ?");
                    $stmtUpdate->execute([$registro_hoy['id_asistencia']]);
                    
                    header("Location: ../dashboard_prefectura.php?msg=exito_salida");
                    exit();
                }
            }
        } else {
            header("Location: ../dashboard_prefectura.php?msg=error_manual&detalle=" . urlencode("La matrícula ingresada no existe en el sistema."));
            exit();
        }
    } catch (PDOException $e) {
        header("Location: ../dashboard_prefectura.php?msg=error_manual&detalle=" . urlencode("Ocurrió un error en la base de datos al intentar guardar."));
        exit();
    }
}
?>