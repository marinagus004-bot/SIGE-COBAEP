<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../login.php");
    exit();
}

try {
    // 1. Obtener todos los horarios registrados en HORARIOS_MAESTRO cruzando con MAESTROS y GRUPOS
    $sql = "SELECT hm.id_horario, hm.id_maestro, hm.id_grupo, hm.nombre_archivo, hm.ruta_pdf, 
                   hm.fecha_subida, hm.ciclo, hm.anio,
                   CONCAT_WS(' ', m.nombre, m.apellido_paterno, m.apellido_materno) AS docente_nombre,
                   m.usuario AS docente_usuario,
                   g.semestre, g.nombre_grupo, g.turno
            FROM HORARIOS_MAESTRO hm
            INNER JOIN MAESTROS m ON hm.id_maestro = m.id_maestro
            INNER JOIN GRUPOS g ON hm.id_grupo = g.id_grupo
            ORDER BY hm.anio DESC, hm.fecha_subida DESC";
    $horarios = $pdo->query($sql)->fetchAll();

    // 2. Catálogo de Maestros para el selector y filtro
    $maestros = $pdo->query("SELECT id_maestro, CONCAT_WS(' ', nombre, apellido_paterno, apellido_materno) AS nombre_completo, usuario, horario_archivo FROM MAESTROS ORDER BY nombre ASC")->fetchAll();

    // 3. Catálogo de Grupos para el selector
    $grupos = $pdo->query("SELECT id_grupo, semestre, nombre_grupo, turno FROM GRUPOS ORDER BY semestre, nombre_grupo ASC")->fetchAll();

} catch (PDOException $e) {
    die("Error al cargar horarios: " . $e->getMessage());
}

