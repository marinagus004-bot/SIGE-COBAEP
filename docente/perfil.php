<?php
session_start();
require_once '../config/database.php';
require_once __DIR__ . '/includes/topbar_docente.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../login.php");
    exit();
}

$id_maestro = $_SESSION['id_usuario'];
$nombre_usuario = isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') : 'Docente';

try {
    $stmt = $pdo->prepare("SELECT * FROM maestros WHERE id_maestro = ?");
    $stmt->execute([$id_maestro]);
    $maestro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$maestro) {
        die("Error: No se encontraron los datos del docente.");
    }

    $nombre_completo_url = urlencode($maestro['nombre'] . ' ' . $maestro['apellido_paterno']);
    $avatar_por_defecto = "https://ui-avatars.com/api/?name={$nombre_completo_url}&background=005a35&color=fff&size=150&bold=true";
    $foto_perfil = docente_foto_url($maestro['foto_perfil'] ?? null) ?? $avatar_por_defecto;

} catch (PDOException $e) {
    error_log("Error al cargar perfil: " . $e->getMessage());
    die("Error de conexión a la base de datos.");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#063f2b">
    <title>Mi Perfil · SIGE COBAEP 27</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <!-- Archivo principal de estilos -->
    <link rel="stylesheet" href="style_dashboard_docente.css?v=<?php echo time(); ?>">

    <style>
        /* =========================================================
           ESTILOS ESPECÍFICOS DE PERFIL (RESPONSIVOS)
           ========================================================= */
        
        .perfil-card {
            background: #ffffff;
            border: 1px solid var(--line);
            border-radius: 20px;
            padding: 30px 40px;
            box-shadow: var(--shadow-sm);
            width: 100%;
            box-sizing: border-box;
            transition: all 0.3s ease;
        }

        /* HEADER DEL PERFIL (Avatar + Nombres) */
        .perfil-header-box {
            display: flex;
            align-items: center;
            gap: 25px;
            margin-bottom: 25px;
            padding-bottom: 25px;
            border-bottom: 1px solid var(--line);
        }

        .foto-upload-wrapper {
            position: relative;
            width: 110px;
            height: 110px;
            border-radius: 50%;
            cursor: pointer;
            border: 4px solid var(--green);
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(22, 160, 93, 0.2);
            flex-shrink: 0; /* Evita que el círculo se aplaste en móviles */
        }
        
        .foto-preview {
            width: 100%;
            height: 100%;
            object-fit: cover;
            background-color: var(--background);
            transition: filter 0.3s;
        }

        .foto-overlay {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(3, 43, 30, 0.7);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: white;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .foto-upload-wrapper:hover .foto-overlay {
            opacity: 1;
        }

        .foto-overlay i { font-size: 22px; margin-bottom: 4px; }
        .foto-overlay span { font-size: 11px; font-weight: 700; text-transform: uppercase; }

        .perfil-info-titles h2 {
            margin: 0;
            color: var(--ink);
            font-size: 1.5rem;
            font-weight: 800;
            letter-spacing: -0.035em;
        }
        .perfil-info-titles p {
            margin: 4px 0 0 0;
            color: var(--muted);
            font-size: 0.85rem;
            font-weight: 500;
        }

        /* ESTRUCTURA DEL FORMULARIO */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px 25px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 8px;
        }

        .input-modern {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #dce7e2;
            border-radius: 10px;
            font-family: inherit;
            font-size: 0.9rem;
            transition: 0.2s;
            box-sizing: border-box;
            background: #fafbfc;
            color: var(--ink);
        }

        .input-modern:focus {
            border-color: var(--green);
            background: #ffffff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(22, 160, 93, 0.1);
        }

        .input-modern:read-only {
            background-color: var(--line);
            color: var(--muted);
            cursor: not-allowed;
            border-color: #dce7e2;
        }

        .form-note {
            display: block;
            color: var(--muted);
            font-size: 0.75rem;
            margin-top: 5px;
            font-style: italic;
        }

        /* BOTÓN DE GUARDAR */
        .btn-guardar {
            background: var(--green);
            color: #ffffff;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 10px rgba(22,160,93,0.2);
            transition: 0.2s;
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
        }
        
        .btn-guardar:hover {
            background: var(--green-800);
            transform: translateY(-2px);
        }

        .alert-success {
            background: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        /* =========================================================
           MEDIA QUERIES (RESPONSIVO PARA MÓVILES Y TABLETS)
           ========================================================= */
        @media (max-width: 768px) {
            .perfil-card {
                padding: 20px; /* Menos padding lateral en móvil */
            }
            .form-row {
                grid-template-columns: 1fr; /* Una sola columna en móvil */
                gap: 15px;
            }
            .perfil-header-box {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            .btn-guardar {
                width: 100%; /* Botón ocupa todo el ancho en celular */
            }
        }
    </style>
    <style>
        .alert-perfil-error { background: #fef2f2 !important; color: #991b1b !important; border-color: #fecaca !important; }
        .foto-ayuda { display: block; margin-top: 6px; color: var(--muted); font-size: 0.78rem; }
        .seguridad-card { margin-top: 22px; scroll-margin-top: 20px; }
        .seguridad-titulo { display: flex; gap: 14px; align-items: flex-start; margin-bottom: 18px; }
        .seguridad-icono { width: 46px; height: 46px; border-radius: 14px; background: #fff6e6; color: #d97706; display: grid; place-items: center; font-size: 1.1rem; flex-shrink: 0; }
        .seguridad-titulo h3 { margin: 0 0 4px; font-size: 1.05rem; color: var(--ink); }
        .seguridad-titulo p { margin: 0; color: var(--muted); font-size: 0.85rem; }
        .campo-password { position: relative; }
        .campo-password .input-modern { padding-right: 44px; width: 100%; }
        .ver-password { position: absolute; right: 8px; top: 50%; transform: translateY(-50%); border: none; background: transparent; color: var(--muted); cursor: pointer; padding: 6px; }
        .ver-password:hover { color: var(--green); }
        .fuerza { height: 5px; background: #e2e8f0; border-radius: 4px; margin-top: 8px; overflow: hidden; }
        .fuerza span { display: block; height: 100%; width: 0; transition: .25s; }
    </style>
</head>

<body>
    <!-- SCRIPT ANTI-PARPADEO: Debe ir exactamente aquí, antes de cualquier HTML -->
    <script>
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            document.body.classList.add('sidebar-collapsed', 'preload-transitions');
        }
    </script>
    <!-- SIDEBAR IGUAL QUE EN TU DASHBOARD -->
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
                <a href="dashboard_docente.php" class="nav-item">
                    <i class="fas fa-house"></i><span>Inicio</span>
                </a>
                <span class="nav-heading">Académico</span>
                <a href="mis_grupos.php" class="nav-item">
                    <i class="fas fa-users-rectangle"></i><span>Mis Grupos</span>
                </a>
                <a href="horario.php" class="nav-item">
                    <i class="far fa-calendar-alt"></i><span>Mi Horario</span>
                </a>
                <a href="horarios_plantel.php" class="nav-item"><i class="fas fa-calendar-week"></i><span>Horarios del Plantel</span></a>
                <span class="nav-heading">Evaluación</span>
                <a href="regularizaciones.php" class="nav-item">
                    <i class="fas fa-book-open-reader"></i><span>Regularizaciones</span>
                </a>
            </nav>

            <div class="sidebar-bottom">
                <div class="institution">
                    <div class="institution-icon"><i class="fas fa-building-columns"></i></div>
                    <div>
                        <strong>COBAEP Plantel 27</strong>
                        <span>Portal Docente</span>
                    </div>
                </div>
                <a href="../logout.php" class="logout">
                    <i class="fas fa-arrow-right-from-bracket"></i><span>Cerrar Sesión</span>
                </a>
            </div>
        </div>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="main-content">
        <!-- TOPBAR -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu" id="mobileMenu" type="button" aria-label="Abrir menú">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <span class="eyebrow">PORTAL DOCENTE · SIGE</span>
                    <h1>Configuración de Perfil</h1>
                </div>
            </div>

            <?php docente_topbar($pdo); ?>
        </header>

        <section class="section">
            <div class="perfil-card">
                <?php if(isset($_GET['msg']) && $_GET['msg'] == 'success'): ?>
                    <div class="alert-success">
                        <i class="fas fa-check-circle"></i> Tu perfil se ha actualizado correctamente.
                    </div>
                <?php elseif (isset($_GET['msg']) && $_GET['msg'] == 'error'): ?>
                    <div class="alert-success alert-perfil-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_GET['texto'] ?? 'No se pudieron guardar los cambios.'); ?>
                    </div>
                <?php endif; ?>

                <form action="procesos/docente_actualizar_perfil.php" method="POST" enctype="multipart/form-data">
                    
                    <div class="perfil-header-box">
                        <!-- AVATAR INTERACTIVO -->
                        <label for="foto" class="foto-upload-wrapper" title="Clic para cambiar foto">
                            <img src="<?php echo htmlspecialchars($foto_perfil); ?>" alt="Foto de Perfil" id="imagenPrevisualizacion" class="foto-preview" onerror="this.src='<?php echo $avatar_por_defecto; ?>'">
                            <div class="foto-overlay">
                                <i class="fas fa-camera"></i>
                                <span>Subir Foto</span>
                            </div>
                        </label>
                        <input type="file" name="foto_perfil" id="foto" accept="image/png, image/jpeg, image/webp" style="display: none;">
                        
                        <div class="perfil-info-titles">
                            <h2><?php echo htmlspecialchars($maestro['nombre'] . ' ' . $maestro['apellido_paterno'] . ' ' . $maestro['apellido_materno']); ?></h2>
                            <p>Docente - COBAEP Plantel 27</p>
                            <small class="foto-ayuda"><i class="fas fa-camera"></i> Haz clic en tu foto para cambiarla. También se mostrará arriba, junto a tu nombre.</small>
                        </div>
                    </div>

                    <!-- CAMPOS DEL FORMULARIO CON ESTILO "INPUT-MODERN" -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nombre(s):</label>
                            <input type="text" class="input-modern" value="<?php echo htmlspecialchars($maestro['nombre']); ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label>Apellidos:</label>
                            <input type="text" class="input-modern" value="<?php echo htmlspecialchars($maestro['apellido_paterno'] . ' ' . $maestro['apellido_materno']); ?>" readonly>
                            <span class="form-note"><i class="fas fa-info-circle"></i> Cambios de nombre solo en administración.</span>
                        </div>
                        
                        <div class="form-group">
                            <label for="titulo">Título Académico:</label>
                            <input type="text" name="titulo_academico" id="titulo" class="input-modern" value="<?php echo htmlspecialchars($maestro['titulo_academico'] ?? ''); ?>" placeholder="Ej. Ing. en Informática">
                        </div>

                        <div class="form-group">
                            <label for="telefono">Teléfono de Contacto:</label>
                            <input type="text" name="telefono" id="telefono" class="input-modern" value="<?php echo htmlspecialchars($maestro['telefono'] ?? ''); ?>" placeholder="Ej. 2331448139">
                        </div>

                        <div class="form-group full-width">
                            <label for="correo">Correo Electrónico Institucional / Personal:</label>
                            <input type="email" name="correo" id="correo" class="input-modern" value="<?php echo htmlspecialchars($maestro['correo'] ?? ''); ?>" placeholder="ejemplo@cobaep.edu.mx">
                        </div>

                        <div class="form-group full-width">
                            <label for="biografia">Presentación / Biografía corta:</label>
                            <textarea name="biografia" id="biografia" class="input-modern" rows="3" placeholder="Escribe un breve mensaje para tus alumnos..."><?php echo htmlspecialchars($maestro['biografia'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div style="margin-top: 15px; text-align: right;" class="full-width">
                        <button type="submit" class="btn-guardar">
                            <i class="fas fa-save"></i> Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>

            <!-- SEGURIDAD: CAMBIAR CONTRASEÑA -->
            <div class="perfil-card seguridad-card" id="seguridad">
                <div class="seguridad-titulo">
                    <div class="seguridad-icono"><i class="fas fa-key"></i></div>
                    <div>
                        <h3>Seguridad · Cambiar contraseña</h3>
                        <p>Usa al menos 8 caracteres combinando letras y números. Si olvidaste tu contraseña actual, pide a Control Escolar que la restablezca.</p>
                    </div>
                </div>

                <?php if (isset($_GET['pwd'])): ?>
                    <div class="alert-success <?php echo $_GET['pwd'] === 'ok' ? '' : 'alert-perfil-error'; ?>">
                        <i class="fas <?php echo $_GET['pwd'] === 'ok' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($_GET['texto'] ?? ''); ?>
                    </div>
                <?php endif; ?>

                <form action="procesos/docente_cambiar_password.php" method="POST" id="formPassword" autocomplete="off">
                    <div class="form-row">
                        <div class="form-group full-width">
                            <label for="password_actual">Contraseña actual:</label>
                            <div class="campo-password">
                                <input type="password" name="password_actual" id="password_actual" class="input-modern" required autocomplete="current-password">
                                <button type="button" class="ver-password" aria-label="Mostrar contraseña"><i class="far fa-eye"></i></button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="password_nueva">Nueva contraseña:</label>
                            <div class="campo-password">
                                <input type="password" name="password_nueva" id="password_nueva" class="input-modern" required minlength="8" autocomplete="new-password">
                                <button type="button" class="ver-password" aria-label="Mostrar contraseña"><i class="far fa-eye"></i></button>
                            </div>
                            <div class="fuerza"><span id="barraFuerza"></span></div>
                            <span class="form-note" id="textoFuerza">Mínimo 8 caracteres, con letras y números.</span>
                        </div>
                        <div class="form-group">
                            <label for="password_confirmar">Confirmar nueva contraseña:</label>
                            <div class="campo-password">
                                <input type="password" name="password_confirmar" id="password_confirmar" class="input-modern" required minlength="8" autocomplete="new-password">
                                <button type="button" class="ver-password" aria-label="Mostrar contraseña"><i class="far fa-eye"></i></button>
                            </div>
                            <span class="form-note" id="textoCoincide"></span>
                        </div>
                    </div>
                    <div style="margin-top: 15px; text-align: right;">
                        <button type="submit" class="btn-guardar" id="btnPassword"><i class="fas fa-lock"></i> Cambiar contraseña</button>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <script src="dashboard_maestro.js"></script>
    
    <!-- LÓGICA PARA PREVISUALIZAR LA IMAGEN -->
    <script>
        document.getElementById('foto').addEventListener('change', function(event) {
            const archivo = event.target.files[0];
            if (archivo) {
                const lector = new FileReader();
                lector.onload = function(e) {
                    document.getElementById('imagenPrevisualizacion').src = e.target.result;
                }
                lector.readAsDataURL(archivo);
            }
        });

        // ---- Cambiar contraseña: mostrar/ocultar, fuerza y coincidencia ----
        document.querySelectorAll('.ver-password').forEach(b => b.addEventListener('click', () => {
            const input = b.previousElementSibling;
            const ver = input.type === 'password';
            input.type = ver ? 'text' : 'password';
            b.querySelector('i').className = ver ? 'far fa-eye-slash' : 'far fa-eye';
            b.setAttribute('aria-label', ver ? 'Ocultar contraseña' : 'Mostrar contraseña');
        }));
        const nueva = document.getElementById('password_nueva'), conf = document.getElementById('password_confirmar');
        const barra = document.getElementById('barraFuerza'), txtF = document.getElementById('textoFuerza'), txtC = document.getElementById('textoCoincide');
        function revisar() {
            const v = nueva.value;
            let p = 0;
            if (v.length >= 8) p++; if (/[A-Za-z]/.test(v) && /\d/.test(v)) p++; if (/[A-Z]/.test(v) && /[a-z]/.test(v)) p++; if (/[^A-Za-z0-9]/.test(v) || v.length >= 12) p++;
            const niveles = [['0%', '#e2e8f0', 'Mínimo 8 caracteres, con letras y números.'], ['30%', '#ef4444', 'Débil'], ['55%', '#f59e0b', 'Aceptable'], ['80%', '#16a05d', 'Buena'], ['100%', '#0f7a45', 'Muy segura']];
            const n = v ? niveles[Math.max(1, p)] : niveles[0];
            barra.style.width = n[0]; barra.style.background = n[1]; txtF.textContent = n[2];
            txtC.textContent = conf.value ? (conf.value === v ? '✔ Las contraseñas coinciden' : '✖ No coinciden') : '';
            txtC.style.color = conf.value === v ? '#16a05d' : '#dc2626';
        }
        nueva.addEventListener('input', revisar); conf.addEventListener('input', revisar);
        document.getElementById('formPassword').addEventListener('submit', e => {
            if (nueva.value !== conf.value) { e.preventDefault(); revisar(); conf.focus(); }
        });
    </script>
</body>
</html>