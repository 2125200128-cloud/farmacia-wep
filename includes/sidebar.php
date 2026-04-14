<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icono"><i class="fa-solid fa-capsules"></i></div>
        <span class="logo-nombre">Medi<b>Click</b></span>
    </div>
    <nav class="sidebar-nav">
        <?php if ($_SESSION['rol'] === 'cajero' || $_SESSION['rol'] === 'encargado'): ?>
            <a href="<?= SITE_URL ?>cajero/nueva_venta.php" class="btn-nueva-venta">
                <i class="fa-solid fa-plus"></i>
                <span>Nueva Venta</span>
            </a>
            <div class="nav-grupo">
                <p class="nav-grupo-titulo">Ventas</p>
                <a href="<?= SITE_URL ?>cajero/ventas.php"
                    class="nav-item <?php echo ($menuActivo ?? '') === 'ventas' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-cash-register"></i>
                    <span>Ventas</span>
                </a>
                <a href="<?= SITE_URL ?>cajero/clientes.php"
                    class="nav-item <?php echo ($menuActivo ?? '') === 'clientes' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-users"></i>
                    <span>Clientes</span>
                </a>
                <a href="<?= SITE_URL ?>cajero/cierre_dia.php"
                    class="nav-item <?php echo ($menuActivo ?? '') === 'cierre' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-clipboard-check"></i>
                    <span>Cierre del día</span>
                </a>
            </div>
        <?php endif; ?>

        <?php if ($_SESSION['rol'] === 'farmaceutico' || $_SESSION['rol'] === 'encargado'): ?>
            <div class="nav-grupo">
                <p class="nav-grupo-titulo">Inventario</p>
                <a href="<?= SITE_URL ?>farmaceutico/productos.php"
                    class="nav-item <?php echo ($menuActivo ?? '') === 'productos' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-boxes-stacked"></i>
                    <span>Catálogo e Inventario</span>
                </a>
                <a href="<?= SITE_URL ?>farmaceutico/entradas.php"
                    class="nav-item <?php echo ($menuActivo ?? '') === 'entradas' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-truck-ramp-box"></i>
                    <span>Compras</span>
                </a>
            </div>
        <?php endif; ?>

        <?php if ($_SESSION['rol'] === 'encargado'): ?>
            <div class="nav-grupo">
                <p class="nav-grupo-titulo">Administración</p>
                <a href="<?= SITE_URL ?>encargado/dashboard.php"
                    class="nav-item <?php echo ($menuActivo ?? '') === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-gauge-high"></i>
                    <span>Dashboard</span>
                </a>
                <a href="<?= SITE_URL ?>encargado/reportes.php"
                    class="nav-item <?php echo ($menuActivo ?? '') === 'reportes' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>Reportes</span>
                </a>
                <a href="<?= SITE_URL ?>encargado/empleados.php"
                    class="nav-item <?php echo ($menuActivo ?? '') === 'empleados' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-user-gear"></i>
                    <span>Empleados</span>
                </a>
                <a href="<?= SITE_URL ?>encargado/promociones.php"
                    class="nav-item <?php echo ($menuActivo ?? '') === 'promociones' ? 'active' : ''; ?>"
                    style="position:relative;">
                    <i class="fa-solid fa-tag"></i>
                    <span>Promociones</span>
                    <?php
                    // Mostrar badge con cantidad de promociones activas hoy
                    try {
                        global $pdo;
                        $hoyNav = date('Y-m-d');
                        $stmtNav = $pdo->prepare("SELECT COUNT(*) FROM promociones WHERE activa=1 AND fecha_inicio<=? AND fecha_fin>=?");
                        $stmtNav->execute([$hoyNav, $hoyNav]);
                        $activasNav = (int) $stmtNav->fetchColumn();
                        if ($activasNav > 0):
                            ?>
                            <span
                                style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:#10b981; color:white; font-size:0.65rem; font-weight:bold; padding:1px 6px; border-radius:999px;"><?= $activasNav ?></span>
                        <?php endif;
                    } catch (Exception $e) {
                    } ?>
                </a>
            </div>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <a href="<?= SITE_URL ?>includes/cerrar_sesion.php" class="nav-item">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Cerrar sesión</span>
        </a>
    </div>
</aside>