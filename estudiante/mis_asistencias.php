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
    // 1. Obtener datos básicos del alumno para el Header
    $stmt = $pdo->prepare("SELECT nombre, apellido_paterno, matricula FROM ALUMNOS WHERE id_alumno = ?");
    $stmt->execute([$id_alumno]);
    $alumno = $stmt->fetch(PDO::FETCH_ASSOC);

    $nombre_pila = htmlspecialchars($alumno['nombre']);
    $apellido_p = htmlspecialchars($alumno['apellido_paterno']);
    $nombre_completo = $nombre_pila . ' ' . $apellido_p;
    $iniciales = mb_substr($nombre_pila, 0, 1) . mb_substr($apellido_p, 0, 1);
    $avatar_url = "https://ui-avatars.com/api/?name=" . urlencode($nombre_pila.'+'.$apellido_p) . "&background=e2e8f0&color=0f172a&size=120";

    // 2. Obtener resumen de asistencias del MES ACTUAL
    $stmtResumen = $pdo->prepare("
        SELECT estatus, COUNT(*) as total 
        FROM ASISTENCIAS 
        WHERE id_alumno = ? 
        AND MONTH(fecha_hora_escaneo) = MONTH(CURRENT_DATE()) 
        AND YEAR(fecha_hora_escaneo) = YEAR(CURRENT_DATE()) 
        GROUP BY estatus
    ");
    $stmtResumen->execute([$id_alumno]);
    $resumen_data = $stmtResumen->fetchAll(PDO::FETCH_KEY_PAIR);

    $puntuales = isset($resumen_data['Puntual']) ? $resumen_data['Puntual'] : 0;
    $retardos = isset($resumen_data['Retardo']) ? $resumen_data['Retardo'] : 0;
    $faltas = isset($resumen_data['Falta']) ? $resumen_data['Falta'] : 0;

    // 3. Obtener el historial detallado de asistencias (Últimos 50 registros)
    $stmtHistorial = $pdo->prepare("
        SELECT fecha_hora_escaneo, tipo_registro, estatus 
        FROM ASISTENCIAS 
        WHERE id_alumno = ? 
        ORDER BY fecha_hora_escaneo DESC 
        LIMIT 50
    ");
    $stmtHistorial->execute([$id_alumno]);
    $historial = $stmtHistorial->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error al cargar las asistencias: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#063f2b">
    <title>Mis Asistencias · COBAEP Plantel 27</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <link rel="stylesheet" href="style_mis_asistencias.css?v=<?php echo time(); ?>">
</head>
<body>

    <!-- ===== BARRA LATERAL ===== -->
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
                <a href="dashboard_estud.php" class="nav-item">
                    <i class="fas fa-home"></i> <span>Inicio</span>
                </a>
                <a href="perfil.php" class="nav-item">
                    <i class="far fa-user"></i> <span>Mi Perfil</span>
                </a>
                <a href="mis_asistencias.php" class="nav-item active">
                    <i class="far fa-calendar-check"></i> <span>Mis Asistencias</span>
                </a>
                <a href="mi_horario.php" class="nav-item">
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

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ===== CONTENIDO PRINCIPAL ===== -->
    <main class="main-content">

        <!-- Barra Superior (Header) -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu" id="mobileMenu" type="button">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="header-title-wrapper">
                    <div class="header-icon-circle">
                        <i class="fas fa-fingerprint"></i>
                    </div>
                    <div class="header-text">
                        <h1 class="page-title">Historial de Asistencia</h1>
                        <p class="page-desc">Consulta tus registros de entrada y salida del plantel</p>
                    </div>
                </div>
            </div>

            <div class="topbar-right">
                <div class="date-pill">
                    <i class="far fa-calendar"></i>
                    <span id="fechaTexto"><?php echo date('d/m/Y, h:i a'); ?></span>
                </div>
                <a href="perfil.php" class="profile-pill">
                    <img src="<?php echo $avatar_url; ?>" alt="Avatar" class="avatar-small">
                    <div class="profile-info">
                        <strong><?php echo $nombre_completo; ?></strong>
                        <span><?php echo htmlspecialchars($alumno['matricula']); ?></span>
                    </div>
                </a>
            </div>
        </header>

        <!-- Tarjetas de Resumen Mensual -->
        <div class="summary-cards">
            <div class="summary-card">
                <div class="card-icon bg-green-light"><i class="fas fa-check-circle"></i></div>
                <div class="card-info">
                    <h3>Puntual</h3>
                    <div class="value text-green"><?php echo $puntuales; ?></div>
                    <span>En el mes actual</span>
                </div>
            </div>
            
            <div class="summary-card">
                <div class="card-icon bg-orange-light"><i class="fas fa-clock"></i></div>
                <div class="card-info">
                    <h3>Retardos</h3>
                    <div class="value text-orange"><?php echo $retardos; ?></div>
                    <span>En el mes actual</span>
                </div>
            </div>

            <div class="summary-card">
                <div class="card-icon bg-red-light"><i class="fas fa-times-circle"></i></div>
                <div class="card-info">
                    <h3>Inasistencias</h3>
                    <div class="value text-red"><?php echo $faltas; ?></div>
                    <span>En el mes actual</span>
                </div>
            </div>
        </div>

        <!-- Tarjeta de la Tabla de Historial -->
        <div class="table-card">
            
            <div class="table-toolbar">
                <div class="toolbar-title">
                    <i class="far fa-list-alt"></i> Registros Recientes
                </div>
                <div class="filter-wrapper">
                    <i class="far fa-calendar-alt"></i>
                    <select>
                        <option>Este mes</option>
                        <option>Mes anterior</option>
                        <option>Todo el semestre</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>FECHA</th>
                            <th>HORA</th>
                            <th>TIPO DE REGISTRO</th>
                            <th>ESTATUS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($historial) > 0): ?>
                            <?php foreach($historial as $row): 
                                $fecha = date('d/m/Y', strtotime($row['fecha_hora_escaneo']));
                                $hora = date('h:i a', strtotime($row['fecha_hora_escaneo']));
                                $tipo = htmlspecialchars($row['tipo_registro']);
                                $estatus = htmlspecialchars($row['estatus']);
                                
                                // Determinar color del estatus
                                $badgeClass = 'badge-green';
                                $iconStatus = 'fa-check-circle';
                                if($estatus == 'Retardo') {
                                    $badgeClass = 'badge-orange';
                                    $iconStatus = 'fa-exclamation-circle';
                                } elseif($estatus == 'Falta') {
                                    $badgeClass = 'badge-red';
                                    $iconStatus = 'fa-times-circle';
                                }

                                // Icono del tipo de registro
                                $tipoIcon = $tipo == 'Entrada' ? 'fa-sign-in-alt text-green' : 'fa-sign-out-alt text-blue';
                            ?>
                                <tr>
                                    <td>
                                        <div class="date-cell">
                                            <i class="far fa-calendar"></i>
                                            <strong><?php echo $fecha; ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="time-cell">
                                            <i class="far fa-clock"></i>
                                            <span><?php echo $hora; ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="type-cell">
                                            <i class="fas <?php echo $tipoIcon; ?>"></i>
                                            <span><?php echo $tipo; ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $badgeClass; ?>">
                                            <i class="fas <?php echo $iconStatus; ?>"></i> <?php echo strtoupper($estatus); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4">
                                    <div class="empty-state">
                                        <div class="empty-icon-box"><i class="fas fa-qrcode"></i></div>
                                        <h3>Aún no hay registros</h3>
                                        <p>No se encontraron registros de asistencia para mostrar en este periodo.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
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

            if (localStorage.getItem('sidebarCollapsed') === 'true') {
                body.classList.add('sidebar-collapsed');
            }

            if (collapseToggle) {
                collapseToggle.addEventListener('click', () => {
                    body.classList.toggle('sidebar-collapsed');
                    localStorage.setItem('sidebarCollapsed', body.classList.contains('sidebar-collapsed'));
                });
            }

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

            window.addEventListener('resize', () => {
                if (window.innerWidth > 900) cerrarMenu();
            });

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