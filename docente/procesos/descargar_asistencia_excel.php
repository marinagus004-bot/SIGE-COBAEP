<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') { exit(); }

$id_clase = isset($_GET['id_clase']) ? (int)$_GET['id_clase'] : 0;
$mes_filtro = isset($_GET['mes']) ? $_GET['mes'] : 'todos';

if ($id_clase === 0) exit("Clase no válida.");

// 1. Info de la materia
$stmt = $pdo->prepare("SELECT m.nombre_materia, g.nombre_grupo, g.semestre FROM clases_regulares c INNER JOIN materias m ON c.id_materia = m.id_materia LEFT JOIN grupos g ON c.id_grupo = g.id_grupo WHERE c.id_clase = ?");
$stmt->execute([$id_clase]);
$clase = $stmt->fetch(PDO::FETCH_ASSOC);

// 2. Construir el filtro del mes (Si el maestro eligió uno)
$where_fecha = "";
$params = [$id_clase];
if ($mes_filtro !== 'todos' && is_numeric($mes_filtro)) {
    $where_fecha = " AND MONTH(fecha) = ?";
    $params[] = $mes_filtro;
}

// 3. Traer los días exactos donde se pasó lista en ese periodo
$stmtFechas = $pdo->prepare("SELECT DISTINCT fecha FROM asistencias_clases WHERE id_clase = ? $where_fecha ORDER BY fecha ASC");
$stmtFechas->execute($params);
$fechas = $stmtFechas->fetchAll(PDO::FETCH_COLUMN);

// 4. Obtener Alumnos
$stmtAlumnos = $pdo->prepare("
    SELECT a.id_alumno, a.matricula, a.nombre, a.apellido_paterno
    FROM clase_regular_alumno cra
    INNER JOIN alumnos a ON cra.id_alumno = a.id_alumno
    WHERE cra.id_clase = ?
    ORDER BY a.apellido_paterno ASC
");
$stmtAlumnos->execute([$id_clase]);
$alumnos = $stmtAlumnos->fetchAll(PDO::FETCH_ASSOC);

// 5. Cruzar todas las asistencias en una matriz
$stmtAsist = $pdo->prepare("SELECT id_alumno, fecha, estatus FROM asistencias_clases WHERE id_clase = ? $where_fecha");
$stmtAsist->execute($params);
$asistencias = [];
while($row = $stmtAsist->fetch(PDO::FETCH_ASSOC)){
    $asistencias[$row['id_alumno']][$row['fecha']] = $row['estatus'];
}

// 6. Forzar la descarga como un archivo Excel (.xls)
$mes_nombre = ($mes_filtro === 'todos') ? 'Semestre' : 'Mes_'.$mes_filtro;
$filename = "Asistencia_" . preg_replace("/[^a-zA-Z0-9]/", "_", $clase['nombre_materia']) . "_" . $mes_nombre . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Este código asegura que los acentos y las Ñ se vean bien en Excel
echo "\xEF\xBB\xBF"; 
?>

<!-- Aquí diseñamos el documento de Excel (HTML interpretado por Excel) -->
<table border="1" style="font-family: Arial, sans-serif; border-collapse: collapse;">
    <thead>
        <tr>
            <th colspan="<?php echo 3 + count($fechas) + 3; ?>" style="text-align: center; font-size: 22px; color: #063f2b; font-weight: bold; padding: 12px; background-color: #f1f5f9;">
                COBAEP PLANTEL 27 ZARAGOZA, PUE.
            </th>
        </tr>
        <tr>
            <th colspan="<?php echo 3 + count($fechas) + 3; ?>" style="text-align: center; font-size: 18px; color: #ffffff; background-color: #10b981; font-weight: bold; padding: 10px;">
                REPORTE DE ASISTENCIA - MATERIA: <?php echo mb_strtoupper($clase['nombre_materia'], 'UTF-8'); ?> (<?php echo $clase['semestre']; ?>° "<?php echo $clase['nombre_grupo']; ?>")
            </th>
        </tr>
        <tr>
            <th style="background-color: #e2e8f0; font-weight:bold; width: 40px;">NO.</th>
            <th style="background-color: #e2e8f0; font-weight:bold; width: 120px;">MATRÍCULA</th>
            <th style="background-color: #e2e8f0; font-weight:bold; width: 300px;">NOMBRE DEL ALUMNO</th>
            
            <?php foreach($fechas as $f): ?>
                <th style="background-color: #e2e8f0; font-weight:bold; text-align:center; width: 60px;"><?php echo date('d/m', strtotime($f)); ?></th>
            <?php endforeach; ?>
            
            <th style="background-color: #dcfce7; color: #166534; font-weight:bold; text-align:center; width: 80px;">T. PRES</th>
            <th style="background-color: #fef9c3; color: #854d0e; font-weight:bold; text-align:center; width: 80px;">T. RET</th>
            <th style="background-color: #fee2e2; color: #991b1b; font-weight:bold; text-align:center; width: 80px;">T. FALT</th>
        </tr>
    </thead>
    <tbody>
        <?php 
        $count = 1;
        foreach($alumnos as $alum): 
            $tot_p = 0; $tot_r = 0; $tot_f = 0;
        ?>
            <tr>
                <td style="text-align:center;"><?php echo $count++; ?></td>
                <td><?php echo htmlspecialchars($alum['matricula']); ?></td>
                <td><?php echo htmlspecialchars($alum['apellido_paterno'] . ' ' . $alum['nombre']); ?></td>
                
                <?php foreach($fechas as $f): 
                    $est = isset($asistencias[$alum['id_alumno']][$f]) ? $asistencias[$alum['id_alumno']][$f] : '';
                    $letra = '-'; $color = '#94a3b8';
                    
                    if($est == 'Presente' || $est == 'Asistencia') { $letra = 'P'; $color = '#10b981'; $tot_p++; }
                    elseif($est == 'Retardo') { $letra = 'R'; $color = '#f59e0b'; $tot_r++; }
                    elseif($est == 'Falta' || $est == 'Ausente') { $letra = 'F'; $color = '#ef4444'; $tot_f++; }
                ?>
                    <td style="text-align:center; color:<?php echo $color; ?>; font-weight:bold;">
                        <?php echo $letra; ?>
                    </td>
                <?php endforeach; ?>

                <td style="text-align:center; font-weight:bold; background-color: #ecfdf5;"><?php echo $tot_p; ?></td>
                <td style="text-align:center; font-weight:bold; background-color: #fffbeb;"><?php echo $tot_r; ?></td>
                <td style="text-align:center; font-weight:bold; background-color: #fef2f2;"><?php echo $tot_f; ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>