<?php
session_start();
require_once '../config/database.php';

// Validar que el usuario haya iniciado sesión y sea estudiante
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'estudiante') {
    header("Location: ../login.php");
    exit();
}

$id_alumno = $_SESSION['id_usuario'];

try {
    // 1. Obtener datos del alumno
    $stmt = $pdo->prepare("SELECT * FROM ALUMNOS WHERE id_alumno = ?");
    $stmt->execute([$id_alumno]);
    $alumno = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$alumno) {
        die("Error: Datos del alumno no encontrados.");
    }

    // Calcular generación y carrera
    $anio_ingreso = !empty($alumno['periodo_ingreso']) ? substr($alumno['periodo_ingreso'], 0, 4) : date('Y');
    $anio_fin = $anio_ingreso + 3;
    $generacion = $anio_ingreso . ' - ' . $anio_fin;
    $carrera = !empty($alumno['capacitacion']) ? $alumno['capacitacion'] : 'Bachillerato General';
    
    // Nombres formateados
    $nombre_pila = htmlspecialchars($alumno['nombre']);
    $apellido_p = htmlspecialchars($alumno['apellido_paterno']);
    $nombre_completo = $nombre_pila . ' ' . $apellido_p;
    
    // Iniciales para el Avatar
    $iniciales = mb_substr($nombre_pila, 0, 1) . mb_substr($apellido_p, 0, 1);

    // 2. Obtener asistencias del MES ACTUAL
    $stmtAsist = $pdo->prepare("
        SELECT estatus, COUNT(*) as total 
        FROM ASISTENCIAS 
        WHERE id_alumno = ? 
        AND MONTH(fecha_hora_escaneo) = MONTH(CURRENT_DATE()) 
        AND YEAR(fecha_hora_escaneo) = YEAR(CURRENT_DATE()) 
        GROUP BY estatus
    ");
    $stmtAsist->execute([$id_alumno]);
    $asist_data = $stmtAsist->fetchAll(PDO::FETCH_KEY_PAIR);

    $puntuales = isset($asist_data['Puntual']) ? $asist_data['Puntual'] : 0;
    $retardos = isset($asist_data['Retardo']) ? $asist_data['Retardo'] : 0;
    $faltas = isset($asist_data['Falta']) ? $asist_data['Falta'] : 0;

    // 3. Actividades / Clases (Proxy de conteo)
    $stmtClases = $pdo->prepare("SELECT COUNT(*) FROM CLASE_ALUMNO WHERE id_alumno = ?");
    $stmtClases->execute([$id_alumno]);
    $actividades = $stmtClases->fetchColumn();

} catch (PDOException $e) {
    die("Error al cargar datos del panel: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#063f2b">
    <title>Inicio · Panel Estudiante</title>

    <!-- Fuentes e Iconos -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <!-- CSS Separado -->
    <link rel="stylesheet" href="style_dashboard_estud.css?v=<?php echo time(); ?>">
</head>
<body>

    <!-- ===== BARRA LATERAL (Idéntica a la del Administrador) ===== -->
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
                <a href="dashboard_estud.php" class="nav-item active">
                    <i class="fas fa-home"></i> <span>Inicio</span>
                </a>
                <a href="perfil.php" class="nav-item">
                    <i class="far fa-user"></i> <span>Mi Perfil</span>
                </a>
                <a href="mis_asistencias.php" class="nav-item">
                    <i class="far fa-calendar-check"></i> <span>Mis Asistencias</span>
                </a>
                <a href="horario_clases.php" class="nav-item">
                    <i class="far fa-calendar-alt"></i> <span>Horario de Clases</span>
                </a>
                <a href="actividades.php" class="nav-item">
                    <i class="fas fa-star"></i> <span>Actividades Escolares</span>
                </a>

                <span class="nav-heading">Información</span>
                <a href="avisos.php" class="nav-item">
                    <i class="far fa-bell"></i> <span>Avisos</span>
                </a>
                <a href="documentos.php" class="nav-item">
                    <i class="far fa-file-alt"></i> <span>Documentos</span>
                </a>
                <a href="reglamentos.php" class="nav-item">
                    <i class="far fa-clipboard"></i> <span>Reglamentos</span>
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

    <!-- ===== CONTENIDO PRINCIPAL ===== -->
    <main class="main-content">

        <!-- Barra Superior (Header) -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu" id="mobileMenu" type="button">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="welcome-text">
                    <span class="eyebrow"> ¡Hola 👋</span>
                    <h1 class="page-title"><?php echo $nombre_completo; ?></h1>
                    <p class="page-desc">Alumno • Generación <?php echo $generacion; ?></p>
                </div>
            </div>

            <div class="topbar-right">
                <div class="date-pill">
                    <i class="far fa-calendar"></i>
                    <span id="fechaTexto"><?php echo date('d/m/Y, h:i a'); ?></span>
                </div>
                <div class="profile-pill">
                    <div class="avatar"><?php echo htmlspecialchars($iniciales); ?></div>
                    <div class="profile-info">
                        <strong><?php echo $nombre_completo; ?></strong>
                        <span>Matrícula: <?php echo htmlspecialchars($alumno['matricula']); ?></span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Cuadrícula Principal del Panel -->
        <div class="dashboard-grid">
            
            <!-- COLUMNA IZQUIERDA (Contenido Principal) -->
            <div class="main-column">
                
                <!-- Banner verde superior -->
                <div class="info-banner">
                    <div class="banner-icon"><i class="fas fa-graduation-cap"></i></div>
                    <span>Mantén tu información actualizada y consulta tus actividades académicas.</span>
                </div>

                <!-- Tarjetas de Estadísticas -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon bg-green"><i class="far fa-calendar-check"></i></div>
                        <div class="stat-info">
                            <h3>Asistencias (Mes)</h3>
                            <div class="value text-green"><?php echo $puntuales; ?></div>
                            <span>Presentes</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon bg-orange"><i class="far fa-clock"></i></div>
                        <div class="stat-info">
                            <h3>Retardos</h3>
                            <div class="value text-orange"><?php echo $retardos; ?></div>
                            <span>En el mes</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon bg-red"><i class="fas fa-percent"></i></div>
                        <div class="stat-info">
                            <h3>Inasistencias</h3>
                            <div class="value text-red"><?php echo $faltas; ?></div>
                            <span>Sin faltas</span>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon bg-blue"><i class="far fa-calendar-alt"></i></div>
                        <div class="stat-info">
                            <h3>Actividades</h3>
                            <div class="value text-blue"><?php echo $actividades; ?></div>
                            <span>Disponibles</span>
                        </div>
                    </div>
                </div>

                <!-- Avisos Recientes -->
                <div class="avisos-card">
                    <div class="avisos-header">
                        <h2><i class="fas fa-bullhorn text-green"></i> Avisos Recientes</h2>
                        <a href="avisos.php" class="btn-outline">Ver todos</a>
                    </div>
                    <div class="avisos-body">
                        <div class="aviso-content">
                            <h3>Bienvenido a la nueva plataforma institucional del Plantel 27.</h3>
                            <p>Por el momento, tu horario de clases de este semestre se encuentra en proceso de validación.</p>
                            <span class="time-badge"><i class="fas fa-circle"></i> Hace 1 día</span>
                        </div>
                        <div class="aviso-illustration">
                            <!-- Ilustración CSS -->
                            <div class="ill-envelope">
                                <i class="fas fa-envelope-open-text"></i>
                                <div class="ill-bell"><i class="fas fa-bell"></i><span class="badge">1</span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fila Inferior: Clases y Progreso -->
                <div class="bottom-split">
                    <div class="clases-card">
                        <div class="card-title"><i class="far fa-calendar-alt"></i> Próximas Clases</div>
                        <div class="clases-empty">
                            <div>
                                <strong>No hay clases programadas para hoy.</strong>
                                <p>¡Disfruta tu día y sigue aprovechando al máximo!</p>
                            </div>
                            <i class="far fa-calendar empty-icon"></i>
                        </div>
                    </div>

                    <div class="progreso-card">
                        <div class="card-title"><i class="fas fa-chart-line"></i> Tu Progreso Académico</div>
                        <div class="chart-container">
                            <div class="circular-chart">
                                <div class="inner-circle">
                                    <span class="percent">92%</span>
                                    <span class="label">Avance General</span>
                                </div>
                            </div>
                        </div>
                        <p class="progreso-msg">Sigue así, vas por un excelente camino.</p>
                    </div>
                </div>

            </div>

            <!-- COLUMNA DERECHA (Credencial y Acciones)
            <div class="side-column">
                
                <div class="card-title"><i class="far fa-id-card"></i> Credencial Virtual</div>
                 -->
                <!-- Credencial 
                <div class="credencial-box">
                    <div class="cred-header">
                        <img src="../img/logo-blanco.png" alt="COBAEP">
                        <div class="cred-plantel">
                            <strong>PLANTEL 27</strong>
                            <span>Zaragoza, Puebla</span>
                        </div>
                    </div>
                    <div class="cred-body">
                        <div class="cred-photo">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($nombre_pila.'+'.$apellido_p); ?>&background=e2e8f0&color=0f172a&size=120" alt="Foto Alumno">
                        </div>
                        <div class="cred-data">
                            <h2><?php echo $nombre_completo; ?></h2>
                            <div class="status-row">
                                <strong>Alumno</strong>
                                <span class="badge-status <?php echo strtolower($alumno['estatus']); ?>"><?php echo htmlspecialchars($alumno['estatus']); ?></span>
                            </div>
                            <div class="info-group">
                                <label>Matrícula</label>
                                <span><?php echo htmlspecialchars($alumno['matricula']); ?></span>
                            </div>
                            <div class="info-group">
                                <label>Generación</label>
                                <span><?php echo $generacion; ?></span>
                            </div>
                            <div class="info-group">
                                <label>Carrera</label>
                                <span><?php echo htmlspecialchars($carrera); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="cred-footer">
                        <div class="qr-code">
                            <i class="fas fa-qrcode"></i>
                        </div>
                        <div class="footer-data">
                            <div class="info-group">
                                <label>Vigencia</label>
                                <span>AGO <?php echo date('Y'); ?> - JUL <?php echo date('Y')+3; ?></span>
                            </div>
                            <div class="info-group">
                                <label>Folio</label>
                                <span>27-<?php echo htmlspecialchars($alumno['matricula']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <button class="btn-download">
                    <i class="fas fa-download"></i> Descargar Credencial
                </button>

                -->

                <!-- Acciones Rápidas 
                <div class="card-title" style="margin-top: 30px;"><i class="far fa-clock"></i> Acciones Rápidas</div>
                <div class="quick-actions-grid">
                    <a href="perfil.php" class="action-btn">
                        <div class="icon bg-green-light"><i class="fas fa-user"></i></div>
                        <span>Subir Foto</span>
                    </a>
                    <a href="mi_horario.php" class="action-btn">
                        <div class="icon bg-blue-light"><i class="fas fa-star"></i></div>
                        <span>Ver Horario</span>
                    </a>
                    <a href="mis_asistencias.php" class="action-btn">
                        <div class="icon bg-orange-light"><i class="fas fa-check-circle"></i></div>
                        <span>Mis Asistencias</span>
                    </a>
                    <a href="documentos.php" class="action-btn">
                        <div class="icon bg-purple-light"><i class="fas fa-file-alt"></i></div>
                        <span>Mis Documentos</span>
                    </a>
                </div>

                   

                <div class="action-banner">
                    <div class="icon"><i class="fas fa-user-circle"></i></div>
                    <div class="text">
                        <p>Sube tu foto para que aparezca en tu credencial.</p>
                        <a href="perfil.php">Ir a mi perfil <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>

                 -->

            </div>
        </div>

    </main>

    <!-- SCRIPTS LÓGICA DEL MENÚ LATERAL -->
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

            if (mobileMenu) mobileMenu.addEventListener('click', abrirMenu);
            if (overlay) overlay.addEventListener('click', cerrarMenu);

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