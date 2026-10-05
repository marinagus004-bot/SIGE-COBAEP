<?php
session_start();
require_once '../config/database.php'; 

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'prefectos') {
    header("Location: ../login.php");
    exit();
}

$nombre_usuario = isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') : 'Prefecto';

try {
    $stmt_faltas = $pdo->query("SELECT id_tipo_falta, numero_reglamento, descripcion_falta, puntos_descuento, tipo_sancion, requiere_citacion_tutor FROM tipos_falta ORDER BY numero_reglamento ASC");
    $tipos_falta = $stmt_faltas->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $tipos_falta = [];
}

$busqueda = trim($_GET['buscar'] ?? '');
$params_reportes = [];
$query_reportes = "
    SELECT r.id_reporte, r.id_alumno, r.id_tipo_falta, r.observaciones, r.fecha_hora, al.matricula, al.nombre, al.apellido_paterno, 
           tf.descripcion_falta, r.puntos_descontados, r.sancion_acumulativa_activada,
           tf.tipo_sancion, tf.requiere_citacion_tutor
    FROM reportes r
    INNER JOIN alumnos al ON r.id_alumno = al.id_alumno
    INNER JOIN tipos_falta tf ON r.id_tipo_falta = tf.id_tipo_falta
";
if (!empty($busqueda)) {
    $query_reportes .= " WHERE al.matricula LIKE :busqueda OR al.nombre LIKE :busqueda OR al.apellido_paterno LIKE :busqueda";
    $params_reportes[':busqueda'] = '%' . $busqueda . '%';
}
$query_reportes .= " ORDER BY r.fecha_hora DESC LIMIT 20";

