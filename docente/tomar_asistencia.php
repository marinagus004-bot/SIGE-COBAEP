<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../login.php");
    exit();
}

$id_docente = $_SESSION['id_usuario'];
$id_clase = isset($_GET['id_clase']) ? (int)$_GET['id_clase'] : 0;
date_default_timezone_set('America/Mexico_City');
$fecha_hoy = date('Y-m-d');

if ($id_clase === 0) {
    header("Location: mis_grupos.php?error=Clase no especificada.");
    exit();
}

try {
    // Info de la clase
    $stmt = $pdo->prepare("
        SELECT c.*, m.nombre_materia, g.nombre_grupo, g.semestre 
        FROM clases_regulares c 
        INNER JOIN materias m ON c.id_materia = m.id_materia 
        LEFT JOIN grupos g ON c.id_grupo = g.id_grupo 
        WHERE c.id_clase = ? AND c.id_maestro = ?
    ");
    $stmt->execute([$id_clase, $id_docente]);
    $clase = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$clase) { header("Location: mis_grupos.php?error=Acceso denegado."); exit(); }

    // Traer alumnos
    $stmtAlumnos = $pdo->prepare("
        SELECT a.id_alumno, a.matricula, a.nombre, a.apellido_paterno 
        FROM clase_regular_alumno cra 
        INNER JOIN alumnos a ON cra.id_alumno = a.id_alumno 
        WHERE cra.id_clase = ? 
        ORDER BY a.apellido_paterno ASC
    ");
    $stmtAlumnos->execute([$id_clase]);
    $alumnos = $stmtAlumnos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error de conexión.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pase de Lista · Portal Docente</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="style_dashboard_docente.css">
    <style>
        .header-asistencia { background: white; padding: 1.5rem; border-radius: 16px; border: 1px solid var(--line); margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: var(--shadow-sm); }
        .fecha-picker { padding: 10px 15px; border-radius: 8px; border: 1px solid #cbd5e1; font-family: 'Inter'; font-size: 1rem; font-weight: 600; color: var(--ink); outline: none; background: #f8fafc;}
        
        .lista-container { background: white; border-radius: 16px; border: 1px solid var(--line); overflow: hidden; box-shadow: var(--shadow-sm); }
        .alum-row { display: flex; align-items: center; justify-content: space-between; padding: 15px 20px; border-bottom: 1px solid var(--line); transition: 0.2s; }
        .alum-row:last-child { border-bottom: none; }
        .alum-row:hover { background: #f8fafc; }
        
        .alum-info strong { display: block; font-size: 1rem; color: var(--ink); margin-bottom: 4px; }
        .alum-info span { font-size: 0.8rem; color: var(--muted); }

        /* Controles tipo Radio Button Modernos */
        .radio-group { display: flex; gap: 8px; }
        .radio-label { display: inline-flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; font-weight: 800; font-size: 1rem; cursor: pointer; border: 2px solid #e2e8f0; color: #94a3b8; transition: 0.2s; background: white;}
        .radio-input { display: none; }
        
        /* Presente */
        .radio-input[value="Presente"]:checked + .radio-label { border-color: #10b981; background: #10b981; color: white; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);}
        /* Retardo */
        .radio-input[value="Retardo"]:checked + .radio-label { border-color: #f59e0b; background: #f59e0b; color: white; box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3);}
        /* Falta */
        .radio-input[value="Falta"]:checked + .radio-label { border-color: #ef4444; background: #ef4444; color: white; box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3);}

        .btn-submit { background: var(--green); color: white; border: none; padding: 16px 24px; border-radius: 12px; font-weight: 800; font-size: 1.1rem; width: 100%; cursor: pointer; display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 20px; transition: 0.2s; }
        .btn-submit:hover { background: var(--green-800); box-shadow: 0 4px 15px rgba(22,160,93,0.3); }

        @media (max-width: 768px) {
            .header-asistencia { flex-direction: column; align-items: flex-start; gap: 15px; }
            .alum-row { flex-direction: column; align-items: flex-start; gap: 15px; }
            .radio-group { width: 100%; justify-content: space-between; }
            .radio-label { width: 100%; border-radius: 8px; height: 45px; }
        }
    </style>
</head>
<body style="background: #f1f5f9; padding: 2rem;">
    
    <div style="max-width: 800px; margin: 0 auto;">
        
        <a href="gestionar_aula.php?id_grupo=<?php echo $clase['id_grupo']; ?>" style="display:inline-flex; align-items:center; gap:8px; color:var(--muted); text-decoration:none; font-weight:700; margin-bottom: 20px;">
            <i class="fas fa-arrow-left"></i> Volver a Mis Materias
        </a>

        <form action="procesos/guardar_asistencia.php" method="POST">
            <input type="hidden" name="id_clase" value="<?php echo $id_clase; ?>">

            <div class="header-asistencia">
                <div>
                    <h2 style="margin:0 0 5px 0; color:var(--ink); font-weight:800;"><i class="fas fa-list-check" style="color:var(--green);"></i> Pase de Lista</h2>
                    <p style="margin:0; color:var(--muted); font-size:0.9rem;">
                        <?php echo htmlspecialchars($clase['nombre_materia']); ?> - Grupo <?php echo htmlspecialchars($clase['semestre']) . '° "' . htmlspecialchars($clase['nombre_grupo']) . '"'; ?>
                    </p>
                </div>
                <div>
                    <label style="display:block; font-size:0.75rem; font-weight:700; color:var(--muted); margin-bottom:5px;">Fecha del Pase:</label>
                    <input type="date" name="fecha_asistencia" class="fecha-picker" value="<?php echo $fecha_hoy; ?>" required>
                </div>
            </div>

            <div class="lista-container">
                <div style="background: #f8fafc; padding: 12px 20px; border-bottom: 1px solid var(--line); display:flex; justify-content:space-between; font-size:0.85rem; font-weight:700; color:var(--muted);">
                    <span>Lista de Alumnos</span>
                    <span style="display:flex; gap:20px;">
                        <span><i class="fas fa-check" style="color:#10b981;"></i> P</span>
                        <span><i class="fas fa-clock" style="color:#f59e0b;"></i> R</span>
                        <span><i class="fas fa-times" style="color:#ef4444;"></i> F</span>
                    </span>
                </div>

                <?php foreach($alumnos as $alum): ?>
                    <div class="alum-row">
                        <div class="alum-info">
                            <strong><?php echo htmlspecialchars($alum['apellido_paterno'] . ' ' . $alum['nombre']); ?></strong>
                            <span>Matrícula: <?php echo htmlspecialchars($alum['matricula']); ?></span>
                        </div>
                        
                        <div class="radio-group">
                            <label>
                                <input type="radio" name="asistencia[<?php echo $alum['id_alumno']; ?>]" value="Presente" class="radio-input" checked>
                                <span class="radio-label" title="Presente">P</span>
                            </label>
                            <label>
                                <input type="radio" name="asistencia[<?php echo $alum['id_alumno']; ?>]" value="Retardo" class="radio-input">
                                <span class="radio-label" title="Retardo">R</span>
                            </label>
                            <label>
                                <input type="radio" name="asistencia[<?php echo $alum['id_alumno']; ?>]" value="Falta" class="radio-input">
                                <span class="radio-label" title="Falta">F</span>
                            </label>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="btn-submit">
                <i class="fas fa-save"></i> Guardar Asistencia
            </button>
        </form>

    </div>

</body>
</html>