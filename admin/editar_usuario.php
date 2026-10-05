<?php
// admin/editar_usuario.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET['id']) || !isset($_GET['tipo'])) {
    header("Location: gestion_usuarios.php");
    exit();
}

$id_editar = intval($_GET['id']);
$tipo = $_GET['tipo'];
$usuario = null;
$grupos = [];
$turnos_catalogo = [];

$etiquetas_rol = [
    'alumnos'   => 'Alumno / Estudiante',
    'maestros'  => 'Personal Docente',
    'admin'     => 'Administrativo',
    'prefectos' => 'Prefectura'
];
$nombre_rol_visual = $etiquetas_rol[$tipo] ?? strtoupper($tipo);

try {
    if ($tipo === 'alumnos') {
        $sql = "SELECT a.id_alumno AS id, a.matricula AS identificador, a.codigo_qr, a.turno, 
                       a.nombre, a.apellido_paterno, a.apellido_materno, a.estatus, a.puntos_conducta, ga.id_grupo
                FROM ALUMNOS a
                LEFT JOIN GRUPO_ALUMNO ga ON a.id_alumno = ga.id_alumno AND ga.fecha_baja IS NULL
                WHERE a.id_alumno = ?";
        $stmt = $pdo->prepare($sql);

        $grupos = $pdo->query("SELECT id_grupo, semestre, nombre_grupo, turno FROM GRUPOS ORDER BY semestre, nombre_grupo ASC")->fetchAll();
        $turnos_catalogo = $pdo->query("SELECT turno, hora_entrada, hora_corte FROM HORARIOS_TURNO")->fetchAll();

    } elseif ($tipo === 'maestros') {
        $stmt = $pdo->prepare("SELECT id_maestro AS id, usuario AS identificador, nombre, apellido_paterno, apellido_materno FROM MAESTROS WHERE id_maestro = ?");
    } elseif ($tipo === 'admin') {
        $stmt = $pdo->prepare("SELECT id_admin AS id, usuario AS identificador, nombre, apellido_paterno, apellido_materno FROM ADMINISTRATIVOS WHERE id_admin = ?");
    } elseif ($tipo === 'prefectos') {
        $stmt = $pdo->prepare("SELECT id_prefecto AS id, usuario AS identificador, nombre, apellido_paterno, apellido_materno FROM PREFECTOS WHERE id_prefecto = ?");
    } else {
        header("Location: gestion_usuarios.php");
        exit();
    }
    
    $stmt->execute([$id_editar]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        header("Location: gestion_usuarios.php?tipo=" . urlencode($tipo) . "&error=" . urlencode("El usuario solicitado no existe."));
        exit();
    }
} catch (PDOException $e) {
    die("Error de BD: " . $e->getMessage());
}

$iniciales = strtoupper(
    mb_substr($usuario['nombre'], 0, 1, 'UTF-8') . 
    mb_substr($usuario['apellido_paterno'], 0, 1, 'UTF-8')
);

$nombre_admin = isset($_SESSION['nombre'])
    ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8')
    : 'Administrador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#063f2b">
    <title>Editar Usuario · SIGE-Cobaep</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="stylesheet" href="style_dashboard_admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="style-editar_usuario.css?v=<?php echo time(); ?>">
