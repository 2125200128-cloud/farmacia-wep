<?php
require_once '../includes/validar_sesion.php';
require_once '../includes/config.php';
$paginaTitulo = 'Sistema';
$menuActivo = 'ventas';

// Filtros básicos
$busqueda = sanitize($_GET['busqueda'] ?? '');
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';
$metodo_filtro = sanitize($_GET['metodo_filtro'] ?? '');

// Construir query principal
$sql = "SELECT v.*, u.nombre as vendedor, c.nombre as cliente_nombre 
        FROM ventas v 
        LEFT JOIN usuarios u ON v.usuario_id = u.id 
        LEFT JOIN clientes c ON v.cliente_id = c.id 
        WHERE 1=1";
$params = [];

// Privacidad: Cajero solo ve sus ventas
if ($_SESSION['rol'] === 'cajero') {
    $sql .= " AND v.usuario_id = :usuario_actual_id";
    $params[':usuario_actual_id'] = $_SESSION['usuario_id'];
}

if ($busqueda) {
    $sql .= " AND (v.numero_venta LIKE :busqueda OR c.nombre LIKE :busqueda)";
    $params[':busqueda'] = "%$busqueda%";
}
if ($fecha_inicio) {
    $sql .= " AND DATE(v.fecha_venta) >= :fecha_inicio";
    $params[':fecha_inicio'] = $fecha_inicio;
}
if ($fecha_fin) {
    $sql .= " AND DATE(v.fecha_venta) <= :fecha_fin";
    $params[':fecha_fin'] = $fecha_fin;
}
if ($metodo_filtro) {
    $sql .= " AND v.metodo_pago = :metodo_filtro";
    $params[':metodo_filtro'] = $metodo_filtro;
}

$sql .= " ORDER BY v.fecha_venta DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ventasFiltradas = $stmt->fetchAll();

// Calcular totales de estas ventas
$totalMontoPeriodo = 0;
foreach ($ventasFiltradas as $v) {
    $totalMontoPeriodo += $v['total'];
}

// Resumen general superior — prepared statements
$hoy = date('Y-m-d');
$stmtHoyQ = $pdo->prepare("SELECT COUNT(*) as num, SUM(total) as total FROM ventas WHERE DATE(fecha_venta) = ?");
$stmtHoyQ->execute([$hoy]);
$statsHoy = $stmtHoyQ->fetch();

$inicioSemana = date('Y-m-d', strtotime('monday this week'));
$stmtSemQ = $pdo->prepare("SELECT SUM(total) as total FROM ventas WHERE DATE(fecha_venta) >= ?");
$stmtSemQ->execute([$inicioSemana]);
$statsSemana = $stmtSemQ->fetch();

