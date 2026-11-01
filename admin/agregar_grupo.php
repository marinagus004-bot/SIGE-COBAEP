<?php
session_start();
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../login.php");
    exit();
}
$nombre_usuario = isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') : 'Administrador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#063f2b">
    <title>Apertura de Grupo · SIGE-Cobaep</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <!-- CSS del sistema -->
    <link rel="stylesheet" href="style_dashboard_admin.css">
    <link rel="stylesheet" href="style_sidebar.css">
    <link rel="stylesheet" href="style-agregar-grupos.css">
</head>
<body>

    <!-- SIDEBAR CENTRALIZADO -->
    <?php include 'sidebar.php'; ?>

    <main class="main-content group-page">
        <!-- TOPBAR -->
        <header class="group-topbar">
            <div class="group-topbar-left">
                <button class="mobile-menu" id="mobileMenu" type="button" aria-label="Abrir menú"><i class="fas fa-bars"></i></button>
                <div class="breadcrumb">
                    <span>Académico</span><i class="fas fa-chevron-right"></i><strong>Gestión de Grupos</strong>
                </div>
            </div>
            <div class="group-topbar-right">
                <div class="date-pill">
                    <i class="far fa-calendar"></i><span id="fechaTexto"><?php echo date('d/m/Y, h:i a'); ?></span>
                </div>
                <div class="profile pill-style">
                    <div class="profile-avatar"><i class="fas fa-user"></i></div>
                    <div class="profile-info">
                        <strong><?php echo $nombre_usuario; ?></strong><span>Administrador</span>
                    </div>
                    <i class="fas fa-chevron-down profile-drop-icon"></i>
                </div>
            </div>
        </header>

        <!-- CONTENIDO -->
        <div class="group-content">
            <section class="group-heading">
                <div class="heading-copy">
                    <span class="heading-tag">ACADÉMICO</span>
                    <h1>Apertura de Nuevo Grupo</h1>
                    <p>Registra la información para habilitar un nuevo grupo en el sistema.</p>
                </div>
                <div class="heading-decoration">
                    <div class="decoration-circle">
                        <i class="fas fa-user-group"></i>
                        <span class="decoration-plus plus-one">+</span>
                        <span class="decoration-plus plus-two">+</span>
                        <span class="decoration-plus plus-three">+</span>
                    </div>
                </div>
            </section>

            <section class="setup-card">
                <div class="setup-header">
                    <div class="setup-icon-circle"><i class="fas fa-file-lines"></i></div>
                    <div class="setup-header-text">
                        <h2>Información del Grupo</h2>
                        <p>Asegúrate de verificar el semestre y el turno correspondiente.</p>
                    </div>
                </div>

                <form action="../procesos/guardar_grupo.php" method="POST" class="setup-body" id="groupForm">
                    <!-- CAMPOS -->
                    <div class="setup-fields">
                        <div class="field-row">
                            <div class="field-icon"><i class="fas fa-layer-group"></i></div>
                            <div class="field-control">
                                <label for="semestre">Semestre asignado</label>
                                <div class="select-wrapper">
                                    <select name="semestre" id="semestre" class="clean-input" required>
                                        <option value="" disabled selected>Selecciona el semestre...</option>
                                        <option value="1">1er Semestre</option>
                                        <option value="2">2do Semestre</option>
                                        <option value="3">3er Semestre</option>
                                        <option value="4">4to Semestre</option>
                                        <option value="5">5to Semestre</option>
                                        <option value="6">6to Semestre</option>
                                    </select>
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                        </div>

                        <div class="field-row">
                            <div class="field-icon text-icon">Aa</div>
                            <div class="field-control">
                                <label for="nombre_grupo">Letra del Grupo</label>
                                <input type="text" name="nombre_grupo" id="nombre_grupo" class="clean-input" placeholder="Ej. A, B, C , D" required maxlength="1" autocomplete="off">
                                <small class="field-hint">Solo se permite una letra mayúscula para identificar al grupo.</small>
                            </div>
                        </div>

                        <div class="field-row">
                            <div class="field-icon"><i class="far fa-clock"></i></div>
                            <div class="field-control">
                                <label for="turno">Turno</label>
                                <div class="select-wrapper">
                                    <select name="turno" id="turno" class="clean-input" required>
                                        <option value="" disabled selected>Selecciona un turno...</option>
                                        <option value="Matutino">Matutino</option>
                                        <option value="Intermedio">Intermedio</option>
                                    </select>
                                    <i class="fas fa-chevron-down"></i>
                                </div>
                            </div>
                        </div>

                        <div class="setup-actions">
                            <a href="gestion_grupos.php" class="btn-cancel-text">Cancelar</a>
                            <button type="submit" class="btn-primary-pill">
                                <span>Crear Grupo</span><i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>

                    <!-- VISTA PREVIA -->
                    <aside class="setup-preview">
                        <div class="preview-box">
                            <div class="preview-title">
                                <div class="preview-title-icon"><i class="fas fa-eye"></i></div>
                                <span>Vista previa del grupo</span>
                            </div>
                            <div class="preview-illustration">
                                <div class="preview-glow"></div>
                                <div class="student student-one"><i class="fas fa-user"></i></div>
                                <div class="student student-two"><i class="fas fa-user"></i></div>
                                <div class="student student-three"><i class="fas fa-user"></i></div>
                                <div class="graduation-badge"><i class="fas fa-graduation-cap"></i></div>
                            </div>
                            <div class="preview-content">
                                <h3 id="previewTitle">Nuevo grupo</h3>
                                <p id="previewDescription">Completa la información para generar una vista previa del nuevo grupo.</p>
                            </div>
                            <div class="preview-status" id="previewStatus">Grupo no generado</div>
                        </div>
                    </aside>
                </form>
            </section>
        </div>
    </main>

    <!-- SCRIPTS -->
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const body = document.body;
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const mobileMenu = document.getElementById('mobileMenu');
        const collapseToggle = document.getElementById('collapseToggle');

        // Sidebar Colapsable
        if (localStorage.getItem('sidebarCollapsed') === 'true') body.classList.add('sidebar-collapsed');
        if (collapseToggle) {
            collapseToggle.addEventListener('click', () => {
                body.classList.toggle('sidebar-collapsed');
                localStorage.setItem('sidebarCollapsed', body.classList.contains('sidebar-collapsed'));
            });
        }

        // Menú Móvil
        const abrirMenu = () => { sidebar.classList.add('mobile-open'); overlay?.classList.add('visible'); body.classList.add('menu-open'); };
        const cerrarMenu = () => { sidebar.classList.remove('mobile-open'); overlay?.classList.remove('visible'); body.classList.remove('menu-open'); };
        if (mobileMenu) mobileMenu.addEventListener('click', abrirMenu);
        if (overlay) overlay.addEventListener('click', cerrarMenu);
        window.addEventListener('resize', () => { if (window.innerWidth > 900) cerrarMenu(); });

        // Letra del Grupo y Vista Previa
        const groupInput = document.getElementById('nombre_grupo');
        const semestre = document.getElementById('semestre');
        const turno = document.getElementById('turno');
        const previewTitle = document.getElementById('previewTitle');
        const previewDescription = document.getElementById('previewDescription');
        const previewStatus = document.getElementById('previewStatus');

        const actualizarPreview = () => {
            if (semestre.value && groupInput.value && turno.value) {
                previewTitle.textContent = `${semestre.value} · Grupo ${groupInput.value}`;
                previewDescription.textContent = `Turno ${turno.value}. La información está lista para crear el grupo.`;
                previewStatus.textContent = 'Listo para crear';
                previewStatus.classList.add('ready');
            } else {
                previewTitle.textContent = 'Nuevo grupo';
                previewDescription.textContent = 'Completa la información para generar una vista previa del nuevo grupo.';
                previewStatus.textContent = 'Grupo no generado';
                previewStatus.classList.remove('ready');
            }
        };

        if (groupInput) {
            groupInput.addEventListener('input', function () {
                this.value = this.value.replace(/[^a-zA-Z]/g, '').toUpperCase().substring(0, 1);
                actualizarPreview();
            });
        }
        if (semestre) semestre.addEventListener('change', actualizarPreview);
        if (turno) turno.addEventListener('change', actualizarPreview);

        // Fecha en tiempo real
        const fechaTexto = document.getElementById('fechaTexto');
        const actualizarFecha = () => {
            if (!fechaTexto) return;
            fechaTexto.textContent = new Date().toLocaleString('es-MX', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true });
        };
        actualizarFecha();
        setInterval(actualizarFecha, 30000);
    });
    </script>
</body>
</html>