$nombre_usuario = isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') : 'Administrador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Horarios de Docentes · COBAEP Plantel 27</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="style_dashboard_admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="style-gestion-usuarios.css?v=<?php echo time(); ?>">
</head>
<body>

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

            <nav class="navigation">
                <span class="nav-heading">Menú principal</span>
                <a href="dashboard_admin.php" class="nav-item"><i class="fas fa-house"></i><span>Panel de Control</span></a>

                <span class="nav-heading">Académico</span>
                <a href="gestion_grupos.php" class="nav-item"><i class="fas fa-users-rectangle"></i><span>Gestión de Grupos</span></a>
                <a href="gestion_horarios.php" class="nav-item active"><i class="fas fa-calendar-days"></i><span>Horarios Docentes</span></a>

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

    <main class="main-content layout-directorio">
        <button class="mobile-menu" id="mobileMenu" type="button" style="margin-bottom: 20px;"><i class="fas fa-bars"></i></button>

        <div class="page-header-title">
            <div class="header-icon-large"><i class="fas fa-calendar-days"></i></div>
            <h1>Gestión de Horarios de Docentes</h1>
        </div>

        <div class="header-pills-row">
            <div class="header-pills-left">
                <div class="pill"><i class="fas fa-chalkboard-user"></i><span>Docentes: <?php echo count($maestros); ?></span></div>
                <div class="pill"><i class="fas fa-file-pdf"></i><span>Horarios asignados: <?php echo count($horarios); ?></span></div>
            </div>
            <button type="button" class="btn-primary-add" style="border:none; cursor:pointer;" onclick="abrirModalHorario()">
                <i class="fas fa-plus"></i> Asignar Nuevo Horario
            </button>
        </div>

        <?php if(isset($_GET['msg'])): ?>
            <div style="padding:15px 20px; border-radius:12px; margin-bottom:20px; background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; font-weight:600;">
                <i class="fas fa-check-circle"></i> Operación realizada correctamente (<?php echo htmlspecialchars($_GET['msg']); ?>).
            </div>
        <?php endif; ?>

        <div class="table-card" style="border-radius:16px;">
            <div class="table-toolbar">
                <div class="search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="buscadorHorarios" placeholder="Buscar por docente, grupo o ciclo...">
                </div>
                <div class="filters-container">
                    <div class="filter-wrapper">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <select id="filtroMaestro">
                            <option value="">Todos los Docentes</option>
                            <?php foreach($maestros as $m): ?>
                                <option value="<?php echo strtolower(htmlspecialchars($m['nombre_completo'])); ?>">
                                    <?php echo htmlspecialchars($m['nombre_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-wrapper">
                        <i class="fas fa-clock"></i>
                        <select id="filtroTurnoH">
                            <option value="">Todos los Turnos</option>
                            <option value="matutino">Matutino</option>
                            <option value="intermedio">Intermedio</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>DOCENTE</th>
                            <th>GRUPO Y TURNO</th>
                            <th>CICLO / AÑO</th>
                            <th>ARCHIVO DE HORARIO</th>
                            <th>ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($horarios) > 0): ?>
                            <?php foreach($horarios as $h): ?>
                                <tr class="fila-horario" 
                                    data-maestro="<?php echo strtolower(htmlspecialchars($h['docente_nombre'])); ?>"
                                    data-turno="<?php echo strtolower(htmlspecialchars($h['turno'])); ?>">
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-avatar docente-avatar"><i class="fas fa-user-tie"></i></div>
                                            <div class="user-info">
                                                <strong><?php echo htmlspecialchars($h['docente_nombre']); ?></strong>
                                                <span>Usuario: <?php echo htmlspecialchars($h['docente_usuario']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($h['semestre'] . '° "' . $h['nombre_grupo'] . '"'); ?></strong><br>
                                        <small style="color:#6b7280;">Turno <?php echo htmlspecialchars($h['turno']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge-role docente"><?php echo htmlspecialchars($h['ciclo'] . ' ' . $h['anio']); ?></span>
                                    </td>
                                    <td>
                                        <?php if(!empty($h['ruta_pdf'])): ?>
                                            <a href="../<?php echo htmlspecialchars($h['ruta_pdf']); ?>" target="_blank" class="btn-action edit" style="background:#ecfdf5; color:#047857;">
                                                <i class="fas fa-file-pdf"></i> Ver PDF
                                            </a>
                                        <?php else: ?>
                                            <span style="color:#9ca3af; font-size:0.85rem;"><i class="fas fa-file-circle-xmark"></i> Sin PDF adjunto</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <button type="button" class="btn-action edit" style="border:none; cursor:pointer;"
                                                onclick="editarHorario(<?php echo $h['id_horario']; ?>, <?php echo $h['id_maestro']; ?>, <?php echo $h['id_grupo']; ?>, '<?php echo $h['ciclo']; ?>', '<?php echo $h['anio']; ?>')">
                                                <i class="fas fa-pen"></i> Editar
                                            </button>
                                            <a href="../procesos/guardar_horario_admin.php?eliminar=<?php echo $h['id_horario']; ?>" 
                                               class="btn-action delete"
                                               onclick="return confirm('¿Seguro que deseas eliminar este registro de horario?');">
                                                <i class="fas fa-trash"></i> Eliminar
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align:center; padding:40px; color:#6b7280;">No hay horarios asignados todavía.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- MODAL CREAR / EDITAR HORARIO -->
    <div id="modalHorario" style="display:none; position:fixed; inset:0; background:rgba(3,43,30,0.6); z-index:3000; align-items:center; justify-content:center; backdrop-filter:blur(4px);">
        <div style="background:#fff; padding:30px; border-radius:20px; width:90%; max-width:500px; box-shadow:0 24px 60px rgba(0,0,0,0.2);">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h2 id="tituloModalHorario" style="margin:0; font-size:1.25rem;"><i class="fas fa-calendar-plus" style="color:#10b981; margin-right:8px;"></i> Asignar Horario</h2>
                <button type="button" onclick="cerrarModalHorario()" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
            </div>

            <form action="../procesos/guardar_horario_admin.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id_horario" id="h_id_horario" value="0">

                <div style="margin-bottom:15px;">
                    <label style="display:block; font-weight:700; font-size:0.85rem; margin-bottom:6px;">Docente</label>
                    <select name="id_maestro" id="h_id_maestro" required style="width:100%; padding:11px; border:1px solid #cbd5e1; border-radius:10px;">
                        <?php foreach($maestros as $m): ?>
                            <option value="<?php echo $m['id_maestro']; ?>"><?php echo htmlspecialchars($m['nombre_completo'] . ' (' . $m['usuario'] . ')'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="margin-bottom:15px;">
                    <label style="display:block; font-weight:700; font-size:0.85rem; margin-bottom:6px;">Grupo y Clase de Turno</label>
                    <select name="id_grupo" id="h_id_grupo" required style="width:100%; padding:11px; border:1px solid #cbd5e1; border-radius:10px;">
                        <?php foreach($grupos as $g): ?>
                            <option value="<?php echo $g['id_grupo']; ?>">
                                <?php echo htmlspecialchars($g['semestre'] . '° "' . $g['nombre_grupo'] . '" - Turno ' . $g['turno']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:15px;">
                    <div>
                        <label style="display:block; font-weight:700; font-size:0.85rem; margin-bottom:6px;">Ciclo Escolar</label>
                        <select name="ciclo" id="h_ciclo" required style="width:100%; padding:11px; border:1px solid #cbd5e1; border-radius:10px;">
                            <option value="Ago-Ene">Ago-Ene</option>
                            <option value="Feb-Jul">Feb-Jul</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-weight:700; font-size:0.85rem; margin-bottom:6px;">Año</label>
                        <input type="number" name="anio" id="h_anio" value="<?php echo date('Y'); ?>" min="2024" max="2035" required style="width:100%; padding:11px; border:1px solid #cbd5e1; border-radius:10px;">
                    </div>
                </div>

                <div style="margin-bottom:20px;">
                    <label style="display:block; font-weight:700; font-size:0.85rem; margin-bottom:6px;">Archivo PDF del Horario (Opcional al editar)</label>
                    <input type="file" name="archivo_pdf" accept=".pdf" style="width:100%; padding:10px; border:1px dashed #cbd5e1; border-radius:10px;">
                </div>

                <div style="display:flex; gap:10px;">
                    <button type="button" onclick="cerrarModalHorario()" style="flex:1; padding:12px; border-radius:10px; border:1px solid #cbd5e1; background:#f8fafc; font-weight:600; cursor:pointer;">Cancelar</button>
                    <button type="submit" style="flex:1; padding:12px; border-radius:10px; border:none; background:#10b981; color:#fff; font-weight:700; cursor:pointer;">Guardar Horario</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModalHorario() {
            document.getElementById('tituloModalHorario').innerHTML = '<i class="fas fa-calendar-plus" style="color:#10b981; margin-right:8px;"></i> Asignar Nuevo Horario';
            document.getElementById('h_id_horario').value = '0';
            document.getElementById('modalHorario').style.display = 'flex';
        }

        function editarHorario(id, idMaestro, idGrupo, ciclo, anio) {
            document.getElementById('tituloModalHorario').innerHTML = '<i class="fas fa-pen" style="color:#2563eb; margin-right:8px;"></i> Editar Horario';
            document.getElementById('h_id_horario').value = id;
            document.getElementById('h_id_maestro').value = idMaestro;
            document.getElementById('h_id_grupo').value = idGrupo;
            document.getElementById('h_ciclo').value = ciclo;
            document.getElementById('h_anio').value = anio;
            document.getElementById('modalHorario').style.display = 'flex';
        }

        function cerrarModalHorario() {
            document.getElementById('modalHorario').style.display = 'none';
        }

        document.addEventListener('DOMContentLoaded', () => {
            const buscador = document.getElementById('buscadorHorarios');
            const filtroM = document.getElementById('filtroMaestro');
            const filtroT = document.getElementById('filtroTurnoH');
            const filas = document.querySelectorAll('.fila-horario');

            function filtrar() {
                const q = buscador.value.toLowerCase();
                const m = filtroM.value.toLowerCase();
                const t = filtroT.value.toLowerCase();

                filas.forEach(f => {
                    const txt = f.textContent.toLowerCase();
                    const dm = f.getAttribute('data-maestro');
                    const dt = f.getAttribute('data-turno');
                    f.style.display = (txt.includes(q) && (m === '' || dm.includes(m)) && (t === '' || dt === t)) ? '' : 'none';
                });
            }

            if(buscador) buscador.addEventListener('keyup', filtrar);
            if(filtroM) filtroM.addEventListener('change', filtrar);
            if(filtroT) filtroT.addEventListener('change', filtrar);
        });
    </script>
</body>
</html>