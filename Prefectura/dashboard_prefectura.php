<?php
session_start();
require_once '../config/database.php'; 

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'prefectos') {
    header("Location: ../login.php");
    exit();
}

date_default_timezone_set('America/Mexico_City');
$fecha_actual = date('d/m/Y');

try {
    $stmt_alumnos = $pdo->query("SELECT COUNT(*) FROM alumnos");
    $total_alumnos = $stmt_alumnos->fetchColumn();

    $stmt_puntuales = $pdo->query("SELECT COUNT(*) FROM asistencias 
                                   WHERE DATE(fecha_hora_escaneo) = CURDATE() 
                                   AND estatus IN ('Puntual', 'Retardo Justificado')");
    $puntuales = $stmt_puntuales->fetchColumn();

    $stmt_retardos = $pdo->query("SELECT COUNT(*) FROM asistencias 
                                  WHERE DATE(fecha_hora_escaneo) = CURDATE() 
                                  AND estatus = 'Retardo'");
    $retardos = $stmt_retardos->fetchColumn();

    $inasistencias = $total_alumnos - ($puntuales + $retardos);
    if ($inasistencias < 0) $inasistencias = 0;

    $stmt_ultimos = $pdo->query("
        SELECT a.fecha_hora_escaneo, al.matricula, al.nombre, al.apellido_paterno, a.estatus 
        FROM asistencias a
        INNER JOIN alumnos al ON a.id_alumno = al.id_alumno
        WHERE DATE(a.fecha_hora_escaneo) = CURDATE()
        ORDER BY a.fecha_hora_escaneo DESC
        LIMIT 15
    ");
    $ultimos_registros = $stmt_ultimos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $total_alumnos = 0;
    $puntuales = 0;
    $retardos = 0;
    $inasistencias = 0;
    $ultimos_registros = [];
}

$nombre_usuario = isset($_SESSION['nombre']) 
    ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') 
    : 'Prefecto';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#063f2b">
    <title>Panel de Prefectura · COBAEP Plantel 27</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <link rel="stylesheet" href="../admin/style_dashboard_admin.css">
    <link rel="stylesheet" href="../Prefectura/style_dashboard_prefectura.css">
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
                <a href="dashboard_prefectura.php" class="nav-item active">
                    <i class="fas fa-shield-halved"></i>
                    <span>Control de Acceso</span>
                </a>
                <a href="historial_asistencias.php" class="nav-item">
                    <i class="fas fa-history"></i>
                    <span>Historial y Reportes</span>
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

        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu" id="mobileMenu" type="button" aria-label="Abrir menú">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <span class="eyebrow">PREFECTURA · SIGE</span>
                    <h1>¡Bienvenido, <?php echo $nombre_usuario; ?>!</h1>
                    <p>Panel de control de asistencias diarias.</p>
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

        <section class="stats-grid" aria-label="Resumen">
            <article class="stat-card teachers">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <span>Total Alumnos</span>
                    <strong><?php echo $total_alumnos; ?></strong>
                    <small>Registrados en sistema</small>
                </div>
            </article>

            <article class="stat-card students">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <span>Puntuales</span>
                    <strong><?php echo $puntuales; ?></strong>
                    <small>Ingresos a tiempo</small>
                </div>
            </article>

            <article class="stat-card attendance">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <span>Retardos</span>
                    <strong><?php echo $retardos; ?></strong>
                    <small>Ingresos con demora</small>
                </div>
            </article>

            <article class="stat-card absent">
                <div class="stat-icon">
                    <i class="fas fa-user-xmark"></i>
                </div>
                <div class="stat-content">
                    <span>Pendientes / Faltas</span>
                    <strong><?php echo $inasistencias; ?></strong>
                    <small>Aún no ingresan</small>
                </div>
            </article>
        </section>

        <section class="section">
            <div class="section-heading">
                <div>
                    <span class="section-kicker">HERRAMIENTAS</span>
                    <h2>Acciones de registro</h2>
                </div>
            </div>

            <div class="action-grid">
                <a href="escaner_qr.php" class="action-card scanner-btn">
    <div class="action-icon green">
        <i class="fas fa-qrcode"></i>
    </div>
    <div class="action-text">
        <h3>Escáner QR</h3>
        <p>Captura credenciales rápidamente.</p>
    </div>
    <i class="fas fa-arrow-right action-arrow"></i>
           </a>
                

                <a href="#" class="action-card suspension-btn" onclick="document.getElementById('modalSuspension').style.display='flex'">
                    <div class="action-icon red">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <div class="action-text">
                        <h3>Suspender Faltas</h3>
                        <p>Configura días sin clases.</p>
                    </div>
                    <i class="fas fa-arrow-right action-arrow"></i>
                </a>

                <a href="#" class="action-card manual-btn" onclick="abrirModal()">
                    <div class="action-icon orange">
                        <i class="fas fa-keyboard"></i>
                    </div>
                    <div class="action-text">
                        <h3>Registro Manual</h3>
                        <p>Ingreso en casos excepcionales.</p>
                    </div>
                    <i class="fas fa-arrow-right action-arrow"></i>
                </a>
            </div>
        </section>

        <section class="dashboard-bottom" style="grid-template-columns: 1fr;">
            <article class="overview-card" style="padding: 0; overflow: hidden;">
                <div class="card-heading" style="padding: 24px 24px 0;">
                    <div>
                        <span class="section-kicker">MONITOREO</span>
                        <h2>Últimos Ingresos Detectados</h2>
                    </div>
                    <button class="btn btn-cancel" onclick="location.reload();" style="padding: 0.5rem 1rem; font-size: 0.75rem;">
                        <i class="fas fa-sync-alt"></i> Actualizar
                    </button>
                </div>

                <div class="table-responsive" style="margin-bottom: 0;">
                    <table>
                        <thead>
                            <tr>
                                <th>Hora</th>
                                <th>Matrícula</th>
                                <th>Nombre del Alumno</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ultimos_registros)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; color: var(--muted); padding: 3rem;">
                                        Aún no hay registros de asistencia para el día de hoy.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($ultimos_registros as $registro): 
                                    $hora_formateada = date('h:i A', strtotime($registro['fecha_hora_escaneo']));
                                    $estatus_min = strtolower($registro['estatus'] ?? '');
    
                                    $clase_badge = 'status-puntual';
                                    $clase_fila = '';
                                    $texto_estatus = !empty($registro['estatus']) ? $registro['estatus'] : 'Falta';

                                    if ($estatus_min === 'retardo') {
                                        $clase_badge = 'status-retardo';
                                    } elseif ($estatus_min === 'falta' || empty($estatus_min)) {
                                        $clase_badge = 'status-falta';
                                        $clase_fila = 'fila-falta';
                                        $texto_estatus = 'Falta';
                                    } elseif ($estatus_min === 'retardo justificado') {
                                        $clase_badge = 'status-puntual'; 
                                        $texto_estatus = 'Puntual (Justificado)'; 
                                    } elseif ($estatus_min === 'falta justificada') {
                                        $clase_badge = 'status-justificada';
                                    }
    
                                    $nombre_completo = htmlspecialchars($registro['apellido_paterno'] . ', ' . $registro['nombre']);
                                ?>
                                <tr class="<?php echo $clase_fila; ?>">
                                    <td><strong><?php echo $hora_formateada; ?></strong></td>
                                    <td><?php echo htmlspecialchars($registro['matricula']); ?></td>
                                    <td><?php echo $nombre_completo; ?></td>
                                    <td>
                                        <span class="status-badge-table <?php echo $clase_badge; ?>">
                                            <?php echo htmlspecialchars($texto_estatus); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <footer class="footer" style="margin-top: 2rem;">
            <span>© 2026 COBAEP Plantel 27</span>
            <span>·</span>
            <span>SIGE · Sistema de Información y Gestión Educativa</span>
        </footer>

    </main>

    <!-- MODAL DE SUSPENSIÓN DE CLASES -->
    <div class="modal-overlay" id="modalSuspension">
        <div class="modal">
            <h3><i class="fas fa-calendar-times" style="color: #ef4444;"></i> Programar Suspensión</h3>
            <p>El sistema no pondrá faltas automáticas durante el rango de fechas que elijas. Al terminar, se reactivará solo.</p>
            
            <form action="procesos/registrar_suspension.php" method="POST">
                <div class="form-group">
                    <label>Motivo de la suspensión</label>
                    <select name="motivo" class="form-control" required>
                        <option value="Consejo Técnico">Consejo Técnico</option>
                        <option value="Suspensión oficial">Día Festivo / Suspensión oficial</option>
                        <option value="Vacaciones">Vacaciones</option>
                        <option value="Fuerza Mayor">Causa de Fuerza Mayor</option>
                    </select>
                </div>
                <div class="form-group" style="display: flex; gap: 10px;">
                    <div style="flex: 1;">
                        <label>Fecha de Inicio</label>
                        <input type="date" name="fecha_inicio" class="form-control" required>
                    </div>
                    <div style="flex: 1;">
                        <label>Fecha de Fin</label>
                        <input type="date" name="fecha_fin" class="form-control" required>
                    </div>
                </div>  
                <div class="modal-actions">
                    <button type="button" class="btn btn-cancel" onclick="document.getElementById('modalSuspension').style.display='none'">Cancelar</button>
                    <button type="submit" class="btn btn-primary" style="background: #ef4444;">Confirmar Suspensión</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL DE REGISTRO MANUAL -->
    <div class="modal-overlay" id="modalManual">
        <div class="modal">
            <h3>Registro Manual</h3>
            <p>Utiliza esta función únicamente en casos excepcionales (credencial dañada o sistema caído).</p>
            
            <form action="procesos/registro_manual.php" method="POST">
                <div class="form-group">
                    <label for="matricula_manual"><i class="fas fa-id-card"></i> Número de Matrícula</label>
                    <input type="text" id="matricula_manual" name="matricula" class="form-control" placeholder="Ej. 22014567" required>
                </div>
                
                <div class="form-group">
                    <label for="estado_asistencia"><i class="fas fa-check-circle"></i> Estado a registrar</label>
                    <select id="estado_asistencia" name="estado" class="form-control" required>
                      <option value="asistencia">Asistencia (Puntual)</option>
                      <option value="retardo">Retardo</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="tipo_movimiento"><i class="fas fa-exchange-alt"></i> Acción</label>
                    <select id="tipo_movimiento" name="tipo_movimiento" class="form-control" required>
                        <option value="entrada">Registrar Entrada</option>
                        <option value="salida">Registrar Salida</option>
                    </select>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-cancel" onclick="cerrarModal()">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Registro</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../Prefectura/procesos/dashboard_prefecturas.js"></script>
</body>
</html>