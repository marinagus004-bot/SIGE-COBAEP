<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../login.php");
    exit();
}

$id_docente = $_SESSION['id_usuario'];
$nombre_usuario = isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') : 'Docente';
date_default_timezone_set('America/Mexico_City');
$partes_nombre = explode(" ", $nombre_usuario);
$iniciales = strtoupper(substr($partes_nombre[0], 0, 1) . (isset($partes_nombre[1]) ? substr($partes_nombre[1], 0, 1) : ''));

$id_clase = isset($_GET['id_clase']) ? (int)$_GET['id_clase'] : 0;
$id_grupo = isset($_GET['id_grupo']) ? (int)$_GET['id_grupo'] : 0;

if ($id_clase === 0 && $id_grupo === 0) {
    header("Location: mis_grupos.php?error=No se especificó el aula.");
    exit();
}

$vista_actual = ($id_clase > 0) ? 'materia' : 'grupo';

$info_cabecera = [];
$lista_alumnos = [];
$actividades = [];
$materias_del_grupo = [];
$link_actual = "";
$total_alumnos = 0;
$alumnos_activos_count = 0;

try {
    if ($vista_actual === 'materia') {
        $stmt = $pdo->prepare("SELECT c.*, m.nombre_materia, g.nombre_grupo, g.semestre, g.id_grupo FROM clases_regulares c INNER JOIN materias m ON c.id_materia = m.id_materia LEFT JOIN grupos g ON c.id_grupo = g.id_grupo WHERE c.id_clase = ? AND c.id_maestro = ?");
        $stmt->execute([$id_clase, $id_docente]);
        $info_cabecera = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$info_cabecera) { header("Location: mis_grupos.php?error=Acceso denegado."); exit(); }
        $link_actual = $info_cabecera['enlace_clase_virtual'] ?? '';

        $stmtAlumnos = $pdo->prepare("SELECT a.id_alumno, a.matricula, a.nombre, a.apellido_paterno, a.apellido_materno, cra.acceso FROM clase_regular_alumno cra INNER JOIN alumnos a ON cra.id_alumno = a.id_alumno WHERE cra.id_clase = ? ORDER BY a.apellido_paterno ASC");
        $stmtAlumnos->execute([$id_clase]);
        $lista_alumnos = $stmtAlumnos->fetchAll(PDO::FETCH_ASSOC);
        
        $total_alumnos = count($lista_alumnos);
        foreach($lista_alumnos as $al) { if($al['acceso'] == 1) $alumnos_activos_count++; }

        $stmtAct = $pdo->prepare("SELECT * FROM actividades_clase WHERE id_clase = ? ORDER BY fecha_publicacion DESC");
        $stmtAct->execute([$id_clase]);
        $actividades = $stmtAct->fetchAll(PDO::FETCH_ASSOC);

    } else {
        $stmt = $pdo->prepare("SELECT id_grupo, nombre_grupo, semestre FROM grupos WHERE id_grupo = ?");
        $stmt->execute([$id_grupo]);
        $info_cabecera = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$info_cabecera) { header("Location: mis_grupos.php?error=Grupo no encontrado."); exit(); }

        $stmtMat = $pdo->prepare("SELECT c.id_clase, m.nombre_materia, c.enlace_clase_virtual FROM clases_regulares c INNER JOIN materias m ON c.id_materia = m.id_materia WHERE c.id_grupo = ? AND c.id_maestro = ? AND c.estatus = 'Activa'");
        $stmtMat->execute([$id_grupo, $id_docente]);
        $materias_del_grupo = $stmtMat->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Aula · Portal Docente</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="style_dashboard_docente.css?v=<?php echo time(); ?>">

    <style>
        .header-aula { background: linear-gradient(145deg, var(--green-950), var(--green-900)); padding: 2rem; border-radius: 20px; color: white; display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; box-shadow: var(--shadow-md); }
        .header-aula h2 { margin: 0 0 5px 0; font-size: 1.8rem; font-weight: 800; }
        .header-aula p { margin: 0; color: #a7f3d0; font-size: 0.95rem; font-weight: 600; display: flex; align-items: center; gap: 8px;}
        .btn-volver { color:white; text-decoration:none; background:rgba(255,255,255,0.15); padding:10px 20px; border-radius:50px; font-size:0.85rem; font-weight:700; transition:0.2s; display:inline-flex; align-items:center; gap:8px;}
        .btn-volver:hover { background:rgba(255,255,255,0.25); }

        .add-materia-bar { background: white; border: 1px solid var(--line); border-radius: 16px; padding: 1.5rem; display: flex; align-items: center; justify-content: space-between; gap: 20px; margin-bottom: 2rem; box-shadow: var(--shadow-sm); }
        .add-materia-info { display: flex; align-items: center; gap: 15px; }
        .add-materia-info .icon-box { width: 45px; height: 45px; background: var(--orange-soft); color: var(--orange); border-radius: 12px; display: grid; place-items: center; font-size: 1.2rem; flex-shrink: 0; }
        .add-materia-info h4 { margin: 0 0 4px 0; color: var(--ink); font-size: 1.1rem; font-weight: 800;}
        .add-materia-info p { margin: 0; color: var(--muted); font-size: 0.85rem; }
        .add-materia-form { display: flex; gap: 10px; flex: 1; max-width: 500px; }
        .add-materia-form input { flex: 1; padding: 12px 15px; border: 1px solid #cbd5e1; border-radius: 10px; font-family: 'Inter', sans-serif; font-size: 0.95rem; outline: none; transition: 0.2s; }
        .add-materia-form input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.15); }
        .add-materia-form button { background: var(--orange); color: white; border: none; padding: 12px 25px; border-radius: 10px; font-weight: 800; cursor: pointer; transition: 0.2s; white-space: nowrap; display:flex; align-items:center; gap:8px; }
        .add-materia-form button:hover { background: #d97706; }

        .grid-materias { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; }
        .materia-card { background: white; border-radius: 16px; border: 1px solid var(--line); padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between; min-height: 160px; box-shadow: var(--shadow-sm); transition: 0.2s ease;}
        .materia-card:hover { transform: translateY(-3px); border-color: var(--green); box-shadow: var(--shadow-md); }
        .materia-card h3 { margin: 0 0 10px 0; color: var(--ink); font-size: 1.3rem; font-weight: 800;}
        .materia-card .meta { color: var(--muted); font-size: 0.85rem; margin-bottom: 15px; display:flex; align-items:center; gap:8px;}
        .btn-entrar { background: var(--green); color: white; border: none; padding: 12px; border-radius: 10px; font-weight: 700; text-align: center; text-decoration: none; width: 100%; display: flex; justify-content:center; align-items:center; gap:8px; transition: 0.2s;}
        .btn-entrar:hover { background: var(--green-800); }
        
        .layout-materia { display: grid; grid-template-columns: 1.8fr 1.2fr; gap: 1.5rem; align-items: start; }
        .card { background: white; border-radius: 16px; border: 1px solid var(--line); padding: 1.5rem; box-shadow: var(--shadow-sm); margin-bottom: 1.5rem; }
        .card-title { font-size: 1.1rem; font-weight: 800; color: var(--ink); margin: 0 0 1.2rem 0; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--line); padding-bottom: 12px; }
        
        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 700; color: var(--muted); margin-bottom: 8px; }
        .form-control { width: 100%; padding: 12px 14px; border: 1px solid #cbd5e1; border-radius: 10px; font-family: 'Inter'; font-size: 0.95rem; color: var(--ink); outline: none; transition: 0.2s; background: #f8fafc;}
        .form-control:focus { border-color: var(--green); box-shadow: 0 0 0 3px rgba(22,160,93,0.15); background: #ffffff;}
        textarea.form-control { resize: vertical; min-height: 100px; }
        
        .btn-primary { background: var(--green); color: white; border: none; padding: 14px; border-radius: 10px; font-weight: 700; width: 100%; cursor: pointer; transition: 0.2s; display:flex; align-items:center; justify-content:center; gap:8px; font-size: 0.95rem;}
        .btn-primary:hover { background: var(--green-800); box-shadow: 0 4px 12px rgba(22,160,93,0.2);}
        .btn-blue { background: #3b82f6; }
        .btn-blue:hover { background: #2563eb;}
        .btn-danger { background: #ef4444; color: white; border: none; padding: 12px; border-radius: 10px; font-weight: 700; width: 100%; cursor: pointer; margin-top: 10px; display:flex; align-items:center; justify-content:center; gap:8px; transition: 0.2s;}
        
        .act-list { display: flex; flex-direction: column; gap: 12px; }
        .act-item { border: 1px solid var(--line); border-radius: 12px; padding: 16px; display: flex; flex-direction: column; background: var(--background); transition: 0.2s;}
        .act-item:hover { border-color: #cbd5e1; background: white; box-shadow: var(--shadow-sm);}
        .act-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px;}
        .act-info h4 { margin: 0 0 6px 0; color: var(--ink); font-size: 1rem; font-weight: 700; line-height: 1.3;}
        .act-info p { margin: 0; color: var(--muted); font-size: 0.8rem; display: flex; align-items: center; gap: 5px;}
        .act-badges { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 10px;}
        .badge { font-size: 0.7rem; padding: 4px 8px; border-radius: 6px; font-weight: 700; }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-orange { background: #ffedd5; color: #9a3412; }
        .act-actions { display: flex; gap: 8px; justify-content: flex-end; border-top: 1px solid var(--line); padding-top: 10px; margin-top: 5px;}
        .btn-outline { background: white; border: 1px solid #cbd5e1; color: var(--ink); padding: 6px 12px; border-radius: 6px; font-size: 0.8rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px;}
        .btn-outline:hover { background: #f1f5f9; border-color: #94a3b8; }

        .search-box { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 10px; margin-bottom: 15px; background: white; transition: 0.2s;}
        .search-box:focus-within { border-color: var(--green); box-shadow: 0 0 0 3px rgba(22,160,93,0.1); }
        .search-box input { border: none; background: transparent; outline: none; width: 100%; font-family: 'Inter'; font-size: 0.9rem; }
        
        .lista-scroll { max-height: 380px; overflow-y: auto; padding-right: 8px; }
        .lista-scroll::-webkit-scrollbar { width: 6px; }
        .lista-scroll::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        .lista-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        
        .alumno-item { display: flex; align-items: center; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid var(--line); }
        .alumno-item:last-child { border-bottom: none; padding-bottom: 0; }
        .alumno-avatar { width: 36px; height: 36px; background: var(--blue-soft); color: var(--blue); border-radius: 50%; display: grid; place-items: center; font-size: 0.8rem; font-weight: bold; flex-shrink: 0;}
        .alumno-info strong { display: block; font-size: 0.9rem; color: var(--ink); line-height: 1.2; margin-bottom: 3px;}
        .alumno-info span { display: block; font-size: 0.75rem; color: var(--muted); }

        .switch { position: relative; display: inline-block; width: 36px; height: 20px; flex-shrink: 0;}
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 14px; width: 14px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: var(--green); }
        input:checked + .slider:before { transform: translateX(16px); }

        /* Estilo Checkbox Options */
        .check-options { display: flex; gap: 15px; flex-wrap: wrap; margin-top: 5px; }
        .check-item { display: flex; align-items: center; gap: 8px; font-size: 0.85rem; color: var(--ink); cursor: pointer; }
        .check-item input { accent-color: var(--green); width: 16px; height: 16px; cursor: pointer;}

        @media (max-width: 1024px) { .layout-materia { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { 
            .header-aula { flex-direction: column; align-items: flex-start; gap: 15px; padding: 1.5rem;} 
            .add-materia-bar { flex-direction: column; align-items: flex-start; }
            .add-materia-form { max-width: 100%; width: 100%; flex-direction: column; }
        }
    </style>
</head>

<body>
    <!-- BARRA LATERAL RESTAURADA A SU DISEÑO ORIGINAL PERFECTO -->
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
                    <i class="fas fa-house"></i><span>Inicio</span>
                </a>

                <span class="nav-heading">Académico</span>
                <a href="mis_grupos.php" class="nav-item active">
                    <i class="fas fa-laptop-code"></i><span>Mis Aulas (Grupos)</span>
                </a>
                <a href="horario.php" class="nav-item">
                    <i class="far fa-calendar-alt"></i><span>Mi Horario</span>
                </a>

                <span class="nav-heading">Evaluación</span>
                <a href="regularizaciones.php" class="nav-item">
                    <i class="fas fa-book-open-reader"></i><span>Regularizaciones</span>
                </a>
            </nav>

            <div class="sidebar-bottom">
                <div class="institution">
                    <div class="institution-icon"><i class="fas fa-building-columns"></i></div>
                    <div><strong>COBAEP Plantel 27</strong><span>Portal Docente</span></div>
                </div>
                <a href="../logout.php" class="logout"><i class="fas fa-arrow-right-from-bracket"></i><span>Cerrar Sesión</span></a>
            </div>
        </div>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu" id="mobileMenu"><i class="fas fa-bars"></i></button>
                <div>
                    <span class="eyebrow">AULA VIRTUAL</span>
                    <h1>Panel de Control</h1>
                </div>
            </div>
            <div class="topbar-right">
                <div class="date-pill"><i class="far fa-calendar"></i><span id="fechaTexto"><?php echo date('d/m/Y'); ?></span></div>
                <div class="profile">
                    <div class="profile-avatar"><?php echo $iniciales; ?></div>
                    <div class="profile-info">
                        <strong><?php echo $nombre_usuario; ?></strong>
                        <span>Docente</span>
                    </div>
                </div>
            </div>
        </header>

        <div class="header-aula">
            <div>
                <?php if ($vista_actual === 'grupo'): ?>
                    <h2>Grupo <?php echo htmlspecialchars($info_cabecera['semestre']) . '° "' . htmlspecialchars($info_cabecera['nombre_grupo']) . '"'; ?></h2>
                    <p><i class="fas fa-folder-open"></i> Gestión de Materias</p>
                <?php else: ?>
                    <h2><?php echo htmlspecialchars($info_cabecera['nombre_materia']); ?></h2>
                    <p><i class="fas fa-layer-group"></i> Grupo <?php echo htmlspecialchars($info_cabecera['semestre']) . '° "' . htmlspecialchars($info_cabecera['nombre_grupo']) . '"'; ?></p>
                <?php endif; ?>
            </div>
            <a href="<?php echo ($vista_actual === 'grupo') ? 'mis_grupos.php' : 'gestionar_aula.php?id_grupo='.$info_cabecera['id_grupo']; ?>" class="btn-volver"><i class="fas fa-arrow-left"></i> <?php echo ($vista_actual === 'grupo') ? 'Regresar a Mis Grupos' : 'Volver a Materias'; ?></a>
        </div>

        <?php if(isset($_GET['exito'])): ?>
            <div style="background: #ecfdf5; color: #065f46; padding: 12px 16px; border-radius: 12px; border: 1px solid #a7f3d0; margin-bottom:1.5rem; font-weight:600;"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['exito']); ?></div>
        <?php endif; ?>
        <?php if(isset($_GET['error'])): ?>
            <div style="background: #fef2f2; color: #991b1b; padding: 12px 16px; border-radius: 12px; border: 1px solid #fecaca; margin-bottom:1.5rem; font-weight:600;"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <?php if ($vista_actual === 'grupo'): ?>
            <div class="add-materia-bar">
                <div class="add-materia-info">
                    <div class="icon-box"><i class="fas fa-plus"></i></div>
                    <div>
                        <h4>Activar Nueva Materia</h4>
                        <p>Crea un aula virtual en este grupo.</p>
                    </div>
                </div>
                <form action="procesos/docente_activar_aula.php" method="POST" class="add-materia-form">
                    <input type="hidden" name="id_grupo" value="<?php echo $id_grupo; ?>">
                    <input type="text" name="nombre_materia" placeholder="Ej. Informática I" required>
                    <button type="submit"><i class="fas fa-bolt"></i> Crear Aula</button>
                </form>
            </div>

            <h3 style="color:var(--ink); font-size:1.1rem; margin-bottom:1rem; font-weight:800;">Materias Activas</h3>
            <?php if(empty($materias_del_grupo)): ?>
                <div style="padding: 3rem 2rem; border: 2px dashed #cbd5e1; border-radius: 20px; text-align: center; color: var(--muted); background: white;">
                    <i class="fas fa-box-open" style="font-size: 3rem; margin-bottom:15px; color: #cbd5e1;"></i>
                    <p style="margin:0; font-size: 1.1rem; font-weight: 600;">No has activado ninguna materia.</p>
                </div>
            <?php else: ?>
                <div class="grid-materias">
                    <?php foreach($materias_del_grupo as $mat): ?>
                        <div class="materia-card">
                            <div>
                                <h3><?php echo htmlspecialchars($mat['nombre_materia']); ?></h3>
                                <div class="meta">
                                    <?php if(empty($mat['enlace_clase_virtual'])): ?>
                                        <i class="fas fa-video-slash" style="color:#94a3b8;"></i> Sin videollamada
                                    <?php else: ?>
                                        <i class="fas fa-video" style="color:var(--green);"></i> Videollamada activa
                                    <?php endif; ?>
                                </div>
                            </div>
                            <a href="gestionar_aula.php?id_clase=<?php echo $mat['id_clase']; ?>" class="btn-entrar">Gestionar Tareas <i class="fas fa-arrow-right"></i></a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="layout-materia">
                <!-- IZQUIERDA: TAREAS -->
                <div>
                    <div class="card">
                        <div class="card-title">
                            <div><i class="fas fa-bullhorn"></i> Publicar Nueva Actividad</div>
                        </div>
                        <form action="procesos/docente_publicar_actividad.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="id_clase" value="<?php echo $id_clase; ?>">
                            <div class="form-group">
                                <label>Título de la Tarea</label>
                                <input type="text" name="titulo" class="form-control" placeholder="Ej. Ensayo sobre la Revolución" required>
                            </div>
                            <div class="form-group">
                                <label>Instrucciones detalladas</label>
                                <textarea name="descripcion" class="form-control" placeholder="Escribe aquí las instrucciones..."></textarea>
                            </div>
                            
                            <div style="display:flex; gap:15px; margin-bottom: 1.2rem; flex-wrap: wrap;">
                                <div class="form-group" style="flex:1; margin:0;">
                                    <label>Fecha y Hora Límite</label>
                                    <input type="datetime-local" name="fecha_limite" class="form-control">
                                </div>
                                <div class="form-group" style="flex:1; margin:0;">
                                    <label>Archivo Adjunto de Ayuda</label>
                                    <input type="file" name="archivo" class="form-control" style="padding: 9px 14px;">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Opciones de Entrega (Para los alumnos)</label>
                                <div class="check-options">
                                    <label class="check-item">
                                        <input type="checkbox" name="permite_entregas" value="1" checked> 
                                        Los alumnos deben subir archivo
                                    </label>
                                    <label class="check-item">
                                        <input type="checkbox" name="acepta_retardo" value="1"> 
                                        Aceptar archivos con retardo (Fuera de fecha)
                                    </label>
                                </div>
                            </div>

                            <button type="submit" class="btn-primary"><i class="fas fa-paper-plane"></i> Crear y Publicar Actividad</button>
                        </form>
                    </div>

                    <div class="card">
                        <div class="card-title">
                            <div><i class="fas fa-list-check"></i> Actividades Publicadas</div>
                        </div>
                        <?php if(empty($actividades)): ?>
                            <div style="padding: 2rem; text-align: center; border: 1px dashed #cbd5e1; border-radius: 12px; background: #f8fafc;">
                                <i class="fas fa-clipboard" style="font-size: 2rem; color: #cbd5e1; margin-bottom: 10px;"></i>
                                <p style="color:var(--muted); font-size:0.9rem; margin:0;">Aún no hay actividades en esta materia.</p>
                            </div>
                        <?php else: ?>
                            <div class="act-list">
                                <?php foreach($actividades as $act): ?>
                                    <div class="act-item">
                                        <div class="act-header">
                                            <div class="act-info">
                                                <h4><?php echo htmlspecialchars($act['titulo']); ?></h4>
                                                <p><i class="far fa-calendar-alt"></i> Publicado: <?php echo date('d/m/Y', strtotime($act['fecha_publicacion'])); ?></p>
                                            </div>
                                        </div>
                                        <div class="act-badges">
                                            <?php if(isset($act['fecha_limite']) && !empty($act['fecha_limite'])): ?>
                                                <span class="badge badge-orange"><i class="far fa-clock"></i> Límite: <?php echo date('d/m/Y H:i', strtotime($act['fecha_limite'])); ?></span>
                                            <?php endif; ?>
                                            <?php if(isset($act['permite_entregas']) && $act['permite_entregas'] == 1): ?>
                                                <span class="badge badge-green"><i class="fas fa-upload"></i> Requiere Entrega</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="act-actions">
                                            <button class="btn-outline" title="Próximamente"><i class="fas fa-edit"></i> Editar</button>
                                            
                                            <?php if(isset($act['permite_entregas']) && $act['permite_entregas'] == 1): ?>
                                                <button class="btn-outline" style="color:var(--blue); border-color:var(--blue);"><i class="fas fa-inbox"></i> Ver Entregas (0)</button>
                                            <?php endif; ?>

                                            <form action="procesos/docente_eliminar_actividad.php" method="POST" style="margin:0;">
                                                <input type="hidden" name="id_actividad" value="<?php echo $act['id_actividad']; ?>">
                                                <input type="hidden" name="id_clase" value="<?php echo $id_clase; ?>">
                                                <button type="submit" class="btn-outline" style="color:red; border-color:red; padding:6px 10px;" onclick="return confirm('¿Eliminar permanentemente?');"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- DERECHA: MEET Y ALUMNOS -->
                <div>
                    <div class="card">
                        <div class="card-title"><div><i class="fas fa-video"></i> Clase en Vivo</div></div>
                        <form action="procesos/docente_activar_clase.php" method="POST">
                            <input type="hidden" name="id_clase" value="<?php echo $id_clase; ?>">
                            <div class="form-group">
                                <label>Enlace de Zoom/Meet</label>
                                <input type="url" name="enlace_clase_virtual" class="form-control" value="<?php echo $link_actual; ?>" placeholder="https://meet.google.com/...">
                            </div>
                            <button type="submit" class="btn-primary btn-blue"><i class="fas fa-save"></i> Guardar Enlace</button>
                            <?php if(!empty($link_actual)): ?>
                                <button type="submit" name="apagar" value="1" class="btn-danger"><i class="fas fa-power-off"></i> Finalizar Clase</button>
                            <?php endif; ?>
                        </form>
                    </div>

                    <div class="card">
                        <!-- HEADER ALUMNOS CON BOTÓN GENERAL -->
                        <div class="card-title" style="justify-content:space-between;">
                            <div><i class="fas fa-users"></i> Alumnos (<?php echo $total_alumnos; ?>)</div>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span style="font-size:0.75rem; color:var(--muted);">Acceso Gral.</span>
                                <div class="switch" title="Dar/Quitar acceso a todos">
                                    <input type="checkbox" id="toggleAll" <?php echo ($alumnos_activos_count == $total_alumnos && $total_alumnos > 0) ? 'checked' : ''; ?> onchange="toggleAllAccesos(<?php echo $id_clase; ?>, this)">
                                    <label class="slider" for="toggleAll"></label>
                                </div>
                            </div>
                        </div>

                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="buscadorAlumnos" placeholder="Buscar alumno...">
                        </div>
                        <div class="lista-scroll">
                            <?php foreach($lista_alumnos as $alum): 
                                $ini_alum = mb_substr($alum['nombre'], 0, 1) . mb_substr($alum['apellido_paterno'], 0, 1);
                                $tiene_acceso = isset($alum['acceso']) ? $alum['acceso'] : 1;
                            ?>
                                <div class="alumno-item">
                                    <div style="display:flex; align-items:center; gap:12px;">
                                        <div class="alumno-avatar"><?php echo htmlspecialchars($ini_alum); ?></div>
                                        <div class="alumno-info">
                                            <strong><?php echo htmlspecialchars($alum['apellido_paterno'] . ' ' . $alum['nombre']); ?></strong>
                                            <span>Mat: <?php echo htmlspecialchars($alum['matricula']); ?></span>
                                        </div>
                                    </div>
                                    <div class="switch" title="Permitir acceso individual">
                                        <input type="checkbox" class="alum-toggle" id="toggle_<?php echo $alum['id_alumno']; ?>" <?php echo ($tiene_acceso == 1) ? 'checked' : ''; ?> onchange="toggleAcceso(<?php echo $alum['id_alumno']; ?>, <?php echo $id_clase; ?>, this)">
                                        <label class="slider" for="toggle_<?php echo $alum['id_alumno']; ?>"></label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

            </div>
        <?php endif; ?>

    </main>

    <script src="dashboard_maestro.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const buscador = document.getElementById('buscadorAlumnos');
            if (buscador) {
                buscador.addEventListener('input', function(e) {
                    let term = e.target.value.toLowerCase();
                    let items = document.querySelectorAll('.alumno-item');
                    items.forEach(item => {
                        let text = item.querySelector('.alumno-info').textContent.toLowerCase();
                        item.style.display = text.includes(term) ? 'flex' : 'none';
                    });
                });
            }
        });

        // Switch individual
        function toggleAcceso(id_alumno, id_clase, checkbox) {
            let estado = checkbox.checked ? 1 : 0;
            fetch('procesos/docente_toggle_acceso.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id_alumno=' + id_alumno + '&id_clase=' + id_clase + '&acceso=' + estado
            })
            .then(response => response.json())
            .then(data => {
                if(!data.success) {
                    alert("Error de conexión al cambiar acceso.");
                    checkbox.checked = !checkbox.checked;
                }
            }).catch(error => { checkbox.checked = !checkbox.checked; });
        }

        // Switch General (Afecta a todos)
        function toggleAllAccesos(id_clase, checkbox) {
            let estado = checkbox.checked ? 1 : 0;
            
            fetch('procesos/docente_toggle_acceso_general.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id_clase=' + id_clase + '&acceso=' + estado
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    // Cambiar visualmente todos los switches individuales
                    document.querySelectorAll('.alum-toggle').forEach(t => t.checked = checkbox.checked);
                } else {
                    alert("Error al intentar cambiar todos los accesos.");
                    checkbox.checked = !checkbox.checked;
                }
            }).catch(error => { checkbox.checked = !checkbox.checked; });
        }
    </script>
</body>
</html>