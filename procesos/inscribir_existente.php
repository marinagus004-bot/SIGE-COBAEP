<?php
session_start();
require_once '../config/database.php';

// Validar sesión de administrativo
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../login.php");
    exit();
}

$nombre_usuario = isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') : 'Administrador';
$id_grupo = isset($_GET['id_grupo']) ? (int)$_GET['id_grupo'] : 0;

// --- PROCESAR EL FORMULARIO DE INSCRIPCIÓN ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inscribir'])) {
    $id_g = (int)$_POST['id_grupo'];
    $alumnos_seleccionados = $_POST['id_alumnos'] ?? [];

    if (!empty($alumnos_seleccionados)) {
        try {
            $pdo->beginTransaction();
            // Preparamos el insert. La tabla GRUPO_ALUMNO tiene (id_grupo, id_alumno, fecha_asignacion)
            $stmtInsert = $pdo->prepare("INSERT INTO GRUPO_ALUMNO (id_grupo, id_alumno, fecha_asignacion) VALUES (?, ?, CURDATE())");
            
            // Comprobar que no exista ya para evitar errores de clave única
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM GRUPO_ALUMNO WHERE id_grupo = ? AND id_alumno = ?");

            foreach ($alumnos_seleccionados as $id_a) {
                $stmtCheck->execute([$id_g, $id_a]);
                if ($stmtCheck->fetchColumn() == 0) {
                    $stmtInsert->execute([$id_g, $id_a]);
                }
            }
            $pdo->commit();
            
            // Redirigir a la vista del grupo (asegúrate de que este archivo exista o cámbialo a gestion_grupos.php)
            header("Location: ver_grupo.php?id=$id_g&msg=inscritos");
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error al inscribir alumnos: " . $e->getMessage();
        }
    } else {
        $error = "Por favor selecciona al menos un alumno.";
    }
}

