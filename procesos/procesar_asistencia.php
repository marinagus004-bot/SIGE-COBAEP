<?php
// procesos/procesar_asistencia.php
session_start();
require_once '../config/database.php';
// require_once '../config/firebase.php'; // Descomentar cuando Firebase esté configurado

// Configuramos la zona horaria para que coincida con la hora local real
date_default_timezone_set('America/Mexico_City');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Recibimos la matrícula enviada por el JavaScript del lector QR
    $data = json_decode(file_get_contents('php://input'), true);
    $matricula = isset($data['matricula']) ? trim($data['matricula']) : '';

    if (empty($matricula)) {
        echo json_encode(['status' => 'error', 'mensaje' => 'Código vacío']);
        exit;
    }

    // 1. Verificar que el alumno exista
    $stmtUser = $pdo->prepare("SELECT nombre_completo FROM usuarios WHERE matricula = ? AND rol = 'estudiante'");
    $stmtUser->execute([$matricula]);
    $alumno = $stmtUser->fetch();

    if (!$alumno) {
        echo json_encode(['status' => 'error', 'mensaje' => '❌ Matrícula no encontrada o no es estudiante.']);
        exit;
    }

    // 2. Lógica de horarios institucionales
    $hora_actual = date('H:i:s');
    $fecha_actual = date('Y-m-d');
    
    // Reglas de negocio para el Plantel 27
    $hora_entrada = '07:00:00';
    $hora_retardo = '07:15:00';

    if ($hora_actual <= $hora_entrada) {
        $estado = 'asistencia';
        $mensaje_ui = "✅ ASISTENCIA: " . $alumno['nombre_completo'];
        $color = "#2e7d32"; // Verde
    } elseif ($hora_actual <= $hora_retardo) {
        $estado = 'retardo';
        $mensaje_ui = "⚠️ RETARDO: " . $alumno['nombre_completo'];
        $color = "#f57c00"; // Naranja
    } else {
        $estado = 'inasistencia';
        $mensaje_ui = "❌ FALTA (Llegó tarde): " . $alumno['nombre_completo'];
        $color = "#c62828"; // Rojo
    }

    // 3. Guardar en MariaDB
    try {
        $stmtInsert = $pdo->prepare("INSERT INTO asistencias (matricula, fecha, hora, estado) VALUES (?, ?, ?, ?)");
        $stmtInsert->execute([$matricula, $fecha_actual, $hora_actual, $estado]);

        // 4. (Opcional) Sincronizar con Firebase como respaldo
        /*
        $datosFirebase = [
            'matricula' => $matricula,
            'fecha' => $fecha_actual,
            'hora' => $hora_actual,
            'estado' => $estado
        ];
        sincronizarConFirebase("asistencias/" . date('Y-m'), $datosFirebase);
        */

        // Responder al frontend
        echo json_encode(['status' => 'success', 'mensaje' => $mensaje_ui, 'color' => $color]);

    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'mensaje' => 'Error de BD: ' . $e->getMessage()]);
    }
}
?>