</head>
<body>

    <!-- ===== BARRA LATERAL ===== -->
    <aside class="sidebar" id="sidebar">
        <button id="collapseToggle" class="collapse-toggle" type="button" aria-label="Contraer menú">
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
                <a href="dashboard_admin.php" class="nav-item">
                    <i class="fas fa-house"></i>
                    <span>Panel de Control</span>
                </a>

                <span class="nav-heading">Académico</span>
                <a href="gestion_grupos.php" class="nav-item">
                    <i class="fas fa-users-rectangle"></i>
                    <span>Gestión de Grupos</span>
                </a>
                <a href="gestion_horarios.php" class="nav-item">
                    <i class="fas fa-calendar-days"></i>
                    <span>Horarios Docentes</span>
                </a>

                <span class="nav-heading">Personal</span>
                <a href="gestion_usuarios.php?tipo=<?php echo urlencode($tipo); ?>" class="nav-item active">
                    <i class="fas fa-users"></i>
                    <span>Directorio de Usuarios</span>
                </a>
                <a href="agregar_usuario.php" class="nav-item">
                    <i class="fas fa-user-plus"></i>
                    <span>Nuevo Registro</span>
                </a>

                <span class="nav-heading">Información</span>
                <a href="reportes.php" class="nav-item">
                    <i class="fas fa-chart-column"></i>
                    <span>Reportes y Listas</span>
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

    <!-- ===== CONTENIDO PRINCIPAL ===== -->
    <main class="main-content">

        <!-- TOPBAR -->
        <header class="edit-topbar">
            <div class="edit-topbar-left">
                <button class="mobile-menu" id="mobileMenu" type="button" aria-label="Abrir menú">
                    <i class="fas fa-bars"></i>
                </button>

                <div class="breadcrumb">
                    <a href="gestion_usuarios.php?tipo=<?php echo urlencode($tipo); ?>">Directorio</a>
                    <i class="fas fa-chevron-right"></i>
                    <span><?php echo htmlspecialchars($nombre_rol_visual); ?></span>
                    <i class="fas fa-chevron-right"></i>
                    <strong>Editar Perfil</strong>
                </div>
            </div>

            <div class="topbar-right">
                <div class="date-pill">
                    <i class="far fa-calendar"></i>
                    <span id="fechaTexto"><?php echo date('d/m/Y, h:i a'); ?></span>
                </div>

                <div class="profile">
                    <div class="profile-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="profile-info">
                        <strong><?php echo $nombre_admin; ?></strong>
                        <span>Administrador</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- ENCABEZADO DE PÁGINA -->
        <section class="edit-header">
            <div class="edit-header-left">
                <div class="edit-header-icon">
                    <i class="fas fa-user-pen"></i>
                </div>
                <div class="edit-header-copy">
                    <span>GESTIÓN DE CUENTAS</span>
                    <h1>Edición de Usuario</h1>
                </div>
            </div>

            <a href="gestion_usuarios.php?tipo=<?php echo urlencode($tipo); ?>" class="btn-back-directory">
                <i class="fas fa-arrow-left"></i> Volver al Directorio
            </a>
        </section>

        <!-- BANNERS DE RESULTADO (ÉXITO O EXCEPCIÓN) -->
        <?php if (isset($_GET['exito'])): ?>
            <div class="banner-status banner-success">
                <i class="fas fa-circle-check"></i>
                <div>
                    <?php if ($_GET['exito'] === 'password'): ?>
                        ¡Contraseña restablecida correctamente! El usuario ya puede ingresar con su clave provisional.
                    <?php else: ?>
                        ¡Usuario editado correctamente! Los cambios se guardaron y sincronizaron en la base de datos.
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="banner-status banner-error">
                <i class="fas fa-triangle-exclamation"></i>
                <div>
                    <strong>Error al procesar la solicitud:</strong><br>
                    <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- WORKSPACE A 2 COLUMNAS -->
        <div class="edit-workspace">

            <!-- COLUMNA IZQUIERDA: FORMULARIO PRINCIPAL -->
            <section class="edit-card">
                <div class="edit-card-header">
                    <h2><i class="fas fa-id-card-clip"></i> Datos Generales y Académicos</h2>
                    <span class="role-pill <?php echo htmlspecialchars($tipo); ?>">
                        <?php echo htmlspecialchars($nombre_rol_visual); ?>
                    </span>
                </div>

                <form action="../procesos/actualizar_usuario.php" method="POST" id="formEditarUsuario" class="edit-card-body">
                    <input type="hidden" name="id_usuario" value="<?php echo $usuario['id']; ?>">
                    <input type="hidden" name="tipo_usuario" value="<?php echo htmlspecialchars($tipo); ?>">

                    <div class="field-group">
                        <label for="nombre">Nombre(s)</label>
                        <div class="input-icon-wrap">
                            <i class="fas fa-user"></i>
                            <input type="text" name="nombre" id="nombre" value="<?php echo htmlspecialchars($usuario['nombre']); ?>" required>
                        </div>
                    </div>

                    <div class="fields-grid-2">
                        <div class="field-group">
                            <label for="apellido_p">Apellido Paterno</label>
                            <div class="input-icon-wrap">
                                <i class="fas fa-signature"></i>
                                <input type="text" name="apellido_p" id="apellido_p" value="<?php echo htmlspecialchars($usuario['apellido_paterno']); ?>" required>
                            </div>
                        </div>

                        <div class="field-group">
                            <label for="apellido_m">Apellido Materno</label>
                            <div class="input-icon-wrap">
                                <i class="fas fa-signature"></i>
                                <input type="text" name="apellido_m" id="apellido_m" value="<?php echo htmlspecialchars($usuario['apellido_materno'] ?? ''); ?>" placeholder="Opcional">
                            </div>
                        </div>
                    </div>

                    <?php if ($tipo === 'alumnos'): ?>
                        <div class="fields-grid-2">
                            <div class="field-group">
                                <label for="identificador">Matrícula Oficial</label>
                                <div class="input-icon-wrap">
                                    <i class="fas fa-id-badge"></i>
                                    <input type="text" name="identificador" id="identificador" value="<?php echo htmlspecialchars($usuario['identificador']); ?>" required>
                                </div>
                            </div>

                            <div class="field-group">
                                <label for="codigo_qr">Código QR de Acceso</label>
                                <div class="input-icon-wrap">
                                    <i class="fas fa-qrcode"></i>
                                    <input type="text" name="codigo_qr" id="codigo_qr" value="<?php echo htmlspecialchars($usuario['codigo_qr']); ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="fields-grid-2">
                            <div class="field-group">
                                <label for="selectGrupoEditar">Grupo Académico</label>
                                <div class="input-icon-wrap">
                                    <i class="fas fa-users-rectangle"></i>
                                    <select name="id_grupo" id="selectGrupoEditar" onchange="sincronizarTurnoGrupo()">
                                        <option value="0">-- Sin grupo asignado --</option>
                                        <?php foreach ($grupos as $g): ?>
                                            <option value="<?php echo $g['id_grupo']; ?>" 
                                                    data-turno="<?php echo htmlspecialchars($g['turno']); ?>"
                                                    <?php if (($usuario['id_grupo'] ?? 0) == $g['id_grupo']) echo 'selected'; ?>>
                                                <?php echo htmlspecialchars($g['semestre'] . '° "' . $g['nombre_grupo'] . '" · ' . $g['turno']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <small class="field-hint">Al elegir un grupo, su Clase de Turno se vincula automáticamente.</small>
                            </div>

                            <div class="field-group">
                                <label for="selectTurnoEditar">Clase de Turno</label>
                                <div class="input-icon-wrap">
                                    <i class="far fa-clock"></i>
                                    <select name="turno" id="selectTurnoEditar">
                                        <?php if (count($turnos_catalogo) > 0): ?>
                                            <?php foreach ($turnos_catalogo as $tc): ?>
                                                <option value="<?php echo htmlspecialchars($tc['turno']); ?>" <?php if ($usuario['turno'] == $tc['turno']) echo 'selected'; ?>>
                                                    <?php echo htmlspecialchars($tc['turno'] . ' (' . substr($tc['hora_entrada'], 0, 5) . ' hrs)'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <option value="Matutino" <?php if($usuario['turno'] == 'Matutino') echo 'selected'; ?>>Matutino</option>
                                            <option value="Intermedio" <?php if($usuario['turno'] == 'Intermedio') echo 'selected'; ?>>Intermedio</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                                <small class="field-hint">Vinculado al catálogo oficial HORARIOS_TURNO.</small>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="field-group">
                            <label for="identificador">Nombre de Usuario (Login de Acceso)</label>
                            <div class="input-icon-wrap">
                                <i class="fas fa-at"></i>
                                <input type="text" name="identificador" id="identificador" value="<?php echo htmlspecialchars($usuario['identificador']); ?>" required>
                            </div>
                            <small class="field-hint">Identificador único con el que inicia sesión en el sistema.</small>
                        </div>
                    <?php endif; ?>

                    <!-- BOTÓN PRINCIPAL -->
                    <div class="form-actions-bar" id="contenedorBotonGuardar">
                        <button type="button" class="btn-save-main" onclick="mostrarBannerConfirmacion()">
                            <i class="fas fa-floppy-disk"></i> Guardar Cambios
                        </button>
                    </div>

                    <!-- BANNER DE CONFIRMACIÓN PREVIA -->
                    <div class="confirm-banner" id="bannerConfirmacion">
                        <div class="confirm-banner-head">
                            <i class="fas fa-circle-question"></i>
                            <span>¿Confirmas la actualización de este usuario?</span>
                        </div>
                        <p>
                            Estás a punto de modificar los datos de <strong id="nombreConfirmacion"><?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido_paterno']); ?></strong>. Verifica que la información sea correcta antes de guardar.
                        </p>
                        <div class="confirm-btn-group">
                            <button type="button" class="btn-confirm-no" onclick="ocultarBannerConfirmacion()">
                                Cancelar y revisar
                            </button>
                            <button type="submit" class="btn-confirm-yes">
                                <i class="fas fa-check"></i> Sí, aplicar cambios
                            </button>
                        </div>
                    </div>
                </form>
            </section>

            <!-- COLUMNA DERECHA: VISTA PREVIA + SEGURIDAD -->
            <aside class="side-panel">

                <!-- TARJETA DE VISTA PREVIA EN VIVO -->
                <div class="profile-preview-card">
                    <div class="preview-avatar" id="previewAvatar"><?php echo htmlspecialchars($iniciales); ?></div>
                    <h3 id="previewNombreCompleto">
                        <?php echo htmlspecialchars(trim($usuario['nombre'] . ' ' . $usuario['apellido_paterno'] . ' ' . ($usuario['apellido_materno'] ?? ''))); ?>
                    </h3>
                    <div class="preview-sub" id="previewIdentificador">
                        <?php echo ($tipo === 'alumnos' ? 'Matrícula: ' : 'Usuario: ') . htmlspecialchars($usuario['identificador']); ?>
                    </div>

                    <div class="preview-meta-list">
                        <div class="preview-meta-item">
                            <span>Categoría</span>
                            <strong><?php echo htmlspecialchars($nombre_rol_visual); ?></strong>
                        </div>
                        <?php if ($tipo === 'alumnos'): ?>
                            <div class="preview-meta-item">
                                <span>Clase de Turno</span>
                                <strong id="previewTurnoTexto"><?php echo htmlspecialchars($usuario['turno']); ?></strong>
                            </div>
                            <div class="preview-meta-item">
                                <span>Conducta</span>
                                <strong><?php echo intval($usuario['puntos_conducta'] ?? 100); ?> / 100 pts</strong>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- TARJETA DE SEGURIDAD (RESTABLECER CONTRASEÑA) -->
                <div class="security-card">
                    <div class="security-card-head">
                        <div class="security-icon">
                            <i class="fas fa-key"></i>
                        </div>
                        <div>
                            <h3>Seguridad de Acceso</h3>
                            <small>Restablecimiento de clave</small>
                        </div>
                    </div>

                    <p class="security-desc">
                        Si el usuario olvidó su contraseña, puedes asignarle una clave provisional. Su acceso anterior quedará invalidado de inmediato.
                    </p>

                    <form action="../procesos/restablecer_password.php" method="POST" onsubmit="return confirm('¿Estás seguro de restablecer la contraseña de este usuario?');">
                        <input type="hidden" name="id_usuario" value="<?php echo htmlspecialchars($usuario['id']); ?>">
                        <input type="hidden" name="tipo_usuario" value="<?php echo htmlspecialchars($tipo); ?>">

                        <div class="field-group">
                            <label for="nueva_password">Clave Provisional</label>
                            <div class="input-icon-wrap">
                                <i class="fas fa-lock"></i>
                                <input type="text" name="nueva_password" id="nueva_password" value="Cobaep2026!" required>
                            </div>
                        </div>

                        <button type="submit" class="btn-reset-password">
                            <i class="fas fa-rotate"></i> Restablecer Contraseña
                        </button>
                    </form>
                </div>

            </aside>

        </div>

    </main>

    <script>
        function sincronizarTurnoGrupo() {
            const selGrupo = document.getElementById('selectGrupoEditar');
            const selTurno = document.getElementById('selectTurnoEditar');
            const previewTurno = document.getElementById('previewTurnoTexto');
            if (selGrupo && selTurno) {
                const opt = selGrupo.options[selGrupo.selectedIndex];
                const turnoGrupo = opt.getAttribute('data-turno');
                if (turnoGrupo) {
                    selTurno.value = turnoGrupo;
                    if (previewTurno) previewTurno.textContent = turnoGrupo;
                }
            }
        }

        function mostrarBannerConfirmacion() {
            const form = document.getElementById('formEditarUsuario');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            const nom = document.getElementById('nombre').value.trim();
            const apP = document.getElementById('apellido_p').value.trim();
            document.getElementById('nombreConfirmacion').textContent = nom + ' ' + apP;

            document.getElementById('contenedorBotonGuardar').style.display = 'none';
            document.getElementById('bannerConfirmacion').classList.add('active');
        }

        function ocultarBannerConfirmacion() {
            document.getElementById('bannerConfirmacion').classList.remove('active');
            document.getElementById('contenedorBotonGuardar').style.display = 'flex';
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Menú lateral y colapso
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const mobileMenu = document.getElementById('mobileMenu');
            const collapseToggle = document.getElementById('collapseToggle');
            const body = document.body;

            if (localStorage.getItem('sidebarCollapsed') === 'true') {
                body.classList.add('sidebar-collapsed');
            }
            if (collapseToggle) {
                collapseToggle.addEventListener('click', () => {
                    body.classList.toggle('sidebar-collapsed');
                    localStorage.setItem('sidebarCollapsed', body.classList.contains('sidebar-collapsed'));
                });
            }
            if (mobileMenu) mobileMenu.addEventListener('click', () => {
                sidebar.classList.add('mobile-open');
                overlay.classList.add('visible');
                body.classList.add('menu-open');
            });
            if (overlay) overlay.addEventListener('click', () => {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('visible');
                body.classList.remove('menu-open');
            });

            // Vista previa reactiva en tiempo real
            const inputNom = document.getElementById('nombre');
            const inputApP = document.getElementById('apellido_p');
            const inputApM = document.getElementById('apellido_m');
            const inputId  = document.getElementById('identificador');
            const selTurno = document.getElementById('selectTurnoEditar');

            function actualizarTarjetaViva() {
                const n = inputNom.value.trim();
                const p = inputApP.value.trim();
                const m = inputApM ? inputApM.value.trim() : '';
                document.getElementById('previewNombreCompleto').textContent = (n + ' ' + p + ' ' + m).trim() || 'Usuario';
                
                const ini = (n.charAt(0) + p.charAt(0)).toUpperCase();
                if (ini) document.getElementById('previewAvatar').textContent = ini;

                if (inputId) {
                    const prefijo = '<?php echo $tipo === "alumnos" ? "Matrícula: " : "Usuario: "; ?>';
                    document.getElementById('previewIdentificador').textContent = prefijo + inputId.value.trim();
                }
                if (selTurno && document.getElementById('previewTurnoTexto')) {
                    document.getElementById('previewTurnoTexto').textContent = selTurno.value;
                }
            }

            [inputNom, inputApP, inputApM, inputId].forEach(el => {
                if (el) el.addEventListener('input', actualizarTarjetaViva);
            });
            if (selTurno) selTurno.addEventListener('change', actualizarTarjetaViva);
        });
    </script>
</body>
</html>