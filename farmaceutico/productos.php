<?php
require_once '../includes/validar_sesion.php';
require_once '../includes/config.php';
$paginaTitulo = 'Productos';
$menuActivo = 'productos';

// Filtros
$busqueda = sanitize($_GET['busqueda'] ?? '');
$categoria = sanitize($_GET['categoria'] ?? '');
$estadoFiltro = sanitize($_GET['estado'] ?? '');

$sql = "SELECT p.*, c.nombre as categoria_nombre 
        FROM productos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id 
        WHERE p.estado != 'inactivo'";
$params = [];

if ($busqueda) {
    $sql .= " AND (p.nombre LIKE :busqueda OR p.codigo LIKE :busqueda)";
    $params[':busqueda'] = "%$busqueda%";
}
if ($categoria) {
    $sql .= " AND p.categoria_id = :categoria";
    $params[':categoria'] = $categoria;
}

$sql .= " ORDER BY p.nombre ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productosRaw = $stmt->fetchAll();

// Filtrar en PHP por el estado derivado
$productos = [];
$totalProd = count($productosRaw);
$activos = 0;
$stockBajo = 0;
$sinStock = 0;

foreach ($productosRaw as $p) {
    if ($p['stock_actual'] <= 0) {
        $estadoCalc = 'sin-stock';
        $sinStock++;
    } elseif ($p['stock_actual'] <= $p['stock_minimo']) {
        $estadoCalc = 'stock-bajo';
        $stockBajo++;
    } else {
        $estadoCalc = 'activo';
        $activos++;
    }
    $p['estado_calc'] = $estadoCalc;
    
    if ($estadoFiltro && $estadoCalc != $estadoFiltro) {
        continue;
    }
    
    $productos[] = $p;
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<main class="contenido-main">

<?php if(isset($_SESSION['exito'])): ?>
<div style="background: #10b981; color: white; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
    <i class="fa-solid fa-check-circle"></i>
    <span><?php echo $_SESSION['exito']; unset($_SESSION['exito']); ?></span>
</div>
<?php endif; ?>

<div class="pagina-header">
        <div>
          <h2 class="pagina-titulo-inner">Productos (PHP)</h2>
        <p class="pagina-subtitulo">Catálogo de medicamentos y productos</p>
        </div>
        <!-- Botón movido por instrucción -->
      </div>
<div class="tarjetas-productos">
        <div class="tarjeta-prod-stat">
          <div class="prod-icono turquesa">
            <i class="fa-solid fa-pills"></i>
          </div>
          <div>
            <p class="prod-etiqueta">Total productos</p>
            <p class="prod-valor"><?= $totalProd ?></p>
          </div>
        </div>
        <div class="tarjeta-prod-stat">
          <div class="prod-icono verde">
            <i class="fa-solid fa-check-circle"></i>
          </div>
          <div>
            <p class="prod-etiqueta">Activos</p>
            <p class="prod-valor"><?= $activos ?></p>
          </div>
        </div>
        <div class="tarjeta-prod-stat">
          <div class="prod-icono amarillo">
            <i class="fa-solid fa-triangle-exclamation"></i>
          </div>
          <div>
            <p class="prod-etiqueta">Stock bajo</p>
            <p class="prod-valor"><?= $stockBajo ?></p>
          </div>
        </div>
        <div class="tarjeta-prod-stat">
          <div class="prod-icono rojo">
            <i class="fa-solid fa-ban"></i>
          </div>
          <div>
            <p class="prod-etiqueta">Sin stock</p>
            <p class="prod-valor"><?= $sinStock ?></p>
          </div>
        </div>
      </div>
<?php
$stmtAlerta = $pdo->query("
    SELECT id, codigo, nombre, stock_actual, stock_minimo 
    FROM productos 
    WHERE estado = 'activo' AND stock_actual <= stock_minimo
    ORDER BY stock_actual ASC
");
$productosAlerta = $stmtAlerta->fetchAll();
?>
<?php if (!empty($productosAlerta)): ?>
<div style="background: #fef9c3; border-left: 4px solid #eab308; color: #854d0e; padding: 12px 16px; border-radius: 4px; margin-bottom: 20px;">
    <h3 style="margin-top: 0; display: flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-triangle-exclamation"></i> 
        Alerta de Inventario (Stock Bajo)
    </h3>
    <ul style="margin-bottom: 0; padding-left: 20px;">
        <?php foreach ($productosAlerta as $pa): ?>
        <li>
            <strong><?= htmlspecialchars($pa['nombre']) ?></strong> 
            (Actual: <?= $pa['stock_actual'] ?> / Mínimo: <?= $pa['stock_minimo'] ?>)
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="card-productos">
<form method="GET" class="filtros-productos">
          <div class="buscador-tabla">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="busqueda" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Buscar por nombre o código..."/>
          </div>
          <div class="filtros-derecha">
            <select class="select-filtro" name="categoria">
              <option value="">Todas las categorías</option>
              <option value="analgesico" <?= $categoria=='analgesico'?'selected':'' ?>>Analgésico</option>
              <option value="antibiotico" <?= $categoria=='antibiotico'?'selected':'' ?>>Antibiótico</option>
              <option value="antiinflamatorio" <?= $categoria=='antiinflamatorio'?'selected':'' ?>>Antiinflamatorio</option>
              <option value="vitamina" <?= $categoria=='vitamina'?'selected':'' ?>>Vitamina</option>
              <option value="otro" <?= $categoria=='otro'?'selected':'' ?>>Otro</option>
            </select>
            <select class="select-filtro" name="estado">
              <option value="">Todos los estados</option>
              <option value="activo" <?= $estadoFiltro=='activo'?'selected':'' ?>>Activo</option>
              <option value="stock-bajo" <?= $estadoFiltro=='stock-bajo'?'selected':'' ?>>Stock bajo</option>
              <option value="sin-stock" <?= $estadoFiltro=='sin-stock'?'selected':'' ?>>Sin stock</option>
            </select>
            <button type="submit" class="btn-secundario" style="margin-right:10px; padding:10px;">Filtrar</button>
            <a href="productos.php" class="btn-secundario" style="padding:10px; text-decoration:none;">Limpiar</a>
          </div>
        </form>
<div class="tabla-wrapper">
          <table class="tabla-productos">
            <thead>
              <tr>
                <th>Producto</th>
                <th>Categoría</th>
                <th class="text-right">Precio</th>
                <th class="text-center">Stock</th>
                <th class="text-center">Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($productos)): ?>
              <tr>
                <td colspan="9" class="tabla-vacia">
                  <i class="fa-solid fa-pills"></i>
                  <p>Sin productos registrados</p>
                </td>
              </tr>
              <?php else: ?>
                <?php foreach ($productos as $p): ?>
                <tr>
                  <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <?php if(!empty($p['imagen_path'])): ?>
                            <img src="../<?= htmlspecialchars($p['imagen_path']) ?>" alt="<?= htmlspecialchars($p['nombre']) ?>" style="width:40px;height:40px;object-fit:cover;border-radius:6px;border:1px solid var(--color-border);"/>
                        <?php else: ?>
                            <div style="width:40px;height:40px;border-radius:6px;background:var(--color-border);display:flex;align-items:center;justify-content:center;color:white;">
                                <i class="fa-solid fa-pills"></i>
                            </div>
                        <?php endif; ?>
                        <div>
                            <strong><?= htmlspecialchars($p['nombre']) ?></strong>
                            <div style="font-size:0.8rem;color:var(--text-muted);"><?= htmlspecialchars($p['codigo']) ?></div>
                        </div>
                    </div>
                  </td>
                  <td><?= htmlspecialchars($p['categoria_nombre'] ?? 'Sin Categoría') ?></td>
                  <td class="text-right">$<?= number_format($p['precio_venta'], 2) ?></td>
                  <td class="text-center"><strong><?= $p['stock_actual'] ?></strong></td>
                  <td class="text-center">
                      <span class="chip <?= $p['estado_calc'] == 'activo' ? 'en-stock' : $p['estado_calc'] ?>">
                          <?= ucfirst($p['estado_calc'] == 'activo' ? 'Activo' : str_replace('-', ' ', $p['estado_calc'])) ?>
                      </span>
                  </td>
                  <td>
                      <div class="acciones-fila" style="display:flex; gap:5px;">
                          <a href="editar_producto.php?id=<?= $p['id'] ?>" class="btn-accion" title="Editar"><i class="fa-solid fa-pen"></i></a>
                          <a href="eliminar_producto.php?id=<?= $p['id'] ?>" class="btn-accion" title="Eliminar" style="color:red;" onclick="return confirm('¿Eliminar producto?')"><i class="fa-solid fa-trash"></i></a>
                      </div>
                  </td>
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
