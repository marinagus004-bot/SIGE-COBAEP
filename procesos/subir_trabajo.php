<?php
// procesos/subir_trabajo.php — Entrega del alumno en un espacio de regularización
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'estudiante') {
    header("Location: ../login.php");
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../estudiante/actividades.php");
    exit();
}

$id_clase  = (int)($_POST['id_clase'] ?? 0);
$id_alumno = (int)$_SESSION['id_usuario'];

function volver_espacio(int $id_clase, string $tipo, string $msg): void {
    header("Location: ../estudiante/espacio_regularizacion.php?id=$id_clase&$tipo=" . urlencode($msg));
    exit();
}

// El alumno debe estar inscrito y el docente debe tener abierta la recepción
$st = $pdo->prepare("
    SELECT c.permitir_entregas, c.estatus, c.visible
    FROM CLASE_ALUMNO ca INNER JOIN CLASES_REGULARIZACION c ON c.id_clase = ca.id_clase
    WHERE ca.id_clase = ? AND ca.id_alumno = ?");
$st->execute([$id_clase, $id_alumno]);
$clase = $st->fetch(PDO::FETCH_ASSOC);
if (!$clase) {
    header("Location: ../estudiante/actividades.php?error=" . urlencode('No tienes acceso a este espacio.'));
    exit();
}
if ((int)$clase['visible'] !== 1) {
    header("Location: ../estudiante/actividades.php?error=" . urlencode('Este curso de regularización ya no está disponible.'));
    exit();
}
if ((int)$clase['permitir_entregas'] !== 1 || $clase['estatus'] !== 'Activa') {
    volver_espacio($id_clase, 'error', 'El docente no está recibiendo trabajos en este momento.');
}

if (!isset($_FILES['archivo_trabajo']) || $_FILES['archivo_trabajo']['error'] !== UPLOAD_ERR_OK) {
    volver_espacio($id_clase, 'error', 'No se adjuntó ningún archivo válido.');
}

$nombre_original = preg_replace('/[^A-Za-z0-9._-]/', '_', $_FILES['archivo_trabajo']['name']);
$extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
$permitidas = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
if (!in_array($extension, $permitidas, true)) {
    volver_espacio($id_clase, 'error', 'Formato no permitido. Usa PDF, Word, PowerPoint, Excel, imagen o ZIP.');
}
if ($_FILES['archivo_trabajo']['size'] > 10 * 1024 * 1024) {
    volver_espacio($id_clase, 'error', 'El archivo no debe pesar más de 10 MB.');
}

$directorio = __DIR__ . '/../uploads/entregas/';
if (!is_dir($directorio)) mkdir($directorio, 0755, true);
$nombre_nuevo = uniqid('entrega_') . '_' . $id_alumno . '_' . $nombre_original;
if (!move_uploaded_file($_FILES['archivo_trabajo']['tmp_name'], $directorio . $nombre_nuevo)) {
    volver_espacio($id_clase, 'error', 'No se pudo guardar el archivo en el servidor.');
}
$ruta = 'uploads/entregas/' . $nombre_nuevo;

try {
    $st = $pdo->prepare("SELECT id_entrega, archivo_ruta, calificacion FROM ENTREGAS_REGULARIZACION WHERE id_clase = ? AND id_alumno = ? LIMIT 1");
    $st->execute([$id_clase, $id_alumno]);
    $previa = $st->fetch(PDO::FETCH_ASSOC);

    if ($previa) {
        if ($previa['calificacion'] !== null) {
            @unlink($directorio . $nombre_nuevo);
            volver_espacio($id_clase, 'error', 'Tu trabajo ya fue calificado; ya no se puede reemplazar.');
        }
        $pdo->prepare("UPDATE ENTREGAS_REGULARIZACION SET archivo_ruta = ?, fecha_entrega = NOW() WHERE id_entrega = ?")
            ->execute([$ruta, $previa['id_entrega']]);
        if (strpos($previa['archivo_ruta'], 'uploads/entregas/') === 0 && is_file(__DIR__ . '/../' . $previa['archivo_ruta'])) {
            unlink(__DIR__ . '/../' . $previa['archivo_ruta']);
        }
        volver_espacio($id_clase, 'exito', 'Tu entrega se reemplazó correctamente.');
    }

    $pdo->prepare("INSERT INTO ENTREGAS_REGULARIZACION (id_clase, id_alumno, archivo_ruta) VALUES (?, ?, ?)")
        ->execute([$id_clase, $id_alumno, $ruta]);
    volver_espacio($id_clase, 'exito', 'Trabajo entregado correctamente.');
} catch (PDOException $e) {
    error_log("Error al subir entrega: " . $e->getMessage());
    volver_espacio($id_clase, 'error', 'Hubo un error al guardar tu trabajo.');
}
