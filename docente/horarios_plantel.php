<?php
session_start();
require_once '../config/database.php';
require_once __DIR__ . '/includes/topbar_docente.php';
require_once '../includes/directorio_horarios.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../login.php");
    exit();
}
date_default_timezone_set('America/Mexico_City');
$id_docente = (int)$_SESSION['id_usuario'];
$nombre_usuario = htmlspecialchars($_SESSION['nombre'] ?? 'Docente', ENT_QUOTES, 'UTF-8');
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
    <title>Horarios del Plantel · SIGE COBAEP 27</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="style_dashboard_docente.css?v=<?php echo time(); ?>">
</head>
<body>
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
                <a href="horario.php" class="nav-item"><i class="far fa-calendar-alt"></i><span>Mi Horario</span></a>
                <a href="horarios_plantel.php" class="nav-item active"><i class="fas fa-calendar-week"></i><span>Horarios del Plantel</span></a>
                <span class="nav-heading">Evaluación</span>
                <a href="regularizaciones.php" class="nav-item"><i class="fas fa-book-open-reader"></i><span>Regularizaciones</span></a>
            </nav>
            <div class="sidebar-bottom">
                <div class="institution">
                    <div class="institution-icon"><i class="fas fa-building-columns"></i></div>
                    <div><strong>COBAEP Plantel 27</strong><span>Portal Docente</span></div>
                </div>
                <a href="../logout.php" class="logout"><i class="fas fa-arrow-right-from-bracket"></i><span>Cerrar Sesión</span></a>
            </div>
        </div>
    </aside>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu" id="mobileMenu"><i class="fas fa-bars"></i></button>
                <div>
                    <span class="eyebrow">ACADÉMICO · DOCENTE</span>
                    <h1>Horarios del Plantel</h1>
                    <p>Consulta el horario de tus compañeros y de cualquier grupo.</p>
                </div>
            </div>
            <?php docente_topbar($pdo); ?>
        </header>
        <?php dh_render($datos, ['mi_maestro' => $id_docente]); ?>
    </main>
    <script src="dashboard_maestro.js"></script>
</body>
</html>
