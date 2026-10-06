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
        a.fecha_hora_escaneo, 
        a.estatus 
    FROM grupo_alumno ga
    INNER JOIN alumnos al ON ga.id_alumno = al.id_alumno
    LEFT JOIN asistencias a ON al.id_alumno = a.id_alumno AND DATE(a.fecha_hora_escaneo) = :fecha
    WHERE ga.id_grupo = :id_grupo
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lista Provisoria Asistencia</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; margin: 0; padding: 2rem; color: #0f172a; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
        .header-actions { display: flex; gap: 15px; margin-bottom: 20px; align-items: center; justify-content: space-between; }
        .export-btn { background-color: #10b981; color: white; border: none; padding: 0.8rem 1.5rem; border-radius: 6px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; }
        .export-btn:hover { background-color: #059669; transform: translateY(-2px); }
        .back-btn { background-color: #e2e8f0; color: #475569; padding: 0.8rem 1.5rem; border-radius: 6px; text-decoration: none; font-weight: 700; transition: 0.2s; }
        .back-btn:hover { background-color: #cbd5e1; color: #0f172a; }
        
        /* Estilos visuales de la tabla para la web (el excel tomará los estilos en línea de abajo) */
        .preview-table-container { overflow-x: auto; max-height: 65vh; border: 1px solid #e2e8f0; }
        table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; }
    </style>
</head>

<body>
    <div class="container">
        <div class="header-actions">
            <div>
                <h2 style="margin:0;">Vista Previa de Lista</h2>
                <p style="margin: 5px 0 0 0; color: #64748b;">Generando formato tipo Provisional...</p>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="monitoreo_grupos.php" class="back-btn"><i class="fas fa-arrow-left"></i> Volver</a>
                <!-- AQUÍ SE ACTUALIZARON LAS VARIABLES PARA EL NOMBRE DEL ARCHIVO -->
                <button onclick="exportarExcel('tablaAsistencia', 'Lista_Asistencia_<?php echo $semestre_titulo.$grupo_titulo.'_'.$fecha_lista; ?>')" class="export-btn">
                    <i class="fas fa-file-excel"></i> Descargar a Excel
                </button>
            </div>
        </div>

        <div class="preview-table-container">
            <?php $total_columnas = 4; ?>
            <table id="tablaAsistencia">
                <thead>
                    <tr>
                        <th colspan="<?php echo $total_columnas; ?>" style="text-align: center; font-size: 22px; color: #063f2b; font-weight: bold; padding: 12px;">
                            COBAEP PLANTEL 27 ZARAGOZA, PUE.
                        </th>
                    </tr>
                    
                    <tr>
                        <th colspan="<?php echo $total_columnas; ?>" style="text-align: center; font-size: 18px; color: #ffffff; background-color: #10b981; font-weight: bold; padding: 10px; border: 1px solid #059669;">
                            <!-- AQUÍ SE ACTUALIZARON LAS VARIABLES PARA EL TÍTULO VERDE -->
                            LISTA DE ASISTENCIA - SEMESTRE: <?php echo htmlspecialchars($semestre_titulo); ?>° GRUPO: "<?php echo htmlspecialchars($grupo_titulo); ?>"
                        </th>
                    </tr>
                    
                    <tr>
                        <th colspan="<?php echo $total_columnas; ?>" style="text-align: center; font-size: 14px; color: #475569; padding: 8px; border-bottom: 2px solid #000000;">
                            FECHA DEL REPORTE: <?php echo $fecha_legible; ?>
                        </th>
                    </tr>
                    
                    <tr>
                        <th style="border: 1px solid #000000; background-color: #f3f4f6; text-align: center; padding: 8px; width: 40px; font-weight: bold;">NO.</th>
                        <th style="border: 1px solid #000000; background-color: #f3f4f6; text-align: center; padding: 8px; width: 120px; font-weight: bold;">MATRÍCULA</th>
                        <th style="border: 1px solid #000000; background-color: #f3f4f6; text-align: left; padding: 8px; width: 350px; font-weight: bold;">NOMBRE DEL ALUMNO</th>
                        <th style="border: 1px solid #000000; background-color: #f3f4f6; text-align: center; padding: 8px; width: 100px; font-weight: bold;">ASIST.</th>
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
                            $marca_asistencia = '0'; // Por defecto Falta
                            $color_texto = '#000000';
                            $fondo_fila = '#ffffff';

                            // LÓGICA DE MARCADORES (1, 0, *)
                            if (empty($estado_str) || $estado_str === 'falta') {
                                $marca_asistencia = '0';
                                $fondo_fila = '#fff1f2'; // Rojito suave para web
                                $color_texto = '#b91c1c';
                            } elseif (strpos($estado_str, 'justificad') !== false || strpos($estado_str, 'permiso') !== false) {
                                $marca_asistencia = '*'; // Permisos o justificados
                                $fondo_fila = '#eff6ff'; // Azulito suave
                                $color_texto = '#1d4ed8';
                            } elseif ($estado_str === 'suspendido') {
                                $marca_asistencia = '-'; // Suspendidos
                                $fondo_fila = '#f1f5f9';
                            } else {
                                $marca_asistencia = '1'; // Asistencia normal o Retardo
                            }
                            
                            $nombre_completo = mb_strtoupper(htmlspecialchars($alumno['apellido_paterno'] . ' ' . $alumno['nombre']), 'UTF-8');
                        ?>
                        <tr style="background-color: <?php echo $fondo_fila; ?>;">
                            <td style="border: 1px solid #000000; text-align: center; padding: 5px;"><?php echo $contador++; ?></td>
                            <td style="border: 1px solid #000000; text-align: center; padding: 5px;"><?php echo htmlspecialchars($alumno['matricula']); ?></td>
                            <td style="border: 1px solid #000000; text-align: left; padding: 5px;"><?php echo $nombre_completo; ?></td>
                            <td style="border: 1px solid #000000; text-align: center; padding: 5px; font-weight: bold; font-size: 16px; color: <?php echo $color_texto; ?>;">
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
            
            // Convertimos la tabla a HTML (Excel lo interpreta y mantiene los bordes y colores en línea)
            let tableHTML = cloneTable.outerHTML;
            
            // Usamos BOM (\ufeff) para evitar problemas con acentos y Ñ
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