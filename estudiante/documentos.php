<?php
require_once '../config/database.php';
require_once 'includes/layout.php';

$alumno = estudiante_requerir_sesion($pdo);
$id_alumno = (int)$alumno['id_alumno'];

$horario = null;
$materiales = [];
$entregas = [];
try {
    // Horario del grupo (lo sube el administrador)
    $st = $pdo->prepare("
        SELECT hm.ruta_pdf, hm.nombre_archivo, hm.ciclo, hm.anio
        FROM GRUPO_ALUMNO ga INNER JOIN HORARIOS_MAESTRO hm ON hm.id_grupo = ga.id_grupo
        WHERE ga.id_alumno = ? AND ga.fecha_baja IS NULL AND hm.ruta_pdf IS NOT NULL
        ORDER BY hm.anio DESC, hm.fecha_subida DESC LIMIT 1
    ");
    $st->execute([$id_alumno]);
    $horario = $st->fetch(PDO::FETCH_ASSOC);

    // Materiales que mis docentes adjuntaron en las actividades del aula virtual
    $st = $pdo->prepare("
        SELECT ac.titulo, ac.archivo_ruta, ac.fecha_publicacion, m.nombre_materia, ac.id_clase
        FROM ACTIVIDADES_CLASE ac
        INNER JOIN CLASE_REGULAR_ALUMNO cra ON cra.id_clase = ac.id_clase AND cra.id_alumno = ? AND cra.acceso = 1
        INNER JOIN CLASES_REGULARES c ON c.id_clase = ac.id_clase
        INNER JOIN MATERIAS m ON m.id_materia = c.id_materia
        WHERE ac.archivo_ruta IS NOT NULL AND ac.archivo_ruta <> '' AND ac.visible = 1
          AND (ac.fecha_apertura IS NULL OR ac.fecha_apertura <= NOW())
        ORDER BY ac.fecha_publicacion DESC
    ");
    $st->execute([$id_alumno]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $archivo = basename($r['archivo_ruta']);
        $materiales[] = ['materia' => $r['nombre_materia'], 'titulo' => $r['titulo'], 'fecha' => $r['fecha_publicacion'],
                         'nombre' => preg_replace('/^mat_.*?_/', '', $archivo), 'url' => '../uploads/materiales_clase/' . $archivo];
    }

    // Materiales de mis cursos de regularización
    $st = $pdo->prepare("
        SELECT ar.titulo, ar.archivo_ruta, ar.fecha_publicacion, c.nombre_materia
        FROM ACTIVIDADES_REGULARIZACION ar
        INNER JOIN CLASE_ALUMNO ca ON ca.id_clase = ar.id_clase AND ca.id_alumno = ?
        INNER JOIN CLASES_REGULARIZACION c ON c.id_clase = ar.id_clase AND c.visible = 1
        WHERE ar.archivo_ruta IS NOT NULL AND ar.archivo_ruta <> ''
        ORDER BY ar.fecha_publicacion DESC
    ");
    $st->execute([$id_alumno]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $materiales[] = ['materia' => $r['nombre_materia'] . ' (Regularización)', 'titulo' => $r['titulo'], 'fecha' => $r['fecha_publicacion'],
                         'nombre' => preg_replace('/^mat_.*?_/', '', basename($r['archivo_ruta'])), 'url' => '../' . ltrim($r['archivo_ruta'], './')];
    }
    usort($materiales, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));

    // Mis entregas
    $st = $pdo->prepare("
        SELECT ea.archivo_ruta, ea.fecha_entrega, ea.estatus_entrega, ac.titulo, m.nombre_materia
        FROM ENTREGAS_ACTIVIDAD ea
        INNER JOIN ACTIVIDADES_CLASE ac ON ac.id_actividad = ea.id_actividad
        INNER JOIN CLASES_REGULARES c ON c.id_clase = ac.id_clase
        INNER JOIN MATERIAS m ON m.id_materia = c.id_materia
        WHERE ea.id_alumno = ?
        ORDER BY ea.fecha_entrega DESC
    ");
    $st->execute([$id_alumno]);
    $entregas = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Documentos alumno: ' . $e->getMessage());
}

$css = '
.doc-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:1rem;}
.doc-tile{display:flex;gap:12px;align-items:center;padding:1rem;border:1px solid #e2e8f0;border-radius:14px;text-decoration:none;color:inherit;background:#fff;transition:.2s;}
.doc-tile:hover{border-color:#059669;box-shadow:0 4px 14px rgba(5,150,105,.08);}
.doc-tile .ico{flex:0 0 44px;height:44px;border-radius:12px;display:grid;place-items:center;font-size:1.1rem;}
.doc-tile strong{display:block;font-size:.9rem;color:#0f172a;}
.doc-tile span{font-size:.78rem;color:#64748b;}
.doc-tile.disabled{opacity:.6;pointer-events:none;}
.dl{color:#047857;font-weight:600;text-decoration:none;white-space:nowrap;}
';
estudiante_layout_inicio('Documentos', 'Tus documentos escolares y materiales de clase.', 'documentos.php', $alumno, $css);
?>
<div class="info-card">
    <h2><i class="fas fa-id-badge"></i> Documentos escolares</h2>
    <p class="desc">Accesos rápidos a tus documentos personales.</p>
    <div class="doc-grid">
        <a class="doc-tile" href="perfil.php">
            <div class="ico" style="background:#ecfdf5;color:#059669;"><i class="fas fa-qrcode"></i></div>
            <div><strong>Credencial digital</strong><span>Con tu código QR de acceso</span></div>
        </a>
        <a class="doc-tile <?php echo $horario ? '' : 'disabled'; ?>" href="<?php echo $horario ? '../' . htmlspecialchars($horario['ruta_pdf']) : '#'; ?>" target="_blank">
            <div class="ico" style="background:#eff6ff;color:#2563eb;"><i class="far fa-calendar-alt"></i></div>
            <div><strong>Horario de clases</strong><span><?php echo $horario ? htmlspecialchars('Ciclo ' . $horario['ciclo'] . ' ' . $horario['anio']) : 'Aún no publicado'; ?></span></div>
        </a>
        <a class="doc-tile" href="mis_asistencias.php">
            <div class="ico" style="background:#fffbeb;color:#d97706;"><i class="far fa-calendar-check"></i></div>
            <div><strong>Historial de asistencia</strong><span>Entradas y salidas por mes</span></div>
        </a>
        <a class="doc-tile" href="reglamentos.php">
            <div class="ico" style="background:#fef2f2;color:#dc2626;"><i class="far fa-clipboard"></i></div>
            <div><strong>Reglamento escolar</strong><span>Faltas, sanciones y puntos</span></div>
        </a>
    </div>
</div>

<div class="info-card">
    <h2><i class="fas fa-folder-open"></i> Materiales de clase</h2>
    <p class="desc">Archivos que tus docentes compartieron en el aula virtual y en regularizaciones.</p>
    <?php if (empty($materiales)): ?>
        <div class="info-empty"><i class="far fa-folder-open"></i>Tus docentes todavía no han compartido materiales.</div>
    <?php else: ?>
        <div class="info-wrap">
            <table class="info-table">
                <thead><tr><th>Materia</th><th>Actividad</th><th>Archivo</th><th>Fecha</th></tr></thead>
                <tbody>
                <?php foreach ($materiales as $m): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($m['materia']); ?></td>
                        <td><?php echo htmlspecialchars($m['titulo']); ?></td>
                        <td><a class="dl" href="<?php echo htmlspecialchars($m['url']); ?>" target="_blank" download="<?php echo htmlspecialchars($m['nombre']); ?>"><i class="fas fa-download"></i> <?php echo htmlspecialchars($m['nombre']); ?></a></td>
                        <td style="white-space:nowrap;"><?php echo date('d/m/Y', strtotime($m['fecha'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="info-card">
    <h2><i class="fas fa-file-arrow-up"></i> Mis entregas</h2>
    <p class="desc">Archivos que has enviado en tus actividades.</p>
    <?php if (empty($entregas)): ?>
        <div class="info-empty"><i class="far fa-paper-plane"></i>Aún no has entregado actividades.</div>
    <?php else: ?>
        <div class="info-wrap">
            <table class="info-table">
                <thead><tr><th>Materia</th><th>Actividad</th><th>Estatus</th><th>Archivo</th><th>Fecha</th></tr></thead>
                <tbody>
                <?php foreach ($entregas as $e):
                    $archivo = basename($e['archivo_ruta']);
                    $est = $e['estatus_entrega'];
                    $cls = stripos($est, 'tiempo') !== false || stripos($est, 'aprob') !== false ? 'green' : (stripos($est, 'tarde') !== false || stripos($est, 'retardo') !== false ? 'amber' : 'gray'); ?>
                    <tr>
                        <td><?php echo htmlspecialchars($e['nombre_materia']); ?></td>
                        <td><?php echo htmlspecialchars($e['titulo']); ?></td>
                        <td><span class="tag <?php echo $cls; ?>"><?php echo htmlspecialchars($est); ?></span></td>
                        <td><a class="dl" href="../uploads/entregas_alumnos/<?php echo htmlspecialchars($archivo); ?>" target="_blank"><i class="fas fa-download"></i> <?php echo htmlspecialchars(preg_replace('/^al_\d+_\d+_/', '', $archivo)); ?></a></td>
                        <td style="white-space:nowrap;"><?php echo date('d/m/Y H:i', strtotime($e['fecha_entrega'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php estudiante_layout_fin(); ?>
