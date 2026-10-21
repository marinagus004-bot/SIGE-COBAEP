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

try {
    // 1. Obtener datos del alumno
    $stmt = $pdo->prepare("SELECT * FROM ALUMNOS WHERE id_alumno = ?");
    $stmt->execute([$id_alumno]);
    $alumno = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$alumno) {
        die("Error: Datos del alumno no encontrados.");
    }

    $anio_ingreso = !empty($alumno['periodo_ingreso']) ? substr($alumno['periodo_ingreso'], 0, 4) : date('Y');
    $anio_fin = $anio_ingreso + 3;
    $generacion = $anio_ingreso . ' - ' . $anio_fin;
    
    $nombre_pila = htmlspecialchars($alumno['nombre']);
    $apellido_p = htmlspecialchars($alumno['apellido_paterno']);
    $nombre_completo = $nombre_pila . ' ' . $apellido_p;
    $iniciales = mb_substr($nombre_pila, 0, 1) . mb_substr($apellido_p, 0, 1);

    $puntos_conducta = isset($alumno['puntos_conducta']) ? (int)$alumno['puntos_conducta'] : 100;

    // 2. Obtener asistencias del MES ACTUAL
    $stmtAsist = $pdo->prepare("
        SELECT estatus, COUNT(*) as total 
        FROM asistencias 
        WHERE id_alumno = ? 
        AND tipo_registro = 'Entrada' 
        AND MONTH(fecha_hora_escaneo) = MONTH(CURRENT_DATE()) 
        AND YEAR(fecha_hora_escaneo) = YEAR(CURRENT_DATE()) 
        GROUP BY estatus
    ");
    $stmtAsist->execute([$id_alumno]);
    $asist_data = $stmtAsist->fetchAll(PDO::FETCH_KEY_PAIR);

    $puntuales = isset($asist_data['Puntual']) ? $asist_data['Puntual'] : 0;
    $retardos = (isset($asist_data['Retardo']) ? $asist_data['Retardo'] : 0) + 
                (isset($asist_data['Retardo Justificado']) ? $asist_data['Retardo Justificado'] : 0);
    $faltas = (isset($asist_data['Falta']) ? $asist_data['Falta'] : 0) + 
              (isset($asist_data['Falta Justificada']) ? $asist_data['Falta Justificada'] : 0);

    // 3. Actividades / Clases
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

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <!-- Archivo base -->
    <link rel="stylesheet" href="style_dashboard_estud.css?v=<?php echo time(); ?>">
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
                <a href="dashboard_estud.php" class="nav-item active"><i class="fas fa-home"></i> <span>Inicio</span></a>
                <!-- Enlace de 'Mi Perfil' removido de aquí como sugeriste -->
                <a href="mis_asistencias.php" class="nav-item"><i class="far fa-calendar-check"></i> <span>Mis Asistencias</span></a>
                <a href="horario_clases.php" class="nav-item"><i class="far fa-calendar-alt"></i> <span>Horario de Clases</span></a>

                <span class="nav-heading">Académico</span>
                <a href="actividades_escolares.php" class="nav-item"><i class="fas fa-book-open"></i> <span>Actividades Escolares</span></a>
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
                    <div>
                        <h1><?php echo $nombre_completo; ?></h1>
                        <p>Alumno • Generación <?php echo $generacion; ?></p>
                    </div>
                </div>

                <div class="topbar-right">
                    <div class="date-pill">
                        <i class="far fa-calendar"></i>
                        <span id="fechaTexto"><?php echo date('d/m/Y, h:i a'); ?></span>
                    </div>
                    
                    <!-- NUEVO CONTENEDOR DE PERFIL CON DROPDOWN -->
                    <div class="profile-container">
                        <div class="profile-pill" id="profileBtn">
                            <div class="avatar"><?php echo htmlspecialchars($iniciales); ?></div>
                            <div class="profile-info">
                                <strong><?php echo $nombre_pila; ?></strong>
                                <span>Matrícula: <?php echo htmlspecialchars($alumno['matricula']); ?></span>
                            </div>
                            <i class="fas fa-chevron-down" style="color: #94a3b8; margin-left: 5px; font-size: 0.8rem;"></i>
                        </div>
                        
                        <!-- El Menú Desplegable -->
                        <div class="profile-dropdown" id="profileDropdown">
                            <a href="perfil.php" class="profile-dropdown-item">
                                <i class="far fa-user-circle"></i> Mi Perfil
                            </a>
                            <a href="../logout.php" class="profile-dropdown-item">
                                <i class="fas fa-arrow-right-from-bracket"></i> Cerrar Sesión
                            </a>
                        </div>
                    </div>

                </div>
            </header>

            <div class="info-banner">
                <i class="fas fa-graduation-cap"></i>
                <span>Mantén tu información actualizada y consulta tus actividades académicas.</span>
            </div>

            <!-- Stats Limpios -->
            <div class="stats-grid">
                <div class="stat-card" style="cursor: pointer;" onclick="window.location.href='mis_asistencias.php'">
                    <div class="stat-card-inner">
                        <div class="stat-icon" style="background:#ecfdf5; color:#10b981;"><i class="far fa-calendar-check"></i></div>
                        <div class="stat-text">
                            <div class="label">Asistencias</div>
                            <div class="value"><?php echo $puntuales; ?></div>
                            <div class="sub">Presentes (Mes)</div>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card" style="cursor: pointer;" onclick="window.location.href='mis_asistencias.php'">
                    <div class="stat-card-inner">
                        <div class="stat-icon" style="background:#fff7ed; color:#f59e0b;"><i class="far fa-clock"></i></div>
                        <div class="stat-text">
                            <div class="label">Retardos</div>
                            <div class="value"><?php echo $retardos; ?></div>
                            <div class="sub">En el mes</div>
                        </div>
                    </div>
                </div>

                <div class="stat-card" style="cursor: pointer;" onclick="window.location.href='mis_asistencias.php'">
                    <div class="stat-card-inner">
                        <div class="stat-icon" style="background:#fef2f2; color:#ef4444;"><i class="fas fa-percent"></i></div>
                        <div class="stat-text">
                            <div class="label">Inasistencias</div>
                            <div class="value"><?php echo $faltas; ?></div>
                            <div class="sub">En el mes</div>
                        </div>
                    </div>
                </div>

                <div class="stat-card" style="cursor: pointer;" onclick="window.location.href='actividades.php'">
                    <div class="stat-card-inner">
                        <div class="stat-icon" style="background:#eff6ff; color:#3b82f6;"><i class="far fa-calendar-alt"></i></div>
                        <div class="stat-text">
                            <div class="label">Actividades</div>
                            <div class="value"><?php echo $actividades; ?></div>
                            <div class="sub">Disponibles</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Avisos -->
            <div class="avisos-card" style="cursor: pointer;" onclick="window.location.href='avisos.php'">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.8rem; margin-bottom: 0.8rem;">
                    <h2 style="margin:0; font-size:1rem; color:#0f172a;"><i class="fas fa-bullhorn" style="color:#059669; margin-right:8px;"></i> Avisos Recientes</h2>
                    <a href="avisos.php" style="color:#475569; text-decoration:none; font-size:0.75rem; font-weight:600; background:#f8fafc; padding:4px 10px; border-radius:6px; border:1px solid #e2e8f0;" onclick="event.stopPropagation();">Ver todos</a>
                </div>
                <div>
                    <h3 style="margin:0 0 4px 0; font-size:0.95rem; color:#1e293b;">Bienvenido a la nueva plataforma institucional del Plantel 27.</h3>
                    <p style="margin:0 0 8px 0; font-size:0.85rem; color:#64748b;">Por el momento, tu horario de clases de este semestre se encuentra en proceso de validación.</p>
                    <span style="font-size:0.7rem; color:#94a3b8; font-weight:600;"><i class="fas fa-circle" style="font-size:5px; vertical-align:middle; margin-right:4px; color:#cbd5e1;"></i> Hace 1 día</span>
                </div>
            </div>

            <div class="bottom-split">
                <!-- Horario -->
                <div class="clases-card" style="cursor: pointer;" onclick="window.location.href='horario_clases.php'">
                    <h2 style="margin:0 0 0.8rem 0; font-size:1rem; color:#0f172a;"><i class="far fa-calendar-alt" style="color:#3b82f6; margin-right:8px;"></i> Próximas Clases</h2>
                    <div style="background:#f8fafc; border:1px dashed #cbd5e1; border-radius:10px; padding:1.5rem 1rem; text-align:center;">
                        <i class="far fa-calendar" style="font-size:2rem; color:#94a3b8; margin-bottom:0.5rem;"></i>
                        <strong style="display:block; font-size:0.9rem; color:#475569; margin-bottom:2px;">No hay clases programadas para hoy.</strong>
                        <p style="margin:0; font-size:0.8rem; color:#64748b;">¡Disfruta tu día y sigue aprovechando al máximo!</p>
                    </div>
                </div>

                <!-- Conducta -->
                <div class="progreso-card" style="text-align: center;">
                    <h2 style="margin:0 0 0.5rem 0; font-size:1rem; color:#0f172a; text-align:left;"><i class="fas fa-shield-alt" style="color:#059669; margin-right:8px;"></i> Puntos de Conducta</h2>
                    <!-- Le pasamos el valor de PHP a la variable CSS --puntos -->
<div class="circular-chart" style="--puntos: <?php echo $puntos_conducta; ?>%;">
    <div class="inner-circle">
        <span style="font-size: 24px; font-weight: 900; color: #0f172a; line-height: 1;"><?php echo $puntos_conducta; ?></span>
        <span style="font-size: 10px; color: #64748b; font-weight: 700; text-transform: uppercase; margin-top:2px;">Puntos</span>
    </div>
</div>
                    <p style="margin:0.5rem 0 0 0; color: #475569; font-weight: 500; font-size:0.85rem;">
                        <?php 
                            if($puntos_conducta >= 90) echo "¡Excelente conducta! Sigue así.";
                            elseif($puntos_conducta >= 70) echo "Conducta regular. Cuida tu comportamiento.";
                            else echo "Atención: Tu puntaje es bajo.";
                        ?>
                    </p>
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

            // --- MENÚ LATERAL ---
            if (localStorage.getItem('sidebarCollapsed') === 'true') {
                body.classList.add('sidebar-collapsed');
            }

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

            // --- DROPDOWN DEL PERFIL ---
            const profileBtn = document.getElementById('profileBtn');
            const profileDropdown = document.getElementById('profileDropdown');

            if (profileBtn && profileDropdown) {
                profileBtn.addEventListener('click', (e) => {
                    e.stopPropagation(); // Evita que se cierre instantáneamente al hacer click
                    profileDropdown.classList.toggle('active');
                });

                // Cierra el menú de perfil si haces click en cualquier otro lado de la pantalla
                document.addEventListener('click', (e) => {
                    if (!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
                        profileDropdown.classList.remove('active');
                    }
                });
            }

            // --- RELOJ ---
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