<?php
session_start();
require_once '../config/database.php';

// Validación estricta: Busca el rol 'docente'
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../login.php");
    exit();
}

// Obtenemos el nombre y el ID del maestro desde la sesión
$nombre_usuario = isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') : 'Docente';
$id_docente = $_SESSION['id_usuario'];

// =======================================================
// CONSULTAS REALES A LA BASE DE DATOS
// =======================================================
try {
    // 1. Total de grupos asignados al maestro
    $stmt_grupos = $pdo->prepare("SELECT COUNT(DISTINCT id_grupo) FROM HORARIOS_MAESTRO WHERE id_maestro = ?");
    $stmt_grupos->execute([$id_docente]);
    $total_grupos = $stmt_grupos->fetchColumn();

    // 2. Total de alumnos en los grupos de este maestro
    $stmt_alumnos = $pdo->prepare("
        SELECT COUNT(DISTINCT ga.id_alumno) 
        FROM GRUPO_ALUMNO ga
        INNER JOIN HORARIOS_MAESTRO hm ON ga.id_grupo = hm.id_grupo
        WHERE hm.id_maestro = ? AND ga.fecha_baja IS NULL
    ");
    $stmt_alumnos->execute([$id_docente]);
    $total_alumnos = $stmt_alumnos->fetchColumn();

    // 3. Regularizaciones activas
    $stmt_reg = $pdo->prepare("SELECT COUNT(*) FROM CLASES_REGULARIZACION WHERE id_maestro = ? AND estatus = 'Activa'");
    $stmt_reg->execute([$id_docente]);
    $regularizaciones_activas = $stmt_reg->fetchColumn();

    // =======================================================
    // NUEVA CONSULTA: HORARIO INTELIGENTE DEL DÍA
    // =======================================================
    date_default_timezone_set('America/Mexico_City');
    $numero_dia_hoy = date('N'); // 1 (Lunes) a 7 (Domingo)
    $hora_actual = date('H:i:s');
    $dias_semana = ['1'=>'Lunes', '2'=>'Martes', '3'=>'Miércoles', '4'=>'Jueves', '5'=>'Viernes', '6'=>'Sábado', '7'=>'Domingo'];
    $nombre_dia_hoy = $dias_semana[$numero_dia_hoy];

    // Busca las clases del maestro programadas para el día de HOY, que aún no han terminado
    $stmt_clases_hoy = $pdo->prepare("
        SELECT c.id_clase, c.id_grupo, hc.hora_inicio, hc.hora_fin, m.nombre_materia, g.nombre_grupo, g.semestre, g.turno 
        FROM horarios_clases hc
        INNER JOIN clases_regulares c ON hc.id_clase = c.id_clase
        INNER JOIN materias m ON c.id_materia = m.id_materia
        INNER JOIN grupos g ON c.id_grupo = g.id_grupo
        WHERE c.id_maestro = ? 
          AND c.estatus = 'Activa' 
          AND hc.dia_semana = ?
          AND hc.hora_fin >= ? -- Oculta las clases que ya terminaron hoy
        ORDER BY hc.hora_inicio ASC
    ");
    $stmt_clases_hoy->execute([$id_docente, $numero_dia_hoy, $hora_actual]);
    $clases_de_hoy = $stmt_clases_hoy->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error obteniendo estadísticas del docente: " . $e->getMessage());
    $total_grupos = 0;
    $total_alumnos = 0;
    $regularizaciones_activas = 0;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#063f2b">
    <title>Portal Docente · SIGE COBAEP 27</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <link rel="stylesheet" href="style_dashboard_docente.css?v=<?php echo time(); ?>">

    <style>
        /* =========================================================
           ESTILOS PARA NUEVOS PANELES (Dashboard Inferior)
           ========================================================= */
        .dashboard-panels {
            display: grid;
            grid-template-columns: 2fr 1fr; /* Panel principal de horario (más ancho) + Panel avisos */
            gap: 20px;
            margin-top: 30px;
        }

        .panel-card {
            background: #ffffff;
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow-sm);
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--line);
            padding-bottom: 15px;
        }

        .panel-header h3 {
            color: var(--ink);
            font-size: 1.15rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }
        
        .panel-header h3 i { color: var(--green); }

        /* LISTA DE CLASES (HORARIO DE HOY) */
        .clase-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f8fafc;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 12px;
            transition: 0.2s ease;
        }
        
        .clase-item:hover {
            border-color: #d1d5db;
            background: #ffffff;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
        }

        .clase-tiempo {
            display: flex;
            flex-direction: column;
            color: var(--muted);
            font-size: 0.85rem;
            font-weight: 700;
            min-width: 100px;
        }
        
        .clase-tiempo span.actual { color: var(--green); }

        .clase-detalles { flex: 1; margin: 0 15px; }
        .clase-detalles h4 { margin: 0 0 4px 0; color: var(--ink); font-size: 1rem; }
        .clase-detalles p { margin: 0; color: var(--muted); font-size: 0.8rem; }
        .clase-detalles span.grupo-badge {
            background: var(--blue-soft); color: var(--blue);
            padding: 3px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: 700; margin-left: 5px;
        }

        .btn-asistencia {
            background: var(--green); color: #fff; border: none; padding: 8px 15px; border-radius: 8px;
            font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: 0.2s; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-asistencia:hover { background: var(--green-800); transform: translateY(-2px); box-shadow: 0 4px 8px rgba(22, 160, 93, 0.2);}

        /* LISTA DE AVISOS Y NOTIFICACIONES */
        .aviso-item {
            display: flex; gap: 12px; padding: 15px 0; border-bottom: 1px dashed var(--line);
        }
        .aviso-item:last-child { border-bottom: none; }
        
        .aviso-icon {
            width: 35px; height: 35px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; flex-shrink: 0;
        }
        .aviso-icon.urgente { background: #fee2e2; color: #ef4444; }
        .aviso-icon.info { background: var(--blue-soft); color: var(--blue); }
        .aviso-icon.tareas { background: var(--orange-soft); color: #d97706; }
        
        .aviso-contenido h4 { margin: 0 0 5px 0; color: var(--ink); font-size: 0.9rem; }
        .aviso-contenido p { margin: 0; color: var(--muted); font-size: 0.75rem; line-height: 1.4; }
        .aviso-fecha { display: block; margin-top: 5px; font-size: 0.7rem; color: #9ca3af; font-weight: 600; }

        @media (max-width: 992px) {
            .dashboard-panels { grid-template-columns: 1fr; }
        }
        @media (max-width: 560px) {
            .clase-item { flex-direction: column; align-items: flex-start; gap: 12px; }
            .btn-asistencia { width: 100%; justify-content: center; }
        }
    </style>
</head>

<body>
    <!-- SCRIPT ANTI-PARPADEO -->
    <script>
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            document.body.classList.add('sidebar-collapsed', 'preload-transitions');
        }
    </script>

    <!-- BARRA LATERAL -->
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
                <a href="dashboard_docente.php" class="nav-item active">
                    <i class="fas fa-house"></i><span>Inicio</span>
                </a>
                <span class="nav-heading">Académico</span>
                <a href="mis_grupos.php" class="nav-item">
                    <i class="fas fa-users-rectangle"></i><span>Mis Grupos</span>
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
                    <div>
                        <strong>COBAEP Plantel 27</strong>
                        <span>Portal Docente</span>
                    </div>
                </div>
                <a href="../logout.php" class="logout">
                    <i class="fas fa-arrow-right-from-bracket"></i><span>Cerrar Sesión</span>
                </a>
            </div>
        </div>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">
        <!-- TOPBAR -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu" id="mobileMenu"><i class="fas fa-bars"></i></button>
                <div>
                    <span class="eyebrow">PORTAL DOCENTE · SIGE</span>
                    <h1>¡Hola, Maestro(a) <?php echo $nombre_usuario; ?>!</h1>
                    <p>Aquí tienes el resumen de tus grupos y actividades académicas.</p>
                </div>
            </div>
            <div class="topbar-right">
                <div class="date-pill"><i class="far fa-calendar"></i><span id="fechaTexto">Cargando...</span></div>
                <button class="notification-button" type="button"><i class="far fa-bell"></i><span class="notification-dot"></span></button>
                
                <div class="profile-dropdown" style="position: relative;">
                    <div class="profile" id="btnPerfil" style="cursor: pointer; display: flex; align-items: center; gap: 10px;">
                        <div class="profile-avatar"><i class="fas fa-chalkboard-user"></i></div>
                        <div class="profile-info">
                            <strong><?php echo $nombre_usuario; ?></strong>
                            <span>Docente</span>
                        </div>
                        <i class="fas fa-chevron-down" style="font-size: 12px; color: #666;"></i>
                    </div>
                    <div class="dropdown-menu-perfil" id="menuPerfil">
                        <a href="perfil.php"><i class="fas fa-user-edit"></i> Mi Perfil</a>
                        <hr style="margin: 0; border: none; border-top: 1px solid #eee;">
                        <a href="../logout.php" style="color: #dc3545;"><i class="fas fa-arrow-right-from-bracket"></i> Cerrar Sesión</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- TARJETAS DE ESTADÍSTICAS -->
        <section class="stats-grid">
            <article class="stat-card groups">
                <div class="stat-icon"><i class="fas fa-layer-group"></i></div>
                <div class="stat-content">
                    <span>Mis Grupos</span>
                    <strong><?php echo $total_grupos; ?></strong>
                    <small>Grupos asignados</small>
                </div>
            </article>
            <article class="stat-card students">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-content">
                    <span>Alumnos</span>
                    <strong><?php echo $total_alumnos; ?></strong>
                    <small>Total en tus clases</small>
                </div>
            </article>
            <article class="stat-card regularizations">
                <div class="stat-icon"><i class="fas fa-file-signature"></i></div>
                <div class="stat-content">
                    <span>Regularizaciones</span>
                    <strong><?php echo $regularizaciones_activas; ?></strong>
                    <small>Procesos activos</small>
                </div>
            </article>
        </section>

        <!-- ACCIONES RÁPIDAS -->
        <section class="section">
            <div class="section-heading">
                <span class="section-kicker">ACCESOS</span>
                <h2>Acciones Rápidas</h2>
            </div>
            <div class="quick-grid">
                <a href="mis_grupos.php" class="quick-card">
                    <div class="quick-top"><div class="quick-icon blue"><i class="fas fa-clipboard-list"></i></div></div>
                    <h3>Consultar Grupos</h3>
                    <p>Revisa la información de los grupos asignados.</p>
                    <span class="quick-link">Ver mis grupos <i class="fas fa-arrow-right"></i></span>
                </a>
                <a href="regularizaciones.php" class="quick-card">
                    <div class="quick-top"><div class="quick-icon orange"><i class="fas fa-folder-open"></i></div></div>
                    <h3>Gestionar Regularizaciones</h3>
                    <p>Crea espacios de recuperación, sube material de apoyo y activa la entrega de trabajos.</p>
                    <span class="quick-link">Ir a regularizaciones <i class="fas fa-arrow-right"></i></span>
                </a>
                <a href="horario.php" class="quick-card">
                    <div class="quick-top"><div class="quick-icon green"><i class="far fa-clock"></i></div></div>
                    <h3>Mis Horarios</h3>
                    <p>Consulta tus horarios de clase asignados para el semestre en curso.</p>
                    <span class="quick-link">Ver horarios <i class="fas fa-arrow-right"></i></span>
                </a>
            </div>
        </section>

        <!-- NUEVOS PANELES (HORARIO DEL DÍA Y NOTIFICACIONES) -->
        <section class="dashboard-panels">
            <!-- PANEL 1: CLASES DE HOY -->
            <div class="panel-card" style="display: flex; flex-direction: column;">
                <div class="panel-header">
                    <h3><i class="fas fa-chalkboard-user"></i> Mis Clases de Hoy</h3>
                    <span class="badge-turno" style="background: var(--green-soft); color: var(--green); padding: 6px 14px; border-radius: 12px; font-size: 0.75rem; font-weight: 700; box-shadow: 0 2px 4px rgba(22, 160, 93, 0.1);">
                        <?php echo $nombre_dia_hoy; ?>
                    </span>
                </div>
                
                <div class="panel-body" style="flex: 1; display: flex; flex-direction: column;">
                    <?php if (empty($clases_de_hoy)): ?>
                        <div style="flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 40px 20px; color: var(--muted); background: #f8fafc; border-radius: 12px; border: 1px dashed #cbd5e1;">
                            <div style="width: 60px; height: 60px; background: #e2e8f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 15px;">
                                <i class="fas fa-mug-hot" style="font-size: 1.5rem; color: #94a3b8;"></i>
                            </div>
                            <h4 style="margin: 0 0 5px 0; color: var(--ink); font-size: 1rem;">Día Libre</h4>
                            <p style="margin: 0; font-size: 0.85rem;">No tienes clases programadas para hoy o ya finalizaron.</p>
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <?php foreach ($clases_de_hoy as $clase_hoy): 
                                $hora_i = date('h:i a', strtotime($clase_hoy['hora_inicio']));
                                $hora_f = date('h:i a', strtotime($clase_hoy['hora_fin']));
                            ?>
                                <!-- TARJETA DE CLASE INDIVIDUAL -->
                                <div class="clase-item" style="display: flex; align-items: center; justify-content: space-between; background: #ffffff; border: 1px solid var(--line); border-left: 4px solid var(--green); border-radius: 12px; padding: 15px; transition: 0.2s ease; box-shadow: 0 2px 5px rgba(0,0,0,0.02);">
                                    
                                    <!-- 1. BLOQUE DE HORA -->
                                    <div class="clase-tiempo" style="min-width: 105px; display: flex; flex-direction: column;">
                                        <span class="actual" style="color: var(--green); font-weight: 800; font-size: 0.85rem;">
                                            <i class="fas fa-circle" style="font-size: 8px; margin-right:4px;"></i> <?php echo $hora_i; ?>
                                        </span>
                                        <span style="padding-left: 14px; color: #94a3b8; font-size: 0.75rem; margin-top: 2px; font-weight: 600;">
                                            <?php echo $hora_f; ?>
                                        </span>
                                    </div>
                                    
                                    <!-- 2. BLOQUE DE MATERIA Y GRUPO -->
                                    <div class="clase-detalles" style="flex: 1; margin: 0 15px;">
                                        <h4 style="margin: 0 0 6px 0; color: var(--ink); font-size: 1.05rem; font-weight: 800; letter-spacing: -0.02em;">
                                            <?php echo htmlspecialchars($clase_hoy['nombre_materia']); ?>
                                        </h4>
                                        <p style="margin: 0; display: flex; align-items: center; gap: 8px;">
                                            <span class="grupo-badge" style="background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; padding: 4px 8px; border-radius: 6px; font-size: 0.75rem; font-weight: 700;">
                                                <i class="fas fa-users" style="margin-right: 4px;"></i> Grupo <?php echo htmlspecialchars($clase_hoy['semestre']) . 'º "' . htmlspecialchars($clase_hoy['nombre_grupo']) . '"'; ?>
                                            </span>
                                            <span style="font-size: 0.75rem; color: #64748b; font-weight: 600;">
                                                <i class="far fa-sun"></i> <?php echo htmlspecialchars($clase_hoy['turno']); ?>
                                            </span>
                                        </p>
                                    </div>
                                    
                                    <!-- 3. BOTÓN DE ACCIÓN -->
                                    <a href="gestionar_aula.php?id_grupo=<?php echo $clase_hoy['id_grupo']; ?>&abrir_lista=<?php echo $clase_hoy['id_clase']; ?>" class="btn-asistencia" style="background: var(--green); color: #fff; border: none; padding: 10px 15px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s;">
                                        <i class="fas fa-clipboard-user"></i> Lista
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PANEL 2: NOTIFICACIONES Y TAREAS -->
            <div class="panel-card" style="display: flex; flex-direction: column;">
                <div class="panel-header">
                    <h3><i class="fas fa-bell"></i> Avisos y Tareas</h3>
                </div>
                <div class="panel-body" style="flex: 1; display: flex; flex-direction: column; justify-content: center;">
                    <!-- Aviso Urgente -->
                    <div class="aviso-item">
                        <div class="aviso-icon urgente"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="aviso-contenido">
                            <h4>Cierre de Actas Parciales</h4>
                            <p>El sistema para subir calificaciones del 1er Parcial se cerrará este viernes a las 23:59 hrs.</p>
                            <span class="aviso-fecha">Hace 2 horas • Dirección Académica</span>
                        </div>
                    </div>
                    
                    <!-- Notificación de Tareas -->
                    <div class="aviso-item">
                        <div class="aviso-icon tareas"><i class="fas fa-file-alt"></i></div>
                        <div class="aviso-contenido">
                            <h4>Trabajos pendientes por revisar</h4>
                            <p>Tienes <strong>5</strong> entregas nuevas de alumnos en el espacio de Regularización "Taller de Lectura".</p>
                            <span class="aviso-fecha">Hace 5 horas • Sistema</span>
                        </div>
                    </div>

                    <!-- Información General -->
                    <div class="aviso-item" style="border-bottom: none; padding-bottom: 0;">
                        <div class="aviso-icon info"><i class="fas fa-info"></i></div>
                        <div class="aviso-contenido">
                            <h4>Reunión de Academia</h4>
                            <p>Se les recuerda a los docentes del área de Ciencias Exactas la reunión programada para el día miércoles.</p>
                            <span class="aviso-fecha">Ayer • Prefectura</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </main>

    <script src="dashboard_maestro.js"></script>
</body>
</html>