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
    // 1. Obtener datos básicos del alumno
    $stmt = $pdo->prepare("SELECT nombre, apellido_paterno, matricula, periodo_ingreso FROM ALUMNOS WHERE id_alumno = ?");
    $stmt->execute([$id_alumno]);
    $alumno = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$alumno) die("Error: Datos del alumno no encontrados.");

    $nombre_pila = htmlspecialchars($alumno['nombre']);
    $apellido_p = htmlspecialchars($alumno['apellido_paterno']);
    $nombre_completo = $nombre_pila . ' ' . $apellido_p;
    $iniciales = mb_substr($nombre_pila, 0, 1) . mb_substr($apellido_p, 0, 1);
    
    $anio_ingreso = !empty($alumno['periodo_ingreso']) ? substr($alumno['periodo_ingreso'], 0, 4) : date('Y');
    $generacion = $anio_ingreso . ' - ' . ($anio_ingreso + 3);

    // 2. Lógica del Nuevo Filtro de Calendario Mensual (Formato YYYY-MM)
    $periodo_filtro = isset($_GET['periodo']) ? $_GET['periodo'] : date('Y-m');
    $partes_fecha = explode('-', $periodo_filtro);
    $anio_filtro = $partes_fecha[0];
    $mes_filtro = $partes_fecha[1];

    // 3. Consultar Historial del Alumno
    $stmtAsist = $pdo->prepare("
        SELECT fecha_hora_escaneo, fecha_hora_salida, estatus, motivo_justificacion 
        FROM asistencias 
        WHERE id_alumno = ? 
        AND tipo_registro = 'Entrada'
        AND MONTH(fecha_hora_escaneo) = ? 
        AND YEAR(fecha_hora_escaneo) = ? 
        ORDER BY fecha_hora_escaneo DESC
    ");
    $stmtAsist->execute([$id_alumno, $mes_filtro, $anio_filtro]);
    $historial = $stmtAsist->fetchAll(PDO::FETCH_ASSOC);

    // 4. Calcular Resumen de Estadísticas del Mes Seleccionado
    $tot_presentes = 0;
    $tot_retardos = 0;
    $tot_faltas = 0;

    foreach($historial as $row) {
        $estatus_calc = strtolower($row['estatus'] ?? '');
        if(stripos($estatus_calc, 'puntual') !== false || stripos($estatus_calc, 'retardo justificado') !== false) {
            $tot_presentes++;
        } elseif(stripos($estatus_calc, 'retardo') !== false) {
            $tot_retardos++;
        } else {
            $tot_faltas++;
        }
    }

} catch (PDOException $e) {
    die("Error al cargar historial: " . $e->getMessage());
}

