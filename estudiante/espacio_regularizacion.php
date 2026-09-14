<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'estudiante') {
    header("Location: ../login.php");
    exit();
}

$id_alumno = $_SESSION['id_usuario'];
$nombre_usuario = isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') : 'Alumno';
$matricula = isset($_SESSION['matricula']) ? htmlspecialchars($_SESSION['matricula'], ENT_QUOTES, 'UTF-8') : '';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: actividades.php");
    exit();
}
$id_clase = (int)$_GET['id'];

try {
    $stmt_check = $pdo->prepare("SELECT * FROM CLASE_ALUMNO WHERE id_clase = ? AND id_alumno = ?");
    $stmt_check->execute([$id_clase, $id_alumno]);
    if (!$stmt_check->fetch()) {
        header("Location: actividades.php?error=No tienes acceso a este espacio.");
        exit();
    }

    $stmt_clase = $pdo->prepare("
        SELECT c.*, m.nombre AS maestro_nombre, m.apellido_paterno AS maestro_apellido 
        FROM CLASES_REGULARIZACION c
        JOIN MAESTROS m ON c.id_maestro = m.id_maestro
        WHERE c.id_clase = ?
    ");
    $stmt_clase->execute([$id_clase]);
    $clase = $stmt_clase->fetch(PDO::FETCH_ASSOC);

    $stmt_bloques = $pdo->prepare("SELECT * FROM BLOQUES_REGULARIZACION WHERE id_clase = ? ORDER BY id_bloque ASC");
    $stmt_bloques->execute([$id_clase]);
    $bloques = $stmt_bloques->fetchAll(PDO::FETCH_ASSOC);

    $stmt_act = $pdo->prepare("SELECT * FROM ACTIVIDADES_REGULARIZACION WHERE id_clase = ? ORDER BY fecha_publicacion DESC");
    $stmt_act->execute([$id_clase]);
    $actividades = $stmt_act->fetchAll(PDO::FETCH_ASSOC);

    $stmt_entrega = $pdo->prepare("SELECT * FROM ENTREGAS_REGULARIZACION WHERE id_clase = ? AND id_alumno = ? LIMIT 1");
    $stmt_entrega->execute([$id_clase, $id_alumno]);
    $mi_entrega = $stmt_entrega->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error cargando el espacio del alumno: " . $e->getMessage());
    header("Location: actividades.php?error=Error de conexión.");
    exit();
}

$bg_image = "linear-gradient(145deg, #10251d, #063f2b)";
if (!empty($clase['imagen_portada'])) {
    $ruta_img = '../' . $clase['imagen_portada'];
    $bg_image = "linear-gradient(rgba(16, 37, 29, 0.5), rgba(16, 37, 29, 0.8)), url('" . htmlspecialchars($ruta_img) . "')";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($clase['nombre_materia']); ?> · SIGE COBAEP</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <link rel="stylesheet" href="style_dashboard_estud.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="style_espacio_regularizacion.css?v=<?php echo time(); ?>">
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-inner">
            <div class="brand">
                <img src="../img/LogoCobaep.png" alt="Logo" class="brand-logo">
                <div class="brand-copy"><strong>SIGE<span>-Cobaep</span></strong><small>Plantel 27</small></div>
            </div>
            <nav class="navigation">
                <span class="nav-heading">MENÚ PRINCIPAL</span>
                <a href="dashboard_estud.php" class="nav-item"><i class="fas fa-house"></i><span>Inicio</span></a>
                <a href="actividades.php" class="nav-item active"><i class="fas fa-book-open-reader"></i><span>Regularizaciones</span></a>
            </nav>
            <div class="sidebar-bottom">
                <a href="../logout.php" class="logout"><i class="fas fa-arrow-right-from-bracket"></i><span>Cerrar Sesión</span></a>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <div class="topbar-title-wrapper">
                    <h1 class="topbar-title"><i class="fas fa-folder-open icon-green"></i> Aula Virtual</h1>
                </div>
            </div>
        </header>

        <div class="layout-espacio">
            
            <div class="hero-estudiante" style="background-image: <?php echo $bg_image; ?>;">
                <div class="hero-content">
                    <a href="actividades.php" class="btn-back"><i class="fas fa-arrow-left"></i> Volver a mis materias</a>
                    <h1 class="hero-title"><?php echo htmlspecialchars($clase['nombre_materia']); ?></h1>
                    <p class="hero-desc"><?php echo nl2br(htmlspecialchars($clase['descripcion'])); ?></p>
                </div>
                <div class="hero-footer">
                    <span class="badge-profesor"><i class="fas fa-chalkboard-user"></i> Prof. <?php echo htmlspecialchars($clase['maestro_apellido']); ?></span>
                </div>
            </div>

            <div class="col-main">
                
                <?php if(isset($_GET['exito'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['exito']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if(isset($_GET['error'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
                    </div>
                <?php endif; ?>

                <h2 class="section-title">Contenido de la materia</h2>

                <?php if(empty($bloques) && empty($actividades)): ?>
                    <div class="empty-state-materiales">
                        <i class="fas fa-folder-open empty-icon"></i>
                        <span>El maestro aún no ha subido material.</span>
                    </div>
                <?php endif; ?>

                <!-- BLOQUES -->
                <?php foreach($bloques as $bloque): ?>
                    <div class="bloque-box">
                        <div class="bloque-header">
                            <i class="fas fa-bookmark icon-green"></i> <?php echo htmlspecialchars($bloque['titulo_bloque']); ?>
                        </div>
                        <div class="bloque-body">
                            <?php 
                            $acts_bloque = array_filter($actividades, function($a) use ($bloque) { return $a['id_bloque'] == $bloque['id_bloque']; });
                            if(empty($acts_bloque)): ?>
                                <p class="text-muted italic">No hay archivos en este bloque.</p>
                            <?php else: ?>
                                <?php foreach($acts_bloque as $act): ?>
                                    
                                    <?php 
                                        // Extraer y limpiar el nombre del archivo original
                                        $nombre_archivo = '';
                                        if(!empty($act['archivo_ruta'])) {
                                            $nombre_archivo = basename($act['archivo_ruta']);
                                            // Le quitamos el código largo que le pone el servidor (mat_XXXXX_)
                                            $nombre_archivo = preg_replace('/^mat_[a-zA-Z0-9]+_/', '', $nombre_archivo);
                                        }
                                    ?>
                                    <div class="mat-item">
                                        <div class="mat-icon"><i class="fas fa-file-pdf"></i></div>
                                        
                                        <div class="mat-info">
                                            <h4 class="mat-title"><?php echo htmlspecialchars($act['titulo']); ?></h4>
                                            
                                            <?php if(!empty($act['descripcion'])): ?>
                                                <p class="mat-desc"><?php echo nl2br(htmlspecialchars($act['descripcion'])); ?></p>
                                            <?php endif; ?>
                                            
                                            <!-- NUEVO: Etiqueta con el nombre del archivo -->
                                            <?php if(!empty($nombre_archivo)): ?>
                                                <span class="mat-filename">
                                                    <i class="fas fa-paperclip"></i> <?php echo htmlspecialchars($nombre_archivo); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if(!empty($act['archivo_ruta'])): ?>
                                            <a href="../<?php echo htmlspecialchars($act['archivo_ruta']); ?>" target="_blank" class="btn-download">
                                                <i class="fas fa-download"></i> Ver Archivo
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- MATERIAL GENERAL -->
                <?php 
                $acts_generales = array_filter($actividades, function($a) { return empty($a['id_bloque']); });
                if(!empty($acts_generales)): 
                ?>
                    <h3 class="general-title">Material General</h3>
                    <?php foreach($acts_generales as $act): ?>
                        
                        <?php 
                            // Extraer y limpiar el nombre del archivo original
                            $nombre_archivo = '';
                            if(!empty($act['archivo_ruta'])) {
                                $nombre_archivo = basename($act['archivo_ruta']);
                                // Le quitamos el código largo que le pone el servidor (mat_XXXXX_)
                                $nombre_archivo = preg_replace('/^mat_[a-zA-Z0-9]+_/', '', $nombre_archivo);
                            }
                        ?>
                        <div class="mat-item">
                            <div class="mat-icon"><i class="fas fa-file-pdf"></i></div>
                            
                            <div class="mat-info">
                                <h4 class="mat-title"><?php echo htmlspecialchars($act['titulo']); ?></h4>
                                
                                <?php if(!empty($act['descripcion'])): ?>
                                    <p class="mat-desc"><?php echo nl2br(htmlspecialchars($act['descripcion'])); ?></p>
                                <?php endif; ?>
                                
                                <!-- NUEVO: Etiqueta con el nombre del archivo -->
                                <?php if(!empty($nombre_archivo)): ?>
                                    <span class="mat-filename">
                                        <i class="fas fa-paperclip"></i> <?php echo htmlspecialchars($nombre_archivo); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if(!empty($act['archivo_ruta'])): ?>
                                <a href="../<?php echo htmlspecialchars($act['archivo_ruta']); ?>" target="_blank" class="btn-download">
                                    <i class="fas fa-download"></i> Ver Archivo
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>

            <!-- PANEL LATERAL DE ENTREGAS -->
            <div class="col-side">
                <div class="side-panel">
                    <h3 class="panel-title"><i class="fas fa-upload icon-green"></i> Tu Trabajo</h3>
                    
                    <?php if ($mi_entrega): ?>
                        <div class="status-entrega status-entregado">
                            <i class="fas fa-check-circle"></i> Tarea Entregada
                        </div>
                        <p class="entrega-date">Enviado el: <?php echo date('d/m/Y h:i a', strtotime($mi_entrega['fecha_entrega'])); ?></p>
                        <a href="../<?php echo htmlspecialchars($mi_entrega['archivo_ruta']); ?>" target="_blank" class="btn-subir outline">
                            <i class="fas fa-file"></i> Ver archivo enviado
                        </a>
                    
                    <?php else: ?>
                        <?php if ($clase['permitir_entregas'] == 1): ?>
                            
                            <div class="status-entrega status-faltante">
                                <i class="fas fa-exclamation-circle"></i> Tarea Asignada
                            </div>
                            
                            <form action="../procesos/subir_trabajo.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="id_clase" value="<?php echo $id_clase; ?>">
                                
                                <div class="form-group-upload">
                                    <label class="upload-label">Adjuntar archivo (PDF, DOCX)</label>
                                    <input type="file" name="archivo_trabajo" required class="input-file">
                                </div>
                                <button type="submit" class="btn-subir primary">
                                    <i class="fas fa-paper-plane"></i> Entregar Trabajo
                                </button>
                            </form>

                        <?php else: ?>
                            <div class="status-entrega status-bloqueado">
                                <i class="fas fa-lock"></i> Entregas deshabilitadas
                            </div>
                            <p class="text-center text-muted text-sm">El maestro actualmente no está recibiendo trabajos por la plataforma.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>

    <script src="dashboard_estudiante.js"></script>
</body>
</html>