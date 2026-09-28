<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'prefectos') {
    header("Location: ../login.php");
    exit();
}

$nombre_usuario = isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') : 'Prefecto';

$matricula_buscada = trim($_GET['matricula'] ?? '');
$alumno = null;
$historial = [];

if (!empty($matricula_buscada)) {
    // Buscar al alumno
    $stmt_alumno = $pdo->prepare("SELECT id_alumno, matricula, nombre, apellido_paterno, semestre, turno, puntos_conducta FROM alumnos WHERE matricula = :matricula LIMIT 1");
    $stmt_alumno->execute([':matricula' => $matricula_buscada]);
    $alumno = $stmt_alumno->fetch(PDO::FETCH_ASSOC);

    if ($alumno) {
        // Buscar su historial de reportes
        $stmt_reportes = $pdo->prepare("
            SELECT r.fecha_hora, tf.descripcion_falta, tf.numero_reglamento, r.puntos_descontados, r.observaciones 
            FROM reportes r
            INNER JOIN tipos_falta tf ON r.id_tipo_falta = tf.id_tipo_falta
            WHERE r.id_alumno = :id
            ORDER BY r.fecha_hora DESC
        ");
        $stmt_reportes->execute([':id' => $alumno['id_alumno']]);
        $historial = $stmt_reportes->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expediente de Alumnos · COBAEP 27</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="../admin/style_dashboard_admin.css">
    <link rel="stylesheet" href="../Prefectura/style_dashboard_prefectura.css">
    <style>
        .search-container { background: #ffffff; padding: 25px; border-radius: var(--radius-md); border: 1px solid var(--line); box-shadow: var(--shadow-sm); margin-bottom: 25px; }
        .search-box { display: flex; gap: 10px; max-width: 500px; }
        .search-box input { flex: 1; padding: 0.8rem; border: 1px solid var(--line); border-radius: var(--radius-sm); font-size: 0.95rem; }
        .search-box button { background: var(--green); color: white; border: none; padding: 0 20px; border-radius: var(--radius-sm); cursor: pointer; font-weight: 600; transition: 0.2s; }
        .search-box button:hover { background: var(--green-700); }
        
        .student-header { display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 20px; border: 1px solid #e2e8f0; border-radius: var(--radius-md); margin-bottom: 25px; }
        .pts-indicator { text-align: center; background: white; padding: 10px 20px; border-radius: var(--radius-sm); border: 1px solid var(--line); }
        .pts-indicator span { display: block; font-size: 0.7rem; color: var(--muted); text-transform: uppercase; font-weight: 700; margin-bottom: 5px; }
        .pts-indicator strong { font-size: 1.8rem; }
        .btn-pdf { background: #0ea5e9; color: white; padding: 0.8rem 1.5rem; border-radius: var(--radius-sm); text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-pdf:hover { background: #0284c7; }
    </style>
</head>
<body>
    
    <aside class="sidebar" id="sidebar">
        <!-- Reemplaza esto con tu código exacto del sidebar. Asegúrate de incluir el nuevo enlace con class="nav-item active" -->
        <div class="sidebar-inner">
            <div class="brand">
                <img src="../img/LogoCobaep.png" alt="Logo COBAEP" class="brand-logo">
                <div class="brand-copy"><strong>SIGE<span>-Cobaep</span></strong><small>Plantel 27</small></div>
            </div>
            <nav class="navigation" aria-label="Navegación principal">
                <span class="nav-heading">Menú principal</span>
                <a href="dashboard_prefectura.php" class="nav-item"><i class="fas fa-shield-halved"></i><span>Control de Acceso</span></a>
                <a href="historial_asistencias.php" class="nav-item"><i class="fas fa-history"></i><span>Historial y Reportes</span></a>
                <a href="creacion_reportes.php" class="nav-item"><i class="fa-solid fa-file-lines"></i><span>Creacion de Reporte</span></a>  
                <!-- ACTIVO AQUI -->
                <a href="expediente_alumnos.php" class="nav-item active"><i class="fas fa-folder-open"></i><span>Expediente de Alumnos</span></a>
            </nav>
            <div class="sidebar-bottom">
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
                    <span class="eyebrow">PREFECTURA · DISCIPLINA</span>
                    <h1>Expediente de Alumnos</h1>
                    <p>Consulta el historial disciplinario y genera documentos oficiales.</p>
                </div>
            </div>
            <div class="topbar-right">
                <div class="profile">
                    <div class="profile-avatar"><i class="fas fa-user-shield"></i></div>
                    <div class="profile-info"><strong><?php echo $nombre_usuario; ?></strong><span>Prefecto</span></div>
                </div>
            </div>
        </header>

        <!-- Buscador -->
        <section class="search-container">
            <label style="display: block; font-weight: 600; color: var(--ink); margin-bottom: 10px;"><i class="fas fa-search"></i> Buscar Expediente</label>
            <form method="GET" class="search-box">
                <input type="text" name="matricula" placeholder="Ingresa la matrícula del alumno (Ej. 23ZP0287)" value="<?php echo htmlspecialchars($matricula_buscada); ?>" required autofocus>
                <button type="submit">Buscar</button>
                <?php if(!empty($matricula_buscada)): ?>
                    <a href="expediente_alumnos.php" style="display:grid; place-items:center; padding:0 15px; color:var(--muted); text-decoration:none; border:1px solid var(--line); border-radius:var(--radius-sm);"><i class="fas fa-times"></i></a>
                <?php endif; ?>
            </form>
        </section>

        <!-- Resultados -->
        <?php if (!empty($matricula_buscada)): ?>
            <?php if (!$alumno): ?>
                <div style="background: #fef2f2; color: #b91c1c; padding: 20px; border-radius: var(--radius-md); border: 1px solid #fecaca; text-align: center;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                    <h3 style="margin: 0;">Alumno no encontrado</h3>
                    <p style="margin: 5px 0 0 0; font-size: 0.9rem;">Verifica que la matrícula sea correcta y vuelve a intentarlo.</p>
                </div>
            <?php else: ?>
                
                <div class="student-header">
                    <div>
                        <h2 style="margin: 0 0 5px 0; color: var(--ink); font-size: 1.5rem;">
                            <?php echo htmlspecialchars($alumno['apellido_paterno'] . ' ' . $alumno['nombre']); ?>
                        </h2>
                        <p style="margin: 0; color: var(--muted); font-size: 0.9rem;">
                            <strong>Matrícula:</strong> <?php echo htmlspecialchars($alumno['matricula']); ?> | 
                            <strong>Grupo:</strong> <?php echo htmlspecialchars($alumno['semestre'] . ' - ' . $alumno['turno']); ?>
                        </p>
                    </div>
                    
                    <div style="display: flex; gap: 20px; align-items: center;">
                        <div class="pts-indicator">
                            <span>Puntos Actuales</span>
                            <strong style="color: <?php echo $alumno['puntos_conducta'] < 75 ? '#ef4444' : '#16a34a'; ?>;">
                                <?php echo htmlspecialchars($alumno['puntos_conducta'] ?? '100'); ?>
                            </strong>
                        </div>
                        
                        <a href="procesos/generar_pdf_expediente.php?id_alumno=<?php echo $alumno['id_alumno']; ?>" target="_blank" class="btn-pdf">
                            <i class="fas fa-file-pdf"></i> Descargar Expediente
                        </a>
                    </div>
                </div>

                <article class="overview-card" style="padding: 0; overflow: hidden;">
                    <div class="card-heading" style="padding: 24px 24px 10px;">
                        <div>
                            <span class="section-kicker">HISTORIAL COMPLETO</span>
                            <h2>Faltas Registradas</h2>
                        </div>
                    </div>
                    <div class="table-responsive" style="margin-bottom: 0;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Fecha y Hora</th>
                                    <th>Regla Infringida</th>
                                    <th>Observaciones</th>
                                    <th>Puntos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($historial)): ?>
                                    <tr><td colspan="4" style="text-align: center; padding: 3rem; color: var(--green);"><strong><i class="fas fa-check-circle"></i> Expediente limpio.</strong> No hay reportes registrados para este alumno.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($historial as $rep): ?>
                                    <tr>
                                        <td style="font-size: 0.8rem; color: var(--muted);">
                                            <?php echo date('d/m/Y', strtotime($rep['fecha_hora'])); ?><br>
                                            <strong><?php echo date('h:i A', strtotime($rep['fecha_hora'])); ?></strong>
                                        </td>
                                        <td>
                                            <strong>Regla <?php echo htmlspecialchars($rep['numero_reglamento']); ?>:</strong><br>
                                            <?php echo htmlspecialchars($rep['descripcion_falta']); ?>
                                        </td>
                                        <td style="font-size: 0.8rem; color: var(--muted); font-style: italic; max-width: 300px;">
                                            <?php echo !empty($rep['observaciones']) ? htmlspecialchars($rep['observaciones']) : 'Sin observaciones adicionales.'; ?>
                                        </td>
                                        <td><span class="status-badge-table" style="background: #fee2e2; color: #b91c1c; font-weight: bold;">-<?php echo htmlspecialchars($rep['puntos_descontados']); ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </article>

            <?php endif; ?>
        <?php endif; ?>
    </main>

    <script src="../Prefectura/procesos/dashboard_prefecturas.js"></script>
</body>
</html>