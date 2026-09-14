<?php
// admin/editar_usuario.php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../login.php");
    exit();
}

// Validar que recibimos los parámetros necesarios
if (!isset($_GET['id']) || !isset($_GET['tipo'])) {
    header("Location: gestion_usuarios.php");
    exit();
}

$id_editar = $_GET['id'];
$tipo = $_GET['tipo'];
$usuario = null;

try {
    // Consultar la tabla correcta según el tipo
    if ($tipo === 'alumnos') {
        $stmt = $pdo->prepare("SELECT id_alumno AS id, matricula AS identificador, codigo_qr, turno, nombre, apellido_paterno FROM ALUMNOS WHERE id_alumno = ?");
    } elseif ($tipo === 'maestros') {
        $stmt = $pdo->prepare("SELECT id_maestro AS id, usuario AS identificador, nombre, apellido_paterno FROM MAESTROS WHERE id_maestro = ?");
    } elseif ($tipo === 'admin') {
        $stmt = $pdo->prepare("SELECT id_admin AS id, usuario AS identificador, nombre, apellido_paterno FROM ADMINISTRATIVOS WHERE id_admin = ?");
    }
    
    $stmt->execute([$id_editar]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        die("Usuario no encontrado en la base de datos.");
    }
} catch (PDOException $e) {
    die("Error de BD: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Usuario - COBAEP 27</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background-color: #f0f7f4; padding: 40px; display: flex; justify-content: center; }
        .form-container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); width: 100%; max-width: 500px; border-top: 5px solid #1a7541; }
        h2 { color: #112d4e; margin-top: 0; text-align: center; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; color: #555; font-weight: bold; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; }
        .btn-submit { width: 100%; padding: 12px; background-color: #1a7541; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight: bold; font-size: 16px; margin-top: 10px; }
        .badge { background: #e0f2e9; color: #1a7541; padding: 5px 10px; border-radius: 5px; font-size: 14px; margin-bottom: 20px; display: inline-block; }
    </style>
</head>
<body>

<div class="form-container">
    <h2>✏️ Editar Registro</h2>
    <div style="text-align: center;">
        <span class="badge">Modificando perfil de: <?php echo strtoupper($tipo); ?></span>
    </div>

    <form action="../procesos/actualizar_usuario.php" method="POST">
        <input type="hidden" name="id_usuario" value="<?php echo $usuario['id']; ?>">
        <input type="hidden" name="tipo_usuario" value="<?php echo $tipo; ?>">

        <div class="form-group" style="display: flex; gap: 10px;">
            <div style="flex: 1;">
                <label>Nombre(s):</label>
                <input type="text" name="nombre" value="<?php echo htmlspecialchars($usuario['nombre']); ?>" required>
            </div>
            <div style="flex: 1;">
                <label>Apellido Paterno:</label>
                <input type="text" name="apellido_p" value="<?php echo htmlspecialchars($usuario['apellido_paterno']); ?>" required>
            </div>
        </div>

        <?php if ($tipo === 'alumnos'): ?>
            <div class="form-group">
                <label>Matrícula:</label>
                <input type="text" name="identificador" value="<?php echo htmlspecialchars($usuario['identificador']); ?>" required>
            </div>
            <div class="form-group">
                <label>Código QR:</label>
                <input type="text" name="codigo_qr" value="<?php echo htmlspecialchars($usuario['codigo_qr']); ?>" required>
            </div>
            <div class="form-group">
                <label>Turno:</label>
                <select name="turno">
                    <option value="Matutino" <?php if($usuario['turno'] == 'Matutino') echo 'selected'; ?>>Matutino</option>
                    <option value="Intermedio" <?php if($usuario['turno'] == 'Intermedio') echo 'selected'; ?>>Intermedio</option>
                </select>
            </div>
        <?php else: ?>
            <div class="form-group">
                <label>Nombre de Usuario (Login):</label>
                <input type="text" name="identificador" value="<?php echo htmlspecialchars($usuario['identificador']); ?>" required>
            </div>
        <?php endif; ?>

        <button type="submit" class="btn-submit">Guardar Cambios</button>
    </form>
    <!-- SEPARADOR -->
    <hr style="margin: 30px 0; border: 0; border-top: 2px dashed #e5e7eb;">
    
    <!-- SEPARADOR -->
    <hr style="margin: 30px 0; border: 0; border-top: 1px solid #e5e7eb;">
    
    <!-- SECCIÓN DE SEGURIDAD (DISCRETA Y DESPLEGABLE) -->
    <details style="background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; margin-bottom: 20px;">
        
        <!-- Título visible por defecto -->
        <summary style="padding: 15px; cursor: pointer; font-weight: 600; color: #4b5563; list-style: none; user-select: none;">
            <i class="fas fa-shield-halved" style="color: #9ca3af; margin-right: 8px;"></i> 
            Opciones de seguridad de la cuenta
            <span style="float: right; font-size: 0.8rem; color: #9ca3af;">▼</span>
        </summary>
        
        <!-- Contenido que se despliega al hacer clic -->
        <div style="padding: 20px; border-top: 1px solid #e5e7eb; background-color: #ffffff;">
            
            <!-- Mensaje de advertencia/instrucción -->
            <div style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 12px 15px; margin-bottom: 20px; border-radius: 4px;">
                <h4 style="margin: 0 0 5px 0; color: #b45309; font-size: 0.95rem;">Restablecer Contraseña</h4>
                <p style="margin: 0; font-size: 0.85rem; color: #92400e; line-height: 1.4;">
                    Al generar una contraseña provisional, el usuario perderá su acceso actual. Deberá ingresar con esta nueva clave y el sistema le pedirá que la cambie por motivos de seguridad.
                </p>
            </div>
            
            <form action="../procesos/restablecer_password.php" method="POST">
                <input type="hidden" name="id_usuario" value="<?php echo htmlspecialchars($usuario['id']); ?>">
                <input type="hidden" name="tipo_usuario" value="<?php echo htmlspecialchars($tipo); ?>">
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="color: #4b5563; font-size: 0.9rem; margin-bottom: 8px; display: block;">Nueva Contraseña Provisional:</label>
                    <input type="text" name="nueva_password" value="Cobaep2026!" required style="width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; background-color: #f9fafb;">
                </div>
                
                <button type="submit" style="background-color: #f59e0b; color: white; border: none; padding: 12px 18px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.9rem; transition: background 0.2s;">
                    <i class="fas fa-sync-alt" style="margin-right: 5px;"></i> Restablecer Acceso
                </button>
            </form>

        </div>
    </details>
    
    <div style="text-align: center; margin-top: 15px;">
        <a href="gestion_usuarios.php?tipo=<?php echo $tipo; ?>" style="color: #112d4e; text-decoration: none;">← Cancelar y Volver</a>
    </div>
</div>

</body>
</html>