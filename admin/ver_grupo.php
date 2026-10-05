<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo' || !isset($_GET['id'])) {
    header("Location: gestion_grupos.php");
    exit();
}

$id_grupo = $_GET['id'];

try {
    // 1. Datos del grupo actual
    $stmtG = $pdo->prepare("SELECT semestre, nombre_grupo, turno FROM GRUPOS WHERE id_grupo = ?");
    $stmtG->execute([$id_grupo]);
    $grupo = $stmtG->fetch();
    if (!$grupo) die("El grupo no existe.");

    // 2. Alumnos ya inscritos en este grupo (que no estén dados de baja del grupo)
    $sql = "SELECT a.id_alumno, a.matricula, CONCAT_WS(' ', a.nombre, a.apellido_paterno, a.apellido_materno) AS nombre_completo, a.estatus, a.periodo_ingreso 
            FROM ALUMNOS a
            INNER JOIN GRUPO_ALUMNO ga ON a.id_alumno = ga.id_alumno
            WHERE ga.id_grupo = ? AND ga.fecha_baja IS NULL
            ORDER BY a.apellido_paterno ASC";
    $stmtA = $pdo->prepare($sql);
    $stmtA->execute([$id_grupo]);
    $alumnos = $stmtA->fetchAll();

    // 3. Alumnos DISPONIBLES (estatus 'Alta') que NO están en este grupo
    $sqlDisponibles = "SELECT a.id_alumno, a.matricula, CONCAT_WS(' ', a.nombre, a.apellido_paterno, a.apellido_materno) AS nombre_completo, a.periodo_ingreso 
                       FROM ALUMNOS a
                       WHERE a.estatus = 'Alta' 
                       AND NOT EXISTS (
                           SELECT 1 
                           FROM GRUPO_ALUMNO ga 
                           WHERE ga.id_alumno = a.id_alumno 
                           AND ga.id_grupo = ? 
                           AND ga.fecha_baja IS NULL
                       )
                       ORDER BY a.apellido_paterno ASC";
    $stmtDisp = $pdo->prepare($sqlDisponibles);
    $stmtDisp->execute([$id_grupo]);
    $alumnos_disponibles = $stmtDisp->fetchAll();

} catch (PDOException $e) {
    die("Error de BD: " . $e->getMessage());
}

