<?php
session_start();
require_once '../config/database.php'; 

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'prefectos') {
    header("Location: ../login.php");
    exit();
}

$id_reporte = $_GET['id'] ?? '';
$return_to = $_GET['return_to'] ?? '';
$mat = $_GET['mat'] ?? '';

if (empty($id_reporte)) {
    header("Location: creacion_reportes.php");
    exit();
}

// Obtener datos del reporte
$stmt = $pdo->prepare("
    SELECT r.*, a.matricula, a.nombre, a.apellido_paterno 
    FROM reportes r
    INNER JOIN alumnos a ON r.id_alumno = a.id_alumno
    WHERE r.id_reporte = ?
");
$stmt->execute([$id_reporte]);
$reporte = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reporte) {
    die("Reporte no encontrado.");
}

// Obtener catálogo de faltas
$stmt_faltas = $pdo->query("SELECT id_tipo_falta, numero_reglamento, descripcion_falta FROM tipos_falta ORDER BY numero_reglamento ASC");
$tipos_falta = $stmt_faltas->fetchAll(PDO::FETCH_ASSOC);

$url_regreso = ($return_to === 'expediente') ? "expediente_alumnos.php?matricula=" . htmlspecialchars($mat) : "creacion_reportes.php";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Reporte · COBAEP 27</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f1f5f9; display: flex; justify-content: center; padding: 40px 20px; }
        .edit-container { background: #fff; width: 100%; max-width: 600px; padding: 30px; border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; }
        .edit-header { display: flex; align-items: center; gap: 15px; border-bottom: 1px solid #e2e8f0; padding-bottom: 20px; margin-bottom: 20px; }
        .edit-header h2 { margin: 0; color: #0f172a; font-size: 1.4rem; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 700; color: #334155; margin-bottom: 8px; font-size: 0.9rem; }
        .form-control { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: 'Inter', sans-serif; font-size: 0.95rem; }
        .form-control:focus { outline: none; border-color: #059669; box-shadow: 0 0 0 3px #a7f3d0; }
        .btn-container { display: flex; gap: 15px; margin-top: 30px; justify-content: flex-end; }
        .btn { padding: 12px 24px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; font-size: 0.95rem; transition: 0.2s; }
        .btn-cancel { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
        .btn-cancel:hover { background: #e2e8f0; }
        .btn-save { background: #059669; color: white; }
        .btn-save:hover { background: #047857; }
        .student-badge { background: #f8fafc; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0; font-size: 0.95rem; color: #0f172a; }
    </style>
</head>
<body>
    <div class="edit-container">
        <div class="edit-header">
            <i class="fas fa-edit" style="font-size: 1.8rem; color: #059669;"></i>
            <h2>Modificar Reporte</h2>
        </div>
        
        <div class="form-group">
            <label>Alumno Sancionado:</label>
            <div class="student-badge">
                <strong><?php echo htmlspecialchars($reporte['matricula']); ?></strong> - 
                <?php echo htmlspecialchars($reporte['apellido_paterno'] . ' ' . $reporte['nombre']); ?>
            </div>
        </div>

        <form action="procesos/actualizar_reporte.php" method="POST">
            <input type="hidden" name="id_reporte" value="<?php echo $id_reporte; ?>">
            <input type="hidden" name="id_alumno" value="<?php echo $reporte['id_alumno']; ?>">
            <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($url_regreso); ?>">

            <div class="form-group">
                <label>Falta Cometida:</label>
                <select name="id_tipo_falta" class="form-control" required>
                    <?php foreach ($tipos_falta as $falta): ?>
                        <option value="<?php echo $falta['id_tipo_falta']; ?>" <?php echo ($falta['id_tipo_falta'] == $reporte['id_tipo_falta']) ? 'selected' : ''; ?>>
                            Regla <?php echo $falta['numero_reglamento']; ?>: <?php echo htmlspecialchars(mb_strimwidth($falta['descripcion_falta'], 0, 80, '...')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Observaciones / Detalles:</label>
                <textarea name="observaciones" class="form-control" style="min-height: 120px; resize: vertical;"><?php echo htmlspecialchars($reporte['observaciones'] ?? ''); ?></textarea>
            </div>

            <div class="btn-container">
                <a href="<?php echo htmlspecialchars($url_regreso); ?>" class="btn btn-cancel">Cancelar</a>
                <button type="submit" class="btn btn-save"><i class="fas fa-save"></i> Guardar Cambios</button>
            </div>
        </form>
    </div>
</body>
</html>