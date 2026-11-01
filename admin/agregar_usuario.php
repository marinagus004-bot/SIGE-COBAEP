<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../login.php");
    exit();
}

try {
    $stmtG = $pdo->query("SELECT id_grupo, semestre, nombre_grupo, turno FROM GRUPOS ORDER BY semestre, nombre_grupo ASC");
    $grupos = $stmtG->fetchAll();
} catch (PDOException $e) {
    die("Error al cargar los grupos: " . $e->getMessage());
}

$nombre_usuario = isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') : 'Administrador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#063f2b">
    <title>Registrar Usuario · COBAEP Plantel 27</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <link href="style_dashboard_admin.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link href="style_sidebar.css?v=<?php echo time(); ?>" rel="stylesheet">
    <link href="style_agregar_usuario.css?v=<?php echo time(); ?>" rel="stylesheet">
</head>
<body>

    <!-- BARRA LATERAL CENTRALIZADA -->
    <?php include 'sidebar.php'; ?>

    <!-- CONTENIDO PRINCIPAL -->
    <main class="main-content layout-formulario">
        <button class="mobile-menu" id="mobileMenu" type="button" aria-label="Abrir menú" style="margin-bottom: 20px;">
            <i class="fas fa-bars"></i>
        </button>

        <div class="page-header-pills">
            <div class="header-left-title">
                <div class="icon-bg-light"><i class="fas fa-user-plus"></i></div>
                <div>
                    <h1>Registrar Nuevo Usuario</h1>
                    <p>Ingresa los datos del estudiante, docente, prefectos o administrativo</p>
                </div>
            </div>
            
            <div class="header-right-pills">
                <div class="pill">
                    <i class="far fa-calendar"></i><span id="fechaTexto"><?php echo date('d/m/Y, h:i a'); ?></span>
                </div>
                <div class="pill">
                    <i class="fas fa-user"></i>
                    <div class="user-pill-info">
                        <strong><?php echo $nombre_usuario; ?></strong><span>Administrador</span>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($_GET['exito'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> ¡Usuario guardado y asignado correctamente!
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <div class="form-wrapper">
            <form action="../procesos/guardar_usuario_v2.php" method="POST" id="formRegistro">
                
                <div class="form-group highlight-select">
                    <label for="tipo_usuario"><i class="fas fa-user-tag"></i> Tipo de Usuario</label>
                    <select name="tipo_usuario" id="tipo_usuario" required onchange="toggleCampos()">
                        <option value="alumno">🎓 Alumno (Estudiante)</option>
                        <option value="maestro">👨‍🏫 Maestro (Docente)</option>
                        <option value="admin">👤 Personal Administrativo</option>
                        <option value="prefect">🎖 Prefecto</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="nombre"><i class="fas fa-user"></i> Nombre(s)</label>
                    <input type="text" name="nombre" id="nombre" placeholder="Ej. María" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="apellido_p"><i class="fas fa-user"></i> Apellido Paterno</label>
                        <input type="text" name="apellido_p" id="apellido_p" placeholder="Ej. López" required>
                    </div>
                    <div class="form-group">
                        <label for="apellido_m"><i class="fas fa-user"></i> Apellido Materno</label>
                        <input type="text" name="apellido_m" id="apellido_m" placeholder="Ej. García (opcional)">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="telefono"><i class="fas fa-phone"></i> Teléfono</label>
                        <input type="tel" name="telefono" id="telefono" placeholder="Ej. 2221234567 (opcional)">
                    </div>
                    <div class="form-group">
                        <label for="correo"><i class="fas fa-envelope"></i> Correo Electrónico</label>
                        <input type="email" name="correo" id="correo" placeholder="ejemplo@cobaep.edu.mx (opcional)">
                    </div>
                </div>

                <!-- SECCIÓN ALUMNO -->
                <div id="campos_alumno">
                    <div class="form-group" style="margin-top: 15px;">
                        <label for="id_grupo"><i class="fas fa-users"></i> Asignar a Grupo</label>
                        <select name="id_grupo" id="id_grupo">
                            <option value="">-- Sin grupo asignado (Pendiente) --</option>
                            <?php foreach($grupos as $g): ?>
                                <option value="<?php echo $g['id_grupo']; ?>">
                                    <?php echo htmlspecialchars($g['semestre'] . ' "' . $g['nombre_grupo'] . '" - Turno ' . $g['turno']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="help-text">Puedes asignar el grupo ahora o dejarlo pendiente.</div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="matricula"><i class="fas fa-id-card"></i> Matrícula</label>
                            <input type="text" name="matricula" id="matricula" placeholder="Ej. 24B0012">
                            <div class="help-text">Obligatorio para alumnos. Debe ser única.</div>
                        </div>
                        <div class="form-group">
                            <label for="codigo_qr"><i class="fas fa-qrcode"></i> Código QR</label>
                            <input type="text" name="codigo_qr" id="codigo_qr" placeholder="Ej. QR-24B0012">
                            <div class="help-text">Opcional, recomendado para escáner.</div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="fecha_nacimiento"><i class="far fa-calendar"></i> Fecha de Nacimiento</label>
                            <input type="date" name="fecha_nacimiento" id="fecha_nacimiento">
                        </div>
                        <div class="form-group">
                            <label for="sexo"><i class="fas fa-venus-mars"></i> Sexo</label>
                            <select name="sexo" id="sexo">
                                <option value="">Selecciona...</option>
                                <option value="M">Masculino</option>
                                <option value="F">Femenino</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="turno"><i class="far fa-clock"></i> Turno</label>
                        <select name="turno" id="turno">
                            <option value="Matutino">Matutino</option>
                            <option value="Intermedio">Intermedio</option>
                        </select>
                    </div>
                </div>

                <!-- SECCIÓN STAFF -->
                <div id="campos_staff" style="display: none; margin-top: 15px;">
                    <div class="form-group">
                        <label for="usuario"><i class="far fa-user-circle"></i> Nombre de Usuario (Login)</label>
                        <input type="text" name="usuario" id="usuario" placeholder="Ej. DOC025">
                        <div class="help-text">Nombre único para iniciar sesión (ej. DOC001, ADM002, PREF001).</div>
                    </div>
                </div>

                <!-- CONTRASEÑA -->
                <div class="form-group" style="margin-top: 15px;">
                    <label for="password"><i class="fas fa-lock"></i> Contraseña Provisional</label>
                    <input type="password" name="password" id="password" placeholder="Mínimo 6 caracteres" required>
                    <div class="help-text">El usuario deberá cambiarla en su primer acceso.</div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Guardar Usuario
                </button>
                <div class="form-footer">
                    <a href="dashboard_admin.php" class="btn-cancel">
                        <i class="fas fa-arrow-left"></i> Cancelar y Volver
                    </a>
                </div>
            </form>
        </div>
    </main>

    <!-- SCRIPTS -->
    <script>
        function toggleCampos() {
            const tipo = document.getElementById("tipo_usuario").value;
            const camposAlumno = document.getElementById("campos_alumno");
            const camposStaff = document.getElementById("campos_staff");
            
            if (tipo === "alumno") {
                camposAlumno.style.display = "block";
                camposStaff.style.display = "none";
            } else {
                camposAlumno.style.display = "none";
                camposStaff.style.display = "block";
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            toggleCampos();

            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const mobileMenu = document.getElementById('mobileMenu');
            const collapseToggle = document.getElementById('collapseToggle');
            const body = document.body;
            const fechaTexto = document.getElementById('fechaTexto');

            if (localStorage.getItem('sidebarCollapsed') === 'true') {
                body.classList.add('sidebar-collapsed');
            }

            if (collapseToggle) {
                collapseToggle.addEventListener('click', () => {
                    body.classList.toggle('sidebar-collapsed');
                    localStorage.setItem('sidebarCollapsed', body.classList.contains('sidebar-collapsed'));
                });
            }

            function abrirMenu() {
                sidebar.classList.add('mobile-open');
                if(overlay) overlay.classList.add('visible');
                body.classList.add('menu-open');
            }

            function cerrarMenu() {
                sidebar.classList.remove('mobile-open');
                if(overlay) overlay.classList.remove('visible');
                body.classList.remove('menu-open');
            }

            if(mobileMenu) mobileMenu.addEventListener('click', abrirMenu);
            if(overlay) overlay.addEventListener('click', cerrarMenu);

            window.addEventListener('resize', () => {
                if (window.innerWidth > 900) cerrarMenu();
            });

            function actualizarHora() {
                if (!fechaTexto) return;
                const now = new Date();
                fechaTexto.textContent = now.toLocaleString('es-MX', {
                    day: '2-digit', month: '2-digit', year: 'numeric',
                    hour: '2-digit', minute: '2-digit'
                });
            }
            actualizarHora();
            setInterval(actualizarHora, 60000);
        });
    </script>
</body>
</html>