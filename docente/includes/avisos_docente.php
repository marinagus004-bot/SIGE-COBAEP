<?php
/**
 * docente/includes/avisos_docente.php
 * Avisos y tareas del docente (se usan en el inicio y en la campana de todas sus páginas).
 */

if (!function_exists('cuando')) {
function cuando(int $ts, int $ahora): string {
    $min = (int)round(($ts - $ahora) / 60);
    if ($min < 60)  return 'en ' . max(1, $min) . ' min';
    if ($min < 360) return 'en ' . floor($min / 60) . ' h' . ($min % 60 ? ' ' . ($min % 60) . ' min' : '');
    if (date('Y-m-d', $ts) === date('Y-m-d', $ahora)) return 'hoy a las ' . date('h:i a', $ts);
    if (date('Y-m-d', $ts) === date('Y-m-d', strtotime('+1 day', $ahora))) return 'mañana a las ' . date('h:i a', $ts);
    return 'el ' . date('d/m', $ts) . ' a las ' . date('h:i a', $ts);
}
function hace(int $ts, int $ahora): string {
    $min = (int)floor(($ahora - $ts) / 60);
    if ($min < 1)    return 'Hace un momento';
    if ($min < 60)   return "Hace $min min";
    if ($min < 1440) return 'Hace ' . floor($min / 60) . ' h';
    $d = (int)floor($min / 1440);
    return $d === 1 ? 'Ayer' : "Hace $d días";
}
function agregar_aviso(array &$avisos, int $prioridad, string $tipo, string $icono, string $titulo, string $texto, string $meta, ?string $enlace = null, ?string $accion = null): void {
    $avisos[] = compact('prioridad', 'tipo', 'icono', 'titulo', 'texto', 'meta', 'enlace', 'accion');
}

}

/**
 * Devuelve ['avisos' => [...], 'clases_semana' => [...]] para el docente.
 */
