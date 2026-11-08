<?php
require_once '../config/database.php';
require_once 'includes/layout.php';
require_once 'includes/avisos_data.php';

$alumno = estudiante_requerir_sesion($pdo);
$avisos = obtener_avisos_estudiante($pdo, (int)$alumno['id_alumno'], 50);

$css = '
.aviso{display:flex;gap:14px;padding:1rem 0;border-bottom:1px solid #f1f5f9;text-decoration:none;color:inherit;}
.aviso:last-child{border-bottom:0;}
a.aviso:hover h3{color:#047857;}
.aviso-icon{flex:0 0 42px;height:42px;border-radius:12px;display:grid;place-items:center;font-size:1rem;}
.aviso-icon.blue{background:#eff6ff;color:#2563eb;} .aviso-icon.green{background:#ecfdf5;color:#059669;}
.aviso-icon.red{background:#fef2f2;color:#dc2626;} .aviso-icon.amber{background:#fffbeb;color:#d97706;} .aviso-icon.gray{background:#f1f5f9;color:#475569;}
.aviso h3{margin:0 0 3px;font-size:.93rem;color:#1e293b;}
.aviso p{margin:0 0 5px;font-size:.84rem;color:#64748b;line-height:1.45;}
.aviso small{font-size:.72rem;color:#94a3b8;font-weight:600;}
';
estudiante_layout_inicio('Avisos', 'Novedades de tus materias, asistencia y conducta.', 'avisos.php', $alumno, $css);
?>
<div class="info-card">
    <h2><i class="fas fa-bullhorn"></i> Avisos recientes</h2>
    <p class="desc">Se generan automáticamente con la información de tus clases, entregas, asistencia y reportes.</p>
    <?php if (empty($avisos)): ?>
        <div class="info-empty"><i class="far fa-bell-slash"></i>No tienes avisos por ahora. Cuando tus docentes publiquen actividades o haya novedades, aparecerán aquí.</div>
    <?php else: ?>
        <?php foreach ($avisos as $a):
            $tag = $a['enlace'] ? 'a href="' . htmlspecialchars($a['enlace']) . '"' : 'div'; ?>
            <<?php echo $tag; ?> class="aviso">
                <div class="aviso-icon <?php echo $a['color']; ?>"><i class="<?php echo $a['icono']; ?>"></i></div>
                <div>
                    <h3><?php echo htmlspecialchars($a['titulo']); ?></h3>
                    <p><?php echo htmlspecialchars($a['texto']); ?></p>
                    <small><?php echo tiempo_relativo($a['fecha']); ?></small>
                </div>
            </<?php echo $a['enlace'] ? 'a' : 'div'; ?>>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php estudiante_layout_fin(); ?>
