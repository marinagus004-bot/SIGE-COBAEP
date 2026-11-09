<?php
// Obtener el nombre del archivo actual (ej. 'gestion_horarios.php')
$pagina_actual = basename($_SERVER['PHP_SELF']);
?>

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

        <nav class="navigation">
            <span class="nav-heading">Menú principal</span>
            <a href="dashboard_admin.php" class="nav-item <?php echo ($pagina_actual == 'dashboard_admin.php') ? 'active' : ''; ?>">
                <i class="fas fa-house"></i><span>Panel de Control</span>
            </a>

            <span class="nav-heading">Académico</span>
            <a href="gestion_grupos.php" class="nav-item <?php echo ($pagina_actual == 'gestion_grupos.php') ? 'active' : ''; ?>">
                <i class="fas fa-users-rectangle"></i><span>Gestión de Grupos</span>
            </a>
            <a href="gestion_horarios.php" class="nav-item <?php echo ($pagina_actual == 'gestion_horarios.php') ? 'active' : ''; ?>">
                <i class="fas fa-calendar-days"></i><span>Horarios Docentes</span>
            </a>
            <a href="asignacion_masiva.php" class="nav-item <?php echo ($pagina_actual == 'asignacion_masiva.php') ? 'active' : ''; ?>">
                <i class="fas fa-people-arrows"></i><span>Asignar Docentes</span>
            </a>

            <span class="nav-heading">Personal</span>
            <a href="gestion_usuarios.php" class="nav-item <?php echo ($pagina_actual == 'gestion_usuarios.php') ? 'active' : ''; ?>">
                <i class="fas fa-users"></i><span>Directorio de Personal</span>
            </a>
            <a href="agregar_usuario.php" class="nav-item <?php echo ($pagina_actual == 'agregar_usuario.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-plus"></i><span>Nuevo Registro</span>
            </a>

            <span class="nav-heading">Información</span>
            <a href="reportes.php" class="nav-item <?php echo ($pagina_actual == 'reportes.php') ? 'active' : ''; ?>">
                <i class="fas fa-chart-column"></i><span>Reportes y Listas</span>
            </a>
            <a href="horarios_plantel.php" class="nav-item <?php echo ($pagina_actual == 'horarios_plantel.php') ? 'active' : ''; ?>">
                <i class="fas fa-calendar-week"></i><span>Horarios del Plantel</span>
            </a>
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