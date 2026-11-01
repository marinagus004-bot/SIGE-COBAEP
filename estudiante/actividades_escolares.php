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

// Valores por defecto
$nombre_pila = 'Alumno';
$apellido_p = '';
$nombre_completo = 'Alumno';
$iniciales = 'AL';
$matricula = 'Sin Matrícula';
$mis_clases = [];

try {
    // 1. Obtener datos básicos del alumno
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

    // 2. Obtener las Materias Regulares a las que está inscrito el alumno
    // (Asumimos que tienes una tabla CLASES y una MATERIAS. También añadimos un campo ficticio 'enlace_clase_virtual' para la lógica de clases en línea)
   $stmtClases = $pdo->prepare("
    SELECT c.id_clase, m.nombre_materia, m.imagen_portada, prof.nombre AS maestro_nombre, prof.apellido_paterno AS maestro_apellido,
           c.enlace_clase_virtual 
    FROM clase_regular_alumno ca
    INNER JOIN clases_regulares c ON ca.id_clase = c.id_clase
    INNER JOIN materias m ON c.id_materia = m.id_materia
    LEFT JOIN maestros prof ON c.id_maestro = prof.id_maestro
    WHERE ca.id_alumno = ? AND c.estatus = 'Activa' AND ca.acceso = 1
");
    $stmtClases->execute([$id_alumno]);
    $mis_clases = $stmtClases->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error cargando materias del alumno: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#063f2b">
    <title>Actividades Escolares · Panel Estudiante</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <link rel="stylesheet" href="style_dashboard_estud.css?v=<?php echo time(); ?>">

    <style>
        /* CORE LAYOUT */
        :root { --sidebar-width: 260px; --sidebar-collapsed-width: 80px; --bg-main: #f8fafc; }
        body { background-color: var(--bg-main); margin: 0; padding: 0; overflow: hidden; box-sizing: border-box; }
        *, *::before, *::after { box-sizing: border-box; }

        .main-content { margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width)); padding: 1.2rem 2rem; height: 100vh; overflow-y: auto; transition: margin-left 0.3s ease, width 0.3s ease; }
        body.sidebar-collapsed .main-content { margin-left: var(--sidebar-collapsed-width); width: calc(100% - var(--sidebar-collapsed-width)); }
        .dashboard-container { max-width: 1200px; margin: 0 auto; display: flex; flex-direction: column; gap: 1rem; }

        /* HEADER */
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
        .topbar-left { display: flex; align-items: center; gap: 15px; }
        .header-icon { background: #059669; color: white; width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; box-shadow: 0 2px 4px rgba(5, 150, 105, 0.2); }
        .topbar-left h1 { font-size: 1.4rem; font-weight: 800; color: #0f172a; margin: 0 0 0.1rem 0; letter-spacing: -0.5px; }
        .topbar-left p { margin: 0; color: #64748b; font-size: 0.85rem; }
        
        .topbar-right { display: flex; align-items: center; gap: 10px; }
        .date-pill { background: white; padding: 0.5rem 1rem; border-radius: 99px; font-size: 0.8rem; font-weight: 600; color: #475569; border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 8px; }
        .date-pill i { color: #059669; }

        /* DROPDOWN PERFIL */
        .profile-container { position: relative; }
        .profile-pill { background: white; padding: 0.3rem 0.8rem 0.3rem 0.3rem; border-radius: 99px; display: flex; align-items: center; gap: 10px; border: 1px solid #e2e8f0; cursor: pointer; transition: background 0.2s; }
        .profile-pill:hover { background: #f8fafc; }
        .profile-pill .avatar { width: 34px; height: 34px; background: #063f2b; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; }
        .profile-info strong { display: block; font-size: 0.85rem; color: #0f172a; }
        .profile-info span { display: block; font-size: 0.7rem; color: #64748b; }
        
        .profile-dropdown { position: absolute; top: calc(100% + 10px); right: 0; background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); width: 220px; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.2s; z-index: 100; }
        .profile-dropdown.active { opacity: 1; visibility: visible; transform: translateY(0); }
        .profile-dropdown-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #475569; text-decoration: none; font-size: 0.9rem; font-weight: 600; transition: all 0.2s; }
        .profile-dropdown-item:hover { background: #f8fafc; color: #0f172a; padding-left: 20px; }
        .mobile-menu { display: none; background: none; border: none; padding: 0; font-size: 1.6rem; color: #0f172a; cursor: pointer; }

        /* ==============================================================
           TARJETAS DE MATERIAS (NIVEL 1)
        ============================================================== */
        .empty-state { background: white; border: 2px dashed #cbd5e1; border-radius: 16px; padding: 4rem 2rem; text-align: center; }
        .empty-state i { font-size: 3.5rem; color: #94a3b8; margin-bottom: 1rem; }
        .empty-state h3 { color: #0f172a; margin: 0 0 0.5rem 0; font-size: 1.2rem; }
        .empty-state p { color: #64748b; margin: 0; font-size: 0.95rem; }

        .activities-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem; }

        .activity-card { background: white; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); display: flex; flex-direction: column; transition: transform 0.2s ease, box-shadow 0.2s ease; position: relative;}
        .activity-card:hover { transform: translateY(-4px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); }

        .activity-header { height: 120px; background-size: cover; background-position: center; background-repeat: no-repeat; position: relative; }
        
        /* Insignias de la materia */
        .badges-container { position: absolute; top: 12px; left: 12px; display: flex; flex-direction: column; gap: 6px; align-items: flex-start;}
        .activity-badge { background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); color: white; padding: 4px 10px; border-radius: 50px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        
        /* Insignia de Clase en Vivo (Parpadeante) */
        .live-badge { background: rgba(239, 68, 68, 0.9); display: flex; align-items: center; gap: 6px; animation: pulseLive 2s infinite; }
        .live-dot { width: 6px; height: 6px; background: white; border-radius: 50%; display: block; }
        @keyframes pulseLive { 0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.7); } 70% { box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); } 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); } }

        .activity-body { padding: 1.25rem; flex: 1; display: flex; flex-direction: column; }
        .activity-body h3 { margin: 0 0 1rem 0; color: #0f172a; font-size: 1.15rem; font-weight: 800; line-height: 1.3; }

        .activity-meta { background: #f8fafc; border-radius: 10px; padding: 12px; border: 1px solid #e2e8f0; margin-bottom: 1rem; }
        .meta-item { display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: #475569; font-weight: 600; margin-bottom: 8px; }
        .meta-item:last-child { margin-bottom: 0; }
        .meta-item i.fa-chalkboard-user { color: #059669; }
        .meta-item i.fa-tasks { color: #3b82f6; }
        .meta-item i.fa-user-check { color: #8b5cf6; }

        .activity-footer { padding: 0 1.25rem 1.25rem 1.25rem; }
        .btn-enter { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 12px; background: #059669; color: white; border-radius: 10px; font-weight: 700; font-size: 0.9rem; text-decoration: none; transition: 0.2s; }
        .btn-enter:hover { background: #047857; }
        
        .btn-live { background: #ef4444; }
        .btn-live:hover { background: #dc2626; }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            body { overflow: auto; }
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 1rem; height: auto; }
            .topbar { flex-direction: column; align-items: stretch; gap: 0.8rem; }
            .topbar-left { align-items: flex-start; }
            .topbar-left h1 { font-size: 1.25rem; }
            .mobile-menu { display: block; margin-top: 3px; }
            .topbar-right { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; width: 100%; }
            .date-pill, .profile-pill { width: 100%; justify-content: center; padding: 0.5rem; }
            .activities-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

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
                <!-- ESTA ES LA OPCIÓN EN LA QUE ESTAMOS[cite: 33] -->
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
                    <button class="mobile-menu" id="mobileMenu" type="button"><i class="fas fa-bars"></i></button>
                    <div class="header-icon"><i class="fas fa-book-open"></i></div>
                    <div>
                        <h1>Actividades Escolares</h1>
                        <p>Tus materias, tareas, asistencia y clases virtuales.</p>
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

            <section class="clases-section">
                <?php if (empty($mis_clases)): ?>
                    <div class="empty-state">
                        <i class="fas fa-box-open"></i>
                        <h3>Sin materias asignadas</h3>
                        <p>Aún no has sido inscrito en ninguna clase para este ciclo escolar.</p>
                    </div>
                <?php else: ?>
                    
                    <div class="activities-grid">
                        <?php foreach ($mis_clases as $clase): ?>
                            
                            <?php 
                                $bg_image = "linear-gradient(145deg, #0f172a, #063f2b)";
                                if (!empty($clase['imagen_portada'])) {
                                    $ruta_img = '../' . $clase['imagen_portada'];
                                    $bg_image = "linear-gradient(rgba(15, 23, 42, 0.4), rgba(15, 23, 42, 0.8)), url('" . htmlspecialchars($ruta_img) . "')";
                                }
                                
                                // Simulación: Comprobar si el maestro configuró un enlace de videollamada para hoy
                                $clase_en_vivo_activa = !empty($clase['enlace_clase_virtual']);
                            ?>

                            <div class="activity-card">
                                <div class="activity-header" style="background-image: <?php echo $bg_image; ?>;">
                                    <div class="badges-container">
                                        <span class="activity-badge">Materia</span>
                                        <?php if($clase_en_vivo_activa): ?>
                                            <span class="activity-badge live-badge"><span class="live-dot"></span> EN VIVO</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="activity-body">
                                    <h3><?php echo htmlspecialchars($clase['nombre_materia']); ?></h3>
                                    
                                    <div class="activity-meta">
                                        <div class="meta-item">
                                            <i class="fas fa-chalkboard-user"></i> 
                                            <span>Prof: <?php echo htmlspecialchars($clase['maestro_nombre'] . ' ' . $clase['maestro_apellido']); ?></span>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-tasks"></i> 
                                            <span>Actividades y Trabajos</span>
                                        </div>
                                        <div class="meta-item">
                                            <i class="fas fa-user-check"></i> 
                                            <span>Asistencia de la materia</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="activity-footer">
                                    <?php if($clase_en_vivo_activa): ?>
                                        <!-- Si hay clase por mal clima, se pinta rojo y enlaza directo a la clase y a la plataforma -->
                                        <a href="aula_virtual.php?id=<?php echo $clase['id_clase']; ?>" class="btn-enter btn-live">
                                            Entrar a Clase en Línea <i class="fas fa-video"></i>
                                        </a>
                                    <?php else: ?>
                                        <!-- Botón normal verde -->
                                        <a href="aula_virtual.php?id=<?php echo $clase['id_clase']; ?>" class="btn-enter">
                                            Ver Actividades <i class="fas fa-arrow-right"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                        <?php endforeach; ?>
                    </div>
                    
                <?php endif; ?>
            </section>
            
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