// Array para mostrar el mes en texto
$meses_texto = [
    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
    '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
    '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#063f2b">
    <title>Mis Asistencias · Panel Estudiante</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <link rel="stylesheet" href="style_dashboard_estud.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="style_mis_asistencias.css?v=<?php echo time(); ?>">

    
</head>
<body>

    <!-- ================= BARRA LATERAL ================= -->
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
                <a href="mis_asistencias.php" class="nav-item active"><i class="far fa-calendar-check"></i> <span>Mis Asistencias</span></a>
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

    <!-- ================= CONTENIDO PRINCIPAL ================= -->
    <main class="main-content">
        <div class="dashboard-container">
            
            <header class="topbar">
                <div class="topbar-left">
                    <button class="mobile-menu" id="mobileMenu" type="button"><i class="fas fa-bars"></i></button>
                    <div>
                        <h1>Mis Asistencias</h1>
                        <p>Consulta tu registro de entradas y salidas del plantel.</p>
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
                                <span>Matrícula: <?php echo htmlspecialchars($alumno['matricula']); ?></span>
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

            <!-- TARJETAS DE RESUMEN -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-inner">
                        <div class="stat-icon" style="background:#ecfdf5; color:#10b981;"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-text">
                            <div class="label">Presentes</div>
                            <div class="value"><?php echo $tot_presentes; ?></div>
                            <div class="sub">Asistencias en el mes</div>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-card-inner">
                        <div class="stat-icon" style="background:#fff7ed; color:#f59e0b;"><i class="fas fa-clock"></i></div>
                        <div class="stat-text">
                            <div class="label">Retardos</div>
                            <div class="value"><?php echo $tot_retardos; ?></div>
                            <div class="sub">Llegadas tarde</div>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-card-inner">
                        <div class="stat-icon" style="background:#fef2f2; color:#ef4444;"><i class="fas fa-user-times"></i></div>
                        <div class="stat-text">
                            <div class="label">Faltas</div>
                            <div class="value"><?php echo $tot_faltas; ?></div>
                            <div class="sub">Inasistencias del mes</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- BARRA DE FILTRO -->
            <form method="GET" action="mis_asistencias.php" class="filter-card">
                <div class="filter-info">
                    <div class="filter-icon"><i class="far fa-calendar-alt"></i></div>
                    <div class="filter-inputs">
                        <label for="periodo">Mes a consultar</label>
                        <!-- Selector Nativo -->
                        <input type="month" id="periodo" name="periodo" class="input-month" value="<?php echo htmlspecialchars($periodo_filtro); ?>" required>
                    </div>
                </div>
                <button type="submit" class="btn-buscar">
                    <i class="fas fa-search"></i> Filtrar Historial
                </button>
            </form>

            <!-- TABLA DE RESULTADOS -->
            <div class="table-card">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Entrada</th>
                                <th>Salida</th>
                                <th>Estado</th>
                                <th>Observaciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($historial)): ?>
                                <tr>
                                    <td colspan="5" class="empty-table">
                                        <i class="far fa-calendar-times"></i>
                                        <strong>No hay registros</strong>
                                        <span>No se encontraron asistencias en <?php echo $meses_texto[$mes_filtro] . ' de ' . $anio_filtro; ?>.</span>
                                    </td>
                                </tr>
                            <?php else: ?>
                                
                                <tr class="row-grupo">
                                    <td colspan="5">
                                        <i class="far fa-calendar-alt"></i> ASISTENCIAS DE <?php echo mb_strtoupper($meses_texto[$mes_filtro] . ' ' . $anio_filtro, 'UTF-8'); ?>
                                    </td>
                                </tr>

                                <?php foreach($historial as $row): 
                                    $fecha = date('d/m/Y', strtotime($row['fecha_hora_escaneo']));
                                    $entrada = date('h:i A', strtotime($row['fecha_hora_escaneo']));
                                    $salida = !empty($row['fecha_hora_salida']) ? date('h:i A', strtotime($row['fecha_hora_salida'])) : '<span style="color:#cbd5e1;">-</span>';
                                    
                                    $estatus_min = strtolower($row['estatus'] ?? '');
                                    $estatus = htmlspecialchars($row['estatus'] ?? 'Falta');
                                    $clase_badge = 'falta';
                                    
                                    if(stripos($estatus_min, 'puntual') !== false || stripos($estatus_min, 'retardo justificado') !== false) {
                                        $clase_badge = 'puntual';
                                        if(stripos($estatus_min, 'justificado') !== false) $estatus = 'Puntual (Just.)';
                                    } elseif(stripos($estatus_min, 'retardo') !== false) {
                                        $clase_badge = 'retardo';
                                    }

                                    $observacion = '<span style="color:#cbd5e1;">-</span>';
                                    if(!empty($row['motivo_justificacion'])) {
                                        $observacion = '<div class="observacion"><i class="fas fa-info-circle"></i> ' . htmlspecialchars($row['motivo_justificacion']) . '</div>';
                                    }
                                ?>
                                <tr>
                                    <td><strong><?php echo $fecha; ?></strong></td>
                                    <td><strong><?php echo $entrada; ?></strong></td>
                                    <td><strong><?php echo $salida; ?></strong></td>
                                    <td><span class="badge <?php echo $clase_badge; ?>"><?php echo $estatus; ?></span></td>
                                    <td><?php echo $observacion; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
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

            // --- MENU LATERAL ---
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

            // --- DROPDOWN PERFIL ---
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