<?php
session_start();
require_once '../config/database.php';

// Validar que el usuario haya iniciado sesión y sea estudiante
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'estudiante') {
    header("Location: ../login.php");
    exit();
}

$id_alumno = $_SESSION['id_usuario'];
date_default_timezone_set('America/Mexico_City');

try {
    // 1. Obtener datos principales del alumno
    $stmt = $pdo->prepare("SELECT * FROM ALUMNOS WHERE id_alumno = ?");
    $stmt->execute([$id_alumno]);
    $alumno = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$alumno) {
        die("Error: Datos del alumno no encontrados.");
    }

    // 2. Obtener grupo actual del alumno
    $stmtGpo = $pdo->prepare("
        SELECT g.nombre_grupo, g.semestre, g.turno 
        FROM GRUPOS g 
        JOIN GRUPO_ALUMNO ga ON g.id_grupo = ga.id_grupo 
        WHERE ga.id_alumno = ? AND ga.fecha_baja IS NULL
        LIMIT 1
    ");
    $stmtGpo->execute([$id_alumno]);
    $grupo = $stmtGpo->fetch(PDO::FETCH_ASSOC);

    // 3. Obtener domicilio
    $stmtDom = $pdo->prepare("SELECT * FROM DOMICILIO_ALUMNO WHERE id_alumno = ?");
    $stmtDom->execute([$id_alumno]);
    $domicilio = $stmtDom->fetch(PDO::FETCH_ASSOC);

    // Formatear variables para la vista
    $nombre_pila = htmlspecialchars($alumno['nombre']);
    $apellido_p = htmlspecialchars($alumno['apellido_paterno']);
    $nombre_completo = $nombre_pila . ' ' . $apellido_p;
    $iniciales = mb_substr($nombre_pila, 0, 1) . mb_substr($apellido_p, 0, 1);
    
    // Generación y Carrera
    $anio_ingreso = !empty($alumno['periodo_ingreso']) ? substr($alumno['periodo_ingreso'], 0, 4) : date('Y');
    $anio_fin = $anio_ingreso + 3;
    $generacion = $anio_ingreso . ' - ' . $anio_fin;
    $carrera = !empty($alumno['capacitacion']) ? htmlspecialchars($alumno['capacitacion']) : 'Bachillerato General';
    
    // Matrícula, Folio y Status
    $matricula = htmlspecialchars($alumno['matricula']);
    $folio = "27-" . $matricula;
    $estatus = htmlspecialchars($alumno['estatus']);
    $turno = $grupo ? htmlspecialchars($grupo['turno']) : htmlspecialchars($alumno['turno']);
    $grupo_str = $grupo ? htmlspecialchars($grupo['semestre'] . ' "' . $grupo['nombre_grupo'] . '"') : '-- Sin grupo asignado --';
    
    // Datos Personales
    $fecha_nac = !empty($alumno['fecha_nacimiento']) ? date('d/m/Y', strtotime($alumno['fecha_nacimiento'])) : '--/--/----';
    $correo = !empty($alumno['correo_institucional']) ? htmlspecialchars($alumno['correo_institucional']) : (!empty($alumno['correo_personal']) ? htmlspecialchars($alumno['correo_personal']) : '-- Sin registro --');
    $telefono = !empty($alumno['telefono']) ? htmlspecialchars($alumno['telefono']) : '-- Sin registro --';
    
    $domicilio_str = '-- Sin registro --';
    if ($domicilio) {
        $calle = htmlspecialchars($domicilio['calle'] ?? '');
        $num = htmlspecialchars($domicilio['num_exterior'] ?? '');
        $colonia = htmlspecialchars($domicilio['colonia'] ?? '');
        $municipio = htmlspecialchars($domicilio['municipio'] ?? '');
        $domicilio_str = trim("$calle $num, $colonia, $municipio", ", ");
        if (empty($domicilio_str)) $domicilio_str = '-- Sin registro --';
    }

    // 4. LÓGICA DE FOTOGRAFÍA
    if (!empty($alumno['fotografia']) && file_exists("../" . $alumno['fotografia'])) {
        $avatar_url = "../" . htmlspecialchars($alumno['fotografia']);
    } else {
        $avatar_url = "https://ui-avatars.com/api/?name=" . urlencode($nombre_pila.'+'.$apellido_p) . "&background=e2e8f0&color=0f172a&size=120";
    }

} catch (PDOException $e) {
    die("Error al cargar datos del perfil: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#063f2b">
    <title>Mi Perfil · Panel Estudiante</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <!-- Librería para generar el QR -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    
    <!-- ÚNICO CSS EXTERNO: Base del sistema -->
    <link rel="stylesheet" href="style_dashboard_estud.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="style_perfil.css?v=<?php echo time(); ?>">

    <style>
        /* ==============================================================
           CORE LAYOUT ESTUDIANTE (Idéntico a los otros módulos)
        ============================================================== */
        :root {
            --sidebar-width: 260px;
            --sidebar-collapsed-width: 80px;
            --bg-main: #f8fafc;
        }
        body { background-color: var(--bg-main); margin: 0; padding: 0; overflow: hidden; box-sizing: border-box; }
        *, *::before, *::after { box-sizing: border-box; }

        .main-content {
            margin-left: var(--sidebar-width); width: calc(100% - var(--sidebar-width));
            padding: 1.2rem 2rem; height: 100vh; overflow-y: auto; transition: margin-left 0.3s ease, width 0.3s ease;
        }
        body.sidebar-collapsed .main-content { margin-left: var(--sidebar-collapsed-width); width: calc(100% - var(--sidebar-collapsed-width)); }
        .dashboard-container { max-width: 100%; margin: 0 auto; display: flex; flex-direction: column; gap: 1rem; }

        /* HEADER Y PERFIL */
        .topbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
        .topbar-left { display: flex; align-items: center; gap: 15px; }
        .topbar-left h1 { font-size: 1.4rem; font-weight: 800; color: #0f172a; margin: 0 0 0.1rem 0; letter-spacing: -0.5px; }
        .topbar-left p { margin: 0; color: #64748b; font-size: 0.85rem; }
        
        .topbar-right { display: flex; align-items: center; gap: 10px; }
        .date-pill { background: white; padding: 0.5rem 1rem; border-radius: 99px; font-size: 0.8rem; font-weight: 600; color: #475569; border: 1px solid #e2e8f0; display: flex; align-items: center; gap: 8px; }
        .date-pill i { color: #059669; }

        .profile-container { position: relative; }
        .profile-pill { background: white; padding: 0.3rem 0.8rem 0.3rem 0.3rem; border-radius: 99px; display: flex; align-items: center; gap: 10px; border: 1px solid #e2e8f0; cursor: pointer; transition: background 0.2s; }
        .profile-pill:hover { background: #f8fafc; }
        .profile-pill .avatar { width: 34px; height: 34px; background: #063f2b; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; }
        .profile-info strong { display: block; font-size: 0.85rem; color: #0f172a; }
        .profile-info span { display: block; font-size: 0.7rem; color: #64748b; }
        
        .profile-dropdown { position: absolute; top: calc(100% + 10px); right: 0; background: white; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); width: 220px; opacity: 0; visibility: hidden; transform: translateY(-10px); transition: all 0.2s; z-index: 100; }
        .profile-dropdown.active { opacity: 1; visibility: visible; transform: translateY(0); }
        .profile-dropdown-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #475569; text-decoration: none; font-size: 0.9rem; font-weight: 600; transition: all 0.2s; }
        .profile-dropdown-item i { width: 16px; text-align: center; }
        .profile-dropdown-item:first-child { border-radius: 12px 12px 0 0; }
        .profile-dropdown-item:last-child { border-radius: 0 0 12px 12px; border-top: 1px solid #f1f5f9; color: #ef4444; }
        .profile-dropdown-item:hover { background: #f8fafc; color: #0f172a; padding-left: 20px; }
        .mobile-menu { display: none; background: none; border: none; padding: 0; font-size: 1.6rem; color: #0f172a; cursor: pointer; }

        /* ==============================================================
           DISEÑO DE TARJETAS DE PERFIL
        ============================================================== */
        .profile-grid { display: grid; grid-template-columns: 1.3fr 1fr; gap: 1.5rem; margin-top: 10px; }

        .card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.02); padding: 1.5rem; margin-bottom: 1.5rem; position: relative; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid #e2e8f0; }
        .card-header.borderless { border-bottom: none; padding-bottom: 0; margin-bottom: 1rem; }
        .header-title { font-size: 1.1rem; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 10px; }
        .header-title i { color: #059669; font-size: 1.2rem; }

        /* Tarjeta Principal */
        .btn-change-photo { position: absolute; top: 1.5rem; right: 1.5rem; background: #f1f5f9; border: 1px solid #e2e8f0; padding: 8px 14px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; color: #475569; display: flex; align-items: center; gap: 6px; transition: 0.2s; cursor: pointer; }
        .btn-change-photo:hover { background: #e2e8f0; color: #0f172a; }

        .profile-main-info { display: flex; align-items: center; gap: 24px; margin-bottom: 2rem; }
        .avatar-large-wrapper { position: relative; width: 110px; height: 110px; border-radius: 50%; padding: 4px; background: white; border: 2px solid #e2e8f0; cursor: pointer; }
        .avatar-large-wrapper img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
        .edit-avatar-btn { position: absolute; bottom: 0; right: 0; width: 32px; height: 32px; background: white; border: 1px solid #e2e8f0; border-radius: 50%; display: grid; place-items: center; color: #64748b; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: 0.2s; cursor: pointer; }
        .edit-avatar-btn:hover { color: #059669; }

        .profile-name-badges h2 { font-size: 1.6rem; font-weight: 800; color: #0f172a; margin: 0 0 10px 0; }
        .badges-row { display: flex; gap: 10px; }
        .badge { padding: 5px 12px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; }
        .badge-alumno { background: #f0fdf4; color: #166534; }
        .badge-activo { background: #d1fae5; color: #059669; }

        .info-grid-3x2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.2rem; margin-bottom: 1.5rem; }
        .info-item { display: flex; align-items: center; gap: 12px; }
        .info-item.border-box { border: 1px solid #e2e8f0; padding: 12px 14px; border-radius: 10px; }
        .icon-box { width: 38px; height: 38px; background: #ecfdf5; color: #10b981; border-radius: 10px; display: grid; place-items: center; font-size: 1.1rem; flex-shrink: 0; }
        .info-data { display: flex; flex-direction: column; }
        .info-data label { font-size: 0.75rem; color: #64748b; margin-bottom: 2px; }
        .info-data span { font-size: 0.9rem; font-weight: 600; color: #0f172a; }

        /* Credencial Virtual */
        .badge-status-green { background: #ecfdf5; color: #10b981; padding: 4px 10px; border-radius: 50px; font-size: 0.75rem; font-weight: 700; display: flex; align-items: center; gap: 6px; }
        .credencial-box { border-radius: 12px; overflow: hidden; border: 1px solid #e2e8f0; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 1.5rem; }
        .cred-header { background: #063f2b; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; color: white; }
        .logo-area { display: flex; align-items: center; gap: 8px; }
        .logo-area img { height: 28px; }
        .logo-area strong { font-size: 1rem; letter-spacing: 1px; }
        .cred-plantel { text-align: right; }
        .cred-plantel strong { display: block; font-size: 0.8rem; letter-spacing: 1px; }
        .cred-plantel span { font-size: 0.7rem; color: #a7f3d0; }

        .cred-body { background: white; padding: 20px 16px; display: flex; gap: 15px; align-items: center; }
        .cred-photo-col img { width: 80px; height: 80px; border-radius: 50%; border: 3px solid #e2e8f0; object-fit: cover; }
        .cred-data-col { flex: 1; }
        .cred-data-col h2 { margin: 0 0 6px 0; font-size: 1.1rem; color: #0f172a; }
        .status-row { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
        .status-row strong { font-size: 0.8rem; color: #0f172a; }
        .badge-activo-small { background: #ecfdf5; color: #10b981; padding: 2px 6px; border-radius: 4px; font-size: 0.6rem; font-weight: 800; }
        .info-list p { margin: 0 0 4px 0; font-size: 0.75rem; color: #0f172a; }
        .info-list label { color: #64748b; display: inline-block; width: 70px; }

        .cred-qr-col { text-align: center; }
        .folio-text label { display: block; font-size: 0.65rem; color: #64748b; text-transform: uppercase; }
        .folio-text span { font-size: 0.8rem; font-weight: 700; color: #0f172a; }
        .cred-footer { background: #063f2b; color: white; text-align: center; padding: 8px; font-size: 0.8rem; font-weight: 600; letter-spacing: 0.5px; }
        
        .btn-action-primary { width: 100%; padding: 10px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; display: flex; justify-content: center; align-items: center; gap: 8px; transition: 0.2s; border: 1px solid #cbd5e1; color: #059669; background: white; cursor: pointer; }
        .btn-action-primary:hover { background: #f0fdf4; border-color: #10b981; }

        /* Acciones Rápidas */
        .actions-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 1.5rem; }
        .action-btn { background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px 10px; border-radius: 12px; text-align: center; display: flex; flex-direction: column; align-items: center; transition: 0.2s; text-decoration: none; }
        .action-btn:hover { transform: translateY(-3px); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border-color: #cbd5e1; background: white; }
        .action-btn .icon { width: 40px; height: 40px; border-radius: 10px; display: grid; place-items: center; font-size: 1.2rem; margin-bottom: 10px; }
        .bg-green-light { background: #d1fae5; color: #059669; }
        .bg-blue-light { background: #dbeafe; color: #2563eb; }
        .bg-orange-light { background: #ffedd5; color: #ea580c; }
        .bg-purple-light { background: #f3e8ff; color: #9333ea; }
        .action-btn strong { font-size: 0.75rem; color: #0f172a; line-height: 1.2; }

        .btn-outline-small { border: 1px solid #cbd5e1; background: transparent; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 0.8rem; color: #475569; display: flex; gap: 6px; align-items: center; transition: 0.2s; cursor: pointer; }
        .btn-outline-small:hover { background: #f1f5f9; color: #0f172a; }
        .personal-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }

        /* Modales */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 9999; justify-content: center; align-items: center; opacity: 0; transition: opacity 0.3s; }
        .modal-overlay.active { display: flex; opacity: 1; }
        .modal-box { background: white; width: 90%; max-width: 450px; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); transform: translateY(20px); transition: transform 0.3s; overflow: hidden; }
        .modal-overlay.active .modal-box { transform: translateY(0); }
        .modal-header { padding: 15px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { margin: 0; font-size: 1.1rem; color: #0f172a; display: flex; align-items: center; gap: 8px; }
        .modal-header h3 i { color: #10b981; }
        .btn-close-modal { background: #f1f5f9; border: none; width: 28px; height: 28px; border-radius: 50%; color: #64748b; cursor: pointer; display: grid; place-items: center; transition: 0.2s; }
        .btn-close-modal:hover { background: #e2e8f0; color: #ef4444; }
        .modal-body { padding: 20px; }
        .form-group { margin-bottom: 15px; display: flex; flex-direction: column; gap: 6px; }
        .form-group label { font-size: 0.8rem; font-weight: 600; color: #475569; }
        .form-control { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 0.9rem; color: #0f172a; outline: none; transition: 0.2s; }
        .form-control:focus { border-color: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1); }
        .modal-footer { padding: 15px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: flex-end; gap: 10px; }
        .btn-cancel { padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; background: white; border: 1px solid #cbd5e1; color: #475569; cursor: pointer; transition: 0.2s; }
        .btn-cancel:hover { background: #f1f5f9; }
        .btn-submit { padding: 8px 16px; border-radius: 8px; font-weight: 600; font-size: 0.85rem; background: #10b981; border: none; color: white; cursor: pointer; transition: 0.2s; display: flex; align-items: center; gap: 6px;}
        .btn-submit:hover { background: #059669; }
        .file-upload-wrapper { border: 2px dashed #cbd5e1; border-radius: 12px; padding: 1.5rem 1rem; text-align: center; cursor: pointer; transition: 0.2s; background: #f8fafc; display: block;}
        .file-upload-wrapper:hover { border-color: #10b981; background: #ecfdf5; }
        .file-upload-wrapper i { font-size: 1.8rem; color: #10b981; margin-bottom: 8px; display: block;}
        .file-upload-wrapper span { display: block; font-size: 0.85rem; color: #475569; font-weight: 600; }
        input[type="file"] { display: none; }

        /* ==============================================================
           RESPONSIVE MOBILE
        ============================================================== */
        @media (max-width: 1024px) {
            .profile-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            body { overflow: auto; }
            .main-content { margin-left: 0 !important; width: 100% !important; padding: 1rem; height: auto; overflow-y: visible; }
            
            .topbar { flex-direction: column; align-items: stretch; gap: 0.8rem; }
            .topbar-left { align-items: flex-start; }
            .topbar-left h1 { font-size: 1.35rem; }
            .mobile-menu { display: block; margin-top: 3px; }
            .topbar-right { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; width: 100%; }
            .date-pill, .profile-pill { width: 100%; justify-content: center; padding: 0.5rem; }

            .info-grid-3x2 { grid-template-columns: 1fr; }
            .personal-grid { grid-template-columns: 1fr; }
            .profile-main-info { flex-direction: column; text-align: center; gap: 15px; }
            .badges-row { justify-content: center; }
            .btn-change-photo { position: relative; top: 0; right: 0; margin-bottom: 15px; width: 100%; justify-content: center; }
            .cred-body { flex-direction: column; text-align: center; }
            .actions-grid { grid-template-columns: repeat(2, 1fr); }
        }
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
                    <strong>SIGE<span>-Cobaep</span></strong>
                    <small>Plantel 27</small>
                </div>
            </div>

            <nav class="navigation" aria-label="Navegación principal">
                <span class="nav-heading">Menú principal</span>
                <a href="dashboard_estud.php" class="nav-item"><i class="fas fa-home"></i> <span>Inicio</span></a>
                <a href="mis_asistencias.php" class="nav-item"><i class="far fa-calendar-check"></i> <span>Mis Asistencias</span></a>
                <a href="horario_clases.php" class="nav-item"><i class="far fa-calendar-alt"></i> <span>Horario de Clases</span></a>
                
                <span class="nav-heading">Académico</span>
                <a href="actividades_escolares.php" class="nav-item"><i class="fas fa-book-open"></i> <span>Actividades Escolares</span></a>
                 <a href="actividades.php" class="nav-item"><i class="fas fa-star"></i> <span>Regularizaciones</span></a>

                <span class="nav-heading">Información</span>
                <a href="avisos.php" class="nav-item"><i class="far fa-bell"></i> <span>Avisos</span></a>
                <a href="documentos.php" class="nav-item"><i class="far fa-file-alt"></i> <span>Documentos</span></a>
                <a href="reglamentos.php" class="nav-item"><i class="far fa-clipboard"></i> <span>Reglamentos</span></a>
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
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">
        <div class="dashboard-container">
            
            <header class="topbar">
                <div class="topbar-left">
                    <button class="mobile-menu" id="mobileMenu" type="button"><i class="fas fa-bars"></i></button>
                    <div>
                        <h1>Mi Perfil</h1>
                        <p>Consulta y administra tu información personal.</p>
                    </div>
                </div>

                <div class="topbar-right">
                    <div class="date-pill">
                        <i class="far fa-calendar"></i>
                        <span id="fechaTexto"><?php echo date('d/m/Y, h:i a'); ?></span>
                    </div>
                    
                    <div class="profile-container">
                        <div class="profile-pill" id="profileBtn">
                            <img src="<?php echo $avatar_url; ?>" alt="Avatar" class="avatar-small">
                            <div class="profile-info">
                                <strong><?php echo $nombre_pila; ?></strong>
                                <span>Matrícula: <?php echo $matricula; ?></span>
                            </div>
                            <i class="fas fa-chevron-down" style="color: #94a3b8; margin-left: 5px; font-size: 0.8rem;"></i>
                        </div>
                        
                        <div class="profile-dropdown" id="profileDropdown">
                            <a href="perfil.php" class="profile-dropdown-item"><i class="far fa-user-circle"></i> Mi Perfil</a>
                            <a href="../logout.php" class="profile-dropdown-item"><i class="fas fa-arrow-right-from-bracket"></i> Cerrar Sesión</a>
                        </div>
                    </div>
                </div>
            </header>

            <?php if (isset($_GET['exito'])): ?>
                <div style="background: #ecfdf5; color: #065f46; padding: 12px 16px; border-radius: 8px; margin-bottom: 5px; border: 1px solid #a7f3d0; font-size: 0.85rem; font-weight: 600;">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['exito']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div style="background: #fef2f2; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 5px; border: 1px solid #fecaca; font-size: 0.85rem; font-weight: 600;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>

            <div class="profile-grid">
                
                <div class="col-left">
                    <div class="card">
                        <button class="btn-change-photo" onclick="abrirModal('modalFoto')"><i class="fas fa-camera"></i> Cambiar foto</button>
                        
                        <div class="profile-main-info">
                            <div class="avatar-large-wrapper" onclick="abrirModal('modalFoto')" title="Actualizar foto">
                                <img src="<?php echo $avatar_url; ?>" alt="Avatar">
                                <button class="edit-avatar-btn"><i class="fas fa-pen"></i></button>
                            </div>
                            
                            <div class="profile-name-badges">
                                <h2><?php echo $nombre_completo; ?></h2>
                                <div class="badges-row">
                                    <span class="badge badge-alumno">Alumno</span>
                                    <span class="badge badge-activo"><?php echo strtoupper($estatus); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="info-grid-3x2">
                            <div class="info-item">
                                <div class="icon-box"><i class="far fa-id-card"></i></div>
                                <div class="info-data"><label>Matrícula</label><span><?php echo $matricula; ?></span></div>
                            </div>
                            <div class="info-item">
                                <div class="icon-box"><i class="fas fa-graduation-cap"></i></div>
                                <div class="info-data"><label>Generación</label><span><?php echo $generacion; ?></span></div>
                            </div>
                            <div class="info-item">
                                <div class="icon-box"><i class="fas fa-book"></i></div>
                                <div class="info-data"><label>Carrera</label><span><?php echo $carrera; ?></span></div>
                            </div>
                            <div class="info-item">
                                <div class="icon-box"><i class="fas fa-building"></i></div>
                                <div class="info-data"><label>Plantel</label><span>COBAEP Plantel 27</span></div>
                            </div>
                            <div class="info-item">
                                <div class="icon-box"><i class="far fa-clock"></i></div>
                                <div class="info-data"><label>Turno</label><span><?php echo $turno; ?></span></div>
                            </div>
                            <div class="info-item">
                                <div class="icon-box"><i class="fas fa-users"></i></div>
                                <div class="info-data"><label>Grupo</label><span><?php echo $grupo_str; ?></span></div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <div class="header-title"><i class="fas fa-user"></i> Información de Contacto</div>
                            <button class="btn-outline-small" onclick="abrirModal('modalContacto')"><i class="fas fa-pen"></i> Editar</button>
                        </div>

                        <div class="personal-grid">
                            <div class="info-item border-box">
                                <div class="icon-box"><i class="far fa-calendar-alt"></i></div>
                                <div class="info-data"><label>Fecha de Nacimiento</label><span><?php echo $fecha_nac; ?></span></div>
                            </div>
                            <div class="info-item border-box">
                                <div class="icon-box"><i class="far fa-envelope"></i></div>
                                <div class="info-data"><label>Correo Electrónico</label><span><?php echo $correo; ?></span></div>
                            </div>
                            <div class="info-item border-box">
                                <div class="icon-box"><i class="fas fa-phone-alt"></i></div>
                                <div class="info-data"><label>Teléfono</label><span><?php echo $telefono; ?></span></div>
                            </div>
                            <div class="info-item border-box">
                                <div class="icon-box"><i class="fas fa-home"></i></div>
                                <div class="info-data"><label>Domicilio (Solo lectura)</label><span><?php echo $domicilio_str; ?></span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-right">
                    
                    <div class="card">
                        <div class="card-header borderless">
                            <div class="header-title"><i class="far fa-id-card"></i> Credencial Virtual</div>
                            <span class="badge-status-green"><i class="fas fa-check"></i> Vigente</span>
                        </div>

                        <div class="credencial-box" id="credencialImprimible">
                            <div class="cred-header">
                                <div class="logo-area">
                                    <img src="../img/logo-blanco.png" alt="COBAEP">
                                    <strong>COBAEP</strong>
                                </div>
                                <div class="cred-plantel">
                                    <strong>PLANTEL 27</strong>
                                    <span>Zaragoza, Puebla</span>
                                </div>
                            </div>
                            <div class="cred-body">
                                <div class="cred-photo-col">
                                    <img src="<?php echo $avatar_url; ?>" alt="Foto Alumno">
                                </div>
                                <div class="cred-data-col">
                                    <h2><?php echo $nombre_completo; ?></h2>
                                    <div class="status-row">
                                        <strong>Alumno</strong>
                                        <span class="badge-activo-small">ACTIVO</span>
                                    </div>
                                    <div class="info-list">
                                        <p><label>Matrícula:</label> <?php echo $matricula; ?></p>
                                        <p><label>Generación:</label> <?php echo $generacion; ?></p>
                                        <p><label>Carrera:</label> <strong><?php echo $carrera; ?></strong></p>
                                    </div>
                                </div>
                                <div class="cred-qr-col">
                                    <div id="credencial-qr-img" style="margin-bottom: 8px; display: flex; justify-content: center;"></div>
                                    <div class="folio-text"><label>Folio</label><span><?php echo $folio; ?></span></div>
                                </div>
                            </div>
                            <div class="cred-footer">
                                Vigencia: AGO <?php echo date('Y'); ?> - JUL <?php echo date('Y')+3; ?>
                            </div>
                        </div>

                        <div class="cred-actions">
                            <button class="btn-action-primary" onclick="window.print()"><i class="fas fa-print"></i> Imprimir Credencial</button>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header borderless">
                            <div class="header-title"><i class="fas fa-bolt"></i> Acciones Rápidas</div>
                        </div>

                        <div class="actions-grid">
                            <a href="javascript:void(0)" onclick="abrirModal('modalFoto')" class="action-btn">
                                <div class="icon bg-green-light"><i class="fas fa-camera"></i></div>
                                <strong>Actualizar Foto</strong>
                            </a>
                            <a href="horario_clases.php" class="action-btn">
                                <div class="icon bg-blue-light"><i class="far fa-calendar-alt"></i></div>
                                <strong>Ver Horario</strong>
                            </a>
                            <a href="mis_asistencias.php" class="action-btn">
                                <div class="icon bg-orange-light"><i class="far fa-check-circle"></i></div>
                                <strong>Asistencias</strong>
                            </a>
                            <a href="documentos.php" class="action-btn">
                                <div class="icon bg-purple-light"><i class="far fa-file-alt"></i></div>
                                <strong>Documentos</strong>
                            </a>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </main>

    <!-- Modal: Cambiar Foto -->
    <div class="modal-overlay" id="modalFoto">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fas fa-camera"></i> Actualizar Fotografía</h3>
                <button class="btn-close-modal" type="button" onclick="cerrarModal('modalFoto')"><i class="fas fa-times"></i></button>
            </div>
            <form action="../procesos/actualizar_foto_perfil.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 15px;">Selecciona una foto clara de tu rostro. Se actualizará en tu credencial virtual.</p>
                    <label class="file-upload-wrapper">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span id="file-name">Haz clic para buscar tu foto (JPG, PNG)</span>
                        <input type="file" name="foto_perfil" id="foto_perfil" accept="image/jpeg, image/png, image/webp" required>
                    </label>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="cerrarModal('modalFoto')">Cancelar</button>
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Subir Foto</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Editar Contacto -->
    <div class="modal-overlay" id="modalContacto">
        <div class="modal-box">
            <div class="modal-header">
                <h3><i class="fas fa-address-book"></i> Editar Contacto</h3>
                <button class="btn-close-modal" type="button" onclick="cerrarModal('modalContacto')"><i class="fas fa-times"></i></button>
            </div>
            <form action="../procesos/actualizar_contacto.php" method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Correo Personal</label>
                        <input type="email" name="correo_personal" class="form-control" value="<?php echo (!empty($alumno['correo_personal'])) ? htmlspecialchars($alumno['correo_personal']) : ''; ?>" placeholder="ejemplo@correo.com">
                    </div>
                    <div class="form-group">
                        <label>Teléfono Celular</label>
                        <input type="tel" name="telefono" class="form-control" value="<?php echo (!empty($alumno['telefono'])) ? htmlspecialchars($alumno['telefono']) : ''; ?>" placeholder="10 dígitos" pattern="[0-9]{10}">
                    </div>
                    <p style="font-size: 0.75rem; color: #94a3b8; margin-top: 10px;"><i class="fas fa-info-circle"></i> Para modificar tu nombre o matrícula debes acudir a control escolar.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="cerrarModal('modalContacto')">Cancelar</button>
                    <button type="submit" class="btn-submit"><i class="fas fa-save"></i> Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const mobileMenu = document.getElementById('mobileMenu');
            const collapseToggle = document.getElementById('collapseToggle');
            const body = document.body;

            // Sidebar PC
            if (localStorage.getItem('sidebarCollapsed') === 'true') body.classList.add('sidebar-collapsed');

            if (collapseToggle) {
                collapseToggle.addEventListener('click', () => {
                    body.classList.toggle('sidebar-collapsed');
                    localStorage.setItem('sidebarCollapsed', body.classList.contains('sidebar-collapsed'));
                });
            }

            // Sidebar Móvil
            window.addEventListener('resize', () => {
                if (window.innerWidth > 768) {
                    sidebar.classList.remove('mobile-open');
                    overlay.classList.remove('visible');
                    body.classList.remove('menu-open');
                }
            });

            if (mobileMenu) {
                mobileMenu.addEventListener('click', () => {
                    sidebar.classList.add('mobile-open');
                    overlay.classList.add('visible');
                    body.classList.add('menu-open');
                });
            }
            if (overlay) {
                overlay.addEventListener('click', () => {
                    sidebar.classList.remove('mobile-open');
                    overlay.classList.remove('visible');
                    body.classList.remove('menu-open');
                });
            }

            // Dropdown Perfil
            const profileBtn = document.getElementById('profileBtn');
            const profileDropdown = document.getElementById('profileDropdown');

            if (profileBtn && profileDropdown) {
                profileBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    profileDropdown.classList.toggle('active');
                });
                document.addEventListener('click', (e) => {
                    if (!profileBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
                        profileDropdown.classList.remove('active');
                    }
                });
            }

            // Reloj
            const fechaTexto = document.getElementById('fechaTexto');
            function actualizarHora() {
                if (!fechaTexto) return;
                const now = new Date();
                fechaTexto.textContent = now.toLocaleString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
            }
            actualizarHora(); setInterval(actualizarHora, 60000);

            // Código QR
            const qrData = "<?php echo htmlspecialchars($alumno['codigo_qr'] ?? ''); ?>";
            if (qrData && qrData !== '') {
                new QRCode(document.getElementById("credencial-qr-img"), {
                    text: qrData, width: 90, height: 90, colorDark : "#000000", colorLight : "#ffffff", correctLevel : QRCode.CorrectLevel.H
                });
            }

            // Input File Dinámico
            document.getElementById('foto_perfil').addEventListener('change', function(e) {
                if (this.files && this.files.length > 0) {
                    document.getElementById('file-name').innerHTML = `<strong style="color:#059669;">${this.files[0].name}</strong>`;
                }
            });
        });

        // Funciones Modal
        function abrirModal(id) {
            document.getElementById(id).classList.add('active');
        }
        function cerrarModal(id) {
            document.getElementById(id).classList.remove('active');
        }
    </script>
</body>
</html>