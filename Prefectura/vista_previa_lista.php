<?php
session_start();
require_once '../config/database.php'; 

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'prefectos') {
    header("Location: ../login.php");
    exit();
}

// Recibir los parámetros del formulario
$fecha_lista = $_GET['fecha_lista'] ?? date('Y-m-d');
$id_grupo_seleccionado = $_GET['id_grupo'] ?? '';

// Convertir fecha a formato legible
$fecha_legible = date('d/m/Y', strtotime($fecha_lista));

// 1. Obtener los datos del grupo seleccionado (Semestre y Nombre) para los títulos
$semestre_titulo = "N/A";
$grupo_titulo = "N/A";
try {
    $stmt_info = $pdo->prepare("SELECT semestre, nombre_grupo FROM grupos WHERE id_grupo = :id");
    $stmt_info->execute([':id' => $id_grupo_seleccionado]);
    if ($info = $stmt_info->fetch(PDO::FETCH_ASSOC)) {
        $semestre_titulo = $info['semestre'];
        $grupo_titulo = $info['nombre_grupo'];
    }
} catch (PDOException $e) {}

// 2. Consulta PRINCIPAL usando grupo_alumno como puente
$query = "
    SELECT 
        al.matricula, 
        al.apellido_paterno, 
        al.nombre, 
        MAX(a.fecha_hora_escaneo) as fecha_hora_escaneo, 
        MAX(a.estatus) as estatus 
    FROM grupo_alumno ga
    INNER JOIN alumnos al ON ga.id_alumno = al.id_alumno
    LEFT JOIN asistencias a ON al.id_alumno = a.id_alumno AND DATE(a.fecha_hora_escaneo) = :fecha
    WHERE ga.id_grupo = :id_grupo
    GROUP BY al.id_alumno, al.matricula, al.apellido_paterno, al.nombre
    ORDER BY al.apellido_paterno ASC, al.nombre ASC
