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
    
    $password_default = "Cobaep2026!";
    $hash_seguro = password_hash($password_default, PASSWORD_BCRYPT);
    $id_admin_actual = $_SESSION['id_usuario']; 
    
    $registros_exitosos = 0;
    $registros_duplicados = 0;
    $grupos_creados_auto = 0; 
    
    if (($handle = fopen($fileTmpPath, "r")) !== FALSE) {
        
        $numero_fila = 0; 
        
        // Preparar consultas
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM ALUMNOS WHERE matricula = ?");
        
        $sqlAlumno = "INSERT INTO ALUMNOS 
                (matricula, codigo_qr, contrasena_hash, turno, sexo, nombre, apellido_paterno, apellido_materno, estatus, puntos_conducta, semestre, periodo_ingreso) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 100, ?, ?)";
        $stmtInsertAlumno = $pdo->prepare($sqlAlumno);

        $sqlBuscarGrupo = "SELECT id_grupo FROM GRUPOS WHERE semestre = ? AND UPPER(nombre_grupo) = ? AND turno = ? AND estatus = 'Activo' LIMIT 1";
        $stmtBuscarGrupo = $pdo->prepare($sqlBuscarGrupo);

        $sqlCrearGrupo = "INSERT INTO GRUPOS (semestre, nombre_grupo, turno, id_admin_responsable) VALUES (?, ?, ?, ?)";
        $stmtCrearGrupo = $pdo->prepare($sqlCrearGrupo);

        $sqlGrupoAlumno = "INSERT INTO GRUPO_ALUMNO (id_grupo, id_alumno, fecha_asignacion) VALUES (?, ?, CURDATE())";
        $stmtInsertGA = $pdo->prepare($sqlGrupoAlumno);
        
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $numero_fila++;
            
            if ($numero_fila === 1) {
                continue; 
            }
            
            $matricula       = trim($data[1] ?? ''); 
            $sexo            = trim($data[2] ?? ''); 
            $grupo_excel     = trim($data[3] ?? ''); 
            $nombre_completo = trim($data[4] ?? ''); 
            $estatus_excel   = trim($data[5] ?? ''); 
            
            if (empty($matricula) || empty($nombre_completo)) {
                continue;
            }
            
            // 1. VERIFICAR DUPLICADOS
            $stmtCheck->execute([$matricula]);
            if ($stmtCheck->fetchColumn() > 0) {
                $registros_duplicados++;
                continue; 
            }
            
            // 2. EXTRAER SEMESTRE, LETRA Y TURNO BASADO SOLO EN LA CELDA DE GRUPO
            $semestre_ingreso = 1; 
            $letra_grupo = '';
            $turno = 'Matutino'; // Por defecto
            
            $grupo_limpio = str_replace(['°', 'º', ' ', '-'], '', $grupo_excel);
            
            // Extraer el número (Ej. de "1AV" saca "1")
            if (preg_match('/(\d+)/', $grupo_limpio, $match_num)) {
                $semestre_ingreso = intval($match_num[1]);
            }
            
            // Extraer TODAS las letras (Ej. de "1AV" saca "AV")
            if (preg_match('/([A-Za-z]+)/', $grupo_limpio, $match_letra)) {
                $letras_encontradas = strtoupper(trim($match_letra[1]));
                
                // La letra oficial del grupo es siempre la primera letra encontrada (la "A", "B", etc.)
                $letra_grupo = substr($letras_encontradas, 0, 1);
                
                // Si entre todas las letras encontradas en LA CELDA GRUPO hay una "V", es Intermedio.
                // Ya NO revisamos la matrícula para esto.
                if (strpos($letras_encontradas, 'V') !== false) {
                    $turno = 'Intermedio';
                }
            }

            // 3. SEPARAR NOMBRES
            $nombre_norm = str_replace(['DE LA ', 'DEL ', 'DE ', 'SAN '], ['DE_LA_', 'DEL_', 'DE_', 'SAN_'], $nombre_completo);
            $partes = explode(' ', $nombre_norm);
            $apellido_p = str_replace('_', ' ', $partes[0] ?? '');
            $apellido_m = str_replace('_', ' ', $partes[1] ?? '');
            $nombre_pila = count($partes) > 2 ? str_replace('_', ' ', implode(' ', array_slice($partes, 2))) : '';
            
            // 4. DATOS COMPLEMENTARIOS
            $codigo_qr = "QR" . str_replace('/', '', $matricula);
            $periodo_ingreso = date('Y') . (date('n') >= 7 ? '-B' : '-A');
            
            $estatus = 'Alta';
            if(strpos(strtoupper($estatus_excel), 'BAJA') !== false || strpos(strtoupper($estatus_excel), 'B.T') !== false) {
                 $estatus = 'Baja'; 
            }
            $sexo_limpio = (strtoupper($sexo) === 'M') ? 'M' : ((strtoupper($sexo) === 'F') ? 'F' : NULL);

            try {
                $pdo->beginTransaction();

                // 5. INSERTAR AL ALUMNO
                $stmtInsertAlumno->execute([$matricula, $codigo_qr, $hash_seguro, $turno, $sexo_limpio, $nombre_pila, $apellido_p, $apellido_m, $estatus, $semestre_ingreso, $periodo_ingreso]);
                $id_alumno_nuevo = $pdo->lastInsertId();

                // 6. BUSCAR EL GRUPO (Y CREARLO SI NO EXISTE)
                if (!empty($letra_grupo)) {
                    $stmtBuscarGrupo->execute([$semestre_ingreso, $letra_grupo, $turno]);
                    $grupo_db = $stmtBuscarGrupo->fetch();

                    $id_grupo_actual = null;

                    if ($grupo_db) {
                        $id_grupo_actual = $grupo_db['id_grupo'];
                    } else {
                        // CREACIÓN AUTOMÁTICA DEL GRUPO (Matutino o Intermedio)
                        $stmtCrearGrupo->execute([$semestre_ingreso, $letra_grupo, $turno, $id_admin_actual]);
                        $id_grupo_actual = $pdo->lastInsertId();
                        $grupos_creados_auto++;
                    }

                    // 7. INSCRIBIRLO EN GRUPO_ALUMNO
                    if ($id_grupo_actual) {
                        $stmtInsertGA->execute([$id_grupo_actual, $id_alumno_nuevo]);
                    }
                }

                $pdo->commit();
                $registros_exitosos++;

            } catch (Exception $e) {
                $pdo->rollBack();
            }
        }
        fclose($handle);
    }
    
    header("Location: ../admin/gestion_usuarios.php?tipo=alumnos&msg=import_success&reg={$registros_exitosos}&grp={$grupos_creados_auto}&dup={$registros_duplicados}");
    exit();

} else {
    // Redirección en caso de error
    header("Location: ../admin/gestion_usuarios.php?tipo=alumnos&msg=import_error");
    exit();
}
?>