$nombre_usuario = isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') : 'Administrador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Grupo · SIGE COBAEP</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <!-- Archivos CSS -->
    <link rel="stylesheet" href="style_dashboard_admin.css">
    <link rel="stylesheet" href="style-ver_grupo.css">
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
    <main class="main-content layout-detalle" id="contenedor-principal">
        
        <header class="top-header">
            <button class="mobile-menu" id="mobileMenu" type="button" aria-label="Abrir menú" style="margin-right: 15px; display: none;">
                <i class="fas fa-bars"></i>
            </button>
            <div class="page-title-container">
                <div class="icon-box">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <h1>Detalle de Grupo</h1>
                    <div class="subtitle-date" id="fechaHora">
                        <i class="far fa-calendar-alt"></i> <span id="fechaTexto"><?php echo date('d/m/Y, h:i a'); ?></span>
                    </div>
                </div>
            </div>

            <div class="user-profile-pill">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-info">
                    <strong><?php echo $nombre_usuario; ?></strong>
                    <span>Administrador</span>
                </div>
            </div>
        </header>

        <!-- Tarjeta de Información del Grupo -->
        <div class="group-info-card">
            <div class="group-details">
                <div class="group-icon"><i class="fas fa-users-viewfinder"></i></div>
                <div>
                    <h2><?php echo htmlspecialchars($grupo['semestre']); ?> - Grupo "<?php echo htmlspecialchars($grupo['nombre_grupo']); ?>"</h2>
                    <p>Turno <?php echo htmlspecialchars($grupo['turno']); ?> <span class="divider">|</span> Total inscritos: <?php echo count($alumnos); ?></p>
                </div>
            </div>
            <div class="group-actions">
                <a href="gestion_grupos.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Volver</a>
                <button type="button" class="btn btn-primary" id="btnAbrirModal">
                    <i class="fas fa-user-plus"></i> Inscribir Alumno
                </button>
            </div>
        </div>

        <!-- Tabla de Alumnos Inscritos -->
        <div class="content-card">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="buscadorAlumnos" placeholder="Buscar por matrícula, nombre o periodo de ingreso...">
            </div>

            <div class="table-responsive">
                <table class="data-table" id="tablaAlumnos">
                    <thead>
                        <tr>
                            <th>MATRÍCULA</th>
                            <th>NOMBRE DEL ALUMNO</th>
                            <th>PERIODO</th>
                            <th>ESTATUS</th>
                            <th>ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($alumnos) > 0): ?>
                            <?php foreach($alumnos as $al): ?>
                            <tr class="fila-alumno">
                                <td class="font-medium"><?php echo htmlspecialchars($al['matricula']); ?></td>
                                <td><?php echo htmlspecialchars($al['nombre_completo']); ?></td>
                                <td class="text-muted"><?php echo htmlspecialchars($al['periodo_ingreso'] ?? 'N/D'); ?></td>
                                <td><span class="badge status-active"><?php echo htmlspecialchars($al['estatus']); ?></span></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="perfil_alumno.php?id=<?php echo $al['id_alumno']; ?>" class="btn-icon btn-view" title="Consultar"><i class="fas fa-eye"></i></a>
                                        <a href="editar_usuario.php?id=<?php echo $al['id_alumno']; ?>&tipo=alumnos" class="btn-icon btn-edit" title="Editar"><i class="fas fa-pen"></i></a>
                                        <a href="../procesos/baja_alumno.php?id=<?php echo $al['id_alumno']; ?>&grupo=<?php echo $id_grupo; ?>" class="btn-icon btn-delete" title="Baja" onclick="return confirm('¿Estás seguro de quitar a este alumno del grupo?');"><i class="fas fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <div class="empty-icon"><i class="fas fa-user-slash"></i></div>
                                        <h3>Sin alumnos registrados</h3>
                                        <p>Aún no hay alumnos inscritos en este grupo.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- ===== MODAL DE INSCRIPCIÓN RÁPIDA ===== -->
    <div id="modalInscribir" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-plus"></i> Inscribir Alumno Existente</h2>
                <button class="close-modal" id="btnCerrarModal">&times;</button>
            </div>
            
            <div class="modal-body">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="buscadorDisponibles" placeholder="Buscar por matrícula o nombre del alumno...">
                </div>

                <div class="disponibles-list">
                    <table class="data-table">
                        <tbody>
                            <?php if(count($alumnos_disponibles) > 0): ?>
                                <?php foreach($alumnos_disponibles as $disp): ?>
                                <tr class="fila-disponible">
                                    <td>
                                        <strong><?php echo htmlspecialchars($disp['matricula']); ?></strong><br>
                                        <span class="text-muted" style="font-size: 0.75rem;">Ingreso: <?php echo htmlspecialchars($disp['periodo_ingreso'] ?? 'N/A'); ?></span>
                                    </td>
                                    <td style="font-size: 0.9rem; font-weight: 500;"><?php echo htmlspecialchars($disp['nombre_completo']); ?></td>
                                    <td style="text-align: right;">
                                        <!-- Formulario para inscribir al alumno seleccionado -->
                                        <form action="inscribir_existente.php" method="POST">
                                            <input type="hidden" name="id_grupo" value="<?php echo $id_grupo; ?>">
                                            <input type="hidden" name="id_alumno" value="<?php echo $disp['id_alumno']; ?>">
                                            <button type="submit" class="btn btn-primary" style="padding: 8px 16px; font-size: 0.85rem; border-radius: 8px;">
                                                <i class="fas fa-plus"></i> Añadir
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align: center; color: #6b7280; padding: 30px;">
                                        No hay alumnos en estatus de "Alta" disponibles para agregar.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer">
                <p>¿El alumno es nuevo en el plantel?</p>
                <a href="agregar_usuario.php?grupo=<?php echo $id_grupo; ?>" class="btn-link">
                    Regístralo desde cero
                </a>
            </div>
        </div>
    </div>

    <!-- Script Integrado -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // LÓGICA DE LA BARRA LATERAL
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const mobileMenu = document.getElementById('mobileMenu');
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

            if(mobileMenu) mobileMenu.addEventListener('click', abrirMenu);
            if(overlay) overlay.addEventListener('click', cerrarMenu);

            window.addEventListener('resize', () => {
                if (window.innerWidth > 900) cerrarMenu();
            });

            // LÓGICA DEL BUSCADOR DE ALUMNOS INSCRITOS
            const buscador = document.getElementById('buscadorAlumnos');
            const filas = document.querySelectorAll('.fila-alumno');
            if (buscador) {
                buscador.addEventListener('keyup', function() {
                    const texto = this.value.toLowerCase();
                    filas.forEach(fila => {
                        const matricula = fila.children[0].textContent.toLowerCase();
                        const nombre = fila.children[1].textContent.toLowerCase();
                        const periodo = fila.children[2].textContent.toLowerCase();
                        if (matricula.includes(texto) || nombre.includes(texto) || periodo.includes(texto)) {
                            fila.style.display = '';
                        } else {
                            fila.style.display = 'none';
                        }
                    });
                });
            }

            // LÓGICA DEL MODAL DE INSCRIPCIÓN RÁPIDA
            const modal = document.getElementById('modalInscribir');
            const btnAbrir = document.getElementById('btnAbrirModal');
            const btnCerrar = document.getElementById('btnCerrarModal');

            if (modal && btnAbrir && btnCerrar) {
                btnAbrir.addEventListener('click', () => modal.classList.add('active'));
                btnCerrar.addEventListener('click', () => modal.classList.remove('active'));
                modal.addEventListener('click', (e) => {
                    if (e.target === modal) modal.classList.remove('active');
                });

                // Buscador dentro del modal
                const buscadorModal = document.getElementById('buscadorDisponibles');
                const filasDisponibles = document.querySelectorAll('.fila-disponible');
                buscadorModal.addEventListener('keyup', function() {
                    const texto = this.value.toLowerCase();
                    filasDisponibles.forEach(fila => {
                        const matricula = fila.children[0].textContent.toLowerCase();
                        const nombre = fila.children[1].textContent.toLowerCase();
                        if (matricula.includes(texto) || nombre.includes(texto)) {
                            fila.style.display = '';
                        } else {
                            fila.style.display = 'none';
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>