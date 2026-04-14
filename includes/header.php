<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>MediClick - <?php echo $paginaTitulo ?? 'Sistema'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="<?= SITE_URL ?>assets/css/layout.css?v=<?php echo time(); ?>" />
    <link rel="stylesheet" href="<?= SITE_URL ?>assets/css/global.css?v=<?php echo time(); ?>" />
</head>
<body>
<?php
$numNotificaciones = 0;
$listaNotificaciones = [];
if (isset($pdo)) {
    $stmtStock = $pdo->query("SELECT nombre FROM productos WHERE estado != 'inactivo' AND stock_actual <= stock_minimo AND stock_actual >= 0 LIMIT 10");
    $notifStockBajo = $stmtStock->fetchAll();
    foreach ($notifStockBajo as $item) {
        $listaNotificaciones[] = [
            'icono' => 'fa-solid fa-triangle-exclamation text-warning',
            'texto' => htmlspecialchars($item['nombre']) . ' tiene stock bajo.',
            'link' => SITE_URL . 'farmaceutico/productos.php?estado=stock-bajo'
        ];
    }
    $stmtVenc = $pdo->query("SELECT nombre, fecha_vencimiento FROM productos WHERE estado != 'inactivo' AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND stock_actual > 0 LIMIT 10");
    $vencidos = $stmtVenc->fetchAll();
    foreach ($vencidos as $item) {
        $listaNotificaciones[] = [
            'icono' => 'fa-solid fa-clock text-danger',
            'texto' => htmlspecialchars($item['nombre']) . ' caduca el ' . date('d/m', strtotime($item['fecha_vencimiento'])),
            'link' => SITE_URL . 'encargado/dashboard.php'
        ];
    }
    $numNotificaciones = count($listaNotificaciones);
}
?>
<div class="contenido-wrapper" id="contenido-wrapper">
    <header class="navbar-top">
        <button class="btn-toggle-sidebar" id="btn-toggle" aria-label="Menú">
            <i class="fa-solid fa-bars"></i>
        </button>
        <h1 class="pagina-titulo"><?php echo $paginaTitulo ?? 'Sistema'; ?></h1>
        <div class="navbar-derecha">

            <div style="position:relative;">
                <button class="btn-notificacion" aria-label="Notificaciones" id="btn-campana-notif" onclick="document.getElementById('notif-dropdown').classList.toggle('d-none');">
                    <i class="fa-regular fa-bell"></i>
                    <?php if($numNotificaciones > 0): ?>
                        <span class="badge-notif"><?= $numNotificaciones ?></span>
                    <?php endif; ?>
                </button>
                <div id="notif-dropdown" class="d-none" style="position:absolute; right:0; top:45px; background:white; width:300px; border-radius:8px; box-shadow:0 4px 15px rgba(0,0,0,0.1); border:1px solid #e2e8f0; z-index:1000; overflow:hidden;">
                    <div style="padding:12px 15px; border-bottom:1px solid #e2e8f0; background:#f8fafc;">
                        <h6 style="margin:0; font-size:0.95rem; font-weight:600; color:#334155;">Notificaciones</h6>
                    </div>
                    <div style="max-height:300px; overflow-y:auto;">
                        <?php if($numNotificaciones === 0): ?>
                            <div style="padding:20px; text-align:center; color:#94a3b8; font-size:0.9rem;">No tienes nuevas notificaciones</div>
                        <?php else: ?>
                            <?php foreach($listaNotificaciones as $notif): ?>
                                <a href="<?= $notif['link'] ?>" style="display:flex; align-items:start; gap:12px; padding:12px 15px; border-bottom:1px solid #f1f5f9; text-decoration:none; transition:0.2s;">
                                    <i class="<?= $notif['icono'] ?>" style="margin-top:3px;"></i>
                                    <span style="font-size:0.85rem; color:#475569; line-height:1.4;"><?= $notif['texto'] ?></span>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="usuario-info">
                <div class="avatar"><span><?php echo strtoupper(substr($_SESSION['usuario'] ?? 'U', 0, 2)); ?></span></div>
                <div class="usuario-datos">
                    <p class="usuario-nombre"><?php echo $_SESSION['usuario'] ?? 'Usuario'; ?></p>
                    <p class="usuario-rol"><?php echo $_SESSION['rol'] ?? 'Rol'; ?></p>
                </div>
                <i class="fa-solid fa-chevron-down usuario-flecha"></i>
            </div>
        </div>
    </header>
<script>
    document.addEventListener('click', function(e) {
        const dd = document.getElementById('notif-dropdown');
        const btn = document.getElementById('btn-campana-notif');
        if (dd && btn && !btn.contains(e.target) && !dd.contains(e.target)) {
            dd.classList.add('d-none');
        }
    });
</script>
