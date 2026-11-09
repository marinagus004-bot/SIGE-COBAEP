<?php
session_start();
require_once '../config/database.php';
require_once '../includes/directorio_horarios.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'prefectos') {
    header("Location: ../login.php");
    exit();
}
date_default_timezone_set('America/Mexico_City');
$nombre_usuario = htmlspecialchars($_SESSION['nombre'] ?? 'Prefecto', ENT_QUOTES, 'UTF-8');
try {
    $datos = dh_cargar($pdo);
} catch (PDOException $e) {
    error_log('Horarios del plantel: ' . $e->getMessage());
    $datos = ['maestros' => [], 'grupos' => [], 'turnos' => []];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#063f2b">
    <title>Horarios del Plantel · Prefectura</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../admin/style_dashboard_admin.css">
    <link rel="stylesheet" href="../admin/style_sidebar.css">
    <link rel="stylesheet" href="../Prefectura/style_dashboard_prefectura.css">
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <button id="collapseToggle" class="collapse-toggle" aria-label="Colapsar menú"><i class="fas fa-chevron-left"></i></button>
        <div class="sidebar-inner">
            <div class="brand">
                <img src="../img/LogoCobaep.png" alt="Logo COBAEP" class="brand-logo">
                <div class="brand-copy"><strong>SIGE<span>-Cobaep</span></strong><small>Plantel 27</small></div>
            </div>
            <nav class="navigation" aria-label="Navegación principal">
                <span class="nav-heading">Menú principal</span>
                <a href="dashboard_prefectura.php" class="nav-item"><i class="fas fa-shield-halved"></i><span>Control de Acceso</span></a>
                <a href="monitoreo_grupos.php" class="nav-item"><i class="fas fa-users-viewfinder"></i><span>Monitoreo de Grupos</span></a>
                <a href="historial_asistencias.php" class="nav-item"><i class="fas fa-history"></i><span>Historial de Asistencias</span></a>
                <a href="creacion_reportes.php" class="nav-item"><i class="fa-solid fa-file-lines"></i><span>Creacion de Reporte de Conducta</span></a>
                <a href="expediente_alumnos.php" class="nav-item"><i class="fas fa-folder-open"></i><span>Expediente de Alumnos</span></a>
                <a href="horarios_plantel.php" class="nav-item active"><i class="fas fa-calendar-week"></i><span>Horarios del Plantel</span></a>
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
                <div><span class="eyebrow">PREFECTURA · CONSULTA</span><h1>Horarios del Plantel</h1><p>Ubica qué grupo tiene clase y con qué docente en cada momento.</p></div>
            </div>
            <div class="topbar-right">
                <div class="profile"><div class="profile-avatar"><i class="fas fa-user-shield"></i></div><div class="profile-info"><strong><?php echo $nombre_usuario; ?></strong><span>Prefecto</span></div></div>
            </div>
        </header>
        <?php dh_render($datos); ?>
    </main>
    <script src="../Prefectura/procesos/dashboard_prefecturas.js"></script>
</body>
</html>
