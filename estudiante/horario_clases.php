<?php
session_start();
require_once '../config/database.php';

// 1. Validación para el ESTUDIANTE
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'estudiante') {
    header("Location: ../login.php");
    exit();
}

$id_alumno = $_SESSION['id_usuario'];

// Valores por defecto para evitar errores visuales (Warnings) en caso de fallo
$nombre_pila = 'Alumno';
$apellido_p = '';
$nombre_completo = 'Alumno';
$iniciales = 'AL';
$generacion = '';
$alumno = ['matricula' => 'N/A'];
$mi_horario = null;

try {
    // 2. Obtener datos básicos del alumno
    $stmt = $pdo->prepare("SELECT nombre, apellido_paterno, matricula, periodo_ingreso FROM ALUMNOS WHERE id_alumno = ?");
    $stmt->execute([$id_alumno]);
    $datos_alumno = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($datos_alumno) {
        $alumno = $datos_alumno;
        $nombre_pila = htmlspecialchars($alumno['nombre']);
        $apellido_p = htmlspecialchars($alumno['apellido_paterno']);
        $nombre_completo = $nombre_pila . ' ' . $apellido_p;
        $iniciales = mb_substr($nombre_pila, 0, 1) . mb_substr($apellido_p, 0, 1);
        
        $anio_ingreso = !empty($alumno['periodo_ingreso']) ? substr($alumno['periodo_ingreso'], 0, 4) : date('Y');
        $generacion = $anio_ingreso . ' - ' . ($anio_ingreso + 3);
    }

    // 3. Obtener el archivo de horario de forma segura
    // Separado para no romper la página si la columna 'horario_archivo' aún no existe
    try {
        $stmtHorario = $pdo->prepare("SELECT horario_archivo FROM ALUMNOS WHERE id_alumno = ?");
        $stmtHorario->execute([$id_alumno]);
        $horario_data = $stmtHorario->fetch(PDO::FETCH_ASSOC);
        if ($horario_data) {
            $mi_horario = $horario_data['horario_archivo'];
        }
    } catch (PDOException $e) {
        // Ignorar el error silenciosamente si la columna no existe en MySQL
        $mi_horario = null;
    }

} catch (PDOException $e) {
    error_log("Error cargando horario: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#063f2b">
    <title>Mi Horario · Panel Estudiante</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <!-- CSS Base -->
    <link rel="stylesheet" href="style_dashboard_estud.css?v=<?php echo time(); ?>">

    <style>
        /* ==============================================================
           CORE LAYOUT ESTUDIANTE (Idéntico al Dashboard)
        ============================================================== */
        :root {
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 80px;
            --bg-main: #f8fafc;
        }
        body { background-color: var(--bg-main); margin: 0; padding: 0; overflow: hidden; box-sizing: border-box; }
        *, *::before, *::after { box-sizing: border-box; }

        .main-content {
            margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width));
            padding: 1.2rem 2rem; height: 100vh; overflow-y: auto; transition: margin-left 0.3s ease, width 0.3s ease;
        }
        body.sidebar-collapsed .main-content { margin-left: var(--sidebar-collapsed-width); width: calc(100% - var(--sidebar-collapsed-width)); }
        .dashboard-container { max-width: 1200px; margin: 0 auto; display: flex; flex-direction: column; gap: 1rem; }

        /* HEADER Y PERFIL */
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
        .topbar-left { display: flex; align-items: center; gap: 15px; }
        .topbar-left h1 { font-size: 1.4rem; font-weight: 800; color: #0f172a; margin: 0 0 0.1rem 0; letter-spacing: -0.5px; }
        .topbar-left p { margin: 0; color: #64748b; font-size: 0.85rem; }
        
        .topbar-right { display: flex; align-items: center; gap: 10px; }
        .date-pill { background: white; padding: 0.5rem 1rem; border-radius: 99px; font-size: 0.8rem; font-weight: 600; color: #475569; border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 8px; }
        .date-pill i { color: #059669; }

        .profile-container { position: relative; }
        .profile-pill { background: white; padding: 0.3rem 0.8rem 0.3rem 0.3rem; border-radius: 99px; display: flex; align-items: center; gap: 10px; border: 1px solid #e2e8f0; cursor: pointer; transition: background 0.2s; }
        .profile-pill:hover { background: #f8fafc; }
        .profile-pill .avatar { width: 34px; height: 34px; background: #063f2b; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; }
        .profile-info strong { display: block; font-size: 0.85rem; color: #0f172a; }
        .profile-info span { display: block; font-size: 0.7rem; color: #64748b; }
        
        .profile-dropdown { position: absolute; top: calc(100% + 10px); right: 0; background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); width: 220px; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.2s; z-index: 100; }
        .profile-dropdown.active { opacity: 1; visibility: visible; transform: translateY(0); }
        .profile-dropdown-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #475569; text-decoration: none; font-size: 0.9rem; font-weight: 600; transition: all 0.2s; }
        .profile-dropdown-item i { width: 16px; text-align: center; }
        .profile-dropdown-item:first-child { border-radius: 12px 12px 0 0; }
        .profile-dropdown-item:last-child { border-radius: 0 0 12px 12px; border-top: 1px solid #f1f5f9; color: #ef4444; }
        .profile-dropdown-item:hover { background: #f8fafc; color: #0f172a; padding-left: 20px; }
        .mobile-menu { display: none; background: none; border: none; padding: 0; font-size: 1.6rem; color: #0f172a; cursor: pointer; }

        /* ==============================================================
           DISEÑO DEL HORARIO (Subida y Visor)
        ============================================================== */
        .layout-horario {
            display: grid;
            grid-template-columns: 1fr 2fr; 
            gap: 1.5rem;
            align-items: start;
        }

        .card-modern {
            background: white; border-radius: 12px; border: 1px solid #e2e8f0;
            padding: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
        }

        .card-modern h3 { margin: 0 0 0.5rem 0; font-size: 1.1rem; color: #0f172a; display: flex; align-items: center; gap: 8px; }
        .card-modern .text-muted { color: #64748b; font-size: 0.85rem; margin-bottom: 1.5rem; line-height: 1.5; }
        .icon-green { color: #059669; }

        /* Alertas */
        .alert { padding: 12px 15px; border-radius: 8px; margin-bottom: 15px; font-size: 0.85rem; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .alert-success { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        /* Zona de Subida */
        .upload-zone-modern {
            border: 2px dashed #cbd5e1; border-radius: 12px; padding: 2rem 1rem;
            text-align: center; cursor: pointer; display: flex; flex-direction: column;
            align-items: center; justify-content: center; gap: 10px; background: #f8fafc;
            transition: all 0.2s ease; margin-bottom: 1rem;
        }
        .upload-zone-modern:hover { border-color: #059669; background: #ecfdf5; }
        .upload-icon-circle { width: 50px; height: 50px; background: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #059669; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .upload-title { font-weight: 700; color: #0f172a; font-size: 0.95rem; }
        .upload-desc { font-size: 0.75rem; color: #64748b; }
        .hidden-input { display: none; }
        
        .file-name-badge { background: #e0f2fe; color: #0369a1; padding: 6px 12px; border-radius: 99px; font-size: 0.8rem; font-weight: 600; margin-top: 10px; align-items: center; gap: 5px; }

        .btn-guardar-full { width: 100%; background: #059669; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: background 0.2s; display: flex; justify-content: center; align-items: center; gap: 8px; }
        .btn-guardar-full:hover { background: #047857; }

        /* Visor */
        .viewer-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #e2e8f0; }
        .btn-outline-small { border: 1px solid #cbd5e1; background: white; color: #475569; padding: 6px 12px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 6px; transition: all 0.2s; }
        .btn-outline-small:hover { background: #f8fafc; border-color: #94a3b8; color: #0f172a; }

        .viewer-body { background: #f8fafc; border-radius: 8px; min-height: 500px; display: flex; align-items: center; justify-content: center; overflow: hidden; border: 1px solid #e2e8f0; }
        .horario-iframe { width: 100%; height: 600px; border: none; }
        .horario-img { max-width: 100%; height: auto; display: block; }
        
        .empty-state-horario { text-align: center; color: #94a3b8; display: flex; flex-direction: column; align-items: center; gap: 10px; }
        .empty-state-horario i { font-size: 3rem; }
        .empty-state-horario span { font-size: 0.95rem; font-weight: 500; }

        /* ==============================================================
           RESPONSIVE MOBILE
        ============================================================== */
        @media (max-width: 900px) {
            .layout-horario { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            body { overflow: auto; }
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 1rem; height: auto; overflow-y: visible; }
            .topbar { flex-direction: column; align-items: stretch; gap: 0.8rem; }
            .topbar-left { align-items: flex-start; }
            .topbar-left h1 { font-size: 1.25rem; }
            .mobile-menu { display: block; margin-top: 3px; }
            .topbar-right { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; width: 100%; }
            .date-pill, .profile-pill { width: 100%; justify-content: center; padding: 0.5rem; }
            .viewer-body { min-height: 300px; }
            .horario-iframe { height: 400px; }
        }
        @media (max-width: 400px) { .topbar-right { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

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
                <a href="mis_asistencias.php" class="nav-item"><i class="far fa-calendar-check"></i> <span>Mis Asistencias</span></a>
                
                <a href="horario_clases.php" class="nav-item active"><i class="far fa-calendar-alt"></i> <span>Horario de Clases</span></a>
                
                <a href="actividades.php" class="nav-item"><i class="fas fa-star"></i> <span>Actividades Escolares</span></a>

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
                        <h1>Horario de Clases</h1>
                        <p>Consulta y gestiona tu carga académica.</p>
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

            <div class="layout-horario">
                
                <div class="col-upload">
                    <div class="card-modern">
                        <h3><i class="fas fa-cloud-upload-alt icon-green"></i> Subir Horario</h3>
                        <p class="text-muted">Si tienes tu horario en PDF o Imagen, súbelo aquí para consultarlo fácilmente en la plataforma.</p>
                        
                        <?php if (isset($_GET['exito'])): ?>
                            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['exito']); ?></div>
                        <?php endif; ?>
                        <?php if (isset($_GET['error'])): ?>
                            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?></div>
                        <?php endif; ?>

                        <!-- Script de guardado -->
                        <form action="../procesos/subir_horario_estud.php" method="POST" enctype="multipart/form-data">
                            <label for="archivo_horario" class="upload-zone-modern">
                                <div class="upload-icon-circle"><i class="fas fa-cloud-upload-alt"></i></div>
                                <span class="upload-title">Haz clic para seleccionar</span>
                                <span class="upload-desc">Admite: PDF, JPG, PNG</span>
                                <input type="file" name="archivo_horario" id="archivo_horario" accept=".pdf, .jpg, .jpeg, .png, .webp" required class="hidden-input">
                                
                                <div id="file-name-display" class="file-name-badge" style="display: none;">
                                    <i class="fas fa-paperclip"></i> <span id="file-name-text"></span>
                                </div>
                            </label>

                            <button type="submit" class="btn-guardar-full">
                                <i class="fas fa-save"></i> Guardar Horario
                            </button>
                        </form>
                    </div>
                </div>

                <div class="col-viewer">
                    <div class="card-modern viewer-card">
                        <div class="viewer-header">
                            <h3><i class="far fa-calendar-check icon-green"></i> Tu Horario Actual</h3>
                            <?php if (!empty($mi_horario)): ?>
                                <a href="../<?php echo htmlspecialchars($mi_horario); ?>" download class="btn-outline-small">
                                    <i class="fas fa-download"></i> Descargar
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="viewer-body">
                            <?php if (empty($mi_horario)): ?>
                                <div class="empty-state-horario">
                                    <i class="far fa-calendar-times"></i>
                                    <span>No has subido tu horario aún.</span>
                                </div>
                            <?php else: ?>
                                <?php 
                                    $ext = strtolower(pathinfo($mi_horario, PATHINFO_EXTENSION)); 
                                    if ($ext === 'pdf'):
                                ?>
                                    <iframe src="../<?php echo htmlspecialchars($mi_horario); ?>" class="horario-iframe" frameborder="0"></iframe>
                                <?php else: ?>
                                    <img src="../<?php echo htmlspecialchars($mi_horario); ?>" alt="Mi Horario" class="horario-img">
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
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

            // Mostrar el nombre del archivo seleccionado
            document.getElementById('archivo_horario').addEventListener('change', function(e) {
                const fileNameDisplay = document.getElementById('file-name-display');
                const fileNameText = document.getElementById('file-name-text');
                
                if (this.files && this.files.length > 0) {
                    fileNameText.textContent = this.files[0].name;
                    fileNameDisplay.style.display = 'inline-flex';
                } else {
                    fileNameDisplay.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>