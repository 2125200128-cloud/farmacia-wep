<?php
require_once '../includes/validar_sesion.php';
require_once '../includes/config.php';
$paginaTitulo = 'Inventario';
$menuActivo = 'inventario';

// Recibir filtros
$buscar = $_GET['buscar'] ?? '';
$filtroStock = $_GET['filtro_stock'] ?? '';

// Construir query de productos
$sql = "SELECT id, codigo, nombre, precio_venta, stock_actual, stock_minimo, imagen_path FROM productos WHERE 1=1";
$params = [];

if ($buscar !== '') {
    $sql .= " AND (nombre LIKE ? OR codigo LIKE ?)";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

// Filtro stock (calculado en BD)
if ($filtroStock === 'en-stock') {
    $sql .= " AND stock_actual > stock_minimo";
} elseif ($filtroStock === 'stock-bajo') {
    $sql .= " AND stock_actual <= stock_minimo AND stock_actual > 0";
} elseif ($filtroStock === 'sin-stock') {
    $sql .= " AND stock_actual <= 0";
}

$sql .= " ORDER BY nombre ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();

// Calcular estadísticas globales
$statsStmt = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN stock_actual > stock_minimo THEN 1 ELSE 0 END) as en_stock,
    SUM(CASE WHEN stock_actual <= stock_minimo AND stock_actual > 0 THEN 1 ELSE 0 END) as stock_bajo,
    SUM(CASE WHEN stock_actual <= 0 THEN 1 ELSE 0 END) as sin_stock
    FROM productos
");
$stats = $statsStmt->fetch();

include '../includes/sidebar.php';
include '../includes/header.php';
?>

<main class="contenido-main">

<div class="pagina-header">
        <div>
          <h2 class="pagina-titulo-inner">Control de Inventario (PHP)</h2>
          <p class="pagina-subtitulo">Entradas, salidas y existencias actuales</p>
        </div>
        <div class="acciones-header">
          <button class="btn-exportar-enc" onclick="window.print()">
            <i class="fa-solid fa-file-export"></i>
            Exportar / Imprimir
          </button>
        </div>
      </div>
<div class="tarjetas-inv">
        <div class="tarjeta-inv">
          <div class="inv-icono turquesa">
            <i class="fa-solid fa-boxes-stacked"></i>
          </div>
          <div>
            <p class="inv-etiqueta">Total productos</p>
            <p class="inv-valor"><?= $stats['total'] ?></p>
          </div>
        </div>
        <div class="tarjeta-inv">
          <div class="inv-icono verde">
            <i class="fa-solid fa-circle-check"></i>
          </div>
          <div>
            <p class="inv-etiqueta">En stock</p>
            <p class="inv-valor"><?= $stats['en_stock'] ?></p>
          </div>
        </div>
        <div class="tarjeta-inv">
          <div class="inv-icono naranja">
            <i class="fa-solid fa-triangle-exclamation"></i>
          </div>
          <div>
            <p class="inv-etiqueta">Stock bajo</p>
            <p class="inv-valor"><?= $stats['stock_bajo'] ?></p>
          </div>
        </div>
        <div class="tarjeta-inv">
          <div class="inv-icono rojo">
            <i class="fa-solid fa-ban"></i>
          </div>
          <div>
            <p class="inv-etiqueta">Sin stock</p>
            <p class="inv-valor"><?= $stats['sin_stock'] ?></p>
          </div>
        </div>
      </div>
<div class="card-inv">

        <form class="filtros-inv" method="GET">
          <div class="buscador-tabla">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="buscar" value="<?= htmlspecialchars($buscar) ?>" placeholder="Buscar por nombre o código..."/>
          </div>
          <div class="filtros-derecha" style="display:flex; gap: 10px;">
            <select class="select-filtro" name="filtro_stock">
              <option value="">Todos</option>
              <option value="en-stock" <?= $filtroStock=='en-stock'?'selected':'' ?>>En stock</option>
              <option value="stock-bajo" <?= $filtroStock=='stock-bajo'?'selected':'' ?>>Stock bajo</option>
              <option value="sin-stock" <?= $filtroStock=='sin-stock'?'selected':'' ?>>Sin stock</option>
            </select>
            <button type="submit" class="btn-guardar" style="padding: 0 15px;">Filtrar</button>
          </div>
        </form>

        <div class="tabla-wrapper">
          <table class="tabla-inv" id="tabla-inventario">
            <thead>
              <tr>
                <th>Código</th>
                <th>Producto</th>
                <th>Existencia actual</th>
                <th>Precio venta</th>
                <th>Estado</th>
              </tr>
            </thead>
            <tbody>
              <?php if(empty($productos)): ?>
              <tr>
                <td colspan="5" class="tabla-vacia">
                  <i class="fa-solid fa-box-open"></i>
                  <p>Sin productos registrados</p>
                </td>
              </tr>
              <?php else: ?>
                  <?php foreach($productos as $p): 
                      $estado = $p['stock_actual'] <= 0 ? 'sin-stock' : ($p['stock_actual'] <= $p['stock_minimo'] ? 'stock-bajo' : 'en-stock');
                      $textoEstado = ['en-stock' => 'En stock', 'stock-bajo' => 'Stock bajo', 'sin-stock' => 'Sin stock'][$estado];
                  ?>
                  <tr>
                      <td style="font-family: monospace; font-weight: bold; color: var(--color-primary);"><?= htmlspecialchars($p['codigo']) ?></td>
                      <td>
                          <div style="display:flex;align-items:center;gap:10px;">
                              <?php if(!empty($p['imagen_path'])): ?>
                                  <img src="../<?= htmlspecialchars($p['imagen_path']) ?>" alt="img" style="width:32px;height:32px;border-radius:6px;object-fit:cover;border:1px solid #ddd;"/>
                              <?php else: ?>
                                  <div style="background:var(--color-border);width:32px;height:32px;border-radius:6px;display:flex;align-items:center;justify-content:center;color:white;">
                                      <i class="fa-solid fa-pills"></i>
                                  </div>
                              <?php endif; ?>
                              <strong><?= htmlspecialchars($p['nombre']) ?></strong>
                          </div>
                      </td>
                      <td><strong><?= $p['stock_actual'] ?></strong></td>
                      <td>$<?= number_format($p['precio_venta'], 2) ?></td>
                      <td><span class="chip <?= $estado ?>"><?= $textoEstado ?></span></td>
                  </tr>
                  <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="paginacion">
          <p class="paginacion-info">Mostrando <?= count($productos) ?> productos</p>
        </div>

      </div>

    
</main>

<?php include '../includes/footer.php'; ?>
