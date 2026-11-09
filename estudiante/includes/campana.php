<?php
/**
 * estudiante/includes/campana.php
 * Campana del encabezado del alumno: lleva a "Avisos" y muestra cuántos avisos
 * nuevos hay (de los últimos 3 días).
 */
require_once __DIR__ . '/avisos_data.php';

function estudiante_campana(PDO $pdo): void {
    $nuevos = 0;
    try {
        $desde = date('Y-m-d H:i:s', strtotime('-3 days'));
        $ahora = date('Y-m-d H:i:s');
        foreach (obtener_avisos_estudiante($pdo, (int)($_SESSION['id_usuario'] ?? 0), 50) as $a) {
            if ($a['fecha'] && $a['fecha'] >= $desde && $a['fecha'] <= $ahora) $nuevos++;
        }
    } catch (PDOException $e) {
        error_log('Campana alumno: ' . $e->getMessage());
    }
    $titulo = $nuevos ? "Tienes $nuevos avisos nuevos" : 'Ver avisos';
    $activa = basename($_SERVER['PHP_SELF']) === 'avisos.php';
    static $estilos = false;
    if (!$estilos) { $estilos = true; ?>
    <style>
        .campana-est { position: relative; width: 44px; height: 44px; flex-shrink: 0; display: grid; place-items: center; border-radius: 14px; background: #fff; border: 1px solid #e2e8f0; color: #475569; text-decoration: none; box-shadow: 0 2px 10px rgba(15,23,42,.04); transition: .2s; }
        .campana-est:hover, .campana-est.activa { color: #059669; border-color: #a7f3d0; transform: translateY(-2px); }
        .campana-est:focus-visible { outline: 3px solid #34d399; outline-offset: 2px; }
        .campana-est .campana-num { position: absolute; top: -5px; right: -5px; min-width: 19px; height: 19px; padding: 0 5px; border-radius: 999px; background: #ef4444; color: #fff; font-size: .65rem; font-weight: 800; display: grid; place-items: center; box-shadow: 0 0 0 2px #fff; }
    </style>
    <?php } ?>
                    <a class="campana-est<?php echo $activa ? ' activa' : ''; ?>" href="avisos.php" title="<?php echo $titulo; ?>" aria-label="<?php echo $titulo; ?>">
                        <i class="far fa-bell"></i><?php if ($nuevos): ?><span class="campana-num"><?php echo $nuevos > 9 ? '9+' : $nuevos; ?></span><?php endif; ?>
                    </a>
<?php
}
