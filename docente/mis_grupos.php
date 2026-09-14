<?php
session_start();
require_once '../config/database.php';

// Validación estricta: Solo docentes[cite: 2]
if (!isset($_SESSION['id_usuario']) || $_SESSION['rol'] !== 'docente') {
    header("Location: ../login.php");
    exit();
}

$id_docente = $_SESSION['id_usuario'];
$nombre_usuario = isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8') : 'Docente';

// Obtener el grupo seleccionado (si hay alguno en la URL)
$id_grupo_seleccionado = isset($_GET['id_grupo']) ? (int)$_GET['id_grupo'] : null;

try {
    // 1. Obtener los grupos asignados a este maestro
    $stmt_grupos = $pdo->prepare("
        SELECT DISTINCT g.id_grupo, g.nombre_grupo, g.semestre, g.turno
        FROM GRUPOS g
        INNER JOIN HORARIOS_MAESTRO hm ON g.id_grupo = hm.id_grupo
        WHERE hm.id_maestro = ?
        ORDER BY g.semestre, g.nombre_grupo
    ");
    $stmt_grupos->execute([$id_docente]);
    $mis_grupos = $stmt_grupos->fetchAll(PDO::FETCH_ASSOC);

    // 2. Si hay un grupo seleccionado, obtener a los alumnos de ese grupo[cite: 5]
    $alumnos = [];
    $nombre_grupo_actual = "";
    
    if ($id_grupo_seleccionado) {
        // Verificar que el maestro realmente tiene acceso a este grupo por seguridad
        $acceso_valido = false;
        foreach ($mis_grupos as $g) {
            if ($g['id_grupo'] == $id_grupo_seleccionado) {
                $acceso_valido = true;
                $nombre_grupo_actual = $g['semestre'] . "° " . $g['nombre_grupo'] . " - " . $g['turno'];
                break;
            }
        }

        if ($acceso_valido) {
            $stmt_alumnos = $pdo->prepare("
                SELECT a.matricula, a.nombre, a.apellido_paterno, a.apellido_materno, a.estatus
                FROM ALUMNOS a
                INNER JOIN GRUPO_ALUMNO ga ON a.id_alumno = ga.id_alumno
                WHERE ga.id_grupo = ? AND ga.fecha_baja IS NULL
                ORDER BY a.apellido_paterno, a.apellido_materno, a.nombre
            ");
            $stmt_alumnos->execute([$id_grupo_seleccionado]);
            $alumnos = $stmt_alumnos->fetchAll(PDO::FETCH_ASSOC);
        }
    }

} catch (PDOException $e) {
    error_log("Error en mis_grupos.php: " . $e->getMessage());
    $mis_grupos = [];
    $alumnos = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#063f2b">
    <title>Mis Grupos · SIGE COBAEP 27</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    
    <!-- Usamos el CSS global que ya tienes y agregamos uno específico para esta vista -->
    <link rel="stylesheet" href="style_dashboard_docente.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="style_mis_grupos.css?v=<?php echo time(); ?>">
</head>

<body>

    <!-- ================= BARRA LATERAL ================= -->
    <aside class="sidebar" id="sidebar">
        <button id="collapseToggle" class="collapse-toggle" aria-label="Colapsar menú">
            <i class="fas fa-chevron-left"></i>
        </button>

        <div class="sidebar-inner">
            <div class="brand">
                <img src="../img/LogoCobaep.png" alt="Logo COBAEP" class="brand-logo">
                <div class="brand-copy">
                    <strong>SIGE<span>-Cobaep</span></strong>
                    <small>Plantel 27</small>
                </div>
            </div>

            <nav class="navigation" aria-label="Navegación principal">
                <span class="nav-heading">Menú principal</span>
                <a href="dashboard_docente.php" class="nav-item">
                    <i class="fas fa-house"></i>
                    <span>Inicio</span>
                </a>

                <span class="nav-heading">Académico</span>
                <!-- MARCADO COMO ACTIVO -->
                <a href="mis_grupos.php" class="nav-item active">
                    <i class="fas fa-users-rectangle"></i>
                    <span>Mis Grupos</span>
                </a>
                    <a href="horario.php" class="nav-item">
                    <i class="far fa-calendar-alt"></i>
                    <span>Mi Horario</span>
                
                </a>

                <span class="nav-heading">Evaluación</span>
                <a href="regularizaciones.php" class="nav-item">
                    <i class="fas fa-book-open-reader"></i>
                    <span>Regularizaciones</span>
                </a>
            </nav>

            <div class="sidebar-bottom">
                <div class="institution">
                    <div class="institution-icon"><i class="fas fa-building-columns"></i></div>
                    <div>
                        <strong>COBAEP Plantel 27</strong>
                        <span>Portal Docente</span>
                    </div>
                </div>
                <a href="../logout.php" class="logout">
                    <i class="fas fa-arrow-right-from-bracket"></i>
                    <span>Cerrar Sesión</span>
                </a>
            </div>
        </div>
    </aside>

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ================= CONTENIDO PRINCIPAL ================= -->
    <main class="main-content">

        <!-- TOPBAR -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-menu" id="mobileMenu" type="button" aria-label="Abrir menú">
                    <i class="fas fa-bars"></i>
                </button>
                <div>
                    <span class="eyebrow">ACADÉMICO · DOCENTE</span>
                    <h1>Mis Grupos Asignados</h1>
                    <p>Consulta las listas y el estatus de tus alumnos. (Modo Lectura)</p>
                </div>
            </div>
            <div class="topbar-right">
                <div class="profile">
                    <div class="profile-avatar"><i class="fas fa-chalkboard-user"></i></div>
                    <div class="profile-info">
                        <strong><?php echo $nombre_usuario; ?></strong>
                        <span>Docente</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- SELECTOR DE GRUPOS (TARJETAS) -->
        <section class="grupos-container">
            <?php if (empty($mis_grupos)): ?>
                <div class="empty-state-box">
                    <div class="icon-circle"><i class="fas fa-folder-open"></i></div>
                    <h3>Aún no tienes grupos asignados</h3>
                    <p>El área administrativa debe asignarte horarios y grupos para este semestre.</p>
                </div>
            <?php else: ?>
                <div class="cards-grid">
                    <?php foreach ($mis_grupos as $grupo): ?>
                        <a href="mis_grupos.php?id_grupo=<?php echo $grupo['id_grupo']; ?>" 
                           class="grupo-card-modern <?php echo ($id_grupo_seleccionado == $grupo['id_grupo']) ? 'selected' : ''; ?>">
                            
                            <!-- Acento de color superior -->
                            <div class="card-accent"></div>
                            
                            <div class="card-content-g">
                                <div class="card-top-info">
                                    <span class="badge-semestre">
                                        <i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($grupo['semestre']); ?>° Semestre
                                    </span>
                                    <span class="badge-turno">
                                        <i class="far fa-clock"></i> <?php echo htmlspecialchars($grupo['turno']); ?>
                                    </span>
                                </div>
                                
                                <h3 class="grupo-title">Grupo "<?php echo htmlspecialchars($grupo['nombre_grupo']); ?>"</h3>
                                
                                <div class="card-footer-g">
                                    <span>Ver lista de alumnos</span>
                                    <div class="btn-circle-arrow">
                                        <i class="fas fa-arrow-right"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- TABLA DE ALUMNOS (Solo si hay un grupo seleccionado) -->
        <?php if ($id_grupo_seleccionado && !empty($nombre_grupo_actual)): ?>
        <section class="alumnos-section">
            <div class="table-card">
                <div class="table-header">
                    <div class="header-titles">
                        <h2>Lista de Alumnos</h2>
                        <span class="badge-grupo"><?php echo $nombre_grupo_actual; ?></span>
                    </div>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="buscadorAlumnos" placeholder="Buscar por nombre o matrícula...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="modern-table" id="tablaAlumnos">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Matrícula</th>
                                <th>Nombre Completo</th>
                                <th>Estatus</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($alumnos)): ?>
                                <tr>
                                    <td colspan="5" class="text-center empty-table">No hay alumnos registrados en este grupo.</td>
                                </tr>
                            <?php else: ?>
                                <?php $contador = 1; foreach ($alumnos as $alumno): ?>
                                <tr>
                                    <td class="text-muted"><?php echo $contador++; ?></td>
                                    <td class="font-medium"><?php echo htmlspecialchars($alumno['matricula']); ?></td>
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-avatar alumno-avatar"><i class="fas fa-user"></i></div>
                                            <div class="user-info">
                                                <strong><?php echo htmlspecialchars($alumno['apellido_paterno'] . ' ' . $alumno['apellido_materno']); ?></strong>
                                                <span><?php echo htmlspecialchars($alumno['nombre']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge-status <?php echo ($alumno['estatus'] === 'Alta') ? 'active' : 'inactive'; ?>">
                                            <?php echo htmlspecialchars($alumno['estatus']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <!-- Botón solo lectura[cite: 2] -->
                                        <button class="btn-action view" onclick="alert('Funcionalidad de Kardex en desarrollo');">
                                            <i class="far fa-eye"></i> Ver Calificaciones
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        <?php endif; ?>

    </main>

    <script src="dashboard_maestro.js"></script>
    <script>
        // Buscador simple para la tabla
        document.getElementById('buscadorAlumnos')?.addEventListener('keyup', function(e) {
            let texto = e.target.value.toLowerCase();
            let filas = document.querySelectorAll('#tablaAlumnos tbody tr');
            
            filas.forEach(fila => {
                if(fila.querySelector('.empty-table')) return;
                let contenido = fila.textContent.toLowerCase();
                fila.style.display = contenido.includes(texto) ? '' : 'none';
            });
        });
    </script>
</body>
</html>