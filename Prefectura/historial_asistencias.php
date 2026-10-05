<?php
session_start();
require_once '../config/database.php'; 

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'prefectos') {
    header("Location: ../login.php");
    exit();
}

$nombre_usuario = isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') : 'Prefecto';

$filtro_matricula = trim($_GET['matricula'] ?? '');
$filtro_fecha = trim($_GET['fecha'] ?? '');

$query = "
    SELECT a.id_asistencia, a.fecha_hora_escaneo, a.fecha_hora_salida, a.tipo_registro, al.matricula, al.nombre, al.apellido_paterno, a.estatus, al.semestre, al.turno 
    FROM asistencias a
    INNER JOIN alumnos al ON a.id_alumno = al.id_alumno
    WHERE 1=1
";
$params = [];

if (!empty($filtro_matricula)) {
    $query .= " AND al.matricula LIKE :matricula";
    $params[':matricula'] = '%' . $filtro_matricula . '%';
}

if (!empty($filtro_fecha)) {
    $query .= " AND DATE(a.fecha_hora_escaneo) = :fecha";
    $params[':fecha'] = $filtro_fecha;
}

$query .= " ORDER BY a.fecha_hora_escaneo DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $historial = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $historial = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Asistencias · COBAEP 27</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <link rel="stylesheet" href="../admin/style_dashboard_admin.css">
    <link rel="stylesheet" href="../Prefectura/style_dashboard_prefectura.css">
    <link rel="stylesheet" href="../Prefectura/style_historial_asistencias.css">

 
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
            
             <nav class="navigation" aria-label="Navegación principal">
    <span class="nav-heading">Menú principal</span>
    <a href="dashboard_prefectura.php" class="nav-item">
        <i class="fas fa-shield-halved"></i>
        <span>Control de Acceso</span>
    </a>
    <a href="historial_asistencias.php" class="nav-item">
        <i class="fas fa-history"></i>
        <span>Historial y Reportes</span>
    </a>
    <a href="creacion_reportes.php" class="nav-item">
        <i class="fa-solid fa-file-lines"></i>
        <span>Creacion de Reporte de Conducta</span>
    </a>  
    <!-- NUEVO MÓDULO DE EXPEDIENTES -->
    <a href="expediente_alumnos.php" class="nav-item">
        <i class="fas fa-folder-open"></i>
        <span>Expediente de Alumnos</span>
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

    <main class="main-content">
        <!-- TOPBAR ACTUALIZADA PARA IGUALAR AL DASHBOARD -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu" id="mobileMenu" type="button" aria-label="Abrir menú">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <span class="eyebrow">PREFECTURA · REPORTES</span>
                    <h1>Historial de Asistencias</h1>
                    <p>Busca y gestiona el registro de entradas y salidas por matrícula o fecha.</p>
                </div>
            </div>

            <div class="topbar-right">
                <div class="date-pill">
                    <i class="far fa-calendar"></i>
                    <span id="fechaTexto"><?php echo date('d/m/Y H:i'); ?></span>
                </div>

                <div class="profile">
                    <div class="profile-avatar">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="profile-info">
                        <strong><?php echo $nombre_usuario; ?></strong>
                        <span>Prefecto</span>
                    </div>
                </div>
            </div>
        </header>

        <form method="GET" class="filter-bar">
            <div class="filter-group">
                <label>Buscar por Matrícula</label>
                <input type="text" name="matricula" class="filter-input" placeholder="Ej. 22014567" value="<?php echo htmlspecialchars($filtro_matricula); ?>">
            </div>
            <div class="filter-group">
                <label>Filtrar por Fecha</label>
                <input type="date" name="fecha" class="filter-input" value="<?php echo htmlspecialchars($filtro_fecha); ?>">
            </div>
            <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Buscar</button>
            <?php if (!empty($filtro_matricula) || !empty($filtro_fecha)): ?>
                <a href="historial_asistencias.php" class="btn-clear">Limpiar</a>
            <?php endif; ?>
        </form>

        <section class="overview-card" style="padding: 0; overflow: hidden;">
            <div class="table-responsive" style="margin-top: 0;">
                <table>
                    <thead>
                        <tr>
                            <th>Entrada</th>
                            <th>Salida</th>
                            <th>Matrícula</th>
                            <th>Alumno</th>
                            <th>Grado y Grupo</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($historial)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--muted); padding: 3rem;">
                                    No se encontraron registros que coincidan con la búsqueda.
                                </td>
                            </tr>
                        <?php else: 
                            $fecha_actual_agrupacion = ''; 
                            
                            foreach ($historial as $row): 
                                $fecha_fila_pura = date('Y-m-d', strtotime($row['fecha_hora_escaneo']));
                                
                                if ($fecha_actual_agrupacion !== $fecha_fila_pura) {
                                    $fecha_actual_agrupacion = $fecha_fila_pura;
                                    $fecha_formateada_titulo = date('d/m/Y', strtotime($row['fecha_hora_escaneo']));
                                    
                                    echo '<tr class="date-divider">';
                                    echo '<td colspan="7"><i class="far fa-calendar-alt" style="margin-right: 8px;"></i> Asistencias del día: ' . $fecha_formateada_titulo . '</td>';
                                    echo '</tr>';
                                }
                                $hora_entrada = date('h:i A', strtotime($row['fecha_hora_escaneo']));

