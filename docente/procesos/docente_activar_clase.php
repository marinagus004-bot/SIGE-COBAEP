<?php
session_start();
require_once '../../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../../login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_clase = isset($_POST['id_clase']) ? (int)$_POST['id_clase'] : 0;
    $apagar = isset($_POST['apagar']) ? (int)$_POST['apagar'] : 0;
    $enlace = isset($_POST['enlace_clase_virtual']) ? trim($_POST['enlace_clase_virtual']) : null;

    if ($id_clase === 0) {
        header("Location: ../mis_grupos.php?error=Aula no válida.");
        exit();
    }

    try {
        if ($apagar === 1) {
            $stmt = $pdo->prepare("UPDATE clases_regulares SET enlace_clase_virtual = NULL WHERE id_clase = ? AND id_maestro = ?");
            $stmt->execute([$id_clase, $_SESSION['id_usuario']]);
            header("Location: ../gestionar_aula.php?id_clase=" . $id_clase . "&exito=La clase en vivo ha sido finalizada.");
            exit();
        } else {
            if (empty($enlace)) {
                header("Location: ../gestionar_aula.php?id_clase=" . $id_clase . "&error=Debes ingresar un enlace válido.");
                exit();
            }
            $stmt = $pdo->prepare("UPDATE clases_regulares SET enlace_clase_virtual = ? WHERE id_clase = ? AND id_maestro = ?");
            $stmt->execute([$enlace, $id_clase, $_SESSION['id_usuario']]);
            header("Location: ../gestionar_aula.php?id_clase=" . $id_clase . "&exito=Enlace guardado exitosamente.");
            exit();
        }
    } catch (PDOException $e) {
        header("Location: ../gestionar_aula.php?id_clase=" . $id_clase . "&error=Error al procesar la solicitud.");
        exit();
    }
}
?>