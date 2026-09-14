<?php
// instalar_v2.php
require_once 'config/database.php';

$pass_estudiante = password_hash('12345', PASSWORD_DEFAULT);
$pass_admin = password_hash('admin123', PASSWORD_DEFAULT);
$pass_maestro = password_hash('profe123', PASSWORD_DEFAULT);

try {
    // 1. Insertar al Alumno (Usando la nueva estructura)
    $sql_alumno = "INSERT INTO ALUMNOS (matricula, codigo_qr, turno, nombre, apellido_paterno, apellido_materno, contrasena_hash) 
                   VALUES ('23B0001', 'QR-23B0001', 'Matutino', 'Rodrigo', 'Martínez', 'Parra', '$pass_estudiante')";
    $pdo->exec($sql_alumno);

    // 2. Insertar al Administrativo
    $sql_admin = "INSERT INTO ADMINISTRATIVOS (nombre, apellido_paterno, usuario, contrasena_hash) 
                  VALUES ('Control', 'Escolar', 'ADMIN01', '$pass_admin')";
    $pdo->exec($sql_admin);

    // 3. Insertar al Maestro
    $sql_maestro = "INSERT INTO MAESTROS (nombre, apellido_paterno, usuario, contrasena_hash) 
                    VALUES ('Carlos', 'Ramírez', 'DOC001', '$pass_maestro')";
    $pdo->exec($sql_maestro);
    
    echo "<h2 style='color: green; text-align: center;'>✅ Usuarios creados en la nueva BD. Ya puedes probar el Login.</h2>";

} catch (PDOException $e) {
    echo "<h2 style='color: red;'>Error al insertar: " . $e->getMessage() . "</h2>";
}
?>