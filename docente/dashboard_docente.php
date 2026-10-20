<?php
session_start();
require_once '../config/database.php';

// Validación estricta: Busca el rol 'docente'
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../login.php");
    exit();
}

// Obtenemos el nombre y el ID del maestro desde la sesión
$nombre_usuario = isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') : 'Docente';
$id_docente = $_SESSION['id_usuario'];

// =======================================================
// CONSULTAS REALES A LA BASE DE DATOS
// =======================================================
try {
    // 1. Total de grupos asignados al maestro
    // Se usa la tabla HORARIOS_MAESTRO que vincula id_maestro con id_grupo
    $stmt_grupos = $pdo->prepare("SELECT COUNT(DISTINCT id_grupo) FROM HORARIOS_MAESTRO WHERE id_maestro = ?");
    $stmt_grupos->execute([$id_docente]);
    $total_grupos = $stmt_grupos->fetchColumn();

    // 2. Total de alumnos en los grupos de este maestro
    // Se cuenta a los alumnos activos (sin fecha_baja) en GRUPO_ALUMNO cuyos grupos correspondan al maestro
    $stmt_alumnos = $pdo->prepare("
        SELECT COUNT(DISTINCT ga.id_alumno) 
        FROM GRUPO_ALUMNO ga
        INNER JOIN HORARIOS_MAESTRO hm ON ga.id_grupo = hm.id_grupo
        WHERE hm.id_maestro = ? AND ga.fecha_baja IS NULL
    ");
    $stmt_alumnos->execute([$id_docente]);
    $total_alumnos = $stmt_alumnos->fetchColumn();

    // 3. Regularizaciones activas
    // Se consulta la tabla CLASES_REGULARIZACION para este maestro con estatus 'Activa'
    $stmt_reg = $pdo->prepare("SELECT COUNT(*) FROM CLASES_REGULARIZACION WHERE id_maestro = ? AND estatus = 'Activa'");
    $stmt_reg->execute([$id_docente]);
    $regularizaciones_activas = $stmt_reg->fetchColumn();

} catch (PDOException $e) {
    // Si hay un error en la base de datos, mostramos 0 para no romper la página y lo registramos en el log
    error_log("Error obteniendo estadísticas del docente: " . $e->getMessage());
    $total_grupos = 0;
    $total_alumnos = 0;
    $regularizaciones_activas = 0;
}
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#063f2b">
    <title>Portal Docente · SIGE COBAEP 27</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <!-- Enlace corregido al CSS con actualizador de caché -->
    <link rel="stylesheet" href="style_dashboard_docente.css?v=<?php echo time(); ?>">
</head>

<body>

    <!-- ================= BARRA LATERAL ================= -->
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

            <!-- Navegación corregida apuntando a dashboard_docente.php -->
            <nav class="navigation" aria-label="Navegación principal">
                <span class="nav-heading">Menú principal</span>
                <a href="dashboard_docente.php" class="nav-item active">
                    <i class="fas fa-house"></i>
                    <span>Inicio</span>
                </a>

                <span class="nav-heading">Académico</span>
                <a href="mis_grupos.php" class="nav-item">
                    <i class="fas fa-users-rectangle"></i>
                    <span>Mis Grupos</span>
                </a>
                <a href="horario.php" class="nav-item">
                    <i class="far fa-calendar-alt"></i>
                    <span>Mi Horario</span>
                </a>

                <span class="nav-heading">Evaluación</span>
                <a href="regularizaciones.php" class="nav-item">
                    <i class="fas fa-book-open-reader"></i>
                    <span>Regularizaciones</span>
                </a>
            </nav>

            <div class="sidebar-bottom">
                <div class="institution">
                    <div class="institution-icon">
                        <i class="fas fa-building-columns"></i>
                    </div>
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

    <!-- ================= CONTENIDO PRINCIPAL ================= -->
    <main class="main-content">

        <!-- TOPBAR -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu" id="mobileMenu" type="button" aria-label="Abrir menú">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <span class="eyebrow">PORTAL DOCENTE · SIGE</span>
                    <h1>¡Hola, Maestro(a) <?php echo $nombre_usuario; ?>!</h1>
                    <p>Aquí tienes el resumen de tus grupos y actividades académicas.</p>
                </div>
            </div>

            <div class="topbar-right">
                <div class="date-pill">
                    <i class="far fa-calendar"></i>
                    <span id="fechaTexto">Cargando...</span>
                </div>
                <button class="notification-button" type="button" aria-label="Notificaciones">
                    <i class="far fa-bell"></i>
                    <span class="notification-dot"></span>
                </button>
                <div class="profile">
                    <div class="profile-avatar"><i class="fas fa-chalkboard-user"></i></div>
                    <div class="profile-info">
                        <strong><?php echo $nombre_usuario; ?></strong>
                        <span>Docente</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- TARJETAS DE ESTADÍSTICAS -->
        <section class="stats-grid" aria-label="Resumen Académico">
            <article class="stat-card groups">
                <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                <div class="stat-content">
                    <span>Mis Grupos</span>
                    <strong><?php echo $total_grupos; ?></strong>
                    <small>Grupos asignados</small>
                </div>
            </article>

            <article class="stat-card students">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-content">
                    <span>Alumnos</span>
                    <strong><?php echo $total_alumnos; ?></strong>
                    <small>Total en tus clases</small>
                </div>
            </article>

            <article class="stat-card regularizations">
                <div class="stat-icon"><i class="fas fa-file-signature"></i></div>
                <div class="stat-content">
                    <span>Regularizaciones</span>
                    <strong><?php echo $regularizaciones_activas; ?></strong>
                    <small>Procesos activos</small>
                </div>
            </article>
        </section>

        <!-- ACCIONES RÁPIDAS -->
        <section class="section">
            <div class="section-heading">
                <div>
                    <span class="section-kicker">ACCESOS</span>
                    <h2>Acciones Rápidas</h2>
                </div>
            </div>

            <div class="quick-grid">
                <!-- Acción 1: Consultar Grupos -->
                <a href="mis_grupos.php" class="quick-card">
                    <div class="quick-top">
                        <div class="quick-icon blue"><i class="fas fa-clipboard-list"></i></div>
                    </div>
                    <h3>Consultar Calificaciones</h3>
                    <p>Revisa las calificaciones y el desempeño actual de los alumnos en tus grupos.</p>
                    <span class="quick-link">Ver mis grupos <i class="fas fa-arrow-right"></i></span>
                </a>

                <!-- Acción 2: Regularizaciones -->
                <a href="regularizaciones.php" class="quick-card">
                    <div class="quick-top">
                        <div class="quick-icon orange"><i class="fas fa-folder-open"></i></div>
                    </div>
                    <h3>Gestionar Regularizaciones</h3>
                    <p>Crea espacios de recuperación, sube material de apoyo y activa la entrega de trabajos.</p>
                    <span class="quick-link">Ir a regularizaciones <i class="fas fa-arrow-right"></i></span>
                </a>

                <!-- Acción 3: Horarios -->
                <a href="horario.php" class="quick-card">
                    <div class="quick-top">
                        <div class="quick-icon green"><i class="far fa-clock"></i></div>
                    </div>
                    <h3>Mis Horarios</h3>
                    <p>Consulta tus horarios de clase asignados para el semestre en curso.</p>
                    <span class="quick-link">Ver horarios <i class="fas fa-arrow-right"></i>
                </span>
                </a>
            </div>
        </section>

    </main>

    <!-- Enlace al JS con el nombre exacto que tienes en VS Code -->
    <script src="dashboard_maestro.js"></script>
</body>
</html>




  <span class="nav-heading">Evaluación</span>
                <a href="regularizaciones.php" class="nav-item">
                    <i class="fas fa-book-open-reader"></i>
                    <span>Regularizaciones</span>
                </a>