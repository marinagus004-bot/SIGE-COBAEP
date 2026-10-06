<?php
session_start();
require_once '../../config/database.php';
require_once '../../vendor/autoload.php'; 

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_GET['id_alumno'])) {
    die("ID no proporcionado");
}
$id_alumno = intval($_GET['id_alumno']);

// 1. Consultar Alumno
$stmt_al = $pdo->prepare("
    SELECT a.*, g.semestre, g.nombre_grupo, g.turno 
    FROM alumnos a
    LEFT JOIN grupo_alumno ga ON a.id_alumno = ga.id_alumno
    LEFT JOIN grupos g ON ga.id_grupo = g.id_grupo
    WHERE a.id_alumno = :id
");
$stmt_al->execute([':id' => $id_alumno]);
$alumno = $stmt_al->fetch(PDO::FETCH_ASSOC);

if(!$alumno) die("Alumno no encontrado");

// 2. Consultar Historial Disciplinario
$stmt_rep = $pdo->prepare("
    SELECT r.fecha_hora, tf.descripcion_falta, tf.numero_reglamento, r.puntos_descontados, r.observaciones
    FROM reportes r
    INNER JOIN tipos_falta tf ON r.id_tipo_falta = tf.id_tipo_falta
    WHERE r.id_alumno = :id 
    ORDER BY r.fecha_hora ASC
");
$stmt_rep->execute([':id' => $id_alumno]);
$reportes = $stmt_rep->fetchAll(PDO::FETCH_ASSOC);

// Variables de diseño
$grupo_str = !empty($alumno['semestre']) ? $alumno['semestre'] . '° "' . $alumno['nombre_grupo'] . '"' : 'Sin asignar';
$turno_str = !empty($alumno['turno']) ? $alumno['turno'] : 'N/A';
$nombre_completo = mb_strtoupper($alumno['apellido_paterno'] . ' ' . $alumno['nombre']);
$puntos_actuales = $alumno['puntos_conducta'] ?? 100;
$fecha_impresion = date('d/m/Y h:i A');

// ==========================================
// PROCESAR LA IMAGEN A BASE64 PARA DOMPDF
// ==========================================
$ruta_logo = __DIR__ . '/../../img/logo-repor.png';
$logo_base64 = '';
$mensaje_error = '';

if (file_exists($ruta_logo)) {
    $tipo = pathinfo($ruta_logo, PATHINFO_EXTENSION);
    $datos = file_get_contents($ruta_logo);
    $logo_base64 = 'data:image/' . $tipo . ';base64,' . base64_encode($datos);
} else {
    $mensaje_error = '<span style="color:red; font-size:10px;">[Error de imagen. Buscando en: ' . $ruta_logo . ']</span>';
}

// 3. Estructurar el HTML
$html = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: "Helvetica", "Arial", sans-serif; font-size: 12px; color: #333; margin: 0; padding: 0; }
        
        /* Ajustes de espacio en el encabezado */
        .header-table { width: 100%; border-bottom: 3px solid #063f2b; padding-bottom: 20px; margin-bottom: 30px; }
        .header-table td { vertical-align: middle; }
        
        /* Ajustes tipográficos para mayor elegancia */
        .header-text { text-align: center; }
        .header-text h1 { font-size: 24px; color: #063f2b; margin: 0; text-transform: uppercase; font-weight: 800; letter-spacing: 1.5px; }
        .header-text h2 { font-size: 13px; color: #475569; margin: 8px 0 0 0; text-transform: uppercase; letter-spacing: 2px; }
        .header-text p { font-size: 10px; color: #64748b; margin: 8px 0 0 0; font-weight: bold; }
        
        .info-box { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 25px; }
        .info-table { width: 100%; border-collapse: collapse; }
        .info-table td { padding: 6px; font-size: 12px; vertical-align: top; }
        .label { font-weight: bold; color: #063f2b; display: block; font-size: 10px; text-transform: uppercase; margin-bottom: 2px; }
        .value { font-size: 13px; font-weight: bold; border-bottom: 1px solid #cbd5e1; padding-bottom: 2px; display: block; }
        
        .puntos-badge { background-color: #063f2b; color: #fff; padding: 10px; text-align: center; border-radius: 6px; font-size: 18px; font-weight: bold; }
        
        .section-title { font-size: 14px; font-weight: bold; color: #063f2b; margin-bottom: 10px; text-transform: uppercase; border-bottom: 1px solid #063f2b; display: inline-block; padding-bottom: 3px; }
        
        .faltas-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .faltas-table th { background-color: #063f2b; color: #ffffff; padding: 10px; text-align: left; font-size: 11px; text-transform: uppercase; }
        .faltas-table td { border-bottom: 1px solid #e2e8f0; padding: 10px; font-size: 11px; vertical-align: top; }
        .faltas-table tr:nth-child(even) { background-color: #f8fafc; }
        
        .pts-rojo { color: #dc2626; font-weight: bold; font-size: 12px; }
        
        .reglamento-box { border: 1px solid #cbd5e1; padding: 15px; margin-bottom: 40px; font-size: 10px; color: #475569; text-align: justify; line-height: 1.4; background-color: #f1f5f9; border-radius: 6px; }
        .reglamento-box strong { color: #0f172a; }
        
        .firmas-container { width: 100%; margin-top: 50px; text-align: center; }
        .firmas-table { width: 100%; border-collapse: collapse; }
        .firmas-table td { width: 33.33%; padding-top: 60px; text-align: center; vertical-align: bottom; }
        .linea-firma { border-top: 1px solid #000; margin: 0 20px; padding-top: 5px; font-weight: bold; font-size: 11px; text-transform: uppercase; }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <!-- El ancho cambió a 22% y la imagen a 160px -->
            <td style="width: 22%; text-align: left; vertical-align: middle;">';
            
            if (!empty($logo_base64)) {
                $html .= '<img src="' . $logo_base64 . '" style="width: 160px; height: auto;">';
            } else {
                $html .= $mensaje_error;
            }
            
$html .= '  </td>
            <td style="width: 78%; text-align: center; vertical-align: middle;" class="header-text">
                <h1>Expediente de Disciplina</h1>
                <h2>Plantel 27 · Zaragoza, Puebla</h2>
                <p>FECHA DE EMISIÓN: ' . $fecha_impresion . '</p>
            </td>
        </tr>
    </table>

    <div class="info-box">
        <table class="info-table">
            <tr>
                <td colspan="2" style="width: 70%;">
                    <span class="label">Nombre del Estudiante</span>
                    <span class="value">' . $nombre_completo . '</span>
                </td>
                <td rowspan="2" style="width: 30%; text-align: center; vertical-align: middle;">
                    <div style="font-size: 10px; font-weight: bold; color: #64748b; margin-bottom: 5px; text-transform: uppercase;">Puntos de Conducta</div>
                    <div class="puntos-badge">' . $puntos_actuales . ' / 100</div>
                </td>
            </tr>
            <tr>
                <td style="width: 35%;">
                    <span class="label">Matrícula</span>
                    <span class="value">' . htmlspecialchars($alumno['matricula']) . '</span>
                </td>
                <td style="width: 35%;">
                    <span class="label">Semestre, Grupo y Turno</span>
                    <span class="value">' . $grupo_str . ' · ' . $turno_str . '</span>
                </td>
            </tr>
        </table>
    </div>

    <div class="section-title">Historial de Reportes e Incidencias</div>

    <table class="faltas-table">
        <thead>
            <tr>
                <th style="width: 15%;">Fecha y Hora</th>
                <th style="width: 35%;">Regla Infringida</th>
                <th style="width: 40%;">Observaciones / Detalles</th>
                <th style="width: 10%; text-align: center;">Pts.</th>
            </tr>
        </thead>
        <tbody>';

        if (empty($reportes)) {
            $html .= '<tr><td colspan="4" style="text-align: center; padding: 20px; color: #16a34a; font-weight: bold;">El alumno mantiene un expediente limpio. No hay incidencias disciplinarias registradas.</td></tr>';
        } else {
            foreach ($reportes as $rep) {
                $html .= '<tr>
                    <td>' . date('d/m/Y', strtotime($rep['fecha_hora'])) . '<br><span style="color:#64748b; font-size:10px;">' . date('h:i A', strtotime($rep['fecha_hora'])) . '</span></td>
                    <td><strong>Regla ' . $rep['numero_reglamento'] . ':</strong><br>' . htmlspecialchars($rep['descripcion_falta']) . '</td>
                    <td style="font-style: italic; color: #475569;">' . (!empty($rep['observaciones']) ? htmlspecialchars($rep['observaciones']) : 'Sin detalles adicionales.') . '</td>
                    <td style="text-align: center;" class="pts-rojo">-' . $rep['puntos_descontados'] . '</td>
                </tr>';
            }
        }

$html .= '
        </tbody>
    </table>

    <div class="reglamento-box">
        <strong>SOBRE LA DISCIPLINA Y SANCIONES ACUMULATIVAS:</strong><br><br>
        Todo alumno cuenta con 100 puntos de disciplina durante el semestre, los cuales deberá preservar con base en su buen comportamiento. Por cada falta al reglamento escolar oficial, será sancionado restando puntos de su expediente.
        <br><br>
        <strong>Medidas Disciplinarias:</strong><br>
        • A los <strong>25 puntos menos (75 pts restantes)</strong>, se llamará al padre de familia o tutor para firmar una CARTA COMPROMISO.<br>
        • A los <strong>40 puntos menos (60 pts restantes)</strong>, se llamará al padre de familia o tutor para firmar una CARTA DE CONDICIONAMIENTO.<br>
        • <strong>Suspensión Temporal</strong> o <strong>Suspensión Definitiva</strong> se aplicará según la gravedad de las faltas o al agotar el puntaje.
    </div>

    <div class="firmas-container">
        <table class="firmas-table">
            <tr>
                <td><div class="linea-firma">Firma del Alumno</div></td>
                <td><div class="linea-firma">Padre de Familia o Tutor</div></td>
                <td><div class="linea-firma">Prefectura / Dirección</div></td>
            </tr>
        </table>
    </div>

</body>
</html>';

// 4. Configurar y generar PDF
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$nombre_archivo = 'Expediente_' . $alumno['matricula'] . '_' . date('Ymd') . '.pdf';
$dompdf->stream($nombre_archivo, ["Attachment" => true]);
?>