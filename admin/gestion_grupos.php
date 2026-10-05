<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../login.php");
    exit();
}

try {
    $sql = "SELECT g.id_grupo, g.semestre, g.nombre_grupo, g.turno,
            (SELECT COUNT(*) FROM GRUPO_ALUMNO ga WHERE ga.id_grupo = g.id_grupo AND ga.fecha_baja IS NULL) AS total_alumnos
            FROM GRUPOS g 
            ORDER BY g.semestre, g.nombre_grupo ASC";
    $stmt = $pdo->query($sql);
    $grupos = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error al cargar grupos: " . $e->getMessage());
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
    <title>Gestión de Grupos · COBAEP Plantel 27</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <!-- Hojas de estilo -->
    <link rel="stylesheet" href="style_dashboard_admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="style-gestionar-grupos.css?v=<?php echo time(); ?>">
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

            <nav class="navigation" aria-label="Navegación principal">
                <span class="nav-heading">Menú principal</span>
                <a href="dashboard_admin.php" class="nav-item">
                    <i class="fas fa-house"></i>
                    <span>Panel de Control</span>
                </a>

                <span class="nav-heading">Académico</span>
                <a href="gestion_grupos.php" class="nav-item active">
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

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ===== CONTENIDO PRINCIPAL ===== -->
    <main class="main-content">

        <!-- Barra superior -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu" id="mobileMenu" type="button" aria-label="Abrir menú">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <span class="eyebrow">ACADÉMICO · SIGE</span>
                    <h1 class="page-title">Gestión de Grupos</h1>
                    <p class="page-desc">Administra los grupos académicos registrados en el plantel.</p>
                </div>
            </div>

            <div class="topbar-right">
                <div class="date-pill">
                    <i class="far fa-calendar"></i>
                    <span id="fechaTexto"><?php echo date('d/m/Y H:i a'); ?></span>
                </div>
                <div class="profile pill-style">
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

        <section class="section">
            
            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'eliminado'): ?>
                <div class="alert alert-error">
                    <i class="fas fa-check-circle"></i> 
                    <span>El grupo ha sido eliminado (los alumnos se han desvinculado).</span>
                </div>
            <?php endif; ?>

            <?php if(count($grupos) > 0): ?>
                
                <!-- CABECERA DE GRUPOS CORREGIDA -->
                <div class="grupos-header">
                    <div class="grupos-info">
                        <div class="info-icon"><i class="fas fa-layer-group"></i></div>
                        <div>
                            <strong>Grupos Activos</strong>
                            <span>Total: <?php echo count($grupos); ?> grupos registrados</span>
                        </div>
                    </div>
                    <a href="agregar_grupo.php" class="btn-add-group">
                        <i class="fas fa-plus"></i> Nuevo Grupo
                    </a>
                </div>

                <!-- CUADRÍCULA DE TARJETAS -->
                <div class="grid-grupos">
                    <?php foreach($grupos as $g): ?>
                        <div class="card-grupo">
                            <div class="card-grupo-top">
                                <h3><?php echo htmlspecialchars($g['semestre']); ?> "<?php echo htmlspecialchars($g['nombre_grupo']); ?>"</h3>
                                <span class="badge-status">Activo</span>
                            </div>
                            
                            <div class="card-grupo-body">
                                <div class="grupo-detail">
                                    <i class="far fa-clock"></i>
                                    <span>Turno <?php echo htmlspecialchars($g['turno']); ?></span>
                                </div>
                                <div class="grupo-detail highlight">
                                    <i class="fas fa-users"></i>
                                    <span><strong><?php echo $g['total_alumnos']; ?></strong> alumnos inscritos</span>
                                </div>
                            </div>

                            <div class="card-actions">
                                <!-- Asegúrate de apuntar a ver_grupo_2.php o el nombre final que le hayas dado -->
                                <a href="ver_grupo.php?id=<?php echo $g['id_grupo']; ?>" class="btn-ver">
                                    <i class="fas fa-eye"></i> Ver Lista
                                </a>
                                <a href="../procesos/eliminar_grupo.php?id=<?php echo $g['id_grupo']; ?>" 
                                   class="btn-eliminar" 
                                   title="Eliminar grupo"
                                   onclick="return confirm('¿Estás seguro de eliminar este grupo? Los alumnos quedarán sin asignación.');">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php else: ?>
            <!-- ESTADO VACÍO (SIN CAMBIOS) -->
            <div class="empty-groups-page">
                <div class="empty-hero">
                    <div class="groups-illustration">
                        <div class="sparkle sp-1"><i class="fas fa-star-of-life"></i></div>
                        <div class="sparkle sp-2"><i class="fas fa-star-of-life"></i></div>
                        <div class="sparkle sp-3"><i class="fas fa-star-of-life"></i></div>
                        
                        <div class="clipboard">
                            <div class="clipboard-clip"><i class="fas fa-paperclip"></i></div>
                            <div class="clipboard-paper">
                                <div class="paper-icon"><i class="fas fa-users"></i></div>
                                <div class="paper-line line-large"></div>
                                <div class="paper-line line-medium"></div>
                                <div class="paper-line line-small"></div>
                            </div>
                        </div>
                        <div class="illustration-plant plant-left"><i class="fas fa-leaf"></i></div>
                        <div class="illustration-plant plant-right"><i class="fas fa-leaf"></i></div>
                        <div class="illustration-shadow"></div>
                    </div>
                    
                    <h2>Aún no hay grupos registrados</h2>
                    <p class="empty-description">Comienza a estructurar el ciclo escolar. Habilita tu primer grupo para poder asignar alumnos y docentes de manera organizada.</p>
                    <a href="agregar_grupo.php" class="btn-add-group">
                        <i class="fas fa-plus"></i> Crear el primer grupo
                    </a>
                </div>

                <div class="group-info-card">
                    <div class="info-card-icon"><i class="far fa-lightbulb"></i></div>
                    <div class="info-card-content">
                        <h3>¿Qué es un grupo académico?</h3>
                        <p>Es la unidad que reúne alumnos y docentes de un grado, grupo y turno para organizar el proceso educativo.</p>
                    </div>
                </div>

                <div class="group-benefits">
                    <div class="benefit-item">
                        <div class="benefit-icon"><i class="fas fa-user-group"></i></div>
                        <div>
                            <h3>Organiza mejor</h3>
                            <p>Agrupa alumnos y docentes de forma estructurada.</p>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon"><i class="fas fa-clipboard-list"></i></div>
                        <div>
                            <h3>Control eficiente</h3>
                            <p>Administra horarios, materias y responsables fácilmente.</p>
                        </div>
                    </div>
                    <div class="benefit-item">
                        <div class="benefit-icon"><i class="fas fa-chart-column"></i></div>
                        <div>
                            <h3>Información clara</h3>
                            <p>Obtén reportes y estadísticas en tiempo real.</p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </section>

    </main>

    <!-- SCRIPTS -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const mobileMenu = document.getElementById('mobileMenu');
            const collapseToggle = document.getElementById('collapseToggle');
            const body = document.body;
            const fechaTexto = document.getElementById('fechaTexto');

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

            if(mobileMenu) mobileMenu.addEventListener('click', abrirMenu);
            if(overlay) overlay.addEventListener('click', cerrarMenu);

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