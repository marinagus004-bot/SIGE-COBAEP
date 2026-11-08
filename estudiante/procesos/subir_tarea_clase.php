<?php
session_start();
// Corregimos la ruta para conectar a la base de datos desde estudiante/procesos/
require_once '../../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'estudiante') {
    header("Location: ../../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_alumno = $_SESSION['id_usuario'];
    $id_actividad = isset($_POST['id_actividad']) ? (int)$_POST['id_actividad'] : 0;
    $id_clase = isset($_POST['id_clase']) ? (int)$_POST['id_clase'] : 0;

    if ($id_actividad === 0 || $id_clase === 0) {
        header("Location: ../actividades_escolares.php");
        exit();
    }

    try {
        // Verificar información de la actividad CRUZADA con la visibilidad del alumno
        $stmt_act = $pdo->prepare("
            SELECT ac.fecha_limite, ac.permite_entregas, ac.acepta_retardo, va.fecha_limite_excepcion 
            FROM actividades_clase ac 
            INNER JOIN clase_regular_alumno cra ON cra.id_clase = ac.id_clase AND cra.id_alumno = ? AND cra.acceso = 1
            LEFT JOIN visibilidad_actividad va ON ac.id_actividad = va.id_actividad AND va.id_alumno = ?
            WHERE ac.id_actividad = ? AND ac.id_clase = ? AND ac.visible = 1 AND COALESCE(va.visible, 1) = 1
        ");
        $stmt_act->execute([$id_alumno, $id_alumno, $id_actividad, $id_clase]);
        $actividad = $stmt_act->fetch(PDO::FETCH_ASSOC);

        if (!$actividad || $actividad['permite_entregas'] == 0) {
            header("Location: ../aula_virtual.php?id=" . $id_clase . "&error=Esta actividad no acepta entregas.");
            exit();
        }

        // Evaluar si es tardío considerando la EXTENSIÓN individual si existe
        $estatus_entrega = "A Tiempo";
        $fecha_a_usar = !empty($actividad['fecha_limite_excepcion']) ? $actividad['fecha_limite_excepcion'] : $actividad['fecha_limite'];

        if (!empty($fecha_a_usar)) {
            $fecha_actual = new DateTime();
            $fecha_limite = new DateTime($fecha_a_usar);
            
            if ($fecha_actual > $fecha_limite) {
                if ($actividad['acepta_retardo'] == 0) {
                    header("Location: ../aula_virtual.php?id=" . $id_clase . "&error=La fecha límite ya pasó.");
                    exit();
                } else {
                    $estatus_entrega = "Con Retardo";
                }
            }
        }

        // Procesar archivo
        if (isset($_FILES['archivo_tarea']) && $_FILES['archivo_tarea']['error'] === UPLOAD_ERR_OK) {
            $archivo = $_FILES['archivo_tarea'];
            if ($archivo['size'] > 10485760) {
                header("Location: ../aula_virtual.php?id=" . $id_clase . "&error=Archivo muy grande.");
                exit();
            }

            // CORRECCIÓN 1: Solo subimos dos niveles (procesos -> estudiante -> raíz)
            $carpeta_destino = '../../uploads/entregas_alumnos/';
            
            // Crea la carpeta si no existe
            if (!is_dir($carpeta_destino)) {
                mkdir($carpeta_destino, 0777, true);
            }
            
            // CORRECCIÓN 2: Extraemos la extensión y el nombre original por separado
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            // Nunca aceptar archivos que el servidor podría ejecutar
            if ($extension === '' || preg_match('/^(php\d?|phtml|phar|pht|cgi|pl|py|sh|exe|bat|js|htaccess)$/', $extension)) {
                header("Location: ../aula_virtual.php?id=" . $id_clase . "&error=" . urlencode("Tipo de archivo no permitido."));
                exit();
            }
            $nombre_original = pathinfo($archivo['name'], PATHINFO_FILENAME);
            
            // Limpiamos el nombre original (cambiamos espacios por guiones bajos y quitamos símbolos)
            $nombre_limpio = preg_replace("/[^a-zA-Z0-9_-]/", "_", $nombre_original);
            
            // Unimos todo: ID de alumno + Fecha + Nombre original limpio + extensión
            $nuevo_nombre = "al_" . $id_alumno . "_" . time() . "_" . $nombre_limpio . "." . $extension;
            
            $ruta_final = $carpeta_destino . $nuevo_nombre;

            if (move_uploaded_file($archivo['tmp_name'], $ruta_final)) {
                
                // Guardar SOLO el nombre en la BD
                $ruta_bd = $nuevo_nombre;

                // Guardar en BD
                $fecha_mysql = date('Y-m-d H:i:s');
                $stmt = $pdo->prepare("INSERT INTO entregas_actividad (id_actividad, id_alumno, archivo_ruta, fecha_entrega, estatus_entrega) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$id_actividad, $id_alumno, $ruta_bd, $fecha_mysql, $estatus_entrega]);

                header("Location: ../aula_virtual.php?id=" . $id_clase . "&exito=Tarea enviada correctamente.");
                exit();
            } else {
                header("Location: ../aula_virtual.php?id=" . $id_clase . "&error=No se pudo mover el archivo al servidor.");
                exit();
            }
        } else {
            header("Location: ../aula_virtual.php?id=" . $id_clase . "&error=Debes adjuntar un archivo válido.");
            exit();
        }

    } catch (PDOException $e) {
        error_log("Error subiendo tarea: " . $e->getMessage());
        header("Location: ../aula_virtual.php?id=" . $id_clase . "&error=Error al procesar la entrega.");
        exit();
    }
}
?>