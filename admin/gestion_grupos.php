<?php
session_start();
require_once '../config/database.php';
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../login.php");
    exit();
}
try {
    $sql = "SELECT g.id_grupo, g.semestre, g.nombre_grupo, g.turno, (SELECT COUNT(*) FROM GRUPO_ALUMNO ga WHERE ga.id_grupo = g.id_grupo AND ga.fecha_baja IS NULL) AS total_alumnos FROM GRUPOS g WHERE g.estatus = 'Activo' ORDER BY g.semestre, g.nombre_grupo ASC";
    $stmt = $pdo->query($sql);
    $grupos = $stmt->fetchAll();
    $grupos_agrupados = [];
    foreach ($grupos as $g) {
        $clave = $g['semestre'] . '_' . $g['turno'];
        if (!isset($grupos_agrupados[$clave])) {
            $grupos_agrupados[$clave] = ['semestre' => $g['semestre'], 'turno' => $g['turno'], 'total_grupos' => 0, 'total_alumnos' => 0];
        }
        $grupos_agrupados[$clave]['total_grupos']++;
        $grupos_agrupados[$clave]['total_alumnos'] += $g['total_alumnos'];
    }
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
    <link rel="stylesheet" href="style_sidebar.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu" id="mobileMenu" type="button"><i class="fas fa-bars"></i></button>
                <div><span class="eyebrow">ACADÉMICO · SIGE</span><h1 class="page-title">Gestión de Grupos</h1><p class="page-desc">Administra los grupos académicos registrados en el plantel.</p></div>
            </div>
            <div class="topbar-right">
                <div class="date-pill"><i class="far fa-calendar"></i><span id="fechaTexto"><?php echo date('d/m/Y H:i a'); ?></span></div>
                <div class="profile pill-style"><div class="profile-avatar"><i class="fas fa-user"></i></div><div class="profile-info"><strong><?php echo $nombre_usuario; ?></strong><span>Administrador</span></div></div>
            </div>
        </header>
        <section class="section">
            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'eliminado'): ?>
                <div class="alert alert-error"><i class="fas fa-check-circle"></i> <span>El grupo ha sido eliminado correctamente.</span></div>
            <?php endif; ?>
            <?php if(count($grupos) > 0): ?>
                <div class="grupos-header">
                    <div class="grupos-info">
                        <div class="info-icon"><i class="fas fa-layer-group"></i></div>
                        <div><strong>Grupos Activos</strong><span>Total: <?php echo count($grupos); ?> grupos registrados</span></div>
                    </div>
                    <div class="header-actions" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                        <div class="search-container"><i class="fas fa-search search-icon"></i><input type="text" id="buscadorGrupos" class="search-input" placeholder="Buscar grupo (ej. 1D)..."></div>
                        <select id="filtroTurno" class="search-select">
                            <option value="">Todos los turnos</option><option value="matutino">Matutino</option><option value="intermedio">Intermedio</option>
                        </select>
                        <div class="toggle-container">
                            <span class="toggle-label"><i class="fas fa-folder-tree"></i> Agrupar</span>
                            <label class="switch"><input type="checkbox" id="toggleVista"><span class="slider round"></span></label>
                        </div>
                        <button type="button" class="btn-add-group" id="btnAbrirModalCierre" style="background-color: #026714; color: white;"><i class="fas fa-forward-step"></i> Promover Semestre</button>
                        <a href="agregar_grupo.php" class="btn-add-group"><i class="fas fa-plus"></i> Nuevo Grupo</a>
                    </div>
                </div>
                <div id="vistaDetallada" class="grid-grupos">
                    <?php foreach($grupos as $g): ?>
                        <div class="card-grupo <?php echo ($g['turno'] === 'Intermedio') ? 'card-intermedio' : ''; ?>">
                            <div class="card-grupo-top">
                                <h3><?php echo htmlspecialchars($g['semestre']); ?> "<?php echo htmlspecialchars($g['nombre_grupo']); ?>"</h3>
                                <?php if($g['turno'] === 'Intermedio'): ?><span class="badge-status badge-intermedio">INTERMEDIO</span><?php else: ?><span class="badge-status">ACTIVO</span><?php endif; ?>
                            </div>
                            <div class="card-grupo-body">
                                <div class="grupo-detail"><i class="far fa-clock"></i><span>Turno <?php echo htmlspecialchars($g['turno']); ?></span></div>
                                <div class="grupo-detail highlight"><i class="fas fa-users"></i><span><strong><?php echo $g['total_alumnos']; ?></strong> alumnos inscritos</span></div>
                            </div>
                            <div class="card-actions">
                                <a href="ver_grupo.php?id=<?php echo $g['id_grupo']; ?>" class="btn-ver" style="background: #e9f8f0; color: #16a05d;"><i class="fas fa-users-cog"></i> Gestionar</a>
                                <a href="editar_grupo.php?id=<?php echo $g['id_grupo']; ?>" class="btn-ver" style="background: #fff6e6; color: #d97706;" title="Editar grupo"><i class="fas fa-pen"></i> Editar</a>
                                <a href="../procesos/eliminar_grupo.php?id=<?php echo $g['id_grupo']; ?>" class="btn-eliminar" onclick="return confirm('¿Estás seguro de eliminar este grupo?');"><i class="fas fa-trash"></i></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div id="vistaAgrupada" class="grid-grupos" style="display: none;">
                    <?php foreach($grupos_agrupados as $agrupado): ?>
                        <div class="card-grupo card-folder <?php echo ($agrupado['turno'] === 'Intermedio') ? 'card-intermedio' : ''; ?>">
                            <div class="card-folder-icon <?php echo ($agrupado['turno'] === 'Intermedio') ? 'icon-intermedio' : ''; ?>"><i class="fas fa-folder-open"></i></div>
                            <div class="card-grupo-top" style="margin-bottom: 10px;"><h3 style="font-size: 1.4rem;">Semestres <?php echo htmlspecialchars($agrupado['semestre']); ?></h3></div>
                            <div class="card-grupo-body">
                                <div class="grupo-detail"><i class="far fa-clock"></i><span>Turno <strong><?php echo htmlspecialchars($agrupado['turno']); ?></strong></span></div>
                                <div class="grupo-detail" style="color: var(--ink);"><i class="fas fa-layer-group"></i><span><strong><?php echo $agrupado['total_grupos']; ?></strong> grupos en total</span></div>
                                <div class="grupo-detail highlight"><i class="fas fa-users"></i><span><strong><?php echo $agrupado['total_alumnos']; ?></strong> alumnos en total</span></div>
                            </div>
                            <div class="card-actions">
                                <!-- BOTÓN MODIFICADO PARA QUE SEA VERDE EN LUGAR DE NEGRO (var(--ink)) -->
                                <a href="ver_semestres.php?semestre=<?php echo urlencode($agrupado['semestre']); ?>&turno=<?php echo urlencode($agrupado['turno']); ?>" class="btn-ver" style="width: 100%; background: var(--green, #16a05d); color: white;"><i class="fas fa-eye"></i> Ver Grupos Internos</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-groups-page"><h2>Aún no hay grupos registrados</h2><a href="agregar_grupo.php" class="btn-add-group"><i class="fas fa-plus"></i> Crear el primer grupo</a></div>
            <?php endif; ?>
        </section>
    </main>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar'), overlay = document.getElementById('sidebarOverlay'), mobileMenu = document.getElementById('mobileMenu'), collapseToggle = document.getElementById('collapseToggle'), body = document.body;
            if (localStorage.getItem('sidebarCollapsed') === 'true') body.classList.add('sidebar-collapsed');
            if (collapseToggle) collapseToggle.addEventListener('click', () => { body.classList.toggle('sidebar-collapsed'); localStorage.setItem('sidebarCollapsed', body.classList.contains('sidebar-collapsed')); });
            function abrirMenu() { if(sidebar) sidebar.classList.add('mobile-open'); if(overlay) overlay.classList.add('visible'); body.classList.add('menu-open'); }
            function cerrarMenu() { if(sidebar) sidebar.classList.remove('mobile-open'); if(overlay) overlay.classList.remove('visible'); body.classList.remove('menu-open'); }
            if(mobileMenu) mobileMenu.addEventListener('click', abrirMenu);
            if(overlay) overlay.addEventListener('click', cerrarMenu);
            window.addEventListener('resize', () => { if (window.innerWidth > 900) cerrarMenu(); });

            const buscadorGrupos = document.getElementById('buscadorGrupos'), filtroTurno = document.getElementById('filtroTurno'), toggleVista = document.getElementById('toggleVista'), vistaDetallada = document.getElementById('vistaDetallada'), vistaAgrupada = document.getElementById('vistaAgrupada');
            function filtrarTarjetas(origen = '') {
                let termino = buscadorGrupos ? buscadorGrupos.value.toLowerCase().replace(/["'\s]/g, '') : '', turnoSeleccionado = filtroTurno ? filtroTurno.value.toLowerCase() : '';
                if (origen === 'buscador' && termino !== '' && toggleVista && toggleVista.checked) {
                    toggleVista.checked = false; if(vistaDetallada) vistaDetallada.style.display = 'grid'; if(vistaAgrupada) vistaAgrupada.style.display = 'none'; localStorage.setItem('vistaAgrupada', 'false');
                }
                document.querySelectorAll('.card-grupo').forEach(tarjeta => {
                    let contenidoLimpio = tarjeta.textContent.toLowerCase().replace(/["'\s]/g, '');
                    let coincideTexto = contenidoLimpio.includes(termino), coincideTurno = turnoSeleccionado !== '' ? tarjeta.textContent.toLowerCase().includes(turnoSeleccionado) : true;
                    tarjeta.style.display = (coincideTexto && coincideTurno) ? '' : 'none';
                });
            }
            if (toggleVista) {
                if (localStorage.getItem('vistaAgrupada') === 'true') { toggleVista.checked = true; if(vistaDetallada) vistaDetallada.style.display = 'none'; if(vistaAgrupada) vistaAgrupada.style.display = 'grid'; }
                toggleVista.addEventListener('change', (e) => {
                    if (e.target.checked) {
                        if(vistaDetallada) vistaDetallada.style.display = 'none'; if(vistaAgrupada) vistaAgrupada.style.display = 'grid'; localStorage.setItem('vistaAgrupada', 'true');
                        if (buscadorGrupos) buscadorGrupos.value = ''; filtrarTarjetas('toggle');
                    } else {
                        if(vistaDetallada) vistaDetallada.style.display = 'grid'; if(vistaAgrupada) vistaAgrupada.style.display = 'none'; localStorage.setItem('vistaAgrupada', 'false');
                    }
                });
            }
            if (buscadorGrupos) buscadorGrupos.addEventListener('input', () => filtrarTarjetas('buscador'));
            if (filtroTurno) filtroTurno.addEventListener('change', () => filtrarTarjetas('filtro'));
            filtrarTarjetas('inicio');
        });
    </script>
</body>
</html>