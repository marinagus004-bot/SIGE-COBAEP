<?php
// procesos/guardar_usuario.php
session_start();
require_once '../config/database.php';

// Verificar que los datos vengan del formulario por el método POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // 1. Recibir y limpiar los datos del formulario
    $matricula = trim($_POST['matricula']);
    $nombre_completo = trim($_POST['nombre_completo']);
    $rol = $_POST['rol'];
    $grado = $_POST['grado'];
    $grupo = $_POST['grupo'];
    $password_plana = $_POST['password'];

    // 2. Encriptar la contraseña (Súper importante)
    $password_hash = password_hash($password_plana, PASSWORD_DEFAULT);

    try {
        // 3. Preparar la consulta SQL para evitar inyecciones (Hacking)
        $sql = "INSERT INTO usuarios (matricula, password_hash, rol, nombre_completo, grado, grupo) 
                VALUES (:matricula, :password_hash, :rol, :nombre_completo, :grado, :grupo)";
        
        $stmt = $pdo->prepare($sql);
        
        // 4. Ejecutar la inserción uniendo las variables
        $exito = $stmt->execute([
            ':matricula' => $matricula,
            ':password_hash' => $password_hash,
            ':rol' => $rol,
            ':nombre_completo' => $nombre_completo,
            ':grado' => $grado,
            ':grupo' => $grupo
        ]);

        // 5. Redirigir de vuelta al formulario con mensaje de éxito
        if ($exito) {
            header("Location: ../admin/agregar_usuario.php?exito=1");
            exit();
        }

    } catch (PDOException $e) {
        // Si hay un error (por ejemplo, si intentas guardar una matrícula que ya existe, 
        // ya que en la BD la pusimos como UNIQUE), captura el error y redirige.
        header("Location: ../admin/agregar_usuario.php?error=1");
        exit();
    }
} else {
    // Si alguien intenta entrar a este archivo escribiendo la URL directamente, lo mandamos afuera
    header("Location: ../login.php");
    exit();
}
?>