<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'prefectos') {
    header("Location: ../login.php");
    exit();
}

$nombre_usuario = isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') : 'Prefecto';

try {
    $stmt_faltas = $pdo->query("SELECT id_tipo_falta, numero_reglamento, descripcion_falta, puntos_descuento FROM tipos_falta ORDER BY numero_reglamento ASC");
    $tipos_falta = $stmt_faltas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $tipos_falta = [];
}

$matricula_buscada = trim($_GET['matricula'] ?? '');
$alumno = null;
$historial = [];

if (!empty($matricula_buscada)) {
    // NUEVA CONSULTA: Hacemos JOIN con grupo_alumno y grupos para obtener el Semestre, Grupo y Turno reales
    $stmt_alumno = $pdo->prepare("
        SELECT a.id_alumno, a.matricula, a.nombre, a.apellido_paterno, a.puntos_conducta, 
               g.semestre, g.nombre_grupo, g.turno
        FROM alumnos a
        LEFT JOIN grupo_alumno ga ON a.id_alumno = ga.id_alumno
        LEFT JOIN grupos g ON ga.id_grupo = g.id_grupo
        WHERE a.matricula = :matricula 
        LIMIT 1
    ");
    $stmt_alumno->execute([':matricula' => $matricula_buscada]);
    $alumno = $stmt_alumno->fetch(PDO::FETCH_ASSOC);

    if ($alumno) {
        $stmt_reportes = $pdo->prepare("
            SELECT r.id_reporte, r.id_alumno, r.id_tipo_falta, r.fecha_hora, tf.descripcion_falta, tf.numero_reglamento, r.puntos_descontados, r.observaciones, tf.tipo_sancion, tf.requiere_citacion_tutor 
            FROM reportes r
            INNER JOIN tipos_falta tf ON r.id_tipo_falta = tf.id_tipo_falta
            WHERE r.id_alumno = :id
            ORDER BY r.fecha_hora DESC
        ");
        $stmt_reportes->execute([':id' => $alumno['id_alumno']]);
        $historial = $stmt_reportes->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expediente de Alumnos · COBAEP 27</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../admin/style_dashboard_admin.css">
    <link rel="stylesheet" href="../Prefectura/style_dashboard_prefectura.css">
    <link rel="stylesheet" href="../Prefectura/style_creacion_reportes.css">
    <style>
        .search-container { background: #ffffff; padding: 25px; border-radius: var(--radius-md); border: 1px solid var(--line); box-shadow: var(--shadow-sm); margin-bottom: 25px; }
        .search-box { display: flex; gap: 10px; max-width: 500px; }
        .search-box input { flex: 1; padding: 0.8rem; border: 1px solid var(--line); border-radius: var(--radius-sm); font-size: 0.95rem; }
        .search-box button { background: var(--green); color: white; border: none; padding: 0 20px; border-radius: var(--radius-sm); cursor: pointer; font-weight: 600; transition: 0.2s; }
        .search-box button:hover { background: var(--green-700); }
        .student-header { display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 20px; border: 1px solid #e2e8f0; border-radius: var(--radius-md); margin-bottom: 25px; }
        .pts-indicator { text-align: center; background: white; padding: 10px 20px; border-radius: var(--radius-sm); border: 1px solid var(--line); }
        .pts-indicator span { display: block; font-size: 0.7rem; color: var(--muted); text-transform: uppercase; font-weight: 700; margin-bottom: 5px; }
        .pts-indicator strong { font-size: 1.8rem; }
        .btn-pdf { background: #0ea5e9; color: white; padding: 0.8rem 1.5rem; border-radius: var(--radius-sm); text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-pdf:hover { background: #0284c7; }
        .action-buttons { display: flex; gap: 8px; justify-content: center; }
        .btn-action { border: none; background: transparent; width: 30px; height: 30px; border-radius: var(--radius-sm); display: grid; place-items: center; cursor: pointer; transition: 0.2s ease; color: var(--muted); text-decoration: none; font-size: 0.9rem; }
        .btn-edit:hover { background: #fef08a; color: #ca8a04; }
        .btn-delete:hover { background: #fee2e2; color: #dc2626; }
        #modalEditarReporte .custom-select-options { z-index: 2050; }
    </style>
</head>
<body>
    
    <aside class="sidebar" id="sidebar">
        <button id="collapseToggle" class="collapse-toggle" aria-label="Colapsar menú">
            <i class="fas fa-chevron-left"></i>
        </button>

        <div class="sidebar-inner">
            <div class="brand">
                <img src="../img/LogoCobaep.png" alt="Logo COBAEP" class="brand-logo">
                <div class="brand-copy">
                    <strong>SIGE<span>-Cobaep</span></strong><small>Plantel 27</small>
                </div>
            </div>

            <nav class="navigation" aria-label="Navegación principal">
                <span class="nav-heading">Menú principal</span>
                <a href="dashboard_prefectura.php" class="nav-item"><i class="fas fa-shield-halved"></i><span>Control de Acceso</span></a>
                <a href="monitoreo_grupos.php" class="nav-item"><i class="fas fa-users-viewfinder"></i><span>Monitoreo de Grupos</span></a>
                <a href="historial_asistencias.php" class="nav-item"><i class="fas fa-history"></i><span>Historial y Reportes</span></a>
                <a href="creacion_reportes.php" class="nav-item"><i class="fa-solid fa-file-lines"></i><span>Creacion de Reporte de Conducta</span></a>  
                <a href="expediente_alumnos.php" class="nav-item active"><i class="fas fa-folder-open"></i><span>Expediente de Alumnos</span></a>
            </nav>

            <div class="sidebar-bottom">
                <div class="institution">
                    <div class="institution-icon"><i class="fas fa-building-columns"></i></div>
                    <div><strong>COBAEP Plantel 27</strong><span>Zaragoza, Puebla</span></div>
                </div>
                <a href="../logout.php" class="logout"><i class="fas fa-arrow-right-from-bracket"></i><span>Cerrar Sesión</span></a>
            </div>
        </div>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu" id="mobileMenu" type="button"><i class="fas fa-bars"></i></button>
                <div><span class="eyebrow">PREFECTURA · DISCIPLINA</span><h1>Expediente de Alumnos</h1><p>Consulta el historial disciplinario y genera documentos oficiales.</p></div>
            </div>
            <div class="topbar-right">
                <div class="profile"><div class="profile-avatar"><i class="fas fa-user-shield"></i></div><div class="profile-info"><strong><?php echo $nombre_usuario; ?></strong><span>Prefecto</span></div></div>
            </div>
        </header>

        <section class="search-container">
            <label style="display: block; font-weight: 600; color: var(--ink); margin-bottom: 10px;"><i class="fas fa-search"></i> Buscar Expediente</label>
            <form method="GET" class="search-box">
                <input type="text" name="matricula" placeholder="Ingresa la matrícula del alumno (Ej. 23ZP0287)" value="<?php echo htmlspecialchars($matricula_buscada); ?>" required autofocus>
                <button type="submit">Buscar</button>
                <?php if(!empty($matricula_buscada)): ?><a href="expediente_alumnos.php" style="display:grid; place-items:center; padding:0 15px; color:var(--muted); text-decoration:none; border:1px solid var(--line); border-radius:var(--radius-sm);"><i class="fas fa-times"></i></a><?php endif; ?>
            </form>
        </section>

        <?php if (!empty($matricula_buscada)): ?>
            <?php if (!$alumno): ?>
                <div style="background: #fef2f2; color: #b91c1c; padding: 20px; border-radius: var(--radius-md); border: 1px solid #fecaca; text-align: center;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                    <h3 style="margin: 0;">Alumno no encontrado</h3>
                    <p style="margin: 5px 0 0 0; font-size: 0.9rem;">Verifica que la matrícula sea correcta y vuelve a intentarlo.</p>
                </div>
            <?php else: ?>
                <div class="student-header">
                    <div>
                        <h2 style="margin: 0 0 5px 0; color: var(--ink); font-size: 1.5rem;"><?php echo htmlspecialchars($alumno['apellido_paterno'] . ' ' . $alumno['nombre']); ?></h2>
                        <p style="margin: 0; color: var(--muted); font-size: 0.9rem;">
                        <strong>Matrícula:</strong> <?php echo htmlspecialchars($alumno['matricula']); ?> | 
                     <strong>Grupo:</strong> 
            <?php 
                     $grupo_texto = !empty($alumno['semestre']) ? $alumno['semestre'] . '° "' . $alumno['nombre_grupo'] . '"' : 'Sin Grupo';
                     $turno_texto = !empty($alumno['turno']) ? $alumno['turno'] : 'N/A';
                     echo htmlspecialchars($grupo_texto . ' - ' . $turno_texto); 
                     ?>
                 </p>
                    </div>
                    <div style="display: flex; gap: 20px; align-items: center;">
                        <?php 
                            $pts = (int)($alumno['puntos_conducta'] ?? 100);
                            $pts_color = '#16a34a'; 
                            if ($pts <= 75 && $pts > 60) { $pts_color = '#a16207'; } 
                            elseif ($pts <= 60 && $pts > 0) { $pts_color = '#dc2626'; } 
                            elseif ($pts == 0) { $pts_color = '#000000'; } 
                        ?>
                        <div class="pts-indicator">
                            <span>Puntos Actuales</span>
                            <strong style="color: <?php echo $pts_color; ?>;"><?php echo $pts; ?></strong>
                            <?php if ($pts <= 75 && $pts > 60): ?><span style="font-size:0.65rem; color:#a16207; font-weight:bold; display:block; margin-top:5px;">CARTA COMPROMISO</span>
                            <?php elseif ($pts <= 60 && $pts > 0): ?><span style="font-size:0.65rem; color:#dc2626; font-weight:bold; display:block; margin-top:5px;">CONDICIONAMIENTO</span>
                            <?php elseif ($pts == 0): ?><span style="font-size:0.65rem; color:#000000; font-weight:bold; display:block; margin-top:5px;">SUSPENSIÓN</span><?php endif; ?>
                        </div>
                        <a href="procesos/generar_pdf_expediente.php?id_alumno=<?php echo $alumno['id_alumno']; ?>" target="_blank" class="btn-pdf"><i class="fas fa-file-pdf"></i> Descargar Expediente</a>
                    </div>
                </div>

                <article class="overview-card" style="padding: 0; overflow: hidden;">
                    <div class="card-heading" style="padding: 24px 24px 10px;">
                        <div><span class="section-kicker">HISTORIAL COMPLETO</span><h2>Faltas Registradas</h2></div>
                    </div>
                    <div class="table-responsive" style="margin-bottom: 0;">
                        <table>
                            <thead>
                                <tr><th>Fecha y Hora</th><th>Regla Infringida</th><th>Observaciones</th><th>Puntos</th><th style="text-align: center;">Acciones</th></tr>
                            </thead>
                            <tbody>
                                <?php if (empty($historial)): ?>
                                    <tr><td colspan="5" style="text-align: center; padding: 3rem; color: var(--green);"><strong><i class="fas fa-check-circle"></i> Expediente limpio.</strong> No hay reportes registrados para este alumno.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($historial as $rep): ?>
                                    <tr>
                                        <td style="font-size: 0.8rem; color: var(--muted);"><?php echo date('d/m/Y', strtotime($rep['fecha_hora'])); ?><br><strong><?php echo date('h:i A', strtotime($rep['fecha_hora'])); ?></strong></td>
                                        <td>
                                            <strong>Regla <?php echo htmlspecialchars($rep['numero_reglamento']); ?>:</strong><br><?php echo htmlspecialchars($rep['descripcion_falta']); ?><br>
                                            <?php if($rep['tipo_sancion'] === 'Suspension'): ?><span style="display:inline-block; margin-top:4px; background: #000; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 0.65rem; font-weight: bold; text-transform: uppercase;">Suspensión</span>
                                            <?php elseif($rep['requiere_citacion_tutor'] == 1 || $rep['tipo_sancion'] === 'Citacion'): ?><span style="display:inline-block; margin-top:4px; background: #fef08a; color: #a16207; padding: 2px 6px; border-radius: 4px; font-size: 0.65rem; font-weight: bold; text-transform: uppercase;">Cita Tutor</span><?php endif; ?>
                                        </td>
                                        <td style="font-size: 0.8rem; color: var(--muted); font-style: italic; max-width: 300px;"><?php echo !empty($rep['observaciones']) ? htmlspecialchars($rep['observaciones']) : 'Sin observaciones adicionales.'; ?></td>
                                        <td>
                                            <?php if(!empty($rep['puntos_descontados']) && $rep['puntos_descontados'] > 0): ?><span class="status-badge-table" style="background: #fee2e2; color: #b91c1c; font-weight: bold;">-<?php echo htmlspecialchars($rep['puntos_descontados']); ?></span>
                                            <?php else: ?><span style="color: var(--muted); font-size: 0.75rem; font-weight: 600; background: #f1f5f9; padding: 4px 8px; border-radius: 4px;">Sin Puntos</span><?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button type="button" class="btn-action btn-edit" title="Editar reporte" 
                                                    data-id="<?php echo $rep['id_reporte']; ?>"
                                                    data-alumno="<?php echo $rep['id_alumno']; ?>"
                                                    data-matricula="<?php echo htmlspecialchars($alumno['matricula']); ?>"
                                                    data-nombre="<?php echo htmlspecialchars($alumno['apellido_paterno'] . ' ' . $alumno['nombre']); ?>"
                                                    data-falta="<?php echo $rep['id_tipo_falta']; ?>"
                                                    data-obs="<?php echo htmlspecialchars($rep['observaciones'] ?? ''); ?>"
                                                    data-url="expediente_alumnos.php?matricula=<?php echo htmlspecialchars($matricula_buscada); ?>"
                                                    onclick="abrirModalEditar(this)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn-action btn-delete" title="Eliminar reporte" onclick="abrirModalEliminar(<?php echo $rep['id_reporte']; ?>)"><i class="fas fa-trash-alt"></i></button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </article>
            <?php endif; ?>
        <?php endif; ?>

        <!-- MODAL DE EDICIÓN DE REPORTE CON BUSCADOR -->
        <div class="modal-overlay" id="modalEditarReporte" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(3, 43, 30, 0.6); backdrop-filter: blur(4px); justify-content: center; align-items: center; z-index: 2000;">
            <div class="modal" style="background: #ffffff; padding: 2.5rem; border-radius: var(--radius-lg); width: 90%; max-width: 500px; box-shadow: var(--shadow-lg);">
                <h3 style="margin-bottom: 0.5rem; font-size: 1.3rem; color: var(--ink);"><i class="fas fa-edit" style="color: var(--green);"></i> Modificar Reporte</h3>
                <p style="font-size: 0.8rem; color: var(--muted); margin-bottom: 1.5rem;">Corrige la falta cometida o actualiza las observaciones del reporte.</p>
                
                <form action="procesos/actualizar_reporte.php" method="POST">
                    <input type="hidden" name="id_reporte" id="edit_id_reporte" value="">
                    <input type="hidden" name="id_alumno" id="edit_id_alumno" value="">
                    <input type="hidden" name="return_url" id="edit_return_url" value="">
                    
                    <div class="form-group" style="margin-bottom: 1.2rem;">
                        <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--ink); margin-bottom: 0.5rem;">Alumno Sancionado:</label>
                        <div id="edit_student_info" style="background: #f8fafc; padding: 12px; border-radius: var(--radius-sm); border: 1px solid var(--line); font-size: 0.9rem; color: var(--ink); font-weight: 600;">
                            <!-- Llenado por JS -->
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 1.2rem;">
                        <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--ink); margin-bottom: 0.5rem;">Falta Cometida:</label>
                        <div class="custom-select-container">
                            <input type="hidden" name="id_tipo_falta" id="edit_id_tipo_falta" required>
                            <div class="custom-select-trigger" id="editCustomSelectTrigger" style="background: #ffffff;">
                                <span id="editTriggerText">Selecciona o busca la falta...</span>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="custom-select-options" id="editCustomSelectOptions">
                                <div class="custom-select-search"><i class="fas fa-search"></i><input type="text" id="editSearchFalta" placeholder="Buscar regla..."></div>
                                <ul class="options-list" id="editFaltaList">
                                    <?php foreach ($tipos_falta as$falta): ?>
                                       <li class="option-item edit-option-item" data-value="<?php echo $falta['id_tipo_falta']; ?>">
                                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                                <div style="flex: 1; padding-right: 10px;"><strong>Regla <?php echo $falta['numero_reglamento']; ?>:</strong> <?php echo htmlspecialchars($falta['descripcion_falta']); ?></div>
                                                <div style="text-align: right; min-width: 60px;">
                                                    <?php if(!empty($falta['puntos_descuento'])): ?><span class="pts" style="display: block; margin-bottom: 4px; color: #b91c1c; font-weight:bold;">-<?php echo $falta['puntos_descuento']; ?> pts</span><?php endif; ?>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 1.2rem;">
                        <label style="display: block; font-size: 0.8rem; font-weight: 700; color: var(--ink); margin-bottom: 0.5rem;">Observaciones / Detalles:</label>
                        <textarea name="observaciones" id="edit_observaciones" class="form-control" style="width: 100%; padding: 0.85rem; border: 1px solid var(--line); border-radius: var(--radius-sm); min-height: 100px; resize: vertical;"></textarea>
                    </div>

                    <div class="modal-actions" style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem;">
                        <button type="button" class="btn btn-cancel" onclick="cerrarModalEditar()" style="padding: 0.75rem 1.5rem; border: 1px solid var(--line); border-radius: var(--radius-sm); cursor: pointer; background: #f1f5f9;">Cancelar</button>
                        <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.5rem; background: var(--green); color: white; border: none; border-radius: var(--radius-sm); cursor: pointer;">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL DE CONFIRMACIÓN DE ELIMINACIÓN -->
        <div class="modal-overlay" id="modalEliminarReporte" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(3, 43, 30, 0.6); backdrop-filter: blur(4px); justify-content: center; align-items: center; z-index: 2000;">
            <div class="modal" style="background: #ffffff; padding: 2.5rem; border-radius: var(--radius-lg); width: 90%; max-width: 450px; box-shadow: var(--shadow-lg);">
                <h3 style="margin-bottom: 0.5rem; font-size: 1.3rem;"><i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> Confirmar Eliminación</h3>
                <p style="font-size: 0.8rem; color: var(--muted); margin-bottom: 1.5rem;">Para eliminar este reporte, ingresa tu contraseña de acceso.</p>
                <form action="procesos/eliminar_reporte.php" method="POST">
                    <input type="hidden" name="id_reporte" id="delete_id_reporte" value="">
                    <input type="hidden" name="return_url" value="expediente_alumnos.php?matricula=<?php echo htmlspecialchars($matricula_buscada); ?>">
                    <div class="form-group">
                        <label for="password_confirm" style="display: block; font-size: 0.8rem; font-weight: 700; margin-bottom: 0.5rem;"><i class="fas fa-lock"></i> Contraseña de Prefecto</label>
                        <input type="password" id="password_confirm" name="password_confirm" class="form-control" placeholder="Ingresa tu contraseña..." required autofocus style="width: 100%; padding: 0.85rem; border: 1px solid var(--line); border-radius: var(--radius-sm);">
                    </div>
                    <div class="modal-actions" style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem;">
                        <button type="button" class="btn btn-cancel" onclick="cerrarModalEliminar()" style="padding: 0.75rem 1.5rem; border: 1px solid var(--line); border-radius: var(--radius-sm); cursor: pointer;">Cancelar</button>
                        <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.5rem; background: #ef4444; color: white; border: none; border-radius: var(--radius-sm); cursor: pointer;">Eliminar</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="../Prefectura/procesos/dashboard_prefecturas.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Lógica del buscador del MODAL DE EDICIÓN
            const editTrigger = document.getElementById('editCustomSelectTrigger');
            const editOptionsContainer = document.getElementById('editCustomSelectOptions');
            const editSearchInput = document.getElementById('editSearchFalta');
            const editOptionsList = document.querySelectorAll('.edit-option-item');
            const editTriggerText = document.getElementById('editTriggerText');
            const editInputFalta = document.getElementById('edit_id_tipo_falta');

            editTrigger.addEventListener('click', function(e) { 
                e.stopPropagation(); 
                editOptionsContainer.classList.toggle('open'); 
                if(editOptionsContainer.classList.contains('open')) editSearchInput.focus(); 
            });

            editSearchInput.addEventListener('input', function() { 
                let filter = this.value.toLowerCase(); 
                editOptionsList.forEach(option => { 
                    if(option.innerText.toLowerCase().includes(filter)) option.style.display = 'block'; 
                    else option.style.display = 'none'; 
                }); 
            });

            editOptionsList.forEach(option => {
                option.addEventListener('click', function(e) {
                    e.stopPropagation();
                    let val = this.getAttribute('data-value');
                    let textContent = this.querySelector('strong').innerText + ' ' + this.innerText.split(':')[1].trim();
                    editTriggerText.textContent = textContent;
                    editTrigger.classList.add('selected');
                    editInputFalta.value = val;
                    editOptionsContainer.classList.remove('open');
                    editSearchInput.value = "";
                    editOptionsList.forEach(opt => opt.style.display = 'block');
                });
            });

            document.addEventListener('click', function(e) { 
                if (!e.target.closest('.custom-select-container')) {
                    editOptionsContainer.classList.remove('open');
                }
            });
        });

        function abrirModalEditar(btn) {
            document.getElementById('edit_id_reporte').value = btn.getAttribute('data-id');
            document.getElementById('edit_id_alumno').value = btn.getAttribute('data-alumno');
            document.getElementById('edit_id_tipo_falta').value = btn.getAttribute('data-falta');
            document.getElementById('edit_observaciones').value = btn.getAttribute('data-obs');
            document.getElementById('edit_return_url').value = btn.getAttribute('data-url');
            
            // Cargar Info del Alumno
            document.getElementById('edit_student_info').innerHTML = `<strong>${btn.getAttribute('data-matricula')}</strong> - ${btn.getAttribute('data-nombre')}`;

            // Actualizar texto del Custom Select de Edición
            let faltaId = btn.getAttribute('data-falta');
            let options = document.querySelectorAll('.edit-option-item');
            options.forEach(opt => {
                if(opt.getAttribute('data-value') === faltaId) {
                    let textContent = opt.querySelector('strong').innerText + ' ' + opt.innerText.split(':')[1].trim();
                    document.getElementById('editTriggerText').textContent = textContent;
                }
            });

            document.getElementById('modalEditarReporte').style.display = 'flex';
        }

        function cerrarModalEditar() {
            document.getElementById('modalEditarReporte').style.display = 'none';
        }

        function abrirModalEliminar(idReporte) {
            document.getElementById('delete_id_reporte').value = idReporte;
            document.getElementById('password_confirm').value = '';
            document.getElementById('modalEliminarReporte').style.display = 'flex';
        }

        function cerrarModalEliminar() {
            document.getElementById('modalEliminarReporte').style.display = 'none';
        }
    </script>
</body>
</html>