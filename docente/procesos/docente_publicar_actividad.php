<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_clase = isset($_POST['id_clase']) ? (int)$_POST['id_clase'] : 0;
    $titulo = isset($_POST['titulo']) ? trim($_POST['titulo']) : '';
    $descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
    
    // Nuevos campos
    $fecha_limite = !empty($_POST['fecha_limite']) ? $_POST['fecha_limite'] : null;
    $permite_entregas = isset($_POST['permite_entregas']) ? 1 : 0;
    $acepta_retardo = isset($_POST['acepta_retardo']) ? 1 : 0;

    if ($id_clase === 0 || empty($titulo)) {
        header("Location: ../gestionar_aula.php?id_clase=" . $id_clase . "&error=El título es obligatorio.");
        exit();
    }

    $ruta_bd = null;

    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
        $archivo = $_FILES['archivo'];
        $nombre_archivo = $archivo['name'];
        if ($archivo['size'] > 10485760) {
            header("Location: ../gestionar_aula.php?id_clase=$id_clase&error=Archivo muy pesado (Max 10MB).");
            exit();
        }
        $carpeta_destino = '../../../uploads/materiales_clase/';
        if (!is_dir($carpeta_destino)) mkdir($carpeta_destino, 0777, true);
        
        $nombre_limpio = preg_replace("/[^a-zA-Z0-9.-]/", "_", $nombre_archivo);
        $nuevo_nombre = "mat_" . time() . "_" . $nombre_limpio;
        $ruta_final = $carpeta_destino . $nuevo_nombre;

        if (move_uploaded_file($archivo['tmp_name'], $ruta_final)) {
            $ruta_bd = "uploads/materiales_clase/" . $nuevo_nombre;
        }
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO actividades_clase (id_clase, titulo, descripcion, archivo_ruta, fecha_limite, permite_entregas, acepta_retardo) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id_clase, $titulo, $descripcion, $ruta_bd, $fecha_limite, $permite_entregas, $acepta_retardo]);
        header("Location: ../gestionar_aula.php?id_clase=" . $id_clase . "&exito=Actividad publicada con opciones de entrega.");
        exit();
    } catch (PDOException $e) {
        header("Location: ../gestionar_aula.php?id_clase=" . $id_clase . "&error=Error BD.");
        exit();
    }
}
?>