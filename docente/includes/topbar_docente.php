<?php
/**
 * docente/includes/topbar_docente.php
 * Parte derecha del encabezado del docente, igual en todas sus páginas:
 * fecha, campana (lleva a "Avisos y Tareas" del inicio) y perfil con su foto.
 *
 * Uso dentro de <header class="topbar">:  <?php docente_topbar($pdo); ?>
 */
require_once __DIR__ . '/avisos_docente.php';

/** Ruta lista para usar desde /docente/ (o null si no hay foto válida). */
function docente_foto_url(?string $ruta): ?string {
    if (!$ruta) return null;
    $limpia = preg_replace('#^(\.\./)+#', '', $ruta);           // acepta "../uploads/..." y "uploads/..."
    if (strpos($limpia, 'uploads/') !== 0) return null;
    return is_file(__DIR__ . '/../../' . $limpia) ? '../' . $limpia : null;
}

function docente_topbar(PDO $pdo): void {
    $id = (int)($_SESSION['id_usuario'] ?? 0);
    $nombre = htmlspecialchars($_SESSION['nombre'] ?? 'Docente', ENT_QUOTES, 'UTF-8');
    $foto = null;
    $pendientes = 0;
    try {
        $st = $pdo->prepare("SELECT foto_perfil FROM MAESTROS WHERE id_maestro = ?");
        $st->execute([$id]);
        $foto = docente_foto_url($st->fetchColumn() ?: null);
        $pendientes = count(docente_avisos($pdo, $id)['avisos']);
    } catch (PDOException $e) {
        error_log('Topbar docente: ' . $e->getMessage());
    }
    $en_inicio = basename($_SERVER['PHP_SELF']) === 'dashboard_docente.php';
    $titulo_campana = $pendientes ? "Tienes $pendientes avisos y tareas pendientes" : 'Avisos y tareas: todo al día';
    ?>
            <div class="topbar-right">
                <div class="date-pill"><i class="far fa-calendar"></i><span id="fechaTexto"><?php echo date('d/m/Y, h:i a'); ?></span></div>
                <a class="notification-button" href="<?php echo $en_inicio ? '#panelAvisos' : 'dashboard_docente.php#panelAvisos'; ?>"
                   title="<?php echo $titulo_campana; ?>" aria-label="<?php echo $titulo_campana; ?>">
                    <i class="far fa-bell"></i>
                    <?php if ($pendientes): ?><span class="notification-count"><?php echo $pendientes > 9 ? '9+' : $pendientes; ?></span><?php endif; ?>
                </a>
                <div class="profile-dropdown" style="position: relative;">
                    <div class="profile" id="btnPerfil" style="cursor: pointer;" role="button" tabindex="0" aria-label="Menú de perfil">
                        <div class="profile-avatar<?php echo $foto ? ' con-foto' : ''; ?>">
                            <?php if ($foto): ?><img src="<?php echo htmlspecialchars($foto); ?>" alt="Foto de perfil"><?php else: ?><i class="fas fa-chalkboard-user"></i><?php endif; ?>
                        </div>
                        <div class="profile-info">
                            <strong><?php echo $nombre; ?></strong>
                            <span>Docente</span>
                        </div>
                        <i class="fas fa-chevron-down" style="font-size: 12px; color: var(--muted); margin-left: 5px;"></i>
                    </div>
                    <div class="dropdown-menu-perfil" id="menuPerfil">
                        <a href="perfil.php"><i class="fas fa-user-edit"></i> Mi Perfil</a>
                        <a href="perfil.php#seguridad"><i class="fas fa-key"></i> Cambiar contraseña</a>
                        <hr style="margin: 0; border: none; border-top: 1px solid var(--line);">
                        <a href="../logout.php" style="color: #ef4444;"><i class="fas fa-arrow-right-from-bracket"></i> Cerrar Sesión</a>
                    </div>
                </div>
            </div>
    <?php
}