function docente_avisos(PDO $pdo, int $id_docente): array {
    date_default_timezone_set('America/Mexico_City');
    $ahora = time();
    $numero_dia_hoy = (int)date('N');
    $avisos = [];
    $clases_semana = [];
    try {
        // ---------------------------------------------------
        // HORARIO DE LA SEMANA
        // ---------------------------------------------------
        $st = $pdo->prepare("
            SELECT hc.id_horario, hc.dia_semana, hc.hora_inicio, hc.hora_fin,
                   c.id_clase, c.id_grupo, m.nombre_materia, g.nombre_grupo, g.semestre, g.turno,
                   (SELECT COUNT(*) FROM ASISTENCIAS_CLASES ac WHERE ac.id_clase = c.id_clase AND ac.fecha = CURDATE()) AS lista_hoy
            FROM HORARIOS_CLASES hc
            INNER JOIN CLASES_REGULARES c ON hc.id_clase = c.id_clase
            INNER JOIN MATERIAS m ON c.id_materia = m.id_materia
            INNER JOIN GRUPOS g ON c.id_grupo = g.id_grupo
            WHERE c.id_maestro = ? AND c.estatus = 'Activa'
            ORDER BY hc.dia_semana, hc.hora_inicio
        ");
        $st->execute([$id_docente]);
        $clases_semana = $st->fetchAll(PDO::FETCH_ASSOC);

        // Pasar lista: clases de hoy que ya empezaron y no tienen asistencia registrada
        foreach ($clases_semana as $c) {
            if ((int)$c['dia_semana'] !== $numero_dia_hoy || (int)$c['lista_hoy'] > 0) continue;
            $inicio = strtotime(date('Y-m-d ') . $c['hora_inicio']);
            $fin    = strtotime(date('Y-m-d ') . $c['hora_fin']);
            if ($ahora < $inicio) continue;
            $grupo = $c['semestre'] . '° "' . $c['nombre_grupo'] . '"';
            agregar_aviso($avisos, $ahora <= $fin ? 1 : 2, 'urgente', 'fa-clipboard-user',
                $ahora <= $fin ? 'Clase en curso sin lista' : 'No pasaste lista hoy',
                "Aún no registras la asistencia de <strong>" . htmlspecialchars($c['nombre_materia']) . "</strong> · Grupo " . htmlspecialchars($grupo) . '.',
                ($ahora <= $fin ? 'Termina a las ' : 'Terminó a las ') . date('h:i a', $fin) . ' • Asistencia',
                'gestionar_aula.php?id_grupo=' . (int)$c['id_grupo'] . '&abrir_lista=' . (int)$c['id_clase'], 'Pasar lista');
        }

        // ---------------------------------------------------
        // ENTREGAS POR REVISAR (aulas virtuales)
        // ---------------------------------------------------
        $st = $pdo->prepare("
            SELECT ac.id_actividad, ac.id_clase, ac.titulo, m.nombre_materia, g.semestre, g.nombre_grupo,
                   COUNT(*) AS n, MAX(ea.fecha_entrega) AS ultima
            FROM ENTREGAS_ACTIVIDAD ea
            INNER JOIN ACTIVIDADES_CLASE ac ON ac.id_actividad = ea.id_actividad
            INNER JOIN CLASES_REGULARES c ON c.id_clase = ac.id_clase AND c.id_maestro = ?
            INNER JOIN MATERIAS m ON m.id_materia = c.id_materia
            INNER JOIN GRUPOS g ON g.id_grupo = c.id_grupo
            WHERE ea.estatus_entrega <> 'Revisada'
            GROUP BY ac.id_actividad, ac.id_clase, ac.titulo, m.nombre_materia, g.semestre, g.nombre_grupo
            ORDER BY ultima DESC LIMIT 6
        ");
        $st->execute([$id_docente]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            agregar_aviso($avisos, 3, 'tareas', 'fa-file-circle-check', 'Trabajos por revisar',
                "Tienes <strong>{$r['n']}</strong> " . ($r['n'] == 1 ? 'entrega' : 'entregas') . " sin revisar en «" . htmlspecialchars($r['titulo']) . "» · " . htmlspecialchars($r['nombre_materia']) . " {$r['semestre']}° \"" . htmlspecialchars($r['nombre_grupo']) . '".',
                hace(strtotime($r['ultima']), $ahora) . ' • Aula virtual',
                "ver_entregas.php?id_actividad={$r['id_actividad']}&id_clase={$r['id_clase']}", 'Revisar');
        }

        // ---------------------------------------------------
        // ACTIVIDADES: por cerrar, programadas y recién cerradas
        // ---------------------------------------------------
        $st = $pdo->prepare("
            SELECT ac.id_actividad, ac.id_clase, ac.titulo, ac.fecha_limite, ac.fecha_apertura, c.id_grupo,
                   m.nombre_materia, g.semestre, g.nombre_grupo,
                   (SELECT COUNT(*) FROM ENTREGAS_ACTIVIDAD ea WHERE ea.id_actividad = ac.id_actividad) AS entregadas,
                   (SELECT COUNT(*) FROM CLASE_REGULAR_ALUMNO cra WHERE cra.id_clase = ac.id_clase AND cra.acceso = 1) AS alumnos
            FROM ACTIVIDADES_CLASE ac
            INNER JOIN CLASES_REGULARES c ON c.id_clase = ac.id_clase AND c.id_maestro = ?
            INNER JOIN MATERIAS m ON m.id_materia = c.id_materia
            INNER JOIN GRUPOS g ON g.id_grupo = c.id_grupo
            WHERE ac.permite_entregas = 1 AND COALESCE(ac.visible, 1) = 1
              AND (ac.fecha_limite BETWEEN NOW() - INTERVAL 3 DAY AND NOW() + INTERVAL 3 DAY
                   OR ac.fecha_apertura BETWEEN NOW() AND NOW() + INTERVAL 7 DAY)
            ORDER BY ac.fecha_limite
        ");
        $st->execute([$id_docente]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $clase_txt = htmlspecialchars($a['nombre_materia']) . " {$a['semestre']}° \"" . htmlspecialchars($a['nombre_grupo']) . '"';
            $enlace = "gestionar_aula.php?id_clase={$a['id_clase']}&id_grupo={$a['id_grupo']}";
            $faltan = max(0, (int)$a['alumnos'] - (int)$a['entregadas']);
            $apertura = $a['fecha_apertura'] ? strtotime($a['fecha_apertura']) : null;
            $limite = $a['fecha_limite'] ? strtotime($a['fecha_limite']) : null;

            if ($apertura && $apertura > $ahora) {
                agregar_aviso($avisos, 6, 'info', 'fa-calendar-plus', 'Actividad programada',
                    "«" . htmlspecialchars($a['titulo']) . "» se abrirá a los alumnos " . cuando($apertura, $ahora) . " · $clase_txt.",
                    'Programada • Aula virtual', $enlace, 'Ver');
            } elseif ($limite && $limite > $ahora) {
                $horas = ($limite - $ahora) / 3600;
                agregar_aviso($avisos, $horas <= 24 ? 2 : 4, $horas <= 24 ? 'urgente' : 'tareas', 'fa-hourglass-half', 'Actividad por cerrar',
                    "«" . htmlspecialchars($a['titulo']) . "» cierra " . cuando($limite, $ahora) . ". Han entregado <strong>{$a['entregadas']} de {$a['alumnos']}</strong> alumnos · $clase_txt.",
                    ($faltan ? "Faltan $faltan" : 'Todos entregaron') . ' • Aula virtual', $enlace, 'Ver');
            } elseif ($limite && $faltan > 0) {
                agregar_aviso($avisos, 5, 'info', 'fa-user-clock', 'Actividad cerrada con faltantes',
                    "«" . htmlspecialchars($a['titulo']) . "» cerró con <strong>$faltan</strong> " . ($faltan == 1 ? 'alumno' : 'alumnos') . " sin entregar · $clase_txt.",
                    hace($limite, $ahora) . ' • Aula virtual', $enlace, 'Ver');
            }
        }

        // ---------------------------------------------------
        // REGULARIZACIONES: entregas sin calificar y espacios por vencer
        // ---------------------------------------------------
        $st = $pdo->prepare("
            SELECT c.id_clase, c.nombre_materia, c.fecha_fin, c.estatus, c.visible,
                   (SELECT COUNT(*) FROM ENTREGAS_REGULARIZACION e WHERE e.id_clase = c.id_clase AND e.calificacion IS NULL) AS sin_calificar,
                   (SELECT MAX(e.fecha_entrega) FROM ENTREGAS_REGULARIZACION e WHERE e.id_clase = c.id_clase AND e.calificacion IS NULL) AS ultima
            FROM CLASES_REGULARIZACION c
            WHERE c.id_maestro = ? AND c.estatus = 'Activa'
        ");
        $st->execute([$id_docente]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $enlace = "detalle_regularizacion.php?id={$r['id_clase']}";
            if ($r['sin_calificar'] > 0) {
                agregar_aviso($avisos, 3, 'tareas', 'fa-file-signature', 'Regularización por calificar',
                    "Tienes <strong>{$r['sin_calificar']}</strong> " . ($r['sin_calificar'] == 1 ? 'trabajo' : 'trabajos') . " sin calificar en «" . htmlspecialchars($r['nombre_materia']) . "».",
                    hace(strtotime($r['ultima']), $ahora) . ' • Regularización', $enlace, 'Calificar');
            }
            if ($r['fecha_fin'] && (int)$r['visible'] === 1) {
                $dias = (int)floor((strtotime($r['fecha_fin']) - strtotime(date('Y-m-d'))) / 86400);
                if ($dias < 0) {
                    agregar_aviso($avisos, 5, 'info', 'fa-flag-checkered', 'Regularización vencida',
                        "«" . htmlspecialchars($r['nombre_materia']) . "» terminó el " . date('d/m/Y', strtotime($r['fecha_fin'])) . ". Si ya concluyó, cambia su estatus a <strong>Finalizada</strong>.",
                        'Regularización', $enlace, 'Revisar');
                } elseif ($dias <= 3) {
                    agregar_aviso($avisos, 4, 'tareas', 'fa-calendar-xmark', 'Regularización por concluir',
                        "«" . htmlspecialchars($r['nombre_materia']) . "» termina " . ($dias === 0 ? 'hoy' : ($dias === 1 ? 'mañana' : "en $dias días")) . ".",
                        'Fecha límite ' . date('d/m/Y', strtotime($r['fecha_fin'])) . ' • Regularización', $enlace, 'Ver');
                }
            }
        }

        usort($avisos, fn($a, $b) => $a['prioridad'] <=> $b['prioridad']);


    } catch (PDOException $e) {
        error_log("Avisos docente: " . $e->getMessage());
    }
    return ['avisos' => $avisos, 'clases_semana' => $clases_semana];
}
