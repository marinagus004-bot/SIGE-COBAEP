<?php
/**
 * includes/directorio_horarios.php
 * "Horarios del Plantel": directorio compartido por los 4 perfiles para consultar
 * el horario de cualquier docente o grupo, y los horarios de entrada por turno.
 *
 * Uso (desde una página de cualquier módulo, ya con su sesión validada):
 *   require_once '../includes/directorio_horarios.php';
 *   $datos = dh_cargar($pdo);
 *   dh_render($datos, ['mi_grupo' => 6]);   // o ['mi_maestro' => 1]
 */

function dh_cargar(PDO $pdo): array {
    $maestros = $pdo->query("
        SELECT id_maestro, nombre, apellido_paterno, apellido_materno, titulo_academico, foto_perfil, horario_archivo
        FROM MAESTROS ORDER BY apellido_paterno, apellido_materno, nombre
    ")->fetchAll(PDO::FETCH_ASSOC);

    $grupos = $pdo->query("
        SELECT g.id_grupo, g.semestre, g.nombre_grupo, g.turno, g.capacitacion,
               (SELECT COUNT(*) FROM GRUPO_ALUMNO ga WHERE ga.id_grupo = g.id_grupo AND ga.fecha_baja IS NULL) AS alumnos
        FROM GRUPOS g
        WHERE g.estatus = 'Activo'
        ORDER BY g.semestre, g.nombre_grupo, g.turno
    ")->fetchAll(PDO::FETCH_ASSOC);

    $pdfs = $pdo->query("
        SELECT hm.id_horario, hm.id_maestro, hm.id_grupo, hm.nombre_archivo, hm.ruta_pdf, hm.fecha_subida, hm.ciclo, hm.anio
        FROM HORARIOS_MAESTRO hm ORDER BY hm.fecha_subida DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $clases = $pdo->query("
        SELECT hc.dia_semana, TIME_FORMAT(hc.hora_inicio, '%H:%i') AS ini, TIME_FORMAT(hc.hora_fin, '%H:%i') AS fin,
               c.id_maestro, c.id_grupo, m.nombre_materia AS materia
        FROM HORARIOS_CLASES hc
        INNER JOIN CLASES_REGULARES c ON c.id_clase = hc.id_clase AND c.estatus = 'Activa'
        INNER JOIN MATERIAS m ON m.id_materia = c.id_materia
        ORDER BY hc.dia_semana, hc.hora_inicio
    ")->fetchAll(PDO::FETCH_ASSOC);

    $asignaciones = $pdo->query("SELECT DISTINCT id_maestro, id_grupo FROM GRUPO_MAESTRO")->fetchAll(PDO::FETCH_ASSOC);
    $turnos = $pdo->query("SELECT turno, hora_entrada, fin_tolerancia, hora_corte FROM HORARIOS_TURNO ORDER BY hora_entrada")->fetchAll(PDO::FETCH_ASSOC);

    // Índices
    $M = []; foreach ($maestros as $m) {
        $nombre = trim($m['nombre'] . ' ' . $m['apellido_paterno'] . ' ' . $m['apellido_materno']);
        $ruta_foto = preg_replace('#^(\.\./)+#', '', (string)$m['foto_perfil']);   // acepta "../uploads/..." y "uploads/..."
        $foto = ($ruta_foto !== '' && strpos($ruta_foto, 'uploads/') === 0 && is_file(__DIR__ . '/../' . $ruta_foto)) ? $ruta_foto : null;
        $M[$m['id_maestro']] = ['id' => (int)$m['id_maestro'], 'nombre' => $nombre, 'titulo' => $m['titulo_academico'],
            'foto' => $foto, 'iniciales' => mb_strtoupper(mb_substr($m['nombre'], 0, 1) . mb_substr($m['apellido_paterno'], 0, 1)),
            'pdfs' => [], 'clases' => [], 'grupos' => []];
        // Horario general subido desde el perfil del docente (si no es uno de los ya listados por grupo)
        if (!empty($m['horario_archivo']) && is_file(__DIR__ . '/../' . $m['horario_archivo'])) {
            $M[$m['id_maestro']]['pdf_general'] = $m['horario_archivo'];
        }
    }
    $G = []; foreach ($grupos as $g) {
        $G[$g['id_grupo']] = ['id' => (int)$g['id_grupo'], 'nombre' => $g['semestre'] . '° "' . $g['nombre_grupo'] . '"',
            'semestre' => (int)$g['semestre'], 'turno' => $g['turno'], 'capacitacion' => $g['capacitacion'],
            'alumnos' => (int)$g['alumnos'], 'pdfs' => [], 'clases' => [], 'maestros' => []];
    }
    $nombre_grupo = fn($id) => isset($G[$id]) ? $G[$id]['nombre'] . ' · ' . $G[$id]['turno'] : 'Grupo';

    foreach ($pdfs as $p) {
        if (!is_file(__DIR__ . '/../' . $p['ruta_pdf'])) continue;
        $item = ['ruta' => $p['ruta_pdf'], 'archivo' => $p['nombre_archivo'], 'ciclo' => $p['ciclo'] . ' ' . $p['anio'],
                 'fecha' => date('d/m/Y', strtotime($p['fecha_subida']))];
        if (isset($M[$p['id_maestro']])) {
            $M[$p['id_maestro']]['pdfs'][] = $item + ['de' => $nombre_grupo($p['id_grupo'])];
            if (($M[$p['id_maestro']]['pdf_general'] ?? null) === $p['ruta_pdf']) unset($M[$p['id_maestro']]['pdf_general']);
        }
        if (isset($G[$p['id_grupo']])) $G[$p['id_grupo']]['pdfs'][] = $item + ['de' => $M[$p['id_maestro']]['nombre'] ?? 'Docente'];
        if (isset($M[$p['id_maestro']], $G[$p['id_grupo']])) $asignaciones[] = ['id_maestro' => $p['id_maestro'], 'id_grupo' => $p['id_grupo']];
    }
    foreach ($M as &$m) {
        if (!empty($m['pdf_general'])) array_unshift($m['pdfs'], ['ruta' => $m['pdf_general'], 'archivo' => basename($m['pdf_general']),
            'ciclo' => 'Horario general', 'fecha' => '', 'de' => 'Subido por el docente']);
        unset($m['pdf_general']);
    }
    unset($m);

    foreach ($clases as $c) {
        if (isset($M[$c['id_maestro']])) $M[$c['id_maestro']]['clases'][] = ['dia' => (int)$c['dia_semana'], 'ini' => $c['ini'], 'fin' => $c['fin'], 'materia' => $c['materia'], 'con' => $nombre_grupo($c['id_grupo'])];
        if (isset($G[$c['id_grupo']])) $G[$c['id_grupo']]['clases'][] = ['dia' => (int)$c['dia_semana'], 'ini' => $c['ini'], 'fin' => $c['fin'], 'materia' => $c['materia'], 'con' => $M[$c['id_maestro']]['nombre'] ?? 'Docente'];
        if (isset($M[$c['id_maestro']], $G[$c['id_grupo']])) $asignaciones[] = ['id_maestro' => $c['id_maestro'], 'id_grupo' => $c['id_grupo']];
    }
    foreach ($asignaciones as $a) {
        if (!isset($M[$a['id_maestro']], $G[$a['id_grupo']])) continue;
        $M[$a['id_maestro']]['grupos'][$a['id_grupo']] = $nombre_grupo($a['id_grupo']);
        $G[$a['id_grupo']]['maestros'][$a['id_maestro']] = $M[$a['id_maestro']]['nombre'];
    }
    foreach ($M as &$m) { $m['grupos'] = array_values($m['grupos']); } unset($m);
    foreach ($G as &$g) { $g['maestros'] = array_values($g['maestros']); } unset($g);

    return ['maestros' => array_values($M), 'grupos' => array_values($G), 'turnos' => $turnos];
}

function dh_render(array $datos, array $ctx = []): void {
    $h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $maestros = $datos['maestros']; $grupos = $datos['grupos']; $turnos = $datos['turnos'];
    $con_horario_m = count(array_filter($maestros, fn($m) => $m['pdfs'] || $m['clases']));
    $con_horario_g = count(array_filter($grupos, fn($g) => $g['pdfs'] || $g['clases']));
    $mi_grupo = (int)($ctx['mi_grupo'] ?? 0);
    $mi_maestro = (int)($ctx['mi_maestro'] ?? 0);
    ?>
<style>
.dh { --dh-green: var(--green, #16a05d); --dh-dark: var(--green-900, #063f2b); --dh-soft: var(--green-soft, #e9f8f0);
      --dh-ink: var(--ink, #10251d); --dh-muted: var(--muted, #6d7f77); --dh-line: var(--line, #e8efeb); font-family: 'Inter', sans-serif; color: var(--dh-ink); }
.dh *, .dh *::before, .dh *::after { box-sizing: border-box; }
.dh [hidden] { display: none !important; }
.dh-resumen { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; margin-bottom: 20px; }
.dh-stat { background: #fff; border: 1px solid var(--dh-line); border-radius: 16px; padding: 16px 18px; display: flex; gap: 14px; align-items: center; }
.dh-stat i { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; background: var(--dh-soft); color: var(--dh-green); font-size: 1.1rem; flex-shrink: 0; }
.dh-stat strong { display: block; font-size: 1.35rem; }
.dh-stat span { font-size: .8rem; color: var(--dh-muted); }
.dh-card { background: #fff; border: 1px solid var(--dh-line); border-radius: 20px; padding: 20px; box-shadow: 0 4px 18px rgba(9,48,32,.05); }
.dh-tabs { display: flex; gap: 6px; background: #f1f5f4; padding: 5px; border-radius: 14px; margin-bottom: 16px; overflow-x: auto; }
.dh-tab { flex: 1; min-width: max-content; border: none; background: transparent; padding: 10px 14px; border-radius: 10px; font: 700 .88rem 'Inter', sans-serif; color: var(--dh-muted); cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; }
.dh-tab .dh-n { background: #e2e8f0; color: #475569; border-radius: 999px; padding: 1px 8px; font-size: .72rem; }
.dh-tab.activo { background: #fff; color: var(--dh-ink); box-shadow: 0 2px 8px rgba(0,0,0,.06); }
.dh-tab.activo .dh-n { background: var(--dh-soft); color: var(--dh-green); }
.dh-tab:focus-visible, .dh-item:focus-visible, .dh-btn:focus-visible { outline: 3px solid var(--dh-green); outline-offset: 2px; }
.dh-toolbar { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
.dh-search { flex: 1 1 260px; position: relative; }
.dh-search i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8; }
.dh-search input { width: 100%; padding: 12px 14px 12px 40px; border: 1px solid #d5dfdb; border-radius: 12px; font: .92rem 'Inter', sans-serif; outline: none; background: #fff; }
.dh-search input:focus, .dh-select:focus { border-color: var(--dh-green); box-shadow: 0 0 0 3px rgba(22,160,93,.12); }
.dh-select { padding: 12px 14px; border: 1px solid #d5dfdb; border-radius: 12px; font: .88rem 'Inter', sans-serif; background: #fff; outline: none; }
.dh-check { display: flex; align-items: center; gap: 8px; font-size: .85rem; font-weight: 600; color: var(--dh-muted); padding: 0 6px; cursor: pointer; }
.dh-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(270px, 1fr)); gap: 12px; }
.dh-item { text-align: left; background: #fff; border: 1px solid var(--dh-line); border-radius: 16px; padding: 16px; cursor: pointer; display: flex; gap: 14px; align-items: flex-start; font-family: inherit; color: inherit; transition: .2s; width: 100%; }
.dh-item:hover { border-color: var(--dh-green); box-shadow: 0 8px 20px rgba(9,48,32,.07); transform: translateY(-2px); }
.dh-item.mio { border-color: var(--dh-green); background: linear-gradient(135deg, #f0fbf5, #fff 60%); }
.dh-avatar { width: 46px; height: 46px; border-radius: 14px; background: var(--dh-dark); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 800; flex-shrink: 0; overflow: hidden; }
.dh-avatar img { width: 100%; height: 100%; object-fit: cover; }
.dh-avatar.grupo { background: #eff6ff; color: #1d4ed8; font-size: .95rem; }
.dh-item-body { min-width: 0; flex: 1; }
.dh-item-body strong { display: block; font-size: .95rem; margin-bottom: 2px; }
.dh-item-body small { display: block; color: var(--dh-muted); font-size: .78rem; margin-bottom: 8px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dh-badges { display: flex; gap: 6px; flex-wrap: wrap; }
.dh-badge { font-size: .7rem; font-weight: 700; padding: 3px 8px; border-radius: 999px; background: #f1f5f9; color: #475569; display: inline-flex; gap: 4px; align-items: center; }
.dh-badge.pdf { background: #fef2f2; color: #b91c1c; }
.dh-badge.cls { background: var(--dh-soft); color: var(--dh-green); }
.dh-badge.mio { background: var(--dh-dark); color: #fff; }
.dh-badge.sin { background: #f8fafc; color: #94a3b8; }
.dh-vacio { text-align: center; padding: 40px 20px; color: var(--dh-muted); border: 1px dashed #cbd5e1; border-radius: 14px; background: #f8fafc; }
.dh-vacio i { font-size: 1.8rem; color: #94a3b8; display: block; margin-bottom: 8px; }
.dh-turnos { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 14px; }
.dh-turno { border: 1px solid var(--dh-line); border-radius: 16px; padding: 18px; }
.dh-turno h4 { margin: 0 0 12px; font-size: 1rem; display: flex; align-items: center; gap: 8px; }
.dh-turno h4 i { color: var(--dh-green); }
.dh-fila { display: flex; justify-content: space-between; padding: 9px 0; border-top: 1px dashed var(--dh-line); font-size: .88rem; }
.dh-fila span { color: var(--dh-muted); }
.dh-nota { font-size: .8rem; color: var(--dh-muted); margin: 14px 0 0; }
/* Ventana de detalle */
.dh-modal { position: fixed; inset: 0; background: rgba(15,23,42,.55); display: none; align-items: center; justify-content: center; z-index: 5000; padding: 16px; }
.dh-modal.abierto { display: flex; }
.dh-ventana { background: #fff; border-radius: 22px; width: 100%; max-width: 980px; max-height: 92vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 24px 60px rgba(0,0,0,.2); }
.dh-vhead { padding: 18px 22px; border-bottom: 1px solid var(--dh-line); display: flex; gap: 14px; align-items: center; }
.dh-vhead h3 { margin: 0; font-size: 1.15rem; }
.dh-vhead p { margin: 2px 0 0; font-size: .82rem; color: var(--dh-muted); }
.dh-cerrar { margin-left: auto; border: none; background: #f1f5f9; width: 38px; height: 38px; border-radius: 10px; cursor: pointer; color: #475569; flex-shrink: 0; }
.dh-cerrar:hover { background: #fee2e2; color: #b91c1c; }
.dh-vbody { padding: 20px 22px; overflow-y: auto; }
.dh-sec { margin-bottom: 22px; }
.dh-sec h4 { margin: 0 0 10px; font-size: .9rem; display: flex; align-items: center; gap: 8px; }
.dh-sec h4 i { color: var(--dh-green); }
.dh-chips { display: flex; gap: 6px; flex-wrap: wrap; }
.dh-chip { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; padding: 4px 10px; border-radius: 8px; font-size: .78rem; font-weight: 600; }
.dh-semana { display: grid; grid-template-columns: repeat(var(--dh-dias, 5), minmax(130px, 1fr)); gap: 8px; overflow-x: auto; }
.dh-dia { background: #f8fafc; border: 1px solid var(--dh-line); border-radius: 12px; padding: 8px; min-height: 90px; }
.dh-dia.hoy { border-color: var(--dh-green); background: #f0fbf5; }
.dh-dia-tit { font-size: .72rem; font-weight: 800; text-transform: uppercase; color: var(--dh-muted); text-align: center; margin-bottom: 8px; letter-spacing: .04em; }
.dh-dia.hoy .dh-dia-tit { color: var(--dh-green); }
.dh-bloque { background: #fff; border: 1px solid var(--dh-line); border-left: 3px solid var(--dh-green); border-radius: 8px; padding: 7px 8px; margin-bottom: 6px; font-size: .76rem; }
.dh-bloque.ahora { background: var(--dh-dark); color: #fff; border-color: var(--dh-dark); }
.dh-bloque b { display: block; font-size: .72rem; color: var(--dh-green); }
.dh-bloque.ahora b, .dh-bloque.ahora em { color: #b7f5d4; }
.dh-bloque em { display: block; font-style: normal; color: var(--dh-muted); font-size: .7rem; margin-top: 2px; }
.dh-libre { text-align: center; font-size: .72rem; color: #cbd5e1; padding-top: 14px; }
.dh-pdfs { display: flex; flex-direction: column; gap: 8px; }
.dh-pdf { display: flex; gap: 12px; align-items: center; border: 1px solid var(--dh-line); border-radius: 12px; padding: 10px 12px; }
.dh-pdf > i { color: #dc2626; font-size: 1.3rem; }
.dh-pdf div { flex: 1; min-width: 0; }
.dh-pdf strong { display: block; font-size: .85rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dh-pdf span { font-size: .74rem; color: var(--dh-muted); }
.dh-btn { border: 1px solid var(--dh-line); background: #fff; color: var(--dh-ink); padding: 8px 12px; border-radius: 9px; font: 700 .78rem 'Inter', sans-serif; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; white-space: nowrap; }
.dh-btn:hover { border-color: var(--dh-green); color: var(--dh-green); }
.dh-btn.primario { background: var(--dh-green); border-color: var(--dh-green); color: #fff; }
.dh-btn.primario:hover { background: var(--dh-dark); color: #fff; }
.dh-visor { margin-top: 12px; border: 1px solid var(--dh-line); border-radius: 12px; overflow: hidden; }
.dh-visor iframe { width: 100%; height: 60vh; border: 0; display: block; }
.dh-sin { font-size: .85rem; color: var(--dh-muted); background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 14px; text-align: center; }
@media (max-width: 640px) {
    .dh-card { padding: 14px; }
    .dh-semana { grid-template-columns: 1fr; }
    .dh-dia { min-height: 0; }
    .dh-pdf { flex-wrap: wrap; }
    .dh-vhead, .dh-vbody { padding: 14px; }
}
</style>

<div class="dh" id="dh">
    <div class="dh-resumen">
        <div class="dh-stat"><i class="fas fa-chalkboard-user"></i><div><strong><?php echo $con_horario_m; ?> / <?php echo count($maestros); ?></strong><span>Docentes con horario</span></div></div>
        <div class="dh-stat"><i class="fas fa-users-rectangle"></i><div><strong><?php echo $con_horario_g; ?> / <?php echo count($grupos); ?></strong><span>Grupos con horario</span></div></div>
        <div class="dh-stat"><i class="fas fa-door-open"></i><div><strong><?php echo count($turnos); ?></strong><span>Turnos del plantel</span></div></div>
    </div>

    <div class="dh-card">
        <div class="dh-tabs" role="tablist">
            <button type="button" class="dh-tab activo" role="tab" data-tab="docentes"><i class="fas fa-chalkboard-user"></i> Docentes <span class="dh-n"><?php echo count($maestros); ?></span></button>
            <button type="button" class="dh-tab" role="tab" data-tab="grupos"><i class="fas fa-users-rectangle"></i> Grupos <span class="dh-n"><?php echo count($grupos); ?></span></button>
            <button type="button" class="dh-tab" role="tab" data-tab="turnos"><i class="fas fa-door-open"></i> Entrada al plantel</button>
        </div>

        <div class="dh-toolbar" id="dhToolbar">
            <label class="dh-search"><i class="fas fa-search"></i><input type="search" id="dhBuscar" placeholder="Buscar docente, grupo o materia..." aria-label="Buscar"></label>
            <select class="dh-select" id="dhTurno" aria-label="Filtrar por turno">
                <option value="">Todos los turnos</option><option value="Matutino">Matutino</option><option value="Intermedio">Intermedio</option>
            </select>
            <label class="dh-check"><input type="checkbox" id="dhSoloCon"> Solo con horario</label>
        </div>

        <!-- DOCENTES -->
        <div class="dh-panel" data-panel="docentes">
            <div class="dh-grid">
                <?php foreach ($maestros as $i => $m):
                    $turnos_m = implode(' ', array_unique(array_map(fn($g) => explode(' · ', $g)[1] ?? '', $m['grupos'])));
                    $texto = mb_strtolower($m['nombre'] . ' ' . $m['titulo'] . ' ' . implode(' ', $m['grupos']) . ' ' . implode(' ', array_column($m['clases'], 'materia'))); ?>
                    <button type="button" class="dh-item<?php echo $m['id'] === $mi_maestro ? ' mio' : ''; ?>" data-tipo="maestros" data-i="<?php echo $i; ?>"
                            data-texto="<?php echo $h($texto); ?>" data-turnos="<?php echo $h($turnos_m); ?>" data-con="<?php echo ($m['pdfs'] || $m['clases']) ? 1 : 0; ?>">
                        <span class="dh-avatar"><?php if ($m['foto']): ?><img src="../<?php echo $h($m['foto']); ?>" alt=""><?php else: echo $h($m['iniciales']); endif; ?></span>
                        <span class="dh-item-body">
                            <strong><?php echo $h($m['nombre']); ?></strong>
                            <small><?php echo $h($m['titulo'] ?: ($m['grupos'] ? implode(', ', $m['grupos']) : 'Docente')); ?></small>
                            <span class="dh-badges">
                                <?php if ($m['id'] === $mi_maestro): ?><span class="dh-badge mio"><i class="fas fa-user"></i> Tú</span><?php endif; ?>
                                <?php if ($m['pdfs']): ?><span class="dh-badge pdf"><i class="fas fa-file-pdf"></i> <?php echo count($m['pdfs']); ?> PDF</span><?php endif; ?>
                                <?php if ($m['clases']): ?><span class="dh-badge cls"><i class="far fa-clock"></i> <?php echo count($m['clases']); ?> clases/sem</span><?php endif; ?>
                                <?php if ($m['grupos']): ?><span class="dh-badge"><i class="fas fa-users"></i> <?php echo count($m['grupos']); ?> grupos</span><?php endif; ?>
                                <?php if (!$m['pdfs'] && !$m['clases']): ?><span class="dh-badge sin">Sin horario registrado</span><?php endif; ?>
                            </span>
                        </span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- GRUPOS -->
        <div class="dh-panel" data-panel="grupos" hidden>
            <div class="dh-grid">
                <?php foreach ($grupos as $i => $g):
                    $texto = mb_strtolower($g['nombre'] . ' ' . $g['semestre'] . $g['turno'] . ' ' . $g['turno'] . ' ' . $g['capacitacion'] . ' ' . implode(' ', $g['maestros']) . ' ' . implode(' ', array_column($g['clases'], 'materia'))); ?>
                    <button type="button" class="dh-item<?php echo $g['id'] === $mi_grupo ? ' mio' : ''; ?>" data-tipo="grupos" data-i="<?php echo $i; ?>"
                            data-texto="<?php echo $h($texto); ?>" data-turnos="<?php echo $h($g['turno']); ?>" data-con="<?php echo ($g['pdfs'] || $g['clases']) ? 1 : 0; ?>">
                        <span class="dh-avatar grupo"><?php echo $g['semestre']; ?>°</span>
                        <span class="dh-item-body">
                            <strong>Grupo <?php echo $h($g['nombre']); ?> · <?php echo $h($g['turno']); ?></strong>
                            <small><?php echo $g['alumnos']; ?> alumnos<?php echo $g['capacitacion'] ? ' · ' . $h($g['capacitacion']) : ''; ?></small>
                            <span class="dh-badges">
                                <?php if ($g['id'] === $mi_grupo): ?><span class="dh-badge mio"><i class="fas fa-star"></i> Mi grupo</span><?php endif; ?>
                                <?php if ($g['pdfs']): ?><span class="dh-badge pdf"><i class="fas fa-file-pdf"></i> <?php echo count($g['pdfs']); ?> PDF</span><?php endif; ?>
                                <?php if ($g['clases']): ?><span class="dh-badge cls"><i class="far fa-clock"></i> <?php echo count($g['clases']); ?> clases/sem</span><?php endif; ?>
                                <?php if ($g['maestros']): ?><span class="dh-badge"><i class="fas fa-chalkboard-user"></i> <?php echo count($g['maestros']); ?> docentes</span><?php endif; ?>
                                <?php if (!$g['pdfs'] && !$g['clases']): ?><span class="dh-badge sin">Sin horario registrado</span><?php endif; ?>
                            </span>
                        </span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="dh-vacio" id="dhVacio" hidden><i class="fas fa-magnifying-glass"></i>No se encontraron resultados con esos filtros.</div>

        <!-- TURNOS -->
        <div class="dh-panel" data-panel="turnos" hidden>
            <?php if (!$turnos): ?>
                <div class="dh-vacio"><i class="fas fa-door-closed"></i>Control Escolar aún no configura los horarios de entrada.</div>
            <?php else: ?>
                <div class="dh-turnos">
                    <?php foreach ($turnos as $t): ?>
                        <div class="dh-turno">
                            <h4><i class="far fa-sun"></i> Turno <?php echo $h($t['turno']); ?></h4>
                            <div class="dh-fila"><span>Hora de entrada</span><strong><?php echo date('h:i a', strtotime($t['hora_entrada'])); ?></strong></div>
                            <div class="dh-fila"><span>Tolerancia hasta</span><strong><?php echo date('h:i a', strtotime($t['fin_tolerancia'])); ?></strong></div>
                            <div class="dh-fila"><span>Después cuenta como falta</span><strong><?php echo date('h:i a', strtotime($t['hora_corte'])); ?></strong></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="dh-nota"><i class="fas fa-circle-info"></i> Al escanear tu credencial en la entrada: antes de la tolerancia es <b>Puntual</b>, después es <b>Retardo</b> y pasada la hora de corte se registra <b>Falta</b>.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Ventana de detalle -->
    <div class="dh-modal" id="dhModal" role="dialog" aria-modal="true" aria-labelledby="dhTitulo">
        <div class="dh-ventana">
            <div class="dh-vhead">
                <span class="dh-avatar" id="dhAvatar"></span>
                <div><h3 id="dhTitulo"></h3><p id="dhSub"></p></div>
                <button type="button" class="dh-cerrar" id="dhCerrar" aria-label="Cerrar"><i class="fas fa-times"></i></button>
            </div>
            <div class="dh-vbody" id="dhCuerpo"></div>
        </div>
    </div>
</div>

<script>
(function () {
    const DATOS = <?php echo json_encode(['maestros' => $maestros, 'grupos' => $grupos], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    const HOY = <?php echo (int)date('N'); ?>;
    const AHORA = '<?php echo date('H:i'); ?>';
    const DIAS = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
    const root = document.getElementById('dh');
    const $ = s => root.querySelector(s), $$ = s => [...root.querySelectorAll(s)];
    const esc = t => { const d = document.createElement('div'); d.textContent = t == null ? '' : t; return d.innerHTML; };
    const hora12 = hm => { let [h, m] = hm.split(':').map(Number); const s = h >= 12 ? 'pm' : 'am'; h = h % 12 || 12; return h + ':' + String(m).padStart(2, '0') + ' ' + s; };
    let tab = 'docentes';

    // ---- Pestañas y filtros ----
    function filtrar() {
        const q = $('#dhBuscar').value.trim().toLowerCase();
        const turno = $('#dhTurno').value, solo = $('#dhSoloCon').checked;
        let visibles = 0;
        $$('.dh-panel[data-panel="' + tab + '"] .dh-item').forEach(it => {
            const ok = (!q || it.dataset.texto.includes(q)) && (!turno || it.dataset.turnos.includes(turno)) && (!solo || it.dataset.con === '1');
            it.hidden = !ok; if (ok) visibles++;
        });
        $('#dhVacio').hidden = tab === 'turnos' || visibles > 0;
    }
    $$('.dh-tab').forEach(b => b.addEventListener('click', () => {
        tab = b.dataset.tab;
        $$('.dh-tab').forEach(t => t.classList.toggle('activo', t === b));
        $$('.dh-panel').forEach(p => p.hidden = p.dataset.panel !== tab);
        $('#dhToolbar').hidden = tab === 'turnos';
        $('#dhBuscar').placeholder = tab === 'grupos' ? 'Buscar grupo, docente o materia...' : 'Buscar docente, grupo o materia...';
        filtrar();
    }));
    ['input', 'change'].forEach(ev => { $('#dhBuscar').addEventListener(ev, filtrar); $('#dhTurno').addEventListener(ev, filtrar); $('#dhSoloCon').addEventListener(ev, filtrar); });

    // ---- Detalle ----
    function semana(clases, etiquetaCon) {
        if (!clases.length) return '<div class="dh-sin"><i class="far fa-calendar-xmark"></i> Aún no hay clases semanales registradas en el sistema.</div>';
        const dias = [1, 2, 3, 4, 5];
        [6, 7].forEach(d => { if (clases.some(c => c.dia === d)) dias.push(d); });
        return '<div class="dh-semana" style="--dh-dias:' + dias.length + '">' + dias.map(d => {
            const del = clases.filter(c => c.dia === d);
            return '<div class="dh-dia' + (d === HOY ? ' hoy' : '') + '"><div class="dh-dia-tit">' + DIAS[d] + (d === HOY ? ' · hoy' : '') + '</div>' +
                (del.length ? del.map(c => {
                    const ahora = d === HOY && AHORA >= c.ini && AHORA < c.fin;
                    return '<div class="dh-bloque' + (ahora ? ' ahora' : '') + '"><b>' + hora12(c.ini) + ' – ' + hora12(c.fin) + (ahora ? ' · ahora' : '') + '</b>' +
                        esc(c.materia) + '<em>' + etiquetaCon + ' ' + esc(c.con) + '</em></div>';
                }).join('') : '<div class="dh-libre">Libre</div>') + '</div>';
        }).join('') + '</div>';
    }
    function pdfs(lista) {
        if (!lista.length) return '<div class="dh-sin"><i class="far fa-file"></i> No se ha subido ningún horario en PDF.</div>';
        return '<div class="dh-pdfs">' + lista.map((p, k) =>
            '<div class="dh-pdf"><i class="fas fa-file-pdf"></i><div><strong>' + esc(p.de) + '</strong><span>' + esc(p.ciclo) + (p.fecha ? ' · subido el ' + esc(p.fecha) : '') + '</span></div>' +
            '<button type="button" class="dh-btn primario" data-ver="' + k + '"><i class="far fa-eye"></i> Ver</button>' +
            '<a class="dh-btn" href="../' + esc(p.ruta) + '" target="_blank" rel="noopener" download><i class="fas fa-download"></i> Descargar</a></div>'
        ).join('') + '</div><div class="dh-visor" id="dhVisor" hidden><iframe title="Vista del horario"></iframe></div>';
    }
    function abrir(tipo, i) {
        const x = DATOS[tipo][i];
        const avatar = $('#dhAvatar');
        let cuerpo = '';
        if (tipo === 'maestros') {
            avatar.className = 'dh-avatar';
            avatar.innerHTML = x.foto ? '<img src="../' + esc(x.foto) + '" alt="">' : esc(x.iniciales);
            $('#dhTitulo').textContent = x.nombre;
            $('#dhSub').textContent = x.titulo || 'Docente';
            cuerpo += '<div class="dh-sec"><h4><i class="fas fa-users"></i> Grupos que atiende</h4>' +
                (x.grupos.length ? '<div class="dh-chips">' + x.grupos.map(g => '<span class="dh-chip">' + esc(g) + '</span>').join('') + '</div>' : '<div class="dh-sin">Sin grupos asignados.</div>') + '</div>';
            cuerpo += '<div class="dh-sec"><h4><i class="far fa-calendar-days"></i> Horario semanal de clases</h4>' + semana(x.clases, 'Grupo') + '</div>';
        } else {
            avatar.className = 'dh-avatar grupo';
            avatar.textContent = x.semestre + '°';
            $('#dhTitulo').textContent = 'Grupo ' + x.nombre + ' · ' + x.turno;
            $('#dhSub').textContent = x.alumnos + ' alumnos' + (x.capacitacion ? ' · ' + x.capacitacion : '');
            cuerpo += '<div class="dh-sec"><h4><i class="fas fa-chalkboard-user"></i> Docentes del grupo</h4>' +
                (x.maestros.length ? '<div class="dh-chips">' + x.maestros.map(m => '<span class="dh-chip">' + esc(m) + '</span>').join('') + '</div>' : '<div class="dh-sin">Sin docentes asignados.</div>') + '</div>';
            cuerpo += '<div class="dh-sec"><h4><i class="far fa-calendar-days"></i> Horario semanal de clases</h4>' + semana(x.clases, 'Docente:') + '</div>';
        }
        cuerpo += '<div class="dh-sec"><h4><i class="fas fa-file-pdf"></i> Horarios en PDF</h4>' + pdfs(x.pdfs) + '</div>';
        $('#dhCuerpo').innerHTML = cuerpo;
        $$('#dhCuerpo [data-ver]').forEach(b => b.addEventListener('click', () => {
            const visor = $('#dhVisor');
            visor.querySelector('iframe').src = '../' + x.pdfs[b.dataset.ver].ruta;
            visor.hidden = false;
            visor.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }));
        $('#dhModal').classList.add('abierto');
        document.body.style.overflow = 'hidden';
        $('#dhCerrar').focus();
    }
    function cerrar() { $('#dhModal').classList.remove('abierto'); document.body.style.overflow = ''; $('#dhCuerpo').innerHTML = ''; }
    $$('.dh-item').forEach(it => it.addEventListener('click', () => abrir(it.dataset.tipo, Number(it.dataset.i))));
    $('#dhCerrar').addEventListener('click', cerrar);
    $('#dhModal').addEventListener('click', e => { if (e.target.id === 'dhModal') cerrar(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && $('#dhModal').classList.contains('abierto')) cerrar(); });

    // Abrir directo desde la URL: ?ver=grupo-6 o ?ver=maestro-1
    const ver = new URLSearchParams(location.search).get('ver');
    if (ver) {
        const [t, id] = ver.split('-');
        const tipo = t === 'grupo' ? 'grupos' : 'maestros';
        const i = DATOS[tipo].findIndex(x => String(x.id) === id);
        if (i >= 0) { if (tipo === 'grupos') $('.dh-tab[data-tab="grupos"]').click(); abrir(tipo, i); }
    }
})();
</script>
<?php
}
