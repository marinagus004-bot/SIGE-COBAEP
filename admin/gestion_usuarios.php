<?php
session_start();
require_once '../config/database.php';
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../login.php");
    exit();
}
$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'maestros';
$registros = []; $generaciones_unicas = [];
try {
    if ($tipo == 'admin') {
        $stmt = $pdo->query("SELECT id_admin AS id, usuario AS identificador, CONCAT_WS(' ', nombre, apellido_paterno, apellido_materno) AS nombre_completo, 'Administrativo' AS rol FROM ADMINISTRATIVOS ORDER BY nombre ASC");
        $registros = $stmt->fetchAll();
    } elseif ($tipo == 'alumnos') {
        $sql = "SELECT a.id_alumno AS id, a.matricula AS identificador, CONCAT_WS(' ', a.nombre, a.apellido_paterno, a.apellido_materno) AS nombre_completo, 'Alumno' AS rol, a.periodo_ingreso, a.semestre, a.turno, g.nombre_grupo AS grupo FROM ALUMNOS a LEFT JOIN GRUPO_ALUMNO ga ON a.id_alumno = ga.id_alumno AND ga.fecha_baja IS NULL LEFT JOIN GRUPOS g ON ga.id_grupo = g.id_grupo ORDER BY a.apellido_paterno ASC, a.nombre ASC";
        $stmt = $pdo->query($sql);
        $registros = $stmt->fetchAll();
        foreach ($registros as $row) {
            $texto_anio = !empty($row['periodo_ingreso']) ? $row['periodo_ingreso'] : $row['identificador'];
            if (preg_match('/(20\d{2})/', $texto_anio, $matches)) $generaciones_unicas[$matches[1]] = $matches[1];
        }
        rsort($generaciones_unicas);
    } elseif ($tipo == 'prefectos') {
        $stmt = $pdo->query("SELECT id_prefecto AS id, usuario AS identificador, CONCAT_WS(' ', nombre, apellido_paterno, apellido_materno) AS nombre_completo, 'Prefecto' AS rol FROM PREFECTOS ORDER BY nombre ASC");
        $registros = $stmt->fetchAll();
    } else {
        $tipo = 'maestros'; 
        $stmt = $pdo->query("SELECT id_maestro AS id, usuario AS identificador, CONCAT_WS(' ', nombre, apellido_paterno, apellido_materno) AS nombre_completo, 'Docente' AS rol FROM MAESTROS ORDER BY nombre ASC");
        $registros = $stmt->fetchAll();
    }
} catch (PDOException $e) { die("Error de BD: " . $e->getMessage()); }
$nombre_usuario = isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') : 'Administrador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#063f2b">
    <title>Directorio Para la Gestion de Usuarios · COBAEP Plantel 27</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="style_dashboard_admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="style-gestion-usuarios.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="style_sidebar.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <main class="main-content layout-directorio">
        <button class="mobile-menu" id="mobileMenu" type="button" aria-label="Abrir menú" style="margin-bottom: 20px;"><i class="fas fa-bars"></i></button>
        <div class="page-header-title"><div class="header-icon-large"><i class="fas fa-user-group"></i></div><h1>Directorio de Usuarios</h1></div>
        <div class="header-pills-row">
            <div class="header-pills-left">
                <div class="pill"><i class="far fa-calendar"></i><span id="fechaTexto"><?php echo date('d/m/Y, h:i a'); ?></span></div>
                <div class="pill"><i class="fas fa-user"></i><span><?php echo $nombre_usuario; ?></span></div>
                <div class="pill"><i class="fas fa-users"></i><span id="contadorRegistros">Total: <?php echo count($registros); ?> registros en esta categoría</span></div>
            </div>
            <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <button type="button" class="btn-primary-add" style="background:#0d9488; border:none; cursor:pointer; box-shadow:0 4px 12px rgba(13,148,136,0.2);" onclick="abrirModalImportar()"><i class="fas fa-file-import"></i> Importar Lista</button>
                <a href="agregar_usuario.php" class="btn-primary-add"><i class="fas fa-plus"></i> Nuevo Registro</a>
            </div>
        </div>
        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'import_success'): ?>
            <div class="alert alert-success" style="background:#ecfdf5; color:#065f46; padding:20px; border-radius:12px; margin-top:15px; margin-bottom:25px; border:1px solid #a7f3d0; box-shadow:0 4px 6px -1px rgba(0,0,0,0.05);">
                <div style="display:flex; align-items:center; margin-bottom:10px;"><i class="fas fa-check-circle" style="font-size:1.5rem; margin-right:10px;"></i><h3 style="margin:0; font-size:1.1rem; font-weight:700;">Importación masiva finalizada con éxito</h3></div>
                <ul style="margin:0; padding-left:35px; list-style-type:none; line-height:1.6; font-size:0.95rem;">
                    <li><i class="fas fa-user-check" style="width:20px; color:#10b981;"></i> <strong><?php echo htmlspecialchars($_GET['reg'] ?? 0); ?></strong> alumnos registrados e inscritos.</li>
                    <?php if(isset($_GET['grp']) && $_GET['grp'] > 0): ?><li><i class="fas fa-users-rectangle" style="width:20px; color:#059669;"></i> <strong><?php echo htmlspecialchars($_GET['grp']); ?></strong> grupos nuevos creados.</li><?php endif; ?>
                    <?php if(isset($_GET['dup']) && $_GET['dup'] > 0): ?><li><i class="fas fa-exclamation-triangle" style="width:20px; color:#d97706;"></i> <strong><?php echo htmlspecialchars($_GET['dup']); ?></strong> alumnos omitidos (ya existía).</li><?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'import_error'): ?>
            <div class="alert alert-error" style="background:#fef2f2; color:#991b1b; padding:15px; border-radius:12px; margin-top:15px; margin-bottom:25px; border:1px solid #fecaca;"><i class="fas fa-times-circle" style="font-size:1.2rem; margin-right:8px;"></i> <strong>Error:</strong> No se pudo procesar el archivo CSV. Asegúrate de que el formato sea correcto.</div>
        <?php endif; ?>
        <div class="tabs-container">
           <a href="?tipo=alumnos" class="tab <?php echo $tipo == 'alumnos' ? 'active' : ''; ?>"><i class="fas fa-user-graduate"></i> Alumnos</a>
           <a href="?tipo=maestros" class="tab <?php echo $tipo == 'maestros' ? 'active' : ''; ?>"><i class="fas fa-chalkboard-teacher"></i> Docentes</a>
           <a href="?tipo=prefectos" class="tab <?php echo $tipo == 'prefectos' ? 'active' : ''; ?>"><i class="fas fa-shield-halved"></i> Prefectos</a>
           <a href="?tipo=admin" class="tab <?php echo $tipo == 'admin' ? 'active' : ''; ?>"><i class="fas fa-briefcase"></i> Administrativos</a>
        </div>
        <div class="table-card">
            <div class="table-toolbar">
                <div class="search-wrapper"><i class="fas fa-search"></i><input type="text" id="buscadorGeneral" placeholder="Buscar por <?php echo ($tipo == 'alumnos') ? 'matrícula' : 'usuario'; ?> o nombre..."></div>
                <div class="filters-container">
                    <?php if($tipo == 'alumnos'): ?>
                        <div class="filter-wrapper"><i class="fas fa-calendar-alt"></i><select id="filtroGeneracion"><option value="">Generación</option><?php foreach($generaciones_unicas as $anio): ?><option value="<?php echo htmlspecialchars($anio); ?>"><?php echo htmlspecialchars($anio); ?></option><?php endforeach; ?></select></div>
                        <div class="filter-wrapper"><i class="fas fa-layer-group"></i><select id="filtroSemestre"><option value="">Semestre</option><option value="1">1er Semestre</option><option value="2">2do Semestre</option><option value="3">3er Semestre</option><option value="4">4to Semestre</option><option value="5">5to Semestre</option><option value="6">6to Semestre</option></select></div>
                        <div class="filter-wrapper"><i class="fas fa-users-rectangle"></i><select id="filtroGrupo"><option value="">Grupo</option><option value="A">Grupo A</option><option value="B">Grupo B</option><option value="C">Grupo C</option><option value="D">Grupo D</option><option value="Sin Grupo">Sin Grupo</option></select></div>
                        <div class="filter-wrapper"><i class="fas fa-clock"></i><select id="filtroTurno"><option value="">Turno</option><option value="Matutino">Matutino</option><option value="Intermedio">Intermedio</option></select></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="table-responsive">
                <table class="modern-table">
                    <thead><tr><th><?php echo ($tipo == 'alumnos') ? 'MATRÍCULA' : 'USUARIO (LOGIN)'; ?></th><th>NOMBRE COMPLETO</th><th>ROL</th><th>ACCIONES</th></tr></thead>
                    <tbody id="cuerpoTabla">
                        <?php if(count($registros) > 0): ?>
                            <?php foreach($registros as $row): 
                                $words = explode(" ", trim($row['nombre_completo']));
                                $initials = mb_substr($words[0], 0, 1, "UTF-8") . (count($words) > 1 ? mb_substr($words[1], 0, 1, "UTF-8") : "");
                                $initials = strtoupper($initials);
                            ?>
                                <tr class="fila-usuario" data-generacion="<?php echo strtolower(htmlspecialchars($row['periodo_ingreso'] ?? $row['identificador'])); ?>" data-semestre="<?php echo htmlspecialchars($row['semestre'] ?? ''); ?>" data-grupo="<?php echo strtolower(htmlspecialchars($row['grupo'] ?? 'Sin Grupo')); ?>" data-turno="<?php echo strtolower(htmlspecialchars($row['turno'] ?? '')); ?>">
                                    <td><div class="user-cell"><div class="user-avatar <?php echo strtolower($row['rol']); ?>-avatar"><?php echo htmlspecialchars($initials); ?></div><div class="user-info"><strong><?php echo htmlspecialchars($row['identificador']); ?></strong><span><?php echo ($tipo == 'alumnos') ? 'Estudiante' : 'Usuario del sistema'; ?></span></div></div></td>
                                    <td><span class="text-secondary"><?php echo htmlspecialchars($row['nombre_completo']); ?></span></td>
                                    <td><span class="badge-role <?php echo strtolower($row['rol']); ?>"><?php echo htmlspecialchars($row['rol']); ?></span></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="editar_usuario.php?id=<?php echo $row['id']; ?>&tipo=<?php echo $tipo; ?>" class="btn-action edit"><i class="fas fa-pen"></i> Editar</a>
                                            <a href="../procesos/eliminar_usuario.php?id=<?php echo $row['id']; ?>&tipo=<?php echo $tipo; ?>" class="btn-action delete" onclick="return confirm('¿Seguro que deseas eliminar este registro?');"><i class="fas fa-trash"></i> Eliminar</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr id="estadoVacioFijo"><td colspan="4"><div class="empty-state"><i class="fas fa-user-slash" style="font-size:3rem; color:#cbd5e1; margin-bottom:15px; display:block; text-align:center;"></i><p style="text-align:center; color:#64748b;">No hay registros en esta categoría.</p></div></td></tr>
                        <?php endif; ?>
                        <tr id="mensajeSinResultadosJS" style="display:none;"><td colspan="4"><div class="empty-state"><i class="fas fa-search-minus" style="font-size:3rem; color:#cbd5e1; margin-bottom:15px; display:block; text-align:center;"></i><p style="text-align:center; color:#64748b;">No se encontraron usuarios con esos filtros.</p></div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="bottom-banner">
            <div class="banner-icon-bg"><i class="fas fa-magnifying-glass search-icon"></i><i class="fas fa-users users-icon"></i><div class="plus-sparkle">+</div></div>
            <div class="banner-content"><h3>Gestiona tu comunidad</h3><p>Administra docentes, personal y alumnos desde un solo lugar.<br>Filtra por generación o grupo para facilitar la asignación académica.</p></div>
        </div>
    </main>
    <div id="modalImportar" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(3,43,30,0.6); z-index:3000; align-items:center; justify-content:center; backdrop-filter:blur(4px); opacity:0; transition:opacity 0.3s ease;">
        <div id="modalImportarContent" style="background:#fff; padding:30px; border-radius:16px; max-width:500px; width:90%; box-shadow:0 24px 60px rgba(0,0,0,0.2); transform:translateY(20px); transition:transform 0.3s ease;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h2 style="margin:0; color:#111827; font-size:1.3rem;"><i class="fas fa-file-import" style="color:#0d9488; margin-right:8px;"></i> Importar Alumnos</h2>
                <button type="button" onclick="cerrarModalImportar()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#6b7280; padding:0;">&times;</button>
            </div>
            <div style="background:#f0fdf4; border-left:4px solid #22c55e; padding:15px; border-radius:6px; margin-bottom:20px;">
                <p style="margin:0; font-size:0.85rem; color:#166534; line-height:1.5;"><strong>Paso previo:</strong> Abre tu lista en Excel y guárdala como <strong>"CSV (delimitado por comas)"</strong>.<br><br>El sistema ignorará los encabezados, evitará duplicados por matrícula, separará los nombres y asignará la contraseña temporal <strong>Cobaep2026!</strong></p>
            </div>
            <form action="../procesos/importar_alumnos.php" method="POST" enctype="multipart/form-data">
                <input type="file" name="archivo_csv" accept=".csv" required style="width:100%; padding:12px; border:2px dashed #cbd5e1; border-radius:8px; margin-bottom:20px; background:#f8fafc; cursor:pointer; box-sizing:border-box; color:#4b5563;">
                <button type="submit" style="width:100%; padding:14px; border-radius:12px; background:#0d9488; color:#fff; border:none; font-weight:600; font-size:1rem; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; box-shadow:0 4px 12px rgba(13,148,136,0.2); transition:transform 0.2s, background 0.2s;"><i class="fas fa-upload"></i> Analizar y Cargar Datos</button>
            </form>
        </div>
    </div>
    <script>
        function abrirModalImportar(){ const m=document.getElementById('modalImportar'), c=document.getElementById('modalImportarContent'); m.style.display='flex'; setTimeout(()=>{ m.style.opacity='1'; c.style.transform='translateY(0)'; },10); }
        function cerrarModalImportar(){ const m=document.getElementById('modalImportar'), c=document.getElementById('modalImportarContent'); m.style.opacity='0'; c.style.transform='translateY(20px)'; setTimeout(()=>m.style.display='none',300); }
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar'), overlay = document.getElementById('sidebarOverlay'), mobileMenu = document.getElementById('mobileMenu'), collapseToggle = document.getElementById('collapseToggle'), body = document.body, fechaTexto = document.getElementById('fechaTexto');
            if (localStorage.getItem('sidebarCollapsed') === 'true') body.classList.add('sidebar-collapsed');
            if (collapseToggle) collapseToggle.addEventListener('click', () => { body.classList.toggle('sidebar-collapsed'); localStorage.setItem('sidebarCollapsed', body.classList.contains('sidebar-collapsed')); });
            function abrirMenu(){ if(sidebar) sidebar.classList.add('mobile-open'); if(overlay) overlay.classList.add('visible'); body.classList.add('menu-open'); }
            function cerrarMenu(){ if(sidebar) sidebar.classList.remove('mobile-open'); if(overlay) overlay.classList.remove('visible'); body.classList.remove('menu-open'); }
            if(mobileMenu) mobileMenu.addEventListener('click', abrirMenu);
            if(overlay) overlay.addEventListener('click', cerrarMenu);
            window.addEventListener('resize', () => { if (window.innerWidth > 900) cerrarMenu(); });
            function actualizarHora(){ if(!fechaTexto) return; fechaTexto.textContent = new Date().toLocaleString('es-MX', { day:'2-digit', month:'2-digit', year:'numeric', hour:'2-digit', minute:'2-digit' }); }
            actualizarHora(); setInterval(actualizarHora, 60000);

            const searchInput = document.getElementById('buscadorGeneral'), selectGeneracion = document.getElementById('filtroGeneracion'), selectSemestre = document.getElementById('filtroSemestre'), selectGrupo = document.getElementById('filtroGrupo'), selectTurno = document.getElementById('filtroTurno'), tableRows = document.querySelectorAll('.fila-usuario'), mensajeSinResultadosJS = document.getElementById('mensajeSinResultadosJS'), contadorTexto = document.getElementById('contadorRegistros');
            function aplicarFiltros() {
                if (tableRows.length === 0) return;
                const txtBusqueda = searchInput ? searchInput.value.toLowerCase() : '', valGeneracion = selectGeneracion ? selectGeneracion.value.toLowerCase() : '', valSemestre = selectSemestre ? selectSemestre.value : '', valGrupo = selectGrupo ? selectGrupo.value.toLowerCase() : '', valTurno = selectTurno ? selectTurno.value.toLowerCase() : '';
                let filasVisibles = 0;
                tableRows.forEach(row => {
                    const dataGen = row.getAttribute('data-generacion'), dataSem = row.getAttribute('data-semestre'), dataGru = row.getAttribute('data-grupo'), dataTur = row.getAttribute('data-turno'), textoFila = row.textContent.toLowerCase();
                    const coincideBusqueda = textoFila.includes(txtBusqueda), coincideGeneracion = valGeneracion === '' || dataGen.includes(valGeneracion), coincideSemestre = valSemestre === '' || dataSem === valSemestre, coincideGrupo = valGrupo === '' || (valGrupo === 'sin grupo' ? dataGru === 'sin grupo' : dataGru === valGrupo), coincideTurno = valTurno === '' || dataTur === valTurno;
                    if (coincideBusqueda && coincideGeneracion && coincideSemestre && coincideGrupo && coincideTurno) { row.style.display = ''; filasVisibles++; } else row.style.display = 'none';
                });
                if(mensajeSinResultadosJS) mensajeSinResultadosJS.style.display = filasVisibles === 0 ? '' : 'none';
                if(contadorTexto) contadorTexto.textContent = `Mostrando ${filasVisibles} registro(s)`;
            }
            [searchInput, selectGeneracion, selectSemestre, selectGrupo, selectTurno].forEach(el => { if(el) el.addEventListener(el.tagName === 'INPUT' ? 'keyup' : 'change', aplicarFiltros); });

            const alertas = document.querySelectorAll('.alert');
            if (alertas.length > 0) setTimeout(() => alertas.forEach(alerta => { alerta.style.transition = 'opacity 0.5s ease, transform 0.5s ease'; alerta.style.opacity = '0'; alerta.style.transform = 'translateY(-10px)'; setTimeout(() => alerta.style.display = 'none', 500); }), 5000);
        });
    </script>
</body>
</html>