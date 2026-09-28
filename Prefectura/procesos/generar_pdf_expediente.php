<?php
session_start();
require_once '../../config/database.php';
// Asegúrate de requerir el autoload de Dompdf (ajusta la ruta si es necesario)
require_once '../../vendor/autoload.php'; 

use Dompdf\Dompdf;
use Dompdf\Options;

if (!isset($_GET['id_alumno'])) {
    die("ID no proporcionado");
}
$id_alumno = intval($_GET['id_alumno']);

// 1. Consultar Alumno
$stmt_al = $pdo->prepare("SELECT * FROM alumnos WHERE id_alumno = :id");
$stmt_al->execute([':id' => $id_alumno]);
$alumno = $stmt_al->fetch(PDO::FETCH_ASSOC);

if(!$alumno) die("Alumno no encontrado");

// 2. Consultar Historial
$stmt_rep = $pdo->prepare("
    SELECT r.fecha_hora, tf.descripcion_falta, r.puntos_descontados, r.observaciones
    FROM reportes r
    INNER JOIN tipos_falta tf ON r.id_tipo_falta = tf.id_tipo_falta
    WHERE r.id_alumno = :id ORDER BY r.fecha_hora ASC
");
$stmt_rep->execute([':id' => $id_alumno]);
$reportes = $stmt_rep->fetchAll(PDO::FETCH_ASSOC);

// 3. Estructurar el HTML basado en el formato físico de COBAEP
$html = '
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; color: #000; }
        .title-header { text-align: center; font-weight: bold; font-size: 13px; text-transform: uppercase; margin-bottom: 25px; }
        
        /* Tabla de Información Personal */
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-table td { padding: 6px 0; border-bottom: 1px solid #000; }
        .label { font-weight: bold; width: 160px; display: inline-block; }
        
        .disclaimer { text-align: justify; font-size: 10px; font-weight: bold; margin-bottom: 20px; border: 1px solid #000; padding: 10px; }
        
        /* Tabla de Faltas */
        .faltas-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; text-align: center; }
        .faltas-table th, .faltas-table td { border: 1px solid #000; padding: 6px; }
        .faltas-table th { background-color: #f2f2f2; font-size: 10px; }
        
        .sanciones-caja { border: 1px solid #000; padding: 10px; margin-bottom: 40px; font-size: 10px; }
        
        .firmas { text-align: center; margin-top: 50px; }
        .linea-firma { width: 250px; border-bottom: 1px solid #000; margin: 0 auto 5px auto; }
    </style>
</head>
<body>

    <div class="title-header">
        EXPEDIENTE DE DISCIPLINA PLANTEL 27, ZARAGOZA, PUEBLA.
    </div>

    <!-- Datos del Alumno y Tutor -->
    <table class="info-table">
        <tr>
            <td colspan="2"><span class="label">Nombre del estudiante:</span> ' . mb_strtoupper($alumno['apellido_paterno'] . ' ' . $alumno['nombre']) . '</td>
            <td><span class="label" style="width:120px;">Grupo y semestre:</span> ' . mb_strtoupper($alumno['semestre']) . '</td>
        </tr>
        <tr>
            <td colspan="3"><span class="label">Nombre del tutor:</span> ___________________________________________________________</td>
        </tr>
        <tr>
            <td><span class="label">Teléfono del tutor:</span> ___________________</td>
            <td colspan="2"><span class="label" style="width:130px;">Teléfono del alumno:</span> ' . ($alumno['telefono'] ?? '___________________') . '</td>
        </tr>
        <tr>
            <td colspan="3"><span class="label">Dirección:</span> __________________________________________________________________________</td>
        </tr>
    </table>

    <div class="disclaimer">
        Cada alumno tendrá 100 puntos de disciplina durante el semestre, que deberá preservar con base a su comportamiento y disciplina, por cada falta al reglamento escolar será sancionado de acuerdo a la siguiente tabla.
    </div>

    <!-- Historial de Faltas -->
    <table class="faltas-table">
        <thead>
            <tr>
                <th style="width: 12%;">Fecha</th>
                <th style="width: 43%;">Faltas al reglamento</th>
                <th style="width: 10%;">Sanción</th>
                <th style="width: 15%;">Puntos</th>
                <th style="width: 20%;">Firma del Alumno</th>
            </tr>
        </thead>
        <tbody>';

        if (empty($reportes)) {
            $html .= '<tr><td colspan="5" style="padding: 15px;">Sin reportes disciplinarios registrados.</td></tr>';
        } else {
            foreach ($reportes as $rep) {
                $html .= '<tr>
                    <td>' . date('d/m/Y', strtotime($rep['fecha_hora'])) . '</td>
                    <td style="text-align: left; font-size: 9px;">' . htmlspecialchars($rep['descripcion_falta']) . '</td>
                    <td style="font-size: 9px;">Puntos menos</td>
                    <td style="color: red; font-weight: bold;">-' . $rep['puntos_descontados'] . '</td>
                    <td></td>
                </tr>';
            }
        }

$html .= '
        </tbody>
    </table>

    <!-- Reglas Acumulativas -->
    <div class="sanciones-caja">
        <strong>SANCIONES ACUMULATIVAS:</strong><br><br>
        2.- A los 25 puntos menos se llamará al padre de familia o tutor y se firmará carta compromiso.<br>
        3.- A los 40 puntos menos, se llamará al padre de familia o tutor y se firmará una carta de condicionamiento.<br>
        4.- Suspensión temporal.<br>
        6.- Suspensión definitiva.
    </div>

    <!-- Sección de Firmas -->
    <div class="firmas">
        <div class="linea-firma"></div>
        <strong>FIRMA DE ENTERADO TUTOR</strong><br><br><br>
        FECHA DE VISITA AL PLANTEL: ______ / ______ / 20____
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

// Descargar automáticamente
$nombre = 'Expediente_' . $alumno['matricula'] . '.pdf';
$dompdf->stream($nombre, ["Attachment" => true]);
?>