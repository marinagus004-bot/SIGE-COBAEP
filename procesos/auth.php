<?php
// procesos/auth.php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // El formulario envía "matricula", pero funciona como el usuario general para los 4 roles
    $username_ingresado = trim($_POST['matricula']); 
    $password = $_POST['password'];
    $usuario_encontrado = null;

    // 1. Buscar en ALUMNOS
    $stmt_al = $pdo->prepare("
        SELECT a.id_alumno AS id_usuario, a.matricula AS usuario, a.contrasena_hash, 
               CONCAT(a.nombre, ' ', a.apellido_paterno) AS nombre_completo,
               g.semestre AS grado, g.nombre_grupo AS grupo, 'estudiante' AS rol
        FROM ALUMNOS a
        LEFT JOIN GRUPO_ALUMNO ga ON a.id_alumno = ga.id_alumno AND ga.fecha_baja IS NULL
        LEFT JOIN GRUPOS g ON ga.id_grupo = g.id_grupo
        WHERE a.matricula = ?
    ");
    $stmt_al->execute([$username_ingresado]);
    $usuario_encontrado = $stmt_al->fetch();

    // 2. Si no es alumno, buscar en MAESTROS
    if (!$usuario_encontrado) {
        $stmt_ma = $pdo->prepare("
            SELECT id_maestro AS id_usuario, usuario, contrasena_hash, 
                   CONCAT(nombre, ' ', apellido_paterno) AS nombre_completo,
                   NULL AS grado, NULL AS grupo, 'docente' AS rol
            FROM MAESTROS WHERE usuario = ?
        ");
        $stmt_ma->execute([$username_ingresado]);
        $usuario_encontrado = $stmt_ma->fetch();
    }

    // 3. Si no es maestro, buscar en ADMINISTRATIVOS
    if (!$usuario_encontrado) {
        $stmt_ad = $pdo->prepare("
            SELECT id_admin AS id_usuario, usuario, contrasena_hash, 
                   CONCAT(nombre, ' ', apellido_paterno) AS nombre_completo,
                   NULL AS grado, NULL AS grupo, 'administrativo' AS rol
            FROM ADMINISTRATIVOS WHERE usuario = ?
        ");
        $stmt_ad->execute([$username_ingresado]);
        $usuario_encontrado = $stmt_ad->fetch();
    }

    // 4. Si no es administrativo, buscar en PREFECTOS
    if (!$usuario_encontrado) {
        $stmt_pref = $pdo->prepare("
            SELECT id_prefecto AS id_usuario, usuario, contrasena_hash, 
                   CONCAT(nombre, ' ', apellido_paterno) AS nombre_completo,
                   NULL AS grado, NULL AS grupo, 'prefectos' AS rol
            FROM prefectos WHERE usuario = ?
        ");
        $stmt_pref->execute([$username_ingresado]);
        $usuario_encontrado = $stmt_pref->fetch();
    }

    

    // 5. Verificar la contraseña si se encontró a alguien
    if ($usuario_encontrado && password_verify($password, $usuario_encontrado['contrasena_hash'])) {
        
        // ==============================================================
        // NUEVA VALIDACIÓN: VERIFICAR QUE EL ROL COINCIDA CON EL PORTAL
        // ==============================================================
        $rol_real = $usuario_encontrado['rol']; 
        $rol_esperado = isset($_POST['rol_esperado']) ? $_POST['rol_esperado'] : '';

        // Ajustamos la variable para que coincida con la base de datos
        if ($rol_esperado === 'admin') {
            $rol_esperado = 'administrativo';
        }

        // Si el rol de la base de datos NO es igual al de la tarjeta elegida:
        if ($rol_real !== $rol_esperado) {
            // Regresamos a la pantalla de login con error respetando su variable original de URL
            $rol_url = ($rol_esperado === 'administrativo') ? 'admin' : $rol_esperado;
            header("Location: ../login.php?rol=" . $rol_url . "&error=1"); 
            exit();
        }
        // ==============================================================
        
        // Configurar el "Gafete VIP" (Sesiones) unificadas
        $_SESSION['id_usuario'] = $usuario_encontrado['id_usuario'];
        $_SESSION['matricula']  = $usuario_encontrado['usuario'];
        $_SESSION['rol']        = $usuario_encontrado['rol'];
        $_SESSION['nombre']     = $usuario_encontrado['nombre_completo'];
        $_SESSION['grado']      = $usuario_encontrado['grado'];
        $_SESSION['grupo']      = $usuario_encontrado['grupo'];

        // Redirigir al panel correspondiente
        switch ($usuario_encontrado['rol']) {
            case 'administrativo':
                header("Location: ../admin/dashboard_admin.php");
                break;
            case 'docente':
                header("Location: ../docente/dashboard_docente.php");
                break;
            case 'prefectos':
                // Asegúrate de que esta ruta coincida con la ubicación real de tu archivo
                header("Location: ../Prefectura/dashboard_prefectura.php"); 
                break;
            case 'estudiante':
                header("Location: ../estudiante/dashboard_estud.php");
                break;
        }
        exit();
    } else {
        // Obtenemos el rol para mantenerlo en la URL si hay error de contraseña
        $rol_url = isset($_POST['rol_esperado']) ? $_POST['rol_esperado'] : 'estudiante';
        header("Location: ../login.php?rol=" . $rol_url . "&error=1");
        exit();
    }
} else {
    header("Location: ../login.php");
    exit();
}
?>