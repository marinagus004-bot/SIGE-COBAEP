<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') { echo "Acceso denegado."; exit(); }

$id_clase = isset($_GET['id_clase']) ? (int)$_GET['id_clase'] : 0;
$fecha = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');

if($id_clase > 0) {
    $stmt = $pdo->prepare("
        SELECT a.id_alumno, a.matricula, a.nombre, a.apellido_paterno, ac.estatus
        FROM clase_regular_alumno cra 
        INNER JOIN alumnos a ON cra.id_alumno = a.id_alumno 
        LEFT JOIN asistencias_clases ac ON a.id_alumno = ac.id_alumno AND ac.id_clase = cra.id_clase AND ac.fecha = ?
        WHERE cra.id_clase = ? 
        ORDER BY a.apellido_paterno ASC
    ");
    $stmt->execute([$fecha, $id_clase]);
    $alumnos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if(count($alumnos) === 0) {
        echo '<div style="padding:20px; text-align:center; color:#64748b;">No hay alumnos inscritos.</div>'; exit;
    }

    foreach($alumnos as $alum) {
        $estatus = $alum['estatus'] ?? 'Presente';
        $chk_p = ($estatus == 'Presente' || $estatus == 'Asistencia') ? 'checked' : '';
        $chk_r = ($estatus == 'Retardo') ? 'checked' : '';
        $chk_f = ($estatus == 'Falta' || $estatus == 'Ausente') ? 'checked' : '';

        echo '
        <div class="alumno-asist-row" style="display: flex; align-items: center; justify-content: space-between; padding: 12px 15px; border-bottom: 1px solid var(--line); transition: 0.2s;">
            <div style="line-height: 1.2;">
                <strong class="asist-nombre" style="display:block; font-size: 0.9rem; color: var(--ink);">'.htmlspecialchars($alum['apellido_paterno'].' '.$alum['nombre']).'</strong>
                <span class="asist-mat" style="font-size: 0.75rem; color: var(--muted);">Mat: '.htmlspecialchars($alum['matricula']).'</span>
            </div>
            
            <div style="display: flex; gap: 8px;">
                <label>
                    <input type="radio" name="asistencia['.$alum['id_alumno'].']" value="Presente" class="radio-input" '.$chk_p.'>
                    <span class="radio-label radio-p" title="Presente">P</span>
                </label>
                <label>
                    <input type="radio" name="asistencia['.$alum['id_alumno'].']" value="Retardo" class="radio-input" '.$chk_r.'>
                    <span class="radio-label radio-r" title="Retardo">R</span>
                </label>
                <label>
                    <input type="radio" name="asistencia['.$alum['id_alumno'].']" value="Falta" class="radio-input" '.$chk_f.'>
                    <span class="radio-label radio-f" title="Falta">F</span>
                </label>
            </div>
        </div>';
    }
}
?>