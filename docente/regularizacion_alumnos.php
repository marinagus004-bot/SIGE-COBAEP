<?php
/**
 * docente/regularizacion_alumnos.php
 * Lista de alumnos inscritos en un curso de regularización, con quién ya entregó,
 * a qué hora, si fue a tiempo y su calificación. Permite filtrar, calificar,
 * imprimir y exportar a CSV.
 */
session_start();
require_once '../config/database.php';
require_once __DIR__ . '/includes/topbar_docente.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../login.php");
    exit();
}
date_default_timezone_set('America/Mexico_City');
$id_docente = (int)$_SESSION['id_usuario'];
$id_clase = (int)($_GET['id'] ?? 0);

$st = $pdo->prepare("SELECT * FROM CLASES_REGULARIZACION WHERE id_clase = ? AND id_maestro = ?");
$st->execute([$id_clase, $id_docente]);
$clase = $st->fetch(PDO::FETCH_ASSOC);
if (!$clase) {
    header("Location: regularizaciones.php?error=" . urlencode('No tienes permiso para ver ese curso.'));
    exit();
}

$st = $pdo->prepare("
    SELECT a.id_alumno, a.matricula, a.nombre, a.apellido_paterno, a.apellido_materno, ca.fecha_asignacion,
           (SELECT CONCAT(g.semestre, '° ', g.nombre_grupo, ' · ', g.turno) FROM GRUPO_ALUMNO ga
              INNER JOIN GRUPOS g ON g.id_grupo = ga.id_grupo
             WHERE ga.id_alumno = a.id_alumno AND ga.fecha_baja IS NULL LIMIT 1) AS grupo,
           e.id_entrega, e.archivo_ruta, e.fecha_entrega, e.calificacion
    FROM CLASE_ALUMNO ca
    INNER JOIN ALUMNOS a ON a.id_alumno = ca.id_alumno
    LEFT JOIN ENTREGAS_REGULARIZACION e ON e.id_clase = ca.id_clase AND e.id_alumno = a.id_alumno
    WHERE ca.id_clase = ?
    ORDER BY a.apellido_paterno, a.apellido_materno, a.nombre
");
$st->execute([$id_clase]);
$alumnos = $st->fetchAll(PDO::FETCH_ASSOC);

$st = $pdo->prepare("SELECT COUNT(*) FROM ACTIVIDADES_REGULARIZACION WHERE id_clase = ?");
$st->execute([$id_clase]);
$total_materiales = (int)$st->fetchColumn();

// La entrega es "a tiempo" si llegó a más tardar el día límite (hasta las 23:59)
$limite_ts = $clase['fecha_fin'] ? strtotime($clase['fecha_fin'] . ' 23:59:59') : null;
$dias = ['', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
foreach ($alumnos as &$a) {
    $a['nombre_completo'] = trim($a['apellido_paterno'] . ' ' . $a['apellido_materno'] . ' ' . $a['nombre']);
    if ($a['id_entrega']) {
        $ts = strtotime($a['fecha_entrega']);
        $a['estado'] = ($limite_ts && $ts > $limite_ts) ? 'tarde' : 'tiempo';
        $a['cuando'] = $dias[(int)date('N', $ts)] . ' ' . date('d/m/Y', $ts);
        $a['hora'] = date('h:i a', $ts);
    } else {
        $a['estado'] = 'pendiente';
    }
}
unset($a);

$total = count($alumnos);
$entregaron = count(array_filter($alumnos, fn($a) => $a['estado'] !== 'pendiente'));
$tarde = count(array_filter($alumnos, fn($a) => $a['estado'] === 'tarde'));
$calificadas = array_values(array_filter(array_column($alumnos, 'calificacion'), fn($c) => $c !== null));
$promedio = $calificadas ? round(array_sum($calificadas) / count($calificadas), 1) : null;
$grupos = array_values(array_unique(array_filter(array_column($alumnos, 'grupo'))));
sort($grupos);
$etiquetas = ['tiempo' => 'Entregó a tiempo', 'tarde' => 'Entregó tarde', 'pendiente' => 'Sin entregar'];

// ---------- Exportar a CSV ----------
if (($_GET['exportar'] ?? '') === 'csv') {
    $nombre = 'entregas_' . preg_replace('/[^A-Za-z0-9]+/', '_', $clase['nombre_materia']) . '_' . date('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombre . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Matrícula', 'Alumno', 'Grupo', 'Estado', 'Fecha de entrega', 'Hora', 'Calificación']);
    foreach ($alumnos as $a) {
        fputcsv($out, [$a['matricula'], $a['nombre_completo'], $a['grupo'] ?? '', $etiquetas[$a['estado']],
            $a['id_entrega'] ? date('d/m/Y', strtotime($a['fecha_entrega'])) : '', $a['id_entrega'] ? date('h:i a', strtotime($a['fecha_entrega'])) : '',
            $a['calificacion'] !== null ? rtrim(rtrim($a['calificacion'], '0'), '.') : '']);
    }
    fclose($out);
    exit();
}

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#063f2b">
    <title>Alumnos y entregas · <?php echo $h($clase['nombre_materia']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="style_dashboard_docente.css?v=<?php echo time(); ?>">
    <style>
        .ra-cabecera { background: #fff; border: 1px solid var(--line); border-radius: 20px; padding: 20px 22px; box-shadow: var(--shadow-sm); display: flex; gap: 16px; align-items: center; flex-wrap: wrap; margin-bottom: 18px; }
        .ra-cabecera .ra-icono { width: 52px; height: 52px; border-radius: 16px; background: var(--green-soft); color: var(--green); display: grid; place-items: center; font-size: 1.3rem; flex-shrink: 0; }
        .ra-cabecera h2 { margin: 0 0 4px; font-size: 1.2rem; color: var(--ink); }
        .ra-meta { display: flex; gap: 8px; flex-wrap: wrap; }
        .ra-chip { font-size: 0.75rem; font-weight: 700; padding: 4px 10px; border-radius: 999px; background: #f1f5f9; color: #475569; display: inline-flex; gap: 6px; align-items: center; }
        .ra-chip.ok { background: #dcfce7; color: #166534; } .ra-chip.no { background: #fee2e2; color: #991b1b; } .ra-chip.warn { background: #fef3c7; color: #92400e; }
        .ra-acciones { margin-left: auto; display: flex; gap: 8px; flex-wrap: wrap; }
        .ra-btn { border: 1px solid var(--line); background: #fff; color: var(--ink); padding: 10px 14px; border-radius: 11px; font: 700 0.82rem 'Inter', sans-serif; text-decoration: none; display: inline-flex; gap: 7px; align-items: center; cursor: pointer; }
        .ra-btn:hover { border-color: var(--green); color: var(--green); }
        .ra-btn.primario { background: var(--green); border-color: var(--green); color: #fff; }
        .ra-btn.primario:hover { background: var(--green-800); color: #fff; }
        .ra-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 18px; }
        .ra-stat { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 14px 16px; }
        .ra-stat strong { display: block; font-size: 1.5rem; color: var(--ink); }
        .ra-stat span { font-size: 0.78rem; color: var(--muted); font-weight: 600; }
        .ra-barra { height: 6px; background: #e2e8f0; border-radius: 6px; margin-top: 8px; overflow: hidden; }
        .ra-barra span { display: block; height: 100%; background: var(--green); }
        .ra-card { background: #fff; border: 1px solid var(--line); border-radius: 20px; padding: 18px; box-shadow: var(--shadow-sm); }
        .ra-filtros { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 14px; }
        .ra-buscar { flex: 1 1 240px; position: relative; }
        .ra-buscar i { position: absolute; left: 13px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
        .ra-buscar input, .ra-select { width: 100%; padding: 11px 13px; border: 1px solid #d5dfdb; border-radius: 11px; font: 0.88rem 'Inter', sans-serif; outline: none; background: #fff; }
        .ra-buscar input { padding-left: 38px; }
        .ra-select { width: auto; }
        .ra-buscar input:focus, .ra-select:focus { border-color: var(--green); box-shadow: 0 0 0 3px rgba(22,160,93,.12); }
        .ra-segmento { display: flex; background: #f1f5f4; border-radius: 11px; padding: 4px; gap: 4px; flex-wrap: wrap; }
        .ra-segmento button { border: none; background: transparent; padding: 7px 12px; border-radius: 8px; font: 700 0.78rem 'Inter', sans-serif; color: var(--muted); cursor: pointer; }
        .ra-segmento button.activo { background: #fff; color: var(--ink); box-shadow: 0 2px 6px rgba(0,0,0,.06); }
        .ra-tabla-wrap { overflow-x: auto; border: 1px solid var(--line); border-radius: 14px; }
        .ra-tabla { width: 100%; border-collapse: collapse; font-size: 0.88rem; min-width: 760px; }
        .ra-tabla th { background: #f8fafc; text-align: left; padding: 11px 14px; font-size: 0.72rem; text-transform: uppercase; letter-spacing: .03em; color: var(--muted); border-bottom: 1px solid var(--line); }
        .ra-tabla td { padding: 12px 14px; border-bottom: 1px solid var(--line); vertical-align: middle; color: var(--ink); }
        .ra-tabla tr:last-child td { border-bottom: none; }
        .ra-tabla tr.pendiente td { background: #fffdfa; }
        .ra-tabla td small { display: block; color: var(--muted); font-size: 0.75rem; }
        .ra-num { color: #94a3b8; font-weight: 700; width: 40px; }
        .ra-estado { display: inline-flex; gap: 6px; align-items: center; padding: 5px 10px; border-radius: 999px; font-size: 0.75rem; font-weight: 700; white-space: nowrap; }
        .ra-estado.tiempo { background: #dcfce7; color: #166534; }
        .ra-estado.tarde { background: #ffedd5; color: #c2410c; }
        .ra-estado.pendiente { background: #fee2e2; color: #991b1b; }
        .ra-hora strong { display: block; }
        .ra-archivo { color: var(--blue); text-decoration: none; font-weight: 600; font-size: 0.82rem; }
        .ra-archivo:hover { text-decoration: underline; }
        .ra-calif { display: flex; gap: 6px; }
        .ra-calif input { width: 64px; padding: 7px; border: 1px solid #cbd5e1; border-radius: 8px; text-align: center; font-family: inherit; }
        .ra-calif button { width: 34px; border: none; border-radius: 8px; background: var(--green); color: #fff; cursor: pointer; }
        .ra-vacio { text-align: center; padding: 36px 16px; color: var(--muted); }
        .ra-vacio i { display: block; font-size: 1.8rem; color: #94a3b8; margin-bottom: 8px; }
        .ra-alerta { padding: 12px 16px; border-radius: 12px; margin-bottom: 16px; font-weight: 600; font-size: 0.88rem; }
        .ra-alerta.exito { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .ra-alerta.error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .ra-solo-print { display: none; }
        @media print {
            .sidebar, .sidebar-overlay, .topbar, .ra-acciones, .ra-filtros, .ra-calif button, .ra-alerta { display: none !important; }
            .main-content { margin: 0 !important; width: 100% !important; padding: 0 !important; }
            body { background: #fff !important; }
            .ra-card, .ra-cabecera, .ra-stat { box-shadow: none !important; }
            .ra-calif input { border: none; width: auto; }
            .ra-tabla tr[hidden] { display: table-row !important; }
            .ra-solo-print { display: block; margin-bottom: 10px; font-size: 0.8rem; color: #475569; }
        }
    </style>
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <button id="collapseToggle" class="collapse-toggle"><i class="fas fa-chevron-left"></i></button>
        <div class="sidebar-inner">
            <div class="brand">
                <img src="../img/LogoCobaep.png" alt="Logo" class="brand-logo">
                <div class="brand-copy"><strong>SIGE<span>-Cobaep</span></strong><small>Plantel 27</small></div>
            </div>
            <nav class="navigation">
                <span class="nav-heading">Menú principal</span>
                <a href="dashboard_docente.php" class="nav-item"><i class="fas fa-house"></i><span>Inicio</span></a>
                <span class="nav-heading">Académico</span>
                <a href="mis_grupos.php" class="nav-item"><i class="fas fa-users-rectangle"></i><span>Mis Grupos</span></a>
                <a href="horario.php" class="nav-item"><i class="far fa-calendar-alt"></i><span>Mi Horario</span></a>
                <a href="horarios_plantel.php" class="nav-item"><i class="fas fa-calendar-week"></i><span>Horarios del Plantel</span></a>
                <span class="nav-heading">Evaluación</span>
                <a href="regularizaciones.php" class="nav-item active"><i class="fas fa-book-open-reader"></i><span>Regularizaciones</span></a>
            </nav>
            <div class="sidebar-bottom">
                <div class="institution">
                    <div class="institution-icon"><i class="fas fa-building-columns"></i></div>
                    <div><strong>COBAEP Plantel 27</strong><span>Portal Docente</span></div>
                </div>
                <a href="../logout.php" class="logout"><i class="fas fa-arrow-right-from-bracket"></i><span>Cerrar Sesión</span></a>
            </div>
        </div>
    </aside>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu" id="mobileMenu"><i class="fas fa-bars"></i></button>
                <div>
                    <span class="eyebrow">REGULARIZACIÓN · DOCENTE</span>
                    <h1>Alumnos y entregas</h1>
                    <p>Quién está inscrito, quién ya entregó y a qué hora.</p>
                </div>
            </div>
            <?php docente_topbar($pdo); ?>
        </header>

        <?php if (isset($_GET['exito'])): ?><div class="ra-alerta exito"><i class="fas fa-check-circle"></i> <?php echo $h($_GET['exito']); ?></div><?php endif; ?>
        <?php if (isset($_GET['error'])): ?><div class="ra-alerta error"><i class="fas fa-exclamation-circle"></i> <?php echo $h($_GET['error']); ?></div><?php endif; ?>

        <section class="ra-cabecera">
            <div class="ra-icono"><i class="fas fa-book-open-reader"></i></div>
            <div>
                <h2><?php echo $h($clase['nombre_materia']); ?></h2>
                <div class="ra-meta">
                    <?php if ($clase['fecha_fin']): ?><span class="ra-chip"><i class="far fa-calendar"></i> Fecha límite: <?php echo date('d/m/Y', strtotime($clase['fecha_fin'])); ?> 11:59 pm</span><?php endif; ?>
                    <span class="ra-chip <?php echo (int)$clase['permitir_entregas'] === 1 ? 'ok' : 'no'; ?>"><i class="fas fa-inbox"></i> Recepción <?php echo (int)$clase['permitir_entregas'] === 1 ? 'abierta' : 'cerrada'; ?></span>
                    <?php if ((int)($clase['visible'] ?? 1) === 0): ?><span class="ra-chip warn"><i class="fas fa-eye-slash"></i> Oculto para los alumnos</span><?php endif; ?>
                    <span class="ra-chip"><i class="fas fa-layer-group"></i> <?php echo $total_materiales; ?> materiales publicados</span>
                </div>
            </div>
            <div class="ra-acciones">
                <a href="detalle_regularizacion.php?id=<?php echo $id_clase; ?>" class="ra-btn"><i class="fas fa-arrow-left"></i> Volver al curso</a>
                <button type="button" class="ra-btn" onclick="window.print()"><i class="fas fa-print"></i> Imprimir</button>
                <a href="?id=<?php echo $id_clase; ?>&exportar=csv" class="ra-btn primario"><i class="fas fa-file-csv"></i> Exportar CSV</a>
            </div>
        </section>

        <section class="ra-stats" aria-label="Resumen">
            <div class="ra-stat"><strong><?php echo $total; ?></strong><span>Alumnos inscritos</span></div>
            <div class="ra-stat"><strong><?php echo $entregaron; ?></strong><span>Ya entregaron</span>
                <div class="ra-barra"><span style="width: <?php echo $total ? round($entregaron / $total * 100) : 0; ?>%"></span></div></div>
            <div class="ra-stat"><strong><?php echo $total - $entregaron; ?></strong><span>Sin entregar</span></div>
            <div class="ra-stat"><strong><?php echo $tarde; ?></strong><span>Entregaron tarde</span></div>
            <div class="ra-stat"><strong><?php echo $promedio !== null ? $promedio : '—'; ?></strong><span>Promedio (<?php echo count($calificadas); ?> calificados)</span></div>
        </section>

        <section class="ra-card">
            <p class="ra-solo-print">Lista generada el <?php echo date('d/m/Y h:i a'); ?> · <?php echo $h($clase['nombre_materia']); ?></p>
            <?php if (!$alumnos): ?>
                <div class="ra-vacio"><i class="fas fa-user-plus"></i>Todavía no hay alumnos inscritos. Inscríbelos desde <a href="detalle_regularizacion.php?id=<?php echo $id_clase; ?>">el curso</a> con <b>Inscribir Alumno</b> o <b>Vincular Grupo</b>.</div>
            <?php else: ?>
                <div class="ra-filtros">
                    <label class="ra-buscar"><i class="fas fa-search"></i><input type="search" id="raBuscar" placeholder="Buscar por nombre o matrícula..." aria-label="Buscar alumno"></label>
                    <?php if (count($grupos) > 1): ?>
                        <select class="ra-select" id="raGrupo" aria-label="Filtrar por grupo">
                            <option value="">Todos los grupos</option>
                            <?php foreach ($grupos as $g): ?><option value="<?php echo $h($g); ?>"><?php echo $h($g); ?></option><?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                    <div class="ra-segmento" role="group" aria-label="Filtrar por estado">
                        <button type="button" class="activo" data-estado="">Todos (<?php echo $total; ?>)</button>
                        <button type="button" data-estado="entrego">Entregaron (<?php echo $entregaron; ?>)</button>
                        <button type="button" data-estado="pendiente">Sin entregar (<?php echo $total - $entregaron; ?>)</button>
                        <?php if ($tarde): ?><button type="button" data-estado="tarde">Tarde (<?php echo $tarde; ?>)</button><?php endif; ?>
                    </div>
                </div>

                <div class="ra-tabla-wrap">
                    <table class="ra-tabla">
                        <thead>
                            <tr><th>#</th><th>Alumno</th><th>Grupo</th><th>Estado</th><th>Fecha y hora de entrega</th><th>Archivo</th><th>Calificación</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alumnos as $n => $a): ?>
                                <tr class="<?php echo $a['estado'] === 'pendiente' ? 'pendiente' : ''; ?>"
                                    data-estado="<?php echo $a['estado']; ?>" data-grupo="<?php echo $h($a['grupo']); ?>"
                                    data-texto="<?php echo $h(mb_strtolower($a['nombre_completo'] . ' ' . $a['matricula'])); ?>">
                                    <td class="ra-num"><?php echo $n + 1; ?></td>
                                    <td><strong><?php echo $h($a['nombre_completo']); ?></strong><small><?php echo $h($a['matricula']); ?></small></td>
                                    <td><?php echo $h($a['grupo'] ?? '—'); ?></td>
                                    <td>
                                        <span class="ra-estado <?php echo $a['estado']; ?>">
                                            <i class="fas <?php echo ['tiempo' => 'fa-check', 'tarde' => 'fa-clock', 'pendiente' => 'fa-xmark'][$a['estado']]; ?>"></i>
                                            <?php echo $etiquetas[$a['estado']]; ?>
                                        </span>
                                    </td>
                                    <td class="ra-hora">
                                        <?php if ($a['id_entrega']): ?><strong><?php echo $a['hora']; ?></strong><small><?php echo $a['cuando']; ?></small>
                                        <?php else: ?><span style="color:#94a3b8;">—</span><?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($a['id_entrega']): ?>
                                            <a class="ra-archivo" href="../<?php echo $h($a['archivo_ruta']); ?>" target="_blank" rel="noopener"><i class="fas fa-paperclip"></i> <?php echo $h(preg_replace('/^entrega_[a-z0-9]+_\d+_/i', '', basename($a['archivo_ruta']))); ?></a>
                                        <?php else: ?><span style="color:#94a3b8;">—</span><?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($a['id_entrega']): ?>
                                            <form class="ra-calif" action="procesos/regularizacion_acciones.php" method="POST">
                                                <input type="hidden" name="accion" value="calificar_entrega">
                                                <input type="hidden" name="volver" value="alumnos">
                                                <input type="hidden" name="id_clase" value="<?php echo $id_clase; ?>">
                                                <input type="hidden" name="id_entrega" value="<?php echo (int)$a['id_entrega']; ?>">
                                                <input type="number" name="calificacion" min="0" max="10" step="0.1" placeholder="—" aria-label="Calificación de <?php echo $h($a['nombre_completo']); ?>"
                                                       value="<?php echo $a['calificacion'] !== null ? $h(rtrim(rtrim($a['calificacion'], '0'), '.')) : ''; ?>">
                                                <button type="submit" title="Guardar calificación"><i class="fas fa-check"></i></button>
                                            </form>
                                        <?php else: ?><span style="color:#94a3b8;">—</span><?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="ra-vacio" id="raSinResultados" hidden><i class="fas fa-magnifying-glass"></i>Ningún alumno coincide con los filtros.</div>
            <?php endif; ?>
        </section>
    </main>

    <script src="dashboard_maestro.js"></script>
    <script>
    (function () {
        const buscar = document.getElementById('raBuscar');
        if (!buscar) return;
        const grupo = document.getElementById('raGrupo');
        const filas = [...document.querySelectorAll('.ra-tabla tbody tr')];
        let estado = '';
        function filtrar() {
            const q = buscar.value.trim().toLowerCase(), g = grupo ? grupo.value : '';
            let n = 0;
            filas.forEach(f => {
                const okEstado = !estado || (estado === 'entrego' ? f.dataset.estado !== 'pendiente' : f.dataset.estado === estado);
                const ok = okEstado && (!q || f.dataset.texto.includes(q)) && (!g || f.dataset.grupo === g);
                f.hidden = !ok; if (ok) n++;
            });
            document.getElementById('raSinResultados').hidden = n > 0;
        }
        buscar.addEventListener('input', filtrar);
        if (grupo) grupo.addEventListener('change', filtrar);
        document.querySelectorAll('.ra-segmento button').forEach(b => b.addEventListener('click', () => {
            document.querySelectorAll('.ra-segmento button').forEach(x => x.classList.toggle('activo', x === b));
            estado = b.dataset.estado; filtrar();
        }));
    })();
    </script>
</body>
</html>
