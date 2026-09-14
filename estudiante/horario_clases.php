<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../login.php");
    exit();
}

$id_docente = $_SESSION['id_usuario'];
$nombre_usuario = isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') : 'Docente';

// Obtener el archivo de horario de este maestro
try {
    $stmt = $pdo->prepare("SELECT horario_archivo FROM MAESTROS WHERE id_maestro = ?");
    $stmt->execute([$id_docente]);
    $maestro = $stmt->fetch(PDO::FETCH_ASSOC);
    $mi_horario = $maestro ? $maestro['horario_archivo'] : null;
} catch (PDOException $e) {
    error_log("Error cargando horario: " . $e->getMessage());
    $mi_horario = null;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#063f2b">
    <title>Mi Horario · SIGE COBAEP 27</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <link rel="stylesheet" href="style_dashboard_estud.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="style_horario_clases.css?v=<?php echo time(); ?>">
</head>

<body>

    <aside class="sidebar" id="sidebar">
        <!-- Puedes copiar el contenido de tu sidebar de mis_grupos.php, solo agrega el enlace activo a Mi Horario -->
        <button id="collapseToggle" class="collapse-toggle"><i class="fas fa-chevron-left"></i></button>
        <div class="sidebar-inner">
            <div class="brand">
                <img src="../img/LogoCobaep.png" alt="Logo" class="brand-logo">
                <div class="brand-copy"><strong>SIGE<span>-Cobaep</span></strong><small>Plantel 27</small></div>
            </div>

            <nav class="navigation">
                <span class="nav-heading">Menú principal</span>
                <a href="dashboard_docente.php" class="nav-item"><i class="fas fa-house"></i><span>Inicio</span></a>
                
                <span class="nav-heading">Académico</span>
                <a href="mis_grupos.php" class="nav-item"><i class="fas fa-users-rectangle"></i><span>Mis Grupos</span></a>
                
                <!-- NUEVO ENLACE AL HORARIO -->
                <a href="mi_horario.php" class="nav-item active"><i class="far fa-calendar-alt"></i><span>Mi Horario</span></a>

                <span class="nav-heading">Evaluación</span>
                <a href="regularizaciones.php" class="nav-item"><i class="fas fa-book-open-reader"></i><span>Regularizaciones</span></a>
            </nav>

            <div class="sidebar-bottom">
                <div class="institution">
                    <div class="institution-icon"><i class="fas fa-building-columns"></i></div>
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

    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu" id="mobileMenu"><i class="fas fa-bars"></i></button>
                <div>
                    <span class="eyebrow">ACADÉMICO · DOCENTE</span>
                    <h1>Mi Horario de Clases</h1>
                    <p>Sube y consulta tu carga horaria de este semestre.</p>
                </div>
            </div>
        </header>

        <div class="layout-horario">
            
            <!-- COLUMNA IZQUIERDA: FORMULARIO DE SUBIDA -->
            <div class="col-upload">
                <div class="card-modern">
                    <h3><i class="fas fa-cloud-upload-alt icon-green"></i> Actualizar Horario</h3>
                    <p class="text-muted">Sube tu horario en formato PDF o Imagen para tenerlo siempre a la mano.</p>
                    
                    <?php if (isset($_GET['exito'])): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['exito']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
                        </div>
                    <?php endif; ?>

                    <form action="../procesos/subir_horario.php" method="POST" enctype="multipart/form-data">
                        <!-- ZONA DE SUBIDA MODERNA -->
                        <label for="archivo_horario" class="upload-zone-modern">
                            <div class="upload-icon-circle">
                                <i class="fas fa-cloud-upload-alt"></i>
                            </div>
                            <span class="upload-title">Haz clic para seleccionar tu archivo</span>
                            <span class="upload-desc">Formatos permitidos: PDF, JPG, PNG</span>
                            
                            <!-- El input real está oculto -->
                            <input type="file" name="archivo_horario" id="archivo_horario" accept=".pdf, .jpg, .jpeg, .png, .webp" required class="hidden-input">
                            
                            <!-- Etiqueta para mostrar el nombre del archivo seleccionado -->
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

            <!-- COLUMNA DERECHA: VISOR DEL HORARIO -->
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
                                <!-- Visor de PDF -->
                                <iframe src="../<?php echo htmlspecialchars($mi_horario); ?>" class="horario-iframe" frameborder="0"></iframe>
                            <?php else: ?>
                                <!-- Visor de Imagen -->
                                <img src="../<?php echo htmlspecialchars($mi_horario); ?>" alt="Mi Horario" class="horario-img">
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script src="dashboard_maestro.js"></script>
    <script>
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
    </script>
</body>
</html>