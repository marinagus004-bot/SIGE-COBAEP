<?php
/**
 * docente/procesos/regularizacion_acciones.php
 * Acciones del espacio de regularización (detalle_regularizacion.php):
 * configuración, edición/eliminación del espacio, bloques y materiales,
 * vinculación de grupos, baja de alumnos y calificación de entregas.
 */
session_start();
require_once '../../config/database.php';
require_once 'permisos.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../../login.php");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../regularizaciones.php");
    exit();
}

$accion   = $_POST['accion'] ?? '';
$id_clase = (int)($_POST['id_clase'] ?? 0);
$es_ajax  = ($accion === 'toggle_entregas');

function volver(int $id_clase, string $tipo, string $msg): void {
    $pagina = (($_POST['volver'] ?? '') === 'alumnos') ? 'regularizacion_alumnos.php' : 'detalle_regularizacion.php';
    header("Location: ../$pagina?id=$id_clase&$tipo=" . urlencode($msg));
    exit();
}

function borrar_archivo(?string $ruta): void {
    if ($ruta && strpos($ruta, 'uploads/') === 0) {
        $abs = __DIR__ . '/../../' . $ruta;
        if (is_file($abs)) @unlink($abs);
    }
}

/** Guarda un archivo subido en uploads/$carpeta y devuelve la ruta relativa (o null). */
function guardar_subida(string $campo, string $carpeta, string $prefijo, array $permitidas): ?string {
    if (!isset($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($_FILES[$campo]['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('No se pudo subir el archivo.');
    $nombre = preg_replace('/[^A-Za-z0-9._-]/', '_', $_FILES[$campo]['name']);
    $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
    if (!in_array($ext, $permitidas, true)) throw new RuntimeException('Tipo de archivo no permitido.');
    $dir = __DIR__ . "/../../uploads/$carpeta/";
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $final = uniqid($prefijo) . '_' . $nombre;
    if (!move_uploaded_file($_FILES[$campo]['tmp_name'], $dir . $final)) throw new RuntimeException('No se pudo guardar el archivo.');
    return "uploads/$carpeta/$final";
}

if ($id_clase <= 0 || !docente_dueno_regularizacion($pdo, $id_clase)) {
    if ($es_ajax) docente_denegar(true);
    header("Location: ../regularizaciones.php?error=" . urlencode('No tienes permiso para modificar este espacio.'));
    exit();
}

try {
    switch ($accion) {

        // ---------------- Configuración ----------------
        case 'toggle_entregas':
            $valor = !empty($_POST['valor']) ? 1 : 0;
            $pdo->prepare("UPDATE CLASES_REGULARIZACION SET permitir_entregas = ? WHERE id_clase = ?")->execute([$valor, $id_clase]);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'valor' => $valor,
                'message' => $valor ? 'Los alumnos ya pueden subir sus trabajos.' : 'Recepción de trabajos cerrada.']);
            exit();

        // ---------------- Espacio ----------------
        case 'toggle_visible':
            $visible = !empty($_POST['visible']) ? 1 : 0;
            if ($visible) {
                $pdo->prepare("UPDATE CLASES_REGULARIZACION SET visible = 1 WHERE id_clase = ?")->execute([$id_clase]);
                volver($id_clase, 'exito', 'El curso vuelve a estar visible para los alumnos. Activa la Recepción de Trabajos si quieres recibir entregas.');
            }
            // Al ocultarlo también se cierra la recepción de trabajos
            $pdo->prepare("UPDATE CLASES_REGULARIZACION SET visible = 0, permitir_entregas = 0 WHERE id_clase = ?")->execute([$id_clase]);
            volver($id_clase, 'exito', 'Curso oculto: los alumnos ya no lo ven y no pueden hacer más entregas.');

        case 'editar_espacio':
            $nombre = trim($_POST['nombre_materia'] ?? '');
            $desc   = trim($_POST['descripcion'] ?? '');
            $inicio = $_POST['fecha_inicio'] ?: null;
            $fin    = $_POST['fecha_fin'] ?: null;
            $estatus = in_array($_POST['estatus'] ?? '', ['Activa', 'Finalizada', 'Cancelada'], true) ? $_POST['estatus'] : 'Activa';
            if ($nombre === '') volver($id_clase, 'error', 'El nombre del espacio es obligatorio.');
            if ($inicio && $fin && $fin < $inicio) volver($id_clase, 'error', 'La fecha límite no puede ser anterior a la de inicio.');

            $portada = guardar_subida('imagen_portada', 'portadas', 'portada_', ['jpg', 'jpeg', 'png', 'webp']);
            if ($portada) {
                $st = $pdo->prepare("SELECT imagen_portada FROM CLASES_REGULARIZACION WHERE id_clase = ?");
                $st->execute([$id_clase]);
                borrar_archivo($st->fetchColumn() ?: null);
                $pdo->prepare("UPDATE CLASES_REGULARIZACION SET imagen_portada = ? WHERE id_clase = ?")->execute([$portada, $id_clase]);
            }
            $pdo->prepare("UPDATE CLASES_REGULARIZACION SET nombre_materia = ?, descripcion = ?, fecha_inicio = ?, fecha_fin = ?, estatus = ? WHERE id_clase = ?")
                ->execute([$nombre, $desc, $inicio, $fin, $estatus, $id_clase]);
            volver($id_clase, 'exito', 'Información del espacio actualizada.');

        case 'eliminar_espacio':
            $archivos = $pdo->prepare("
                SELECT imagen_portada FROM CLASES_REGULARIZACION WHERE id_clase = ?
                UNION ALL SELECT archivo_ruta FROM ACTIVIDADES_REGULARIZACION WHERE id_clase = ?
                UNION ALL SELECT archivo_ruta FROM ENTREGAS_REGULARIZACION WHERE id_clase = ?");
            $archivos->execute([$id_clase, $id_clase, $id_clase]);
            $rutas = $archivos->fetchAll(PDO::FETCH_COLUMN);
            // Bloques, materiales, entregas e inscripciones se borran en cascada
            $pdo->prepare("DELETE FROM CLASES_REGULARIZACION WHERE id_clase = ?")->execute([$id_clase]);
            foreach ($rutas as $r) borrar_archivo($r);
            header("Location: ../regularizaciones.php?exito=" . urlencode('Espacio de regularización eliminado.'));
            exit();

        // ---------------- Bloques ----------------
        case 'editar_bloque':
            $titulo = trim($_POST['titulo_bloque'] ?? '');
            if ($titulo === '') volver($id_clase, 'error', 'El título del bloque es obligatorio.');
            $pdo->prepare("UPDATE BLOQUES_REGULARIZACION SET titulo_bloque = ? WHERE id_bloque = ? AND id_clase = ?")
                ->execute([$titulo, (int)$_POST['id_bloque'], $id_clase]);
            volver($id_clase, 'exito', 'Bloque actualizado.');

        case 'eliminar_bloque':
            // El material del bloque pasa a "Materiales Generales" (FK con SET NULL)
            $pdo->prepare("DELETE FROM BLOQUES_REGULARIZACION WHERE id_bloque = ? AND id_clase = ?")
                ->execute([(int)$_POST['id_bloque'], $id_clase]);
            volver($id_clase, 'exito', 'Bloque eliminado. Su material pasó a Materiales Generales.');

        // ---------------- Materiales ----------------
        case 'editar_material':
            $id_act = (int)$_POST['id_actividad'];
            $titulo = trim($_POST['titulo'] ?? '');
            $id_bloque = !empty($_POST['id_bloque']) ? (int)$_POST['id_bloque'] : null;
            if ($titulo === '') volver($id_clase, 'error', 'El título del material es obligatorio.');
            if ($id_bloque) {
                $st = $pdo->prepare("SELECT 1 FROM BLOQUES_REGULARIZACION WHERE id_bloque = ? AND id_clase = ?");
                $st->execute([$id_bloque, $id_clase]);
                if (!$st->fetchColumn()) $id_bloque = null;
            }
            $st = $pdo->prepare("SELECT archivo_ruta FROM ACTIVIDADES_REGULARIZACION WHERE id_actividad = ? AND id_clase = ?");
            $st->execute([$id_act, $id_clase]);
            $actual = $st->fetch(PDO::FETCH_ASSOC);
            if (!$actual) volver($id_clase, 'error', 'El material no existe.');

            $nuevo = guardar_subida('archivo', 'materiales', 'mat_', ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip', 'txt']);
            $ruta = $actual['archivo_ruta'];
            if ($nuevo) { borrar_archivo($ruta); $ruta = $nuevo; }
            if (!empty($_POST['quitar_archivo']) && !$nuevo) { borrar_archivo($ruta); $ruta = null; }

            $pdo->prepare("UPDATE ACTIVIDADES_REGULARIZACION SET titulo = ?, descripcion = ?, id_bloque = ?, archivo_ruta = ? WHERE id_actividad = ? AND id_clase = ?")
                ->execute([$titulo, trim($_POST['descripcion'] ?? ''), $id_bloque, $ruta, $id_act, $id_clase]);
            volver($id_clase, 'exito', 'Material actualizado.');

        case 'eliminar_material':
            $st = $pdo->prepare("SELECT archivo_ruta FROM ACTIVIDADES_REGULARIZACION WHERE id_actividad = ? AND id_clase = ?");
            $st->execute([(int)$_POST['id_actividad'], $id_clase]);
            $ruta = $st->fetchColumn();
            $pdo->prepare("DELETE FROM ACTIVIDADES_REGULARIZACION WHERE id_actividad = ? AND id_clase = ?")
                ->execute([(int)$_POST['id_actividad'], $id_clase]);
            borrar_archivo($ruta ?: null);
            volver($id_clase, 'exito', 'Material eliminado.');

        // ---------------- Alumnos ----------------
        case 'vincular_grupo':
            $id_grupo = (int)($_POST['id_grupo'] ?? 0);
            // Solo grupos que el docente tiene asignados
            $st = $pdo->prepare("
                SELECT 1 FROM GRUPO_MAESTRO WHERE id_grupo = ? AND id_maestro = ?
                UNION SELECT 1 FROM HORARIOS_MAESTRO WHERE id_grupo = ? AND id_maestro = ?");
            $st->execute([$id_grupo, $_SESSION['id_usuario'], $id_grupo, $_SESSION['id_usuario']]);
            if (!$st->fetchColumn()) volver($id_clase, 'error', 'Ese grupo no está asignado a ti.');

            $st = $pdo->prepare("
                INSERT IGNORE INTO CLASE_ALUMNO (id_clase, id_alumno, id_asignador, tipo_asignador, fecha_asignacion)
                SELECT ?, ga.id_alumno, ?, 'maestro', CURDATE()
                FROM GRUPO_ALUMNO ga INNER JOIN ALUMNOS a ON a.id_alumno = ga.id_alumno
                WHERE ga.id_grupo = ? AND ga.fecha_baja IS NULL AND a.estatus = 'Alta'");
            $st->execute([$id_clase, $_SESSION['id_usuario'], $id_grupo]);
            $n = $st->rowCount();
            volver($id_clase, 'exito', $n > 0 ? "Se inscribieron $n alumnos del grupo." : 'Todos los alumnos de ese grupo ya estaban inscritos.');

        case 'quitar_alumno':
            $pdo->prepare("DELETE FROM CLASE_ALUMNO WHERE id_clase = ? AND id_alumno = ?")
                ->execute([$id_clase, (int)$_POST['id_alumno']]);
            volver($id_clase, 'exito', 'Alumno dado de baja del espacio.');

        // ---------------- Entregas ----------------
        case 'calificar_entrega':
            $cal = trim($_POST['calificacion'] ?? '');
            if ($cal !== '' && (!is_numeric($cal) || $cal < 0 || $cal > 10)) volver($id_clase, 'error', 'La calificación debe estar entre 0 y 10.');
            $pdo->prepare("UPDATE ENTREGAS_REGULARIZACION SET calificacion = ? WHERE id_entrega = ? AND id_clase = ?")
                ->execute([$cal === '' ? null : round((float)$cal, 1), (int)$_POST['id_entrega'], $id_clase]);
            volver($id_clase, 'exito', 'Calificación guardada.');

        default:
            volver($id_clase, 'error', 'Acción no reconocida.');
    }
} catch (RuntimeException $e) {
    volver($id_clase, 'error', $e->getMessage());
} catch (PDOException $e) {
    error_log('Regularización (' . $accion . '): ' . $e->getMessage());
    if ($es_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error de base de datos.']);
        exit();
    }
    volver($id_clase, 'error', 'Ocurrió un error al guardar los cambios.');
}
