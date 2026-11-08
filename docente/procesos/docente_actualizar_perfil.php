<?php
session_start();
require_once '../../config/database.php';

// Validación estricta de sesión
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../../login.php");
    exit();
}

$id_maestro = $_SESSION['id_usuario'];
$telefono = $_POST['telefono'] ?? '';
$correo = $_POST['correo'] ?? '';
$titulo_academico = $_POST['titulo_academico'] ?? '';
$biografia = $_POST['biografia'] ?? '';

try {
    // Manejo de la subida de foto de perfil
    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['foto_perfil']['tmp_name'];
        $fileName = $_FILES['foto_perfil']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $allowedfileExtensions = ['jpg', 'jpeg', 'png'];
        
        if (in_array($fileExtension, $allowedfileExtensions)) {
            $uploadFileDir = '../../uploads/perfiles/';
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }
            
            $newFileName = 'perfil_' . $id_maestro . '_' . time() . '.' . $fileExtension;
            $dest_path = $uploadFileDir . $newFileName;
            
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $ruta_db = '../uploads/perfiles/' . $newFileName;
                
                // Actualizar con foto (usando PDO)
                $stmt = $pdo->prepare("UPDATE maestros SET telefono = ?, correo = ?, titulo_academico = ?, biografia = ?, foto_perfil = ? WHERE id_maestro = ?");
                $stmt->execute([$telefono, $correo, $titulo_academico, $biografia, $ruta_db, $id_maestro]);
                
                header("Location: ../perfil.php?msg=success");
                exit();
            }
        }
    }

    // Si no se subió foto o hubo error, actualizamos solo el texto
    $stmt = $pdo->prepare("UPDATE maestros SET telefono = ?, correo = ?, titulo_academico = ?, biografia = ? WHERE id_maestro = ?");
    $stmt->execute([$telefono, $correo, $titulo_academico, $biografia, $id_maestro]);
    
    header("Location: ../perfil.php?msg=success");

} catch (PDOException $e) {
    error_log("Error al actualizar perfil: " . $e->getMessage());
    header("Location: ../perfil.php?msg=error");
}
?>