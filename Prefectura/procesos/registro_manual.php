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
        header("Location: ../dashboard_prefectura.php?msg=campos_vacios");
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

            if ($tipo_movimiento === 'salida') {
                $stmtCheck = $pdo->prepare("
                    SELECT id_asistencia FROM asistencias 
                    WHERE id_alumno = ? AND DATE(fecha_hora_escaneo) = CURDATE() 
                    ORDER BY id_asistencia DESC LIMIT 1
                ");
                $stmtCheck->execute([$alumno['id_alumno']]);
                $registro_hoy = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if ($registro_hoy) {
                    $stmtUpdate = $pdo->prepare("UPDATE asistencias SET fecha_hora_salida = NOW() WHERE id_asistencia = ?");
                    $stmtUpdate->execute([$registro_hoy['id_asistencia']]);
                    header("Location: ../dashboard_prefectura.php?msg=salida_registrada");
                } else {
                    header("Location: ../dashboard_prefectura.php?msg=sin_entrada_previa");
                }
                exit();
            } else {
                $estatus_db = 'Puntual'; 
                if ($estado_post === 'retardo') $estatus_db = 'Retardo';
                elseif ($estado_post === 'inasistencia') $estatus_db = 'Falta'; 

                $stmtInsert = $pdo->prepare("INSERT INTO asistencias (id_alumno, fecha_hora_escaneo, tipo_registro, estatus, id_prefecto) VALUES (?, NOW(), 'Entrada', ?, ?)");
                $stmtInsert->execute([$alumno['id_alumno'], $estatus_db, $id_prefecto]);

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