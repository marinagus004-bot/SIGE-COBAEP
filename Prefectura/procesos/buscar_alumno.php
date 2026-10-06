<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
require_once '../../config/database.php';

$matricula = trim($_GET['matricula'] ?? '');

if (empty($matricula)) {
    echo json_encode(['success' => false, 'error' => 'Matrícula vacía']);
    exit();
}

try {
    // Jalamos directamente la columna puntos_conducta de la tabla alumnos
    $query = "SELECT id_alumno, nombre, apellido_paterno, semestre, turno, puntos_conducta FROM alumnos WHERE matricula = :matricula LIMIT 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':matricula' => $matricula]);
    $alumno = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($alumno) {
        $grado_grupo = ($alumno['semestre'] ?? 'Sin Semestre') . ' - ' . ($alumno['turno'] ?? 'Sin Turno');
        
        // Si por alguna razón está nulo, el valor por defecto es 100
        $puntos_actuales = $alumno['puntos_conducta'] !== null ? $alumno['puntos_conducta'] : 100;

        echo json_encode([
            'success' => true,
            'nombre' => $alumno['apellido_paterno'] . ' ' . $alumno['nombre'],
            'grado_grupo' => $grado_grupo,
            'puntos_restantes' => $puntos_actuales
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'No encontrado']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}
?>