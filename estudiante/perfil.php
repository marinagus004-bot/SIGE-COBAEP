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
    // 1. Obtener datos principales del alumno
    $stmt = $pdo->prepare("SELECT * FROM ALUMNOS WHERE id_alumno = ?");
    $stmt->execute([$id_alumno]);
    $alumno = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$alumno) {
        die("Error: Datos del alumno no encontrados.");
    }

    // 2. Obtener grupo actual del alumno
    $stmtGpo = $pdo->prepare("
        SELECT g.nombre_grupo, g.semestre, g.turno 
        FROM GRUPOS g 
        JOIN GRUPO_ALUMNO ga ON g.id_grupo = ga.id_grupo 
        WHERE ga.id_alumno = ? AND ga.fecha_baja IS NULL
        LIMIT 1
    ");
    $stmtGpo->execute([$id_alumno]);
    $grupo = $stmtGpo->fetch(PDO::FETCH_ASSOC);

    // 3. Obtener domicilio
    $stmtDom = $pdo->prepare("SELECT * FROM DOMICILIO_ALUMNO WHERE id_alumno = ?");
    $stmtDom->execute([$id_alumno]);
    $domicilio = $stmtDom->fetch(PDO::FETCH_ASSOC);

    // Formatear variables para la vista
    $nombre_pila = htmlspecialchars($alumno['nombre']);
    $apellido_p = htmlspecialchars($alumno['apellido_paterno']);
    $nombre_completo = $nombre_pila . ' ' . $apellido_p;
    
    // Generación y Carrera
    $anio_ingreso = !empty($alumno['periodo_ingreso']) ? substr($alumno['periodo_ingreso'], 0, 4) : date('Y');
    $anio_fin = $anio_ingreso + 3;
    $generacion = $anio_ingreso . ' - ' . $anio_fin;
    $carrera = !empty($alumno['capacitacion']) ? htmlspecialchars($alumno['capacitacion']) : 'Bachillerato General';
    
    // Matrícula, Folio y Status
    $matricula = htmlspecialchars($alumno['matricula']);
    $folio = "27-" . $matricula;
    $estatus = htmlspecialchars($alumno['estatus']);
    $turno = $grupo ? htmlspecialchars($grupo['turno']) : htmlspecialchars($alumno['turno']);
    $grupo_str = $grupo ? htmlspecialchars($grupo['semestre'] . ' "' . $grupo['nombre_grupo'] . '"') : '-- Sin grupo asignado --';
    
    // Datos Personales
    $fecha_nac = !empty($alumno['fecha_nacimiento']) ? date('d/m/Y', strtotime($alumno['fecha_nacimiento'])) : '--/--/----';
    $correo = !empty($alumno['correo_institucional']) ? htmlspecialchars($alumno['correo_institucional']) : (!empty($alumno['correo_personal']) ? htmlspecialchars($alumno['correo_personal']) : '-- Sin registro --');
    $telefono = !empty($alumno['telefono']) ? htmlspecialchars($alumno['telefono']) : '-- Sin registro --';
    
    $domicilio_str = '-- Sin registro --';
    if ($domicilio) {
        $calle = htmlspecialchars($domicilio['calle'] ?? '');
        $num = htmlspecialchars($domicilio['num_exterior'] ?? '');
        $colonia = htmlspecialchars($domicilio['colonia'] ?? '');
        $municipio = htmlspecialchars($domicilio['municipio'] ?? '');
        $domicilio_str = trim("$calle $num, $colonia, $municipio", ", ");
        if (empty($domicilio_str)) $domicilio_str = '-- Sin registro --';
    }

    // Avatar
    $avatar_url = "https://ui-avatars.com/api/?name=" . urlencode($nombre_pila.'+'.$apellido_p) . "&background=e2e8f0&color=0f172a&size=120";

} catch (PDOException $e) {
    die("Error al cargar datos del perfil: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#063f2b">
    <title>Mi Perfil · COBAEP Plantel 27</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <!-- Librería para generar el QR -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    
    <link rel="stylesheet" href="style_perfil.css?v=<?php echo time(); ?>">
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
                <a href="perfil.php" class="nav-item active">
                    <i class="far fa-user"></i> <span>Mi Perfil</span>
                </a>
                <a href="mis_asistencias.php" class="nav-item">
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
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="header-text">
                        <h1 class="page-title">Mi Perfil</h1>
                        <p class="page-desc">Consulta y administra tu información personal</p>
                    </div>
                </div>
            </div>

            <div class="topbar-right">
                <div class="date-pill">
                    <i class="far fa-calendar"></i>
                    <span id="fechaTexto"><?php echo date('d/m/Y, h:i a'); ?></span>
                </div>
                <div class="profile-pill dropdown">
                    <img src="<?php echo $avatar_url; ?>" alt="Avatar" class="avatar-small">
                    <div class="profile-info">
                        <strong><?php echo $nombre_completo; ?></strong>
                        <span><?php echo $matricula; ?></span>
                    </div>
                    <i class="fas fa-chevron-down drop-icon"></i>
                </div>
            </div>
        </header>

        <!-- Cuadrícula Principal -->
        <div class="profile-grid">
            
            <!-- COLUMNA IZQUIERDA -->
            <div class="col-left">
                
                <!-- Tarjeta Principal de Perfil -->
                <div class="card profile-main-card">
                    <button class="btn-change-photo"><i class="fas fa-camera"></i> Cambiar foto</button>
                    
                    <div class="profile-main-info">
                        <div class="avatar-large-wrapper">
                            <img src="<?php echo $avatar_url; ?>" alt="Avatar">
                            <button class="edit-avatar-btn"><i class="fas fa-pen"></i></button>
                        </div>
                        
                        <div class="profile-name-badges">
                            <h2><?php echo $nombre_completo; ?></h2>
                            <div class="badges-row">
                                <span class="badge badge-alumno">Alumno</span>
                                <span class="badge badge-activo"><?php echo strtoupper($estatus); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="info-grid-3x2">
                        <div class="info-item">
                            <div class="icon-box"><i class="far fa-id-card"></i></div>
                            <div class="info-data">
                                <label>Matrícula</label>
                                <span><?php echo $matricula; ?></span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="icon-box"><i class="fas fa-graduation-cap"></i></div>
                            <div class="info-data">
                                <label>Generación</label>
                                <span><?php echo $generacion; ?></span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="icon-box"><i class="fas fa-book"></i></div>
                            <div class="info-data">
                                <label>Carrera</label>
                                <span><?php echo $carrera; ?></span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="icon-box"><i class="fas fa-building"></i></div>
                            <div class="info-data">
                                <label>Plantel</label>
                                <span>COBAEP Plantel 27</span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="icon-box"><i class="far fa-clock"></i></div>
                            <div class="info-data">
                                <label>Turno</label>
                                <span><?php echo $turno; ?></span>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="icon-box"><i class="fas fa-users"></i></div>
                            <div class="info-data">
                                <label>Grupo</label>
                                <span><?php echo $grupo_str; ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="security-banner">
                        <div class="shield-icon"><i class="fas fa-shield-alt"></i></div>
                        <div class="sec-text">
                            <strong>Tu información está segura y protegida</strong>
                            <span>Solo personal autorizado puede acceder a tus datos.</span>
                        </div>
                    </div>
                </div>

                <!-- Tarjeta de Información Personal -->
                <div class="card personal-info-card">
                    <div class="card-header">
                        <div class="header-title">
                            <i class="fas fa-user"></i> Información Personal
                        </div>
                        <button class="btn-outline-small"><i class="fas fa-pen"></i> Editar</button>
                    </div>

                    <div class="personal-grid">
                        <div class="info-item border-box">
                            <div class="icon-box"><i class="far fa-calendar-alt"></i></div>
                            <div class="info-data">
                                <label>Fecha de Nacimiento</label>
                                <span><?php echo $fecha_nac; ?></span>
                            </div>
                        </div>
                        <div class="info-item border-box">
                            <div class="icon-box"><i class="far fa-envelope"></i></div>
                            <div class="info-data">
                                <label>Correo Electrónico</label>
                                <span><?php echo $correo; ?></span>
                            </div>
                        </div>
                        <div class="info-item border-box">
                            <div class="icon-box"><i class="fas fa-phone-alt"></i></div>
                            <div class="info-data">
                                <label>Teléfono</label>
                                <span><?php echo $telefono; ?></span>
                            </div>
                        </div>
                        <div class="info-item border-box">
                            <div class="icon-box"><i class="fas fa-home"></i></div>
                            <div class="info-data">
                                <label>Domicilio</label>
                                <span><?php echo $domicilio_str; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- COLUMNA DERECHA -->
            <div class="col-right">
                
                <!-- Credencial Virtual -->
                <div class="card credencial-wrapper">
                    <div class="card-header borderless">
                        <div class="header-title">
                            <i class="far fa-id-card"></i> Credencial Virtual
                        </div>
                        <span class="badge-status-green"><i class="fas fa-check"></i> Vigente</span>
                    </div>

                    <div class="credencial-box">
                        <div class="cred-header">
                            <div class="logo-area">
                                <img src="../img/logo-blanco.png" alt="COBAEP">
                                <strong>COBAEP</strong>
                            </div>
                            <div class="cred-plantel">
                                <strong>PLANTEL 27</strong>
                                <span>Zaragoza, Puebla</span>
                            </div>
                        </div>
                        <div class="cred-body">
                            <div class="cred-photo-col">
                                <img src="<?php echo $avatar_url; ?>" alt="Foto Alumno">
                            </div>
                            <div class="cred-data-col">
                                <h2><?php echo $nombre_completo; ?></h2>
                                <div class="status-row">
                                    <strong>Alumno</strong>
                                    <span class="badge-activo-small">ACTIVO</span>
                                </div>
                                <div class="info-list">
                                    <p><label>Matrícula:</label> <?php echo $matricula; ?></p>
                                    <p><label>Generación:</label> <?php echo $generacion; ?></p>
                                    <p><label>Carrera:</label> <strong><?php echo $carrera; ?></strong></p>
                                </div>
                            </div>
                            <div class="cred-qr-col">
                                <!-- Contenedor donde se generará el QR. Le damos margen abajo para separarlo del texto "Folio" -->
                                <div id="credencial-qr-img" style="margin-bottom: 8px; display: flex; justify-content: center;"></div>
                                <div class="folio-text">
                                    <label>Folio</label>
                                    <span><?php echo $folio; ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="cred-footer">
                            Vigencia: AGO <?php echo date('Y'); ?> - JUL <?php echo date('Y')+3; ?>
                        </div>
                    </div>

                    <div class="cred-actions">
                        <button class="btn-action-primary"><i class="fas fa-download"></i> Descargar Credencial</button>
                        <button class="btn-action-secondary"><i class="fas fa-print"></i> Imprimir</button>
                    </div>
                </div>

                
                <!-- Acciones Rápidas -->
                <div class="card quick-actions-card">
                    <div class="card-header borderless">
                        <div class="header-title">
                            <i class="fas fa-bolt"></i> Acciones Rápidas
                        </div>
                    </div>

                    <div class="actions-grid">
                        <a href="perfil.php" class="action-btn">
                            <div class="icon bg-green-light"><i class="fas fa-user-circle"></i></div>
                            <strong>Subir Foto de Perfil</strong>
                            <span>Actualiza tu foto</span>
                        </a>
                        <a href="mi_horario.php" class="action-btn">
                            <div class="icon bg-blue-light"><i class="far fa-calendar-alt"></i></div>
                            <strong>Ver Horario</strong>
                            <span>Consulta tu horario</span>
                        </a>
                        <a href="mis_asistencias.php" class="action-btn">
                            <div class="icon bg-orange-light"><i class="far fa-check-circle"></i></div>
                            <strong>Mis Asistencias</strong>
                            <span>Revisa tus asistencias</span>
                        </a>
                        <a href="documentos.php" class="action-btn">
                            <div class="icon bg-purple-light"><i class="far fa-file-alt"></i></div>
                            <strong>Mis Documentos</strong>
                            <span>Descarga documentos</span>
                        </a>
                    </div>
                    

                    <div class="update-banner">
                        <div class="icon"><i class="fas fa-user"></i></div>
                        <div class="text">
                            <strong>Mantén tu información actualizada</strong>
                            <p>Esto te ayudará a acceder sin problemas a todos los servicios.</p>
                        </div>
                        <i class="fas fa-chevron-right arrow"></i>
                    </div>
                </div>

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

            window.addEventListener('resize', () => {
                if (window.innerWidth > 900) cerrarMenu();
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

    <!-- SCRIPT PARA GENERAR EL CÓDIGO QR -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Traemos el código QR directamente de la base de datos a través de PHP
            const qrData = "<?php echo htmlspecialchars($alumno['codigo_qr'] ?? ''); ?>";
            
            if (qrData && qrData !== '') {
                // Seleccionamos el contenedor
                const contenedor = document.getElementById("credencial-qr-img");
                
                // Generamos el QR
                new QRCode(contenedor, {
                    text: qrData,
                    width: 90,  // Tamaño ajustado para encajar bien en la columna
                    height: 90,
                    colorDark : "#000000", // Color negro
                    colorLight : "#ffffff", // Fondo blanco
                    correctLevel : QRCode.CorrectLevel.H // Alta corrección de errores
                });
            }
        });
    </script>
</body>
</html>