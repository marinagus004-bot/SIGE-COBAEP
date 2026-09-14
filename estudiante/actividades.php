<?php
session_start();
require_once '../config/database.php';

// Validar que el usuario haya iniciado sesión y sea estudiante
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'estudiante') {
    header("Location: ../login.php");
    exit();
}

$id_alumno = $_SESSION['id_usuario'];
$nombre_usuario = isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') : 'Alumno';
$matricula = isset($_SESSION['matricula']) ? htmlspecialchars($_SESSION['matricula'], ENT_QUOTES, 'UTF-8') : 'Sin Matrícula';

// Obtener las clases de regularización a las que está inscrito el alumno
try {
    $stmt = $pdo->prepare("
        SELECT cr.*, m.nombre AS maestro_nombre, m.apellido_paterno AS maestro_apellido 
        FROM CLASES_REGULARIZACION cr
        INNER JOIN CLASE_ALUMNO ca ON cr.id_clase = ca.id_clase
        LEFT JOIN MAESTROS m ON cr.id_maestro = m.id_maestro
        WHERE ca.id_alumno = ? AND cr.estatus = 'Activa'
        ORDER BY cr.fecha_inicio DESC
    ");
    $stmt->execute([$id_alumno]);
    $mis_regularizaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error cargando regularizaciones del alumno: " . $e->getMessage());
    $mis_regularizaciones = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Regularizaciones · SIGE COBAEP 27</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
   <link rel="stylesheet" href="style_dashboard_estud.css?v=<?php echo time(); ?>">
</head>
<body>

    <!-- ================= BARRA LATERAL (Basada en tu captura) ================= -->
    <aside class="sidebar">
        <div class="sidebar-inner">
            <div class="brand">
                <img src="../img/LogoCobaep.png" alt="Logo" class="brand-logo">
                <div class="brand-copy">
                    <strong>SIGE<span>-Cobaep</span></strong>
                    <small>Plantel 27</small>
                </div>
            </div>

            <nav class="navigation">
                <span class="nav-heading">MENÚ PRINCIPAL</span>
                <a href="dashboard_estud.php" class="nav-item"><i class="fas fa-house"></i><span>Inicio</span></a>
                <a href="perfil.php" class="nav-item"><i class="fas fa-user"></i><span>Mi Perfil</span></a>
                <a href="asistencias.php" class="nav-item"><i class="far fa-calendar-check"></i><span>Mis Asistencias</span></a>
                <a href="horario_clases.php" class="nav-item"><i class="far fa-calendar-alt"></i><span>Horario de Clases</span></a>
                
                <!-- MARCADO COMO ACTIVO -->
                <a href="actividades.php" class="nav-item active"><i class="fas fa-book-open-reader"></i><span>Actividades Escolares</span></a>
                
                <span class="nav-heading">INFORMACIÓN</span>
                <a href="avisos.php" class="nav-item"><i class="far fa-bell"></i><span>Avisos</span></a>
                <a href="documentos.php" class="nav-item"><i class="far fa-file-alt"></i><span>Documentos</span></a>
            </nav>

            <div class="sidebar-bottom">
                <a href="../logout.php" class="logout"><i class="fas fa-arrow-right-from-bracket"></i><span>Cerrar Sesión</span></a>
            </div>
        </div>
    </aside>

    <!-- ================= CONTENIDO PRINCIPAL ================= -->
    <main class="main-content">
        
        <!-- TOPBAR (Basada en tu captura) -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu"><i class="fas fa-bars"></i></button>
                <div>
                    <h1 style="display:flex; align-items:center; gap:10px;">
                        <div style="background:var(--green); color:#fff; width:35px; height:35px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1rem;"><i class="fas fa-book-open"></i></div>
                        Mis Regularizaciones
                    </h1>
                    <p>Consulta tus actividades asignadas para recuperar calificación.</p>
                </div>
            </div>
            <div class="topbar-right">
                <div class="date-pill">
                    <i class="far fa-calendar"></i>
                    <span><?php echo date('d/m/Y, h:i a'); ?></span>
                </div>
                <div class="profile-pill" style="display:flex; align-items:center; gap:10px; background:#fff; border:1px solid var(--line); padding:5px 15px 5px 5px; border-radius:30px;">
                    <div style="background:#f1f5f9; color:#475569; width:35px; height:35px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700;">
                        <?php echo substr($nombre_usuario, 0, 2); ?>
                    </div>
                    <div style="line-height:1.2;">
                        <strong style="display:block; font-size:0.85rem; color:var(--ink);"><?php echo $nombre_usuario; ?></strong>
                        <span style="font-size:0.7rem; color:var(--muted);"><?php echo $matricula; ?></span>
                    </div>
                    <i class="fas fa-chevron-down" style="color:var(--muted); font-size:0.8rem; margin-left:10px;"></i>
                </div>
            </div>
        </header>

        <!-- GRID DE CLASES INSCRITAS -->
        <section class="clases-container" style="padding: 20px 0;">
            <?php if (empty($mis_regularizaciones)): ?>
                <div style="background:#fff; border:1px dashed #cbd5e1; border-radius:20px; padding:50px 20px; text-align:center;">
                    <i class="fas fa-check-circle" style="font-size:3rem; color:#10b981; margin-bottom:15px;"></i>
                    <h3 style="color:var(--ink); margin-bottom:5px;">Todo al corriente</h3>
                    <p style="color:var(--muted);">No tienes materias en proceso de regularización en este momento.</p>
                </div>
            <?php else: ?>
                <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(320px, 1fr)); gap:25px;">
                    <?php foreach ($mis_regularizaciones as $clase): ?>
                        
                        <?php 
                            // 1. Degradado por defecto si no hay imagen
                            $bg_image = "linear-gradient(145deg, #10251d, #063f2b)";
                            
                            // 2. Si la base de datos SÍ tiene una imagen registrada
                            if (!empty($clase['imagen_portada'])) {
                                // Aseguramos la ruta correcta (sale de estudiante/ y entra a uploads/)
                                $ruta_img = '../' . $clase['imagen_portada'];
                                // Mezclamos un degradado oscuro con la URL de la imagen
                                $bg_image = "linear-gradient(rgba(16, 37, 29, 0.4), rgba(16, 37, 29, 0.7)), url('" . htmlspecialchars($ruta_img) . "')";
                            }
                        ?>

                        <div style="background:#fff; border:1px solid var(--line); border-radius:16px; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.05); display:flex; flex-direction:column;">
                            
                            <!-- APLICAMOS EL BLINDAJE CSS DESGLOSADO -->
                            <div style="height:120px; background-image: <?php echo $bg_image; ?>; background-size: cover; background-position: center; background-repeat: no-repeat; padding:15px; position:relative;">
                                <!-- Aquí adentro puedes poner el estatus de la materia si lo deseas -->
                            </div>

                            <div style="padding:20px; flex:1;">
                                <h3 style="margin:0 0 10px 0; color:var(--ink); font-size:1.2rem;"><?php echo htmlspecialchars($clase['nombre_materia']); ?></h3>
                                <p style="margin:0 0 15px 0; color:var(--muted); font-size:0.85rem; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
                                    <?php echo htmlspecialchars($clase['descripcion']); ?>
                                </p>
                                
                                <div style="background:#f8fafc; border-radius:10px; padding:12px; border:1px solid var(--line);">
                                    <div style="display:flex; align-items:center; gap:8px; font-size:0.8rem; color:var(--muted); font-weight:600; margin-bottom:8px;">
                                        <i class="fas fa-chalkboard-user" style="color:var(--green);"></i> 
                                        <span>Docente: <?php echo htmlspecialchars($clase['maestro_nombre'] . ' ' . $clase['maestro_apellido']); ?></span>
                                    </div>
                                    <div style="display:flex; align-items:center; gap:8px; font-size:0.8rem; color:var(--muted); font-weight:600;">
                                        <i class="far fa-calendar-check" style="color:var(--orange);"></i> 
                                        <span>Límite: <?php echo date('d/m/Y', strtotime($clase['fecha_fin'])); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div style="padding:15px 20px; border-top:1px solid var(--line);">
                                <a href="espacio_regularizacion.php?id=<?php echo $clase['id_clase']; ?>" style="display:flex; align-items:center; justify-content:center; gap:8px; width:100%; padding:12px; background:var(--green-soft); color:var(--green-800); border-radius:10px; font-weight:700; font-size:0.9rem; text-decoration:none; box-sizing:border-box;">
                                    Entrar al espacio <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>