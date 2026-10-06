<?php
session_start();
require_once '../config/database.php'; 

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'prefectos') {
    header("Location: ../login.php");
    exit();
}

$nombre_usuario = isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') : 'Prefecto';
date_default_timezone_set('America/Mexico_City');

// Consulta inteligente: Trae todos los grupos y calcula los asistentes de HOY
$query_grupos = "
    SELECT 
        g.id_grupo, 
        g.semestre, 
        g.nombre_grupo,
        g.turno,
        (SELECT COUNT(*) FROM grupo_alumno WHERE id_grupo = g.id_grupo) AS total_alumnos,
        (SELECT COUNT(DISTINCT a.id_alumno) 
         FROM asistencias a 
         INNER JOIN grupo_alumno ga2 ON a.id_alumno = ga2.id_alumno 
         WHERE ga2.id_grupo = g.id_grupo 
         AND DATE(a.fecha_hora_escaneo) = CURDATE() 
         AND a.estatus NOT IN ('Falta', 'Suspendido', '')
        ) AS asistencias_hoy
    FROM grupos g
    ORDER BY g.semestre ASC, g.nombre_grupo ASC;
";

try {
    $stmt = $pdo->query($query_grupos);
    $grupos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $grupos = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoreo de Grupos · SIGE COBAEP 27</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <link rel="stylesheet" href="../admin/style_dashboard_admin.css">
    <link rel="stylesheet" href="../Prefectura/style_dashboard_prefectura.css">

    <style>
        /* ESTILOS DE LAS TARJETAS DE MONITOREO */
        .groups-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .group-card {
            background: #ffffff;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .group-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            border-color: var(--green-soft);
        }
        
        .group-card::before {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 5px; transition: 0.3s ease;
        }

        /* 1. COLORES DE LA LÍNEA SUPERIOR SEGÚN TURNO */
        .card-matutino::before { background: var(--green); }
        .card-intermedio::before { background: #f59e0b; }
        .card-vespertino::before { background: #3b82f6; }

        /* 2. COLORES DE LAS ETIQUETAS (BADGES) SEGÚN TURNO */
        .badge-turno {
            padding: 4px 10px; border-radius: 99px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;
        }
        .badge-matutino { background: #dcfce7; color: #166534; }
        .badge-intermedio { background: #fef3c7; color: #b45309; }
        .badge-vespertino { background: #dbeafe; color: #1d4ed8; }

        /* Elementos internos */
        .group-header {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;
        }
        .group-header h2 {
            font-size: 1.5rem; font-weight: 800; color: var(--ink); margin: 0;
        }
        
        .group-stats {
            display: flex; gap: 15px; margin-bottom: 20px; 
            background: #f8fafc; /* Fondo gris claro original */
            padding: 12px; border-radius: 10px;
        }
        .stat-item {
            flex: 1; display: flex; flex-direction: column; align-items: center; text-align: center;
        }
        .stat-item i { font-size: 1.2rem; margin-bottom: 5px; }
        .stat-item span { font-size: 1.2rem; font-weight: 800; color: var(--ink); }
        .stat-item small { font-size: 0.7rem; color: var(--muted); font-weight: 600; text-transform: uppercase; }
        
        .stat-present i { color: #10b981; }
        .stat-absent i { color: #ef4444; }

        .group-actions { display: flex; flex-direction: column; gap: 10px; }
        .btn-group-action {
            display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 0.7rem;
            border-radius: 8px; font-size: 0.85rem; font-weight: 700; cursor: pointer; transition: 0.2s; border: none; text-decoration: none;
        }
        .btn-list { background-color: var(--green-soft); color: var(--green-700); }
        .btn-list:hover { background-color: var(--green); color: #ffffff; }
        
        .btn-folder { background-color: #f1f5f9; color: #334155; }
        .btn-folder:hover { background-color: #e2e8f0; color: #0f172a; }
    </style>
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
                    <strong>SIGE<span>-Cobaep</span></strong><small>Plantel 27</small>
                </div>
            </div>

            <nav class="navigation" aria-label="Navegación principal">
                <span class="nav-heading">Menú principal</span>
                <a href="dashboard_prefectura.php" class="nav-item"><i class="fas fa-shield-halved"></i><span>Control de Acceso</span></a>
                <a href="monitoreo_grupos.php" class="nav-item active"><i class="fas fa-users-viewfinder"></i><span>Monitoreo de Grupos</span></a>
                <a href="historial_asistencias.php" class="nav-item"><i class="fas fa-history"></i><span>Historial y Reportes</span></a>
                <a href="creacion_reportes.php" class="nav-item"><i class="fa-solid fa-file-lines"></i><span>Creacion de Reporte de Conducta</span></a>  
                <a href="expediente_alumnos.php" class="nav-item"><i class="fas fa-folder-open"></i><span>Expediente de Alumnos</span></a>
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

    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <div>
                    <span class="eyebrow">OPERACIONES DE PREFECTURA</span>
                    <h1>Monitoreo de Grupos en Vivo</h1>
                    <p>Vista general de asistencia y operaciones por salón (Datos del día de hoy).</p>
                </div>
            </div>
        </header>

        <section class="groups-grid">
            <?php if (empty($grupos)): ?>
                <div style="grid-column: 1 / -1; padding: 2rem; text-align: center; background: white; border-radius: 12px;">
                    <i class="fas fa-exclamation-circle" style="font-size: 2rem; color: #cbd5e1; margin-bottom: 10px;"></i>
                    <p>No hay grupos registrados en el sistema actualmente.</p>
                </div>
            <?php else: ?>
                <?php foreach ($grupos as $g): 
                    $total = (int)$g['total_alumnos'];
                    $asistencias = (int)$g['asistencias_hoy'];
                    $faltas = $total - $asistencias;
                    if($faltas < 0) $faltas = 0;
                    
                    $nombre_completo = $g['semestre'] . '° "' . $g['nombre_grupo'] . '"';
                    
                    // Lógica para detectar el turno y asignar los colores
                    $turno_str = strtolower($g['turno']);
                    $clase_tarjeta = 'card-matutino'; // Por defecto verde
                    $clase_badge = 'badge-matutino';
                    
                    if (strpos($turno_str, 'intermedio') !== false) {
                        $clase_tarjeta = 'card-intermedio'; // Naranja
                        $clase_badge = 'badge-intermedio';
                    } elseif (strpos($turno_str, 'vespertino') !== false) {
                        $clase_tarjeta = 'card-vespertino'; // Azul
                        $clase_badge = 'badge-vespertino';
                    }
                ?>
                <article class="group-card <?php echo $clase_tarjeta; ?>">
                    <div class="group-header">
                        <h2><?php echo htmlspecialchars($nombre_completo); ?></h2>
                        <span class="badge-turno <?php echo $clase_badge; ?>"><?php echo htmlspecialchars($g['turno']); ?></span>
                    </div>
                    
                    <div class="group-stats">
                        <div class="stat-item stat-present">
                            <i class="fas fa-user-check"></i>
                            <span><?php echo $asistencias; ?></span>
                            <small>Asistencias</small>
                        </div>
                        <div class="stat-item stat-absent">
                            <i class="fas fa-user-times"></i>
                            <span><?php echo $faltas; ?></span>
                            <small>Faltas / Ausentes</small>
                        </div>
                        <div class="stat-item" style="border-left: 1px solid #e2e8f0;">
                            <i class="fas fa-users" style="color: #94a3b8;"></i>
                            <span><?php echo $total; ?></span>
                            <small>Inscritos</small>
                        </div>
                    </div>

                    <div class="group-actions">
                        <button onclick="abrirModalLista(<?php echo $g['id_grupo']; ?>, '<?php echo $g['semestre']; ?>° <?php echo $g['nombre_grupo']; ?>')" class="btn-group-action btn-list">
                            <i class="fas fa-list-ol"></i> Generar Lista de Asistencia
                        </button>
                        
                        <a href="expediente_alumnos.php?grupo=<?php echo $g['id_grupo']; ?>" class="btn-group-action btn-folder">
                            <i class="fas fa-folder-open"></i> Ver Expedientes
                        </a>
                    </div>
                </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>

    <!-- MODAL PEQUEÑO PARA ELEGIR LA FECHA -->
    <div class="modal-overlay" id="modalFechaLista" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(3, 43, 30, 0.6); backdrop-filter: blur(4px); justify-content: center; align-items: center; z-index: 2000;">
        <div class="modal" style="background: #ffffff; padding: 2.5rem; border-radius: var(--radius-lg); width: 90%; max-width: 400px; box-shadow: var(--shadow-lg);">
            <h3 style="margin-bottom: 0.5rem; color: var(--ink); font-weight: 800;"><i class="fas fa-calendar-day" style="color: var(--green);"></i> Lista <span id="nombreGrupoModal"></span></h3>
            <p style="font-size: 0.8rem; color: var(--muted); margin-bottom: 1.5rem;">Selecciona el día que deseas exportar.</p>
            
            <form action="vista_previa_lista.php" method="GET">
                <input type="hidden" name="id_grupo" id="idGrupoModalInput">
                
                <div class="form-group" style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; margin-bottom: 0.5rem;">Fecha de la lista</label>
                    <input type="date" name="fecha_lista" class="form-control" value="<?php echo date('Y-m-d'); ?>" required style="width: 100%; padding: 0.85rem; border: 1px solid var(--line); border-radius: 6px;">
                </div>
                
                <div class="modal-actions" style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-cancel" onclick="document.getElementById('modalFechaLista').style.display='none'" style="padding: 0.75rem 1.2rem; border-radius: 6px; border: none; cursor: pointer; background: #e2e8f0;">Cancelar</button>
                    <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.2rem; border-radius: 6px; border: none; cursor: pointer; background: var(--green); color: white; font-weight: bold;">Generar Vista Previa</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../Prefectura/procesos/dashboard_prefecturas.js"></script>
    <script>
        function abrirModalLista(idGrupo, nombreGrupo) {
            document.getElementById('idGrupoModalInput').value = idGrupo;
            document.getElementById('nombreGrupoModal').innerText = nombreGrupo;
            document.getElementById('modalFechaLista').style.display = 'flex';
        }
    </script>
</body>
</html>