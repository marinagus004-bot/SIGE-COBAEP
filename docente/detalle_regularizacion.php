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

    $stmt_alumnos = $pdo->prepare("
        SELECT DISTINCT a.id_alumno, a.matricula, a.nombre, a.apellido_paterno, a.apellido_materno
        FROM ALUMNOS a
        INNER JOIN GRUPO_ALUMNO ga ON a.id_alumno = ga.id_alumno
        INNER JOIN HORARIOS_MAESTRO hm ON ga.id_grupo = hm.id_grupo
        WHERE hm.id_maestro = ?
        AND a.id_alumno NOT IN (
            SELECT id_alumno FROM CLASE_ALUMNO WHERE id_clase = ?
        )
        ORDER BY a.apellido_paterno, a.apellido_materno
    ");
    $stmt_alumnos->execute([$id_docente, $id_clase]);
    $alumnos_disponibles = $stmt_alumnos->fetchAll(PDO::FETCH_ASSOC);

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

        <!-- CABECERA (HERO) DEL ESPACIO -->
        <div class="espacio-hero" style="<?php echo $estilo_fondo; ?>">
            <div class="hero-top-bar" style="display: flex; justify-content: space-between; align-items: flex-start; position: relative; z-index: 2;">
                <a href="regularizaciones.php" class="btn-back"><i class="fas fa-arrow-left"></i> Volver</a>
                
                <!-- NUEVO: BOTONERA DE GESTIÓN DEL ESPACIO -->
                <div class="hero-actions" style="display: flex; gap: 10px;">
                    <button class="btn-hero-action edit" title="Editar Info del Espacio"><i class="fas fa-edit"></i> Editar</button>
                    <button class="btn-hero-action delete" title="Eliminar Espacio"><i class="fas fa-trash"></i> Eliminar</button>
                </div>
            </div>

            <div class="hero-content">
                <h1><?php echo htmlspecialchars($clase['nombre_materia']); ?></h1>
                <p><?php echo nl2br(htmlspecialchars($clase['descripcion'])); ?></p>
            </div>
            <div class="hero-badges">
                <span class="badge-date"><i class="far fa-calendar"></i> Límite: <?php echo date('d/m/Y', strtotime($clase['fecha_fin'])); ?></span>
                <span class="badge-status <?php echo strtolower($clase['estatus']); ?>"><?php echo htmlspecialchars($clase['estatus']); ?></span>
            </div>
        </div>

        <?php if (isset($_GET['exito'])): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['exito']); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error" style="background: #fef2f2; color: #991b1b; padding: 15px; border-radius: 12px; margin-bottom: 25px;"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <div class="espacio-layout">
            <!-- COLUMNA IZQUIERDA: MATERIALES Y ACTIVIDADES -->
            <div class="col-main">
                
                <div class="section-header">
                    <h2><i class="fas fa-layer-group"></i> Materiales y Actividades</h2>
                    <div style="display: flex; gap: 10px;">
                        <!-- NUEVO: BOTÓN CREAR BLOQUE -->
                        <button class="btn-outline-action" id="btnCrearBloque"><i class="fas fa-folder-plus"></i> Crear Bloque</button>
                        <button class="btn-add-material" id="btnAbrirModalMaterial"><i class="fas fa-plus"></i> Subir Material</button>
                    </div>
                </div>

                <div class="materiales-list">
                    
                    <?php if(empty($bloques) && empty($actividades)): ?>
                        <div class="empty-materiales">
                            <i class="fas fa-folder-open"></i>
                            <h3>Espacio vacío</h3>
                            <p>Crea un bloque o sube material para comenzar a organizar tu clase.</p>
                        </div>
                    <?php endif; ?>

                    <!-- PINTAR LOS BLOQUES Y SUS ACTIVIDADES -->
                    <?php foreach($bloques as $bloque): ?>
                        <div class="bloque-container">
                            <div class="bloque-header">
                                <h3><i class="fas fa-folder"></i> <?php echo htmlspecialchars($bloque['titulo_bloque']); ?></h3>
                                <div style="display: flex; gap: 8px;">
                                    <button class="btn-mat-action edit" title="Editar Bloque"><i class="fas fa-edit"></i></button>
                                    <button class="btn-mat-action delete" title="Eliminar Bloque"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                            <div class="bloque-body">
                                <?php 
                                // Filtrar las actividades que pertenecen a este bloque
                                $acts_bloque = array_filter($actividades, function($a) use ($bloque) { 
                                    return $a['id_bloque'] == $bloque['id_bloque']; 
                                });
                                ?>
                                
                                <?php if(empty($acts_bloque)): ?>
                                    <p class="empty-text">No hay materiales en este bloque.</p>
                                <?php else: ?>
                                    <?php foreach($acts_bloque as $act): ?>
                                        <div class="material-card">
                                            <div class="material-icon"><i class="fas fa-file-alt"></i></div>
                                            <div class="material-info">
                                                <h4><?php echo htmlspecialchars($act['titulo']); ?></h4>
                                                <?php if(!empty($act['descripcion'])): ?>
                                                    <p><?php echo nl2br(htmlspecialchars($act['descripcion'])); ?></p>
                                                <?php endif; ?>
                                                <span class="date-posted">Publicado: <?php echo date('d/m/Y h:i A', strtotime($act['fecha_publicacion'])); ?></span>
                                            </div>
                                            <div class="material-actions" style="display: flex; gap: 8px;">
                                                <?php if(!empty($act['archivo_ruta'])): ?>
                                                    <a href="../<?php echo htmlspecialchars($act['archivo_ruta']); ?>" target="_blank" class="btn-mat-action view"><i class="fas fa-download"></i></a>
                                                <?php endif; ?>
                                                <button class="btn-mat-action edit"><i class="fas fa-edit"></i></button>
                                                <button class="btn-mat-action delete"><i class="fas fa-trash"></i></button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- MATERIALES GENERALES (SIN BLOQUE) -->
                    <?php 
                    $acts_generales = array_filter($actividades, function($a) { return empty($a['id_bloque']); });
                    if(!empty($acts_generales)): 
                    ?>
                        <h3 class="general-title">Materiales Generales</h3>
                        <?php foreach($acts_generales as $act): ?>
                            <!-- Misma estructura de la tarjeta de material -->
                            <div class="material-card">
                                <div class="material-icon"><i class="fas fa-file-alt"></i></div>
                                <div class="material-info">
                                    <h4><?php echo htmlspecialchars($act['titulo']); ?></h4>
                                    <?php if(!empty($act['descripcion'])): ?>
                                        <p><?php echo nl2br(htmlspecialchars($act['descripcion'])); ?></p>
                                    <?php endif; ?>
                                    <span class="date-posted">Publicado: <?php echo date('d/m/Y h:i A', strtotime($act['fecha_publicacion'])); ?></span>
                                </div>
                                <div class="material-actions" style="display: flex; gap: 8px;">
                                    <?php if(!empty($act['archivo_ruta'])): ?>
                                        <a href="../<?php echo htmlspecialchars($act['archivo_ruta']); ?>" target="_blank" class="btn-mat-action view"><i class="fas fa-download"></i></a>
                                    <?php endif; ?>
                                    <button class="btn-mat-action edit"><i class="fas fa-edit"></i></button>
                                    <button class="btn-mat-action delete"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                </div>
            </div>

            <!-- COLUMNA DERECHA: CONFIGURACIÓN Y ALUMNOS -->
            <div class="col-side">
                
                <div class="side-card config-card">
                    <h3><i class="fas fa-cog"></i> Configuración</h3>
                    <div class="toggle-container">
                        <div class="toggle-text">
                            <strong>Recepción de Trabajos</strong>
                            <p>Permitir que los alumnos suban archivos.</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" <?php echo ($clase['permitir_entregas'] == 1) ? 'checked' : ''; ?>>
                            <span class="slider round"></span>
                        </label>
                    </div>
                </div>

                <!-- NUEVA TARJETA: GESTIÓN DE ALUMNOS Y GRUPOS -->
                <div class="side-card">
                    <h3><i class="fas fa-users"></i> Gestión de Alumnos</h3>
                    <p class="side-desc">Agrega a los alumnos irregulares a este espacio para que puedan ver el material.</p>
                    
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <!-- SE AGREGÓ EL ID btnAbrirModalAlumno -->
                        <button class="btn-outline-full" id="btnAbrirModalAlumno"><i class="fas fa-user-plus"></i> Inscribir Alumno</button>
                        <button class="btn-outline-full"><i class="fas fa-users-viewfinder"></i> Vincular Grupo</button>
                        <button class="btn-outline-full" style="background: var(--green-soft); color: var(--green-800); border-color: transparent;"><i class="fas fa-list"></i> Ver Lista de Inscritos</button>
                    </div>
                </div>

                <div class="side-card">
                    <h3><i class="fas fa-inbox"></i> Entregas Recibidas</h3>
                    <p class="side-desc">Revisa los trabajos subidos por los alumnos.</p>
                    <div class="trabajos-empty">
                        <i class="fas fa-box-open"></i>
                        <span>Sin entregas aún</span>
                    </div>
                </div>

            </div>
        </div>

    </main>

    <!-- MODAL PARA SUBIR MATERIAL -->
    <div class="modal-overlay" id="modalMaterial">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-file-upload"></i> Subir Material</h2>
                <button class="close-modal" id="btnCerrarModalMaterial"><i class="fas fa-times"></i></button>
            </div>
            <!-- Formulario apunta al script que creamos en el Paso 1 -->
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
                            <?php foreach($bloques as $b): ?>
                                <option value="<?php echo $b['id_bloque']; ?>"><?php echo htmlspecialchars($b['titulo_bloque']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="archivo"><i class="fas fa-paperclip"></i> Adjuntar Archivo (PDF, DOCX, PPTX, JPG)</label>
                        <input type="file" name="archivo" id="archivo" class="input-modern" style="padding: 10px;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancelar" id="btnCancelarMaterial">Cancelar</button>
                    <button type="submit" class="btn-guardar"><i class="fas fa-upload"></i> Publicar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL PARA CREAR BLOQUE -->
    <div class="modal-overlay" id="modalBloque">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-folder-plus"></i> Crear Nuevo Bloque</h2>
                <button class="close-modal" id="btnCerrarModalBloque"><i class="fas fa-times"></i></button>
            </div>
            <!-- Formulario apunta al script para guardar el bloque -->
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
                    <button type="button" class="btn-cancelar" id="btnCancelarBloque">Cancelar</button>
                    <button type="submit" class="btn-guardar"><i class="fas fa-save"></i> Guardar Bloque</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL PARA INSCRIBIR ALUMNO -->
    <div class="modal-overlay" id="modalAlumno">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-plus"></i> Inscribir Alumno</h2>
                <button class="close-modal" id="btnCerrarModalAlumno"><i class="fas fa-times"></i></button>
            </div>
            
            <form action="../procesos/inscribir_alumno.php" method="POST">
                <input type="hidden" name="id_clase" value="<?php echo $id_clase; ?>">
                
                <div class="modal-body">
                    <p class="modal-instruction">Selecciona al alumno de tus grupos que necesita regularización.</p>
                    
                    <div class="form-group">
                        <label for="id_alumno">Buscar Alumno</label>
                        <select name="id_alumno" id="id_alumno" class="input-modern" required>
                            <option value="">-- Selecciona un alumno --</option>
                            <?php if(empty($alumnos_disponibles)): ?>
                                <option value="" disabled>No tienes alumnos disponibles para inscribir.</option>
                            <?php else: ?>
                                <?php foreach($alumnos_disponibles as $alum): ?>
                                    <option value="<?php echo $alum['id_alumno']; ?>">
                                        <?php echo htmlspecialchars($alum['matricula'] . ' - ' . $alum['apellido_paterno'] . ' ' . $alum['apellido_materno'] . ' ' . $alum['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancelar" id="btnCancelarAlumno">Cancelar</button>
                    <button type="submit" class="btn-guardar"><i class="fas fa-check"></i> Inscribir</button>
                </div>
            </form>
        </div>
    </div>

    <script src="dashboard_maestro.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ==========================================
            // Lógica Modal Materiales
            // ==========================================
            const modalMat = document.getElementById('modalMaterial');
            const btnAbrirMat = document.getElementById('btnAbrirModalMaterial');
            const btnCerrarMat = document.getElementById('btnCerrarModalMaterial');
            const btnCancelarMat = document.getElementById('btnCancelarMaterial');

            if (btnAbrirMat) {
                btnAbrirMat.onclick = function(e) {
                    e.preventDefault();
                    modalMat.classList.add('active');
                    document.body.style.overflow = 'hidden';
                };
            }

            function cerrarModalMat() {
                modalMat.classList.remove('active');
                document.body.style.overflow = 'auto';
            }

            if (btnCerrarMat) btnCerrarMat.onclick = cerrarModalMat;
            if (btnCancelarMat) btnCancelarMat.onclick = cerrarModalMat;

            // ==========================================
            // Lógica Modal Bloques
            // ==========================================
            const modalBloque = document.getElementById('modalBloque');
            const btnAbrirBloque = document.getElementById('btnCrearBloque');
            const btnCerrarBloque = document.getElementById('btnCerrarModalBloque');
            const btnCancelarBloque = document.getElementById('btnCancelarBloque');

            if (btnAbrirBloque) {
                btnAbrirBloque.onclick = function(e) {
                    e.preventDefault();
                    modalBloque.classList.add('active');
                    document.body.style.overflow = 'hidden';
                };
            }

            function cerrarModalBloque() {
                modalBloque.classList.remove('active');
                document.body.style.overflow = 'auto';
            }

            if (btnCerrarBloque) btnCerrarBloque.onclick = cerrarModalBloque;
            if (btnCancelarBloque) btnCancelarBloque.onclick = cerrarModalBloque;
            

            // ==========================================
            // Lógica Modal Alumnos
            // ==========================================
            const modalAlumno = document.getElementById('modalAlumno');
            const btnAbrirAlumno = document.getElementById('btnAbrirModalAlumno');
            const btnCerrarAlumno = document.getElementById('btnCerrarModalAlumno');
            const btnCancelarAlumno = document.getElementById('btnCancelarAlumno');

            if (btnAbrirAlumno) {
                btnAbrirAlumno.onclick = function(e) {
                    e.preventDefault();
                    modalAlumno.classList.add('active');
                    document.body.style.overflow = 'hidden';
                };
            }

            function cerrarModalAlumno() {
                modalAlumno.classList.remove('active');
                document.body.style.overflow = 'auto';
            }

            if (btnCerrarAlumno) btnCerrarAlumno.onclick = cerrarModalAlumno;
            if (btnCancelarAlumno) btnCancelarAlumno.onclick = cerrarModalAlumno;
        });
    </script>
</body>
</html>