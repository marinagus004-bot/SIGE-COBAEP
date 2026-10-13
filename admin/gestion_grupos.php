<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../login.php");
    exit();
}

try {
    // Añadimos la condición WHERE g.estatus = 'Activo'
    $sql = "SELECT g.id_grupo, g.semestre, g.nombre_grupo, g.turno,
            (SELECT COUNT(*) FROM GRUPO_ALUMNO ga WHERE ga.id_grupo = g.id_grupo AND ga.fecha_baja IS NULL) AS total_alumnos
            FROM GRUPOS g 
            WHERE g.estatus = 'Activo'
            ORDER BY g.semestre, g.nombre_grupo ASC";
    $stmt = $pdo->query($sql);
    $grupos = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error al cargar grupos: " . $e->getMessage());
}

$nombre_usuario = isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') : 'Administrador';
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
    <link rel="stylesheet" href="style_dashboard_admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="style-gestionar-grupos.css?v=<?php echo time(); ?>">
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <button id="collapseToggle" class="collapse-toggle" aria-label="Colapsar menú"><i class="fas fa-chevron-left"></i></button>
        <div class="sidebar-inner">
            <div class="brand">
                <img src="../img/LogoCobaep.png" alt="Logo COBAEP" class="brand-logo">
                <div class="brand-copy"><strong>SIGE<span>-Cobaep</span></strong><small>Plantel 27</small></div>
            </div>
            <nav class="navigation" aria-label="Navegación principal">
                <span class="nav-heading">Menú principal</span>
                <a href="dashboard_admin.php" class="nav-item"><i class="fas fa-house"></i><span>Panel de Control</span></a>
                <span class="nav-heading">Académico</span>
                <a href="gestion_grupos.php" class="nav-item active"><i class="fas fa-users-rectangle"></i><span>Gestión de Grupos</span></a>
                <a href="gestion_horarios.php" class="nav-item"><i class="fas fa-calendar-days"></i><span>Gestión de Horarios</span></a>
                <span class="nav-heading">Personal</span>
                <a href="gestion_usuarios.php" class="nav-item"><i class="fas fa-users"></i><span>Directorio de Personal</span></a>
                <a href="agregar_usuario.php" class="nav-item"><i class="fas fa-user-plus"></i><span>Nuevo Registro</span></a>
                <span class="nav-heading">Información</span>
                <a href="reportes.php" class="nav-item"><i class="fas fa-chart-column"></i><span>Reportes y Listas</span></a>
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
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu" id="mobileMenu" type="button"><i class="fas fa-bars"></i></button>
                <div>
                    <span class="eyebrow">ACADÉMICO · SIGE</span>
                    <h1 class="page-title">Gestión de Grupos</h1>
                    <p class="page-desc">Administra los grupos académicos registrados en el plantel.</p>
                </div>
            </div>
            <div class="topbar-right">
                <div class="date-pill"><i class="far fa-calendar"></i><span id="fechaTexto"><?php echo date('d/m/Y H:i a'); ?></span></div>
                <div class="profile pill-style">
                    <div class="profile-avatar"><i class="fas fa-user"></i></div>
                    <div class="profile-info">
                        <strong><?php echo $nombre_usuario; ?></strong><span>Administrador</span>
                    </div>
                </div>
            </div>
        </header>

        <section class="section">
            <!-- ALERTA: GRUPO ELIMINADO -->
            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'eliminado'): ?>
                <div class="alert alert-error" style="background: #fef2f2; color: #991b1b; padding: 15px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #fecaca;">
                    <i class="fas fa-check-circle"></i> <span>El grupo ha sido eliminado correctamente.</span>
                </div>
            <?php endif; ?>
            
            <!-- ALERTA: PROMOCIÓN DE SEMESTRE EXITOSA -->
            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'ciclo_cerrado'): ?>
                <div class="alert alert-success" style="background: #ecfdf5; color: #065f46; padding: 15px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #a7f3d0;">
                    <i class="fas fa-check-circle"></i> <span>¡Promoción de semestre exitosa! Los alumnos de 6to han egresado y el resto avanzó de semestre.</span>
                </div>
            <?php endif; ?>

            <!-- ALERTA NUEVA: GRUPO CREADO EXITOSAMENTE -->
            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'grupo_creado'): ?>
                <div class="alert alert-success" style="background: #ecfdf5; color: #065f46; padding: 15px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #a7f3d0;">
                    <i class="fas fa-check-circle"></i> <span>¡El grupo fue creado exitosamente!</span>
                </div>
            <?php endif; ?>

            <!-- ALERTA NUEVA: ERROR GRUPO DUPLICADO -->
            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'grupo_duplicado'): ?>
                <div class="alert alert-error" style="background: #fef2f2; color: #991b1b; padding: 15px; border-radius: 12px; margin-bottom: 20px; border: 1px solid #fecaca;">
                    <i class="fas fa-exclamation-triangle"></i> <span><strong>No se pudo crear el grupo:</strong> Ya existe un grupo activo con ese semestre y solo se permiten duplicados para el turno Intermedio.</span>
                </div>
            <?php endif; ?>

            <?php if(count($grupos) > 0): ?>
                <div class="grupos-header">
                    <div class="grupos-info">
                        <div class="info-icon"><i class="fas fa-layer-group"></i></div>
                        <div>
                            <strong>Grupos Activos</strong>
                            <span>Total: <?php echo count($grupos); ?> grupos registrados</span>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <!-- BOTÓN DE CIERRE DE CICLO -->
                        <button type="button" class="btn-add-group" id="btnAbrirModalCierre" style="background-color: #026714; color: white;">
                            <i class="fas fa-forward-step"></i> Promover Semestre
                        </button>
                        <a href="agregar_grupo.php" class="btn-add-group"><i class="fas fa-plus"></i> Nuevo Grupo</a>
                    </div>
                </div>

                <div class="grid-grupos">
                    <?php foreach($grupos as $g): ?>
                        <div class="card-grupo">
                            <div class="card-grupo-top">
                                <h3><?php echo htmlspecialchars($g['semestre']); ?> "<?php echo htmlspecialchars($g['nombre_grupo']); ?>"</h3>
                                <span class="badge-status">Activo</span>
                            </div>
                            <div class="card-grupo-body">
                                <div class="grupo-detail"><i class="far fa-clock"></i><span>Turno <?php echo htmlspecialchars($g['turno']); ?></span></div>
                                <div class="grupo-detail highlight"><i class="fas fa-users"></i><span><strong><?php echo $g['total_alumnos']; ?></strong> alumnos inscritos</span></div>
                            </div>
                            <div class="card-actions">
                                <a href="ver_grupo.php?id=<?php echo $g['id_grupo']; ?>" class="btn-ver" style="background: #e9f8f0; color: #16a05d;">
                                    <i class="fas fa-users-cog"></i> Gestionar Alumnos
                                </a>
                                <a href="editar_grupo.php?id=<?php echo $g['id_grupo']; ?>" class="btn-ver" style="background: #fff6e6; color: #d97706;" title="Editar grupo">
                                    <i class="fas fa-pen"></i> Editar
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
                <div class="empty-groups-page">
                    <h2>Aún no hay grupos registrados</h2>
                    <a href="agregar_grupo.php" class="btn-add-group"><i class="fas fa-plus"></i> Crear el primer grupo</a>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- MODAL DE CIERRE DE CICLO -->
    <div id="modalCierre" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(3, 43, 30, 0.7); z-index: 3000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div style="background: #ffffff; padding: 30px; border-radius: 16px; max-width: 550px; width: 90%; box-shadow: 0 24px 60px rgba(0,0,0,0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0; color: #92400e; font-size: 1.4rem;"><i class="fas fa-exclamation-triangle" style="color: #e73909; margin-right: 8px;"></i> Advertencia de Promoción</h2>
                <button type="button" id="btnCerrarModalCierre" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280;">&times;</button>
            </div>
            
            <div style="background: #fffbeb; border-left: 4px solid #f5260b; padding: 15px; border-radius: 6px; margin-bottom: 20px; color: #92400e; font-size: 0.95rem; line-height: 1.5;">
                Estás a punto de ejecutar el <strong>Cierre de Ciclo Escolar</strong>. Esta acción automatizará lo siguiente:
                <ul style="margin-top: 10px; margin-left: 20px;">
                    <li>Los alumnos de <b>6to semestre</b> se marcarán como Egresados/Finalizados y sus grupos se archivarán.</li>
                    <li>El resto de los grupos (1ro a 5to) avanzarán automáticamente al <b>siguiente semestre</b> (ej. 1ro pasa a 2do).</li>
                    <li>Todos los alumnos inscritos avanzarán de semestre junto con su grupo.</li>
                </ul>
                <br><strong>¿Estás completamente seguro de realizar esta acción?</strong> Esta operación no se puede deshacer fácilmente.
            </div>

            <div style="display: flex; gap: 15px; justify-content: flex-end;">
                <button type="button" id="btnCancelarCierre" style="padding: 12px 20px; border-radius: 10px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; cursor: pointer; font-weight: 600;">Cancelar</button>
                <a href="../procesos/promover_semestre.php" style="padding: 12px 20px; border-radius: 10px; background: #d93006; color: white; border: none; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-check"></i> Sí, Promover Semestre
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const mobileMenu = document.getElementById('mobileMenu');
            const collapseToggle = document.getElementById('collapseToggle');
            const body = document.body;
            
            if (collapseToggle) {
                collapseToggle.addEventListener('click', () => {
                    body.classList.toggle('sidebar-collapsed');
                });
            }
            if(mobileMenu) mobileMenu.addEventListener('click', () => {
                sidebar.classList.add('mobile-open');
                overlay.classList.add('visible');
                body.classList.add('menu-open');
            });
            if(overlay) overlay.addEventListener('click', () => {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('visible');
                body.classList.remove('menu-open');
            });

            // Lógica del Modal de Cierre
            const modalCierre = document.getElementById('modalCierre');
            const btnAbrirCierre = document.getElementById('btnAbrirModalCierre');
            const btnCerrarCierre = document.getElementById('btnCerrarModalCierre');
            const btnCancelarCierre = document.getElementById('btnCancelarCierre');

            if (btnAbrirCierre && modalCierre) {
                btnAbrirCierre.addEventListener('click', () => {
                    modalCierre.style.display = 'flex';
                });
                
                const cerrarModal = () => modalCierre.style.display = 'none';
                btnCerrarCierre.addEventListener('click', cerrarModal);
                btnCancelarCierre.addEventListener('click', cerrarModal);
            }
        });
    </script>
</body>
</html>