$inicioMes = date('Y-m-01');
$stmtMesQ = $pdo->prepare("SELECT SUM(total) as total FROM ventas WHERE DATE(fecha_venta) >= ?");
$stmtMesQ->execute([$inicioMes]);
$statsMes = $stmtMesQ->fetch();

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<main class="contenido-main">

      <div class="pagina-header">
        <div>
          <h2 class="pagina-titulo-inner">Control de Ventas (PHP)</h2>
          <p class="pagina-subtitulo">Historial y seguimiento de ventas</p>
        </div>
        <div class="acciones-header">
          <a href="cierre_dia.php" class="btn-secundario">
            <i class="fa-solid fa-clipboard-check"></i>
            Cierre del día
          </a>
          <a href="nueva_venta.php" class="btn-primario">
            <i class="fa-solid fa-plus"></i>
            Nueva venta
          </a>
        </div>
      </div>
      <?php if ($_SESSION['rol'] !== 'cajero'): ?>
      <div class="tarjetas-ventas">
        <div class="tarjeta-venta-stat">
          <div class="venta-icono turquesa">
            <i class="fa-solid fa-coins"></i>
          </div>
          <div>
            <p class="venta-etiqueta">Total hoy</p>
            <p class="venta-valor">$<?= number_format($statsHoy['total'] ?? 0, 2) ?></p>
          </div>
        </div>
        <div class="tarjeta-venta-stat">
          <div class="venta-icono azul">
            <i class="fa-solid fa-receipt"></i>
          </div>
          <div>
            <p class="venta-etiqueta">Ventas hoy</p>
            <p class="venta-valor"><?= $statsHoy['num'] ?? 0 ?></p>
          </div>
        </div>
        <div class="tarjeta-venta-stat">
          <div class="venta-icono verde">
            <i class="fa-solid fa-calendar-week"></i>
          </div>
          <div>
            <p class="venta-etiqueta">Total semana</p>
            <p class="venta-valor">$<?= number_format($statsSemana['total'] ?? 0, 2) ?></p>
          </div>
        </div>
        <div class="tarjeta-venta-stat">
          <div class="venta-icono naranja">
            <i class="fa-solid fa-calendar-days"></i>
          </div>
          <div>
            <p class="venta-etiqueta">Total mes</p>
            <p class="venta-valor">$<?= number_format($statsMes['total'] ?? 0, 2) ?></p>
          </div>
        </div>
      </div>
      <?php endif; ?>
      <div class="card-ventas">
        <form method="GET" class="filtros-ventas">
          <div class="buscador-tabla">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="busqueda" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Buscar por nota, cliente..." />
          </div>
          <div class="filtros-derecha">
            <div class="filtro-fecha">
              <i class="fa-regular fa-calendar"></i>
              <input type="date" name="fecha_inicio" value="<?= $fecha_inicio ?>" title="Fecha inicio" />
              <span>—</span>
              <input type="date" name="fecha_fin" value="<?= $fecha_fin ?>" title="Fecha fin" />
            </div>

            <select class="select-filtro" name="metodo_filtro" style="padding:8px; border:1px solid #cbd5e1; border-radius:6px; background:#fff; color:#475569; font-size:0.9rem;">
                <option value="">Todas las ventas</option>
                <option value="credito" <?= $metodo_filtro === 'credito' ? 'selected' : '' ?>>Solo fiadas / crédito</option>
                <option value="efectivo" <?= $metodo_filtro === 'efectivo' ? 'selected' : '' ?>>Solo pagadas</option>
            </select>

            <button type="submit" class="btn-secundario" style="margin-right: 10px; padding: 10px;">
              Filtrar
            </button>
            <a href="ventas.php" class="btn-secundario" style="margin-right: 10px; padding: 10px; text-decoration: none;">
              Limpiar
            </a>
            
            <button type="button" class="btn-exportar">
              <i class="fa-solid fa-file-export"></i>
              Exportar
            </button>
          </div>
        </form>
        
        <div class="tabla-wrapper" style="max-height: 400px; overflow-y: auto; border: 1px solid var(--color-border); border-radius: 8px;">
          <table class="tabla-ventas">
            <thead>
              <tr>
                <th>Nota</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Vendedor</th>
                <th>Estado</th>
                <th>Subtotal</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($ventasFiltradas)): ?>
              <tr>
                <td colspan="7" class="tabla-vacia">
                  <i class="fa-solid fa-receipt"></i>
                  <p>Sin ventas registradas</p>
                </td>
              </tr>
              <?php else: ?>
                <?php foreach ($ventasFiltradas as $venta): ?>
                <?php $bgRow = $venta['metodo_pago'] === 'credito' ? 'background-color:#fef9c3;' : ''; ?>
                <tr style="<?= $bgRow ?>">
                  <td><strong><?= htmlspecialchars($venta['numero_venta']) ?></strong></td>
                  <td><?= date('d/m/Y H:i', strtotime($venta['fecha_venta'])) ?></td>
                  <td>
                      <?= htmlspecialchars($venta['cliente_nombre'] ?? 'Mostrador') ?>
                      <?php if($venta['metodo_pago'] === 'credito'): ?>
                          <span style="background:#f97316; color:white; padding:2px 6px; border-radius:4px; font-size:0.75rem; font-weight:bold; margin-left:5px;">Fiado</span>
                      <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($venta['vendedor'] ?? '-') ?></td>
                  <td><span class="chip <?= $venta['estado'] == 'cancelada' ? 'sin-stock' : 'en-stock'?>"><?= ucfirst(htmlspecialchars($venta['estado'])) ?></span></td>
                  <td>$<?= number_format($venta['subtotal'], 2) ?></td>
                  <td><strong>$<?= number_format($venta['total'], 2) ?></strong></td>
                </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        
        <div class="totales-tabla">
          <div class="total-item">
            <span class="total-label">Ventas mostradas:</span>
            <span class="total-num"><?= count($ventasFiltradas) ?></span>
          </div>
          <div class="total-item">
            <span class="total-label">Total del período:</span>
            <span class="total-num destacado">$<?= number_format($totalMontoPeriodo, 2) ?></span>
          </div>
        </div>
      </div>

    
</main>

<?php include '../includes/footer.php'; ?>
