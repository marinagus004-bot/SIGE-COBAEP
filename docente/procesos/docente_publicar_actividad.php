<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_clase = isset($_POST['id_clase']) ? (int)$_POST['id_clase'] : 0;
    require_once __DIR__ . '/permisos.php';
    if (!docente_dueno_clase($pdo, $id_clase)) docente_denegar(false);
    $titulo = isset($_POST['titulo']) ? trim($_POST['titulo']) : '';
    $descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
    
    $fecha_limite = !empty($_POST['fecha_limite']) ? $_POST['fecha_limite'] : null;
    $permite_entregas = isset($_POST['permite_entregas']) ? 1 : 0;
    $acepta_retardo = isset($_POST['acepta_retardo']) ? 1 : 0;
    $solo_asistentes = isset($_POST['solo_asistentes']) ? 1 : 0;

    // --- VARIABLES DE VISIBILIDAD Y PROGRAMACIÓN ---
    $visible = isset($_POST['visible']) ? 1 : 0;
    $fecha_apertura = !empty($_POST['fecha_apertura']) ? $_POST['fecha_apertura'] : null;

    // --- NUEVAS VARIABLES DE LÍMITES DE ARCHIVOS ---
    $tamano = isset($_POST['tamano_maximo']) ? (int)$_POST['tamano_maximo'] : 10;
    $archivos = isset($_POST['numero_archivos']) ? (int)$_POST['numero_archivos'] : 1;
    $tipo = isset($_POST['tipo_archivo']) ? $_POST['tipo_archivo'] : '*';

    if ($id_clase === 0 || empty($titulo)) {
        header("Location: ../gestionar_aula.php?id_clase=" . $id_clase . "&error=El título es obligatorio.");
        exit();
    }

    $ruta_bd = null;

    // Guardado del archivo adjunto del profesor (si envió uno)
    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $archivo_prof = $_FILES['archivo'];
        
        if ($archivo_prof['size'] > 10485760) {
            header("Location: ../gestionar_aula.php?id_clase=$id_clase&error=Archivo muy pesado (Max 10MB).");
            exit();
        }
        
        $carpeta_destino = '../../uploads/materiales_clase/';
        if (!is_dir($carpeta_destino)) { mkdir($carpeta_destino, 0777, true); }
        
        $extension = strtolower(pathinfo($archivo_prof['name'], PATHINFO_EXTENSION));
        $nombre_original = pathinfo($archivo_prof['name'], PATHINFO_FILENAME);
        $nombre_limpio = preg_replace("/[^a-zA-Z0-9_-]/", "_", $nombre_original);
        
        $nuevo_nombre = "mat_" . time() . "_" . $nombre_limpio . "." . $extension;
        $ruta_final = $carpeta_destino . $nuevo_nombre;

        if (move_uploaded_file($archivo_prof['tmp_name'], $ruta_final)) {
            $ruta_bd = $nuevo_nombre;
        }
    }

    try {
        $pdo->beginTransaction();

        // 1. Guardamos la actividad (AHORA INCLUYE LOS LÍMITES DE ARCHIVO Y EL ARCHIVO DEL MAESTRO)
        $stmt = $pdo->prepare("INSERT INTO actividades_clase (id_clase, titulo, descripcion, archivo_ruta, fecha_limite, permite_entregas, acepta_retardo, visible, fecha_apertura, tamano_maximo, numero_archivos, tipo_archivo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        // Pasamos exactamente 12 parámetros al execute, en el mismo orden
        $stmt->execute([$id_clase, $titulo, $descripcion, $ruta_bd, $fecha_limite, $permite_entregas, $acepta_retardo, $visible, $fecha_apertura, $tamano, $archivos, $tipo]);
        
        $id_actividad = $pdo->lastInsertId();

        // 2. VISIBILIDAD INTELIGENTE
        if ($solo_asistentes === 1) {
            $fecha_hoy = date('Y-m-d');
            
            // Primero, damos permiso (visible=1) a TODOS los alumnos de la clase
            $stmtPermisoGlobal = $pdo->prepare("
                INSERT INTO visibilidad_actividad (id_actividad, id_alumno, visible)
                SELECT ?, id_alumno, 1 FROM clase_regular_alumno WHERE id_clase = ?
            ");
            $stmtPermisoGlobal->execute([$id_actividad, $id_clase]);

            // Segundo, bloqueamos (visible=0) SOLAMENTE a los que tienen 'Falta' o 'Ausente' hoy en esta clase
            $stmtBloqueo = $pdo->prepare("
                UPDATE visibilidad_actividad va
                INNER JOIN asistencias_clases ac ON va.id_alumno = ac.id_alumno
                SET va.visible = 0
                WHERE va.id_actividad = ? 
                  AND ac.id_clase = ? 
                  AND ac.fecha = ? 
                  AND (ac.estatus = 'Falta' OR ac.estatus = 'Ausente')
            ");
            $stmtBloqueo->execute([$id_actividad, $id_clase, $fecha_hoy]);
        }

        $pdo->commit();
        header("Location: ../gestionar_aula.php?id_clase=" . $id_clase . "&exito=Actividad guardada correctamente.");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        header("Location: ../gestionar_aula.php?id_clase=" . $id_clase . "&error=Error al publicar: " . urlencode($e->getMessage()));
        exit();
    }
}
?>