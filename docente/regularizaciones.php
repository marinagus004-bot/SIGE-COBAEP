<?php
session_start();
require_once '../config/database.php';

// Validación estricta: Solo docentes
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../login.php");
    exit();
}

$id_docente = $_SESSION['id_usuario'];
$nombre_usuario = isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') : 'Docente';

// Obtener las clases de regularización creadas por este maestro
try {
    $stmt = $pdo->prepare("
        SELECT * FROM CLASES_REGULARIZACION 
        WHERE id_maestro = ? 
        ORDER BY id_clase DESC
    ");
    $stmt->execute([$id_docente]);
    $clases_reg = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error cargando regularizaciones: " . $e->getMessage());
    $clases_reg = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#063f2b">
    <title>Regularizaciones · SIGE COBAEP 27</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <!-- CSS global del maestro y el específico para esta vista -->
    <link rel="stylesheet" href="style_dashboard_docente.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="style_regularizaciones.css?v=<?php echo time(); ?>">
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

            <nav class="navigation" aria-label="Navegación principal">
                <span class="nav-heading">Menú principal</span>
                <a href="dashboard_docente.php" class="nav-item">
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
                <!-- MARCADO COMO ACTIVO -->
                <a href="regularizaciones.php" class="nav-item active">
                    <i class="fas fa-book-open-reader"></i>
                    <span>Regularizaciones</span>
                </a>
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

    <!-- ================= CONTENIDO PRINCIPAL ================= -->
    <main class="main-content">

        <!-- TOPBAR -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu" id="mobileMenu" type="button" aria-label="Abrir menú">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <span class="eyebrow">EVALUACIÓN · DOCENTE</span>
                    <h1>Espacios de Regularización</h1>
                    <p>Crea y gestiona las clases para recuperación de alumnos.</p>
                </div>
            </div>
            <div class="topbar-right">
                <div class="profile">
                    <div class="profile-avatar"><i class="fas fa-chalkboard-user"></i></div>
                    <div class="profile-info">
                        <strong><?php echo $nombre_usuario; ?></strong>
                        <span>Docente</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- ALERTAS DE ÉXITO O ERROR -->
        <?php if (isset($_GET['exito'])): ?>
            <div class="alert alert-success" style="margin: 0 0 25px 0; background: #ecfdf5; color: #065f46; border: 1px solid #d1fae5; padding: 15px 20px; border-radius: 12px; font-weight: 600;">
                <i class="fas fa-check-circle" style="color: #10b981; margin-right: 8px;"></i>
                <?php echo htmlspecialchars($_GET['exito']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error" style="margin: 0 0 25px 0; background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; padding: 15px 20px; border-radius: 12px; font-weight: 600;">
                <i class="fas fa-exclamation-circle" style="color: #ef4444; margin-right: 8px;"></i>
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <!-- ACCIONES (Botón de Crear) -->
        <section class="toolbar-section">
            <button class="btn-crear" id="btnAbrirModal">
                <i class="fas fa-plus"></i>
                Nuevo Espacio de Regularización
            </button>
        </section>

        <!-- GRID DE CLASES DE REGULARIZACIÓN -->
        <section class="clases-container">
            <?php if (empty($clases_reg)): ?>
                <div class="empty-state-box">
                    <div class="icon-circle"><i class="fas fa-folder-plus"></i></div>
                    <h3>No hay espacios creados</h3>
                    <p>Haz clic en "Nuevo Espacio" para crear tu primera clase de regularización.</p>
                </div>
            <?php else: ?>
                <div class="clases-grid">
                    <?php foreach ($clases_reg as $clase): ?>
                        
                        <?php 
                            // Lógica blindada para el fondo de la tarjeta
                            $estilo_fondo = "";
                            if (!empty($clase['imagen_portada'])) {
                                $ruta_img = '../' . $clase['imagen_portada'];
                                // Forzamos el tamaño y posición directamente desde el HTML
                                $estilo_fondo = "background: linear-gradient(rgba(16, 37, 29, 0.4), rgba(16, 37, 29, 0.7)), url('" . htmlspecialchars($ruta_img) . "') center/cover no-repeat;";
                            }
                        ?>

                        <div class="clase-card">
                            <div class="clase-cover" style="<?php echo $estilo_fondo; ?>">
                                <span class="badge-estatus <?php echo strtolower($clase['estatus']); ?>">
                                    <?php echo htmlspecialchars($clase['estatus']); ?>
                                </span>
                            </div>
                            <div class="clase-info">
                                <h3><?php echo htmlspecialchars($clase['nombre_materia']); ?></h3>
                                <p class="desc"><?php echo htmlspecialchars($clase['descripcion']); ?></p>
                                
                                <div class="fechas">
                                    <div class="fecha-item">
                                        <i class="far fa-calendar-plus"></i> 
                                        <span>Inicio: <?php echo date('d/m/Y', strtotime($clase['fecha_inicio'])); ?></span>
                                    </div>
                                    <div class="fecha-item">
                                        <i class="far fa-calendar-check"></i> 
                                        <span>Fin: <?php echo date('d/m/Y', strtotime($clase['fecha_fin'])); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="clase-actions">
                                <a href="detalle_regularizacion.php?id=<?php echo $clase['id_clase']; ?>" class="btn-entrar">
                                    Entrar al espacio <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

    </main>

    <!-- ================= MODAL DE CREACIÓN ================= -->
    <div class="modal-overlay" id="modalCrear">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-layer-group"></i> Crear Espacio de Regularización</h2>
                <button class="close-modal" id="btnCerrarModal"><i class="fas fa-times"></i></button>
            </div>
            <!-- Formulario modificado para aceptar subida de archivos (imágenes) -->
            <form action="../procesos/guardar_regularizacion.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <p class="modal-instruction">Configura la materia para los alumnos que no alcanzaron la calificación mínima.</p>
                    
                    <div class="form-group">
                        <label for="nombre_materia">Nombre de la Materia</label>
                        <input type="text" id="nombre_materia" name="nombre_materia" class="input-modern" placeholder="Ej. Matemáticas I" required>
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción o Instrucciones</label>
                        <textarea id="descripcion" name="descripcion" class="input-modern" rows="3" placeholder="Ej. Espacio para entregar los cuadernillos de recuperación..."></textarea>
                    </div>

                    <!-- CAMPO DE IMAGEN DE PORTADA -->
                    <div class="form-group">
                        <label for="imagen_portada"><i class="fas fa-image" style="color:#16a05d;"></i> Imagen de Portada (Opcional)</label>
                        <input type="file" id="imagen_portada" name="imagen_portada" class="input-modern" accept="image/jpeg, image/png, image/webp" style="padding: 10px;">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="fecha_inicio">Fecha de Inicio</label>
                            <input type="date" id="fecha_inicio" name="fecha_inicio" class="input-modern" required>
                        </div>
                        <div class="form-group">
                            <label for="fecha_fin">Fecha Límite</label>
                            <input type="date" id="fecha_fin" name="fecha_fin" class="input-modern" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancelar" id="btnCancelarModal">Cancelar</button>
                    <button type="submit" class="btn-guardar"><i class="fas fa-save"></i> Crear Espacio</button>
                </div>
            </form>
        </div>
    </div>

    <script src="dashboard_maestro.js"></script>
    <script>
        // Lógica del Modal
        const modal = document.getElementById('modalCrear');
        const btnAbrir = document.getElementById('btnAbrirModal');
        const btnCerrar = document.getElementById('btnCerrarModal');
        const btnCancelar = document.getElementById('btnCancelarModal');

        function abrirModal() {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function cerrarModal() {
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        btnAbrir.addEventListener('click', abrirModal);
        btnCerrar.addEventListener('click', cerrarModal);
        btnCancelar.addEventListener('click', cerrarModal);

        // Cerrar si hace clic fuera del modal
        modal.addEventListener('click', (e) => {
            if (e.target === modal) cerrarModal();
        });
    </script>
</body>
</html>