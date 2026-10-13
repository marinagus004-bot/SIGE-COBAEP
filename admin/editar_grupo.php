<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'administrativo' || !isset($_GET['id'])) {
    header("Location: gestion_grupos.php");
    exit();
}

$id_grupo = intval($_GET['id']);

try {
    $stmt = $pdo->prepare("SELECT * FROM GRUPOS WHERE id_grupo = ?");
    $stmt->execute([$id_grupo]);
    $grupo = $stmt->fetch();

    if (!$grupo) {
        header("Location: gestion_grupos.php?error=Grupo no encontrado");
        exit();
    }
} catch (PDOException $e) {
    die("Error al cargar el grupo: " . $e->getMessage());
}

$nombre_usuario = isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') : 'Administrador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#063f2b">
    <title>Editar Grupo · SIGE-Cobaep</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="style_dashboard_admin.css">
    <link rel="stylesheet" href="style-agregar-grupos.css">
</head>
<body>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-inner">
        <div class="brand">
            <img src="../img/LogoCobaep.png" alt="Logo" class="brand-logo">
            <div class="brand-copy"><strong>SIGE<span>-Cobaep</span></strong><small>Plantel 27</small></div>
        </div>
        <nav class="navigation">
            <span class="nav-heading">Menú principal</span>
            <a href="dashboard_admin.php" class="nav-item"><i class="fas fa-house"></i><span>Panel de Control</span></a>
            <span class="nav-heading">Académico</span>
            <a href="gestion_grupos.php" class="nav-item active"><i class="fas fa-users-rectangle"></i><span>Gestión de Grupos</span></a>
            <a href="gestion_horarios.php" class="nav-item"><i class="fas fa-calendar-days"></i><span>Gestión de Horarios</span></a>
            <span class="nav-heading">Personal</span>
            <a href="gestion_usuarios.php" class="nav-item"><i class="fas fa-users"></i><span>Directorio de Personal</span></a>
        </nav>
        <div class="sidebar-bottom">
            <a href="../logout.php" class="logout"><i class="fas fa-arrow-right-from-bracket"></i><span>Cerrar Sesión</span></a>
        </div>
    </div>
</aside>

<main class="main-content group-page">
    <header class="group-topbar">
        <div class="group-topbar-left">
            <div class="breadcrumb">
                <a href="gestion_grupos.php" style="text-decoration:none; color:inherit;">Gestión de Grupos</a>
                <i class="fas fa-chevron-right"></i>
                <strong>Editar Grupo</strong>
            </div>
        </div>
        <div class="group-topbar-right">
            <div class="profile pill-style">
                <div class="profile-avatar"><i class="fas fa-user"></i></div>
                <div class="profile-info">
                    <strong><?php echo $nombre_usuario; ?></strong><span>Administrador</span>
                </div>
            </div>
        </div>
    </header>

    <div class="group-content">
        <section class="group-heading">
            <div class="heading-copy">
                <span class="heading-tag" style="background:#fff6e6; color:#d97706;">EDICIÓN</span>
                <h1>Edición de Grupo</h1>
                <p>Modifica el semestre, la letra o el turno de este grupo.</p>
            </div>
        </section>

        <section class="setup-card">
            <div class="setup-header">
                <div class="setup-icon-circle" style="background: linear-gradient(145deg, #03721f, #058103); color: #ffff;">
                    <i class="fas fa-pen"></i>
                </div>
                <div class="setup-header-text">
                    <h2>Información del Grupo</h2>
                    <p>Actualiza los datos correspondientes.</p>
                </div>
            </div>

            <form action="Procesos/actualizar_grupo.php" method="POST" class="setup-body" id="groupForm">
                <input type="hidden" name="id_grupo" value="<?php echo $grupo['id_grupo']; ?>">

                <div class="setup-fields">
                    <div class="field-row">
                        <div class="field-icon"><i class="fas fa-layer-group"></i></div>
                        <div class="field-control">
                            <label for="semestre">Semestre asignado</label>
                            <div class="select-wrapper">
                                <select name="semestre" id="semestre" class="clean-input" required>
                                    <?php for($i=1; $i<=6; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php if($grupo['semestre'] == $i) echo 'selected'; ?>><?php echo $i; ?>er Semestre</option>
                                    <?php endfor; ?>
                                </select>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field-icon text-icon">Aa</div>
                        <div class="field-control">
                            <label for="nombre_grupo">Letra del Grupo</label>
                            <input type="text" name="nombre_grupo" id="nombre_grupo" class="clean-input" value="<?php echo htmlspecialchars($grupo['nombre_grupo']); ?>" required maxlength="1" autocomplete="off">
                        </div>
                    </div>

                    <div class="field-row">
                        <div class="field-icon"><i class="far fa-clock"></i></div>
                        <div class="field-control">
                            <label for="turno">Turno</label>
                            <div class="select-wrapper">
                                <select name="turno" id="turno" class="clean-input" required>
                                    <option value="Matutino" <?php if($grupo['turno'] == 'Matutino') echo 'selected'; ?>>Matutino</option>
                                    <option value="Intermedio" <?php if($grupo['turno'] == 'Intermedio') echo 'selected'; ?>>Intermedio</option>
                                    <option value="Vespertino" <?php if($grupo['turno'] == 'Vespertino') echo 'selected'; ?>>Vespertino</option>
                                </select>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                    </div>

                    <div class="setup-actions">
                        <a href="gestion_grupos.php" class="btn-cancel-text">Cancelar</a>
                        <button type="submit" class="btn-primary-pill" style="background: linear-gradient(135deg, #1e9504, #1e9504); box-shadow: 0 8px 20px rgba(217, 119, 6, 0.2);">
                            <span>Guardar Cambios</span> <i class="fas fa-save"></i>
                        </button>
                    </div>
                </div>

                <aside class="setup-preview">
                    <div class="preview-box">
                        <div class="preview-title">
                            <div class="preview-title-icon" style="background:#1e9504; color:#ffffff;"><i class="fas fa-eye"></i></div>
                            <span>Vista previa actual</span>
                        </div>
                        <div class="preview-illustration">
                            <div class="preview-glow" style="background: radial-gradient(circle, rgba(21, 138, 3, 0.15), transparent);"></div>
                            <div class="student student-one" style="background: #1e9504; color: #ffffff;"><i class="fas fa-user"></i></div>
                        </div>
                        <div class="preview-content">
                            <h3 id="previewTitle"><?php echo $grupo['semestre'] . ' · Grupo ' . $grupo['nombre_grupo']; ?></h3>
                            <p id="previewDescription">Turno <?php echo $grupo['turno']; ?></p>
                        </div>
                    </div>
                </aside>
            </form>
        </section>
    </div>
</main>
<script>
    // Vista previa interactiva
    const sem = document.getElementById('semestre');
    const grp = document.getElementById('nombre_grupo');
    const trn = document.getElementById('turno');
    const pTitle = document.getElementById('previewTitle');
    const pDesc = document.getElementById('previewDescription');

    function updatePreview() {
        pTitle.textContent = sem.value + ' · Grupo ' + grp.value.toUpperCase();
        pDesc.textContent = 'Turno ' + trn.value;
    }
    
    grp.addEventListener('input', function() {
        this.value = this.value.replace(/[^a-zA-Z]/g, '').toUpperCase().substring(0, 1);
        updatePreview();
    });
    sem.addEventListener('change', updatePreview);
    trn.addEventListener('change', updatePreview);
</script>
</body>
</html>