<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') { exit(); }

$id_clase = isset($_GET['id_clase']) ? (int)$_GET['id_clase'] : 0;
$tipo_filtro = isset($_GET['tipo_filtro']) ? $_GET['tipo_filtro'] : 'semestre';
$mes_filtro = isset($_GET['mes']) ? $_GET['mes'] : '';
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';

if ($id_clase === 0) {
    echo "<tr><td colspan='5'>Error: Clase no válida.</td></tr>";
    exit;
}

// Construir condición WHERE dinámica (Corregido para evitar el error SQLSTATE)
$where_fecha = "";
$params = [];

if ($tipo_filtro === 'mes' && !empty($mes_filtro) && is_numeric($mes_filtro)) {
    $where_fecha = " AND MONTH(ac.fecha) = ?";
    $params[] = $mes_filtro;
} elseif ($tipo_filtro === 'parcial' && !empty($fecha_inicio) && !empty($fecha_fin)) {
    $where_fecha = " AND ac.fecha BETWEEN ? AND ?";
    $params[] = $fecha_inicio;
    $params[] = $fecha_fin;
}

// Agregamos al final el parámetro de la clase para el WHERE principal
$params[] = $id_clase;

try {
    $query = "
        SELECT a.id_alumno, a.matricula, a.nombre, a.apellido_paterno,
            SUM(CASE WHEN ac.estatus IN ('Presente', 'Asistencia') THEN 1 ELSE 0 END) as presentes,
            SUM(CASE WHEN ac.estatus = 'Retardo' THEN 1 ELSE 0 END) as retardos,
            SUM(CASE WHEN ac.estatus IN ('Falta', 'Ausente') THEN 1 ELSE 0 END) as faltas
        FROM clase_regular_alumno cra
        INNER JOIN alumnos a ON cra.id_alumno = a.id_alumno
        LEFT JOIN asistencias_clases ac ON a.id_alumno = ac.id_alumno AND ac.id_clase = cra.id_clase $where_fecha
        WHERE cra.id_clase = ?
        GROUP BY a.id_alumno
        ORDER BY a.apellido_paterno ASC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params); 
    
    $alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 1. Validar si la clase no tiene alumnos inscritos
    if (empty($alumnos)) {
        echo "<tr><td colspan='5' style='text-align:center; padding: 20px; color: #64748b;'>No hay alumnos inscritos en esta clase.</td></tr>";
        exit;
    }

    // 2. VALIDACIÓN INTELIGENTE: Comprobar si hay asistencias en el periodo seleccionado
    $total_asistencias_periodo = 0;
    foreach($alumnos as $alum) {
        $total_asistencias_periodo += ($alum['presentes'] + $alum['retardos'] + $alum['faltas']);
    }

    if ($total_asistencias_periodo == 0) {
        // Personalizamos el mensaje según el filtro que eligió el profe
        $mensaje = "No hay asistencias registradas en todo el semestre.";
        if ($tipo_filtro === 'mes') $mensaje = "No hay asistencias registradas en el mes seleccionado.";
        if ($tipo_filtro === 'parcial') $mensaje = "No hay asistencias registradas en este rango de fechas.";
        
        echo "<tr>
                <td colspan='5' style='text-align:center; padding: 40px 20px; color: #dc2626; background: #fef2f2; border-bottom: 2px solid #fecaca;'>
                    <i class='fas fa-calendar-times' style='font-size: 2rem; display:block; margin-bottom: 10px; color: #f87171;'></i>
                    <strong style='font-size: 1.1rem;'>$mensaje</strong>
                </td>
              </tr>";
        exit;
    }

    // 3. Si sí hay datos, imprimimos la tabla normal
    foreach($alumnos as $alum) {
        $presentes = $alum['presentes'] ?? 0;
        $retardos = $alum['retardos'] ?? 0;
        $faltas = $alum['faltas'] ?? 0;

        echo "<tr>";
        echo "<td>" . htmlspecialchars($alum['matricula']) . "</td>";
        echo "<td><strong>" . htmlspecialchars($alum['apellido_paterno'] . ' ' . $alum['nombre']) . "</strong></td>";
        echo "<td class='text-center' style='color:#198754; font-weight:bold;'>" . $presentes . "</td>";
        echo "<td class='text-center' style='color:#d97706; font-weight:bold;'>" . $retardos . "</td>";
        echo "<td class='text-center' style='color:#dc3545; font-weight:bold;'>" . $faltas . "</td>";
        echo "</tr>";
    }

} catch (PDOException $e) {
    echo "<tr><td colspan='5'>Error en la base de datos: " . $e->getMessage() . "</td></tr>";
}
?>