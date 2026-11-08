<?php
/**
 * Genera los avisos del estudiante a partir de información real del sistema:
 * actividades nuevas, entregas revisadas, reportes de conducta, asistencia del
 * mes, regularizaciones asignadas y suspensiones de clases.
 *
 * Cada aviso: ['fecha' => 'Y-m-d H:i:s', 'icono', 'color', 'titulo', 'texto', 'enlace']
 */
function obtener_avisos_estudiante(PDO $pdo, int $id_alumno, int $limite = 30): array {
    $avisos = [];
    $agregar = function ($fecha, $icono, $color, $titulo, $texto, $enlace = null) use (&$avisos) {
        $avisos[] = compact('fecha', 'icono', 'color', 'titulo', 'texto', 'enlace');
    };

    // Cada consulta va por separado: si una falla, las demás se siguen mostrando.
    try {
        // 1. Actividades publicadas en mis materias (últimos 30 días)
        $st = $pdo->prepare("
            SELECT ac.id_clase, ac.titulo, ac.fecha_publicacion, ac.fecha_apertura, ac.fecha_limite, m.nombre_materia
            FROM ACTIVIDADES_CLASE ac
            INNER JOIN CLASE_REGULAR_ALUMNO cra ON cra.id_clase = ac.id_clase AND cra.id_alumno = ? AND cra.acceso = 1
            INNER JOIN CLASES_REGULARES c ON c.id_clase = ac.id_clase AND c.estatus = 'Activa'
            INNER JOIN MATERIAS m ON m.id_materia = c.id_materia
            WHERE ac.visible = 1
              AND (ac.fecha_apertura IS NULL OR ac.fecha_apertura <= NOW())
              AND COALESCE(ac.fecha_apertura, ac.fecha_publicacion) >= NOW() - INTERVAL 30 DAY
        ");
        $st->execute([$id_alumno]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $limite_txt = $r['fecha_limite'] ? ' Fecha límite: ' . date('d/m/Y h:i a', strtotime($r['fecha_limite'])) . '.' : '';
            $agregar($r['fecha_apertura'] ?: $r['fecha_publicacion'], 'fas fa-book-open', 'blue',
                'Nueva actividad en ' . $r['nombre_materia'], '"' . $r['titulo'] . '".' . $limite_txt,
                'aula_virtual.php?id=' . (int)$r['id_clase']);
        }
    } catch (PDOException $e) { error_log('Avisos/actividades: ' . $e->getMessage()); }

    try {
        // 2. Entregas revisadas por el docente
        $st = $pdo->prepare("
            SELECT ea.fecha_entrega, ea.estatus_entrega, ea.retroalimentacion, ac.titulo, ac.id_clase
            FROM ENTREGAS_ACTIVIDAD ea
            INNER JOIN ACTIVIDADES_CLASE ac ON ac.id_actividad = ea.id_actividad
            WHERE ea.id_alumno = ? AND ea.retroalimentacion IS NOT NULL AND ea.retroalimentacion <> ''
            ORDER BY ea.fecha_entrega DESC LIMIT 10
        ");
        $st->execute([$id_alumno]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $agregar($r['fecha_entrega'], 'fas fa-comment-dots', 'green', 'Tu docente revisó "' . $r['titulo'] . '"',
                'Estatus: ' . $r['estatus_entrega'] . '. Comentario: ' . $r['retroalimentacion'], 'aula_virtual.php?id=' . (int)$r['id_clase']);
        }
    } catch (PDOException $e) { error_log('Avisos/entregas: ' . $e->getMessage()); }

    try {
        // 3. Reportes de conducta (últimos 60 días)
        $st = $pdo->prepare("
            SELECT r.fecha_hora, r.puntos_descontados, r.puntos_restantes, tf.numero_reglamento, tf.descripcion_falta
            FROM REPORTES r INNER JOIN TIPOS_FALTA tf ON tf.id_tipo_falta = r.id_tipo_falta
            WHERE r.id_alumno = ? AND r.fecha_hora >= NOW() - INTERVAL 60 DAY
        ");
        $st->execute([$id_alumno]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $agregar($r['fecha_hora'], 'fas fa-triangle-exclamation', 'red', 'Reporte de conducta (Art. ' . (int)$r['numero_reglamento'] . ')',
                mb_strimwidth($r['descripcion_falta'], 0, 140, '…') . ' Se descontaron ' . (int)$r['puntos_descontados'] . ' puntos; te quedan ' . (int)$r['puntos_restantes'] . '.',
                'reglamentos.php');
        }
    } catch (PDOException $e) { error_log('Avisos/reportes: ' . $e->getMessage()); }

    try {
        // 4. Resumen de asistencia del mes (solo si hay retardos o faltas)
        $st = $pdo->prepare("
            SELECT SUM(estatus = 'Retardo') AS retardos, SUM(estatus = 'Falta') AS faltas, MAX(fecha_hora_escaneo) AS ultima
            FROM ASISTENCIAS
            WHERE id_alumno = ? AND tipo_registro = 'Entrada'
              AND YEAR(fecha_hora_escaneo) = YEAR(CURDATE()) AND MONTH(fecha_hora_escaneo) = MONTH(CURDATE())
        ");
        $st->execute([$id_alumno]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        if ($r && ((int)$r['retardos'] > 0 || (int)$r['faltas'] > 0)) {
            $agregar($r['ultima'], 'far fa-calendar-xmark', 'amber', 'Tu asistencia de este mes',
                'Llevas ' . (int)$r['retardos'] . ' retardo(s) y ' . (int)$r['faltas'] . ' falta(s) sin justificar este mes.', 'mis_asistencias.php');
        }
    } catch (PDOException $e) { error_log('Avisos/asistencia: ' . $e->getMessage()); }

    try {
        // 5. Regularizaciones asignadas y activas
        $st = $pdo->prepare("
            SELECT c.id_clase, c.nombre_materia, c.fecha_inicio, c.fecha_fin, ca.fecha_asignacion
            FROM CLASE_ALUMNO ca INNER JOIN CLASES_REGULARIZACION c ON c.id_clase = ca.id_clase
            WHERE ca.id_alumno = ? AND c.estatus = 'Activa'
        ");
        $st->execute([$id_alumno]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $periodo = ($r['fecha_inicio'] && $r['fecha_fin']) ? ' del ' . date('d/m/Y', strtotime($r['fecha_inicio'])) . ' al ' . date('d/m/Y', strtotime($r['fecha_fin'])) : '';
            $agregar($r['fecha_asignacion'] . ' 08:00:00', 'fas fa-star', 'amber', 'Regularización asignada: ' . $r['nombre_materia'],
                'Fuiste inscrito(a) al curso de regularización' . $periodo . '. Revisa los materiales y entregas.', 'espacio_regularizacion.php?id=' . (int)$r['id_clase']);
        }
    } catch (PDOException $e) { error_log('Avisos/regularizaciones: ' . $e->getMessage()); }

    try {
        // 6. Suspensiones de clases próximas o en curso
        $st = $pdo->query("SELECT motivo, fecha_inicio, fecha_fin FROM SUSPENSIONES_CLASES WHERE fecha_fin >= CURDATE() ORDER BY fecha_inicio LIMIT 5");
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $rango = $r['fecha_inicio'] === $r['fecha_fin'] ? 'el ' . date('d/m/Y', strtotime($r['fecha_inicio']))
                   : 'del ' . date('d/m/Y', strtotime($r['fecha_inicio'])) . ' al ' . date('d/m/Y', strtotime($r['fecha_fin']));
            $agregar(date('Y-m-d H:i:s'), 'fas fa-school-circle-xmark', 'gray', 'Suspensión de clases: ' . $r['motivo'], 'No habrá clases ' . $rango . '.');
        }
    } catch (PDOException $e) { error_log('Avisos/suspensiones: ' . $e->getMessage()); }

    usort($avisos, fn($a, $b) => strcmp((string)$b['fecha'], (string)$a['fecha']));
    return array_slice($avisos, 0, $limite);
}

/** "Hace 5 minutos", "Hace 2 días", etc. */
function tiempo_relativo(?string $fecha): string {
    if (!$fecha) return '';
    $seg = time() - strtotime($fecha);
    if ($seg < 0) return 'Próximamente';
    if ($seg < 60) return 'Hace un momento';
    if ($seg < 3600) return 'Hace ' . floor($seg / 60) . ' min';
    if ($seg < 86400) return 'Hace ' . floor($seg / 3600) . ' h';
    $d = floor($seg / 86400);
    return $d == 1 ? 'Hace 1 día' : ($d < 30 ? "Hace $d días" : date('d/m/Y', strtotime($fecha)));
}
