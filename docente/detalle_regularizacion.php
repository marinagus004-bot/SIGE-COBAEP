<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../login.php");
    exit();
}

$id_docente = $_SESSION['id_usuario'];
$nombre_usuario = isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') : 'Docente';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: regularizaciones.php");
    exit();
}

$id_clase = (int)$_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM CLASES_REGULARIZACION WHERE id_clase = ? AND id_maestro = ?");
    $stmt->execute([$id_clase, $id_docente]);
    $clase = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$clase) {
        header("Location: regularizaciones.php?error=No tienes permiso para acceder a este espacio.");
        exit();
    }

    // Consulta de Actividades (Ya la tenías)
    $stmt_act = $pdo->prepare("SELECT * FROM ACTIVIDADES_REGULARIZACION WHERE id_clase = ? ORDER BY fecha_publicacion DESC");
    $stmt_act->execute([$id_clase]);
    $actividades = $stmt_act->fetchAll(PDO::FETCH_ASSOC);

    // NUEVO: Consulta de Bloques
    $stmt_bloques = $pdo->prepare("SELECT * FROM BLOQUES_REGULARIZACION WHERE id_clase = ? ORDER BY id_bloque ASC");
    $stmt_bloques->execute([$id_clase]);
    $bloques = $stmt_bloques->fetchAll(PDO::FETCH_ASSOC);

    // Grupos asignados al docente (por asignación de Control Escolar o por horario subido)
    $stmt_grupos = $pdo->prepare("
        SELECT g.id_grupo, g.semestre, g.nombre_grupo, g.turno,
               (SELECT COUNT(*) FROM GRUPO_ALUMNO ga INNER JOIN ALUMNOS a ON a.id_alumno = ga.id_alumno
                 WHERE ga.id_grupo = g.id_grupo AND ga.fecha_baja IS NULL AND a.estatus = 'Alta') AS total,
               (SELECT COUNT(*) FROM GRUPO_ALUMNO ga INNER JOIN CLASE_ALUMNO ca ON ca.id_alumno = ga.id_alumno AND ca.id_clase = ?
                 WHERE ga.id_grupo = g.id_grupo AND ga.fecha_baja IS NULL) AS inscritos
        FROM GRUPOS g
        WHERE g.id_grupo IN (SELECT id_grupo FROM GRUPO_MAESTRO WHERE id_maestro = ?
                             UNION SELECT id_grupo FROM HORARIOS_MAESTRO WHERE id_maestro = ?)
        ORDER BY g.semestre, g.nombre_grupo, g.turno
    ");
    $stmt_grupos->execute([$id_clase, $id_docente, $id_docente]);
    $grupos_docente = $stmt_grupos->fetchAll(PDO::FETCH_ASSOC);

    // Alumnos de esos grupos que aún no están inscritos
    $stmt_alumnos = $pdo->prepare("
        SELECT DISTINCT a.id_alumno, a.matricula, a.nombre, a.apellido_paterno, a.apellido_materno,
               CONCAT(g.semestre, '° ', g.nombre_grupo) AS grupo
        FROM ALUMNOS a
        INNER JOIN GRUPO_ALUMNO ga ON a.id_alumno = ga.id_alumno AND ga.fecha_baja IS NULL
        INNER JOIN GRUPOS g ON g.id_grupo = ga.id_grupo
        WHERE a.estatus = 'Alta'
          AND ga.id_grupo IN (SELECT id_grupo FROM GRUPO_MAESTRO WHERE id_maestro = ?
                              UNION SELECT id_grupo FROM HORARIOS_MAESTRO WHERE id_maestro = ?)
          AND a.id_alumno NOT IN (SELECT id_alumno FROM CLASE_ALUMNO WHERE id_clase = ?)
        ORDER BY a.apellido_paterno, a.apellido_materno, a.nombre
    ");
    $stmt_alumnos->execute([$id_docente, $id_docente, $id_clase]);
    $alumnos_disponibles = $stmt_alumnos->fetchAll(PDO::FETCH_ASSOC);

    // Alumnos inscritos al espacio
    $stmt_inscritos = $pdo->prepare("
        SELECT a.id_alumno, a.matricula, a.nombre, a.apellido_paterno, a.apellido_materno, ca.fecha_asignacion,
               (SELECT CONCAT(g.semestre, '° ', g.nombre_grupo, ' · ', g.turno) FROM GRUPO_ALUMNO ga
                  INNER JOIN GRUPOS g ON g.id_grupo = ga.id_grupo
                 WHERE ga.id_alumno = a.id_alumno AND ga.fecha_baja IS NULL LIMIT 1) AS grupo,
               (SELECT COUNT(*) FROM ENTREGAS_REGULARIZACION er WHERE er.id_clase = ca.id_clase AND er.id_alumno = a.id_alumno) AS entrego
        FROM CLASE_ALUMNO ca
        INNER JOIN ALUMNOS a ON a.id_alumno = ca.id_alumno
        WHERE ca.id_clase = ?
        ORDER BY a.apellido_paterno, a.apellido_materno, a.nombre
    ");
    $stmt_inscritos->execute([$id_clase]);
    $inscritos = $stmt_inscritos->fetchAll(PDO::FETCH_ASSOC);

    // Entregas recibidas
    $stmt_entregas = $pdo->prepare("
        SELECT e.id_entrega, e.archivo_ruta, e.fecha_entrega, e.calificacion,
               a.matricula, a.nombre, a.apellido_paterno
        FROM ENTREGAS_REGULARIZACION e
        INNER JOIN ALUMNOS a ON a.id_alumno = e.id_alumno
        WHERE e.id_clase = ?
        ORDER BY (e.calificacion IS NOT NULL), e.fecha_entrega DESC
    ");
    $stmt_entregas->execute([$id_clase]);
    $entregas = $stmt_entregas->fetchAll(PDO::FETCH_ASSOC);
    $pendientes_calificar = count(array_filter($entregas, fn($e) => $e['calificacion'] === null));

} catch (PDOException $e) {
    error_log("Error cargando detalle de regularización: " . $e->getMessage());
    header("Location: regularizaciones.php?error=Error al cargar el espacio.");
    exit();
}

$estilo_fondo = "background: linear-gradient(145deg, #10251d, #063f2b);";
if (!empty($clase['imagen_portada'])) {
    $ruta_img = '../' . $clase['imagen_portada'];
    $estilo_fondo = "background: linear-gradient(rgba(16, 37, 29, 0.6), rgba(16, 37, 29, 0.8)), url('" . htmlspecialchars($ruta_img) . "') center/cover no-repeat;";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#063f2b">
    <title><?php echo htmlspecialchars($clase['nombre_materia']); ?> · SIGE COBAEP</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <link rel="stylesheet" href="style_dashboard_docente.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="style_detalle_regularizacion.css?v=<?php echo time(); ?>">
</head>

<body>

    <!-- SIDEBAR (Misma estructura de siempre) -->
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
                <a href="horario.php" class="nav-item"><i class="fas fa-calendar-days"></i><span>Mi Horario</span></a>
                <a href="horarios_plantel.php" class="nav-item"><i class="fas fa-calendar-week"></i><span>Horarios del Plantel</span></a>
                <span class="nav-heading">Evaluación</span>
                <a href="regularizaciones.php" class="nav-item active"><i class="fas fa-book-open-reader"></i><span>Regularizaciones</span></a>
            </nav>
            <div class="sidebar-bottom">
                <div class="institution">
                    <div class="institution-icon"><i class="fas fa-building-columns"></i></div>
                    <div>
                        <strong>COBAEP Plantel 27</strong>
                        <span>Portal Docente</span>
                    </div>
                </div>
                <a href="../logout.php" class="logout">
                    <i class="fas fa-arrow-right-from-bracket"></i>
                    <span>Cerrar Sesión</span>
                </a>
            </div>
        </div>
    </aside>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content layout-espacio">
<?php
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$total_inscritos = count($inscritos);
$es_visible = (int)($clase['visible'] ?? 1) === 1;
$con_entrega = count(array_filter($inscritos, fn($i) => $i['entrego'] > 0));

/** Tarjeta de un material (se usa dentro de bloques y en "Materiales Generales") */
$render_material = function (array $act) use ($h) { ?>
                                        <div class="material-card">
                                            <div class="material-icon"><i class="fas fa-file-alt"></i></div>
                                            <div class="material-info">
                                                <h4><?php echo $h($act['titulo']); ?></h4>
                                                <?php if (!empty($act['descripcion'])): ?>
                                                    <p><?php echo nl2br($h($act['descripcion'])); ?></p>
                                                <?php endif; ?>
                                                <span class="date-posted">Publicado: <?php echo date('d/m/Y h:i A', strtotime($act['fecha_publicacion'])); ?></span>
                                            </div>
                                            <div class="material-actions">
                                                <?php if (!empty($act['archivo_ruta'])): ?>
                                                    <a href="../<?php echo $h($act['archivo_ruta']); ?>" target="_blank" class="btn-mat-action view" title="Ver / descargar archivo"><i class="fas fa-download"></i></a>
                                                <?php endif; ?>
                                                <button type="button" class="btn-mat-action edit js-editar-material" title="Editar material"
                                                    data-id="<?php echo (int)$act['id_actividad']; ?>"
                                                    data-titulo="<?php echo $h($act['titulo']); ?>"
                                                    data-descripcion="<?php echo $h($act['descripcion']); ?>"
                                                    data-bloque="<?php echo (int)$act['id_bloque']; ?>"
                                                    data-archivo="<?php echo $h($act['archivo_ruta'] ? basename($act['archivo_ruta']) : ''); ?>"><i class="fas fa-edit"></i></button>
                                                <button type="button" class="btn-mat-action delete js-confirmar" title="Eliminar material"
                                                    data-accion="eliminar_material" data-campo="id_actividad" data-valor="<?php echo (int)$act['id_actividad']; ?>"
                                                    data-titulo="¿Eliminar este material?"
                                                    data-mensaje="Se eliminará «<?php echo $h($act['titulo']); ?>» y su archivo adjunto. Esta acción no se puede deshacer."><i class="fas fa-trash"></i></button>
                                            </div>
                                        </div>
<?php }; ?>

        <!-- CABECERA (HERO) DEL ESPACIO -->
        <div class="espacio-hero" style="<?php echo $estilo_fondo; ?>">
            <div class="hero-top-bar">
                <a href="regularizaciones.php" class="btn-back"><i class="fas fa-arrow-left"></i> Volver</a>
                <div class="hero-actions">
                    <a href="regularizacion_alumnos.php?id=<?php echo $id_clase; ?>" class="btn-hero-action alumnos" title="Lista de alumnos y quién ya entregó"><i class="fas fa-clipboard-list"></i> Alumnos y entregas</a>
                    <?php if ($es_visible): ?>
                        <button type="button" class="btn-hero-action ocultar js-confirmar" title="Ocultar el curso a los alumnos"
                            data-accion="toggle_visible" data-campo="visible" data-valor="0" data-boton="Sí, ocultar" data-icono="fa-eye-slash"
                            data-titulo="¿Ocultar este curso?"
                            data-mensaje="Los alumnos dejarán de verlo y se cerrará la recepción de trabajos. Las entregas y calificaciones se conservan y puedes volver a mostrarlo cuando quieras."><i class="fas fa-eye-slash"></i> Ocultar curso</button>
                    <?php else: ?>
                        <form action="procesos/regularizacion_acciones.php" method="POST" style="display:inline;">
                            <input type="hidden" name="accion" value="toggle_visible">
                            <input type="hidden" name="id_clase" value="<?php echo $id_clase; ?>">
                            <input type="hidden" name="visible" value="1">
                            <button type="submit" class="btn-hero-action mostrar" title="Volver a mostrar el curso a los alumnos"><i class="fas fa-eye"></i> Mostrar curso</button>
                        </form>
                    <?php endif; ?>
                    <button type="button" class="btn-hero-action edit" data-open="modalEditarEspacio" title="Editar información del espacio"><i class="fas fa-edit"></i> Editar</button>
                    <button type="button" class="btn-hero-action delete js-confirmar" title="Eliminar espacio"
                        data-accion="eliminar_espacio" data-titulo="¿Eliminar este espacio?"
                        data-mensaje="Se borrarán los bloques, materiales, entregas e inscripciones de «<?php echo $h($clase['nombre_materia']); ?>». Esta acción no se puede deshacer."><i class="fas fa-trash"></i> Eliminar</button>
                </div>
            </div>

            <div class="hero-content">
                <h1><?php echo $h($clase['nombre_materia']); ?></h1>
                <p><?php echo nl2br($h($clase['descripcion'])); ?></p>
            </div>
            <div class="hero-badges">
                <?php if (!empty($clase['fecha_fin'])): ?>
                    <span class="badge-date"><i class="far fa-calendar"></i> Límite: <?php echo date('d/m/Y', strtotime($clase['fecha_fin'])); ?></span>
                <?php endif; ?>
                <span class="badge-status <?php echo strtolower($clase['estatus']); ?>"><?php echo $h($clase['estatus']); ?></span>
                <span class="badge-date"><i class="fas fa-users"></i> <?php echo $total_inscritos; ?> inscritos</span>
                <?php if (!$es_visible): ?><span class="badge-oculto"><i class="fas fa-eye-slash"></i> Oculto para los alumnos</span><?php endif; ?>
            </div>
        </div>

        <?php if (isset($_GET['exito'])): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $h($_GET['exito']); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $h($_GET['error']); ?></div>
        <?php endif; ?>

        <?php if (!$es_visible): ?>
            <div class="alert aviso-oculto"><i class="fas fa-eye-slash"></i> Este curso está <b>oculto</b>: los alumnos no lo ven ni pueden entregar. Usa <b>Mostrar curso</b> para publicarlo de nuevo.</div>
        <?php endif; ?>

        <div class="espacio-layout">
            <!-- COLUMNA IZQUIERDA: MATERIALES Y ACTIVIDADES -->
            <div class="col-main">
                <div class="section-header">
                    <h2><i class="fas fa-layer-group"></i> Materiales y Actividades</h2>
                    <div class="section-actions">
                        <button type="button" class="btn-outline-action" id="btnCrearBloque" data-open="modalBloque"><i class="fas fa-folder-plus"></i> Crear Bloque</button>
                        <button type="button" class="btn-add-material" id="btnAbrirModalMaterial" data-open="modalMaterial"><i class="fas fa-plus"></i> Subir Material</button>
                    </div>
                </div>

                <div class="materiales-list">
                    <?php if (empty($bloques) && empty($actividades)): ?>
                        <div class="empty-materiales">
                            <i class="fas fa-folder-open"></i>
                            <h3>Espacio vacío</h3>
                            <p>Crea un bloque o sube material para comenzar a organizar tu clase.</p>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($bloques as $bloque): ?>
                        <div class="bloque-container">
                            <div class="bloque-header">
                                <h3><i class="fas fa-folder"></i> <?php echo $h($bloque['titulo_bloque']); ?></h3>
                                <div class="material-actions">
                                    <button type="button" class="btn-mat-action edit js-editar-bloque" title="Renombrar bloque"
                                        data-id="<?php echo (int)$bloque['id_bloque']; ?>" data-titulo="<?php echo $h($bloque['titulo_bloque']); ?>"><i class="fas fa-edit"></i></button>
                                    <button type="button" class="btn-mat-action delete js-confirmar" title="Eliminar bloque"
                                        data-accion="eliminar_bloque" data-campo="id_bloque" data-valor="<?php echo (int)$bloque['id_bloque']; ?>"
                                        data-titulo="¿Eliminar este bloque?"
                                        data-mensaje="Se eliminará el bloque «<?php echo $h($bloque['titulo_bloque']); ?>». Su material no se borra: pasa a Materiales Generales."><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                            <div class="bloque-body">
                                <?php $acts_bloque = array_filter($actividades, fn($a) => $a['id_bloque'] == $bloque['id_bloque']); ?>
                                <?php if (empty($acts_bloque)): ?>
                                    <p class="empty-text">No hay materiales en este bloque.</p>
                                <?php else: foreach ($acts_bloque as $act) $render_material($act); endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php $acts_generales = array_filter($actividades, fn($a) => empty($a['id_bloque'])); ?>
                    <?php if (!empty($acts_generales)): ?>
                        <h3 class="general-title">Materiales Generales</h3>
                        <?php foreach ($acts_generales as $act) $render_material($act); ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- COLUMNA DERECHA: CONFIGURACIÓN, ALUMNOS Y ENTREGAS -->
            <div class="col-side">

                <div class="side-card config-card">
                    <h3><i class="fas fa-cog"></i> Configuración</h3>
                    <div class="toggle-container">
                        <div class="toggle-text">
                            <strong>Recepción de Trabajos</strong>
                            <p id="toggleEstadoTexto"><?php echo ((int)$clase['permitir_entregas'] === 1) ? 'Abierta: los alumnos pueden subir archivos.' : 'Cerrada: los alumnos no pueden subir archivos.'; ?></p>
                        </div>
                        <label class="switch" title="Abrir o cerrar la recepción de trabajos">
                            <input type="checkbox" id="toggleEntregas" <?php echo ((int)$clase['permitir_entregas'] === 1) ? 'checked' : ''; ?>>
                            <span class="slider round"></span>
                        </label>
                    </div>
                </div>

                <div class="side-card">
                    <h3><i class="fas fa-users"></i> Gestión de Alumnos</h3>
                    <p class="side-desc">Agrega a los alumnos irregulares a este espacio para que puedan ver el material.</p>
                    <div class="side-stats">
                        <div><strong><?php echo $total_inscritos; ?></strong><span>Inscritos</span></div>
                        <div><strong><?php echo $con_entrega; ?></strong><span>Entregaron</span></div>
                        <div><strong><?php echo max(0, $total_inscritos - $con_entrega); ?></strong><span>Pendientes</span></div>
                    </div>
                    <div class="side-buttons">
                        <button type="button" class="btn-outline-full" id="btnAbrirModalAlumno" data-open="modalAlumno"><i class="fas fa-user-plus"></i> Inscribir Alumno</button>
                        <button type="button" class="btn-outline-full" data-open="modalGrupo"><i class="fas fa-users-viewfinder"></i> Vincular Grupo</button>
                        <button type="button" class="btn-outline-full btn-soft" data-open="modalInscritos"><i class="fas fa-list"></i> Ver Lista de Inscritos (<?php echo $total_inscritos; ?>)</button>
                        <a href="regularizacion_alumnos.php?id=<?php echo $id_clase; ?>" class="btn-outline-full"><i class="fas fa-clipboard-check"></i> Ver quién ya entregó</a>
                    </div>
                </div>

                <div class="side-card">
                    <h3><i class="fas fa-inbox"></i> Entregas Recibidas
                        <?php if ($pendientes_calificar > 0): ?><span class="count-pill"><?php echo $pendientes_calificar; ?> por calificar</span><?php endif; ?>
                    </h3>
                    <p class="side-desc">Revisa y califica (0 a 10) los trabajos subidos por los alumnos.</p>
                    <?php if (empty($entregas)): ?>
                        <div class="trabajos-empty">
                            <i class="fas fa-box-open"></i>
                            <span>Sin entregas aún</span>
                            <?php if ((int)$clase['permitir_entregas'] !== 1): ?>
                                <small>Activa la <b>Recepción de Trabajos</b> para que los alumnos puedan entregar.</small>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="entregas-list">
                            <?php foreach ($entregas as $e): ?>
                                <div class="entrega-item <?php echo $e['calificacion'] === null ? 'pendiente' : 'calificada'; ?>">
                                    <div class="entrega-info">
                                        <strong><?php echo $h($e['apellido_paterno'] . ' ' . $e['nombre']); ?></strong>
                                        <span><?php echo $h($e['matricula']); ?> · <?php echo date('d/m/Y h:i a', strtotime($e['fecha_entrega'])); ?></span>
                                        <a href="../<?php echo $h($e['archivo_ruta']); ?>" target="_blank"><i class="fas fa-paperclip"></i> <?php echo $h(preg_replace('/^entrega_[a-z0-9]+_\d+_/i', '', basename($e['archivo_ruta']))); ?></a>
                                    </div>
                                    <form action="procesos/regularizacion_acciones.php" method="POST" class="form-calificar">
                                        <input type="hidden" name="accion" value="calificar_entrega">
                                        <input type="hidden" name="id_clase" value="<?php echo $id_clase; ?>">
                                        <input type="hidden" name="id_entrega" value="<?php echo (int)$e['id_entrega']; ?>">
                                        <input type="number" name="calificacion" min="0" max="10" step="0.1" placeholder="—" value="<?php echo $e['calificacion'] !== null ? $h(rtrim(rtrim($e['calificacion'], '0'), '.')) : ''; ?>" aria-label="Calificación">
                                        <button type="submit" title="Guardar calificación"><i class="fas fa-check"></i></button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </main>

    <!-- MODAL: SUBIR MATERIAL -->
    <div class="modal-overlay" id="modalMaterial">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-file-upload"></i> Subir Material</h2>
                <button type="button" class="close-modal" data-close><i class="fas fa-times"></i></button>
            </div>
            <form action="../procesos/guardar_material.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id_clase" value="<?php echo $id_clase; ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="titulo">Título del Material</label>
                        <input type="text" name="titulo" id="titulo" class="input-modern" placeholder="Ej. Guía de Estudio Bloque 1" required>
                    </div>
                    <div class="form-group">
                        <label for="descripcion_mat">Instrucciones (Opcional)</label>
                        <textarea name="descripcion" id="descripcion_mat" class="input-modern" rows="3" placeholder="Instrucciones para el alumno..."></textarea>
                    </div>
                    <div class="form-group">
                        <label for="id_bloque">Asignar al Bloque (Opcional)</label>
                        <select name="id_bloque" id="id_bloque" class="input-modern">
                            <option value="">-- Material General (Sin bloque) --</option>
                            <?php foreach ($bloques as $b): ?>
                                <option value="<?php echo (int)$b['id_bloque']; ?>"><?php echo $h($b['titulo_bloque']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="archivo"><i class="fas fa-paperclip"></i> Adjuntar Archivo (PDF, DOCX, PPTX, JPG)</label>
                        <input type="file" name="archivo" id="archivo" class="input-modern">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancelar" data-close>Cancelar</button>
                    <button type="submit" class="btn-guardar"><i class="fas fa-upload"></i> Publicar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: EDITAR MATERIAL -->
    <div class="modal-overlay" id="modalEditarMaterial">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-pen-to-square"></i> Editar Material</h2>
                <button type="button" class="close-modal" data-close><i class="fas fa-times"></i></button>
            </div>
            <form action="procesos/regularizacion_acciones.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="editar_material">
                <input type="hidden" name="id_clase" value="<?php echo $id_clase; ?>">
                <input type="hidden" name="id_actividad" id="em_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="em_titulo">Título del Material</label>
                        <input type="text" name="titulo" id="em_titulo" class="input-modern" required>
                    </div>
                    <div class="form-group">
                        <label for="em_descripcion">Instrucciones</label>
                        <textarea name="descripcion" id="em_descripcion" class="input-modern" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="em_bloque">Bloque</label>
                        <select name="id_bloque" id="em_bloque" class="input-modern">
                            <option value="">-- Material General (Sin bloque) --</option>
                            <?php foreach ($bloques as $b): ?>
                                <option value="<?php echo (int)$b['id_bloque']; ?>"><?php echo $h($b['titulo_bloque']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="em_archivo"><i class="fas fa-paperclip"></i> Reemplazar archivo (opcional)</label>
                        <p class="archivo-actual" id="em_archivo_actual"></p>
                        <input type="file" name="archivo" id="em_archivo" class="input-modern">
                        <label class="check-inline" id="em_quitar_wrap"><input type="checkbox" name="quitar_archivo" value="1"> Quitar el archivo actual</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancelar" data-close>Cancelar</button>
                    <button type="submit" class="btn-guardar"><i class="fas fa-save"></i> Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: CREAR BLOQUE -->
    <div class="modal-overlay" id="modalBloque">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-folder-plus"></i> Crear Nuevo Bloque</h2>
                <button type="button" class="close-modal" data-close><i class="fas fa-times"></i></button>
            </div>
            <form action="../procesos/guardar_bloque.php" method="POST">
                <input type="hidden" name="id_clase" value="<?php echo $id_clase; ?>">
                <div class="modal-body">
                    <p class="modal-instruction">Los bloques te ayudan a organizar el material por semanas, temas o unidades.</p>
                    <div class="form-group">
                        <label for="titulo_bloque">Título del Bloque</label>
                        <input type="text" name="titulo_bloque" id="titulo_bloque" class="input-modern" placeholder="Ej. Semana 1: Introducción" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancelar" data-close>Cancelar</button>
                    <button type="submit" class="btn-guardar"><i class="fas fa-save"></i> Guardar Bloque</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: RENOMBRAR BLOQUE -->
    <div class="modal-overlay" id="modalEditarBloque">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-folder"></i> Renombrar Bloque</h2>
                <button type="button" class="close-modal" data-close><i class="fas fa-times"></i></button>
            </div>
            <form action="procesos/regularizacion_acciones.php" method="POST">
                <input type="hidden" name="accion" value="editar_bloque">
                <input type="hidden" name="id_clase" value="<?php echo $id_clase; ?>">
                <input type="hidden" name="id_bloque" id="eb_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="eb_titulo">Título del Bloque</label>
                        <input type="text" name="titulo_bloque" id="eb_titulo" class="input-modern" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancelar" data-close>Cancelar</button>
                    <button type="submit" class="btn-guardar"><i class="fas fa-save"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: EDITAR ESPACIO -->
    <div class="modal-overlay" id="modalEditarEspacio">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Editar Espacio</h2>
                <button type="button" class="close-modal" data-close><i class="fas fa-times"></i></button>
            </div>
            <form action="procesos/regularizacion_acciones.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="accion" value="editar_espacio">
                <input type="hidden" name="id_clase" value="<?php echo $id_clase; ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="ee_nombre">Nombre de la materia</label>
                        <input type="text" name="nombre_materia" id="ee_nombre" class="input-modern" value="<?php echo $h($clase['nombre_materia']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="ee_desc">Descripción</label>
                        <textarea name="descripcion" id="ee_desc" class="input-modern" rows="3"><?php echo $h($clase['descripcion']); ?></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="ee_inicio">Fecha de inicio</label>
                            <input type="date" name="fecha_inicio" id="ee_inicio" class="input-modern" value="<?php echo $h($clase['fecha_inicio']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="ee_fin">Fecha límite</label>
                            <input type="date" name="fecha_fin" id="ee_fin" class="input-modern" value="<?php echo $h($clase['fecha_fin']); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="ee_estatus">Estatus</label>
                        <select name="estatus" id="ee_estatus" class="input-modern">
                            <?php foreach (['Activa', 'Finalizada', 'Cancelada'] as $op): ?>
                                <option value="<?php echo $op; ?>" <?php echo $clase['estatus'] === $op ? 'selected' : ''; ?>><?php echo $op; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="ee_portada"><i class="fas fa-image"></i> Cambiar portada (JPG, PNG o WEBP)</label>
                        <input type="file" name="imagen_portada" id="ee_portada" class="input-modern" accept=".jpg,.jpeg,.png,.webp">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancelar" data-close>Cancelar</button>
                    <button type="submit" class="btn-guardar"><i class="fas fa-save"></i> Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: INSCRIBIR ALUMNO -->
    <div class="modal-overlay" id="modalAlumno">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-plus"></i> Inscribir Alumno</h2>
                <button type="button" class="close-modal" data-close><i class="fas fa-times"></i></button>
            </div>
            <form action="../procesos/inscribir_alumno.php" method="POST">
                <input type="hidden" name="id_clase" value="<?php echo $id_clase; ?>">
                <div class="modal-body">
                    <p class="modal-instruction">Selecciona al alumno de tus grupos que necesita regularización.</p>
                    <div class="form-group">
                        <label for="buscarAlumno">Buscar por nombre o matrícula</label>
                        <input type="search" id="buscarAlumno" class="input-modern" placeholder="Escribe para filtrar...">
                    </div>
                    <div class="form-group">
                        <label for="id_alumno">Alumno</label>
                        <select name="id_alumno" id="id_alumno" class="input-modern" required size="6">
                            <?php if (empty($alumnos_disponibles)): ?>
                                <option value="" disabled>No tienes alumnos disponibles para inscribir.</option>
                            <?php else: foreach ($alumnos_disponibles as $alum): ?>
                                <option value="<?php echo (int)$alum['id_alumno']; ?>">
                                    <?php echo $h($alum['matricula'] . ' · ' . $alum['apellido_paterno'] . ' ' . $alum['apellido_materno'] . ' ' . $alum['nombre'] . ' (' . $alum['grupo'] . ')'); ?>
                                </option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancelar" data-close>Cancelar</button>
                    <button type="submit" class="btn-guardar"><i class="fas fa-check"></i> Inscribir</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: VINCULAR GRUPO -->
    <div class="modal-overlay" id="modalGrupo">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-users-viewfinder"></i> Vincular Grupo</h2>
                <button type="button" class="close-modal" data-close><i class="fas fa-times"></i></button>
            </div>
            <form action="procesos/regularizacion_acciones.php" method="POST">
                <input type="hidden" name="accion" value="vincular_grupo">
                <input type="hidden" name="id_clase" value="<?php echo $id_clase; ?>">
                <div class="modal-body">
                    <p class="modal-instruction">Inscribe de una sola vez a todos los alumnos activos de uno de tus grupos. Los que ya estén inscritos no se duplican.</p>
                    <?php if (empty($grupos_docente)): ?>
                        <p class="empty-text">No tienes grupos asignados. Control Escolar debe asignarte grupos primero.</p>
                    <?php else: ?>
                        <div class="grupo-opciones">
                            <?php foreach ($grupos_docente as $i => $g): $completo = $g['total'] > 0 && $g['inscritos'] >= $g['total']; ?>
                                <label class="grupo-opcion <?php echo $completo ? 'completo' : ''; ?>">
                                    <input type="radio" name="id_grupo" value="<?php echo (int)$g['id_grupo']; ?>" required <?php echo $completo ? 'disabled' : ''; ?>>
                                    <span class="grupo-nombre"><?php echo (int)$g['semestre']; ?>° "<?php echo $h($g['nombre_grupo']); ?>" · <?php echo $h($g['turno']); ?></span>
                                    <span class="grupo-meta"><?php echo $completo ? 'Ya vinculado' : ((int)$g['inscritos'] . ' de ' . (int)$g['total'] . ' inscritos'); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancelar" data-close>Cancelar</button>
                    <button type="submit" class="btn-guardar" <?php echo empty($grupos_docente) ? 'disabled' : ''; ?>><i class="fas fa-link"></i> Vincular</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: LISTA DE INSCRITOS -->
    <div class="modal-overlay" id="modalInscritos">
        <div class="modal-content modal-wide">
            <div class="modal-header">
                <h2><i class="fas fa-list"></i> Alumnos Inscritos (<?php echo $total_inscritos; ?>)</h2>
                <button type="button" class="close-modal" data-close><i class="fas fa-times"></i></button>
            </div>
            <div class="modal-body">
                <?php if (empty($inscritos)): ?>
                    <p class="empty-text">Todavía no hay alumnos inscritos. Usa <b>Inscribir Alumno</b> o <b>Vincular Grupo</b>.</p>
                <?php else: ?>
                    <input type="search" id="buscarInscrito" class="input-modern" placeholder="Buscar por nombre o matrícula...">
                    <div class="tabla-inscritos">
                        <table>
                            <thead><tr><th>Alumno</th><th>Grupo</th><th>Entrega</th><th></th></tr></thead>
                            <tbody>
                                <?php foreach ($inscritos as $al): ?>
                                    <tr>
                                        <td><strong><?php echo $h($al['apellido_paterno'] . ' ' . $al['apellido_materno'] . ' ' . $al['nombre']); ?></strong><small><?php echo $h($al['matricula']); ?></small></td>
                                        <td><?php echo $h($al['grupo'] ?? '—'); ?></td>
                                        <td><?php echo $al['entrego'] ? '<span class="tag ok">Entregó</span>' : '<span class="tag pend">Pendiente</span>'; ?></td>
                                        <td>
                                            <button type="button" class="btn-mat-action delete js-confirmar" title="Dar de baja del espacio"
                                                data-accion="quitar_alumno" data-campo="id_alumno" data-valor="<?php echo (int)$al['id_alumno']; ?>"
                                                data-titulo="¿Dar de baja a este alumno?"
                                                data-mensaje="<?php echo $h($al['nombre'] . ' ' . $al['apellido_paterno']); ?> dejará de ver el material de este espacio."><i class="fas fa-user-minus"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- MODAL: CONFIRMAR ACCIÓN (eliminar / dar de baja) -->
    <div class="modal-overlay" id="modalConfirmar">
        <div class="modal-content modal-confirm">
            <form action="procesos/regularizacion_acciones.php" method="POST">
                <input type="hidden" name="accion" id="conf_accion">
                <input type="hidden" name="id_clase" value="<?php echo $id_clase; ?>">
                <input type="hidden" id="conf_campo">
                <div class="modal-body">
                    <div class="confirm-icon"><i class="fas fa-triangle-exclamation"></i></div>
                    <h2 id="conf_titulo">¿Confirmar?</h2>
                    <p id="conf_mensaje"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancelar" data-close>Cancelar</button>
                    <button type="submit" class="btn-peligro" id="conf_boton"><i class="fas fa-trash"></i> <span>Sí, continuar</span></button>
                </div>
            </form>
        </div>
    </div>

    <div class="toast" id="toast" role="status" aria-live="polite"></div>

    <script src="dashboard_maestro.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const ID_CLASE = <?php echo (int)$id_clase; ?>;

        // ---------- Modales ----------
        function abrir(id) {
            const m = document.getElementById(id);
            if (!m) return;
            m.classList.add('active');
            document.body.style.overflow = 'hidden';
            const foco = m.querySelector('input:not([type=hidden]):not([disabled]), select, textarea');
            if (foco) setTimeout(() => foco.focus(), 150);
        }
        function cerrar(m) {
            m.classList.remove('active');
            if (!document.querySelector('.modal-overlay.active')) document.body.style.overflow = '';
        }
        document.querySelectorAll('[data-open]').forEach(b => b.addEventListener('click', e => { e.preventDefault(); abrir(b.dataset.open); }));
        document.querySelectorAll('.modal-overlay').forEach(m => {
            m.addEventListener('click', e => { if (e.target === m || e.target.closest('[data-close]')) cerrar(m); });
        });
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.active').forEach(cerrar);
        });

        // ---------- Confirmaciones (eliminar espacio / bloque / material, baja de alumno) ----------
        document.querySelectorAll('.js-confirmar').forEach(b => b.addEventListener('click', () => {
            document.getElementById('conf_accion').value = b.dataset.accion;
            const campo = document.getElementById('conf_campo');
            campo.name = b.dataset.campo || '';
            campo.value = b.dataset.valor || '';
            document.getElementById('conf_titulo').textContent = b.dataset.titulo;
            document.getElementById('conf_mensaje').textContent = b.dataset.mensaje;
            const boton = document.getElementById('conf_boton');
            boton.querySelector('span').textContent = b.dataset.boton || 'Sí, continuar';
            boton.querySelector('i').className = 'fas ' + (b.dataset.icono || 'fa-trash');
            boton.classList.toggle('neutro', b.dataset.accion === 'toggle_visible');
            abrir('modalConfirmar');
        }));

        // ---------- Editar bloque / material ----------
        document.querySelectorAll('.js-editar-bloque').forEach(b => b.addEventListener('click', () => {
            document.getElementById('eb_id').value = b.dataset.id;
            document.getElementById('eb_titulo').value = b.dataset.titulo;
            abrir('modalEditarBloque');
        }));
        document.querySelectorAll('.js-editar-material').forEach(b => b.addEventListener('click', () => {
            document.getElementById('em_id').value = b.dataset.id;
            document.getElementById('em_titulo').value = b.dataset.titulo;
            document.getElementById('em_descripcion').value = b.dataset.descripcion;
            document.getElementById('em_bloque').value = b.dataset.bloque !== '0' ? b.dataset.bloque : '';
            document.getElementById('em_archivo').value = '';
            const actual = document.getElementById('em_archivo_actual');
            const quitar = document.getElementById('em_quitar_wrap');
            quitar.querySelector('input').checked = false;
            if (b.dataset.archivo) {
                actual.innerHTML = '<i class="fas fa-file"></i> Archivo actual: ';
                actual.appendChild(document.createTextNode(b.dataset.archivo.replace(/^mat_[a-z0-9]+_/i, '')));
                quitar.style.display = '';
            } else {
                actual.textContent = 'Este material no tiene archivo adjunto.';
                quitar.style.display = 'none';
            }
            abrir('modalEditarMaterial');
        }));

        // ---------- Filtros de búsqueda ----------
        const buscarAlumno = document.getElementById('buscarAlumno');
        if (buscarAlumno) buscarAlumno.addEventListener('input', () => {
            const q = buscarAlumno.value.toLowerCase();
            document.querySelectorAll('#id_alumno option').forEach(o => { o.hidden = q && !o.textContent.toLowerCase().includes(q); });
        });
        const buscarInscrito = document.getElementById('buscarInscrito');
        if (buscarInscrito) buscarInscrito.addEventListener('input', () => {
            const q = buscarInscrito.value.toLowerCase();
            document.querySelectorAll('.tabla-inscritos tbody tr').forEach(tr => { tr.hidden = q && !tr.textContent.toLowerCase().includes(q); });
        });

        // ---------- Recepción de trabajos (se guarda al instante) ----------
        const toast = document.getElementById('toast');
        function avisar(msg, error) {
            toast.textContent = msg;
            toast.className = 'toast show' + (error ? ' error' : '');
            clearTimeout(avisar.t);
            avisar.t = setTimeout(() => toast.className = 'toast', 3000);
        }
        const toggle = document.getElementById('toggleEntregas');
        toggle.addEventListener('change', async () => {
            const valor = toggle.checked ? 1 : 0;
            toggle.disabled = true;
            try {
                const datos = new FormData();
                datos.append('accion', 'toggle_entregas');
                datos.append('id_clase', ID_CLASE);
                datos.append('valor', valor);
                const r = await fetch('procesos/regularizacion_acciones.php', { method: 'POST', body: datos });
                const j = await r.json();
                if (!j.success) throw new Error(j.message);
                document.getElementById('toggleEstadoTexto').textContent = valor
                    ? 'Abierta: los alumnos pueden subir archivos.'
                    : 'Cerrada: los alumnos no pueden subir archivos.';
                avisar(j.message);
            } catch (err) {
                toggle.checked = !toggle.checked;
                avisar(err.message || 'No se pudo guardar el cambio.', true);
            } finally {
                toggle.disabled = false;
            }
        });
    });
    </script>
</body>
</html>
