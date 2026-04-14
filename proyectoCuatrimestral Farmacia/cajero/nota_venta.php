<?php
require_once '../includes/validar_sesion.php';
require_once '../includes/config.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ventas.php');
    exit;
}

$stmtV = $pdo->prepare("
    SELECT v.*,
           u.nombre AS vendedor_nombre,
           c.nombre AS cliente_nombre,
           c.rfc    AS cliente_rfc,
           c.telefono AS cliente_telefono,
           c.direccion AS cliente_direccion
    FROM ventas v
    LEFT JOIN usuarios u ON v.usuario_id = u.id
    LEFT JOIN clientes c ON v.cliente_id = c.id
    WHERE v.id = :id
");
$stmtV->execute([':id' => $id]);
$venta = $stmtV->fetch();

if (!$venta) {
    header('Location: ventas.php');
    exit;
}

$stmtD = $pdo->prepare("
    SELECT dv.cantidad, dv.precio_unitario, dv.subtotal,
           p.nombre AS producto_nombre, p.codigo
    FROM detalle_ventas dv
    JOIN productos p ON dv.producto_id = p.id
    WHERE dv.venta_id = :id
");
$stmtD->execute([':id' => $id]);
$detalles = $stmtD->fetchAll();
$paginaTitulo = 'Nota de Venta';
$menuActivo = 'ventas';
include '../includes/sidebar.php';
include '../includes/header.php';
?>

<style>
/* === ESTILOS PARA IMPRESORA TÉRMICA 80mm === */
@media print {
  /* Ocultar elementos no imprimibles */
  aside.sidebar,
  header,
  .pagina-header,
  .panel-acciones-nota,
  .th-codigo,
  .td-codigo {
    display: none !important;
  }

  /* Contenedor principal de la nota */
  body, main.contenido-main {
    margin: 0 !important;
    padding: 0 !important;
  }

  .layout-nota {
    display: block !important;
    width: 100% !important;
    margin: 0 !important;
    padding: 0 !important;
  }

  .zona-imprimible {
    max-width: 72mm !important;
    width: 72mm !important;
    font-family: 'Courier New', Courier, monospace !important;
    font-size: 8.5pt !important;
    margin: 0 !important;
    padding: 2mm !important;
    background: white !important;
    color: black !important;
  }

  table.tabla-nota {
    width: 100% !important;
    border-collapse: collapse !important;
    font-size: 8pt !important;
  }

  table.tabla-nota th,
  table.tabla-nota td {
    padding: 1mm 0 !important;
    vertical-align: top !important;
  }

  .nota-encabezado, .nota-datos, .nota-totales, .nota-pie {
    margin-bottom: 2mm !important;
  }

  .nota-divisor {
    border-top: 1px dashed #000 !important;
    margin: 1mm 0 !important;
  }

  .nota-empresa {
    font-size: 11pt !important;
    font-weight: bold !important;
  }

  .nota-empresa-sub {
    font-size: 7pt !important;
  }

  .total-final {
    font-size: 10pt !important;
    font-weight: bold !important;
  }
}
</style>

<main class="contenido-main">

<div class="pagina-header">
        <div>
<h2 class="pagina-titulo-inner">Nota <span><?= htmlspecialchars($venta['numero_venta']) ?></span></h2>
          <p class="pagina-subtitulo"></p>
        </div>
        <div class="acciones-header">
          <a href="ventas.php" class="btn-volver">
            <i class="fa-solid fa-arrow-left"></i>
            Volver
          </a>
          <button class="btn-imprimir" onclick="window.print()">
            <i class="fa-solid fa-print"></i>
            Imprimir
          </button>
        </div>
      </div>

      
      <div class="layout-nota">

        
        <div class="zona-imprimible" id="zona-imprimible">
<div class="nota-encabezado">
            <div class="nota-logo">
              
              <div class="logo-nota-placeholder">
                <i class="fa-solid fa-capsules"></i>
              </div>
              <div>
                <p class="nota-empresa">FarmaControl</p>
                <p class="nota-empresa-sub">Sistema de Control de Farmacia</p>
              </div>
            </div>
            <div class="nota-folio">
              <p class="folio-label">Nota de Venta</p>
              <p class="folio-num"><span><?= htmlspecialchars($venta['numero_venta']) ?></span></p>
            </div>
          </div>

          <div class="nota-divisor"></div>
<div class="nota-datos">
            <div class="datos-col">
              <p class="datos-titulo">Datos de la venta</p>
              <div class="dato-fila">
                <span class="dato-label">Fecha:</span>
<span class="dato-valor"><span><?= date('d/m/Y H:i', strtotime($venta['fecha_venta'])) ?></span></span>
              </div>
              <div class="dato-fila">
                <span class="dato-label">Nota:</span>
                <span class="dato-valor"><span><?= htmlspecialchars($venta['numero_venta']) ?></span></span>
              </div>
              <div class="dato-fila">
                <span class="dato-label">Atendió:</span>
                <span class="dato-valor"><span><?= htmlspecialchars($venta['vendedor_nombre'] ?? '—') ?></span></span>
              </div>
            </div>
            <div class="datos-col">
              <p class="datos-titulo">Datos del cliente</p>
              <div class="dato-fila">
                <span class="dato-label">Código:</span>
                <span class="dato-valor" id="nota-cli-codigo">--</span>
              </div>
              <div class="dato-fila">
                <span class="dato-label">Nombre:</span>
                <span class="dato-valor"><span><?= htmlspecialchars($venta['cliente_nombre'] ?? 'Público general') ?></span></span>
              </div>
              <div class="dato-fila">
                <span class="dato-label">RFC:</span>
                <span class="dato-valor"><span><?= htmlspecialchars($venta['cliente_rfc'] ?? '—') ?></span></span>
              </div>
              <div class="dato-fila">
                <span class="dato-label">Teléfono:</span>
                <span class="dato-valor"><span><?= htmlspecialchars($venta['cliente_telefono'] ?? '—') ?></span></span>
              </div>
              <div class="dato-fila">
                <span class="dato-label">Dirección:</span>
                <span class="dato-valor"><span><?= htmlspecialchars($venta['cliente_direccion'] ?? '—') ?></span></span>
              </div>
            </div>
          </div>

          <div class="nota-divisor"></div>
<table class="tabla-nota">
            <thead>
              <tr>
                <th class="th-codigo">Código</th>
                <th>Producto</th>
                <th class="text-right">Precio unit.</th>
                <th class="text-center">Cantidad</th>
                <th class="text-right">Subtotal</th>
              </tr>
            </thead>
            <tbody>
<?php if (empty($detalles)): ?>
    <tr>
        <td colspan="5" class="tabla-vacia">Sin productos</td>
    </tr>
<?php else: ?>
    <?php foreach ($detalles as $d): ?>
    <tr>
        <td class="td-codigo"><?= htmlspecialchars($d['codigo']) ?></td>
        <td><?= htmlspecialchars($d['producto_nombre']) ?></td>
        <td class="text-right">$<?= number_format($d['precio_unitario'], 2) ?></td>
        <td class="text-center"><?= intval($d['cantidad']) ?></td>
        <td class="text-right">$<?= number_format($d['subtotal'], 2) ?></td>
    </tr>
    <?php endforeach; ?>
<?php endif; ?>
</tbody>
          </table>

          <div class="nota-divisor"></div>
          <div class="nota-totales">
            <div class="total-fila">
              <span>Subtotal</span>
<span>$<?= number_format($venta['subtotal'], 2) ?></span>
            </div>
            <div class="total-fila">
              <span>IVA (16%)</span>
              <span>$<?= number_format($venta['iva'], 2) ?></span>
            </div>
            <!-- Sección de descuento (visible solo si aplica) -->
            <?php if (floatval($venta['descuento'] ?? 0) > 0): ?>
            <div style="display:flex; justify-content:space-between; background:#f0fdf4; border:1px dashed #16a34a; border-radius:4px; padding:4px 8px; margin:4px 0; font-size:0.85rem;">
                <span style="color:#15803d;">🎉 Descuento aplicado</span>
                <span style="color:#15803d; font-weight:bold;">-$<?= number_format($venta['descuento'], 2) ?></span>
            </div>
            <?php endif; ?>
            <div class="total-fila total-final">
              <span>Total a pagar</span>
              <span>$<?= number_format($venta['total'], 2) ?></span>
            </div>
          </div>

          <div class="nota-divisor"></div>
<div class="nota-pie">
            <p>Gracias por su compra</p>
            <p class="nota-pie-sub">Conserve esta nota como comprobante de su compra.</p>
          </div>

        </div>

        
        <div class="panel-acciones-nota">

          <div class="card-acciones">
            <h3 class="acciones-titulo">Acciones</h3>

            <button class="btn-accion-nota imprimir" onclick="window.print()">
              <i class="fa-solid fa-print"></i>
              Imprimir nota
            </button>

            <a href="nueva_venta.php" class="btn-accion-nota nueva">
              <i class="fa-solid fa-plus"></i>
              Nueva venta
            </a>

            <a href="ventas.php" class="btn-accion-nota volver">
              <i class="fa-solid fa-list"></i>
              Ver todas las ventas
            </a>
          </div>
<div class="card-resumen-nota">
            <p class="resumen-nota-label">Total de esta venta</p>
            <p class="resumen-nota-total"><span>$<?= number_format($venta['total'], 2) ?></span></p>
            <div class="resumen-nota-dato">
              <span>Productos</span>
              <span><?= count($detalles) ?></span>
            </div>
            <div class="resumen-nota-dato">
              <span>Fecha</span>
              <span><?= date('d/m/Y', strtotime($venta['fecha_venta'])) ?></span>
            </div>
            <div class="resumen-nota-dato">
              <span>Cliente</span>
              <span><?= htmlspecialchars($venta['cliente_nombre'] ?? 'Público general') ?></span>
            </div>
          </div>

        </div>

      </div>

    
</main>

<?php include '../includes/footer.php'; ?>