// LÓGICA ACTUALIZADA DE COLORES Y ESTADOS
$estado_str = strtolower($row['estatus'] ?? '');
$clase_badge = 'status-puntual'; 
$clase_fila = '';
$texto_estatus = !empty($row['estatus']) ? $row['estatus'] : 'Falta';

if ($estado_str === 'retardo') {
    $clase_badge = 'status-retardo';
} elseif ($estado_str === 'falta' || empty($estado_str)) {
    $clase_badge = 'status-falta';
    $clase_fila = 'fila-falta';
    $texto_estatus = 'Falta';
} elseif ($estado_str === 'suspendido') {
    $clase_badge = 'status-suspendido';
    $clase_fila = 'fila-suspendido'; // <-- NUEVA CLASE PARA LA FILA
    $texto_estatus = 'Suspendido';
} elseif ($estado_str === 'retardo justificado') {
    $clase_badge = 'status-puntual';
    $texto_estatus = 'Puntual (Just.)';
} elseif ($estado_str === 'falta justificada') {
    $clase_badge = 'status-justificada';
}
                                

                                
                        ?>
                           <tr class="<?php echo $clase_fila; ?>">
    <td><strong><?php echo $hora_entrada; ?></strong></td>
    <td>
        <!-- ... (código de salida) ... -->
    </td>
    <td><?php echo htmlspecialchars($row['matricula']); ?></td>
    <td><?php echo htmlspecialchars($row['apellido_paterno'] . ', ' . $row['nombre']); ?></td>
    <td><?php echo htmlspecialchars($row['grado_grupo'] ?? 'Sin asignar'); ?></td>
    <td>
        <span class="status-badge-table <?php echo $clase_badge; ?>">
            <?php echo htmlspecialchars($texto_estatus); ?>
        </span>
    </td>
    <td>
                                    <?php if ($estado_str === 'falta' || $estado_str === 'retardo' || empty($estado_str)): ?>
                                        <form action="procesos/justificar.php" method="POST" style="margin: 0;">
                                            <input type="hidden" name="id_asistencia" value="<?php echo $row['id_asistencia']; ?>">
                                            <button type="submit" class="btn-justificar" onclick="return confirm('¿Confirmas que deseas justificar este registro?');">
                                                <i class="fas fa-file-signature"></i> Justificar
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="color: var(--muted); font-size: 0.8rem;">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script src="../Prefectura/procesos/dashboard_prefecturas.js"></script>
</body>
</html>