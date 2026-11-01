<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') { exit(); }

$id_clase = isset($_GET['id_clase']) ? (int)$_GET['id_clase'] : 0;
if ($id_clase === 0) exit("Clase no válida.");

// Recibir Filtros
$tipo_filtro = isset($_GET['tipo_filtro']) ? $_GET['tipo_filtro'] : 'semestre';
$mes_filtro = isset($_GET['mes']) ? $_GET['mes'] : '';
$fecha_inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : '';
$fecha_fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : '';

// 1. Info de la materia
$stmt = $pdo->prepare("SELECT m.nombre_materia, g.nombre_grupo, g.semestre FROM clases_regulares c INNER JOIN materias m ON c.id_materia = m.id_materia LEFT JOIN grupos g ON c.id_grupo = g.id_grupo WHERE c.id_clase = ?");
$stmt->execute([$id_clase]);
$clase = $stmt->fetch(PDO::FETCH_ASSOC);

// 2. Construir la consulta según el filtro elegido
$where_fecha = "";
$params = [$id_clase];
$titulo_periodo = "PERIODO: SEMESTRE COMPLETO";
$nombre_archivo = "Semestre";

if ($tipo_filtro === 'mes' && !empty($mes_filtro) && is_numeric($mes_filtro)) {
    $where_fecha = " AND MONTH(fecha) = ?";
    $params[] = $mes_filtro;
    $titulo_periodo = "Corte del Mes: " . $mes_filtro;
    $nombre_archivo = "Mes_" . $mes_filtro;
} elseif ($tipo_filtro === 'parcial' && !empty($fecha_inicio) && !empty($fecha_fin)) {
    $where_fecha = " AND fecha BETWEEN ? AND ?";
    $params[] = $fecha_inicio;
    $params[] = $fecha_fin;
    $titulo_periodo = "PERIODO (Parcial): " . date('d/m/Y', strtotime($fecha_inicio)) . " AL " . date('d/m/Y', strtotime($fecha_fin));
    $nombre_archivo = "Parcial";
}

// 3. Traer los días exactos donde se pasó lista
$stmtFechas = $pdo->prepare("SELECT DISTINCT fecha FROM asistencias_clases WHERE id_clase = ? $where_fecha ORDER BY fecha ASC");
$stmtFechas->execute($params);
$fechas = $stmtFechas->fetchAll(PDO::FETCH_COLUMN);

// 4. Obtener Alumnos
$stmtAlumnos = $pdo->prepare("SELECT a.id_alumno, a.matricula, a.nombre, a.apellido_paterno FROM clase_regular_alumno cra INNER JOIN alumnos a ON cra.id_alumno = a.id_alumno WHERE cra.id_clase = ? ORDER BY a.apellido_paterno ASC");
$stmtAlumnos->execute([$id_clase]);
$alumnos = $stmtAlumnos->fetchAll(PDO::FETCH_ASSOC);

// 5. Cruzar todas las asistencias
$stmtAsist = $pdo->prepare("SELECT id_alumno, fecha, estatus FROM asistencias_clases WHERE id_clase = ? $where_fecha");
$stmtAsist->execute($params);
$asistencias = [];
while($row = $stmtAsist->fetch(PDO::FETCH_ASSOC)){
    $asistencias[$row['id_alumno']][$row['fecha']] = $row['estatus'];
}

$filename = "Asistencia_" . preg_replace("/[^a-zA-Z0-9]/", "_", $clase['nombre_materia']) . "_" . $nombre_archivo . ".xls";
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");
echo "\xEF\xBB\xBF"; 
?>

<!-- Diseño Institucional del Excel (Más limpio, menos agresivo visualmente) -->
<table border="1" style="font-family: Arial, sans-serif; border-collapse: collapse; font-size: 12px;">
    <thead>
        <tr>
            <th colspan="<?php echo 3 + count($fechas) + 3; ?>" style="text-align: left; font-size: 16px; color: #1b4332; font-weight: bold; padding: 5px; border-bottom: none;">
                COBAEP PLANTEL 27 ZARAGOZA, PUE.
            </th>
        </tr>
        <tr>
            <th colspan="<?php echo 3 + count($fechas) + 3; ?>" style="text-align: left; font-size: 14px; font-weight: bold; padding: 5px;">
                REPORTE DE ASISTENCIA - <?php echo mb_strtoupper($clase['nombre_materia'], 'UTF-8'); ?> (<?php echo $clase['semestre']; ?>° "<?php echo $clase['nombre_grupo']; ?>")
            </th>
        </tr>
        <tr>
            <th colspan="<?php echo 3 + count($fechas) + 3; ?>" style="text-align: left; font-size: 12px; font-weight: normal; padding: 5px; color: #555;">
                <?php echo $titulo_periodo; ?>
            </th>
        </tr>
        <tr>
            <th style="background-color: #e9ecef; font-weight:bold; width: 40px; border: 1px solid #000;">NO.</th>
            <th style="background-color: #e9ecef; font-weight:bold; width: 120px; border: 1px solid #000;">MATRÍCULA</th>
            <th style="background-color: #e9ecef; font-weight:bold; width: 300px; border: 1px solid #000;">NOMBRE DEL ALUMNO</th>
            
            <?php foreach($fechas as $f): ?>
                <th style="background-color: #f8f9fa; font-weight:bold; text-align:center; width: 50px; border: 1px solid #000;"><?php echo date('d/m', strtotime($f)); ?></th>
            <?php endforeach; ?>
            
            <th style="background-color: #e9ecef; font-weight:bold; text-align:center; width: 60px; border: 1px solid #000;">ASIST</th>
            <th style="background-color: #e9ecef; font-weight:bold; text-align:center; width: 60px; border: 1px solid #000;">RET</th>
            <th style="background-color: #e9ecef; font-weight:bold; text-align:center; width: 60px; border: 1px solid #000;">FALTAS</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $count = 1;
        foreach($alumnos as $alum): 
            $tot_p = 0; $tot_r = 0; $tot_f = 0;
        ?>
            <tr>
                <td style="text-align:center; border: 1px solid #000;"><?php echo $count++; ?></td>
                <td style="border: 1px solid #000;"><?php echo htmlspecialchars($alum['matricula']); ?></td>
                <td style="border: 1px solid #000;"><?php echo htmlspecialchars($alum['apellido_paterno'] . ' ' . $alum['nombre']); ?></td>
                
                <?php foreach($fechas as $f): 
                    $est = isset($asistencias[$alum['id_alumno']][$f]) ? $asistencias[$alum['id_alumno']][$f] : '';
                    $letra = ''; 
                    $color = '#000000';
                    
                    if($est == 'Presente' || $est == 'Asistencia') { $letra = 'P'; $tot_p++; }
                    elseif($est == 'Retardo') { $letra = 'R'; $tot_r++; }
                    elseif($est == 'Falta' || $est == 'Ausente') { $letra = 'F'; $color = '#dc3545'; $tot_f++; }
                ?>
                    <td style="text-align:center; color:<?php echo $color; ?>; border: 1px solid #000;">
                        <?php echo $letra; ?>
                    </td>
                <?php endforeach; ?>

                <td style="text-align:center; font-weight:bold; border: 1px solid #000;"><?php echo $tot_p; ?></td>
                <td style="text-align:center; font-weight:bold; border: 1px solid #000;"><?php echo $tot_r; ?></td>
                <td style="text-align:center; font-weight:bold; color:#dc3545; border: 1px solid #000;"><?php echo $tot_f; ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>