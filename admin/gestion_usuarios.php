<?php
session_start();
require_once '../config/database.php';

// Verificación de sesión y rol
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../login.php");
    exit();
}

$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'maestros';
$registros = [];
$generaciones_unicas = [];

try {
    if ($tipo == 'admin') {
        $stmt = $pdo->query("SELECT id_admin AS id, usuario AS identificador, CONCAT_WS(' ', nombre, apellido_paterno, apellido_materno) AS nombre_completo, 'Administrativo' AS rol FROM ADMINISTRATIVOS ORDER BY nombre ASC");
        $registros = $stmt->fetchAll();

    } elseif ($tipo == 'alumnos') {
        // Consulta avanzada para Alumnos (incluye cruce con tabla grupos)
        $sql = "SELECT 
                    a.id_alumno AS id, 
                    a.matricula AS identificador, 
                    CONCAT_WS(' ', a.nombre, a.apellido_paterno, a.apellido_materno) AS nombre_completo, 
                    'Alumno' AS rol,
                    a.periodo_ingreso,
                    a.semestre,
                    a.turno,
                    g.nombre_grupo AS grupo
                FROM ALUMNOS a
                LEFT JOIN GRUPO_ALUMNO ga ON a.id_alumno = ga.id_alumno AND ga.fecha_baja IS NULL
                LEFT JOIN GRUPOS g ON ga.id_grupo = g.id_grupo
                ORDER BY a.apellido_paterno ASC, a.nombre ASC";
        $stmt = $pdo->query($sql);
        $registros = $stmt->fetchAll();

        // Extraer las generaciones (años) dinámicamente del periodo o matrícula
        foreach ($registros as $row) {
            $texto_anio = !empty($row['periodo_ingreso']) ? $row['periodo_ingreso'] : $row['identificador'];
            // Buscamos cualquier año que empiece con 20 (ej. 2023, 2025, 2027)
            if (preg_match('/(20\d{2})/', $texto_anio, $matches)) {
                $generaciones_unicas[$matches[1]] = $matches[1];
            }
        }
        // Ordenamos los años de mayor a menor
        rsort($generaciones_unicas);

    } elseif ($tipo == 'prefectos') {
        $stmt = $pdo->query("SELECT id_prefecto AS id, usuario AS identificador, CONCAT_WS(' ', nombre, apellido_paterno, apellido_materno) AS nombre_completo, 'Prefecto' AS rol FROM PREFECTOS ORDER BY nombre ASC");
        $registros = $stmt->fetchAll();

    } else {
        $tipo = 'maestros'; 
        $stmt = $pdo->query("SELECT id_maestro AS id, usuario AS identificador, CONCAT_WS(' ', nombre, apellido_paterno, apellido_materno) AS nombre_completo, 'Docente' AS rol FROM MAESTROS ORDER BY nombre ASC");
        $registros = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    die("Error de BD: " . $e->getMessage());
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
    <title>Directorio de Usuarios · COBAEP Plantel 27</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <link rel="stylesheet" href="style_dashboard_admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="style-gestion-usuarios.css?v=<?php echo time(); ?>">
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
                <a href="gestion_grupos.php" class="nav-item">
                    <i class="fas fa-users-rectangle"></i>
                    <span>Gestión de Grupos</span>
                </a>

                <a href="gestion_horarios.php" class="nav-item">
                    <i class="fas fa-calendar-days"></i>
                    <span>Gestión de Horarios</span>
                </a>
                

                <span class="nav-heading">Personal y Alumnado</span>
                <a href="gestion_usuarios.php" class="nav-item active">
                    <i class="fas fa-users"></i>
                    <span>Directorio de Usuarios</span>
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
    <main class="main-content layout-directorio">
        
        <button class="mobile-menu" id="mobileMenu" type="button" aria-label="Abrir menú" style="margin-bottom: 20px;">
            <i class="fas fa-bars"></i>
        </button>

        <div class="page-header-title">
            <div class="header-icon-large">
                <i class="fas fa-user-group"></i>
            </div>
            <h1>Directorio de Usuarios</h1>
        </div>

        <div class="header-pills-row">
            
            <!-- Píldoras de información (Izquierda) -->
            <div class="header-pills-left">
                <div class="pill">
                    <i class="far fa-calendar"></i>
                    <span id="fechaTexto"><?php echo date('d/m/Y, h:i a'); ?></span>
                </div>
                <div class="pill">
                    <i class="fas fa-user"></i>
                    <span><?php echo $nombre_usuario; ?></span>
                </div>
                <div class="pill">
                    <i class="fas fa-users"></i>
                    <span id="contadorRegistros">Total: <?php echo count($registros); ?> registros en esta categoría</span>
                </div>
            </div>
            
            <!-- Grupo de Botones de Acción (Derecha) -->
            <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                
                <button href="importar_alumnos.php" type="button" class="btn-primary-add" style="background: #0d9488; border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(13, 148, 136, 0.2);" onclick="abrirModalImportar()">
                    <i class="fas fa-file-import"></i> Importar Lista
                </button>

                <a href="agregar_usuario.php" class="btn-primary-add">
                    <i class="fas fa-plus"></i> Nuevo Registro
                </a>
                
            </div>

        </div>

        
        <!-- Pestañas -->
        <div class="tabs-container">
            <a href="?tipo=maestros" class="tab <?php echo $tipo == 'maestros' ? 'active' : ''; ?>">
                <i class="fas fa-chalkboard-teacher"></i> Docentes
            </a>
            <a href="?tipo=admin" class="tab <?php echo $tipo == 'admin' ? 'active' : ''; ?>">
                <i class="fas fa-briefcase"></i> Administrativos
            </a>
            <a href="?tipo=prefectos" class="tab <?php echo $tipo == 'prefectos' ? 'active' : ''; ?>">
                <i class="fas fa-shield-halved"></i> Prefectos
            </a>
            <a href="?tipo=alumnos" class="tab <?php echo $tipo == 'alumnos' ? 'active' : ''; ?>">
                <i class="fas fa-user-graduate"></i> Alumnos
            </a>
        </div>

        <div class="table-card">
            <div class="table-toolbar">
                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="buscadorGeneral" placeholder="Buscar por <?php echo ($tipo == 'alumnos') ? 'matrícula' : 'usuario'; ?> o nombre...">
                </div>
                
                <div class="filters-container">
                    <?php if($tipo == 'alumnos'): ?>
                        <!-- FILTRO GENERACIÓN DINÁMICO -->
                        <div class="filter-wrapper">
                            <i class="fas fa-calendar-alt"></i>
                            <select id="filtroGeneracion">
                                <option value="">Generación</option>
                                <?php foreach($generaciones_unicas as $anio): ?>
                                    <option value="<?php echo htmlspecialchars($anio); ?>"><?php echo htmlspecialchars($anio); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-wrapper">
                            <i class="fas fa-layer-group"></i>
                            <select id="filtroSemestre">
                                <option value="">Semestre</option>
                                <option value="1">1er Semestre</option>
                                <option value="2">2do Semestre</option>
                                <option value="3">3er Semestre</option>
                                <option value="4">4to Semestre</option>
                                <option value="5">5to Semestre</option>
                                <option value="6">6to Semestre</option>
                            </select>
                        </div>
                        <div class="filter-wrapper">
                            <i class="fas fa-users-rectangle"></i>
                            <select id="filtroGrupo">
                                <option value="">Grupo</option>
                                <option value="A">Grupo A</option>
                                <option value="B">Grupo B</option>
                                <option value="C">Grupo C</option>
                                <option value="D">Grupo D</option>
                                <option value="Sin Grupo">Sin Grupo</option>
                            </select>
                        </div>
                        <div class="filter-wrapper">
                            <i class="fas fa-clock"></i>
                            <select id="filtroTurno">
                                <option value="">Turno</option>
                                <option value="Matutino">Matutino</option>
                                <option value="Intermedio">Intermedio</option>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th><?php echo ($tipo == 'alumnos') ? 'MATRÍCULA' : 'USUARIO (LOGIN)'; ?></th>
                            <th>NOMBRE COMPLETO</th>
                            <th>ROL</th>
                            <th>ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody id="cuerpoTabla">
                        <?php if(count($registros) > 0): ?>
                            <?php foreach($registros as $row): 
                                $words = explode(" ", trim($row['nombre_completo']));
                                $initials = mb_substr($words[0], 0, 1, "UTF-8");
                                if(count($words) > 1) {
                                    $initials .= mb_substr($words[1], 0, 1, "UTF-8");
                                }
                                $initials = strtoupper($initials);

                                // Preparamos variables de datos para el filtrado en tiempo real
                                $data_generacion = htmlspecialchars($row['periodo_ingreso'] ?? $row['identificador']);
                                $data_semestre = htmlspecialchars($row['semestre'] ?? '');
                                $data_grupo = htmlspecialchars($row['grupo'] ?? 'Sin Grupo');
                                $data_turno = htmlspecialchars($row['turno'] ?? '');
                            ?>
                                <tr 
                                    class="fila-usuario"
                                    data-generacion="<?php echo strtolower($data_generacion); ?>"
                                    data-semestre="<?php echo $data_semestre; ?>"
                                    data-grupo="<?php echo strtolower($data_grupo); ?>"
                                    data-turno="<?php echo strtolower($data_turno); ?>"
                                >
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-avatar <?php echo strtolower($row['rol']); ?>-avatar"><?php echo htmlspecialchars($initials); ?></div>
                                            <div class="user-info">
                                                <strong><?php echo htmlspecialchars($row['identificador']); ?></strong>
                                                <span><?php echo ($tipo == 'alumnos') ? 'Estudiante' : 'Usuario del sistema'; ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-secondary"><?php echo htmlspecialchars($row['nombre_completo']); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge-role <?php echo strtolower($row['rol']); ?>">
                                            <?php echo htmlspecialchars($row['rol']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="editar_usuario.php?id=<?php echo $row['id']; ?>&tipo=<?php echo $tipo; ?>" class="btn-action edit">
                                                <i class="fas fa-pen"></i> Editar
                                            </a>
                                            <a href="../procesos/eliminar_usuario.php?id=<?php echo $row['id']; ?>&tipo=<?php echo $tipo; ?>" 
                                               class="btn-action delete" 
                                               onclick="return confirm('¿Seguro que deseas eliminar este registro?');">
                                                <i class="fas fa-trash"></i> Eliminar
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr id="estadoVacioFijo">
                                <td colspan="4">
                                    <div class="empty-state">
                                        <i class="fas fa-user-slash" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 15px; display: block; text-align: center;"></i>
                                        <p style="text-align: center; color: #64748b;">No hay registros en esta categoría.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                        <!-- Mensaje de sin resultados para el JS -->
                        <tr id="mensajeSinResultadosJS" style="display: none;">
                            <td colspan="4">
                                <div class="empty-state">
                                    <i class="fas fa-search-minus" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 15px; display: block; text-align: center;"></i>
                                    <p style="text-align: center; color: #64748b;">No se encontraron usuarios con esos filtros.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bottom-banner">
            <div class="banner-icon-bg">
                <i class="fas fa-magnifying-glass search-icon"></i>
                <i class="fas fa-users users-icon"></i>
                <div class="plus-sparkle">+</div>
            </div>
            <div class="banner-content">
                <h3>Gestiona tu comunidad</h3>
                <p>Administra docentes, personal y alumnos desde un solo lugar.<br>Filtra por generación o grupo para facilitar la asignación académica.</p>
            </div>
        </div>

    </main>

<!-- MODAL DE IMPORTACIÓN MASIVA (CORREGIDO) -->
    <div id="modalImportar" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(3, 43, 30, 0.6); z-index: 3000; align-items: center; justify-content: center; backdrop-filter: blur(4px); opacity: 0; transition: opacity 0.3s ease;">
        
        <div id="modalImportarContent" style="background: #ffffff; padding: 30px; border-radius: 16px; max-width: 500px; width: 90%; box-shadow: 0 24px 60px rgba(0,0,0,0.2); transform: translateY(20px); transition: transform 0.3s ease;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0; color: #111827; font-size: 1.3rem;"><i class="fas fa-file-import" style="color: #0d9488; margin-right: 8px;"></i> Importar Alumnos</h2>
                <button type="button" onclick="cerrarModalImportar()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280; padding: 0;">&times;</button>
            </div>
            
            <div style="background: #f0fdf4; border-left: 4px solid #22c55e; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                <p style="margin: 0; font-size: 0.85rem; color: #166534; line-height: 1.5;">
                    <strong>Paso previo:</strong> Abre tu lista en Excel y guárdala como <strong>"CSV (delimitado por comas)"</strong>. <br><br>
                    El sistema ignorará los encabezados, evitará duplicados por matrícula, separará los nombres y asignará la contraseña temporal <strong>Cobaep2026!</strong>
                </p>
            </div>

            <form action="../procesos/importar_alumnos.php" method="POST" enctype="multipart/form-data">
                <input type="file" name="archivo_csv" accept=".csv" required style="width: 100%; padding: 12px; border: 2px dashed #cbd5e1; border-radius: 8px; margin-bottom: 20px; background: #f8fafc; cursor: pointer; box-sizing: border-box; color: #4b5563;">
                
                <button type="submit" style="width: 100%; padding: 14px; border-radius: 12px; background: #0d9488; color: white; border: none; font-weight: 600; font-size: 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 12px rgba(13, 148, 136, 0.2); transition: transform 0.2s, background 0.2s;">
                    <i class="fas fa-upload"></i> Analizar y Cargar Datos
                </button>
            </form>

        </div>
    </div>

    <!-- SCRIPT DEL MODAL (Evita conflictos con otros JS) -->
    <script>
        function abrirModalImportar() {
            const modal = document.getElementById('modalImportar');
            const content = document.getElementById('modalImportarContent');
            
            // 1. Lo hacemos visible (display: flex)
            modal.style.display = 'flex';
            
            // 2. Un pequeñísimo retraso para que aplique la transición suave
            setTimeout(() => {
                modal.style.opacity = '1';
                content.style.transform = 'translateY(0)';
            }, 10);
        }

        function cerrarModalImportar() {
            const modal = document.getElementById('modalImportar');
            const content = document.getElementById('modalImportarContent');
            
            // 1. Animamos hacia afuera
            modal.style.opacity = '0';
            content.style.transform = 'translateY(20px)';
            
            // 2. Esperamos que acabe la animación (300ms) para ocultarlo del todo
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            
            /* =====================================================
               UI MENÚ LATERAL Y HORA
               ===================================================== */
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


            /* =====================================================
               LÓGICA DE BÚSQUEDA Y FILTROS EN TIEMPO REAL
            ===================================================== */
            const searchInput = document.getElementById('buscadorGeneral');
            const selectGeneracion = document.getElementById('filtroGeneracion');
            const selectSemestre = document.getElementById('filtroSemestre');
            const selectGrupo = document.getElementById('filtroGrupo');
            const selectTurno = document.getElementById('filtroTurno');
            
            const tableRows = document.querySelectorAll('.fila-usuario');
            const mensajeSinResultadosJS = document.getElementById('mensajeSinResultadosJS');
            const contadorTexto = document.getElementById('contadorRegistros');

            function aplicarFiltros() {
                if (tableRows.length === 0) return; // Si está completamente vacío desde BD, no hacemos nada

                const txtBusqueda = searchInput ? searchInput.value.toLowerCase() : '';
                const valGeneracion = selectGeneracion ? selectGeneracion.value.toLowerCase() : '';
                const valSemestre = selectSemestre ? selectSemestre.value : '';
                const valGrupo = selectGrupo ? selectGrupo.value.toLowerCase() : '';
                const valTurno = selectTurno ? selectTurno.value.toLowerCase() : '';

                let filasVisibles = 0;

                tableRows.forEach(row => {
                    const dataGen = row.getAttribute('data-generacion');
                    const dataSem = row.getAttribute('data-semestre');
                    const dataGru = row.getAttribute('data-grupo');
                    const dataTur = row.getAttribute('data-turno');
                    const textoFila = row.textContent.toLowerCase(); 

                    // Evaluaciones de los filtros
                    const coincideBusqueda = textoFila.includes(txtBusqueda);
                    const coincideGeneracion = valGeneracion === '' || dataGen.includes(valGeneracion);
                    const coincideSemestre = valSemestre === '' || dataSem === valSemestre;
                    // Para evitar conflictos exactos, si elige "Sin Grupo" busca string literal
                    const coincideGrupo = valGrupo === '' || (valGrupo === 'sin grupo' ? dataGru === 'sin grupo' : dataGru === valGrupo);
                    const coincideTurno = valTurno === '' || dataTur === valTurno;

                    if (coincideBusqueda && coincideGeneracion && coincideSemestre && coincideGrupo && coincideTurno) {
                        row.style.display = '';
                        filasVisibles++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                // Mostrar mensaje de "No hay resultados" si es necesario
                if (filasVisibles === 0) {
                    mensajeSinResultadosJS.style.display = '';
                } else {
                    mensajeSinResultadosJS.style.display = 'none';
                }

                // Actualizar el contador del banner superior
                if (contadorTexto) {
                    contadorTexto.textContent = `Mostrando ${filasVisibles} registro(s)`;
                }
            }

            if (searchInput) searchInput.addEventListener('keyup', aplicarFiltros);
            if (selectGeneracion) selectGeneracion.addEventListener('change', aplicarFiltros);
            if (selectSemestre) selectSemestre.addEventListener('change', aplicarFiltros);
            if (selectGrupo) selectGrupo.addEventListener('change', aplicarFiltros);
            if (selectTurno) selectTurno.addEventListener('change', aplicarFiltros);
        });
    </script>
</body>
</html>