// --- OBTENER DATOS DEL GRUPO Y ALUMNOS DISPONIBLES ---
try {
    // 1. Obtener detalles del grupo
    $stmtGrupo = $pdo->prepare("SELECT id_grupo, semestre, nombre_grupo, turno FROM GRUPOS WHERE id_grupo = ?");
    $stmtGrupo->execute([$id_grupo]);
    $grupo = $stmtGrupo->fetch(PDO::FETCH_ASSOC);

    if (!$grupo) {
        die("Error: El grupo especificado no existe.");
    }

    // 2. Obtener alumnos del MISMO SEMESTRE que NO estén ya en este grupo
    $sqlAlumnos = "SELECT id_alumno, matricula, nombre, apellido_paterno, apellido_materno 
                   FROM ALUMNOS 
                   WHERE estatus = 'Alta' 
                     AND semestre = ?
                     AND id_alumno NOT IN (
                         SELECT id_alumno FROM GRUPO_ALUMNO WHERE id_grupo = ? AND fecha_baja IS NULL
                     )
                   ORDER BY apellido_paterno ASC, apellido_materno ASC, nombre ASC";
    
    $stmtAlumnos = $pdo->prepare($sqlAlumnos);
    $stmtAlumnos->execute([$grupo['semestre'], $id_grupo]);
    $alumnos_disponibles = $stmtAlumnos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error de base de datos: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscribir Alumnos · COBAEP Plantel 27</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <link rel="stylesheet" href="style_dashboard_admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="style-gestionar-grupos.css?v=<?php echo time(); ?>">

    <style>
        /* Estilos específicos para la selección múltiple */
        .inscripcion-card { background: #fff; border-radius: 16px; padding: 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); border: 1px solid #e5e7eb; max-width: 800px; margin: 0 auto; }
        .grupo-resumen { background: #f0fdf4; border: 1px solid #bbf7d0; padding: 20px; border-radius: 12px; margin-bottom: 24px; display: flex; align-items: center; gap: 15px; }
        .grupo-resumen i { font-size: 2rem; color: #10b981; }
        .grupo-resumen h2 { margin: 0; color: #064e3b; font-size: 1.4rem; }
        .grupo-resumen p { margin: 0; color: #15803d; font-size: 0.9rem; }
        
        .lista-alumnos { max-height: 400px; overflow-y: auto; border: 1px solid #e5e7eb; border-radius: 8px; margin-top: 15px; }
        .alumno-item { display: flex; align-items: center; padding: 12px 15px; border-bottom: 1px solid #e5e7eb; transition: background 0.2s; }
        .alumno-item:last-child { border-bottom: none; }
        .alumno-item:hover { background: #f8fafc; }
        .alumno-item input[type="checkbox"] { margin-right: 15px; width: 18px; height: 18px; accent-color: #10b981; cursor: pointer; }
        .alumno-info strong { display: block; font-size: 0.95rem; color: #111827; }
        .alumno-info span { font-size: 0.8rem; color: #6b7280; }
        
        .btn-guardar { background: #10b981; color: white; padding: 12px 24px; border-radius: 8px; font-weight: 600; font-size: 1rem; margin-top: 20px; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; border: none; width: 100%; justify-content: center; transition: 0.2s; }
        .btn-guardar:hover { background: #059669; }
        .alert-error { background: #fef2f2; color: #991b1b; padding: 15px; border-radius: 8px; border: 1px solid #fecaca; margin-bottom: 20px; }
        .no-results { padding: 30px; text-align: center; color: #6b7280; }
    </style>
</head>
<body>
    <!-- (Puedes incluir aquí tu Aside Sidebar igual que en el otro archivo) -->
    
    <main class="main-content" style="margin-left: 0; width: 100%;">
        <header class="topbar">
            <div>
                <a href="gestion_grupos.php" style="color: #10b981; font-weight: 600; text-decoration: none; margin-bottom: 10px; display: inline-block;">
                    <i class="fas fa-arrow-left"></i> Volver a Grupos
                </a>
                <h1 class="page-title">Inscripción de Alumnos</h1>
            </div>
        </header>

        <section class="section">
            <div class="inscripcion-card">
                <?php if(isset($error)): ?>
                    <div class="alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?></div>
                <?php endif; ?>

                <div class="grupo-resumen">
                    <i class="fas fa-users-rectangle"></i>
                    <div>
                        <h2>Semestre <?php echo htmlspecialchars($grupo['semestre']); ?> "<?php echo htmlspecialchars($grupo['nombre_grupo']); ?>"</h2>
                        <p>Turno <?php echo htmlspecialchars($grupo['turno']); ?></p>
                    </div>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="id_grupo" value="<?php echo $id_grupo; ?>">
                    
                    <h3>Selecciona los alumnos a inscribir:</h3>
                    <p style="color: #6b7280; font-size: 0.85rem; margin-bottom: 10px;">Solo se muestran alumnos de semestre <?php echo $grupo['semestre']; ?> que no están en este grupo.</p>
                    
                    <div class="lista-alumnos">
                        <?php if(count($alumnos_disponibles) > 0): ?>
                            <?php foreach($alumnos_disponibles as $a): ?>
                                <label class="alumno-item">
                                    <input type="checkbox" name="id_alumnos[]" value="<?php echo $a['id_alumno']; ?>">
                                    <div class="alumno-info">
                                        <strong><?php echo htmlspecialchars($a['apellido_paterno'] . ' ' . $a['apellido_materno'] . ' ' . $a['nombre']); ?></strong>
                                        <span>Matrícula: <?php echo htmlspecialchars($a['matricula']); ?></span>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-results">
                                <i class="fas fa-check-circle" style="font-size: 2rem; color: #10b981; margin-bottom: 10px; display: block;"></i>
                                No hay alumnos disponibles para inscribir.<br>Todos los alumnos de este semestre ya están en un grupo.
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if(count($alumnos_disponibles) > 0): ?>
                        <button type="submit" name="inscribir" class="btn-guardar">
                            <i class="fas fa-user-plus"></i> Inscribir Seleccionados
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </section>
    </main>
</body>
</html>