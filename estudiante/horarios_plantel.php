<?php
require_once '../config/database.php';
require_once 'includes/layout.php';
require_once '../includes/directorio_horarios.php';

$alumno = estudiante_requerir_sesion($pdo);
try {
    $st = $pdo->prepare("SELECT id_grupo FROM GRUPO_ALUMNO WHERE id_alumno = ? AND fecha_baja IS NULL ORDER BY id_grupo DESC LIMIT 1");
    $st->execute([$alumno['id_alumno']]);
    $mi_grupo = (int)$st->fetchColumn();
    $datos = dh_cargar($pdo);
} catch (PDOException $e) {
    error_log('Horarios del plantel: ' . $e->getMessage());
    $mi_grupo = 0;
    $datos = ['maestros' => [], 'grupos' => [], 'turnos' => []];
}

estudiante_layout_inicio('Horarios del Plantel', 'Busca el horario de cualquier maestro o grupo.', 'horarios_plantel.php', $alumno);
dh_render($datos, ['mi_grupo' => $mi_grupo]);
estudiante_layout_fin();
