<?php
/**
 * Plantilla compartida del panel del estudiante (barra lateral + encabezado).
 * Uso:
 *   $alumno = estudiante_requerir_sesion($pdo);
 *   estudiante_layout_inicio('Avisos', 'Descripción', 'avisos.php', $alumno, $css_extra);
 *   ... contenido ...
 *   estudiante_layout_fin();
 */

function estudiante_requerir_sesion(PDO $pdo): array {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'estudiante') {
        header("Location: ../login.php");
        exit();
    }
    date_default_timezone_set('America/Mexico_City');
    $st = $pdo->prepare("SELECT * FROM ALUMNOS WHERE id_alumno = ?");
    $st->execute([$_SESSION['id_usuario']]);
    $alumno = $st->fetch(PDO::FETCH_ASSOC);
    if (!$alumno) die("Error: Datos del alumno no encontrados.");
    return $alumno;
}

function estudiante_layout_inicio(string $titulo, string $subtitulo, string $activo, array $alumno, string $css_extra = ''): void {
    $nombre_pila = htmlspecialchars($alumno['nombre']);
    $iniciales = htmlspecialchars(mb_substr($alumno['nombre'], 0, 1) . mb_substr($alumno['apellido_paterno'], 0, 1));
    $menu = [
        'Menú principal' => [
            ['dashboard_estud.php', 'fas fa-home', 'Inicio'],
            ['mis_asistencias.php', 'far fa-calendar-check', 'Mis Asistencias'],
            ['horario_clases.php', 'far fa-calendar-alt', 'Horario de Clases'],
        ],
        'Académico' => [
            ['actividades_escolares.php', 'fas fa-book-open', 'Actividades Escolares'],
            ['actividades.php', 'fas fa-star', 'Regularizaciones'],
        ],
        'Información' => [
            ['avisos.php', 'far fa-bell', 'Avisos'],
            ['documentos.php', 'far fa-file-alt', 'Documentos'],
            ['reglamentos.php', 'far fa-clipboard', 'Reglamentos'],
        ],
    ];
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#063f2b">
    <title><?php echo htmlspecialchars($titulo); ?> · Panel Estudiante</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="style_dashboard_estud.css">
    <style>
        .info-card{background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:1.4rem;margin-bottom:1.2rem;box-shadow:0 2px 10px rgba(15,23,42,.03);}
        .info-card h2{margin:0 0 .3rem;font-size:1rem;color:#0f172a;display:flex;align-items:center;gap:10px;}
        .info-card h2 i{color:#059669;}
        .info-card .desc{margin:0 0 1rem;color:#64748b;font-size:.85rem;}
        .info-empty{background:#f8fafc;border:1px dashed #cbd5e1;border-radius:12px;padding:1.6rem 1rem;text-align:center;color:#64748b;font-size:.88rem;}
        .info-empty i{display:block;font-size:1.8rem;color:#94a3b8;margin-bottom:.5rem;}
        .info-table{width:100%;border-collapse:collapse;font-size:.85rem;}
        .info-table th{text-align:left;color:#475569;font-weight:600;padding:.6rem;border-bottom:1px solid #e2e8f0;background:#f8fafc;}
        .info-table td{padding:.6rem;border-bottom:1px solid #f1f5f9;color:#1e293b;vertical-align:top;}
        .info-wrap{overflow-x:auto;}
        .tag{display:inline-block;padding:2px 9px;border-radius:999px;font-size:.72rem;font-weight:700;}
        .tag.green{background:#ecfdf5;color:#047857;} .tag.amber{background:#fffbeb;color:#b45309;} .tag.red{background:#fef2f2;color:#b91c1c;} .tag.blue{background:#eff6ff;color:#1d4ed8;} .tag.gray{background:#f1f5f9;color:#475569;}
        <?php echo $css_extra; ?>
    </style>
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <button id="collapseToggle" class="collapse-toggle" aria-label="Colapsar menú"><i class="fas fa-chevron-left"></i></button>
        <div class="sidebar-inner">
            <div class="brand">
                <img src="../img/LogoCobaep.png" alt="Logo COBAEP" class="brand-logo">
                <div class="brand-copy"><strong>SIGE<span>-Cobaep</span></strong><small>Plantel 27</small></div>
            </div>
            <nav class="navigation">
                <?php foreach ($menu as $seccion => $items): ?>
                    <span class="nav-heading"><?php echo $seccion; ?></span>
                    <?php foreach ($items as [$href, $icono, $texto]): ?>
                        <a href="<?php echo $href; ?>" class="nav-item <?php echo $href === $activo ? 'active' : ''; ?>"><i class="<?php echo $icono; ?>"></i> <span><?php echo $texto; ?></span></a>
                    <?php endforeach; ?>
                <?php endforeach; ?>
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
                    <button class="mobile-menu" id="mobileMenu" type="button" aria-label="Abrir menú"><i class="fas fa-bars"></i></button>
                    <div>
                        <h1><?php echo htmlspecialchars($titulo); ?></h1>
                        <p><?php echo htmlspecialchars($subtitulo); ?></p>
                    </div>
                </div>
                <div class="topbar-right">
                    <div class="date-pill"><i class="far fa-calendar"></i><span id="fechaTexto"><?php echo date('d/m/Y, h:i a'); ?></span></div>
                    <div class="profile-container">
                        <div class="profile-pill" id="profileBtn">
                            <div class="avatar"><?php echo $iniciales; ?></div>
                            <div class="profile-info">
                                <strong><?php echo $nombre_pila; ?></strong>
                                <span>Matrícula: <?php echo htmlspecialchars($alumno['matricula']); ?></span>
                            </div>
                            <i class="fas fa-chevron-down" style="color:#94a3b8;margin-left:5px;font-size:.8rem;"></i>
                        </div>
                        <div class="profile-dropdown" id="profileDropdown">
                            <a href="perfil.php" class="profile-dropdown-item"><i class="far fa-user-circle"></i> Mi Perfil</a>
                            <a href="../logout.php" class="profile-dropdown-item"><i class="fas fa-arrow-right-from-bracket"></i> Cerrar Sesión</a>
                        </div>
                    </div>
                </div>
            </header>
    <?php
}

function estudiante_layout_fin(string $js_extra = ''): void {
    ?>
        </div>
    </main>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sidebar = document.getElementById('sidebar'), overlay = document.getElementById('sidebarOverlay'),
                  mobileMenu = document.getElementById('mobileMenu'), collapseToggle = document.getElementById('collapseToggle'), body = document.body;
            if (localStorage.getItem('sidebarCollapsed') === 'true' && window.innerWidth > 768) body.classList.add('sidebar-collapsed');
            if (collapseToggle) collapseToggle.addEventListener('click', () => { body.classList.toggle('sidebar-collapsed'); localStorage.setItem('sidebarCollapsed', body.classList.contains('sidebar-collapsed')); });
            if (mobileMenu) mobileMenu.addEventListener('click', () => { sidebar.classList.add('mobile-open'); overlay.classList.add('visible'); body.classList.add('menu-open'); });
            if (overlay) overlay.addEventListener('click', () => { sidebar.classList.remove('mobile-open'); overlay.classList.remove('visible'); body.classList.remove('menu-open'); });
            const profileBtn = document.getElementById('profileBtn'), profileDropdown = document.getElementById('profileDropdown');
            if (profileBtn && profileDropdown) {
                profileBtn.addEventListener('click', (e) => { e.stopPropagation(); profileDropdown.classList.toggle('active'); });
                document.addEventListener('click', (e) => { if (!profileDropdown.contains(e.target)) profileDropdown.classList.remove('active'); });
            }
        });
        <?php echo $js_extra; ?>
    </script>
</body>
</html>
    <?php
}