";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':fecha' => $fecha_lista,
        ':id_grupo' => $id_grupo_seleccionado
    ]);
    $lista_alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $lista_alumnos = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Etiqueta crucial para responsividad -->
    <title>Lista Provisoria Asistencia - SIGE COBAEP</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <style>
        /* Diseño institucional, sobrio y responsivo */
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background-color: #f0f2f5; margin: 0; padding: 20px; color: #333; }
        .container { max-width: 900px; margin: 0 auto; background: #fff; border: 1px solid #ccc; border-top: 4px solid #1b4332; padding: 25px; }
        
        /* Cabecera */
        .header-actions { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 20px; }
        .header-title h2 { margin: 0; font-size: 22px; color: #1b4332; font-weight: normal; }
        .header-title p { margin: 5px 0 0 0; color: #555; font-size: 14px; font-weight: bold; }
        
        .btn-group { display: flex; gap: 10px; }
        .back-btn { background: #e9ecef; color: #333; padding: 8px 15px; text-decoration: none; font-size: 13px; border: 1px solid #ccc; border-radius: 2px; }
        .back-btn:hover { background: #d3d9df; }
        
        .export-btn { background: #1b4332; color: white; border: none; padding: 8px 15px; font-size: 13px; cursor: pointer; border-radius: 2px; font-weight: bold; display: flex; align-items: center; gap: 5px; }
        .export-btn:hover { background: #112d21; }

        /* Contenedor de la tabla responsivo */
        .preview-table-container { overflow-x: auto; width: 100%; margin-top: 10px; }
        table { border-collapse: collapse; width: 100%; min-width: 600px; font-family: Arial, sans-serif; font-size: 12px; }

        /* MEDIA QUERIES PARA CELULARES (Responsividad) */
        @media (max-width: 600px) {
            body { padding: 10px; }
            .container { padding: 15px; }
            .header-actions { flex-direction: column; align-items: flex-start; gap: 15px; }
            .btn-group { width: 100%; flex-direction: column; }
            .export-btn, .back-btn { width: 100%; justify-content: center; text-align: center; }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header-actions">
            <div class="header-title">
                <h2>Vista Previa de Lista</h2>
                <p>Formato de Asistencia Provisional</p>
            </div>
            <div class="btn-group">
                <a href="monitoreo_grupos.php" class="back-btn"><i class="fas fa-arrow-left"></i> Regresar</a>
                <button onclick="exportarExcel('tablaAsistencia', 'Lista_Asistencia_<?php echo $semestre_titulo.$grupo_titulo.'_'.$fecha_lista; ?>')" class="export-btn">
                    <i class="fas fa-file-excel"></i> Descargar XLS
                </button>
            </div>
        </div>

        <div class="preview-table-container">
            <?php $total_columnas = 4; ?>
            <!-- Los estilos en línea se mantienen limpios para que el Excel salga formal -->
            <table id="tablaAsistencia" border="1" style="font-family: Arial, sans-serif; border-collapse: collapse; font-size: 12px;">
                <thead>
                    <tr>
                        <th colspan="<?php echo $total_columnas; ?>" style="text-align: left; font-size: 16px; color: #1b4332; font-weight: bold; padding: 5px; border-bottom: none; border: 1px solid #000;">
                            COBAEP PLANTEL 27 ZARAGOZA, PUE.
                        </th>
                    </tr>
                    
                    <tr>
                        <th colspan="<?php echo $total_columnas; ?>" style="text-align: left; font-size: 14px; font-weight: bold; padding: 5px; border: 1px solid #000;">
                            LISTA DE ASISTENCIA - SEMESTRE: <?php echo htmlspecialchars($semestre_titulo); ?>° GRUPO: "<?php echo htmlspecialchars($grupo_titulo); ?>"
                        </th>
                    </tr>
                    
                    <tr>
                        <th colspan="<?php echo $total_columnas; ?>" style="text-align: left; font-size: 12px; font-weight: normal; padding: 5px; color: #555; border: 1px solid #000;">
                            FECHA DEL REPORTE: <?php echo $fecha_legible; ?>
                        </th>
                    </tr>
                    
                    <tr>
                        <th style="background-color: #e9ecef; border: 1px solid #000000; text-align: center; padding: 8px; width: 40px; font-weight: bold;">NO.</th>
                        <th style="background-color: #e9ecef; border: 1px solid #000000; text-align: center; padding: 8px; width: 120px; font-weight: bold;">MATRÍCULA</th>
                        <th style="background-color: #e9ecef; border: 1px solid #000000; text-align: left; padding: 8px; width: 350px; font-weight: bold;">NOMBRE DEL ALUMNO</th>
                        <th style="background-color: #e9ecef; border: 1px solid #000000; text-align: center; padding: 8px; width: 100px; font-weight: bold;">ASIST.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($lista_alumnos)): ?>
                        <tr>
                            <td colspan="4" style="border: 1px solid #000000; text-align: center; padding: 20px;">No hay alumnos registrados.</td>
                        </tr>
                    <?php else: ?>
                        <?php 
                        $contador = 1;
                        foreach ($lista_alumnos as $alumno): 
                            $estado_str = strtolower($alumno['estatus'] ?? '');
                            $marca_asistencia = '0';
                            $color_texto = '#000000';

                            // LÓGICA DE MARCADORES (1, 0, *)
                            if (empty($estado_str) || $estado_str === 'falta') {
                                $marca_asistencia = '0';
                                $color_texto = '#dc3545'; // Rojo sobrio
                            } elseif (strpos($estado_str, 'justificad') !== false || strpos($estado_str, 'permiso') !== false) {
                                $marca_asistencia = '*'; 
                                $color_texto = '#0d6efd'; // Azul sobrio
                            } elseif ($estado_str === 'suspendido') {
                                $marca_asistencia = '-'; 
                                $color_texto = '#6c757d'; // Gris
                            } else {
                                $marca_asistencia = '1'; 
                                $color_texto = '#000000'; // Negro normal
                            }
                            
                            $nombre_completo = mb_strtoupper(htmlspecialchars($alumno['apellido_paterno'] . ' ' . $alumno['nombre']), 'UTF-8');
                        ?>
                        <tr>
                            <td style="border: 1px solid #000000; text-align: center; padding: 5px;"><?php echo $contador++; ?></td>
                            <td style="border: 1px solid #000000; text-align: center; padding: 5px;"><?php echo htmlspecialchars($alumno['matricula']); ?></td>
                            <td style="border: 1px solid #000000; text-align: left; padding: 5px;"><?php echo $nombre_completo; ?></td>
                            <td style="border: 1px solid #000000; text-align: center; padding: 5px; font-weight: bold; font-size: 14px; color: <?php echo $color_texto; ?>;">
                                <?php echo $marca_asistencia; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- SCRIPT PARA EXPORTAR A EXCEL MANTENIENDO EL DISEÑO -->
    <script>
        function exportarExcel(tableID, filename = '') {
            let table = document.getElementById(tableID);
            let cloneTable = table.cloneNode(true);
            
            let tableHTML = cloneTable.outerHTML;
            let blob = new Blob(['\ufeff', tableHTML], { type: 'application/vnd.ms-excel' });
            
            let url = URL.createObjectURL(blob);
            let a = document.createElement('a');
            a.href = url;
            a.download = filename ? filename + '.xls' : 'Lista_Asistencia.xls';
            
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>