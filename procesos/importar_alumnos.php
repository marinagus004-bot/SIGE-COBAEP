<?php
session_start();
require_once '../config/database.php';

// Validar que sea administrativo
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../login.php");
    exit();
}

if (isset($_FILES['archivo_csv']) && $_FILES['archivo_csv']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['archivo_csv']['tmp_name'];
    
    // Configuración inicial de seguridad
    $password_default = "Cobaep2026!";
    $hash_seguro = password_hash($password_default, PASSWORD_BCRYPT);
    
    $registros_exitosos = 0;
    $registros_duplicados = 0;
    
    // Abrimos el archivo en modo lectura
    if (($handle = fopen($fileTmpPath, "r")) !== FALSE) {
        
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            
            // Según el Excel analizado, los datos están en estos índices:
            $matricula = trim($data[1] ?? '');
            $nombre_completo = trim($data[4] ?? '');
            $estatus_excel = trim($data[5] ?? '');
            
            // Ignorar filas de encabezados institucionales o vacías
            if (empty($matricula) || empty($nombre_completo) || strtolower($matricula) == 'matrícula') {
                continue;
            }
            
            // 1. VERIFICAR DUPLICADOS
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM ALUMNOS WHERE matricula = ?");
            $stmtCheck->execute([$matricula]);
            if ($stmtCheck->fetchColumn() > 0) {
                $registros_duplicados++;
                continue; // Saltamos este registro y pasamos al siguiente
            }
            
            // 2. MAGIA DE NOMBRES: Unimos apellidos compuestos temporalmente
            $nombre_norm = str_replace(
                ['DE LA ', 'DEL ', 'DE ', 'SAN '], 
                ['DE_LA_', 'DEL_', 'DE_', 'SAN_'], 
                $nombre_completo
            );
            $partes = explode(' ', $nombre_norm);
            
            // Revertimos el formato para guardarlo limpio
            $apellido_p = str_replace('_', ' ', $partes[0] ?? '');
            $apellido_m = str_replace('_', ' ', $partes[1] ?? '');
            $nombre_pila = count($partes) > 2 ? str_replace('_', ' ', implode(' ', array_slice($partes, 2))) : '';
            
            // 3. PREPARAR DATOS AUTOMÁTICOS
            $codigo_qr = "QR" . str_replace('/', '', $matricula);
            $turno = 'Matutino'; // Valor por defecto
            
            // Si el excel dice "B.T" u otra variante de baja, lo aplicamos
            $estatus = 'Alta';
            if(strpos(strtoupper($estatus_excel), 'B.T') !== false || strpos(strtoupper($estatus_excel), 'BAJA') !== false) {
                 $estatus = 'Baja'; 
            }
            
            // 4. INSERCIÓN EN BASE DE DATOS
            $sql = "INSERT INTO ALUMNOS 
                    (matricula, codigo_qr, contrasena_hash, turno, nombre, apellido_paterno, apellido_materno, estatus, puntos_conducta) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 100)";
            $stmtInsert = $pdo->prepare($sql);
            $stmtInsert->execute([
                $matricula, $codigo_qr, $hash_seguro, $turno, $nombre_pila, $apellido_p, $apellido_m, $estatus
            ]);
            
            $registros_exitosos++;
        }
        fclose($handle);
    }
    
    // Devolvemos al usuario con los resultados
    echo "<script>
        alert('Importación finalizada.\\n\\n✅ Nuevos registrados: {$registros_exitosos}\\n⚠️ Omitidos (Ya existían): {$registros_duplicados}');
        window.location.href = '../admin/gestion_usuarios.php?tipo=alumnos';
    </script>";
    exit();

} else {
    echo "<script>
        alert('Error: No se pudo subir el archivo.');
        window.location.href = '../admin/gestion_usuarios.php?tipo=alumnos';
    </script>";
    exit();
}
?>