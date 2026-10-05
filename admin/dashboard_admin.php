<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../login.php");
    exit();
}

try {
    $stmt_alumnos = $pdo->query("SELECT COUNT(*) FROM ALUMNOS");
    $total_alumnos = $stmt_alumnos->fetchColumn();

    $stmt_docentes = $pdo->query("SELECT COUNT(*) FROM MAESTROS");
    $total_docentes = $stmt_docentes->fetchColumn();

    $stmt_asist = $pdo->query("SELECT COUNT(*) FROM ASISTENCIAS
                               WHERE DATE(fecha_hora_escaneo) = CURDATE()
                               AND estatus IN ('Puntual', 'Retardo')");
    $asistencias_hoy = $stmt_asist->fetchColumn();

} catch (PDOException $e) {
    $total_alumnos = 0;
    $total_docentes = 0;
    $asistencias_hoy = 0;
}

$nombre_usuario = isset($_SESSION['nombre'])
    ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8')
    : 'Administrador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#063f2b">
    <title>Panel de Control · COBAEP Plantel 27</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <!-- Asegúrate de que el nombre del CSS coincida con tu archivo local -->
    <link rel="stylesheet" href="style_dashboard_admin.css">
</head>

<body>

    <!-- Barra lateral -->
    <aside class="sidebar" id="sidebar">

        <!-- Botón para colapsar/expandir en escritorio -->
        <button id="collapseToggle" class="collapse-toggle" aria-label="Colapsar menú">
            <i class="fas fa-chevron-left"></i>
        </button>

        <div class="sidebar-inner">
            
            <!-- INICIO SECCIÓN LOGO Y TEXTO -->
            <div class="brand">
                <img src="../img/LogoCobaep.png" alt="Logo COBAEP" class="brand-logo">
                <div class="brand-copy">
                    <strong>SIGE<span>-Cobaep</span></strong>
                    <small>Plantel 27</small>
                </div>
            </div>

            <nav class="navigation" aria-label="Navegación principal">

                <span class="nav-heading">Menú principal</span>

                <a href="dashboard_admin.php" class="nav-item active">
                    <i class="fas fa-house"></i>
                    <span>Panel de Control</span>
                </a>

                <span class="nav-heading">Académico</span>

                <a href="gestion_grupos.php" class="nav-item">
                    <i class="fas fa-users-rectangle"></i>
                    <span>Gestión de Grupos</span>
                </a>
                <a href="gestion_horarios.php" class="nav-item">
                    <i class="fas fa-calendar-days"></i>
                    <span>Gestión de Horarios</span>
                </a>


                <span class="nav-heading">Personal</span>

                <a href="gestion_usuarios.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>Directorio de Personal</span>
                </a>

                <a href="agregar_usuario.php" class="nav-item">
                    <i class="fas fa-user-plus"></i>
                    <span>Nuevo Registro</span>
                </a>

                <span class="nav-heading">Información</span>

                <a href="reportes.php" class="nav-item">
                    <i class="fas fa-chart-column"></i>
                    <span>Reportes y Listas</span>
                </a>

            </nav>

            <div class="sidebar-bottom">

                <div class="institution">
                    <div class="institution-icon">
                        <i class="fas fa-building-columns"></i>
                    </div>

                    <div>
                        <strong>COBAEP Plantel 27</strong>
                        <span>Zaragoza, Puebla</span>
                    </div>
                </div>

                <a href="../logout.php" class="logout">
                    <i class="fas fa-arrow-right-from-bracket"></i>
                    <span>Cerrar Sesión</span>
                </a>

            </div>

        </div>

    </aside>

    <!-- Fondo para menú móvil -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Contenido principal -->
    <main class="main-content">

        <header class="topbar">

            <div class="topbar-left">

                <button
                    class="mobile-menu"
                    id="mobileMenu"
                    type="button"
                    aria-label="Abrir menú">
                    <i class="fas fa-bars"></i>
                </button>

                <div>
                    <span class="eyebrow">
                        ADMINISTRACIÓN · SIGE
                    </span>

                    <h1>
                        ¡Bienvenido, <?php echo $nombre_usuario; ?>!
                    </h1>

                    <p>
                        Aquí tienes un resumen general del sistema.
                    </p>
                </div>

            </div>

            <div class="topbar-right">

                <div class="date-pill">
                    <i class="far fa-calendar"></i>
                    <span id="fechaTexto">
                        <?php echo date('d/m/Y H:i'); ?>
                    </span>
                </div>

                <button
                    class="notification-button"
                    type="button"
                    aria-label="Notificaciones">
                    <i class="far fa-bell"></i>
                    <span class="notification-dot"></span>
                </button>

                <div class="profile">

                    <div class="profile-avatar">
                        <i class="fas fa-user"></i>
                    </div>

                    <div class="profile-info">
                        <strong><?php echo $nombre_usuario; ?></strong>
                        <span>Administrador</span>
                    </div>

                </div>

            </div>

        </header>

        <!-- Estadísticas principales -->
        <section class="stats-grid" aria-label="Resumen">

            <article class="stat-card students">

                <div class="stat-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>

                <div class="stat-content">
                    <span>Total de alumnos</span>
                    <strong><?php echo $total_alumnos; ?></strong>
                    <small>Alumnos registrados</small>
                </div>

                <div class="stat-arrow">
                    <i class="fas fa-arrow-up-right"></i>
                </div>

            </article>

            <article class="stat-card teachers">

                <div class="stat-icon">
                    <i class="fas fa-chalkboard-user"></i>
                </div>

                <div class="stat-content">
                    <span>Docentes</span>
                    <strong><?php echo $total_docentes; ?></strong>
                    <small>Docentes registrados</small>
                </div>

                <div class="stat-arrow">
                    <i class="fas fa-arrow-up-right"></i>
                </div>

            </article>

            <article class="stat-card attendance">

                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>

                <div class="stat-content">
                    <span>Asistencias hoy</span>
                    <strong><?php echo $asistencias_hoy; ?></strong>
                    <small>Registros de hoy</small>
                </div>

                <div class="stat-arrow">
                    <i class="fas fa-arrow-up-right"></i>
                </div>

            </article>

        </section>

        <!-- Acciones rápidas -->
        <section class="section">

            <div class="section-heading">
                <div>
                    <span class="section-kicker">GESTIÓN</span>
                    <h2>Acciones rápidas</h2>
                </div>

                <span class="section-description">
                    Accede a las funciones más utilizadas
                </span>
            </div>

            <div class="quick-grid">

                <a href="agregar_usuario.php" class="quick-card">

                    <div class="quick-top">
                        <div class="quick-icon green">
                            <i class="fas fa-user-plus"></i>
                        </div>

                        <span class="quick-number">01</span>
                    </div>

                    <h3>Registrar estudiante</h3>

                    <p>
                        Agrega nuevos usuarios y registra
                        la información académica correspondiente.
                    </p>

                    <span class="quick-link">
                        Ir al registro
                        <i class="fas fa-arrow-right"></i>
                    </span>

                </a>

                <a href="gestion_usuarios.php" class="quick-card">

                    <div class="quick-top">
                        <div class="quick-icon blue">
                            <i class="fas fa-users"></i>
                        </div>

                        <span class="quick-number">02</span>
                    </div>

                    <h3>Administrar personal</h3>

                    <p>
                        Consulta, edita y administra
                        los usuarios registrados en el sistema.
                    </p>

                    <span class="quick-link">
                        Gestionar personal
                        <i class="fas fa-arrow-right"></i>
                    </span>

                </a>

                <a href="reportes.php" class="quick-card">

                    <div class="quick-top">
                        <div class="quick-icon orange">
                            <i class="fas fa-chart-line"></i>
                        </div>

                        <span class="quick-number">03</span>
                    </div>

                    <h3>Reportes y listas</h3>

                    <p>
                        Consulta información y genera
                        reportes para la administración escolar.
                    </p>

                    <span class="quick-link">
                        Ver reportes
                        <i class="fas fa-arrow-right"></i>
                    </span>

                </a>

            </div>

        </section>

        <!-- Resumen inferior -->
        <section class="dashboard-bottom">

            <article class="overview-card">

                <div class="card-heading">

                    <div>
                        <span class="section-kicker">SISTEMA</span>
                        <h2>Resumen general</h2>
                    </div>

                    <span class="status-badge">
                        <i class="fas fa-circle"></i>
                        Operativo
                    </span>

                </div>

                <div class="overview-body">

                    <div class="overview-main">
                        <div class="overview-circle">
                            <i class="fas fa-school"></i>
                        </div>

                        <div>
                            <strong>COBAEP Plantel 27</strong>
                            <p>
                                Panel administrativo central
                                del Sistema de Información y
                                Gestión Educativa.
                            </p>
                        </div>
                    </div>

                    <div class="progress-area">

                        <div class="progress-label">
                            <span>Alumnos registrados</span>
                            <strong><?php echo $total_alumnos; ?></strong>
                        </div>

                        <div class="progress">
                            <span style="width: <?php echo min((int)$total_alumnos, 100); ?>%;"></span>
                        </div>

                        <div class="progress-label">
                            <span>Docentes registrados</span>
                            <strong><?php echo $total_docentes; ?></strong>
                        </div>

                        <div class="progress">
                            <span style="width: <?php echo min((int)$total_docentes, 100); ?>%;"></span>
                        </div>

                    </div>

                </div>

            </article>

            <article class="welcome-card">

                <div class="welcome-decoration"></div>

                <div class="welcome-content">

                    <div class="welcome-icon">
                        <i class="fas fa-shield-halved"></i>
                    </div>

                    <span>ADMINISTRACIÓN ESCOLAR</span>

                    <h2>
                        Todo bajo control.
                    </h2>

                    <p>
                        Gestiona la información de tu plantel
                        desde un solo lugar.
                    </p>

                </div>

            </article>

        </section>

        <footer class="footer">
            <span>© 2026 COBAEP Plantel 27</span>
            <span>·</span>
            <span>SIGE · Sistema de Información y Gestión Educativa</span>
        </footer>

    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {

            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const mobileMenu = document.getElementById('mobileMenu');
            const fechaTexto = document.getElementById('fechaTexto');
            const collapseToggle = document.getElementById('collapseToggle');
            const body = document.body;

            // --- LÓGICA DE COLAPSO EN ESCRITORIO ---
            if (localStorage.getItem('sidebarCollapsed') === 'true') {
                body.classList.add('sidebar-collapsed');
            }

            if (collapseToggle) {
                collapseToggle.addEventListener('click', () => {
                    body.classList.toggle('sidebar-collapsed');
                    localStorage.setItem('sidebarCollapsed', body.classList.contains('sidebar-collapsed'));
                });
            }

            // --- LÓGICA DE MENÚ MÓVIL ---
            function abrirMenu() {
                sidebar.classList.add('mobile-open');
                overlay.classList.add('visible');
                body.classList.add('menu-open');
            }

            function cerrarMenu() {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('visible');
                body.classList.remove('menu-open');
            }

            mobileMenu.addEventListener('click', abrirMenu);
            overlay.addEventListener('click', cerrarMenu);

            document.querySelectorAll('.nav-item').forEach(item => {
                item.addEventListener('click', () => {
                    if (window.innerWidth <= 900) {
                        cerrarMenu();
                    }
                });
            });

            window.addEventListener('resize', () => {
                if (window.innerWidth > 900) {
                    cerrarMenu();
                }
            });

            // --- LÓGICA DE HORA ---
            function actualizarHora() {
                if (!fechaTexto) return;

                const now = new Date();

                fechaTexto.textContent = now.toLocaleString('es-MX', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }

            actualizarHora();
            setInterval(actualizarHora, 60000);

        });
    </script>

</body>
</html>