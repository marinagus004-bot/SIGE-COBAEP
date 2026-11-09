<?php
session_start();
require_once '../config/database.php';
require_once '../includes/directorio_horarios.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../login.php");
    exit();
}
date_default_timezone_set('America/Mexico_City');
$nombre_usuario = htmlspecialchars($_SESSION['nombre'] ?? 'Administrador', ENT_QUOTES, 'UTF-8');
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
    <title>Horarios del Plantel · COBAEP Plantel 27</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="style_dashboard_admin.css">
    <link rel="stylesheet" href="style_sidebar.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu" id="mobileMenu" type="button" aria-label="Abrir menú"><i class="fas fa-bars"></i></button>
                <div>
                    <span class="eyebrow">INFORMACIÓN · SIGE</span>
                    <h1>Horarios del Plantel</h1>
                    <p>Consulta el horario de cualquier docente o grupo. Para subir o cambiar horarios usa <a href="gestion_horarios.php">Horarios Docentes</a>.</p>
                </div>
            </div>
            <div class="topbar-right">
                <div class="date-pill"><i class="far fa-calendar"></i><span><?php echo date('d/m/Y H:i'); ?></span></div>
                <div class="profile">
                    <div class="profile-avatar"><i class="fas fa-user"></i></div>
                    <div class="profile-info"><strong><?php echo $nombre_usuario; ?></strong><span>Administrador</span></div>
                </div>
            </div>
        </header>
        <?php dh_render($datos); ?>
    </main>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar'), overlay = document.getElementById('sidebarOverlay'), mobileMenu = document.getElementById('mobileMenu'), collapseToggle = document.getElementById('collapseToggle'), body = document.body;
            if (localStorage.getItem('sidebarCollapsed') === 'true') body.classList.add('sidebar-collapsed');
            if (collapseToggle) collapseToggle.addEventListener('click', () => { body.classList.toggle('sidebar-collapsed'); localStorage.setItem('sidebarCollapsed', body.classList.contains('sidebar-collapsed')); });
            if (mobileMenu) mobileMenu.addEventListener('click', () => { sidebar.classList.add('mobile-open'); overlay.classList.add('visible'); body.classList.add('menu-open'); });
            if (overlay) overlay.addEventListener('click', () => { sidebar.classList.remove('mobile-open'); overlay.classList.remove('visible'); body.classList.remove('menu-open'); });
        });
    </script>
</body>
</html>
