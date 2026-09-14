<?php
session_start();
require_once '../config/database.php';

// Verificación de sesión
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo' || !isset($_GET['id'])) {
    header("Location: gestion_usuarios.php");
    exit();
}

$id_alumno = intval($_GET['id']);

try {
    // Consulta para obtener todos los datos del alumno
    $sql = "SELECT *, CONCAT_WS(' ', nombre, apellido_paterno, apellido_materno) AS nombre_completo 
            FROM ALUMNOS WHERE id_alumno = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id_alumno]);
    $alumno = $stmt->fetch();

    if (!$alumno) {
        die("Error: El alumno no existe en la base de datos.");
    }
} catch (PDOException $e) {
    die("Error de BD: " . $e->getMessage());
}

$nombre_admin = isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') : 'Administrador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Perfil de Alumno · SIGE COBAEP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="style_dashboard_admin.css">
    <style>
        .profile-container { padding: 30px; max-width: 900px; margin: 0 auto; }
        .card-profile { background: #fff; border-radius: 20px; padding: 30px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border: 1px solid #e5e7eb; margin-bottom: 20px; }
        .profile-header { display: flex; gap: 25px; align-items: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #e5e7eb; }
        .avatar-large { width: 90px; height: 90px; border-radius: 50%; background: #064e3b; color: white; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: bold; }
        .profile-title h2 { margin: 0; font-size: 1.8rem; color: #111827; }
        .profile-title p { margin: 5px 0 0 0; color: #6b7280; font-size: 1rem; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .info-item { background: #f9fafb; padding: 15px; border-radius: 12px; border: 1px solid #f3f4f6; }
        .info-item label { display: block; font-size: 0.75rem; color: #6b7280; text-transform: uppercase; font-weight: 700; margin-bottom: 5px; }
        .info-item span { font-size: 1rem; color: #111827; font-weight: 500; }
        .badge-status { padding: 5px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 700; display: inline-block; margin-top: 5px;}
        .status-alta { background: #d1fae5; color: #065f46; }
        .status-baja { background: #fee2e2; color: #991b1b; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; color: #16a05d; text-decoration: none; font-weight: 600; margin-bottom: 20px; }
        .btn-back:hover { color: #064e3b; }
    </style>
</head>
<body>

    <!-- ===== BARRA LATERAL (IDÉNTICA AL DASHBOARD) ===== -->
    <aside class="sidebar" id="sidebar">
        <!-- Puedes copiar aquí el mismo <aside> que tienes en ver_grupo_2.php para mantener el menú -->
        
        
        
        <div class="sidebar-inner">



            <div class="brand">
                <img src="../img/LogoCobaep.png" alt="Logo" class="brand-logo" style="width: 50px;">
                <div class="brand-copy"><strong>SIGE<span>-Cobaep</span></strong><small>Plantel 27</small></div>
            </div>

            <nav class="navigation">
                <span class="nav-heading">Académico</span>
                <a href="gestion_grupos.php" class="nav-item"><i class="fas fa-users-rectangle"></i><span>Gestión de Grupos</span></a>
                <a href="gestion_usuarios.php" class="nav-item active"><i class="fas fa-users"></i><span>Directorio</span></a>
            </nav>


            <div class="sidebar-bottom">

            <div class="institution">

                <div class="institution-icon">
                    <i class="fas fa-building-columns"></i>
                </div>
                <div>
                    <strong>
                        COBAEP Plantel 27
                    </strong>
                    <span>
                        Zaragoza, Puebla
                    </span>
                </div>
            </div>


            <a
                href="../logout.php"
                class="logout"
            >
                <i class="fas fa-arrow-right-from-bracket"></i>
                <span>Cerrar Sesión</span>
            </a>

        </div>



        </div>
    </aside>

    <main class="main-content">
        <div class="profile-container">
            <a href="javascript:history.back()" class="btn-back"><i class="fas fa-arrow-left"></i> Volver a la lista</a>
            
            <div class="card-profile">
                <div class="profile-header">
                    <div class="avatar-large">
                        <?php echo strtoupper(substr($alumno['nombre'], 0, 1) . substr($alumno['apellido_paterno'], 0, 1)); ?>
                    </div>
                    <div class="profile-title">
                        <h2><?php echo htmlspecialchars($alumno['nombre_completo']); ?></h2>
                        <p><i class="fas fa-id-card"></i> Matrícula: <strong><?php echo htmlspecialchars($alumno['matricula']); ?></strong></p>
                        <span class="badge-status <?php echo strtolower($alumno['estatus']) == 'alta' ? 'status-alta' : 'status-baja'; ?>">
                            Estatus: <?php echo htmlspecialchars($alumno['estatus']); ?>
                        </span>
                    </div>
                </div>

                <div class="info-grid">
                    <div class="info-item">
                        <label>Periodo de Ingreso</label>
                        <span><?php echo htmlspecialchars($alumno['periodo_ingreso'] ?? 'N/D'); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Turno</label>
                        <span><?php echo htmlspecialchars($alumno['turno']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Puntos de Conducta</label>
                        <span><i class="fas fa-star" style="color: #f59e0b;"></i> <?php echo htmlspecialchars($alumno['puntos_conducta']); ?> / 100</span>
                    </div>
                    <div class="info-item">
                        <label>Código QR Asignado</label>
                        <span><i class="fas fa-qrcode"></i> <?php echo htmlspecialchars($alumno['codigo_qr']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Correo Personal</label>
                        <span><?php echo htmlspecialchars($alumno['correo_personal'] ?? 'No registrado'); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Correo Institucional</label>
                        <span><?php echo htmlspecialchars($alumno['correo_institucional'] ?? 'No registrado'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </main>

</body>
</html>