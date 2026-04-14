<?php
require_once '../includes/validar_sesion.php';
require_once '../includes/config.php';
$paginaTitulo = 'Dashboard';
$menuActivo = 'dashboard';

// Fetch stats PHP
$hoy = date('Y-m-d');
$inicioSemana = date('Y-m-d', strtotime('monday this week'));
$inicioMes = date('Y-m-01');

$ventasHoy = $pdo->query("SELECT SUM(total) FROM ventas WHERE DATE(fecha_venta) = '$hoy'")->fetchColumn() ?: 0;
$ventasSemana = $pdo->query("SELECT SUM(total) FROM ventas WHERE DATE(fecha_venta) >= '$inicioSemana'")->fetchColumn() ?: 0;
$ventasMes = $pdo->query("SELECT SUM(total) FROM ventas WHERE DATE(fecha_venta) >= '$inicioMes'")->fetchColumn() ?: 0;
$stockBajo = $pdo->query("SELECT COUNT(*) FROM productos WHERE stock_actual <= stock_minimo")->fetchColumn() ?: 0;

// Fetch últimas ventas
$ultimasVentas = $pdo->query("
    SELECT v.*, c.nombre as cliente_nombre 
    FROM ventas v 
    LEFT JOIN clientes c ON v.cliente_id = c.id 
    ORDER BY v.fecha_venta DESC LIMIT 5
")->fetchAll();

// Fetch stock bajo
$productosStockBajo = $pdo->query("
    SELECT codigo, nombre, stock_actual, stock_minimo, imagen_path 
    FROM productos 
    WHERE stock_actual <= stock_minimo
    ORDER BY stock_actual ASC LIMIT 5
")->fetchAll();

$en30dias = date('Y-m-d', strtotime('+30 days'));
$stmtVenc = $pdo->prepare("
    SELECT codigo, nombre, fecha_vencimiento, stock_actual 
    FROM productos 
    WHERE estado = 'activo' 
      AND fecha_vencimiento IS NOT NULL 
      AND fecha_vencimiento BETWEEN :hoy AND :limite
    ORDER BY fecha_vencimiento ASC
    LIMIT 10
");
$stmtVenc->execute([':hoy' => $hoy, ':limite' => $en30dias]);
$productosVencen = $stmtVenc->fetchAll();

include '../includes/sidebar.php';
include '../includes/header.php';
?>

<main class="contenido-main">

<div class="saludo">
        <div>
          <h2 class="saludo-titulo">Buen día</h2>
          <p class="saludo-fecha"><?= date('l, d F Y') ?></p>
        </div>
        <a href="reportes.php" class="btn-ver-reportes">
          <i class="fa-solid fa-chart-line"></i>
          Ver reportes completos
        </a>
      </div>

<?php if (!empty($productosVencen)): ?>
<div style="background: #fffedd; border-left: 4px solid #f97316; color: #9a3412; padding: 16px; border-radius: 8px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
    <h3 style="margin-top: 0; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; font-size: 1.1rem;">
        <i class="fa-solid fa-triangle-exclamation"></i> 
        Alerta de Vencimiento Próximo (30 días)
    </h3>
    <ul style="margin-bottom: 0; padding-left: 20px;">
        <?php foreach ($productosVencen as $pv): ?>
        <li style="margin-bottom: 4px;">
            <strong><?= htmlspecialchars($pv['nombre']) ?></strong> 
            (Lote/Cód: <?= htmlspecialchars($pv['codigo']) ?>) - 
            Vence el: <strong><?= date('d/m/Y', strtotime($pv['fecha_vencimiento'])) ?></strong> 
            <span style="font-size: 0.9em; opacity: 0.8;">[Stock: <?= $pv['stock_actual'] ?>]</span>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
<div class="tarjetas-resumen">

        <div class="tarjeta-stat">
          <div class="stat-icono turquesa">
            <i class="fa-solid fa-coins"></i>
          </div>
          <div class="stat-info">
            <p class="stat-etiqueta">Ventas hoy</p>
            <p class="stat-valor">$<?= number_format($ventasHoy, 2) ?></p>
          </div>
          <span class="stat-badge positivo">Hoy</span>
        </div>

        <div class="tarjeta-stat">
          <div class="stat-icono azul">
            <i class="fa-solid fa-calendar-week"></i>
          </div>
          <div class="stat-info">
            <p class="stat-etiqueta">Ventas semana</p>
            <p class="stat-valor">$<?= number_format($ventasSemana, 2) ?></p>
          </div>
          <span class="stat-badge neutro">Semana</span>
        </div>

        <div class="tarjeta-stat">
          <div class="stat-icono verde">
            <i class="fa-solid fa-calendar-days"></i>
          </div>
          <div class="stat-info">
            <p class="stat-etiqueta">Ventas este mes</p>
            <p class="stat-valor">$<?= number_format($ventasMes, 2) ?></p>
          </div>
          <span class="stat-badge neutro">Mes</span>
        </div>

        <div class="tarjeta-stat">
          <div class="stat-icono naranja">
            <i class="fa-solid fa-triangle-exclamation"></i>
          </div>
          <div class="stat-info">
            <p class="stat-etiqueta">Stock bajo/Sin stock</p>
            <p class="stat-valor"><?= $stockBajo ?></p>
          </div>
          <span class="stat-badge alerta">Alerta</span>
        </div>

      </div>

      
      <div class="fila-media">
<div class="card-grafica">
          <div class="card-header-custom">
            <h3 class="card-titulo">Ventas de la semana</h3>
            <div class="filtros-grafica">
              <button class="filtro-btn activo" data-periodo="semana">Semana</button>
              <button class="filtro-btn" data-periodo="mes">Mes</button>
            </div>
          </div>
          <div class="grafica-wrapper">
            <canvas id="grafica-ventas"></canvas>
          </div>
        </div>
<div class="card-accesos">
          <div class="card-header-custom">
            <h3 class="card-titulo">Accesos rápidos</h3>
          </div>
          <div class="accesos-grid">
            <a href="../cajero/ventas.php" class="acceso-item">
              <div class="acceso-icono turquesa">
                <i class="fa-solid fa-cash-register"></i>
              </div>
              <span>Nueva venta</span>
            </a>
            <a href="../farmaceutico/productos.php" class="acceso-item">
              <div class="acceso-icono azul">
                <i class="fa-solid fa-pills"></i>
              </div>
              <span>Productos</span>
            </a>
            <a href="../farmaceutico/entradas.php" class="acceso-item">
              <div class="acceso-icono verde">
                <i class="fa-solid fa-truck-ramp-box"></i>
              </div>
              <span>Compras</span>
            </a>
            <a href="../cajero/clientes.php" class="acceso-item">
              <div class="acceso-icono morado">
                <i class="fa-solid fa-users"></i>
              </div>
              <span>Clientes</span>
            </a>
            <a href="reportes.php" class="acceso-item">
              <div class="acceso-icono naranja">
                <i class="fa-solid fa-chart-bar"></i>
              </div>
              <span>Reportes</span>
            </a>
            <a href="../cajero/cierre_dia.php" class="acceso-item">
              <div class="acceso-icono rojo">
                <i class="fa-solid fa-clipboard-check"></i>
              </div>
              <span>Cierre del día</span>
            </a>
            <a href="../farmaceutico/entradas.php" class="acceso-item">
              <div class="acceso-icono verde-oscuro">
                <i class="fa-solid fa-arrow-down-to-bracket"></i>
              </div>
              <span>Registrar entrada</span>
            </a>
            <a href="../farmaceutico/salidas.php" class="acceso-item">
              <div class="acceso-icono naranja-oscuro">
                <i class="fa-solid fa-arrow-up-from-bracket"></i>
              </div>
              <span>Registrar salida</span>
            </a>
          </div>
        </div>

      </div>

      
      <div class="fila-inferior">
<div class="card-tabla">
          <div class="card-header-custom">
            <h3 class="card-titulo">Últimas ventas</h3>
            <a href="../cajero/ventas.php" class="link-ver-todo">Ver todo</a>
          </div>
          <div class="tabla-wrapper">
            <table class="tabla-custom">
              <thead>
                <tr>
                  <th>Nota</th>
                  <th>Fecha</th>
                  <th>Cliente</th>
                  <th>Total</th>
                  <th>Estado</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($ultimasVentas)): ?>
                <tr>
                  <td colspan="5" class="tabla-vacia">Sin datos aún</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($ultimasVentas as $v): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($v['numero_venta']) ?></strong></td>
                        <td><?= date('d/m/Y H:i', strtotime($v['fecha_venta'])) ?></td>
                        <td><?= htmlspecialchars($v['cliente_nombre'] ?? 'Mostrador') ?></td>
                        <td><strong>$<?= number_format($v['total'], 2) ?></strong></td>
                        <td><span class="chip <?= $v['estado'] == 'cancelada' ? 'sin-stock' : 'en-stock' ?>"><?= ucfirst(htmlspecialchars($v['estado'])) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
<div class="card-tabla">
          <div class="card-header-custom">
            <h3 class="card-titulo">Stock bajo</h3>
            <a href="../farmaceutico/productos.php" class="link-ver-todo">Ver inventario</a>
          </div>
          <div class="tabla-wrapper">
            <table class="tabla-custom">
              <thead>
                <tr>
                  <th>Código</th>
                  <th>Producto</th>
                  <th>Existencia</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($productosStockBajo)): ?>
                <tr>
                  <td colspan="3" class="tabla-vacia">Sin alertas</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($productosStockBajo as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['codigo']) ?></td>
                        <td>
                          <div style="display:flex;align-items:center;gap:10px;">
                              <?php if(!empty($p['imagen_path'])): ?>
                                  <img src="../<?= htmlspecialchars($p['imagen_path']) ?>" alt="img" style="width:24px;height:24px;border-radius:4px;object-fit:cover;border:1px solid #ddd;"/>
                              <?php else: ?>
                                  <div style="width:24px;height:24px;border-radius:4px;background:var(--color-border);display:flex;justify-content:center;align-items:center;color:white;font-size:0.6rem;"><i class="fa-solid fa-pills"></i></div>
                              <?php endif; ?>
                              <span><?= htmlspecialchars($p['nombre']) ?></span>
                          </div>
                        </td>
                        <td><span style="color:var(--color-danger);font-weight:bold;"><?= $p['stock_actual'] ?></span> (Mín: <?= $p['stock_minimo'] ?>)</td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>

    
</main>

<?php include '../includes/footer.php'; ?>
