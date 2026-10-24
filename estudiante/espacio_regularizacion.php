<?php
session_start();
require_once '../config/database.php';

// Validar que el usuario haya iniciado sesión y sea estudiante
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'estudiante') {
    header("Location: ../login.php");
    exit();
}

$id_alumno = $_SESSION['id_usuario'];
date_default_timezone_set('America/Mexico_City');

// Variables por defecto para evitar errores visuales (Warnings) en caso de fallo
$nombre_pila = 'Alumno';
$apellido_p = '';
$nombre_completo = 'Alumno';
$iniciales = 'AL';
$matricula = 'Sin Matrícula';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: actividades.php");
    exit();
}
$id_clase = (int)$_GET['id'];

try {
    // 1. Obtener datos básicos del alumno para el Header
    $stmt_alumno = $pdo->prepare("SELECT nombre, apellido_paterno, matricula FROM ALUMNOS WHERE id_alumno = ?");
    $stmt_alumno->execute([$id_alumno]);
    $datos_alumno = $stmt_alumno->fetch(PDO::FETCH_ASSOC);

    if ($datos_alumno) {
        $nombre_pila = htmlspecialchars($datos_alumno['nombre']);
        $apellido_p = htmlspecialchars($datos_alumno['apellido_paterno']);
        $nombre_completo = $nombre_pila . ' ' . $apellido_p;
        $iniciales = mb_substr($nombre_pila, 0, 1) . mb_substr($apellido_p, 0, 1);
        $matricula = htmlspecialchars($datos_alumno['matricula']);
    }

    // 2. Verificar que el alumno tenga acceso a esta clase
    $stmt_check = $pdo->prepare("SELECT * FROM CLASE_ALUMNO WHERE id_clase = ? AND id_alumno = ?");
    $stmt_check->execute([$id_clase, $id_alumno]);
    if (!$stmt_check->fetch()) {
        header("Location: actividades.php?error=No tienes acceso a este espacio.");
        exit();
    }

    // 3. Cargar información de la clase y el maestro
    $stmt_clase = $pdo->prepare("
        SELECT c.*, m.nombre AS maestro_nombre, m.apellido_paterno AS maestro_apellido 
        FROM CLASES_REGULARIZACION c
        LEFT JOIN MAESTROS m ON c.id_maestro = m.id_maestro
        WHERE c.id_clase = ?
    ");
    $stmt_clase->execute([$id_clase]);
    $clase = $stmt_clase->fetch(PDO::FETCH_ASSOC);

    // 4. Cargar material: Bloques y Actividades
    $stmt_bloques = $pdo->prepare("SELECT * FROM BLOQUES_REGULARIZACION WHERE id_clase = ? ORDER BY id_bloque ASC");
    $stmt_bloques->execute([$id_clase]);
    $bloques = $stmt_bloques->fetchAll(PDO::FETCH_ASSOC);

    $stmt_act = $pdo->prepare("SELECT * FROM ACTIVIDADES_REGULARIZACION WHERE id_clase = ? ORDER BY fecha_publicacion DESC");
    $stmt_act->execute([$id_clase]);
    $actividades = $stmt_act->fetchAll(PDO::FETCH_ASSOC);

    // 5. Verificar si el alumno ya hizo una entrega
    $stmt_entrega = $pdo->prepare("SELECT * FROM ENTREGAS_REGULARIZACION WHERE id_clase = ? AND id_alumno = ? LIMIT 1");
    $stmt_entrega->execute([$id_clase, $id_alumno]);
    $mi_entrega = $stmt_entrega->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error cargando el espacio del alumno: " . $e->getMessage());
    header("Location: actividades.php?error=Error de conexión con la base de datos.");
    exit();
}

// Configurar imagen de fondo (Hero)
$bg_image = "linear-gradient(145deg, #0f172a, #063f2b)";
if (!empty($clase['imagen_portada'])) {
    $ruta_img = '../' . $clase['imagen_portada'];
    $bg_image = "linear-gradient(rgba(15, 23, 42, 0.5), rgba(15, 23, 42, 0.8)), url('" . htmlspecialchars($ruta_img) . "')";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#063f2b">
    <title><?php echo htmlspecialchars($clase['nombre_materia']); ?> · Panel Estudiante</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <!-- CSS Base unificado -->
    <link rel="stylesheet" href="style_dashboard_estud.css?v=<?php echo time(); ?>">

    <style>
        /* ==============================================================
           CORE LAYOUT ESTUDIANTE (Estándar para todos los módulos)
        ============================================================== */
        :root {
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 80px;
            --bg-main: #f8fafc;
        }
        body { background-color: var(--bg-main); margin: 0; padding: 0; overflow: hidden; box-sizing: border-box; }
        *, *::before, *::after { box-sizing: border-box; }

        .main-content {
            margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width));
            padding: 1.2rem 2rem; height: 100vh; overflow-y: auto; transition: margin-left 0.3s ease, width 0.3s ease;
        }
        body.sidebar-collapsed .main-content { margin-left: var(--sidebar-collapsed-width); width: calc(100% - var(--sidebar-collapsed-width)); }
        .dashboard-container { max-width: 100%; margin: 0 auto; display: flex; flex-direction: column; gap: 1rem; }

        /* HEADER Y PERFIL */
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
        .topbar-left { display: flex; align-items: center; gap: 15px; }
        .topbar-left h1 { font-size: 1.4rem; font-weight: 800; color: #0f172a; margin: 0 0 0.1rem 0; letter-spacing: -0.5px; }
        .topbar-left p { margin: 0; color: #64748b; font-size: 0.85rem; }
        
        .topbar-right { display: flex; align-items: center; gap: 10px; }
        .date-pill { background: white; padding: 0.5rem 1rem; border-radius: 99px; font-size: 0.8rem; font-weight: 600; color: #475569; border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 8px; }
        .date-pill i { color: #059669; }

        .profile-container { position: relative; }
        .profile-pill { background: white; padding: 0.3rem 0.8rem 0.3rem 0.3rem; border-radius: 99px; display: flex; align-items: center; gap: 10px; border: 1px solid #e2e8f0; cursor: pointer; transition: background 0.2s; }
        .profile-pill:hover { background: #f8fafc; }
        .profile-pill .avatar { width: 34px; height: 34px; background: #063f2b; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; }
        .profile-info strong { display: block; font-size: 0.85rem; color: #0f172a; }
        .profile-info span { display: block; font-size: 0.7rem; color: #64748b; }
        
        .profile-dropdown { position: absolute; top: calc(100% + 10px); right: 0; background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); width: 220px; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.2s; z-index: 100; }
        .profile-dropdown.active { opacity: 1; visibility: visible; transform: translateY(0); }
        .profile-dropdown-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #475569; text-decoration: none; font-size: 0.9rem; font-weight: 600; transition: all 0.2s; }
        .profile-dropdown-item i { width: 16px; text-align: center; }
        .profile-dropdown-item:first-child { border-radius: 12px 12px 0 0; }
        .profile-dropdown-item:last-child { border-radius: 0 0 12px 12px; border-top: 1px solid #f1f5f9; color: #ef4444; }
        .profile-dropdown-item:hover { background: #f8fafc; color: #0f172a; padding-left: 20px; }
        .mobile-menu { display: none; background: none; border: none; padding: 0; font-size: 1.6rem; color: #0f172a; cursor: pointer; }

        /* ==============================================================
           DISEÑO DEL ESPACIO DE REGULARIZACIÓN
        ============================================================== */
        .layout-espacio {
            display: grid;
            grid-template-columns: 2fr 1fr; /* Columna Izquierda Principal, Columna Derecha Entregas */
            grid-template-areas: "hero hero" "main side";
            gap: 1.5rem;
            align-items: start;
        }

        /* Hero Header (Portada de la Materia) */
        .hero-estudiante {
            grid-area: hero;
            height: 200px;
            border-radius: 16px;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 1.5rem 2rem;
            box-shadow: 0 10px 20px -5px rgba(0,0,0,0.1);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .btn-back { display: inline-flex; align-items: center; gap: 8px; color: #f8fafc; text-decoration: none; font-size: 0.85rem; font-weight: 600; background: rgba(0,0,0,0.4); padding: 6px 12px; border-radius: 50px; backdrop-filter: blur(4px); transition: 0.2s; width: max-content;}
        .btn-back:hover { background: rgba(0,0,0,0.6); }

        .hero-title { margin: 0.5rem 0; font-size: 2rem; font-weight: 800; color: white; line-height: 1.2; text-shadow: 0 2px 4px rgba(0,0,0,0.5); }
        .hero-desc { margin: 0; font-size: 0.95rem; color: #cbd5e1; max-width: 800px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-shadow: 0 1px 2px rgba(0,0,0,0.5); }
        
        .badge-profesor { background: rgba(16, 185, 129, 0.9); color: white; padding: 6px 14px; border-radius: 50px; font-size: 0.85rem; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; backdrop-filter: blur(4px); position: absolute; bottom: 1.5rem; right: 2rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }

        /* Columna Izquierda (Contenido y Bloques) */
        .col-main { grid-area: main; display: flex; flex-direction: column; gap: 1.5rem; }
        
        .section-title { font-size: 1.2rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 10px; }
        .icon-green { color: #059669; }

        .empty-state-materiales { background: white; border: 2px dashed #cbd5e1; border-radius: 12px; padding: 3rem 2rem; text-align: center; color: #64748b; font-weight: 500; font-size: 0.95rem; }
        .empty-state-materiales i { font-size: 3rem; color: #cbd5e1; margin-bottom: 1rem; display: block; }

        .bloque-box { background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); overflow: hidden; }
        .bloque-header { background: #f8fafc; padding: 1rem 1.5rem; font-weight: 700; color: #0f172a; border-bottom: 1px solid #e2e8f0; font-size: 1.05rem; display: flex; align-items: center; gap: 10px;}
        .bloque-body { padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; }

        .mat-item { display: flex; align-items: flex-start; gap: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #f1f5f9; }
        .mat-item:last-child { padding-bottom: 0; border-bottom: none; }
        .mat-icon { width: 40px; height: 40px; background: #ecfdf5; color: #10b981; border-radius: 10px; display: grid; place-items: center; font-size: 1.2rem; flex-shrink: 0; }
        
        .mat-info { flex: 1; }
        .mat-title { margin: 0 0 4px 0; font-size: 1rem; font-weight: 700; color: #0f172a; }
        .mat-desc { margin: 0 0 8px 0; font-size: 0.85rem; color: #64748b; line-height: 1.5; }
        .mat-filename { display: inline-flex; align-items: center; gap: 6px; background: #f1f5f9; color: #475569; font-size: 0.75rem; padding: 4px 10px; border-radius: 6px; font-weight: 600; border: 1px solid #e2e8f0; }

        .btn-download { display: inline-flex; align-items: center; justify-content: center; gap: 8px; border: 1px solid #cbd5e1; background: white; color: #0f172a; padding: 8px 16px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; text-decoration: none; transition: 0.2s; height: max-content; margin-top: 5px;}
        .btn-download:hover { background: #f8fafc; border-color: #94a3b8; }

        /* Columna Derecha (Panel de Entregas) */
        .col-side { grid-area: side; }
        .side-panel { background: white; border-radius: 12px; border: 1px solid #e2e8f0; padding: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .panel-title { margin: 0 0 1.5rem 0; font-size: 1.1rem; color: #0f172a; font-weight: 800; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;}
        
        .status-entrega { padding: 12px; border-radius: 8px; font-weight: 700; font-size: 0.95rem; display: flex; align-items: center; gap: 8px; margin-bottom: 1rem; text-align: center; justify-content: center; }
        .status-entregado { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
        .status-faltante { background: #fff7ed; color: #ea580c; border: 1px solid #fed7aa; }
        .status-bloqueado { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

        .entrega-date { font-size: 0.8rem; color: #64748b; text-align: center; margin-bottom: 1rem; font-weight: 600; }
        
        .form-group-upload { margin-bottom: 1rem; }
        .upload-label { display: block; font-size: 0.8rem; font-weight: 700; color: #475569; margin-bottom: 8px; }
        .input-file { width: 100%; border: 1px dashed #cbd5e1; padding: 10px; border-radius: 8px; font-size: 0.85rem; color: #64748b; background: #f8fafc; }
        
        .btn-subir { width: 100%; padding: 12px; border-radius: 8px; font-weight: 700; font-size: 0.9rem; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; text-decoration: none; border: none; }
        .btn-subir.primary { background: #059669; color: white; }
        .btn-subir.primary:hover { background: #047857; }
        .btn-subir.outline { border: 1px solid #cbd5e1; background: white; color: #0f172a; }
        .btn-subir.outline:hover { background: #f8fafc; border-color: #94a3b8; }

        .alert { padding: 12px 16px; border-radius: 8px; font-size: 0.9rem; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        /* ==============================================================
           RESPONSIVE MOBILE
        ============================================================== */
        @media (max-width: 900px) {
            .layout-espacio { grid-template-columns: 1fr; grid-template-areas: "hero" "side" "main"; }
        }
        @media (max-width: 768px) {
            body { overflow: auto; }
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 1rem; height: auto; overflow-y: visible; }
            .topbar { flex-direction: column; align-items: stretch; gap: 0.8rem; }
            .topbar-left { align-items: flex-start; }
            .topbar-left h1 { font-size: 1.25rem; }
            .mobile-menu { display: block; margin-top: 3px; }
            .topbar-right { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; width: 100%; }
            .date-pill, .profile-pill { width: 100%; justify-content: center; padding: 0.5rem; }
            
            .hero-estudiante { padding: 1.25rem; height: auto; min-height: 180px; }
            .hero-title { font-size: 1.6rem; }
            .badge-profesor { position: static; margin-top: 1rem; width: max-content;}
            .mat-item { flex-direction: column; }
            .btn-download { width: 100%; }
        }
        @media (max-width: 400px) { .topbar-right { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

    <!-- ================= BARRA LATERAL ================= -->
    <aside class="sidebar" id="sidebar">
        <button id="collapseToggle" class="collapse-toggle" aria-label="Colapsar menú"><i class="fas fa-chevron-left"></i></button>
        <div class="sidebar-inner">
            <div class="brand">
                <img src="../img/LogoCobaep.png" alt="Logo COBAEP" class="brand-logo">
                <div class="brand-copy"><strong>SIGE<span>-Cobaep</span></strong><small>Plantel 27</small></div>
            </div>

            <nav class="navigation">
                <span class="nav-heading">Menú principal</span>
                <a href="dashboard_estud.php" class="nav-item"><i class="fas fa-home"></i> <span>Inicio</span></a>
                <a href="mis_asistencias.php" class="nav-item"><i class="far fa-calendar-check"></i> <span>Mis Asistencias</span></a>
                <a href="horario_clases.php" class="nav-item"><i class="far fa-calendar-alt"></i> <span>Horario de Clases</span></a>
                
                <span class="nav-heading">Académico</span>
                <a href="actividades_escolares.php" class="nav-item"><i class="fas fa-book-open"></i> <span>Actividades Escolares</span></a>
                <a href="actividades.php" class="nav-item active"><i class="fas fa-star"></i> <span>Regularizaciones</span></a>

                <span class="nav-heading">Información</span>
                <a href="avisos.php" class="nav-item"><i class="far fa-bell"></i> <span>Avisos</span></a>
                <a href="documentos.php" class="nav-item"><i class="far fa-file-alt"></i> <span>Documentos</span></a>
                <a href="reglamentos.php" class="nav-item"><i class="far fa-clipboard"></i> <span>Reglamentos</span></a>
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

    <!-- ================= CONTENIDO PRINCIPAL ================= -->
    <main class="main-content">
        <div class="dashboard-container">
            
            <header class="topbar">
                <div class="topbar-left">
                    <button class="mobile-menu" id="mobileMenu" type="button"><i class="fas fa-bars"></i></button>
                    <div>
                        <h1>Aula Virtual</h1>
                        <p>Espacio de trabajo y recepción de tareas.</p>
                    </div>
                </div>

                <div class="topbar-right">
                    <div class="date-pill">
                        <i class="far fa-calendar"></i>
                        <span id="fechaTexto"><?php echo date('d/m/Y, h:i a'); ?></span>
                    </div>
                    
                    <div class="profile-container">
                        <div class="profile-pill" id="profileBtn">
                            <div class="avatar"><?php echo htmlspecialchars($iniciales); ?></div>
                            <div class="profile-info">
                                <strong><?php echo $nombre_pila; ?></strong>
                                <span>Matrícula: <?php echo htmlspecialchars($matricula); ?></span>
                            </div>
                            <i class="fas fa-chevron-down" style="color: #94a3b8; margin-left: 5px; font-size: 0.8rem;"></i>
                        </div>
                        
                        <div class="profile-dropdown" id="profileDropdown">
                            <a href="perfil.php" class="profile-dropdown-item"><i class="far fa-user-circle"></i> Mi Perfil</a>
                            <a href="../logout.php" class="profile-dropdown-item"><i class="fas fa-arrow-right-from-bracket"></i> Cerrar Sesión</a>
                        </div>
                    </div>
                </div>
            </header>

            <div class="layout-espacio">
                
                <div class="hero-estudiante" style="background-image: <?php echo $bg_image; ?>;">
                    <div class="hero-content">
                        <a href="actividades.php" class="btn-back"><i class="fas fa-arrow-left"></i> Volver a mis materias</a>
                        <h1 class="hero-title"><?php echo htmlspecialchars($clase['nombre_materia']); ?></h1>
                        <p class="hero-desc"><?php echo nl2br(htmlspecialchars($clase['descripcion'])); ?></p>
                    </div>
                    <div class="hero-footer">
                        <span class="badge-profesor"><i class="fas fa-chalkboard-user"></i> Prof. <?php echo htmlspecialchars($clase['maestro_apellido'] ?? 'No Asignado'); ?></span>
                    </div>
                </div>

                <div class="col-main">
                    
                    <?php if(isset($_GET['exito'])): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['exito']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if(isset($_GET['error'])): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
                        </div>
                    <?php endif; ?>

                    <h2 class="section-title"><i class="fas fa-folder-open icon-green"></i> Contenido de la materia</h2>

                    <?php if(empty($bloques) && empty($actividades)): ?>
                        <div class="empty-state-materiales">
                            <i class="fas fa-folder-open"></i>
                            <span>El maestro aún no ha subido material.</span>
                        </div>
                    <?php endif; ?>

                    <!-- BLOQUES -->
                    <?php foreach($bloques as $bloque): ?>
                        <div class="bloque-box">
                            <div class="bloque-header">
                                <i class="fas fa-bookmark icon-green"></i> <?php echo htmlspecialchars($bloque['titulo_bloque']); ?>
                            </div>
                            <div class="bloque-body">
                                <?php 
                                $acts_bloque = array_filter($actividades, function($a) use ($bloque) { return $a['id_bloque'] == $bloque['id_bloque']; });
                                if(empty($acts_bloque)): ?>
                                    <p style="color:#94a3b8; font-style:italic; margin:0; font-size:0.9rem;">No hay archivos en este bloque.</p>
                                <?php else: ?>
                                    <?php foreach($acts_bloque as $act): ?>
                                        
                                        <?php 
                                            // Extraer y limpiar el nombre del archivo original
                                            $nombre_archivo = '';
                                            if(!empty($act['archivo_ruta'])) {
                                                $nombre_archivo = basename($act['archivo_ruta']);
                                                $nombre_archivo = preg_replace('/^mat_[a-zA-Z0-9]+_/', '', $nombre_archivo);
                                            }
                                        ?>
                                        <div class="mat-item">
                                            <div class="mat-icon"><i class="fas fa-file-pdf"></i></div>
                                            
                                            <div class="mat-info">
                                                <h4 class="mat-title"><?php echo htmlspecialchars($act['titulo']); ?></h4>
                                                <?php if(!empty($act['descripcion'])): ?>
                                                    <p class="mat-desc"><?php echo nl2br(htmlspecialchars($act['descripcion'])); ?></p>
                                                <?php endif; ?>
                                                <?php if(!empty($nombre_archivo)): ?>
                                                    <span class="mat-filename"><i class="fas fa-paperclip"></i> <?php echo htmlspecialchars($nombre_archivo); ?></span>
                                                <?php endif; ?>
                                            </div>

                                            <?php if(!empty($act['archivo_ruta'])): ?>
                                                <a href="../<?php echo htmlspecialchars($act['archivo_ruta']); ?>" target="_blank" class="btn-download">
                                                    <i class="fas fa-download"></i> Ver Archivo
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- MATERIAL GENERAL -->
                    <?php 
                    $acts_generales = array_filter($actividades, function($a) { return empty($a['id_bloque']); });
                    if(!empty($acts_generales)): 
                    ?>
                        <h2 class="section-title" style="margin-top: 1rem;"><i class="fas fa-copy icon-green"></i> Material General</h2>
                        
                        <div class="bloque-box" style="border-top: 4px solid #10b981;">
                            <div class="bloque-body">
                                <?php foreach($acts_generales as $act): ?>
                                    
                                    <?php 
                                        $nombre_archivo = '';
                                        if(!empty($act['archivo_ruta'])) {
                                            $nombre_archivo = basename($act['archivo_ruta']);
                                            $nombre_archivo = preg_replace('/^mat_[a-zA-Z0-9]+_/', '', $nombre_archivo);
                                        }
                                    ?>
                                    <div class="mat-item">
                                        <div class="mat-icon"><i class="fas fa-file-pdf"></i></div>
                                        
                                        <div class="mat-info">
                                            <h4 class="mat-title"><?php echo htmlspecialchars($act['titulo']); ?></h4>
                                            <?php if(!empty($act['descripcion'])): ?>
                                                <p class="mat-desc"><?php echo nl2br(htmlspecialchars($act['descripcion'])); ?></p>
                                            <?php endif; ?>
                                            <?php if(!empty($nombre_archivo)): ?>
                                                <span class="mat-filename"><i class="fas fa-paperclip"></i> <?php echo htmlspecialchars($nombre_archivo); ?></span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if(!empty($act['archivo_ruta'])): ?>
                                            <a href="../<?php echo htmlspecialchars($act['archivo_ruta']); ?>" target="_blank" class="btn-download">
                                                <i class="fas fa-download"></i> Ver Archivo
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>

                <div class="col-side">
                    <div class="side-panel">
                        <h3 class="panel-title"><i class="fas fa-upload icon-green"></i> Tu Trabajo</h3>
                        
                        <?php if ($mi_entrega): ?>
                            <div class="status-entrega status-entregado">
                                <i class="fas fa-check-circle"></i> Tarea Entregada
                            </div>
                            <p class="entrega-date">Enviado el: <?php echo date('d/m/Y h:i a', strtotime($mi_entrega['fecha_entrega'])); ?></p>
                            <a href="../<?php echo htmlspecialchars($mi_entrega['archivo_ruta']); ?>" target="_blank" class="btn-subir outline">
                                <i class="fas fa-file"></i> Ver archivo enviado
                            </a>
                        
                        <?php else: ?>
                            <?php if ($clase['permitir_entregas'] == 1): ?>
                                
                                <div class="status-entrega status-faltante">
                                    <i class="fas fa-exclamation-circle"></i> Tarea Asignada
                                </div>
                                
                                <form action="../procesos/subir_trabajo.php" method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="id_clase" value="<?php echo $id_clase; ?>">
                                    
                                    <div class="form-group-upload">
                                        <label class="upload-label">Adjuntar archivo (PDF, DOCX)</label>
                                        <input type="file" name="archivo_trabajo" required class="input-file">
                                    </div>
                                    <button type="submit" class="btn-subir primary">
                                        <i class="fas fa-paper-plane"></i> Entregar Trabajo
                                    </button>
                                </form>

                            <?php else: ?>
                                <div class="status-entrega status-bloqueado">
                                    <i class="fas fa-lock"></i> Entregas deshabilitadas
                                </div>
                                <p style="text-align: center; color: #64748b; font-size: 0.85rem; margin-top: 10px;">El maestro actualmente no está recibiendo trabajos por la plataforma.</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const mobileMenu = document.getElementById('mobileMenu');
            const collapseToggle = document.getElementById('collapseToggle');
            const body = document.body;

            // Lógica Menú Lateral
            if (localStorage.getItem('sidebarCollapsed') === 'true') body.classList.add('sidebar-collapsed');

            if (collapseToggle) {
                collapseToggle.addEventListener('click', () => {
                    body.classList.toggle('sidebar-collapsed');
                    localStorage.setItem('sidebarCollapsed', body.classList.contains('sidebar-collapsed'));
                });
            }

            window.addEventListener('resize', () => {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('mobile-open');
                    overlay.classList.remove('visible');
                    body.classList.remove('menu-open');
                }
            });

            if (mobileMenu) {
                mobileMenu.addEventListener('click', () => {
                    sidebar.classList.add('mobile-open');
                    overlay.classList.add('visible');
                    body.classList.add('menu-open');
                });
            }
            if (overlay) {
                overlay.addEventListener('click', () => {
                    sidebar.classList.remove('mobile-open');
                    overlay.classList.remove('visible');
                    body.classList.remove('menu-open');
                });
            }

            // Lógica Dropdown
            const profileBtn = document.getElementById('profileBtn');
            const profileDropdown = document.getElementById('profileDropdown');

            if (profileBtn && profileDropdown) {
                profileBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    profileDropdown.classList.toggle('active');
                });
                document.addEventListener('click', (e) => {
                    if (!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
                        profileDropdown.classList.remove('active');
                    }
                });
            }

            // Reloj
            const fechaTexto = document.getElementById('fechaTexto');
            function actualizarHora() {
                if (!fechaTexto) return;
                const now = new Date();
                fechaTexto.textContent = now.toLocaleString('es-MX', {
                    day: '2-digit', month: '2-digit', year: 'numeric',
                    hour: '2-digit', minute: '2-digit'
                });
            }
            actualizarHora();
            setInterval(actualizarHora, 60000);
        });
    </script>
</body>
</html>