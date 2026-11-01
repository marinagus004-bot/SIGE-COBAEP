<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo') {
    header("Location: ../login.php");
    exit();
}

try {
    $sqlGrupos = "SELECT id_grupo, semestre, nombre_grupo, turno FROM GRUPOS WHERE estatus = 'Activo' ORDER BY semestre, nombre_grupo ASC";
    $grupos = $pdo->query($sqlGrupos)->fetchAll();

    $sqlMaestros = "SELECT id_maestro, nombre, apellido_paterno, apellido_materno FROM MAESTROS ORDER BY nombre ASC";
    $maestros = $pdo->query($sqlMaestros)->fetchAll();
} catch (PDOException $e) {
    die("Error al cargar datos: " . $e->getMessage());
}

$nombre_usuario = isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') : 'Administrador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#063f2b">
    <title>Asignación Masiva · COBAEP Plantel 27</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <link rel="stylesheet" href="style_dashboard_admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="style_sidebar.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="style-asignacion_masiva.css?v=<?php echo time(); ?>">
</head>
<body>

    <!-- BARRA LATERAL CENTRALIZADA -->
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar" style="margin-bottom: 30px;">
            <div class="topbar-left">
                <div>
                    <span class="eyebrow" style="color: #10b981; font-weight: 700; font-size: 0.85rem; letter-spacing: 0.05em; text-transform: uppercase;">ACADÉMICO · SIGE</span>
                    <h1 class="page-title" style="margin: 5px 0; font-size: 1.8rem; color: #0f172a;">Asignación Rápida de Docentes</h1>
                    <p class="page-desc" style="color: #64748b; margin: 0;">Selecciona un grupo a la izquierda y marca los docentes a la derecha.</p>
                </div>
            </div>
        </header>
        
        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'exito'): ?>
            <div style="padding:15px 20px; border-radius:12px; margin-bottom:20px; background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; font-weight:600;">
                <i class="fas fa-check-circle"></i> Docentes asignados correctamente al grupo.
            </div>
        <?php elseif(isset($_GET['msg']) && $_GET['msg'] == 'vacio'): ?>
            <div style="padding:15px 20px; border-radius:12px; margin-bottom:20px; background:#fef2f2; color:#991b1b; border:1px solid #fecaca; font-weight:600;">
                <i class="fas fa-exclamation-circle"></i> Debes seleccionar un grupo y al menos un docente.
            </div>
        <?php endif; ?>

        <section class="section">
            <form action="Procesos/asignar_docentes.php" method="POST" id="formAsignacion">
                <div class="split-layout">
                    <!-- PANEL 1: GRUPOS -->
                    <div class="panel">
                        <div class="panel-header">
                            <h2><i class="fas fa-users-viewfinder" style="color: #10b981;"></i> 1. Selecciona el Grupo</h2>
                        </div>
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="buscGrupo" placeholder="Buscar por semestre o turno (ej. 1A, Intermedio)...">
                        </div>
                        <div class="list-container" id="listaGrupos">
                            <?php foreach($grupos as $g): ?>
                                <label class="select-card item-grupo">
                                    <input type="radio" name="id_grupo" value="<?php echo $g['id_grupo']; ?>" required>
                                    <div class="card-content">
                                        <div>
                                            <span class="item-title"><?php echo $g['semestre']; ?>° "<?php echo $g['nombre_grupo']; ?>"</span>
                                            <span class="item-subtitle">Turno <?php echo $g['turno']; ?></span>
                                        </div>
                                        <span class="badge <?php echo ($g['turno'] == 'Matutino') ? 'matutino' : 'intermedio'; ?>">
                                            <?php echo strtoupper($g['turno']); ?>
                                        </span>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- PANEL 2: DOCENTES -->
                    <div class="panel">
                        <div class="panel-header">
                            <h2><i class="fas fa-chalkboard-user" style="color: #0ea5e9;"></i> 2. Selecciona Docentes</h2>
                        </div>
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="buscDocente" placeholder="Buscar por nombre o apellido...">
                        </div>
                        <div class="list-container" id="listaDocentes" style="max-height: 480px;">
                            <?php foreach($maestros as $m): ?>
                                <label class="select-card item-docente">
                                    <input type="checkbox" name="maestros_seleccionados[]" value="<?php echo $m['id_maestro']; ?>">
                                    <div class="card-content">
                                        <div style="display: flex; align-items: center; gap: 12px;">
                                            <div class="avatar-docente"><i class="fas fa-user-tie"></i></div>
                                            <span class="item-title">
                                                <?php echo htmlspecialchars($m['nombre'] . ' ' . $m['apellido_paterno'] . ' ' . $m['apellido_materno']); ?>
                                            </span>
                                        </div>
                                        <i class="fas fa-check-circle check-icon"></i>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="sticky-footer">
                            <button type="submit" class="btn-guardar">
                                <i class="fas fa-save"></i> Guardar Asignación
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </section>
    </main>

    <script>
        document.getElementById('buscGrupo').addEventListener('input', function() {
            let term = this.value.toLowerCase().replace(/["'\s]/g, '');
            document.querySelectorAll('.item-grupo').forEach(el => {
                let text = el.textContent.toLowerCase().replace(/["'\s]/g, '');
                el.style.display = text.includes(term) ? 'flex' : 'none';
            });
        });

        document.getElementById('buscDocente').addEventListener('input', function() {
            let term = this.value.toLowerCase().replace(/["'\s]/g, '');
            document.querySelectorAll('.item-docente').forEach(el => {
                let text = el.textContent.toLowerCase().replace(/["'\s]/g, '');
                el.style.display = text.includes(term) ? 'flex' : 'none';
            });
        });
    </script>
</body>
</html>