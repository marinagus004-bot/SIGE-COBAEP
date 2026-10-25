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

$mis_grupos = [];

try {
    // SOLO TRAEMOS LOS GRUPOS PUROS ASIGNADOS AL DOCENTE
    $stmtGrupos = $pdo->prepare("
        SELECT 
            g.id_grupo,
            g.nombre_grupo,
            g.semestre,
            g.turno,
            (SELECT COUNT(id_alumno) FROM grupo_alumno WHERE id_grupo = g.id_grupo AND fecha_baja IS NULL) AS total_alumnos
        FROM horarios_maestro hm
        INNER JOIN grupos g ON hm.id_grupo = g.id_grupo
        WHERE hm.id_maestro = ?
    ");
    $stmtGrupos->execute([$id_docente]);
    $mis_grupos = $stmtGrupos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error cargando grupos del docente: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Grupos · Portal Docente</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="style_dashboard_docente.css?v=<?php echo time(); ?>">

    <style>
        .empty-state { background: #ffffff; border: 2px dashed #e8efeb; border-radius: 20px; padding: 4rem 2rem; text-align: center; margin-top: 10px; }
        .empty-state i { font-size: 3.5rem; color: #9bc7b5; margin-bottom: 1rem; display: block; }
        .empty-state h3 { color: #10251d; margin: 0 0 0.5rem 0; font-size: 1.2rem; font-weight: 800;}
        .empty-state p { color: #6d7f77; margin: 0; font-size: 0.95rem; }

        .grid-aulas { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem; }
        
        .aula-card { background: #ffffff; border-radius: 20px; border: 1px solid #e8efeb; overflow: hidden; box-shadow: 0 4px 18px rgba(9, 48, 32, 0.05); display: flex; flex-direction: column; transition: 0.3s ease; }
        .aula-card:hover { transform: translateY(-5px); box-shadow: 0 14px 40px rgba(9, 48, 32, 0.08); border-color: #d9e9e1;}
        
        .aula-header { height: 130px; background: linear-gradient(145deg, var(--green-950), var(--green-900)); position: relative; padding: 1.5rem; display: flex; flex-direction: column; justify-content: flex-end; }
        .aula-badge { position: absolute; top: 15px; left: 15px; background: rgba(255,255,255,0.2); color: white; padding: 6px 12px; border-radius: 50px; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .aula-header h3 { margin: 0; font-size: 1.5rem; color: white; font-weight: 800; }
        .aula-header p { margin: 5px 0 0 0; font-size: 0.85rem; color: #a7f3d0; font-weight: 600; }

        .aula-body { padding: 1.5rem; flex: 1; display: flex; flex-direction: column; justify-content: center;}
        .meta { font-size: 0.9rem; color: #6d7f77; display: flex; align-items: center; gap: 10px; font-weight: 600;}
        .meta i { width: 20px; text-align: center; font-size: 1.1rem; color: #3b82f6; }
        
        .aula-footer { padding: 0 1.5rem 1.5rem 1.5rem; }
        .btn-manage { display: flex; align-items: center; justify-content: center; gap: 8px; background: var(--green); color: white; padding: 12px; border-radius: 12px; text-decoration: none; font-weight: 700; font-size: 0.95rem; transition: 0.2s ease; width: 100%;}
        .btn-manage:hover { background: var(--green-800); box-shadow: 0 4px 10px rgba(22, 160, 93, 0.3); }
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
                    <span class="eyebrow">ACADÉMICO · DOCENTE</span>
                    <h1>Mis Grupos Asignados</h1>
                    <p>Selecciona un grupo para gestionar sus materias y alumnos.</p>
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

        <section>
            <?php if (empty($mis_grupos)): ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <h3>Aún no tienes grupos asignados</h3>
                    <p>El área administrativa debe asignarte horarios y grupos para este semestre.</p>
                </div>
            <?php else: ?>
                <div class="grid-aulas">
                    <?php foreach ($mis_grupos as $grupo): ?>
                        <div class="aula-card">
                            <div class="aula-header">
                                <span class="aula-badge">Grupo Asignado</span>
                                <div>
                                    <h3>Grupo <?php echo htmlspecialchars($grupo['semestre'] ?? '') . '° "' . htmlspecialchars($grupo['nombre_grupo'] ?? '') . '"'; ?></h3>
                                    <p>Turno <?php echo htmlspecialchars($grupo['turno']); ?></p>
                                </div>
                            </div>
                            <div class="aula-body">
                                <div class="meta">
                                    <i class="fas fa-users"></i> 
                                    <span><strong><?php echo $grupo['total_alumnos']; ?></strong> Alumnos inscritos</span>
                                </div>
                            </div>
                            <div class="aula-footer">
                                <a href="gestionar_aula.php?id_grupo=<?php echo $grupo['id_grupo']; ?>" class="btn-manage">
                                    Abrir Grupo <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <script src="dashboard_maestro.js"></script>
</body>
</html>