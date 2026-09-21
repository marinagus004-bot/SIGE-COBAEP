<?php
$rol_esperado = isset($_GET['rol']) ? $_GET['rol'] : 'estudiante';
if ($rol_esperado == 'admin') {
    $rol_nombre = 'Administrador';
} elseif ($rol_esperado == 'docente') {
    $rol_nombre = 'Docente';
} elseif ($rol_esperado == 'prefectos') {
    $rol_nombre = 'Prefectura';
} else {
    $rol_nombre = 'Estudiante';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Iniciar Sesión · COBAEP Plantel 27</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>

    <!-- MENSAJE EMERGENTE (TOAST) FLOTANTE -->
    <?php if (isset($_GET['error'])): ?>
        <div class="toast-notification" id="errorToast">
            <i class="fas fa-exclamation-circle" style="font-size: 1.2rem;"></i>
            <span>El número de control o la contraseña son incorrectos. Revisa tus datos e intenta de nuevo.</span>
        </div>
    <?php endif; ?>

    <main class="login-layout">
        <!-- Panel Izquierdo (Branding) -->
        <section class="brand-panel">
            <div class="brand-content">
                <img src="img/LogoCobaep.png" alt="Logo COBAEP" class="logo-img">
                <h2 class="brand-title">Colegio de Bachilleres del<br>Estado de Puebla</h2>
                <div class="brand-divider"></div>
                <p class="brand-motto">Formamos presente, construimos futuro.</p>
            </div>
            <div class="info-card">
                <div class="info-icon"><i class="fas fa-graduation-cap"></i></div>
                <div class="info-text">
                    <h3>Plantel 27</h3>
                    <p>Comprometidos con la educación y el desarrollo integral de nuestras comunidades.</p>
                </div>
            </div>
        </section>

        <!-- Panel Derecho (Formulario) -->
        <section class="form-panel">
            <header class="top-header">
                <div class="system-badge">
                    <i class="fas fa-shield-alt"></i>
                    <span>Sistema Integral de<br><strong>Gestión Educativa</strong></span>
                </div>
            </header>

            <div class="login-container">
                <div class="login-box">

                    <div class="login-header">
                        <div class="badge-plantel">
                            <i class="fas fa-school"></i> COBAEP · PLANTEL 27
                        </div>
                        <h2 class="title-login">
                            <span class="icon-circle"><i class="fas fa-user"></i></span> Iniciar Sesión
                        </h2>
                        <p class="subtitle">Accede al sistema con tus credenciales</p>
                        <p class="subtitle"><strong>(<?php echo $rol_nombre; ?>)</strong></p>
                        <div class="divider"></div>
                    </div>

                    <form action="procesos/auth.php" method="POST">

                        <!-- INPUT OCULTO PARA VALIDAR EL ROL -->
                        <input type="hidden" name="rol_esperado" value="<?php echo htmlspecialchars($rol_esperado); ?>">

                        <div class="form-group">
                            <label for="matricula"><i class="fas fa-id-card"></i> NÚMERO DE CONTROL</label>
                            <div class="input-wrapper">
                                <i class="fas fa-user input-icon"></i>
                                <input type="text" id="matricula" name="matricula" placeholder="Ingresa tu número de control" required autofocus>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="password"><i class="fas fa-lock"></i> CONTRASEÑA</label>
                            <div class="input-wrapper">
                                <i class="fas fa-key input-icon"></i>
                                <input type="password" id="password" name="password" placeholder="Ingresa tu contraseña" required>
                                <!-- Botón del ojito -->
                                <button type="button" class="toggle-password" id="togglePasswordBtn">
                                    <i class="fas fa-eye-slash" id="toggleIcon"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-options">
                            <label class="checkbox-container">
                                <input type="checkbox" name="remember">
                                Recordar sesión
                            </label>
                            <a href="#" class="forgot-link">¿Olvidaste tu contraseña?</a>
                        </div>

                        <!-- CONTENEDOR DE BOTONES -->
                        <div class="botones-container">
                            <!-- Botón Regresar (IZQUIERDA) -->
                            <a href="index.html" class="btn-submit1">
                                <i class="fas fa-arrow-left"></i> Regresar
                            </a>

                            <!-- Botón Iniciar Sesión (DERECHA) -->
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                            </button>
                        </div>
                    </form>

                    <div class="card-footer">
                        <div class="footer-item">
                            <i class="fas fa-question-circle item-icon"></i>
                            <div>
                                <strong>¿Necesitas ayuda?</strong>
                                <span>Contacta a control escolar</span>
                            </div>
                        </div>
                        <div class="footer-divider"></div>
                        <div class="footer-item">
                            <i class="fas fa-check-circle item-icon"></i>
                            <div>
                                <strong>Acceso seguro</strong>
                                <span>Tus datos están protegidos</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <footer class="bottom-footer">
                <p style="font-size: 15px; text-align: center"  >&copy; <?php echo date('Y'); ?> COBAEP Plantel 27. Todos los derechos reservados.</p>
                <div class="footer-divider-sm"></div>
                <a href="http://www.cobaep.edu.mx/plantel-27/" target="_blank" rel="noopener noreferrer">
                    <img src="img/logoLetrasLaterales.png"  style="width: 150px; height: 80px;" alt="Logo COBAEP" class="footer-logo-img">
                </a>
            </footer>
        </section>
    </main>
    <script src="js/app.js"></script>
</body>
</html>
