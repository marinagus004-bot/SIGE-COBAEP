<?php
session_start();
require_once '../config/database.php';
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../login.php");
    exit();
}
if (!isset($_GET['semestre']) || !isset($_GET['turno'])) {
    header("Location: gestion_grupos.php");
    exit();
}
$semestre_filtro = $_GET['semestre'];
$turno_filtro = $_GET['turno'];
try {
    $sql = "SELECT g.id_grupo, g.semestre, g.nombre_grupo, g.turno, (SELECT COUNT(*) FROM GRUPO_ALUMNO ga WHERE ga.id_grupo = g.id_grupo AND ga.fecha_baja IS NULL) AS total_alumnos FROM GRUPOS g WHERE g.estatus = 'Activo' AND g.semestre = :semestre AND g.turno = :turno ORDER BY g.nombre_grupo ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':semestre' => $semestre_filtro, ':turno' => $turno_filtro]);
    $grupos = $stmt->fetchAll();
} catch (PDOException $e) { die("Error al cargar grupos: " . $e->getMessage()); }
$nombre_usuario = isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') : 'Administrador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#063f2b">
    <title>Grupos <?php echo htmlspecialchars($semestre_filtro); ?> <?php echo htmlspecialchars($turno_filtro); ?> · COBAEP Plantel 27</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="style_dashboard_admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="style-gestionar-grupos.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="style_sidebar.css">
    <style>
        .btn-back{display:inline-flex;align-items:center;gap:8px;color:var(--muted,#6d7f77);text-decoration:none;font-weight:600;font-size:0.9rem;margin-bottom:15px;transition:color 0.2s;}
        .btn-back:hover{color:var(--green,#16a05d);}
        .btn-back i{background:#f1f5f9;width:32px;height:32px;display:grid;place-items:center;border-radius:50%;transition:background 0.2s;}
        .btn-back:hover i{background:var(--green-soft,#e9f8f0);}
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu" id="mobileMenu" type="button"><i class="fas fa-bars"></i></button>
                <div>
                    <a href="gestion_grupos.php" class="btn-back"><i class="fas fa-arrow-left"></i> Volver a Gestión de Grupos</a>
                    <h1 class="page-title">Detalle de Semestre <?php echo htmlspecialchars($semestre_filtro); ?> <span style="color:var(--muted); font-size:1.2rem; font-weight:500;">(Turno <?php echo htmlspecialchars($turno_filtro); ?>)</span></h1>
                </div>
            </div>
            <div class="topbar-right">
                <div class="date-pill"><i class="far fa-calendar"></i><span id="fechaTexto"><?php echo date('d/m/Y H:i a'); ?></span></div>
                <div class="profile pill-style"><div class="profile-avatar"><i class="fas fa-user"></i></div><div class="profile-info"><strong><?php echo $nombre_usuario; ?></strong><span>Administrador</span></div></div>
            </div>
        </header>
        <section class="section">
            <?php if(count($grupos) > 0): ?>
                <div class="grupos-header">
                    <div class="grupos-info">
                        <div class="info-icon" <?php echo ($turno_filtro === 'Intermedio') ? 'style="background: #ffedd5; color: #f59e0b;"' : ''; ?>><i class="fas fa-folder-open"></i></div>
                        <div><strong>Grupos Internos</strong><span>Total: <?php echo count($grupos); ?> grupos encontrados</span></div>
                    </div>
                    <div style="display:flex; gap:15px; align-items:center; flex-wrap:wrap;">
                        <div class="search-container"><i class="fas fa-search search-icon"></i><input type="text" id="buscadorGrupos" class="search-input" placeholder="Buscar grupo (ej. 1D)..."></div>
                        <select id="filtroTurno" class="search-select"><option value="">Todos los turnos</option><option value="matutino">Matutino</option><option value="intermedio">Intermedio</option></select>
                        <a href="agregar_grupo.php?semestre=<?php echo urlencode($semestre_filtro); ?>&turno=<?php echo urlencode($turno_filtro); ?>" class="btn-add-group"><i class="fas fa-plus"></i> Añadir a esta carpeta</a>
                    </div>
                </div>
                <div class="grid-grupos">
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
                                <a href="ver_grupo.php?id=<?php echo $g['id_grupo']; ?>" class="btn-ver" style="background:#e9f8f0; color:#16a05d;"><i class="fas fa-users-cog"></i> Gestionar</a>
                                <a href="editar_grupo.php?id=<?php echo $g['id_grupo']; ?>" class="btn-ver" style="background:#fff6e6; color:#d97706;" title="Editar grupo"><i class="fas fa-pen"></i> Editar</a>
                                <a href="../procesos/eliminar_grupo.php?id=<?php echo $g['id_grupo']; ?>&redirect=ver_semestres&semestre=<?php echo urlencode($semestre_filtro); ?>&turno=<?php echo urlencode($turno_filtro); ?>" class="btn-eliminar" onclick="return confirm('¿Estás seguro de eliminar este grupo?');"><i class="fas fa-trash"></i></a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-groups-page">
                    <h2>No se encontraron grupos</h2><p class="empty-description">Al parecer no hay grupos activos registrados para el semestre <?php echo htmlspecialchars($semestre_filtro); ?> en el turno <?php echo htmlspecialchars($turno_filtro); ?>.</p>
                    <a href="gestion_grupos.php" class="btn-add-group" style="background:#f1f5f9; color:var(--ink);"><i class="fas fa-arrow-left"></i> Volver a la lista general</a>
                </div>
            <?php endif; ?>
        </section>
    </main>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar'), overlay = document.getElementById('sidebarOverlay'), mobileMenu = document.getElementById('mobileMenu'), collapseToggle = document.getElementById('collapseToggle'), body = document.body;
            if (collapseToggle) collapseToggle.addEventListener('click', () => { body.classList.toggle('sidebar-collapsed'); localStorage.setItem('sidebarCollapsed', body.classList.contains('sidebar-collapsed')); });
            if (mobileMenu) mobileMenu.addEventListener('click', () => { if(sidebar) sidebar.classList.add('mobile-open'); if(overlay) overlay.classList.add('visible'); body.classList.add('menu-open'); });
            if (overlay) overlay.addEventListener('click', () => { if(sidebar) sidebar.classList.remove('mobile-open'); if(overlay) overlay.classList.remove('visible'); body.classList.remove('menu-open'); });

            const buscadorGrupos = document.getElementById('buscadorGrupos'), filtroTurno = document.getElementById('filtroTurno'), toggleVista = document.getElementById('toggleVista'), vistaDetallada = document.getElementById('vistaDetallada'), vistaAgrupada = document.getElementById('vistaAgrupada');
            function filtrarTarjetas(origen = '') {
                let termino = buscadorGrupos ? buscadorGrupos.value.toLowerCase().replace(/["'\s]/g, '') : '', turnoSeleccionado = filtroTurno ? filtroTurno.value.toLowerCase() : '';
                if (origen === 'buscador' && termino !== '' && toggleVista && toggleVista.checked) {
                    toggleVista.checked = false; if(vistaDetallada) vistaDetallada.style.display = 'grid'; if(vistaAgrupada) vistaAgrupada.style.display = 'none'; localStorage.setItem('vistaAgrupada', 'false');
                }
                document.querySelectorAll('.card-grupo').forEach(tarjeta => {
                    let contenidoOriginal = tarjeta.textContent.toLowerCase(), contenidoLimpio = contenidoOriginal.replace(/["'\s]/g, '');
                    let coincideTexto = contenidoLimpio.includes(termino), coincideTurno = turnoSeleccionado !== '' ? contenidoOriginal.includes(turnoSeleccionado) : true;
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