try {
    $stmt_reportes = $pdo->prepare($query_reportes);
    $stmt_reportes->execute($params_reportes);
    $ultimos_reportes = $stmt_reportes->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $ultimos_reportes = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes de Conducta · COBAEP 27</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../admin/style_dashboard_admin.css">
    <link rel="stylesheet" href="../Prefectura/style_dashboard_prefectura.css">
    <link rel="stylesheet" href="../Prefectura/style_creacion_reportes.css">
    
    <style>
        .overview-card .table-responsive { max-height: 450px; overflow-y: auto; }
        .overview-card .table-responsive thead th { position: sticky; top: 0; background-color: #ffffff; z-index: 10; box-shadow: 0 1px 0 var(--line); }
        .overview-card .table-responsive::-webkit-scrollbar { width: 6px; }
        .overview-card .table-responsive::-webkit-scrollbar-track { background: transparent; }
        .overview-card .table-responsive::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        .date-divider td { background-color: #f0fdf4 !important; color: var(--green-700) !important; font-weight: 800 !important; font-size: 0.85rem; padding: 0.8rem 1.5rem !important; border-bottom: 2px solid var(--green-soft) !important; text-transform: uppercase; }
        .action-buttons { display: flex; gap: 8px; justify-content: center; }
        .btn-action { border: none; background: transparent; width: 30px; height: 30px; border-radius: var(--radius-sm); display: grid; place-items: center; cursor: pointer; transition: 0.2s ease; color: var(--muted); text-decoration: none; font-size: 0.9rem; }
        .btn-edit:hover { background: #fef08a; color: #ca8a04; }
        .btn-delete:hover { background: #fee2e2; color: #dc2626; }
        #btnSubmit:disabled { background-color: #cbd5e1 !important; color: #64748b !important; cursor: not-allowed !important; box-shadow: none !important; }
        
        /* Ajuste para el custom-select dentro del modal para que no se oculte */
        #modalEditarReporte .custom-select-options { z-index: 2050; }
    </style>
</head>
<body>
    
    <aside class="sidebar" id="sidebar">
        <button id="collapseToggle" class="collapse-toggle" aria-label="Colapsar menú"><i class="fas fa-chevron-left"></i></button>
        <div class="sidebar-inner">
            <div class="brand"><img src="../img/LogoCobaep.png" alt="Logo COBAEP" class="brand-logo"><div class="brand-copy"><strong>SIGE<span>-Cobaep</span></strong><small>Plantel 27</small></div></div>
             <nav class="navigation" aria-label="Navegación principal">
                <span class="nav-heading">Menú principal</span>
<<<<<<< HEAD
                <a href="dashboard_prefectura.php" class="nav-item">
                    <i class="fas fa-shield-halved"></i>
                    <span>Control de Acceso</span>
                </a>
                <a href="historial_asistencias.php" class="nav-item">
                    <i class="fas fa-history"></i>
                    <span>Historial y Reportes</span>
                </a>
                <a href="creacion_reportes.php" class="nav-item active">
                    <i class="fa-solid fa-file-lines"></i>
                    <span>Creación de Reporte de Conducta</span>
                </a>  
                <!-- NUEVO MÓDULO EN EL MENÚ -->
                <a href="expediente_alumnos.php" class="nav-item">
                    <i class="fas fa-folder-open"></i>
                    <span>Expediente de Alumnos</span>
                </a>
=======
                <a href="dashboard_prefectura.php" class="nav-item"><i class="fas fa-shield-halved"></i><span>Control de Acceso</span></a>
                <a href="historial_asistencias.php" class="nav-item"><i class="fas fa-history"></i><span>Historial y Reportes</span></a>
                <a href="creacion_reportes.php" class="nav-item active"><i class="fa-solid fa-file-lines"></i><span>Creacion de Reporte</span></a>  
                <a href="expediente_alumnos.php" class="nav-item"><i class="fas fa-folder-open"></i><span>Expediente de Alumnos</span></a>
>>>>>>> 611881685e88626578473a6af3560137eaa1b192
            </nav>
             <div class="sidebar-bottom"><a href="../logout.php" class="logout"><i class="fas fa-arrow-right-from-bracket"></i><span>Cerrar Sesión</span></a></div>
        </div>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu" id="mobileMenu" type="button"><i class="fas fa-bars"></i></button>
                <div><span class="eyebrow">PREFECTURA · DISCIPLINA</span><h1>Reportes de Conducta</h1><p>Registra faltas disciplinarias y consulta el historial reciente.</p></div>
            </div>
            <div class="topbar-right">
                <div class="date-pill"><i class="far fa-calendar"></i><span id="fechaTexto"><?php echo date('d/m/Y H:i'); ?></span></div>
                <div class="profile"><div class="profile-avatar"><i class="fas fa-user-shield"></i></div><div class="profile-info"><strong><?php echo $nombre_usuario; ?></strong><span>Prefecto</span></div></div>
            </div>
        </header>

        <section class="reportes-container">
            <article class="form-reporte">
                <div class="card-heading" style="margin-bottom: 20px;">
                    <h2><i class="fas fa-file-signature" style="color: var(--green); margin-right: 8px;"></i> Nuevo Reporte de Conducta</h2>
                </div>
                <form action="procesos/guardar_reporte.php" method="POST" id="formReporte">
                    <div class="form-group">
                        <label><i class="fas fa-id-card"></i> Matrícula del Alumno</label>
                        <div class="input-group">
                            <input type="text" id="matricula" name="matricula" class="form-control" placeholder="Ej. 22014567" required autocomplete="off">
                            <button type="button" id="btnBuscarAlumno" class="btn-icon-search"><i class="fas fa-search"></i></button>
                        </div>
                    </div>

                    <div id="studentProfile" class="student-profile">
                        <div class="sp-avatar"><i class="fas fa-user"></i></div>
                        <div class="sp-info"><h4 id="spNombre"></h4><p id="spGrupo"></p></div>
                        <div class="sp-points">
                            <span style="display:block; font-size:0.6rem; color:var(--muted); font-weight:700;">Puntos</span>
                            <span class="pts-badge" id="spPuntos"></span>
                        </div>
                    </div>
                    <div id="studentError" class="student-profile error" style="display: none;">
                        <i class="fas fa-exclamation-triangle" style="margin-bottom: 5px; font-size:1.2rem;"></i><br>Alumno no encontrado.
                    </div>

                    <hr style="border: 0; border-top: 1px solid var(--line); margin: 20px 0;">

                    <div class="form-group">
                        <label><i class="fas fa-gavel"></i> Motivo / Regla Infringida</label>
                        <div class="custom-select-container">
                            <input type="hidden" name="id_tipo_falta" id="id_tipo_falta" required>
                            <div class="custom-select-trigger" id="customSelectTrigger">
                                <span id="triggerText">Selecciona o busca la falta...</span>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                            <div class="custom-select-options" id="customSelectOptions">
                                <div class="custom-select-search"><i class="fas fa-search"></i><input type="text" id="searchFalta" placeholder="Buscar regla..."></div>
                                <ul class="options-list" id="faltaList">
                                    <?php foreach ($tipos_falta as $falta): ?>
                                       <li class="option-item" data-value="<?php echo $falta['id_tipo_falta']; ?>" data-sancion="<?php echo $falta['tipo_sancion']; ?>">
                                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                                <div style="flex: 1; padding-right: 10px;"><strong>Regla <?php echo $falta['numero_reglamento']; ?>:</strong> <?php echo htmlspecialchars($falta['descripcion_falta']); ?></div>
                                                <div style="text-align: right; min-width: 60px;">
                                                    <?php if(!empty($falta['puntos_descuento'])): ?><span class="pts" style="display: block; margin-bottom: 4px; color:#b91c1c; font-weight:bold;">-<?php echo $falta['puntos_descuento']; ?> pts</span><?php endif; ?>
                                                    <?php if($falta['tipo_sancion'] === 'Suspension'): ?><span style="background: #000; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 0.65rem; font-weight: bold;">SUSPENSIÓN</span>
                                                    <?php elseif($falta['requiere_citacion_tutor'] == 1 || $falta['tipo_sancion'] === 'Citacion'): ?><span style="background: #fef08a; color: #a16207; padding: 2px 6px; border-radius: 4px; font-size: 0.65rem; font-weight: bold;">CITA TUTOR</span><?php endif; ?>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>

                        <!-- PANEL DE SUSPENSIÓN -->
                        <div id="suspensionConfig" style="display: none; background: #fff1f2; border: 1px solid #fecaca; padding: 15px; border-radius: var(--radius-sm); margin-top: 15px;">
                            <label style="color: #9f1239; font-weight: 700; margin-bottom: 10px; display: block; font-size: 0.85rem;"><i class="fas fa-user-lock"></i> Configurar Sanción</label>
                            <div style="display: flex; gap: 15px; align-items: center;">
                                <div style="flex: 1;">
                                    <label style="font-size: 0.75rem; color: #991b1b;">Días de Suspensión:</label>
                                    <input type="number" name="dias_suspension" id="dias_suspension" min="1" max="30" class="form-control" placeholder="Ej. 3" style="border-color: #fca5a5;">
                                </div>
                                <div style="flex: 1; display: flex; align-items: center; gap: 8px; margin-top: 15px;">
                                    <input type="checkbox" name="suspension_definitiva" id="suspension_definitiva" value="1" style="width: 18px; height: 18px; accent-color: #991b1b;">
                                    <label for="suspension_definitiva" style="font-size: 0.85rem; font-weight: 700; color: #991b1b; cursor: pointer; margin:0;">Baja Definitiva</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 15px;">
                        <label for="observaciones"><i class="fas fa-comment-dots"></i> Detalles (Opcional)</label>
                        <textarea id="observaciones" name="observaciones" class="form-control" style="resize:vertical; min-height:80px;" placeholder="Describe la situación..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" id="btnSubmit" style="width: 100%; margin-top: 10px;" disabled><i class="fas fa-paper-plane"></i> Emitir Reporte</button>
                </form>
            </article>

            <!-- TABLA RECIENTES -->
            <article class="overview-card" style="padding: 0; overflow: hidden; margin-top: 0;">
                <div class="card-heading" style="padding: 24px 24px 10px;">
                    <div><span class="section-kicker">HISTORIAL</span><h2>Últimos Reportes</h2></div>
                </div>
                <form method="GET" class="search-bar">
                    <input type="text" name="buscar" placeholder="Buscar por matrícula..." value="<?php echo htmlspecialchars($busqueda); ?>">
                    <button type="submit" class="btn-search"><i class="fas fa-search"></i></button>
                    <?php if(!empty($busqueda)): ?><a href="creacion_reportes.php" class="btn-search" style="text-decoration:none;"><i class="fas fa-times"></i></a><?php endif; ?>
                </form>

                <div class="table-responsive" style="margin-bottom: 0;">
                    <table>
                        <thead>
                            <tr><th>Hora</th><th>Alumno</th><th>Falta Cometida</th><th>Pts</th><th style="text-align: center;">Acciones</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ultimos_reportes)): ?>
                                <tr><td colspan="5" style="text-align: center; padding: 3rem;">No se encontraron reportes.</td></tr>
                            <?php else: 
                                $fecha_actual_agrupacion = '';
                                foreach ($ultimos_reportes as $reporte): 
                                    $fecha_fila_pura = date('Y-m-d', strtotime($reporte['fecha_hora']));
                                    $hora_formateada = date('h:i A', strtotime($reporte['fecha_hora']));
                                    
                                    if ($fecha_actual_agrupacion !== $fecha_fila_pura) {
                                        $fecha_actual_agrupacion = $fecha_fila_pura;
                                        echo '<tr class="date-divider"><td colspan="5"><i class="far fa-calendar-alt" style="margin-right: 8px;"></i> Reportes del día: ' . date('d/m/Y', strtotime($fecha_fila_pura)) . '</td></tr>';
                                    }
                            ?>
                            <tr>
                                <td style="font-size: 0.75rem; color: var(--muted);"><strong><?php echo $hora_formateada; ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($reporte['apellido_paterno'] . ' ' . $reporte['nombre']); ?></strong><br>
                                    <span style="font-size: 0.7rem; color: var(--muted);"><?php echo htmlspecialchars($reporte['matricula']); ?></span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars(mb_strimwidth($reporte['descripcion_falta'], 0, 35, '...')); ?><br>
                                    <?php if($reporte['tipo_sancion'] === 'Suspension'): ?><span style="display:inline-block; margin-top:2px; background: #000; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 0.65rem; font-weight: bold; text-transform: uppercase;">Suspensión</span>
                                    <?php elseif($reporte['requiere_citacion_tutor'] == 1 || $reporte['tipo_sancion'] === 'Citacion'): ?><span style="display:inline-block; margin-top:2px; background: #fef08a; color: #a16207; padding: 2px 6px; border-radius: 4px; font-size: 0.65rem; font-weight: bold; text-transform: uppercase;">Cita Tutor</span><?php endif; ?>
                                </td>
                                <td>
                                    <?php if(!empty($reporte['puntos_descontados']) && $reporte['puntos_descontados'] > 0): ?>
                                        <span class="status-badge-table status-falta">-<?php echo $reporte['puntos_descontados']; ?></span>
                                    <?php else: ?>
                                        <span style="color: var(--muted); font-size: 0.75rem; font-weight: 600; background: #f1f5f9; padding: 4px 8px; border-radius: 4px;">Sin Puntos</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button type="button" class="btn-action btn-edit" title="Editar reporte" 
                                            data-id="<?php echo $reporte['id_reporte']; ?>"
                                            data-alumno="<?php echo $reporte['id_alumno']; ?>"
                                            data-matricula="<?php echo htmlspecialchars($reporte['matricula']); ?>"
                                            data-nombre="<?php echo htmlspecialchars($reporte['apellido_paterno'] . ' ' . $reporte['nombre']); ?>"
                                            data-falta="<?php echo $reporte['id_tipo_falta']; ?>"
                                            data-obs="<?php echo htmlspecialchars($reporte['observaciones'] ?? ''); ?>"
                                            data-url="creacion_reportes.php"
                                            onclick="abrirModalEditar(this)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        
                                        <button type="button" class="btn-action btn-delete" title="Eliminar reporte" onclick="abrirModalEliminar(<?php echo $reporte['id_reporte']; ?>)">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

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
                                    <?php foreach ($tipos_falta as $falta): ?>
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
                    <input type="hidden" name="return_url" value="creacion_reportes.php">
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
            // Lógica del buscador original (Nuevo Reporte)
            const btnBuscar = document.getElementById('btnBuscarAlumno');
            const inputMatricula = document.getElementById('matricula');
            const studentProfile = document.getElementById('studentProfile');
            const studentError = document.getElementById('studentError');
            const btnSubmit = document.getElementById('btnSubmit');
            const inputFalta = document.getElementById('id_tipo_falta');
            const trigger = document.getElementById('customSelectTrigger');
            const optionsContainer = document.getElementById('customSelectOptions');
            const searchInput = document.getElementById('searchFalta');
            const optionsList = document.querySelectorAll('#faltaList .option-item');
            const triggerText = document.getElementById('triggerText');
            
            function validarFormulario() {
                if(studentProfile.classList.contains('active') && inputFalta.value !== "") btnSubmit.disabled = false;
                else btnSubmit.disabled = true;
            }

            function buscarAlumno() {
                let matricula = inputMatricula.value.trim();
                if (matricula.length > 0) {
                    btnBuscar.innerHTML = '<i class="fas fa-spinner fa-spin"></i>'; 
                    fetch('procesos/buscar_alumno.php?matricula=' + matricula)
                        .then(response => response.json())
                        .then(data => {
                            btnBuscar.innerHTML = '<i class="fas fa-search"></i>';
                            studentError.style.display = 'none';
                            if (data.success) {
                                document.getElementById('spNombre').textContent = data.nombre;
                                document.getElementById('spGrupo').textContent = data.grado_grupo || 'Sin asignar';
                                let ptsBadge = document.getElementById('spPuntos');
                                let pts = parseInt(data.puntos_restantes);
                                let estadoHtml = '';
                                if (pts > 75) { ptsBadge.className = 'pts-badge good'; estadoHtml = pts + ' pts'; } 
                                else if (pts <= 75 && pts > 60) { ptsBadge.className = 'pts-badge warning'; estadoHtml = pts + ' pts <br><span style="font-size:0.6rem; display:block; margin-top:3px; text-transform:uppercase;">Carta Compromiso</span>'; } 
                                else if (pts <= 60 && pts > 0) { ptsBadge.className = 'pts-badge danger'; estadoHtml = pts + ' pts <br><span style="font-size:0.6rem; display:block; margin-top:3px; text-transform:uppercase;">Condicionamiento</span>'; } 
                                else { ptsBadge.className = 'pts-badge critical'; estadoHtml = '0 pts <br><span style="font-size:0.6rem; display:block; margin-top:3px; text-transform:uppercase;">Suspensión</span>'; }
                                ptsBadge.innerHTML = estadoHtml;
                                studentProfile.classList.add('active');
                            } else {
                                studentProfile.classList.remove('active');
                                studentError.style.display = 'block';
                            }
                            validarFormulario();
                        }).catch(error => { console.error('Error:', error); btnBuscar.innerHTML = '<i class="fas fa-search"></i>'; });
                }
            }
            btnBuscar.addEventListener('click', buscarAlumno);
            inputMatricula.addEventListener('keypress', function(e) { if (e.key === 'Enter') { e.preventDefault(); buscarAlumno(); } });
            trigger.addEventListener('click', function() { optionsContainer.classList.toggle('open'); if(optionsContainer.classList.contains('open')) searchInput.focus(); });
            searchInput.addEventListener('input', function() { let filter = this.value.toLowerCase(); optionsList.forEach(option => { if(option.innerText.toLowerCase().includes(filter)) option.style.display = 'block'; else option.style.display = 'none'; }); });
            optionsList.forEach(option => {
                option.addEventListener('click', function() {
                    let val = this.getAttribute('data-value');
                    let tipoSancion = this.getAttribute('data-sancion'); 
                    let textContent = this.querySelector('strong').innerText + ' ' + this.innerText.split(':')[1].trim();
                    triggerText.textContent = textContent;
                    trigger.classList.add('selected');
                    inputFalta.value = val;
                    optionsContainer.classList.remove('open');
                    searchInput.value = "";
                    optionsList.forEach(opt => opt.style.display = 'block');
                    let configDiv = document.getElementById('suspensionConfig');
                    let inputDias = document.getElementById('dias_suspension');
                    let checkDefinitiva = document.getElementById('suspension_definitiva');
                    if (tipoSancion === 'Suspension') { configDiv.style.display = 'block'; inputDias.required = true; } 
                    else { configDiv.style.display = 'none'; inputDias.required = false; inputDias.value = ''; checkDefinitiva.checked = false; inputDias.disabled = false; }
                    validarFormulario();
                });
            });
            const checkDefinitiva = document.getElementById('suspension_definitiva');
            if (checkDefinitiva) { checkDefinitiva.addEventListener('change', function() { let inputDias = document.getElementById('dias_suspension'); if(this.checked) { inputDias.disabled = true; inputDias.required = false; inputDias.value = ''; } else { inputDias.disabled = false; inputDias.required = true; } }); }
            
            // Lógica del buscador del MODAL DE EDICIÓN
            const editTrigger = document.getElementById('editCustomSelectTrigger');
            const editOptionsContainer = document.getElementById('editCustomSelectOptions');
            const editSearchInput = document.getElementById('editSearchFalta');
            const editOptionsList = document.querySelectorAll('.edit-option-item');
            const editTriggerText = document.getElementById('editTriggerText');
            const editInputFalta = document.getElementById('edit_id_tipo_falta');

            editTrigger.addEventListener('click', function(e) { 
                e.stopPropagation(); // Evitar cerrar al hacer clic en sí mismo
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
                    optionsContainer.classList.remove('open'); 
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