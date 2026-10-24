<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'estudiante') {
    header("Location: ../login.php");
    exit();
}

$id_alumno = $_SESSION['id_usuario'];
date_default_timezone_set('America/Mexico_City');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: actividades_escolares.php");
    exit();
}
$id_clase = (int)$_GET['id'];

$nombre_pila = 'Alumno';
$apellido_p = '';
$nombre_completo = 'Alumno';
$iniciales = 'AL';
$matricula = 'Sin Matrícula';

try {
    // 1. Obtener datos del alumno
    $stmt_alumno = $pdo->prepare("SELECT nombre, apellido_paterno, matricula FROM alumnos WHERE id_alumno = ?");
    $stmt_alumno->execute([$id_alumno]);
    $datos_alumno = $stmt_alumno->fetch(PDO::FETCH_ASSOC);

    if ($datos_alumno) {
        $nombre_pila = htmlspecialchars($datos_alumno['nombre']);
        $apellido_p = htmlspecialchars($datos_alumno['apellido_paterno']);
        $nombre_completo = $nombre_pila . ' ' . $apellido_p;
        $iniciales = mb_substr($nombre_pila, 0, 1) . mb_substr($apellido_p, 0, 1);
        $matricula = htmlspecialchars($datos_alumno['matricula']);
    }

    // 2. Verificar acceso a la clase
    $stmt_clase = $pdo->prepare("
        SELECT c.id_clase, c.enlace_clase_virtual, m.nombre_materia, m.imagen_portada, 
               prof.nombre AS maestro_nombre, prof.apellido_paterno AS maestro_apellido 
        FROM clase_regular_alumno ca
        INNER JOIN clases_regulares c ON ca.id_clase = c.id_clase
        INNER JOIN materias m ON c.id_materia = m.id_materia
        LEFT JOIN maestros prof ON c.id_maestro = prof.id_maestro
        WHERE ca.id_alumno = ? AND c.id_clase = ? AND ca.acceso = 1
    ");
    $stmt_clase->execute([$id_alumno, $id_clase]);
    $clase = $stmt_clase->fetch(PDO::FETCH_ASSOC);

    if (!$clase) {
        header("Location: actividades_escolares.php?error=El profesor ha restringido el acceso a esta clase.");
        exit();
    }

    // 3. Obtener Actividades (SOLO LAS QUE TIENEN VISIBLE = 1 A NIVEL GLOBAL)
    $stmt_act = $pdo->prepare("SELECT * FROM actividades_clase WHERE id_clase = ? AND visible = 1 ORDER BY fecha_publicacion DESC");
    $stmt_act->execute([$id_clase]);
    $actividades = $stmt_act->fetchAll(PDO::FETCH_ASSOC);

    // 4. Obtener Entregas
    $stmt_entregas = $pdo->prepare("SELECT id_actividad, fecha_entrega, estatus_entrega, archivo_ruta, retroalimentacion FROM entregas_actividad WHERE id_alumno = ?");
    $stmt_entregas->execute([$id_alumno]);
    $mis_entregas = [];
    while($row = $stmt_entregas->fetch(PDO::FETCH_ASSOC)) {
        $mis_entregas[$row['id_actividad']] = $row;
    }

    // 5. Obtener Permisos Individuales (El switch del engrane)
    $stmt_perm = $pdo->prepare("SELECT id_actividad, visible FROM visibilidad_actividad WHERE id_alumno = ?");
    $stmt_perm->execute([$id_alumno]);
    $mis_permisos = [];
    while($row = $stmt_perm->fetch(PDO::FETCH_ASSOC)) {
        $mis_permisos[$row['id_actividad']] = $row['visible'];
    }

} catch (PDOException $e) {
    header("Location: actividades_escolares.php?error=Error BD: " . urlencode($e->getMessage()));
    exit();
}

$asistencias_clase = 0; $faltas_clase = 0;
try {
    $stmt_asist = $pdo->prepare("SELECT estatus, COUNT(*) as total FROM asistencias WHERE id_alumno = ? AND id_clase = ? GROUP BY estatus");
    $stmt_asist->execute([$id_alumno, $id_clase]);
    while($row = $stmt_asist->fetch(PDO::FETCH_ASSOC)) {
        $estatus = strtolower(trim($row['estatus']));
        if ($estatus == 'presente' || $estatus == 'asistencia' || $estatus == 'retardo') $asistencias_clase += $row['total'];
        else if ($estatus == 'falta' || $estatus == 'ausente') $faltas_clase += $row['total'];
    }
} catch (PDOException $e) {}

$bg_image = "linear-gradient(145deg, #0f172a, #063f2b)";
if (!empty($clase['imagen_portada'])) {
    $ruta_img = '../' . $clase['imagen_portada'];
    $bg_image = "linear-gradient(rgba(15, 23, 42, 0.6), rgba(15, 23, 42, 0.85)), url('" . htmlspecialchars($ruta_img) . "')";
}
$clase_en_vivo_activa = !empty($clase['enlace_clase_virtual']);
$fecha_actual = new DateTime();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#063f2b">
    <title><?php echo htmlspecialchars($clase['nombre_materia']); ?> · Aula Virtual</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="style_dashboard_estud.css?v=<?php echo time(); ?>">

    <style>
        :root { --sidebar-width: 260px; --sidebar-collapsed-width: 80px; --bg-main: #f8fafc; }
        body { background-color: var(--bg-main); margin: 0; padding: 0; overflow: hidden; box-sizing: border-box; }
        *, *::before, *::after { box-sizing: border-box; }

        .main-content { margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); padding: 1.2rem 2rem; height: 100vh; overflow-y: auto; transition: margin-left 0.3s ease, width 0.3s ease; }
        body.sidebar-collapsed .main-content { margin-left: var(--sidebar-collapsed-width); width: calc(100% - var(--sidebar-collapsed-width)); }
        .dashboard-container { max-width: 1200px; margin: 0 auto; display: flex; flex-direction: column; gap: 1.5rem; }

        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
        .topbar-left { display: flex; align-items: center; gap: 15px; }
        .header-icon { background: #059669; color: white; width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; box-shadow: 0 2px 4px rgba(5, 150, 105, 0.2); }
        .topbar-left h1 { font-size: 1.4rem; font-weight: 800; color: #0f172a; margin: 0 0 0.1rem 0; letter-spacing: -0.5px; }
        .topbar-left p { margin: 0; color: #64748b; font-size: 0.85rem; }
        
        .topbar-right { display: flex; align-items: center; gap: 10px; }
        .date-pill, .profile-pill { background: white; padding: 0.5rem 1rem; border-radius: 99px; font-size: 0.8rem; font-weight: 600; color: #475569; border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 8px; }
        .date-pill i { color: #059669; }
        .profile-container { position: relative; }
        .profile-pill { padding: 0.3rem 0.8rem 0.3rem 0.3rem; cursor: pointer; transition: 0.2s; }
        .profile-pill:hover { background: #f8fafc; }
        .profile-pill .avatar { width: 34px; height: 34px; background: #063f2b; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; }
        .profile-info strong { display: block; font-size: 0.85rem; color: #0f172a; }
        .profile-info span { display: block; font-size: 0.7rem; color: #64748b; }
        .profile-dropdown { position: absolute; top: calc(100% + 10px); right: 0; background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); width: 220px; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.2s; z-index: 100; }
        .profile-dropdown.active { opacity: 1; visibility: visible; transform: translateY(0); }
        .profile-dropdown-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #475569; text-decoration: none; font-size: 0.9rem; font-weight: 600; transition: 0.2s; }
        .profile-dropdown-item:hover { background: #f8fafc; color: #0f172a; padding-left: 20px; }
        .mobile-menu { display: none; background: none; border: none; padding: 0; font-size: 1.6rem; color: #0f172a; cursor: pointer; }

        .layout-aula { display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start; }
        .hero-materia { grid-column: 1 / -1; height: 180px; background-size: cover; background-position: center; border-radius: 16px; display: flex; flex-direction: column; justify-content: space-between; padding: 1.5rem 2rem; position: relative; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; color: #f8fafc; text-decoration: none; font-size: 0.85rem; font-weight: 600; background: rgba(0,0,0,0.4); padding: 6px 12px; border-radius: 50px; backdrop-filter: blur(4px); transition: 0.2s; width: max-content;}
        .btn-back:hover { background: rgba(0,0,0,0.6); }
        .hero-title { margin: 0; font-size: 2rem; font-weight: 800; color: white; line-height: 1.2; text-shadow: 0 2px 4px rgba(0,0,0,0.5); }
        .hero-prof { color: #a7f3d0; font-size: 1rem; font-weight: 600; text-shadow: 0 1px 2px rgba(0,0,0,0.5); display: flex; align-items: center; gap: 8px; }

        .col-main { display: flex; flex-direction: column; gap: 1.5rem; }
        .section-title { font-size: 1.2rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 10px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
        .section-title i { color: #059669; }

        .actividad-card { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; display: flex; gap: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); transition: 0.2s; }
        .actividad-card:hover { border-color: #cbd5e1; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .act-icon { width: 45px; height: 45px; border-radius: 10px; display: grid; place-items: center; font-size: 1.3rem; flex-shrink: 0; }
        .bg-tarea { background: #eff6ff; color: #3b82f6; }
        .bg-material { background: #fef2f2; color: #ef4444; }

        .act-content { flex: 1; }
        .act-title { margin: 0 0 5px 0; font-size: 1.05rem; font-weight: 700; color: #0f172a; }
        .act-desc { margin: 0 0 10px 0; font-size: 0.85rem; color: #64748b; line-height: 1.4; }
        
        .act-meta { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 15px; }
        .meta-tag { display: inline-flex; align-items: center; gap: 6px; font-size: 0.75rem; font-weight: 700; padding: 4px 10px; border-radius: 6px; background: #f1f5f9; color: #475569; }
        .tag-red { background: #fee2e2; color: #991b1b; }
        .tag-orange { background: #ffedd5; color: #9a3412; }
        .tag-green { background: #dcfce7; color: #166534; }
        
        .btn-action { padding: 8px 16px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; border: 1px solid transparent; cursor: pointer;}
        .btn-outline { background: white; border-color: #cbd5e1; color: #0f172a; }
        .btn-outline:hover { background: #f8fafc; border-color: #94a3b8; }
        .btn-primary { background: #059669; color: white; }
        .btn-primary:hover { background: #047857; }

        .status-entregado { font-weight: 800; font-size: 0.85rem; display: flex; align-items: center; gap: 5px; padding: 6px 12px; border-radius: 8px; width: fit-content;}
        .status-verde { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0;}
        .status-rojo { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca;}

        .box-entrega { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 15px; margin-top: 15px; }
        .box-entrega h5 { margin: 0 0 10px 0; font-size: 0.9rem; color: #0f172a; display: flex; align-items: center; gap: 6px;}

        .col-side { display: flex; flex-direction: column; gap: 1.5rem; }
        .side-widget { background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .widget-title { font-size: 1rem; font-weight: 800; color: #0f172a; margin: 0 0 1rem 0; display: flex; align-items: center; gap: 8px; }
        
        .btn-live-class { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 1.5rem; border-radius: 12px; text-decoration: none; transition: 0.2s; text-align: center; }
        .btn-live-class:hover { background: #fee2e2; border-color: #f87171; }
        .btn-live-class i { font-size: 2rem; animation: pulseLive 2s infinite; }
        .btn-live-class strong { font-size: 1.1rem; }
        .btn-live-class span { font-size: 0.8rem; color: #991b1b; }
        @keyframes pulseLive { 0% { transform: scale(1); } 50% { transform: scale(1.1); } 100% { transform: scale(1); } }

        .attendance-stats { display: flex; gap: 10px; }
        .att-box { flex: 1; text-align: center; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; }
        .att-box strong { display: block; font-size: 1.5rem; font-weight: 800; line-height: 1; margin-bottom: 5px; }
        .att-box span { font-size: 0.7rem; font-weight: 700; color: #64748b; text-transform: uppercase; }
        .att-green strong { color: #10b981; }
        .att-red strong { color: #ef4444; }

        @media (max-width: 900px) { .layout-aula { grid-template-columns: 1fr; } .col-side { order: -1; } }
        @media (max-width: 768px) {
            body { overflow: auto; }
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 1rem; height: auto; }
            .topbar { flex-direction: column; align-items: stretch; gap: 0.8rem; }
            .topbar-left { align-items: flex-start; }
            .mobile-menu { display: block; margin-top: 3px; }
            .topbar-right { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; width: 100%; }
            .date-pill, .profile-pill { width: 100%; justify-content: center; padding: 0.5rem; }
            .hero-materia { height: auto; padding: 1.5rem; }
            .actividad-card { flex-direction: column; }
        }
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
                    <strong>SIGE<span>-Cobaep</span></strong>
                    <small>Plantel 27</small>
                </div>
            </div>

            <nav class="navigation" aria-label="Navegación principal">
                <span class="nav-heading">Menú principal</span>
                <a href="dashboard_estud.php" class="nav-item"><i class="fas fa-home"></i> <span>Inicio</span></a>
                <a href="mis_asistencias.php" class="nav-item"><i class="far fa-calendar-check"></i> <span>Mis Asistencias</span></a>
                <a href="horario_clases.php" class="nav-item"><i class="far fa-calendar-alt"></i> <span>Horario de Clases</span></a>

                <span class="nav-heading">Académico</span>
                <a href="actividades_escolares.php" class="nav-item active"><i class="fas fa-book-open"></i> <span>Actividades Escolares</span></a>
                <a href="actividades.php" class="nav-item"><i class="fas fa-star"></i> <span>Regularizaciones</span></a>

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

    <main class="main-content">
        <div class="dashboard-container">
            
            <header class="topbar">
                <div class="topbar-left">
                    <button class="mobile-menu" id="mobileMenu"><i class="fas fa-bars"></i></button>
                    <div class="header-icon"><i class="fas fa-laptop-code"></i></div>
                    <div>
                        <h1>Aula Virtual</h1>
                        <p>Trabajos, material de apoyo y seguimiento.</p>
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

            <div class="layout-aula">
                
                <div class="hero-materia" style="background-image: <?php echo $bg_image; ?>;">
                    <a href="actividades_escolares.php" class="btn-back"><i class="fas fa-arrow-left"></i> Volver a Mis Materias</a>
                    <div>
                        <h1 class="hero-title"><?php echo htmlspecialchars($clase['nombre_materia']); ?></h1>
                        <div class="hero-prof"><i class="fas fa-chalkboard-user"></i> Prof. <?php echo htmlspecialchars($clase['maestro_nombre'] . ' ' . $clase['maestro_apellido']); ?></div>
                    </div>
                </div>

                <div class="col-main">
                    <?php if (isset($_GET['exito'])): ?>
                        <div style="background: #ecfdf5; color: #065f46; padding: 12px; border-radius: 8px; border: 1px solid #a7f3d0; font-weight: 600;"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['exito']); ?></div>
                    <?php endif; ?>
                    <?php if (isset($_GET['error'])): ?>
                        <div style="background: #fef2f2; color: #991b1b; padding: 12px; border-radius: 8px; border: 1px solid #fecaca; font-weight: 600;"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?></div>
                    <?php endif; ?>

                    <h2 class="section-title"><i class="fas fa-tasks"></i> Tareas y Material de Apoyo</h2>

                    <?php if (empty($actividades)): ?>
                        <div style="text-align:center; padding: 3rem; background:white; border-radius:12px; border: 1px dashed #cbd5e1; color:#64748b;">
                            <i class="fas fa-folder-open" style="font-size:3rem; color:#cbd5e1; margin-bottom:10px;"></i>
                            <p>El profesor aún no ha publicado actividades en esta materia.</p>
                        </div>
                    <?php else: ?>
                        
                        <?php foreach($actividades as $act): 
                            $es_tarea = ($act['permite_entregas'] == 1);
                            $icon_class = $es_tarea ? 'bg-tarea' : 'bg-material';
                            $icon_fa = $es_tarea ? 'fa-file-signature' : 'fa-file-pdf';
                            
                            $entregado = isset($mis_entregas[$act['id_actividad']]);
                            $info_entrega = $entregado ? $mis_entregas[$act['id_actividad']] : null;
                            
                            // Lógica de fechas (Bloqueo automático)
                            $tiene_limite = !empty($act['fecha_limite']);
                            $vencida = false;
                            if($tiene_limite) {
                                $fecha_limite_dt = new DateTime($act['fecha_limite']);
                                $vencida = ($fecha_actual > $fecha_limite_dt);
                            }
                            $acepta_retardo = ($act['acepta_retardo'] == 1);
                            $bloqueo_fecha = ($vencida && !$acepta_retardo);

                            // Lógica de Permiso Individual (Por defecto apagado, valor 0)
                            $permiso_individual = isset($mis_permisos[$act['id_actividad']]) ? $mis_permisos[$act['id_actividad']] : 0;
                        ?>
                            <div class="actividad-card">
                                <div class="act-icon <?php echo $icon_class; ?>"><i class="fas <?php echo $icon_fa; ?>"></i></div>
                                
                                <div class="act-content">
                                    <h4 class="act-title"><?php echo htmlspecialchars($act['titulo']); ?></h4>
                                    <p class="act-desc"><?php echo nl2br(htmlspecialchars($act['descripcion'])); ?></p>
                                    
                                    <div class="act-meta">
                                        <span class="meta-tag"><i class="far fa-clock"></i> Publicado: <?php echo date('d M', strtotime($act['fecha_publicacion'])); ?></span>
                                        <?php if($tiene_limite): ?>
                                            <?php if($vencida && !$entregado): ?>
                                                <span class="meta-tag tag-red"><i class="fas fa-exclamation-circle"></i> Vencida: <?php echo date('d M Y, h:i a', strtotime($act['fecha_limite'])); ?></span>
                                            <?php else: ?>
                                                <span class="meta-tag tag-orange"><i class="far fa-calendar-times"></i> Límite: <?php echo date('d M Y, h:i a', strtotime($act['fecha_limite'])); ?></span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($permiso_individual == 1): ?>
                                        <!-- SI EL MAESTRO LE DIO PERMISO, PUEDE VER TODO -->
                                        
                                        <?php if(!empty($act['archivo_ruta'])): 
                                            $archivo_bd = basename($act['archivo_ruta']);
                                            $nombre_material_limpio = preg_replace('/^mat_\d+_/', '', $archivo_bd);
                                        ?>
                                            <div style="margin-bottom: 15px;">
                                                <a href="../uploads/materiales_clase/<?php echo htmlspecialchars($archivo_bd); ?>" download="<?php echo htmlspecialchars($nombre_material_limpio); ?>" target="_blank" class="btn-action btn-outline">
                                                    <i class="fas fa-download"></i> <?php echo htmlspecialchars($nombre_material_limpio); ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>

                                        <?php if($es_tarea): ?>
                                            <div class="box-entrega">
                                                <h5><i class="fas fa-user-graduate"></i> Sección de Entrega</h5>

                                                <?php if($entregado): ?>
                                                    <div style="display:flex; gap: 10px; align-items: center; flex-wrap: wrap; margin-bottom: 10px;">
                                                        <?php if($info_entrega['estatus_entrega'] === 'Con Retardo'): ?>
                                                            <span class="status-entregado status-rojo" style="margin:0;"><i class="fas fa-clock"></i> Entregada (Retardo)</span>
                                                        <?php else: ?>
                                                            <span class="status-entregado status-verde" style="margin:0;"><i class="fas fa-check-double"></i> Entregada a Tiempo</span>
                                                        <?php endif; ?>
                                                        
                                                        <?php if(!empty($info_entrega['archivo_ruta'])): 
                                                            $archivo_entregado = basename($info_entrega['archivo_ruta']);
                                                            $nombre_entrega_limpio = preg_replace('/^al_\d+_\d+_/', '', $archivo_entregado);
                                                        ?>
                                                            <a href="../uploads/entregas_alumnos/<?php echo htmlspecialchars($archivo_entregado); ?>" download="<?php echo htmlspecialchars($nombre_entrega_limpio); ?>" target="_blank" class="btn-action btn-outline" style="background: white;">
                                                                <i class="fas fa-file-download"></i> Mi archivo: <?php echo htmlspecialchars($nombre_entrega_limpio); ?>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>

                                                    <?php if(!empty($info_entrega['retroalimentacion'])): ?>
                                                        <div style="background: white; border-left: 4px solid #3b82f6; padding: 12px; border-radius: 6px; font-size: 0.85rem; color: #334155; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                                                            <strong style="display:block; margin-bottom: 4px; color: #0f172a;">
                                                                <i class="fas fa-comment-dots" style="color: #3b82f6;"></i> Comentario del Profesor:
                                                            </strong>
                                                            <?php echo nl2br(htmlspecialchars($info_entrega['retroalimentacion'])); ?>
                                                        </div>
                                                    <?php endif; ?>

                                                <?php else: ?>
                                                    <!-- BLOQUEO POR FECHA ESTRICTO -->
                                                    <?php if($bloqueo_fecha): ?>
                                                        <div style="color: #991b1b; background:#fef2f2; border:1px solid #fecaca; padding:10px; border-radius:6px; font-size: 0.85rem; font-weight: 600;">
                                                            <i class="fas fa-lock"></i> La fecha límite ha pasado y esta tarea no acepta entregas con retardo.
                                                        </div>
                                                    <?php else: ?>
                                                        <form action="procesos/subir_tarea_clase.php" method="POST" enctype="multipart/form-data">
                                                            <input type="hidden" name="id_actividad" value="<?php echo $act['id_actividad']; ?>">
                                                            <input type="hidden" name="id_clase" value="<?php echo $id_clase; ?>">
                                                            
                                                            <?php if($vencida && $acepta_retardo): ?>
                                                                <div style="color: #dc2626; font-size: 0.8rem; font-weight: 700; margin-bottom: 10px;"><i class="fas fa-info-circle"></i> Nota: Esta tarea está vencida. Se registrará como "Entrega con retardo".</div>
                                                            <?php endif; ?>

                                                            <div style="display:flex; gap:10px; align-items: center; flex-wrap:wrap;">
                                                                <input type="file" name="archivo_tarea" required style="border: 1px solid #cbd5e1; padding: 6px; border-radius: 6px; background: white; font-size: 0.85rem; flex: 1; min-width: 200px;">
                                                                <button type="submit" class="btn-action btn-primary" style="padding: 8px 16px;">Confirmar Envío</button>
                                                            </div>
                                                        </form>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                    <?php else: ?>
                                        <!-- SI NO TIENE PERMISO, SE BLOQUEA TOTALMENTE LA INTERACCIÓN -->
                                        <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 15px; margin-top: 15px; color: #991b1b; font-size: 0.85rem;">
                                            <i class="fas fa-lock"></i> <strong>Acceso restringido:</strong> El profesor aún no te ha habilitado el permiso para descargar archivos o realizar entregas en esta actividad.
                                        </div>
                                    <?php endif; ?>

                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="col-side">
                    <?php if ($clase_en_vivo_activa): ?>
                        <a href="<?php echo htmlspecialchars($clase['enlace_clase_virtual']); ?>" target="_blank" class="btn-live-class">
                            <i class="fas fa-video"></i>
                            <strong>Entrar a Clase en Línea</strong>
                            <span>La sesión está activa ahora mismo</span>
                        </a>
                    <?php endif; ?>

                    <div class="side-widget">
                        <h3 class="widget-title"><i class="fas fa-user-check" style="color:#059669;"></i> Asistencia en Clase</h3>
                        <p style="font-size:0.85rem; color:#64748b; margin: 0 0 15px 0;">Tu registro de entradas exclusivo para esta asignatura.</p>
                        
                        <div class="attendance-stats">
                            <div class="att-box att-green">
                                <strong><?php echo $asistencias_clase; ?></strong>
                                <span>Presentes</span>
                            </div>
                            <div class="att-box att-red">
                                <strong><?php echo $faltas_clase; ?></strong>
                                <span>Faltas</span>
                            </div>
                        </div>
                        <a href="mis_asistencias.php" style="display:block; text-align:center; font-size:0.8rem; color:#3b82f6; font-weight:600; text-decoration:none; margin-top:15px;">
                            Ver desglose completo <i class="fas fa-external-link-alt"></i>
                        </a>
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
        });
    </script>
</body>
</html>